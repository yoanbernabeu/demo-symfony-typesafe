<?php

namespace App\Dataset;

final readonly class ImportResult
{
    public function __construct(
        public int $imported,
        public int $alreadyThere,
    ) {
    }
}
