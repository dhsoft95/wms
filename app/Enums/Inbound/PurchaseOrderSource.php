<?php

declare(strict_types=1);

namespace App\Enums\Inbound;

enum PurchaseOrderSource: string
{
    case Manual         = 'manual';
    case System         = 'system';
    case CustomerReturn = 'customer_return';
}
