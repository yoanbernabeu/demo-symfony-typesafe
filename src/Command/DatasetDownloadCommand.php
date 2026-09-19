<?php

namespace App\Command;

use App\Dataset\ExperiencesDatasetDownloader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:dataset:download', 'Downloads the public services feedback dataset from data.gouv.fr')]
final class DatasetDownloadCommand
{
    public function __construct(
        private readonly ExperiencesDatasetDownloader $downloader,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Download the dataset again even if it is already there')] bool $force = false,
    ): int {
        $progressBar = null;

        $file = $this->downloader->download($force, static function (int $downloaded, int $total) use ($io, &$progressBar): void {
            if (null === $progressBar) {
                $progressBar = $io->createProgressBar($total);
                $progressBar->setFormat(' %current_size% / %total_size% [%bar%] %percent:3s%%');
                $progressBar->minSecondsBetweenRedraws(0.2);
            }

            $progressBar->setMessage(Helper::formatMemory($downloaded), 'current_size');
            $progressBar->setMessage($total > 0 ? Helper::formatMemory($total) : '?', 'total_size');
            $progressBar->setProgress($downloaded);
        });

        if (null !== $progressBar) {
            $progressBar->finish();
            $io->newLine(2);
        }

        if (!$file->downloaded) {
            $io->note(\sprintf('The dataset is already there (%s). Use --force to download it again.', Helper::formatMemory($file->size)));
        } else {
            $io->success(\sprintf('Downloaded %s%s.', Helper::formatMemory($file->size), null !== $file->publishedAt ? ', published on '.$file->publishedAt->format('Y-m-d') : ''));
        }

        $io->writeln(\sprintf('Dataset: <info>%s</info>', $file->path));

        return 0;
    }
}
