<?php

declare(strict_types=1);

namespace App\Enums\Inbound;

enum PackageType: string
{
    case Container  = 'container';
    case LooseCargo = 'loose_cargo';

    public function label(): string
    {
        return match($this) {
            self::Container  => 'Container',
            self::LooseCargo => 'Loose Cargo',
        };
    }
}
