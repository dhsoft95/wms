<?php

namespace App\Enums;

enum LocationLevel: string
{
    case Zone       = 'zone';
    case Aisle      = 'aisle';
    case Rack       = 'rack';
    case ShelfLevel = 'shelf_level';
    case Bin        = 'bin';

    public function label(): string
    {
        return match($this) {
            self::Zone       => 'Zone',
            self::Aisle      => 'Aisle',
            self::Rack       => 'Rack',
            self::ShelfLevel => 'Shelf Level',
            self::Bin        => 'Bin',
        };
    }
}
