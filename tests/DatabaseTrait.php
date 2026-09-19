<?php

namespace App\Tests;

use App\Entity\Experience;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

trait DatabaseTrait
{
    /**
     * The test database is a throwaway SQLite file, rebuilt from the mapping before every test.
     */
    private function resetDatabase(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($entityManager);
        $metadata = [$entityManager->getClassMetadata(Experience::class)];
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        return $entityManager;
    }
}
