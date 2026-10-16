<?php

declare(strict_types=1);

namespace App\Enums;

use App\Exceptions\InvalidTransitionException;

enum TransferStatus: string
{
    case Draft     = 'draft';
    case Submitted = 'submitted';
    case Approved  = 'approved';
    case Posted    = 'posted';
    case Rejected  = 'rejected';

    public function canTransitionTo(self $new): bool
    {
        return match ($this) {
            self::Draft     => in_array($new, [self::Submitted], true),
            self::Submitted => in_array($new, [self::Approved, self::Rejected], true),
            self::Approved  => $new === self::Posted,
            default         => false,
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
            self::Draft     => 'Draft',
            self::Submitted => 'Pending Approval',
            self::Approved  => 'Approved',
            self::Posted    => 'Posted',
            self::Rejected  => 'Rejected',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft     => 'badge-slate',
            self::Submitted => 'badge-amber',
            self::Approved  => 'badge-green',
            self::Posted    => 'badge-neutral',
            self::Rejected  => 'badge-red',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Posted, self::Rejected], true);
    }
}
