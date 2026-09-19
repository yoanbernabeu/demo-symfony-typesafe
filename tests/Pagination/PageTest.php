<?php

namespace App\Tests\Pagination;

use App\Pagination\Page;
use PHPUnit\Framework\TestCase;

final class PageTest extends TestCase
{
    public function testItKnowsItsNeighbours(): void
    {
        $first = new Page([], 1, 20, 41);
        $last = new Page([], 3, 20, 41);

        self::assertSame(3, $first->lastNumber());
        self::assertFalse($first->hasPrevious());
        self::assertTrue($first->hasNext());
        self::assertTrue($last->hasPrevious());
        self::assertFalse($last->hasNext());
    }
}
