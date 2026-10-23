<?php

declare(strict_types=1);

namespace App\Enums\Inventory;

enum DestroyMethod: string
{
    case Scrap              = 'scrap';
    case Recycle            = 'recycle';
    case ReturnToSupplier   = 'return_to_supplier';

    public function label(): string
    {
        return match($this) {
            self::Scrap            => 'Scrap',
            self::Recycle          => 'Recycle',
            self::ReturnToSupplier => 'Return to Supplier',
        };
    }
}
