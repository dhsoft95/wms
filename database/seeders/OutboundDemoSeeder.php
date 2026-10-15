<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Outbound\AllocationRule;
use App\Enums\Outbound\DeliveryOrderPriority;
use App\Enums\Outbound\DeliveryOrderStatus;
use App\Enums\Outbound\DoLineStatus;
use App\Enums\Outbound\GatePassStatus;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderApproval;
use App\Models\DeliveryOrderLine;
use App\Models\Dispatch;
use App\Models\GatePass;
use App\Models\Item;
use App\Models\Partner;
use App\Models\ProofOfDelivery;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OutboundDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            // ── Idempotency: remove any previously seeded demo DOs ──────────────────
            $existing = DeliveryOrder::withTrashed()
                ->where('number', 'like', 'DO-DEMO-%')
                ->get();

            foreach ($existing as $do) {
                // Remove child records first (hard-delete to avoid FK issues)
                foreach ($do->dispatches as $dispatch) {
                    $dispatch->gatePass?->forceDelete();
                    $dispatch->forceDelete();
                }
                $do->proofOfDelivery?->forceDelete();
                $do->approvals()->delete();
                $do->lines()->delete();
                $do->forceDelete();
            }

            // ── Load reference records ───────────────────────────────────────────────
            $vw        = VirtualWarehouse::first();
            $warehouse = Warehouse::first();
            $consignee = Partner::first();
            $item      = Item::with('baseUom')->first();
            $user      = User::where('email', 'davidhaule3@gmail.com')->first() ?? User::first();

            // Guard: skip everything if core records are missing
            if (! $vw || ! $warehouse || ! $item || ! $user) {
                $this->command?->warn(
                    'OutboundDemoSeeder: missing VirtualWarehouse, Warehouse, Item, or User — skipping all DOs.'
                );
                return;
            }

            $baseUomId = $item->base_uom_id;

            // ── DO-DEMO-001 · Draft ──────────────────────────────────────────────────
            $this->command?->info('OutboundDemoSeeder: creating DO-DEMO-001 (Draft)…');

            $do1 = DeliveryOrder::create([
                'number'               => 'DO-DEMO-001',
                'virtual_warehouse_id' => $vw->id,
                'warehouse_id'         => $warehouse->id,
                'consignee_partner_id' => $consignee?->id,
                'required_date'        => now()->addDays(7)->toDateString(),
                'priority'             => DeliveryOrderPriority::Normal,
                'allocation_rule'      => AllocationRule::Default,
                'status'               => DeliveryOrderStatus::Draft,
                'requested_by'         => $user->id,
                'remarks'              => 'Demo DO — Draft state',
            ]);

            DeliveryOrderLine::create([
                'delivery_order_id'  => $do1->id,
                'line_no'            => 1,
                'item_id'            => $item->id,
                'uom_id'             => $baseUomId,
                'requested_qty'      => 50,
                'requested_qty_base' => 50,
                'status'             => DoLineStatus::Pending,
            ]);

            DeliveryOrderLine::create([
                'delivery_order_id'  => $do1->id,
                'line_no'            => 2,
                'item_id'            => $item->id,
                'uom_id'             => $baseUomId,
                'requested_qty'      => 50,
                'requested_qty_base' => 50,
                'status'             => DoLineStatus::Pending,
            ]);

            // ── DO-DEMO-002 · Submitted ──────────────────────────────────────────────
            $this->command?->info('OutboundDemoSeeder: creating DO-DEMO-002 (Submitted)…');

            $do2 = DeliveryOrder::create([
                'number'               => 'DO-DEMO-002',
                'virtual_warehouse_id' => $vw->id,
                'warehouse_id'         => $warehouse->id,
                'consignee_partner_id' => $consignee?->id,
                'required_date'        => now()->addDays(5)->toDateString(),
                'priority'             => DeliveryOrderPriority::High,
                'allocation_rule'      => AllocationRule::Default,
                'status'               => DeliveryOrderStatus::Submitted,
                'requested_by'         => $user->id,
                'submitted_at'         => now()->subHour(),
                'remarks'              => 'Demo DO — Submitted state, awaiting approval',
            ]);

            DeliveryOrderLine::create([
                'delivery_order_id'  => $do2->id,
                'line_no'            => 1,
                'item_id'            => $item->id,
                'uom_id'             => $baseUomId,
                'requested_qty'      => 100,
                'requested_qty_base' => 100,
                'status'             => DoLineStatus::Pending,
            ]);

            // Pending approval record (no decision yet)
            DeliveryOrderApproval::create([
                'delivery_order_id' => $do2->id,
                'level'             => 1,
                'approver_role'     => 'department',
                'decided_by'        => null,
                'decision'          => null,
                'notes'             => null,
                'decided_at'        => null,
            ]);

            // ── DO-DEMO-003 · Approved ───────────────────────────────────────────────
            $this->command?->info('OutboundDemoSeeder: creating DO-DEMO-003 (Approved)…');

            $do3 = DeliveryOrder::create([
                'number'               => 'DO-DEMO-003',
                'virtual_warehouse_id' => $vw->id,
                'warehouse_id'         => $warehouse->id,
                'consignee_partner_id' => $consignee?->id,
                'required_date'        => now()->addDays(2)->toDateString(),
                'priority'             => DeliveryOrderPriority::Urgent,
                'allocation_rule'      => AllocationRule::Fifo,
                'status'               => DeliveryOrderStatus::Approved,
                'requested_by'         => $user->id,
                'submitted_at'         => now()->subHours(3),
                'remarks'              => 'Demo DO — Approved, ready for allocation',
            ]);

            DeliveryOrderLine::create([
                'delivery_order_id'  => $do3->id,
                'line_no'            => 1,
                'item_id'            => $item->id,
                'uom_id'             => $baseUomId,
                'requested_qty'      => 200,
                'requested_qty_base' => 200,
                'status'             => DoLineStatus::Pending,
            ]);

            // ── DO-DEMO-004 · Allocated ──────────────────────────────────────────────
            $this->command?->info('OutboundDemoSeeder: creating DO-DEMO-004 (Allocated)…');

            $do4 = DeliveryOrder::create([
                'number'               => 'DO-DEMO-004',
                'virtual_warehouse_id' => $vw->id,
                'warehouse_id'         => $warehouse->id,
                'consignee_partner_id' => $consignee?->id,
                'required_date'        => now()->addDays(4)->toDateString(),
                'priority'             => DeliveryOrderPriority::Normal,
                'allocation_rule'      => AllocationRule::Default,
                'status'               => DeliveryOrderStatus::Allocated,
                'requested_by'         => $user->id,
                'submitted_at'         => now()->subHours(5),
                'remarks'              => 'Demo DO — Stock allocated, awaiting picking',
            ]);

            DeliveryOrderLine::create([
                'delivery_order_id'  => $do4->id,
                'line_no'            => 1,
                'item_id'            => $item->id,
                'uom_id'             => $baseUomId,
                'requested_qty'      => 30,
                'requested_qty_base' => 30,
                'allocated_qty'      => 30,
                'status'             => DoLineStatus::Allocated,
            ]);

            // ── DO-DEMO-005 · Dispatched ─────────────────────────────────────────────
            $this->command?->info('OutboundDemoSeeder: creating DO-DEMO-005 (Dispatched)…');

            $do5 = DeliveryOrder::create([
                'number'               => 'DO-DEMO-005',
                'virtual_warehouse_id' => $vw->id,
                'warehouse_id'         => $warehouse->id,
                'consignee_partner_id' => $consignee?->id,
                'required_date'        => now()->addDay()->toDateString(),
                'priority'             => DeliveryOrderPriority::High,
                'allocation_rule'      => AllocationRule::Default,
                'status'               => DeliveryOrderStatus::Dispatched,
                'requested_by'         => $user->id,
                'submitted_at'         => now()->subHours(8),
                'remarks'              => 'Demo DO — Truck on the way',
            ]);

            DeliveryOrderLine::create([
                'delivery_order_id'  => $do5->id,
                'line_no'            => 1,
                'item_id'            => $item->id,
                'uom_id'             => $baseUomId,
                'requested_qty'      => 50,
                'requested_qty_base' => 50,
                'allocated_qty'      => 50,
                'picked_qty'         => 50,
                'dispatched_qty'     => 50,
                'status'             => DoLineStatus::Dispatched,
            ]);

            $dispatch5 = Dispatch::create([
                'number'            => 'DISP-DEMO-005',
                'delivery_order_id' => $do5->id,
                'vehicle_no'        => 'T123ABC',
                'driver_name'       => 'John Doe',
                'driver_phone'      => '+60123456789',
                'dispatched_at'     => now()->subHours(2),
                'dispatched_by'     => $user->id,
            ]);

            GatePass::create([
                'number'      => 'GP-DEMO-005',
                'dispatch_id' => $dispatch5->id,
                'status'      => GatePassStatus::Issued,
            ]);

            // ── DO-DEMO-006 · Delivered ──────────────────────────────────────────────
            $this->command?->info('OutboundDemoSeeder: creating DO-DEMO-006 (Delivered)…');

            $do6 = DeliveryOrder::create([
                'number'               => 'DO-DEMO-006',
                'virtual_warehouse_id' => $vw->id,
                'warehouse_id'         => $warehouse->id,
                'consignee_partner_id' => $consignee?->id,
                'required_date'        => now()->subDay()->toDateString(),
                'priority'             => DeliveryOrderPriority::Normal,
                'allocation_rule'      => AllocationRule::Default,
                'status'               => DeliveryOrderStatus::Delivered,
                'requested_by'         => $user->id,
                'submitted_at'         => now()->subHours(10),
                'remarks'              => 'Demo DO — Delivered and confirmed',
            ]);

            DeliveryOrderLine::create([
                'delivery_order_id'  => $do6->id,
                'line_no'            => 1,
                'item_id'            => $item->id,
                'uom_id'             => $baseUomId,
                'requested_qty'      => 25,
                'requested_qty_base' => 25,
                'allocated_qty'      => 25,
                'picked_qty'         => 25,
                'dispatched_qty'     => 25,
                'status'             => DoLineStatus::Dispatched,
            ]);

            $dispatch6 = Dispatch::create([
                'number'            => 'DISP-DEMO-006',
                'delivery_order_id' => $do6->id,
                'vehicle_no'        => 'T456DEF',
                'driver_name'       => 'Ali Hassan',
                'driver_phone'      => '+60198765432',
                'dispatched_at'     => now()->subHours(4),
                'dispatched_by'     => $user->id,
            ]);

            GatePass::create([
                'number'      => 'GP-DEMO-006',
                'dispatch_id' => $dispatch6->id,
                'status'      => GatePassStatus::Exited,
                'exited_at'   => now()->subHour(),
                'verified_by' => $user->id,
            ]);

            ProofOfDelivery::create([
                'delivery_order_id' => $do6->id,
                'received_by_name'  => 'Jane Customer',
                'received_at'       => now()->subHour(),
                'notes'             => 'Condition: good. All items received.',
                'uploaded_by'       => $user->id,
            ]);

            $this->command?->info('OutboundDemoSeeder: all 6 demo DOs created successfully.');
        });
    }
}
