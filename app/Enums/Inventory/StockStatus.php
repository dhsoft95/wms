<?php

declare(strict_types=1);

namespace App\Enums\Inventory;

enum StockStatus: string
{
    case Available = 'available';
    case OnHold    = 'on_hold';

    public function label(): string
    {
        return match($this) {
            self::Available => 'Available',
            self::OnHold    => 'On Hold',
        };
    }
}
