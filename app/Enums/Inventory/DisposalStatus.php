<?php

declare(strict_types=1);

namespace App\Enums\Inventory;

enum DisposalStatus: string
{
    case Draft     = 'draft';
    case Submitted = 'submitted';
    case Approved  = 'approved';
    case Moved     = 'moved';
    case Completed = 'completed';
    case Rejected  = 'rejected';

    public function canTransitionTo(self $new): bool
    {
        return match($this) {
            self::Draft     => $new === self::Submitted,
            self::Submitted => in_array($new, [self::Approved, self::Rejected], true),
            self::Approved  => in_array($new, [self::Moved, self::Rejected], true),
            self::Moved     => in_array($new, [self::Completed, self::Rejected], true),
            self::Completed,
            self::Rejected  => false,
        };
    }

    public function label(): string
    {
        return match($this) {
            self::Draft     => 'Draft',
            self::Submitted => 'Submitted',
            self::Approved  => 'Approved',
            self::Moved     => 'Moved to Disposal Area',
            self::Completed => 'Completed',
            self::Rejected  => 'Rejected',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Draft     => 'neutral',
            self::Submitted => 'amber',
            self::Approved  => 'green',
            self::Moved     => 'simba',
            self::Completed => 'green',
            self::Rejected  => 'red',
        };
    }

    public function badge(): string
    {
        return match($this) {
            self::Draft     => 'badge-neutral',
            self::Submitted => 'badge-warning',
            self::Approved  => 'badge-success',
            self::Moved     => 'badge-info',
            self::Completed => 'badge-success',
            self::Rejected  => 'badge-error',
        };
    }
}
