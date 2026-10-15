<?php

declare(strict_types=1);

namespace App\Enums\Outbound;

enum PickTaskStatus: string
{
    case Open      = 'open';
    case Picked    = 'picked';
    case Short     = 'short';
    case Cancelled = 'cancelled';
}
