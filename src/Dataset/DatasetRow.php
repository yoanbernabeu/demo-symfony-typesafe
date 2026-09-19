<?php

namespace App\Dataset;

/**
 * A row of the dataset, reduced to what a user wrote to a public service.
 */
final readonly class DatasetRow
{
    public function __construct(
        public string $id,
        public \DateTimeImmutable $writtenAt,
        public string $text,
    ) {
    }
}
