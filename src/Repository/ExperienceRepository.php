<?php

namespace App\Repository;

use App\Entity\Experience;
use App\Inbox\InboxQuery;
use App\Inbox\InboxSort;
use App\Pagination\Page;
use App\Triage\Intention;
use App\Triage\TriageProgress;
use App\Triage\TriageResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Experience>
 */
class ExperienceRepository extends ServiceEntityRepository
{
    // SQLite builds older than 3.32 accept at most 999 bound parameters
    private const IDS_PER_STATEMENT = 500;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Experience::class);
    }

    /**
     * @return Page<Experience>
     */
    public function paginate(InboxQuery $query, int $perPage): Page
    {
        $queryBuilder = $this->createQueryBuilder('e')
            ->setFirstResult(($query->page - 1) * $perPage)
            ->setMaxResults($perPage);

        if (InboxSort::Priority === $query->sort) {
            // What Jev has not seen yet has no priority, and goes last
            $queryBuilder->addOrderBy('CASE WHEN e.priority IS NULL THEN 1 ELSE 0 END', 'ASC')->addOrderBy('e.priority', 'DESC');
        }
        $queryBuilder->addOrderBy('e.writtenAt', 'DESC')->addOrderBy('e.id', 'DESC');

        if ('' !== $search = $query->search()) {
            // "!" escapes the wildcards typed by the user, so "100%" only matches that exact text
            $queryBuilder
                ->andWhere("e.text LIKE :search ESCAPE '!'")
                ->setParameter('search', '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%');
        }

        if (null !== $query->intention) {
            $queryBuilder->andWhere('e.intention = :intention')->setParameter('intention', $query->intention);
        }

        if ($query->bugs) {
            $queryBuilder->andWhere('e.bugProbability >= :bug')->setParameter('bug', TriageResult::PROBABLE_BUG);
        }

        $paginator = new Paginator($queryBuilder, fetchJoinCollection: false);

        return new Page(iterator_to_array($paginator, false), $query->page, $perPage, \count($paginator));
    }

    /**
     * @param list<int> $ids
     *
     * @return list<int> The identifiers that are already stored, among the given ones
     */
    public function findExistingIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->createQueryBuilder('e')
            ->select('e.id')
            ->where('e.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * The experiences nobody asked Jev about yet, most recent first.
     *
     * @return list<int>
     */
    public function findIdsToTriage(\DateTimeInterface $since, ?int $limit): array
    {
        return $this->createQueryBuilder('e')
            ->select('e.id')
            ->where('e.triageRequestedAt IS NULL')
            ->andWhere('e.writtenAt >= :since')
            ->setParameter('since', $since->format('Y-m-d'))
            ->orderBy('e.writtenAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function countTriageRequested(): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.triageRequestedAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param list<int> $ids
     */
    public function markTriageRequested(array $ids, \DateTimeImmutable $now): void
    {
        foreach (array_chunk($ids, self::IDS_PER_STATEMENT) as $chunk) {
            $this->createQueryBuilder('e')
                ->update()
                ->set('e.triageRequestedAt', ':now')
                ->where('e.id IN (:ids)')
                ->setParameter('now', $now)
                ->setParameter('ids', $chunk)
                ->getQuery()
                ->execute();
        }
    }

    /**
     * Forgets everything about the triage, so it can be played again from scratch.
     *
     * @return int How many experiences had been sent to Jev
     */
    public function resetTriage(): int
    {
        return $this->createQueryBuilder('e')
            ->update()
            ->set('e.intention', ':null')
            ->set('e.intentionConfidence', ':null')
            ->set('e.priority', ':null')
            ->set('e.bugProbability', ':null')
            ->set('e.triageTokens', ':null')
            ->set('e.triageDurationMs', ':null')
            ->set('e.triageRequestedAt', ':null')
            ->set('e.triagedAt', ':null')
            ->where('e.triageRequestedAt IS NOT NULL')
            ->setParameter('null', null)
            ->getQuery()
            ->execute();
    }

    public function getTriageProgress(): TriageProgress
    {
        $run = $this->createQueryBuilder('e')
            ->select('COUNT(e.id) AS requested', 'MIN(e.triageRequestedAt) AS startedAt', 'MAX(e.triagedAt) AS lastTriagedAt')
            ->where('e.triageRequestedAt IS NOT NULL')
            ->getQuery()
            ->getSingleResult();

        $rows = $this->createQueryBuilder('e')
            ->select(
                'e.intention AS intention',
                'COUNT(e.id) AS triaged',
                'SUM(CASE WHEN e.bugProbability >= :bug THEN 1 ELSE 0 END) AS probableBugs',
                'SUM(CASE WHEN e.priority >= :urgent THEN 1 ELSE 0 END) AS urgent',
                'SUM(e.triageTokens) AS tokens',
            )
            ->where('e.triagedAt IS NOT NULL')
            ->groupBy('e.intention')
            ->setParameter('bug', TriageResult::PROBABLE_BUG)
            ->setParameter('urgent', TriageResult::URGENT_PRIORITY)
            ->getQuery()
            ->getArrayResult();

        $byIntention = array_fill_keys(array_map(static fn (Intention $intention): string => $intention->value, Intention::cases()), 0);
        $triaged = $probableBugs = $urgent = $tokens = 0;
        foreach ($rows as $row) {
            $intention = $row['intention'] instanceof Intention ? $row['intention']->value : (string) $row['intention'];
            $byIntention[$intention] = (int) $row['triaged'];
            $triaged += (int) $row['triaged'];
            $probableBugs += (int) $row['probableBugs'];
            $urgent += (int) $row['urgent'];
            $tokens += (int) $row['tokens'];
        }

        return new TriageProgress(
            (int) $run['requested'],
            $triaged,
            $byIntention,
            $probableBugs,
            $urgent,
            $tokens,
            null !== $run['startedAt'] ? new \DateTimeImmutable($run['startedAt']) : null,
            null !== $run['lastTriagedAt'] ? new \DateTimeImmutable($run['lastTriagedAt']) : null,
            $this->getTriageLatency(),
        );
    }

    /**
     * @return array{mean: int, median: int, p95: int, max: int}|null
     */
    private function getTriageLatency(): ?array
    {
        $summary = $this->createQueryBuilder('e')
            ->select('COUNT(e.id) AS measured', 'AVG(e.triageDurationMs) AS mean', 'MAX(e.triageDurationMs) AS max')
            ->where('e.triageDurationMs IS NOT NULL')
            ->getQuery()
            ->getSingleResult();

        if (0 === $measured = (int) $summary['measured']) {
            return null;
        }

        // A percentile is the value found at that rank once the durations are sorted
        $percentile = fn (float $rank): int => (int) $this->createQueryBuilder('e')
            ->select('e.triageDurationMs')
            ->where('e.triageDurationMs IS NOT NULL')
            ->orderBy('e.triageDurationMs', 'ASC')
            ->setFirstResult((int) floor($rank * ($measured - 1)))
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();

        return ['mean' => (int) round($summary['mean']), 'median' => $percentile(0.5), 'p95' => $percentile(0.95), 'max' => (int) $summary['max']];
    }
}
