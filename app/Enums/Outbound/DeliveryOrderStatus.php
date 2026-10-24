<?php

declare(strict_types=1);

namespace App\Enums\Outbound;

enum DeliveryOrderStatus: string
{
    case Draft                       = 'draft';
    case Submitted                   = 'submitted';
    case ReturnedForCorrection       = 'returned_for_correction';
    case AwaitingRequesterCorrection = 'awaiting_requester_correction';
    case Approved                    = 'approved';
    case Rejected                    = 'rejected';
    case Acceptance                  = 'acceptance';
    case Assigned                    = 'assigned';
    case Allocated                   = 'allocated';
    case PartiallyAllocated          = 'partially_allocated';
    case Picking                     = 'picking';
    case Picked                      = 'picked';
    case Dispatched                  = 'dispatched';
    case Delivered                   = 'delivered';
    case Cancelled                   = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Draft                       => 'Draft',
            self::Submitted                   => 'Pending Approval',
            self::ReturnedForCorrection       => 'Returned for Correction',
            self::AwaitingRequesterCorrection => 'Awaiting Requester Correction',
            self::Approved                    => 'Approved',
            self::Rejected                    => 'Rejected',
            self::Acceptance                  => 'Acceptance',
            self::Assigned                    => 'Assigned',
            self::Allocated                   => 'Allocated',
            self::PartiallyAllocated          => 'Partially Allocated',
            self::Picking                     => 'Picking',
            self::Picked                      => 'Picked',
            self::Dispatched                  => 'Dispatched',
            self::Delivered                   => 'Delivered',
            self::Cancelled                   => 'Cancelled',
        };
    }

    public function badge(): string
    {
        return match($this) {
            self::Draft                       => 'badge-slate',
            self::Submitted                   => 'badge-amber',
            self::ReturnedForCorrection       => 'badge-amber',
            self::AwaitingRequesterCorrection => 'badge-orange',
            self::Approved                    => 'badge-blue',
            self::Rejected                    => 'badge-red',
            self::Acceptance                  => 'badge-purple',
            self::Assigned                    => 'badge-cyan',
            self::Allocated                   => 'badge-cyan',
            self::PartiallyAllocated          => 'badge-orange',
            self::Picking                     => 'badge-violet',
            self::Picked                      => 'badge-green',
            self::Dispatched                  => 'badge-emerald',
            self::Delivered                   => 'badge-teal',
            self::Cancelled                   => 'badge-red',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        $allowed = match($this) {
            self::Draft                       => [self::Submitted, self::Cancelled],
            self::Submitted                   => [self::Approved, self::ReturnedForCorrection, self::Rejected, self::Cancelled],
            self::ReturnedForCorrection       => [self::AwaitingRequesterCorrection, self::Cancelled],
            self::AwaitingRequesterCorrection => [self::Submitted, self::Cancelled],
            self::Approved                    => [self::Acceptance, self::ReturnedForCorrection, self::Cancelled],
            self::Acceptance                  => [self::Allocated, self::PartiallyAllocated, self::ReturnedForCorrection, self::Cancelled],
            self::Allocated                   => [self::Picking, self::Cancelled],
            self::PartiallyAllocated          => [self::Allocated, self::Picking, self::Cancelled],
            self::Picking                     => [self::Picked, self::Dispatched],
            self::Picked                      => [self::Dispatched],
            self::Dispatched                  => [self::Delivered],
            self::Assigned                    => [self::Allocated, self::PartiallyAllocated, self::Picking, self::Cancelled],
            default                           => [],
        };

        return in_array($target, $allowed, true);
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::AwaitingRequesterCorrection], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Rejected, self::Delivered, self::Cancelled], true);
    }

    public function isCancellable(): bool
    {
        return in_array($this, [
            self::Draft,
            self::Submitted,
            self::ReturnedForCorrection,
            self::AwaitingRequesterCorrection,
            self::Approved,
            self::Acceptance,
            self::Allocated,
            self::PartiallyAllocated,
        ], true);
    }
}
