<?php

namespace App\Tests\MessageHandler;

use App\Entity\Experience;
use App\Message\TriageExperiences;
use App\MessageHandler\TriageExperiencesHandler;
use App\Repository\ExperienceRepository;
use App\Tests\DatabaseTrait;
use App\Tests\Triage\JevResponses;
use App\Triage\ExperienceTriage;
use App\Triage\Intention;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class TriageExperiencesHandlerTest extends KernelTestCase
{
    use DatabaseTrait;

    private EntityManagerInterface $entityManager;
    private ExperienceRepository $repository;
    private RateLimiterFactory $limiter;

    protected function setUp(): void
    {
        $this->entityManager = $this->resetDatabase();
        $this->repository = self::getContainer()->get(ExperienceRepository::class);
        $this->limiter = new RateLimiterFactory(['id' => 'jev', 'policy' => 'fixed_window', 'limit' => 100, 'interval' => '1 minute'], new InMemoryStorage());

        $this->entityManager->persist(new Experience(1, new \DateTimeImmutable('2026-02-01'), 'Impossible de payer ma carte grise, le site plante.'));
        $this->entityManager->persist(new Experience(2, new \DateTimeImmutable('2026-02-02'), 'Celle-ci tombe pendant une panne de Jev.'));
        $this->entityManager->persist(new Experience(3, new \DateTimeImmutable('2026-02-03'), 'Jamais demandée : elle ne doit pas être envoyée à Jev.'));
        $this->entityManager->flush();
        $this->repository->markTriageRequested([1, 2], new \DateTimeImmutable('2026-09-19 10:00:00'));
        $this->entityManager->clear();
    }

    public function testItStoresWhatJevAnswered(): void
    {
        $jev = new JevResponses(static fn (): MockResponse => JevResponses::answer('debloquer', 0.9, 2.2, 0.95));

        $this->createHandler($jev)(new TriageExperiences([1]));
        $this->entityManager->clear();

        $experience = $this->repository->find(1);
        self::assertTrue($experience->isTriaged());
        self::assertSame(Intention::Unblock, $experience->getIntention());
        self::assertSame(2.2, $experience->getPriority());
        self::assertSame(0.95, $experience->getBugProbability());
        self::assertSame('2026-09-19 10:00:05', $experience->getTriagedAt()->format('Y-m-d H:i:s'));
    }

    public function testItOnlySendsToJevWhatStillAwaitsATriage(): void
    {
        $jev = new JevResponses(static fn (): MockResponse => JevResponses::answer('information', 0.7, 1.0, 0.1));
        $handler = $this->createHandler($jev);

        $handler(new TriageExperiences([1, 3]));
        $handler(new TriageExperiences([1, 3]));

        self::assertCount(1, $jev->requests, 'Neither the experience nobody asked about, nor the one already triaged, costs a call.');
    }

    public function testEveryRequestToJevCountsAgainstTheRateLimit(): void
    {
        $jev = new JevResponses(static fn (): MockResponse => JevResponses::answer('information', 0.7, 1.0, 0.1));

        // Experience 3 was never requested: it is not sent to Jev, so it must not use up the budget either
        $this->createHandler($jev)(new TriageExperiences([1, 2, 3]));

        self::assertCount(2, $jev->requests);
        self::assertSame(98, $this->limiter->create('triage')->consume(0)->getRemainingTokens());
    }

    public function testAPartialFailureKeepsWhatSucceededAndAsksForARetry(): void
    {
        $jev = new JevResponses(static fn (string $state): MockResponse => str_contains($state, 'panne')
            ? new MockResponse('{"detail": "Service unavailable"}', ['http_code' => 503])
            : JevResponses::answer('debloquer', 0.9, 2.2, 0.95));

        try {
            $this->createHandler($jev)(new TriageExperiences([1, 2]));
            self::fail('Messenger must be told to retry.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('1 of 2', $e->getMessage());
        }

        $this->entityManager->clear();
        self::assertTrue($this->repository->find(1)->isTriaged());
        self::assertTrue($this->repository->find(2)->awaitsTriage());
    }

    private function createHandler(JevResponses $jev): TriageExperiencesHandler
    {
        return new TriageExperiencesHandler(
            $this->repository,
            new ExperienceTriage($jev->createPlatform(), new NullLogger()),
            $this->entityManager,
            new MockClock('2026-09-19 10:00:05'),
            new NullLogger(),
            $this->limiter,
        );
    }
}
