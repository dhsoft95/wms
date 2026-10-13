<?php

declare(strict_types=1);

namespace App\Enums;

enum LabelTemplateType: string
{
    case PalletLpn  = 'pallet_lpn';
    case Item       = 'item';
    case Location   = 'location';
    case Container  = 'container';
    case Dispatch   = 'dispatch';

    public function label(): string
    {
        return match($this) {
            self::PalletLpn => 'Pallet / LPN',
            self::Item      => 'Item',
            self::Location  => 'Location',
            self::Container => 'Container',
            self::Dispatch  => 'Dispatch',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PalletLpn => 'badge-brand',
            self::Item      => 'badge-slate',
            self::Location  => 'badge-amber',
            self::Container => 'badge-neutral',
            self::Dispatch  => 'badge-blue',
        };
    }
}
