<?php

namespace App\Repository;

use App\Entity\Experience;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
