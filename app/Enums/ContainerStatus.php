<?php

declare(strict_types=1);

namespace App\Enums;

use App\Exceptions\InvalidTransitionException;

enum ContainerStatus: string
{
    case Expected  = 'expected';
    case Arrived   = 'arrived';
    case Parked    = 'parked';
    case Unloading = 'unloading';
    case Unloaded  = 'unloaded';
    case Released  = 'released';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Expected  => 'Expected',
            self::Arrived   => 'Arrived',
            self::Parked    => 'Parked',
            self::Unloading => 'Unloading',
            self::Unloaded  => 'Unloaded',
            self::Released  => 'Released',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Expected  => 'badge-slate',
            self::Arrived   => 'badge-amber',
            self::Parked    => 'badge-brand',
            self::Unloading => 'badge-brand',
            self::Unloaded  => 'badge-green',
            self::Released  => 'badge-green',
            self::Cancelled => 'badge-neutral',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [
            self::Expected, self::Arrived, self::Parked,
            self::Unloading, self::Unloaded,
        ], true);
    }

    /** Allowed status transitions per workflow spec */
    public function canTransitionTo(self $to): bool
    {
        return match($this) {
            self::Expected  => in_array($to, [self::Arrived, self::Cancelled], true),
            self::Arrived   => in_array($to, [self::Parked, self::Unloading], true),
            self::Parked    => in_array($to, [self::Unloading], true),
            self::Unloading => in_array($to, [self::Unloaded], true),
            self::Unloaded  => in_array($to, [self::Released], true),
            self::Released,
            self::Cancelled => false,
        };
    }
}
