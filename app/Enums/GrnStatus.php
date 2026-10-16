<?php

declare(strict_types=1);

namespace App\Enums;

enum GrnStatus: string
{
    case Draft                   = 'draft';
    case Receiving               = 'receiving';
    case PendingVarianceApproval = 'pending_variance_approval';
    case Received                = 'received';
    case PutawayInProgress       = 'putaway_in_progress';
    case PutawayComplete         = 'putaway_complete';
    case Closed                  = 'closed';
    case Cancelled               = 'cancelled';

    public function canTransitionTo(self $new): bool
    {
        return match($this) {
            self::Draft                   => in_array($new, [self::Receiving, self::Cancelled], true),
            self::Receiving               => in_array($new, [self::PendingVarianceApproval, self::Received, self::Cancelled], true),
            self::PendingVarianceApproval => in_array($new, [self::Receiving, self::Received, self::Cancelled], true),
            self::Received                => $new === self::PutawayInProgress,
            self::PutawayInProgress       => $new === self::PutawayComplete,
            self::PutawayComplete         => $new === self::Closed,
            default                       => false,
        };
    }

    public function isActive(): bool
    {
        return !in_array($this, [self::Closed, self::Cancelled], true);
    }

    public function label(): string
    {
        return match($this) {
            self::Draft                   => 'Draft',
            self::Receiving               => 'Receiving',
            self::PendingVarianceApproval => 'Pending Approval',
            self::Received                => 'Received',
            self::PutawayInProgress       => 'Putaway In Progress',
            self::PutawayComplete         => 'Putaway Complete',
            self::Closed                  => 'Closed',
            self::Cancelled               => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Draft                   => 'badge-slate',
            self::Receiving               => 'badge-brand',
            self::PendingVarianceApproval => 'badge-amber',
            self::Received                => 'badge-green',
            self::PutawayInProgress       => 'badge-brand',
            self::PutawayComplete         => 'badge-green',
            self::Closed                  => 'badge-neutral',
            self::Cancelled               => 'badge-neutral',
        };
    }
}
