<?php

declare(strict_types=1);

namespace App\Enums;

enum PutawayTaskStatus: string
{
    case Open       = 'open';
    case Assigned   = 'assigned';
    case InProgress = 'in_progress';
    case Completed  = 'completed';
    case Cancelled  = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Open       => 'Open',
            self::Assigned   => 'Assigned',
            self::InProgress => 'In Progress',
            self::Completed  => 'Completed',
            self::Cancelled  => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Open       => 'badge-slate',
            self::Assigned   => 'badge-brand',
            self::InProgress => 'badge-amber',
            self::Completed  => 'badge-green',
            self::Cancelled  => 'badge-neutral',
        };
    }

    public function isActive(): bool
    {
        return !in_array($this, [self::Completed, self::Cancelled], true);
    }
}
