<?php

declare(strict_types=1);

namespace App\Enums;

enum ContainerSizeType: string
{
    case TwentyGP = '20GP';
    case FortyGP  = '40GP';
    case FortyHC  = '40HC';
    case TwentyRF = '20RF';
    case FortyRF  = '40RF';
    case Other    = 'other';

    public function label(): string
    {
        return match($this) {
            self::TwentyGP => "20' General Purpose",
            self::FortyGP  => "40' General Purpose",
            self::FortyHC  => "40' High Cube",
            self::TwentyRF => "20' Refrigerated",
            self::FortyRF  => "40' Refrigerated",
            self::Other    => 'Other',
        };
    }
}
