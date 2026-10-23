<?php

declare(strict_types=1);

namespace App\Enums\Inventory;

enum StockStatus: string
{
    case Available = 'available';
    case OnHold    = 'on_hold';
    case Obsolete  = 'obsolete';

    public function label(): string
    {
        return match($this) {
            self::Available => 'Available',
            self::OnHold    => 'On Hold',
            self::Obsolete  => 'Obsolete',
        };
    }

    public function isPickable(): bool
    {
        return match($this) {
            self::Available => true,
            self::OnHold    => false,
            self::Obsolete  => false,
        };
    }

    public function isAllocatable(): bool
    {
        return match($this) {
            self::Available => true,
            self::OnHold    => false,
            self::Obsolete  => false,
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Available => 'green',
            self::OnHold    => 'amber',
            self::Obsolete  => 'red',
        };
    }
}
