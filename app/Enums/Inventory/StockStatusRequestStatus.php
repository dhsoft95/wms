<?php

declare(strict_types=1);

namespace App\Enums\Inventory;

enum StockStatusRequestStatus: string
{
    case Draft                            = 'draft';
    case Submitted                        = 'submitted';                        // awaiting dept head
    case PendingWarehouseManagerApproval  = 'pending_warehouse_manager_approval'; // awaiting WM
    case Approved                         = 'approved';
    case Rejected                         = 'rejected';

    public function canTransitionTo(self $new): bool
    {
        return match($this) {
            self::Draft     => $new === self::Submitted,
            self::Submitted => in_array($new, [self::PendingWarehouseManagerApproval, self::Rejected], true),
            self::PendingWarehouseManagerApproval
                            => in_array($new, [self::Approved, self::Submitted, self::Rejected], true),
            self::Approved,
            self::Rejected  => false,
        };
    }

    public function isAwaitingApproval(): bool
    {
        return in_array($this, [self::Submitted, self::PendingWarehouseManagerApproval], true);
    }

    public function label(): string
    {
        return match($this) {
            self::Draft                           => 'Draft',
            self::Submitted                       => 'Pending Dept Head Approval',
            self::PendingWarehouseManagerApproval => 'Pending Warehouse Manager Approval',
            self::Approved                        => 'Approved',
            self::Rejected                        => 'Rejected',
        };
    }

    public function badge(): string
    {
        return match($this) {
            self::Draft                           => 'badge-neutral',
            self::Submitted                       => 'badge-warning',
            self::PendingWarehouseManagerApproval => 'badge-warning',
            self::Approved                        => 'badge-success',
            self::Rejected                        => 'badge-error',
        };
    }
}
