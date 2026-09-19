<?php

namespace App\Tests\Repository;

use App\Entity\Experience;
use App\Inbox\InboxQuery;
use App\Inbox\InboxSort;
use App\Repository\ExperienceRepository;
use App\Tests\DatabaseTrait;
use App\Triage\Intention;
use App\Triage\TriageResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ExperienceRepositoryTest extends KernelTestCase
{
    use DatabaseTrait;

    private ExperienceRepository $repository;

    protected function setUp(): void
    {
        $entityManager = $this->resetDatabase();
        $this->repository = self::getContainer()->get(ExperienceRepository::class);

        $requestedAt = new \DateTimeImmutable('2026-09-19 10:00:00');
        $answers = [
            1 => new TriageResult(Intention::Unblock, 0.9, 2.6, 0.95, 500, 300),
            2 => new TriageResult(Intention::Unblock, 0.8, 1.2, 0.10, 450, 400),
            3 => new TriageResult(Intention::Thanks, 0.99, 0.0, 0.02, 380, 900),
        ];
        for ($id = 1; $id <= 5; ++$id) {
            $experience = new Experience($id, new \DateTimeImmutable(\sprintf('2026-03-%02d', $id)), "Demande numéro $id.");
            if ($id <= 4) {
                $experience->requestTriage($requestedAt);
            }
            if (isset($answers[$id])) {
                $experience->triage($answers[$id], $requestedAt->modify(\sprintf('+%d seconds', 2 * $id)));
            }
            $entityManager->persist($experience);
        }
        $entityManager->flush();
        $entityManager->clear();
    }

    public function testItReportsWhereTheTriageStands(): void
    {
        $progress = $this->repository->getTriageProgress();

        self::assertSame(4, $progress->requested);
        self::assertSame(3, $progress->triaged);
        self::assertSame(1, $progress->pending());
        self::assertTrue($progress->isRunning());
        self::assertSame(75, $progress->percent());
        self::assertSame(2, $progress->byIntention[Intention::Unblock->value]);
        self::assertSame(1, $progress->byIntention[Intention::Thanks->value]);
        self::assertSame(0, $progress->byIntention[Intention::Contest->value]);
        self::assertSame(67, $progress->share(Intention::Unblock));
        self::assertSame(1, $progress->probableBugs);
        self::assertSame(1, $progress->urgent);
        self::assertSame(1330, $progress->tokens);
        self::assertSame(6, $progress->duration());
        self::assertSame(0.5, $progress->rate());
        self::assertSame(['mean' => 533, 'median' => 400, 'p95' => 400, 'max' => 900], $progress->latency);
    }

    public function testNothingRequestedMeansNothingToReport(): void
    {
        $this->repository->resetTriage();

        $progress = $this->repository->getTriageProgress();

        self::assertSame(0, $progress->requested);
        self::assertFalse($progress->isRunning());
        self::assertSame(0, $progress->percent());
        self::assertNull($progress->latency);
        self::assertNull($this->repository->find(1)->getIntention());
    }

    public function testItFiltersAndSortsTheInbox(): void
    {
        $ids = fn (InboxQuery $query): array => array_map(static fn (Experience $e): int => $e->getId(), $this->repository->paginate($query, 20)->items);

        self::assertSame([5, 4, 3, 2, 1], $ids(new InboxQuery()));
        self::assertSame([2, 1], $ids(new InboxQuery(intention: Intention::Unblock)));
        self::assertSame([1], $ids(new InboxQuery(bugs: true)));
        self::assertSame([1, 2, 3, 5, 4], $ids(new InboxQuery(sort: InboxSort::Priority)), 'The most urgent first, what Jev has not seen last.');
    }
}
