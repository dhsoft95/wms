<?php

declare(strict_types=1);

namespace App\Enums\Transfers;

use App\Exceptions\InvalidTransitionException;

enum WarehouseTransferStatus: string
{
    case Draft      = 'draft';
    case Submitted  = 'submitted';
    case Accepted   = 'accepted';
    case Dispatched = 'dispatched';
    case Completed  = 'completed';
    case Rejected   = 'rejected';
    case Cancelled  = 'cancelled';

    public function canTransitionTo(self $new): bool
    {
        return match ($this) {
            self::Draft      => in_array($new, [self::Submitted, self::Cancelled], true),
            self::Submitted  => in_array($new, [self::Accepted, self::Rejected, self::Cancelled], true),
            self::Accepted   => in_array($new, [self::Dispatched, self::Rejected, self::Cancelled], true),
            self::Dispatched => $new === self::Completed,
            default          => false,
        };
    }

    public function transitionTo(self $new): self
    {
        if (! $this->canTransitionTo($new)) {
            throw new InvalidTransitionException($this->value, $new->value);
        }

        return $new;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft      => 'Draft',
            self::Submitted  => 'Pending Acceptance',
            self::Accepted   => 'Accepted',
            self::Dispatched => 'Dispatched',
            self::Completed  => 'Completed',
            self::Rejected   => 'Rejected',
            self::Cancelled  => 'Cancelled',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft      => 'badge-slate',
            self::Submitted  => 'badge-amber',
            self::Accepted   => 'badge-blue',
            self::Dispatched => 'badge-purple',
            self::Completed  => 'badge-green',
            self::Rejected   => 'badge-red',
            self::Cancelled  => 'badge-red',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Rejected, self::Cancelled], true);
    }
}
