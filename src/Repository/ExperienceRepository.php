<?php

namespace App\Repository;

use App\Entity\Experience;
use App\Pagination\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Experience>
 */
class ExperienceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Experience::class);
    }

    /**
     * The most recent experiences first, optionally narrowed to those containing some text.
     *
     * @return Page<Experience>
     */
    public function paginate(int $page, int $perPage, string $search = ''): Page
    {
        $queryBuilder = $this->createQueryBuilder('e')
            ->orderBy('e.writtenAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ('' !== $search) {
            // "!" escapes the wildcards typed by the user, so "100%" only matches that exact text
            $queryBuilder
                ->where("e.text LIKE :search ESCAPE '!'")
                ->setParameter('search', '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%');
        }

        $paginator = new Paginator($queryBuilder, fetchJoinCollection: false);

        return new Page(iterator_to_array($paginator, false), $page, $perPage, \count($paginator));
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
}
