<?php

declare(strict_types=1);

namespace App\Enums;

enum GrnVarianceType: string
{
    case None    = 'none';
    case Short   = 'short';
    case Excess  = 'excess';
    case Damaged = 'damaged';
    case Mixed   = 'mixed';

    public function label(): string
    {
        return match($this) {
            self::None    => 'No Variance',
            self::Short   => 'Short Delivery',
            self::Excess  => 'Excess Delivery',
            self::Damaged => 'Damaged Goods',
            self::Mixed   => 'Mixed Variance',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::None    => 'badge-neutral',
            self::Short   => 'badge-amber',
            self::Excess  => 'badge-brand',
            self::Damaged => 'badge-red',
            self::Mixed   => 'badge-amber',
        };
    }

    public function requiresApproval(): bool
    {
        return $this !== self::None;
    }
}
