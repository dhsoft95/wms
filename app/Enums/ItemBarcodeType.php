<?php

namespace App\Enums;

enum ItemBarcodeType: string
{
    case Supplier = 'supplier';
    case Internal = 'internal';

    public function label(): string
    {
        return match($this) {
            self::Supplier => 'Supplier',
            self::Internal => 'Internal',
        };
    }
}
