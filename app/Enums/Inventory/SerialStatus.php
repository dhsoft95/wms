<?php

declare(strict_types=1);

namespace App\Enums\Inventory;

enum SerialStatus: string
{
    case InStock    = 'in_stock';
    case Dispatched = 'dispatched';
    case WrittenOff = 'written_off';

    public function label(): string
    {
        return match($this) {
            self::InStock    => 'In Stock',
            self::Dispatched => 'Dispatched',
            self::WrittenOff => 'Written Off',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::InStock    => 'green',
            self::Dispatched => 'neutral',
            self::WrittenOff => 'red',
        };
    }
}
