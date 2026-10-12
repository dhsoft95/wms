<?php

namespace App\Enums;

enum ReasonCodeAppliesTo: string
{
    case Variance     = 'variance';
    case Adjustment   = 'adjustment';
    case ShortPick    = 'short_pick';
    case Reprint      = 'reprint';
    case Hold         = 'hold';
    case SealMismatch = 'seal_mismatch';

    public function label(): string
    {
        return match($this) {
            self::Variance     => 'Variance',
            self::Adjustment   => 'Adjustment',
            self::ShortPick    => 'Short Pick',
            self::Reprint      => 'Reprint',
            self::Hold         => 'Hold',
            self::SealMismatch => 'Seal Mismatch',
        };
    }
}
