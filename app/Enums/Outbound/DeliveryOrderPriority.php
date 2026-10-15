<?php

declare(strict_types=1);

namespace App\Enums\Outbound;

enum DeliveryOrderPriority: string
{
    case Low    = 'low';
    case Normal = 'normal';
    case High   = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match($this) {
            self::Low    => 'Low',
            self::Normal => 'Normal',
            self::High   => 'High',
            self::Urgent => 'Urgent',
        };
    }

    public function badge(): string
    {
        return match($this) {
            self::Low    => 'badge-slate',
            self::Normal => 'badge-blue',
            self::High   => 'badge-amber',
            self::Urgent => 'badge-red',
        };
    }
}
