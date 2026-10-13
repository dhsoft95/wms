<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Data\Inventory\StockLine;
use App\Data\Inventory\StockReference;
use App\Enums\GrnSource;
use App\Enums\GrnStatus;
use App\Enums\Inventory\StockStatus;
use App\Enums\PutawayTaskStatus;
use App\Enums\ReceiptCondition;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\GrnReceipt;
use App\Models\Inventory\Lpn;
use App\Models\Item;
use App\Models\Location;
use App\Models\Partner;
use App\Models\PutawayTask;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\Foundation\DocumentNumber;
use App\Services\Inventory\StockLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReceivingDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $admin     = User::where('email', 'davidhaule3@gmail.com')->first() ?? User::first();
            $warehouse = Warehouse::first();
            $vw        = VirtualWarehouse::first();
            $supplier  = Partner::where('code', 'like', 'SUP-%')->first();
            $pcs       = UnitOfMeasure::where('code', 'PCS')->first();

            if (! $warehouse || ! $vw || ! $pcs) {
                $this->command?->warn('ReceivingDemoSeeder: missing reference data — skipping.');
                return;
            }

            $items = Item::limit(3)->get();
            if ($items->isEmpty()) {
                $this->command?->warn('ReceivingDemoSeeder: no items found — skipping.');
                return;
            }

            $item1 = $items->first();
            $item2 = $items->count() > 1 ? $items->skip(1)->first() : $items->first();
            $item3 = $items->count() > 2 ? $items->skip(2)->first() : $items->first();

            // Dock and quarantine locations
            $dock = Location::where('warehouse_id', $warehouse->id)
                ->where('type', 'receiving_dock')
                ->where('status', 'active')
                ->first();

            $quarantine = Location::where('warehouse_id', $warehouse->id)
                ->where('type', 'quarantine')
                ->where('status', 'active')
                ->first();

            $storageBin = Location::where('warehouse_id', $warehouse->id)
                ->where('type', 'storage')
                ->where('status', 'active')
                ->first();

            if (! $dock) {
                $this->command?->warn('ReceivingDemoSeeder: no dock location found — skipping confirmed GRNs.');
            }

            // ── GRN 1: Draft ────────────────────────────────────────────────────────
            $grn1 = Grn::create([
                'number'              => DocumentNumber::next('GRN'),
                'source'              => GrnSource::Supplier->value,
                'supplier_partner_id' => $supplier?->id,
                'warehouse_id'        => $warehouse->id,
                'receiving_dock_location_id' => $dock?->id,
                'status'              => GrnStatus::Draft->value,
                'received_by'         => $admin->id,
            ]);

            GrnLine::create([
                'grn_id'              => $grn1->id,
                'item_id'             => $item1->id,
                'virtual_warehouse_id'=> $vw->id,
                'uom_id'              => $pcs->id,
                'expected_qty'        => 500,
            ]);

            GrnLine::create([
                'grn_id'              => $grn1->id,
                'item_id'             => $item2->id,
                'virtual_warehouse_id'=> $vw->id,
                'uom_id'              => $pcs->id,
                'expected_qty'        => 200,
            ]);

            // ── GRN 2: Receiving in Progress ────────────────────────────────────────
            $grn2 = Grn::create([
                'number'              => DocumentNumber::next('GRN'),
                'source'              => GrnSource::Supplier->value,
                'supplier_partner_id' => $supplier?->id,
                'warehouse_id'        => $warehouse->id,
                'receiving_dock_location_id' => $dock?->id,
                'status'              => GrnStatus::Receiving->value,
                'received_by'         => $admin->id,
            ]);

            $line2a = GrnLine::create([
                'grn_id'              => $grn2->id,
                'item_id'             => $item1->id,
                'virtual_warehouse_id'=> $vw->id,
                'uom_id'              => $pcs->id,
                'expected_qty'        => 300,
            ]);

            GrnReceipt::create([
                'grn_line_id' => $line2a->id,
                'qty'         => 150,
                'uom_id'      => $pcs->id,
                'condition'   => ReceiptCondition::Good->value,
                'received_by' => $admin->id,
            ]);
            $line2a->recalculateTotals();

            // ── GRN 3: Fully Received — stock posted ────────────────────────────────
            if ($dock) {
                $grn3 = Grn::create([
                    'number'              => DocumentNumber::next('GRN'),
                    'source'              => GrnSource::Supplier->value,
                    'supplier_partner_id' => $supplier?->id,
                    'warehouse_id'        => $warehouse->id,
                    'receiving_dock_location_id' => $dock->id,
                    'status'              => GrnStatus::Received->value,
                    'received_by'         => $admin->id,
                    'confirmed_by'        => $admin->id,
                    'confirmed_at'        => now()->subHours(2),
                ]);

                $line3a = GrnLine::create([
                    'grn_id'              => $grn3->id,
                    'item_id'             => $item2->id,
                    'virtual_warehouse_id'=> $vw->id,
                    'uom_id'              => $pcs->id,
                    'expected_qty'        => 100,
                    'received_good_qty'   => 100,
                    'received_damaged_qty'=> 0,
                    'variance_qty'        => 0,
                    'variance_type'       => 'none',
                ]);

                $receipt3 = GrnReceipt::create([
                    'grn_line_id' => $line3a->id,
                    'qty'         => 100,
                    'uom_id'      => $pcs->id,
                    'condition'   => ReceiptCondition::Good->value,
                    'received_by' => $admin->id,
                ]);

                // Post stock via StockLedger
                if ($storageBin ?? $dock) {
                    $postLocation = $storageBin ?? $dock;
                    $ref = new StockReference('grn', $grn3->id, $grn3->number);

                    try {
                        $sl = app(StockLedger::class);
                        $sl->receive(
                            new StockLine(
                                itemId:             $item2->id,
                                locationId:         $dock->id,
                                virtualWarehouseId: $vw->id,
                                stockStatus:        StockStatus::Available,
                                enteredQty:         '100',
                                enteredUomId:       $pcs->id,
                            ),
                            $ref,
                            "grn:{$grn3->id}:receipt:{$receipt3->id}:confirm:demo"
                        );
                    } catch (\Exception $e) {
                        // Skip if stock validation fails (missing UoM config etc.)
                    }
                }

                // Create putaway task
                if ($storageBin) {
                    PutawayTask::create([
                        'grn_id'               => $grn3->id,
                        'item_id'              => $item2->id,
                        'virtual_warehouse_id' => $vw->id,
                        'qty'                  => 100,
                        'from_location_id'     => $dock->id,
                        'suggested_location_id'=> $storageBin->id,
                        'status'               => PutawayTaskStatus::Open->value,
                    ]);
                }
            }

            // ── GRN 4: Closed ───────────────────────────────────────────────────────
            $grn4 = Grn::create([
                'number'              => DocumentNumber::next('GRN'),
                'source'              => GrnSource::Manual->value,
                'warehouse_id'        => $warehouse->id,
                'status'              => GrnStatus::Closed->value,
                'received_by'         => $admin->id,
                'confirmed_by'        => $admin->id,
                'confirmed_at'        => now()->subDays(3),
                'closed_at'           => now()->subDays(2),
            ]);

            GrnLine::create([
                'grn_id'              => $grn4->id,
                'item_id'             => $item3->id,
                'virtual_warehouse_id'=> $vw->id,
                'uom_id'              => $pcs->id,
                'expected_qty'        => 50,
                'received_good_qty'   => 48,
                'received_damaged_qty'=> 2,
                'variance_qty'        => 0,
                'variance_type'       => 'damaged',
            ]);
        });
    }
}
