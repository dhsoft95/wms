<?php

namespace App\Enums;

enum LocationType: string
{
    case YardSlot     = 'yard_slot';
    case ReceivingDock = 'receiving_dock';
    case Storage      = 'storage';
    case Staging      = 'staging';
    case Quarantine   = 'quarantine';

    public function label(): string
    {
        return match($this) {
            self::YardSlot      => 'Yard Slot',
            self::ReceivingDock => 'Receiving Dock',
            self::Storage       => 'Storage',
            self::Staging       => 'Staging',
            self::Quarantine    => 'Quarantine',
        };
    }

    public function isPickable(): bool
    {
        return match($this) {
            self::YardSlot, self::ReceivingDock, self::Quarantine => false,
            self::Storage, self::Staging                          => true,
        };
    }
}
