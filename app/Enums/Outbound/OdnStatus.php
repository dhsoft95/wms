<?php

declare(strict_types=1);

namespace App\Enums\Outbound;

enum OdnStatus: string
{
    case Generated = 'generated';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Generated => 'Generated',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badge(): string
    {
        return match($this) {
            self::Generated => 'badge-blue',
            self::Delivered => 'badge-green',
            self::Cancelled => 'badge-red',
        };
    }
}
