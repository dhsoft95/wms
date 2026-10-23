<?php

declare(strict_types=1);

namespace App\Enums\Inventory;

enum StockStatusRequestStatus: string
{
    case Draft     = 'draft';
    case Submitted = 'submitted';
    case Approved  = 'approved';
    case Rejected  = 'rejected';

    public function canTransitionTo(self $new): bool
    {
        return match($this) {
            self::Draft     => $new === self::Submitted,
            self::Submitted => in_array($new, [self::Approved, self::Rejected], true),
            self::Approved,
            self::Rejected  => false,
        };
    }

    public function label(): string
    {
        return match($this) {
            self::Draft     => 'Draft',
            self::Submitted => 'Submitted',
            self::Approved  => 'Approved',
            self::Rejected  => 'Rejected',
        };
    }

    public function badge(): string
    {
        return match($this) {
            self::Draft     => 'badge-neutral',
            self::Submitted => 'badge-warning',
            self::Approved  => 'badge-success',
            self::Rejected  => 'badge-error',
        };
    }
}
