<?php

namespace App\Inbox;

use App\Triage\Intention;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What the inbox is asked to show, as read from the query string.
 */
final readonly class InboxQuery
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,
        public string $q = '',
        public ?Intention $intention = null,
        public bool $bugs = false,
        public InboxSort $sort = InboxSort::Recent,
    ) {
    }

    public function search(): string
    {
        return trim($this->q);
    }

    public function isFiltered(): bool
    {
        return '' !== $this->search() || null !== $this->intention || $this->bugs;
    }

    /**
     * The query string of a link to the inbox: the current one with some changes, without what is left to its default.
     * Any change other than the page goes back to the first page.
     *
     * @param array{page?: int, q?: string, intention?: ?Intention, bugs?: bool, sort?: InboxSort} $changes
     *
     * @return array<string, string|int>
     */
    public function toParameters(array $changes = []): array
    {
        $page = $changes['page'] ?? ([] === $changes ? $this->page : 1);
        $search = trim($changes['q'] ?? $this->q);
        $intention = \array_key_exists('intention', $changes) ? $changes['intention'] : $this->intention;
        $bugs = $changes['bugs'] ?? $this->bugs;
        $sort = $changes['sort'] ?? $this->sort;

        return array_filter([
            'page' => $page > 1 ? $page : null,
            'q' => '' !== $search ? $search : null,
            'intention' => $intention?->value,
            'bugs' => $bugs ? 1 : null,
            'sort' => InboxSort::Recent !== $sort ? $sort->value : null,
        ], static fn (mixed $value): bool => null !== $value);
    }
}
