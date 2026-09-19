<?php

namespace App\Command;

use App\Dataset\ExperiencesImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:dataset:import', 'Imports the downloaded dataset into the database')]
final class DatasetImportCommand
{
    public function __construct(
        private readonly ExperiencesImporter $importer,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Only import what was written on or after this day (YYYY-MM-DD)')] string $since = '2025-01-01',
        #[Option('Stop after this many rows of the dataset')] ?int $limit = null,
    ): int {
        if (false === $sinceDay = \DateTimeImmutable::createFromFormat('!Y-m-d', $since)) {
            $io->error(\sprintf('"%s" is not a valid day, expected YYYY-MM-DD.', $since));

            return 2;
        }

        $progressBar = $io->createProgressBar($limit ?? 0);
        $progressBar->minSecondsBetweenRedraws(0.2);

        $startedAt = microtime(true);
        $result = $this->importer->import($sinceDay, $limit, static fn (int $readRows) => $progressBar->setProgress($readRows));

        $progressBar->finish();
        $io->newLine(2);

        $io->success(\sprintf(
            '%d imported, %d already there, in %s (peak memory %s).',
            $result->imported,
            $result->alreadyThere,
            Helper::formatTime(microtime(true) - $startedAt),
            Helper::formatMemory(memory_get_peak_usage(true)),
        ));

        return 0;
    }
}
