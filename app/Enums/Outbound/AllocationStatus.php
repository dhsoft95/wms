<?php

declare(strict_types=1);

namespace App\Enums\Outbound;

enum AllocationStatus: string
{
    case Reserved = 'reserved';
    case Picked   = 'picked';
    case Released = 'released';
}
