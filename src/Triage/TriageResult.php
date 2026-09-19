<?php

namespace App\Triage;

/**
 * The three answers Jev gave about an experience.
 */
final readonly class TriageResult
{
    public const MAX_PRIORITY = 3;

    // From which answers an experience counts as urgent, or as a bug worth forwarding to the developers
    public const URGENT_PRIORITY = 2.0;
    public const PROBABLE_BUG = 0.5;

    /**
     * @param float $intentionConfidence Choice: how sure Jev is of the intention, from 0 to 1
     * @param float $priority            Score: from 0 (no urgency) to 3 (the person is deprived of income, papers or care)
     * @param float $bugProbability      Noul: probability that the message reports a malfunction of the website
     * @param int|null $durationMs        How long Jev took to answer, as measured by the HTTP client
     */
    public function __construct(
        public Intention $intention,
        public float $intentionConfidence,
        public float $priority,
        public float $bugProbability,
        public int $tokens,
        public ?int $durationMs = null,
    ) {
    }
}
