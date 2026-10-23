<?php

declare(strict_types=1);

namespace App\Enums\Inventory;

enum StockStatusRequestType: string
{
    case MarkObsolete = 'mark_obsolete';
    case Restore      = 'restore';

    public function label(): string
    {
        return match($this) {
            self::MarkObsolete => 'Mark Obsolete',
            self::Restore      => 'Restore to Available',
        };
    }

    /** The status the stock should be on submit (source) */
    public function fromStatus(): StockStatus
    {
        return match($this) {
            self::MarkObsolete => StockStatus::Available,
            self::Restore      => StockStatus::Obsolete,
        };
    }

    /** The status the stock should be changed to on approve */
    public function toStatus(): StockStatus
    {
        return match($this) {
            self::MarkObsolete => StockStatus::Obsolete,
            self::Restore      => StockStatus::Available,
        };
    }
}
