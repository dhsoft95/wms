<?php

declare(strict_types=1);

namespace App\Enums;

enum ReceiptCondition: string
{
    case Good    = 'good';
    case Damaged = 'damaged';

    public function label(): string
    {
        return match($this) {
            self::Good    => 'Good',
            self::Damaged => 'Damaged',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Good    => 'badge-green',
            self::Damaged => 'badge-red',
        };
    }
}
