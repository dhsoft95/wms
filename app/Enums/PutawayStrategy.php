<?php

declare(strict_types=1);

namespace App\Enums;

enum PutawayStrategy: string
{
    case PreferredZone = 'preferred_zone';
    case NearestBin    = 'nearest_bin';
    case FixedBin      = 'fixed_bin';
    case Manual        = 'manual';

    public function label(): string
    {
        return match($this) {
            self::PreferredZone => 'Preferred Zone',
            self::NearestBin    => 'Nearest Available Bin',
            self::FixedBin      => 'Fixed Bin',
            self::Manual        => 'Manual Selection',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PreferredZone => 'badge-brand',
            self::NearestBin    => 'badge-slate',
            self::FixedBin      => 'badge-green',
            self::Manual        => 'badge-neutral',
        };
    }
}
