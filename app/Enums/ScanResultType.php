<?php

declare(strict_types=1);

namespace App\Enums;

enum ScanResultType: string
{
    case Lpn           = 'lpn';
    case Container     = 'container';
    case Grn             = 'grn';
    case PurchaseOrder   = 'purchase_order';
    case DispatchOrder   = 'dispatch_order';
    case Location      = 'location';
    case Item          = 'item';
    case Serial        = 'serial';
    case Ambiguous     = 'ambiguous';
    case Unknown       = 'unknown';
}
