<?php

namespace App\Enums;

enum UomType: string
{
    case Count = 'count';
    case Weight = 'weight';
    case Volume = 'volume';
    case Length = 'length';

    public function label(): string
    {
        return match($this) {
            self::Count  => 'Count',
            self::Weight => 'Weight (kg)',
            self::Volume => 'Volume (L)',
            self::Length => 'Length (m)',
        };
    }
}
