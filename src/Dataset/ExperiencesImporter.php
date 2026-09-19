<?php

namespace App\Dataset;

use App\Entity\Experience;
use App\Repository\ExperienceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Copies the rows of the dataset into the database. Importing twice is harmless:
 * what is already stored is left untouched.
 *
 * The dataset is streamed and written in batches, one INSERT statement per batch,
 * which keeps both the memory and the number of queries low.
 */
final class ExperiencesImporter
{
    // Three values per row: stays under the 999 bound parameters of older SQLite builds
    private const BATCH_SIZE = 200;

    public function __construct(
        private readonly ExperiencesDatasetReader $reader,
        private readonly EntityManagerInterface $entityManager,
        private readonly ExperienceRepository $repository,
        // Only exists in debug mode, where Doctrine keeps every query and its parameters for the profiler.
        // Being nullable, it is simply left out when the service does not exist.
        #[Autowire(service: 'doctrine.debug_data_holder')] private readonly ?DebugDataHolder $queryLog = null,
    ) {
    }

    /**
     * @param int|null                               $limit      Stop after this many rows of the dataset
     * @param (\Closure(int $readRows): void)|null $onProgress
     */
    public function import(?\DateTimeInterface $since = null, ?int $limit = null, ?\Closure $onProgress = null): ImportResult
    {
        $imported = $alreadyThere = $read = 0;
        $batch = [];

        foreach ($this->reader->read($since) as $row) {
            if (null !== $limit && $read >= $limit) {
                break;
            }

            ++$read;
            $batch[(int) $row->id] = $row;

            if (\count($batch) >= self::BATCH_SIZE) {
                $stored = $this->store($batch);
                $imported += $stored;
                $alreadyThere += \count($batch) - $stored;
                $batch = [];
                $onProgress?->__invoke($read);
            }
        }

        $stored = $this->store($batch);
        $onProgress?->__invoke($read);

        return new ImportResult($imported + $stored, $alreadyThere + \count($batch) - $stored);
    }

    /**
     * @param array<int, DatasetRow> $batch
     */
    private function store(array $batch): int
    {
        $new = array_diff_key($batch, array_flip($this->repository->findExistingIds(array_keys($batch))));

        if ([] !== $new) {
            // Table and column names come from the mapping of the entity, so they cannot drift apart
            $metadata = $this->entityManager->getClassMetadata(Experience::class);
            $columns = array_map($metadata->getColumnName(...), ['id', 'writtenAt', 'text']);

            $values = [];
            foreach ($new as $id => $row) {
                array_push($values, $id, $row->writtenAt->format('Y-m-d'), $row->text);
            }

            $this->entityManager->getConnection()->executeStatement(\sprintf(
                'INSERT INTO %s (%s) VALUES %s',
                $metadata->getTableName(),
                implode(', ', $columns),
                implode(', ', array_fill(0, \count($new), '(?, ?, ?)')),
            ), $values);
        }

        // Without this the profiler would keep the text of every imported row in memory
        $this->queryLog?->reset();

        return \count($new);
    }
}
