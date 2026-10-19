<?php

declare(strict_types=1);

namespace App\Enums\Inbound;

enum ApprovalChainDocumentType: string
{
    case Po       = 'po';
    case Mrn      = 'mrn';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match($this) {
            self::Po       => 'Purchase Order',
            self::Mrn      => 'Material Receipt Note',
            self::Transfer => 'Transfer',
        };
    }
}
