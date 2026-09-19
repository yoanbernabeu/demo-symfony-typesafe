<?php

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

/**
 * Asks for a batch of experiences to be triaged by Jev, away from the web request.
 */
#[AsMessage('async')]
final readonly class TriageExperiences
{
    /**
     * @param list<int> $experienceIds
     */
    public function __construct(
        public array $experienceIds,
    ) {
    }
}
