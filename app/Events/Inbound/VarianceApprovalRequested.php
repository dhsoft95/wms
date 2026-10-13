<?php

declare(strict_types=1);

namespace App\Events\Inbound;

use App\Models\Grn;
use App\Models\GrnVarianceApproval;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class VarianceApprovalRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Grn $grn,
        public readonly GrnVarianceApproval $approval,
    ) {}
}
