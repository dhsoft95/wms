<?php

declare(strict_types=1);

namespace App\Enums\Inventory;

enum LpnStatus: string
{
    case Active   = 'active';
    case Consumed = 'consumed';

    public function label(): string
    {
        return match($this) {
            self::Active   => 'Active',
            self::Consumed => 'Consumed',
        };
    }
}
