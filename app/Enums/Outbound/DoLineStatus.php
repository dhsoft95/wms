<?php

declare(strict_types=1);

namespace App\Enums\Outbound;

enum DoLineStatus: string
{
    case Pending            = 'pending';
    case Allocated          = 'allocated';
    case PartiallyAllocated = 'partially_allocated';
    case Picking            = 'picking';
    case Picked             = 'picked';
    case Short              = 'short';
    case Dispatched         = 'dispatched';
    case Cancelled          = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Pending            => 'Pending',
            self::Allocated          => 'Allocated',
            self::PartiallyAllocated => 'Partially Allocated',
            self::Picking            => 'Picking',
            self::Picked             => 'Picked',
            self::Short              => 'Short',
            self::Dispatched         => 'Dispatched',
            self::Cancelled          => 'Cancelled',
        };
    }
}
