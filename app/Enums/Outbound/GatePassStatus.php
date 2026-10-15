<?php

declare(strict_types=1);

namespace App\Enums\Outbound;

enum GatePassStatus: string
{
    case Issued = 'issued';
    case Exited = 'exited';

    public function label(): string
    {
        return match($this) {
            self::Issued => 'Issued',
            self::Exited => 'Exited',
        };
    }
}
