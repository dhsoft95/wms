<?php

declare(strict_types=1);

namespace App\Enums;

enum ContainerDiscrepancyType: string
{
    case SealMismatch    = 'seal_mismatch';
    case Short           = 'short';
    case Excess          = 'excess';
    case Damaged         = 'damaged';
    case UnexpectedItem  = 'unexpected_item';
    case Other           = 'other';

    public function label(): string
    {
        return match($this) {
            self::SealMismatch   => 'Seal Mismatch',
            self::Short          => 'Short Shipment',
            self::Excess         => 'Excess',
            self::Damaged        => 'Damaged',
            self::UnexpectedItem => 'Unexpected Item',
            self::Other          => 'Other',
        };
    }
}
