<?php

namespace App\Triage;

/**
 * Where the triage stands: how many experiences were sent to Jev, how many came back, and with which answers.
 */
final readonly class TriageProgress
{
    /**
     * @param array<string, int>                                 $byIntention Number of triaged experiences for every Intention value
     * @param array{mean: int, median: int, p95: int, max: int}|null $latency     How long Jev took to answer, in milliseconds
     */
    public function __construct(
        public int $requested,
        public int $triaged,
        public array $byIntention,
        public int $probableBugs,
        public int $urgent,
        public int $tokens,
        public ?\DateTimeImmutable $startedAt,
        public ?\DateTimeImmutable $lastTriagedAt,
        public ?array $latency = null,
    ) {
    }

    public function pending(): int
    {
        return max(0, $this->requested - $this->triaged);
    }

    public function isRunning(): bool
    {
        return $this->pending() > 0;
    }

    /**
     * Whether experiences are pending while nothing came back lately: the worker is most likely
     * waiting for the rate limit of Jev to let it go on, or it is not running.
     */
    public function isStalled(\DateTimeImmutable $now, int $afterSeconds = 4): bool
    {
        $lastActivity = $this->lastTriagedAt ?? $this->startedAt;

        return $this->isRunning() && null !== $lastActivity && $now->getTimestamp() - $lastActivity->getTimestamp() >= $afterSeconds;
    }

    public function percent(): int
    {
        return 0 === $this->requested ? 0 : (int) floor(100 * $this->triaged / $this->requested);
    }

    /**
     * Seconds between the first request and the last answer.
     */
    public function duration(): int
    {
        if (null === $this->startedAt || null === $this->lastTriagedAt) {
            return 0;
        }

        return max(0, $this->lastTriagedAt->getTimestamp() - $this->startedAt->getTimestamp());
    }

    /**
     * Experiences triaged per second.
     */
    public function rate(): float
    {
        return 0 === $this->duration() ? 0.0 : $this->triaged / $this->duration();
    }

    public function share(Intention $intention): int
    {
        return 0 === $this->triaged ? 0 : (int) round(100 * ($this->byIntention[$intention->value] ?? 0) / $this->triaged);
    }
}
