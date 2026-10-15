<?php

declare(strict_types=1);

namespace App\Services\Outbound;

use App\Enums\GrnSource;
use App\Enums\GrnStatus;
use App\Models\DeliveryOrder;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\User;
use App\Services\Foundation\DocumentNumber;
use Illuminate\Support\Facades\DB;

final class ReturnService
{
    public function createReturnGrn(DeliveryOrder $do, User $by): Grn
    {
        return DB::transaction(function () use ($do, $by) {
            $grn = Grn::create([
                'number'            => DocumentNumber::next('GRN'),
                'source'            => GrnSource::Return,
                'warehouse_id'      => $do->warehouse_id,
                'delivery_order_id' => $do->id,
                'status'            => GrnStatus::Draft,
                'received_by'       => $by->id,
            ]);

            // Pre-fill lines from dispatched allocations
            foreach ($do->lines()->with('allocations.batch', 'item', 'uom')->get() as $doLine) {
                if (bccomp((string) $doLine->dispatched_qty, '0', 4) <= 0) {
                    continue;
                }

                GrnLine::create([
                    'grn_id'               => $grn->id,
                    'item_id'              => $doLine->item_id,
                    'virtual_warehouse_id' => $do->virtual_warehouse_id,
                    'uom_id'               => $doLine->uom_id,
                    'expected_qty'         => $doLine->dispatched_qty,
                    'remarks'              => "Return from {$do->number}",
                ]);
            }

            return $grn;
        });
    }
}
