<?php

declare(strict_types=1);

namespace App\Enums\Inventory;

enum MovementType: string
{
    case OpeningBalance    = 'opening_balance';
    case Receipt           = 'receipt';
    case Putaway           = 'putaway';
    case Relocation        = 'relocation';
    case Pick              = 'pick';
    case Dispatch          = 'dispatch';
    case OwnershipTransfer = 'ownership_transfer';
    case Adjustment        = 'adjustment';
    case CountAdjustment   = 'count_adjustment';
    case Hold              = 'hold';
    case Release           = 'release';
    case Reversal          = 'reversal';
    case SerialCapture     = 'serial_capture';
    case Obsolete          = 'obsolete';
    case Disposal          = 'disposal';

    public function label(): string
    {
        return match($this) {
            self::OpeningBalance    => 'Opening Balance',
            self::Receipt           => 'Receipt',
            self::Putaway           => 'Putaway',
            self::Relocation        => 'Internal Transfer',
            self::Pick              => 'Pick',
            self::Dispatch          => 'Dispatch',
            self::OwnershipTransfer => 'Warehouse-to-Warehouse Transfer',
            self::Adjustment        => 'Adjustment',
            self::CountAdjustment   => 'Count Adjustment',
            self::Hold              => 'Hold',
            self::Release           => 'Release',
            self::Reversal          => 'Reversal',
            self::SerialCapture     => 'Serial Capture',
            self::Obsolete          => 'Mark Obsolete',
            self::Disposal          => 'Disposal',
        };
    }

    // True for movements that only add stock (positive qty)
    public function inboundOnly(): bool
    {
        return in_array($this, [self::Receipt, self::Putaway, self::OpeningBalance], true);
    }

    // True for movements that only reduce stock (negative qty)
    public function outboundOnly(): bool
    {
        return in_array($this, [self::Pick, self::Dispatch, self::Disposal], true);
    }

    // True for movements that generate two lines netting to zero
    public function isPaired(): bool
    {
        return in_array($this, [
            self::Relocation,
            self::OwnershipTransfer,
            self::Hold,
            self::Release,
            self::Obsolete,
        ], true);
    }
}
