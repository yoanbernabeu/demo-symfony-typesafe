<?php

namespace App\Tests\Dataset;

use App\Dataset\ExperiencesDatasetReader;
use App\Dataset\ExperiencesImporter;
use App\Entity\Experience;
use App\Repository\ExperienceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ExperiencesImporterTest extends KernelTestCase
{
    use DatasetFixtureTrait;

    private string $path;
    private EntityManagerInterface $entityManager;
    private ExperienceRepository $repository;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/demojev_'.bin2hex(random_bytes(4)).'.csv';
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(ExperienceRepository::class);

        // The test database is a throwaway SQLite file, rebuilt from the mapping before every test
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = [$this->entityManager->getClassMetadata(Experience::class)];
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        parent::tearDown();
    }

    public function testItStoresTheRowsOfTheDataset(): void
    {
        $this->writeDataset($this->path, [
            ['280027', '2025-03-14', 'Jean D.', 'Mon dossier est bloqué depuis trois mois, personne ne répond au téléphone ni aux courriers.', 'Négatif'],
            ['280028', '2025-03-15', 'Anne L.', "Démarche très simple ;\nle site est clair et j'ai reçu ma carte grise en quatre jours. Merci beaucoup.", 'Positif'],
        ]);

        $result = $this->createImporter()->import();

        self::assertSame(2, $result->imported);
        self::assertSame(0, $result->alreadyThere);

        $experience = $this->repository->find(280028);
        self::assertInstanceOf(Experience::class, $experience);
        self::assertSame('2025-03-15', $experience->getWrittenAt()->format('Y-m-d'));
        self::assertSame("Démarche très simple ; le site est clair et j'ai reçu ma carte grise en quatre jours. Merci beaucoup.", $experience->getText());
    }

    public function testImportingTwiceOnlyAddsWhatIsNew(): void
    {
        $rows = [
            ['1', '2025-01-01', 'A', str_repeat('Premier message. ', 10), 'Négatif'],
            ['2', '2025-01-02', 'B', str_repeat('Deuxième message. ', 10), 'Négatif'],
        ];
        $this->writeDataset($this->path, $rows);
        $this->createImporter()->import();

        $rows[] = ['3', '2025-01-03', 'C', str_repeat('Troisième message. ', 10), 'Positif'];
        // The same identifier twice in the file must not break the batch either
        $rows[] = ['3', '2025-01-03', 'C', str_repeat('Troisième message. ', 10), 'Positif'];
        $this->writeDataset($this->path, $rows);

        $result = $this->createImporter()->import();

        self::assertSame(1, $result->imported);
        self::assertSame(2, $result->alreadyThere);
        self::assertSame(3, $this->repository->count());
    }

    public function testItWritesInSeveralBatchesAndReportsItsProgress(): void
    {
        $rows = [];
        for ($i = 1; $i <= 450; ++$i) {
            $rows[] = [(string) $i, '2025-02-01', 'A', str_repeat("Message numéro $i. ", 8), 'Neutre'];
        }
        $this->writeDataset($this->path, $rows);
        $progress = [];

        $result = $this->createImporter()->import(onProgress: static function (int $readRows) use (&$progress): void {
            $progress[] = $readRows;
        });

        self::assertSame(450, $result->imported);
        self::assertSame(450, $this->repository->count());
        self::assertSame([200, 400, 450], $progress);
    }

    public function testItHonoursThePeriodAndTheLimit(): void
    {
        $this->writeDataset($this->path, [
            ['1', '2024-12-31', 'A', str_repeat('Trop ancien. ', 10), 'Négatif'],
            ['2', '2025-01-01', 'B', str_repeat('Dans la période. ', 10), 'Négatif'],
            ['3', '2025-01-02', 'C', str_repeat('Dans la période. ', 10), 'Négatif'],
            ['4', '2025-01-03', 'D', str_repeat('Au-delà de la limite. ', 10), 'Négatif'],
        ]);

        $result = $this->createImporter()->import(new \DateTimeImmutable('2025-01-01'), 2);

        self::assertSame(2, $result->imported);
        self::assertSame([2, 3], $this->repository->findExistingIds([1, 2, 3, 4]));
    }

    private function createImporter(): ExperiencesImporter
    {
        return new ExperiencesImporter(new ExperiencesDatasetReader($this->path), $this->entityManager, $this->repository);
    }
}
