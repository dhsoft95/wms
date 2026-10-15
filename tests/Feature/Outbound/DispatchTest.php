<?php

declare(strict_types=1);

use App\Data\Inventory\StockLine;
use App\Data\Inventory\StockReference;
use App\Enums\Inventory\ReservationStatus;
use App\Enums\Inventory\StockStatus;
use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Enums\Outbound\AllocationRule;
use App\Enums\Outbound\AllocationStatus;
use App\Enums\Outbound\DeliveryOrderPriority;
use App\Enums\Outbound\DeliveryOrderStatus;
use App\Enums\Outbound\DoLineStatus;
use App\Enums\Outbound\GatePassStatus;
use App\Enums\Outbound\PickListStatus;
use App\Enums\Outbound\PickTaskStatus;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderAllocation;
use App\Models\DeliveryOrderLine;
use App\Models\Department;
use App\Models\Dispatch;
use App\Models\DocumentSequence;
use App\Models\GatePass;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockReservation;
use App\Models\Item;
use App\Models\Location;
use App\Models\PickList;
use App\Models\PickTask;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedger;
use App\Services\Outbound\DispatchService;
use App\Services\Outbound\PickingService;
use Database\Seeders\FoundationDefaultsSeeder;
use Database\Seeders\RolePermissionSeeder;

// ─── setUp ────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(FoundationDefaultsSeeder::class);

    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    $this->dispatcher = User::factory()->create(['status' => 'active']);
    $this->dispatcher->assignRole('storekeeper');
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

function dspUom(): UnitOfMeasure
{
    return UnitOfMeasure::firstOrCreate(
        ['code' => 'PCS'],
        ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
    );
}

function dspItem(): Item
{
    return Item::create([
        'sku'          => 'DSP-' . uniqid(),
        'name'         => 'Dispatch Test Item',
        'base_uom_id'  => dspUom()->id,
        'is_active'    => true,
        'track_batch'  => false,
        'track_expiry' => false,
        'track_serial' => false,
    ]);
}

/**
 * @return array{0: Warehouse, 1: Location, 2: Location}
 */
function dspWarehouseWithLocations(): array
{
    $wh = Warehouse::firstOrCreate(
        ['code' => 'DSP-WH'],
        ['name' => 'Dispatch Warehouse', 'is_active' => true]
    );

    $staging = Location::firstOrCreate(
        ['code' => 'DSP-STAGING'],
        [
            'warehouse_id' => $wh->id,
            'segment_code' => 'S',
            'level'        => LocationLevel::Bin,
            'type'         => LocationType::Staging,
            'status'       => LocationStatus::Active,
            'is_pickable'  => false,
            'path'         => '/',
        ]
    );

    $bin = Location::firstOrCreate(
        ['code' => 'DSP-BIN-A'],
        [
            'warehouse_id' => $wh->id,
            'segment_code' => 'B',
            'level'        => LocationLevel::Bin,
            'type'         => LocationType::Storage,
            'status'       => LocationStatus::Active,
            'is_pickable'  => true,
            'path'         => '/',
        ]
    );

    if ($wh->default_staging_location_id !== $staging->id) {
        $wh->update(['default_staging_location_id' => $staging->id]);
        $wh->refresh();
    }

    return [$wh, $staging, $bin];
}

function dspVw(Warehouse $wh): VirtualWarehouse
{
    $dept = Department::firstOrCreate(
        ['code' => 'DSP-DEPT'],
        ['name' => 'Dispatch Dept', 'is_active' => true]
    );

    return VirtualWarehouse::firstOrCreate(
        ['code' => 'DSP-VW'],
        [
            'name'          => 'Dispatch VW',
            'department_id' => $dept->id,
            'is_active'     => true,
        ]
    );
}

/**
 * Build a fully picked DeliveryOrder so DispatchService can act on it.
 * Returns [DeliveryOrder, PickTask].
 *
 * @return array{0: DeliveryOrder, 1: PickTask}
 */
function buildPickedDo(User $actor): array
{
    [$wh, $staging, $bin] = dspWarehouseWithLocations();
    $vw   = dspVw($wh);
    $item = dspItem();

    // Post inbound stock to the bin
    $ledger = app(StockLedger::class);
    $ledger->receive(
        new StockLine(
            itemId:             $item->id,
            locationId:         $bin->id,
            virtualWarehouseId: $vw->id,
            stockStatus:        StockStatus::Available,
            enteredQty:         '10.0000',
            enteredUomId:       $item->base_uom_id,
        ),
        new StockReference('test', 1, 'DSP-IN-' . uniqid())
    );

    $balance = StockBalance::where('item_id', $item->id)
        ->where('location_id', $bin->id)
        ->first();

    $do = DeliveryOrder::create([
        'number'               => 'DO-DSP-' . uniqid(),
        'warehouse_id'         => $wh->id,
        'virtual_warehouse_id' => $vw->id,
        'department_id'        => $vw->department_id,
        'status'               => DeliveryOrderStatus::Picked,
        'priority'             => DeliveryOrderPriority::Normal,
        'allocation_rule'      => AllocationRule::Default,
        'requested_by'         => $actor->id,
        'required_date'        => now()->addDays(2)->toDateString(),
    ]);

    $line = DeliveryOrderLine::create([
        'delivery_order_id'  => $do->id,
        'line_no'            => 1,
        'item_id'            => $item->id,
        'uom_id'             => $item->base_uom_id,
        'requested_qty'      => '5.0000',
        'requested_qty_base' => '5.0000',
        'allocated_qty'      => '5.0000',
        'picked_qty'         => '5.0000',
        'status'             => DoLineStatus::Allocated,
    ]);

    // Create reservation (consumed, as pick has happened)
    $reservation = StockReservation::create([
        'stock_balance_id' => $balance->id,
        'item_id'          => $item->id,
        'location_id'      => $bin->id,
        'qty'              => '5.0000',
        'reference_type'   => 'delivery_order_line',
        'reference_id'     => $line->id,
        'status'           => ReservationStatus::Consumed,
    ]);

    $allocation = DeliveryOrderAllocation::create([
        'delivery_order_line_id' => $line->id,
        'stock_balance_id'       => $balance->id,
        'stock_reservation_id'   => $reservation->id,
        'item_id'                => $item->id,
        'location_id'            => $bin->id,
        'qty'                    => '5.0000',
        'status'                 => AllocationStatus::Picked,
    ]);

    // Post stock to staging (simulating what pick would have done)
    $ledger->receive(
        new StockLine(
            itemId:             $item->id,
            locationId:         $staging->id,
            virtualWarehouseId: $vw->id,
            stockStatus:        StockStatus::Available,
            enteredQty:         '5.0000',
            enteredUomId:       $item->base_uom_id,
        ),
        new StockReference('pick_sim', $allocation->id, 'PCK-SIM-' . uniqid())
    );

    $stagingBalance = StockBalance::where('item_id', $item->id)
        ->where('location_id', $staging->id)
        ->first();

    // Create pick list + task in Picked state
    $pickList = PickList::create([
        'number'            => 'PCK-DSP-' . uniqid(),
        'delivery_order_id' => $do->id,
        'status'            => PickListStatus::Completed,
        'assigned_to'       => $actor->id,
        'completed_at'      => now(),
    ]);

    $pickTask = PickTask::create([
        'pick_list_id'                 => $pickList->id,
        'delivery_order_allocation_id' => $allocation->id,
        'sequence'                     => 1,
        'location_id'                  => $bin->id,
        'item_id'                      => $item->id,
        'qty_to_pick'                  => '5.0000',
        'qty_picked'                   => '5.0000',
        'status'                       => PickTaskStatus::Picked,
        'picked_by'                    => $actor->id,
        'picked_at'                    => now(),
    ]);

    return [$do, $pickTask];
}

/** Default dispatch payload. */
function dspPayload(): array
{
    return [
        'vehicle_no'  => 'T-123-ABC',
        'driver_name' => 'John Doe',
        'driver_phone' => '+255700000001',
    ];
}

// ─── Test 1: Confirm dispatch creates dispatch and gate pass ───────────────────

test('test_confirm_dispatch_creates_dispatch_and_gate_pass', function () {
    [$do] = buildPickedDo($this->dispatcher);

    $service  = app(DispatchService::class);
    $dispatch = $service->confirmDispatch($do, dspPayload(), $this->dispatcher);

    $do->refresh();

    expect($dispatch)->toBeInstanceOf(Dispatch::class)
        ->and($dispatch->number)->toStartWith('DSP')
        ->and($do->status)->toBe(DeliveryOrderStatus::Dispatched);

    $gatePass = GatePass::where('dispatch_id', $dispatch->id)->first();
    expect($gatePass)->not->toBeNull()
        ->and($gatePass->number)->toStartWith('GP')
        ->and($gatePass->status)->toBe(GatePassStatus::Issued);
});

// ─── Test 2: Dispatch is idempotent ───────────────────────────────────────────

test('test_dispatch_is_idempotent', function () {
    [$do] = buildPickedDo($this->dispatcher);

    $requestId = 'req-dsp-' . uniqid();
    $service   = app(DispatchService::class);

    $dispatch1 = $service->confirmDispatch($do, dspPayload(), $this->dispatcher, $requestId);

    // Reload the DO to get the dispatched status
    $do->refresh();

    // Second call with same requestId — service must return the same dispatch
    $dispatch2 = $service->confirmDispatch($do, dspPayload(), $this->dispatcher, $requestId);

    expect($dispatch1->id)->toBe($dispatch2->id);

    // Exactly one gate pass should exist
    $gatePassCount = GatePass::where('dispatch_id', $dispatch1->id)->count();
    expect($gatePassCount)->toBe(1);
});

// ─── Test 3: Verify gate exit marks pass as exited ────────────────────────────

test('test_verify_gate_exit_marks_pass_as_exited', function () {
    [$do] = buildPickedDo($this->dispatcher);

    $service  = app(DispatchService::class);
    $dispatch = $service->confirmDispatch($do, dspPayload(), $this->dispatcher);

    $gatePass = GatePass::where('dispatch_id', $dispatch->id)->firstOrFail();
    expect($gatePass->status)->toBe(GatePassStatus::Issued);

    $updatedPass = $service->verifyGateExit($gatePass, $this->dispatcher);

    expect($updatedPass->status)->toBe(GatePassStatus::Exited)
        ->and($updatedPass->exited_at)->not->toBeNull()
        ->and($updatedPass->verified_by)->toBe($this->dispatcher->id);
});
