<?php

namespace App\Command;

use App\Repository\ExperienceRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:triage:reset', 'Forgets what Jev answered, so the triage can be played again')]
final class TriageResetCommand
{
    public function __construct(
        private readonly ExperienceRepository $experiences,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $forgotten = $this->experiences->resetTriage();

        // Messages still in the queue become harmless: the handler skips what no longer awaits a triage
        $io->success(\sprintf('%d experiences are back to untriaged.', $forgotten));

        return 0;
    }
}
