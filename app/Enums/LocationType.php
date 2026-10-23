<?php

namespace App\Enums;

enum LocationType: string
{
    case YardSlot      = 'yard_slot';
    case ReceivingDock = 'receiving_dock';
    case Storage       = 'storage';
    case Staging       = 'staging';
    case Quarantine    = 'quarantine';
    case Transit       = 'transit';
    case Hazardous     = 'hazardous';
    case Disposal      = 'disposal';

    public function label(): string
    {
        return match($this) {
            self::YardSlot      => 'Yard Slot',
            self::ReceivingDock => 'Receiving Dock',
            self::Storage       => 'Storage',
            self::Staging       => 'Staging',
            self::Quarantine    => 'Quarantine',
            self::Transit       => 'In-Transit',
            self::Hazardous     => 'Hazardous',
            self::Disposal      => 'Disposal',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Hazardous     => 'badge-amber',
            self::Disposal      => 'badge-red',
            self::Quarantine    => 'badge-amber',
            default             => 'badge-slate',
        };
    }

    public function isPickable(): bool
    {
        return match($this) {
            self::YardSlot,
            self::ReceivingDock,
            self::Quarantine,
            self::Transit,
            self::Disposal      => false,
            self::Storage,
            self::Staging,
            self::Hazardous     => true,
        };
    }
}
