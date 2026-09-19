<?php

namespace App\Triage;

use App\Message\TriageExperiences;
use App\Repository\ExperienceRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Sends the experiences to triage to the queue, in batches, within the budget set by the environment.
 */
final class TriageLauncher
{
    public function __construct(
        private readonly ExperienceRepository $experiences,
        private readonly MessageBusInterface $bus,
        private readonly ClockInterface $clock,
        #[Autowire(env: 'APP_TRIAGE_SINCE')] private readonly string $since,
        #[Autowire(env: 'int:APP_TRIAGE_LIMIT')] private readonly int $limit,
        #[Autowire(env: 'int:APP_TRIAGE_BATCH_SIZE')] private readonly int $batchSize,
    ) {
    }

    public function getSince(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->since);
    }

    /**
     * The most experiences that may ever be sent to Jev, or null when there is no cap.
     */
    public function getLimit(): ?int
    {
        return $this->limit > 0 ? $this->limit : null;
    }

    /**
     * How many more experiences may be sent to Jev, or null when there is no cap.
     */
    public function getRemainingBudget(): ?int
    {
        return null === $this->getLimit() ? null : max(0, $this->getLimit() - $this->experiences->countTriageRequested());
    }

    /**
     * @return int How many experiences were sent to the queue
     */
    public function launch(): int
    {
        $budget = $this->getRemainingBudget();
        if (0 === $budget) {
            return 0;
        }

        $ids = $this->experiences->findIdsToTriage($this->getSince(), $budget);
        if ([] === $ids) {
            return 0;
        }

        // Marked before being queued, so that launching twice in a row never queues an experience twice
        $this->experiences->markTriageRequested($ids, $this->clock->now());

        foreach (array_chunk($ids, max(1, $this->batchSize)) as $batch) {
            $this->bus->dispatch(new TriageExperiences($batch));
        }

        return \count($ids);
    }
}
