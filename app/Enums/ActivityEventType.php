<?php

declare(strict_types=1);

namespace App\Enums;

enum ActivityEventType: string
{
    case PageView = 'page_view';
    case Heartbeat = 'heartbeat';
    case Leave = 'leave';
    case Click = 'click';

    public function label(): string
    {
        return match ($this) {
            self::PageView => 'Page view',
            self::Heartbeat => 'Heartbeat',
            self::Leave => 'Leave',
            self::Click => 'Click',
        };
    }
}
