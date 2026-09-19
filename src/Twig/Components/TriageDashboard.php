<?php

namespace App\Twig\Components;

use App\Repository\ExperienceRepository;
use App\Triage\Intention;
use App\Triage\TriageLauncher;
use App\Triage\TriageProgress;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Follows the triage as it happens. There is nothing to push from the worker: the
 * component polls, and every render reads the counters again from the database.
 */
#[AsLiveComponent]
final class TriageDashboard
{
    use DefaultActionTrait;

    private ?TriageProgress $progress = null;

    public function __construct(
        private readonly ExperienceRepository $experiences,
        private readonly TriageLauncher $launcher,
        private readonly ClockInterface $clock,
        #[Autowire(env: 'int:APP_TRIAGE_REQUESTS_PER_MINUTE')] private readonly int $requestsPerMinute,
        // Only exists when the profiler is installed and enabled, that is in dev
        #[Autowire(service: 'profiler')] private readonly ?Profiler $profiler = null,
    ) {
    }

    #[LiveAction]
    public function launch(): void
    {
        // Queuing a whole year dispatches a thousand messages and marks tens of thousands of rows: the profiler
        // would run out of memory trying to store the details of all that, and take the request down with it
        $this->profiler?->disable();

        $this->launcher->launch();
        $this->progress = null;
    }

    public function getProgress(): TriageProgress
    {
        return $this->progress ??= $this->experiences->getTriageProgress();
    }

    /**
     * @return list<Intention>
     */
    public function getIntentions(): array
    {
        return Intention::cases();
    }

    public function getLimit(): ?int
    {
        return $this->launcher->getLimit();
    }

    public function getSince(): \DateTimeImmutable
    {
        return $this->launcher->getSince();
    }

    public function getRequestsPerMinute(): int
    {
        return $this->requestsPerMinute;
    }

    public function isStalled(): bool
    {
        return $this->getProgress()->isStalled($this->clock->now());
    }

    public function canLaunch(): bool
    {
        return 0 !== $this->launcher->getRemainingBudget();
    }
}
