<?php

declare(strict_types=1);

namespace App\Enums\Inventory;

enum ReservationStatus: string
{
    case Active   = 'active';
    case Released = 'released';
    case Consumed = 'consumed';

    public function label(): string
    {
        return match($this) {
            self::Active   => 'Active',
            self::Released => 'Released',
            self::Consumed => 'Consumed',
        };
    }
}
