<?php

namespace App\Command;

use App\Triage\TriageLauncher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:triage:start', 'Queues the experiences to be triaged by Jev')]
final class TriageStartCommand
{
    public function __construct(
        private readonly TriageLauncher $launcher,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $queued = $this->launcher->launch();

        if (0 === $queued) {
            $io->note(0 === $this->launcher->getRemainingBudget()
                ? \sprintf('The cap of %d experiences is reached. Raise APP_TRIAGE_LIMIT (0 removes it) or run app:triage:reset.', $this->launcher->getLimit())
                : \sprintf('Nothing left to triage since %s.', $this->launcher->getSince()->format('Y-m-d')));

            return 0;
        }

        $io->success(\sprintf('%d experiences queued. Process them with "bin/console messenger:consume async" (already running under "symfony serve").', $queued));

        return 0;
    }
}
