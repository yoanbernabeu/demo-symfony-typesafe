<?php

namespace App\Dataset;

/**
 * What a user wrote to a public service. Nothing else of the dataset is kept.
 */
final readonly class Experience
{
    public function __construct(
        public string $id,
        public \DateTimeImmutable $writtenAt,
        public string $text,
    ) {
    }
}
