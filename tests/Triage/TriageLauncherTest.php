<?php

namespace App\Tests\Triage;

use App\Entity\Experience;
use App\Message\TriageExperiences;
use App\Repository\ExperienceRepository;
use App\Tests\DatabaseTrait;
use App\Triage\TriageLauncher;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class TriageLauncherTest extends KernelTestCase
{
    use DatabaseTrait;

    private ExperienceRepository $repository;

    /** @var list<TriageExperiences> */
    private array $dispatched = [];

    protected function setUp(): void
    {
        $entityManager = $this->resetDatabase();
        $this->repository = self::getContainer()->get(ExperienceRepository::class);

        $entityManager->persist(new Experience(1, new \DateTimeImmutable('2025-12-31'), 'Trop ancienne pour être qualifiée.'));
        for ($i = 2; $i <= 8; ++$i) {
            $entityManager->persist(new Experience($i, new \DateTimeImmutable(\sprintf('2026-01-%02d', $i)), "Demande numéro $i."));
        }
        $entityManager->flush();
        $entityManager->clear();
    }

    public function testItQueuesTheMostRecentExperiencesInBatchesWithinTheCap(): void
    {
        $launcher = $this->createLauncher(limit: 5, batchSize: 2);

        self::assertSame(5, $launcher->launch());

        self::assertSame([[8, 7], [6, 5], [4]], array_map(static fn (TriageExperiences $m): array => $m->experienceIds, $this->dispatched));
        self::assertSame(5, $this->repository->countTriageRequested());
        self::assertSame(0, $launcher->getRemainingBudget());
    }

    public function testLaunchingAgainNeverGoesOverTheCapNorQueuesAnExperienceTwice(): void
    {
        $launcher = $this->createLauncher(limit: 5, batchSize: 10);
        $launcher->launch();

        self::assertSame(0, $launcher->launch());
        self::assertCount(1, $this->dispatched);
    }

    public function testWithoutACapItTakesEverythingSinceTheGivenDay(): void
    {
        $launcher = $this->createLauncher(limit: 0, batchSize: 10);

        self::assertNull($launcher->getRemainingBudget());
        self::assertSame(7, $launcher->launch());
        self::assertSame([[8, 7, 6, 5, 4, 3, 2]], array_map(static fn (TriageExperiences $m): array => $m->experienceIds, $this->dispatched));
    }

    private function createLauncher(int $limit, int $batchSize): TriageLauncher
    {
        $bus = new class($this->dispatched) implements MessageBusInterface {
            /** @param list<TriageExperiences> $dispatched */
            public function __construct(private array &$dispatched)
            {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message;

                return new Envelope($message);
            }
        };

        return new TriageLauncher($this->repository, $bus, new MockClock('2026-09-19 10:00:00'), '2026-01-01', $limit, $batchSize);
    }
}
