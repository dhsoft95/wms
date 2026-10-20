<?php

declare(strict_types=1);

namespace App\Enums\Outbound;

enum PackageType: string
{
    case LooseCargo = 'loose_cargo';
    case Container  = 'container';

    public function label(): string
    {
        return match($this) {
            self::LooseCargo => 'Loose Cargo',
            self::Container  => 'Container',
        };
    }
}
