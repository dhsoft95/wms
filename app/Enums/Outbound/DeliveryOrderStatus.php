<?php

declare(strict_types=1);

namespace App\Enums\Outbound;

enum DeliveryOrderStatus: string
{
    case Draft              = 'draft';
    case Submitted          = 'submitted';
    case Approved           = 'approved';
    case Rejected           = 'rejected';
    case Allocated          = 'allocated';
    case PartiallyAllocated = 'partially_allocated';
    case Picking            = 'picking';
    case Picked             = 'picked';
    case Dispatched         = 'dispatched';
    case Delivered          = 'delivered';
    case Cancelled          = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Draft              => 'Draft',
            self::Submitted          => 'Submitted',
            self::Approved           => 'Approved',
            self::Rejected           => 'Rejected',
            self::Allocated          => 'Allocated',
            self::PartiallyAllocated => 'Partially Allocated',
            self::Picking            => 'Picking',
            self::Picked             => 'Picked',
            self::Dispatched         => 'Dispatched',
            self::Delivered          => 'Delivered',
            self::Cancelled          => 'Cancelled',
        };
    }

    public function badge(): string
    {
        return match($this) {
            self::Draft              => 'badge-slate',
            self::Submitted          => 'badge-amber',
            self::Approved           => 'badge-blue',
            self::Rejected           => 'badge-red',
            self::Allocated          => 'badge-cyan',
            self::PartiallyAllocated => 'badge-orange',
            self::Picking            => 'badge-violet',
            self::Picked             => 'badge-green',
            self::Dispatched         => 'badge-emerald',
            self::Delivered          => 'badge-teal',
            self::Cancelled          => 'badge-red',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        $allowed = match($this) {
            self::Draft              => [self::Submitted, self::Cancelled],
            self::Submitted          => [self::Approved, self::Rejected, self::Cancelled],
            self::Approved           => [self::Allocated, self::PartiallyAllocated, self::Cancelled],
            self::PartiallyAllocated => [self::Allocated, self::Picking, self::Cancelled],
            self::Allocated          => [self::Picking, self::Cancelled],
            self::Picking            => [self::Picked],
            self::Picked             => [self::Dispatched],
            self::Dispatched         => [self::Delivered],
            self::Rejected           => [self::Draft],
            default                  => [],
        };

        return in_array($target, $allowed, true);
    }
}
