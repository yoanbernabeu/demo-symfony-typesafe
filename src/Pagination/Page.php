<?php

namespace App\Pagination;

/**
 * One page of a longer list.
 *
 * @template T
 */
final readonly class Page
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public array $items,
        public int $number,
        public int $perPage,
        public int $total,
    ) {
    }

    public function lastNumber(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasPrevious(): bool
    {
        return $this->number > 1;
    }

    public function hasNext(): bool
    {
        return $this->number < $this->lastNumber();
    }
}
