<?php

declare(strict_types=1);

namespace App\Listeners\Inbound;

use App\Enums\GrnSource;
use App\Enums\GrnStatus;
use App\Events\Inbound\ContainerUnloadingStarted;
use App\Models\Grn;
use App\Services\Foundation\DocumentNumber;
use Illuminate\Support\Facades\DB;

final class CreateGrnFromContainer
{
    /**
     * Idempotently creates a GRN for the container when unloading starts.
     */
    public function handle(ContainerUnloadingStarted $event): void
    {
        $container = $event->container;

        // Idempotency check — already has a non-cancelled GRN
        $exists = Grn::where('container_id', $container->id)
            ->where('status', '!=', GrnStatus::Cancelled->value)
            ->exists();

        if ($exists) {
            return;
        }

        DB::transaction(function () use ($container) {
            $number = DocumentNumber::next('GRN');

            // Determine warehouse from yard slot location or from the first VW's associated warehouse
            $warehouseId = null;
            if ($container->yard_location_id) {
                $warehouseId = \App\Models\Location::find($container->yard_location_id)?->warehouse_id;
            }
            if (! $warehouseId) {
                // Fall back to first warehouse
                $warehouseId = \App\Models\Warehouse::first()?->id;
            }

            $grn = Grn::create([
                'number'                     => $number,
                'source'                     => GrnSource::Container->value,
                'container_id'               => $container->id,
                'supplier_partner_id'        => $container->shipper_partner_id ?? null,
                'warehouse_id'               => $warehouseId,
                'receiving_dock_location_id' => null,
                'status'                     => GrnStatus::Draft->value,
            ]);

            // Copy expected lines to GRN lines
            foreach ($container->expectedLines as $expectedLine) {
                \App\Models\GrnLine::create([
                    'grn_id'                    => $grn->id,
                    'container_expected_line_id'=> $expectedLine->id,
                    'item_id'                   => $expectedLine->item_id,
                    'virtual_warehouse_id'      => $expectedLine->virtual_warehouse_id,
                    'uom_id'                    => $expectedLine->uom_id,
                    'expected_qty'              => $expectedLine->qty,
                ]);
            }
        });
    }
}
