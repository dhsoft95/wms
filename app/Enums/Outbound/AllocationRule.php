<?php

declare(strict_types=1);

namespace App\Enums\Outbound;

enum AllocationRule: string
{
    case Default = 'default';
    case Fifo    = 'fifo';
    case Fefo    = 'fefo';

    public function label(): string
    {
        return match($this) {
            self::Default => 'Default (FIFO / FEFO by item setting)',
            self::Fifo    => 'Force FIFO',
            self::Fefo    => 'Force FEFO',
        };
    }
}
