<?php

declare(strict_types=1);

namespace App\Enums\Inventory;

enum DisposalMethod: string
{
    case Auction = 'auction';
    case Destroy = 'destroy';

    public function label(): string
    {
        return match($this) {
            self::Auction => 'Auction / Sale',
            self::Destroy => 'Destruction',
        };
    }
}
