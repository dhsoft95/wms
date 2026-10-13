<?php

declare(strict_types=1);

namespace App\Enums;

enum GrnSource: string
{
    case Container = 'container';
    case Supplier  = 'supplier';
    case Return    = 'return';
    case Manual    = 'manual';

    public function label(): string
    {
        return match($this) {
            self::Container => 'Container',
            self::Supplier  => 'Supplier Delivery',
            self::Return    => 'Customer Return',
            self::Manual    => 'Manual',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Container => 'badge-brand',
            self::Supplier  => 'badge-slate',
            self::Return    => 'badge-amber',
            self::Manual    => 'badge-neutral',
        };
    }
}
