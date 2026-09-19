<?php

namespace App\Inbox;

enum InboxSort: string
{
    case Recent = 'recent';
    case Priority = 'priority';

    public function label(): string
    {
        return match ($this) {
            self::Recent => 'Les plus récentes',
            self::Priority => 'Les plus urgentes',
        };
    }
}
