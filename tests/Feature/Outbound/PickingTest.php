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
use App\Enums\Outbound\PickListStatus;
use App\Enums\Outbound\PickTaskStatus;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderAllocation;
use App\Models\DeliveryOrderLine;
use App\Models\Department;
use App\Models\DocumentSequence;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockReservation;
use App\Models\Item;
use App\Models\Location;
use App\Models\PickList;
use App\Models\PickTask;
use App\Models\ReasonCode;
use App\Models\StockCountRequest;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedger;
use App\Services\Outbound\PickingService;
use Database\Seeders\FoundationDefaultsSeeder;
use Database\Seeders\RolePermissionSeeder;

// ─── setUp ────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(FoundationDefaultsSeeder::class);

    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    $this->picker = User::factory()->create(['status' => 'active']);
    $this->picker->assignRole('storekeeper');
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

function pickUom(): UnitOfMeasure
{
    return UnitOfMeasure::firstOrCreate(
        ['code' => 'PCS'],
        ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
    );
}

function pickItem(): Item
{
    return Item::create([
        'sku'          => 'PICK-' . uniqid(),
        'name'         => 'Pick Test Item',
        'base_uom_id'  => pickUom()->id,
        'is_active'    => true,
        'track_batch'  => false,
        'track_expiry' => false,
        'track_serial' => false,
    ]);
}

/**
 * Build a warehouse with a staging location and return both.
 *
 * @return array{0: Warehouse, 1: Location}
 */
function pickWarehouseWithStaging(): array
{
    $wh = Warehouse::firstOrCreate(
        ['code' => 'PICK-WH'],
        ['name' => 'Pick Warehouse', 'is_active' => true]
    );

    $staging = Location::firstOrCreate(
        ['code' => 'PICK-STAGING'],
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

    if ($wh->default_staging_location_id !== $staging->id) {
        $wh->update(['default_staging_location_id' => $staging->id]);
        $wh->refresh();
    }

    return [$wh, $staging];
}

function pickVw(Warehouse $wh): VirtualWarehouse
{
    $dept = Department::firstOrCreate(
        ['code' => 'PICK-DEPT'],
        ['name' => 'Pick Dept', 'is_active' => true]
    );

    return VirtualWarehouse::firstOrCreate(
        ['code' => 'PICK-VW'],
        [
            'name'          => 'Pick VW',
            'department_id' => $dept->id,
            'is_active'     => true,
        ]
    );
}

function pickBinLocation(Warehouse $wh): Location
{
    return Location::create([
        'warehouse_id' => $wh->id,
        'code'         => 'PICK-BIN-' . uniqid(),
        'segment_code' => 'B',
        'level'        => LocationLevel::Bin,
        'type'         => LocationType::Storage,
        'status'       => LocationStatus::Active,
        'is_pickable'  => true,
        'path'         => '/',
    ]);
}

/**
 * Build a full allocated DO + pick-ready structure.
 * Returns [DeliveryOrder, DeliveryOrderAllocation, StockReservation, StockBalance].
 *
 * @return array{0: DeliveryOrder, 1: DeliveryOrderAllocation, 2: StockReservation, 3: StockBalance}
 */
function buildAllocatedDo(User $actor): array
{
    [$wh, $staging] = pickWarehouseWithStaging();
    $vw   = pickVw($wh);
    $item = pickItem();
    $bin  = pickBinLocation($wh);

    // Post real inbound stock so balance exists
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
        new StockReference('test', 1, 'PICK-IN-' . uniqid())
    );

    $balance = StockBalance::where('item_id', $item->id)
        ->where('location_id', $bin->id)
        ->first();

    $do = DeliveryOrder::create([
        'number'               => 'DO-PICK-' . uniqid(),
        'warehouse_id'         => $wh->id,
        'virtual_warehouse_id' => $vw->id,
        'department_id'        => $vw->department_id,
        'status'               => DeliveryOrderStatus::Allocated,
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
        'picked_qty'         => '0.0000',
        'status'             => DoLineStatus::Allocated,
    ]);

    $reservation = StockReservation::create([
        'stock_balance_id' => $balance->id,
        'item_id'          => $item->id,
        'location_id'      => $bin->id,
        'qty'              => '5.0000',
        'reference_type'   => 'delivery_order_line',
        'reference_id'     => $line->id,
        'status'           => ReservationStatus::Active,
    ]);

    $balance->update(['qty_allocated' => '5.0000']);
    $balance->refresh();

    $allocation = DeliveryOrderAllocation::create([
        'delivery_order_line_id' => $line->id,
        'stock_balance_id'       => $balance->id,
        'stock_reservation_id'   => $reservation->id,
        'item_id'                => $item->id,
        'location_id'            => $bin->id,
        'qty'                    => '5.0000',
        'status'                 => AllocationStatus::Reserved,
    ]);

    return [$do, $allocation, $reservation, $balance];
}

// ─── Test 1: Generate pick list creates tasks for all allocations ──────────────

test('test_generate_pick_list_creates_tasks_for_all_allocations', function () {
    [$do, $allocation] = buildAllocatedDo($this->picker);

    $service  = app(PickingService::class);
    $pickList = $service->generatePickList($do, $this->picker);

    expect($pickList)->toBeInstanceOf(PickList::class)
        ->and($pickList->number)->toStartWith('PCK')
        ->and($pickList->status)->toBe(PickListStatus::Open);

    $tasks = PickTask::where('pick_list_id', $pickList->id)->get();
    expect($tasks->count())->toBe(1)
        ->and((float) $tasks->first()->qty_to_pick)->toBe(5.0)
        ->and($tasks->first()->status)->toBe(PickTaskStatus::Open);
});

// ─── Test 2: Execute task posts two stock movements ────────────────────────────

test('test_execute_task_posts_two_stock_movements', function () {
    [$do, $allocation, $reservation, $balance] = buildAllocatedDo($this->picker);

    $service  = app(PickingService::class);
    $pickList = $service->generatePickList($do, $this->picker);
    $task     = PickTask::where('pick_list_id', $pickList->id)->firstOrFail();

    $updatedTask = $service->executeTask($task, '5.0000', $this->picker);

    $updatedTask->refresh();
    $tx = $updatedTask->stockTransaction;

    expect($updatedTask->status)->toBe(PickTaskStatus::Picked)
        ->and($tx)->not->toBeNull();

    // Two movements: bin debit + staging credit
    $movements = \App\Models\Inventory\StockMovement::where('stock_transaction_id', $tx->id)->get();
    expect($movements->count())->toBe(2);

    $netQty = $movements->sum('qty');
    expect(bccomp(number_format((float) $netQty, 4, '.', ''), '0.0000', 4))->toBe(0);
});

// ─── Test 3: Execute task releases reservation ────────────────────────────────

test('test_execute_task_releases_reservation', function () {
    [$do, $allocation, $reservation, $balance] = buildAllocatedDo($this->picker);

    $service  = app(PickingService::class);
    $pickList = $service->generatePickList($do, $this->picker);
    $task     = PickTask::where('pick_list_id', $pickList->id)->firstOrFail();

    $service->executeTask($task, '5.0000', $this->picker);

    $reservation->refresh();

    // After consuming the reservation during pick, it should be consumed/released
    expect($reservation->status)->toBe(ReservationStatus::Consumed);
});

// ─── Test 4: Short pick triggers stock count request ──────────────────────────

test('test_short_pick_triggers_stock_count_request', function () {
    [$do, $allocation, $reservation, $balance] = buildAllocatedDo($this->picker);

    // SP-SYSTM is seeded by FoundationDefaultsSeeder
    $reasonCode = ReasonCode::where('code', 'SP-SYSTM')->firstOrFail();

    $service  = app(PickingService::class);
    $pickList = $service->generatePickList($do, $this->picker);
    $task     = PickTask::where('pick_list_id', $pickList->id)->firstOrFail();

    $service->handleShort($task, '2.0000', $reasonCode->id, $this->picker);

    $task->refresh();

    expect($task->status)->toBe(PickTaskStatus::Short)
        ->and((float) $task->qty_short)->toBe(2.0);

    $countRequest = StockCountRequest::where('reference_type', 'pick_task')
        ->where('reference_id', $task->id)
        ->first();

    expect($countRequest)->not->toBeNull()
        ->and($countRequest->status)->toBe('open')
        ->and($countRequest->reason)->toBe('short_pick');
});
