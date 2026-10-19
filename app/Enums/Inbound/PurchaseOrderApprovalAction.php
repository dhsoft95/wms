<?php

declare(strict_types=1);

namespace App\Enums\Inbound;

enum PurchaseOrderApprovalAction: string
{
    case Approved  = 'approved';
    case Rejected  = 'rejected';
    case Returned  = 'returned';

    public function label(): string
    {
        return match($this) {
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Returned => 'Returned for Correction',
        };
    }
}
