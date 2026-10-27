<?php

declare(strict_types=1);

namespace App\Enums\Inbound;

enum PurchaseOrderApprovalAction: string
{
    case Approved  = 'approved';
    case Rejected  = 'rejected';
    case Returned  = 'returned';
    case Forwarded = 'forwarded'; // HOD forwards warehouse send-back to requester

    public function label(): string
    {
        return match($this) {
            self::Approved  => 'Approved',
            self::Rejected  => 'Rejected',
            self::Returned  => 'Returned for Correction',
            self::Forwarded => 'Forwarded to Requester',
        };
    }
}
