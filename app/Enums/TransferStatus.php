<?php

declare(strict_types=1);

namespace App\Enums;

use App\Exceptions\InvalidTransitionException;

enum TransferStatus: string
{
    case Draft                    = 'draft';
    case Submitted                = 'submitted';
    case Accepted                 = 'accepted';
    case Approved                 = 'approved';   // Legacy alias — kept for backward compatibility
    case Posted                   = 'posted';
    case Rejected                 = 'rejected';
    case ReturnedForCorrection    = 'returned_for_correction';
    case AwaitingRequesterCorrection = 'awaiting_requester_correction';

    public function canTransitionTo(self $new): bool
    {
        return match ($this) {
            self::Draft                    => in_array($new, [self::Submitted], true),
            self::Submitted                => in_array($new, [self::Accepted, self::Approved, self::Rejected, self::ReturnedForCorrection], true),
            self::ReturnedForCorrection    => $new === self::AwaitingRequesterCorrection,
            self::AwaitingRequesterCorrection => $new === self::Submitted,
            self::Accepted,
            self::Approved                 => $new === self::Posted,
            default                        => false,
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
            self::Draft                      => 'Draft',
            self::Submitted                  => 'Pending Acceptance',
            self::Accepted                   => 'Accepted',
            self::Approved                   => 'Approved',
            self::Posted                     => 'Posted',
            self::Rejected                   => 'Rejected',
            self::ReturnedForCorrection      => 'Returned for Correction',
            self::AwaitingRequesterCorrection => 'Awaiting Correction',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft                      => 'badge-slate',
            self::Submitted                  => 'badge-amber',
            self::Accepted                   => 'badge-blue',
            self::Approved                   => 'badge-green',
            self::Posted                     => 'badge-neutral',
            self::Rejected                   => 'badge-red',
            self::ReturnedForCorrection      => 'badge-orange',
            self::AwaitingRequesterCorrection => 'badge-amber',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::AwaitingRequesterCorrection], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Posted, self::Rejected], true);
    }
}
