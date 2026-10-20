<?php

declare(strict_types=1);

namespace App\Enums\Outbound;

enum ContainerSize: string
{
    case Ft20   = '20ft';
    case Ft40   = '40ft';
    case Ft40HC = '40ft_hc';

    public function label(): string
    {
        return match($this) {
            self::Ft20   => '20ft',
            self::Ft40   => '40ft',
            self::Ft40HC => '40ft HC',
        };
    }
}
