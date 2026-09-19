<?php

namespace App\Dataset;

/**
 * The local copy of the dataset.
 */
final readonly class DatasetFile
{
    public function __construct(
        public string $path,
        public int $size,
        public bool $downloaded,
        public ?\DateTimeImmutable $publishedAt = null,
    ) {
    }
}
