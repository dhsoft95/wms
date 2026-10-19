<?php

declare(strict_types=1);

use App\Data\Inventory\StockLine;
use App\Data\Inventory\StockPosting;
use App\Data\Inventory\StockReference;
use App\Enums\Inventory\MovementType;
use App\Enums\Inventory\StockStatus;
use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Enums\Outbound\AllocationRule;
use App\Enums\Outbound\AllocationStatus;
use App\Enums\Outbound\DeliveryOrderPriority;
use App\Enums\Outbound\DeliveryOrderStatus;
use App\Enums\Outbound\PickTaskStatus;
use App\Models\Container;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderAllocation;
use App\Models\DeliveryOrderLine;
use App\Models\GrnLine;
use App\Models\GrnReceipt;
use App\Models\Inventory\StockBalance;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(FoundationDefaultsSeeder::class);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    $this->manager = User::factory()->create(['status' => 'active']);
    $this->manager->assignRole('warehouse-manager');

    $this->uom = UnitOfMeasure::firstOrCreate(
        ['code' => 'PCS'],
        ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
    );

    $this->warehouse = Warehouse::factory()->create();
    $this->vw        = VirtualWarehouse::factory()->create(['is_active' => true]);
});

// ─── Rule 1: UoM EA exists after seeder ───────────────────────────────────────

it('seeds EA unit of measure', function () {
    $ea = UnitOfMeasure::where('code', 'EA')->first();
    expect($ea)->not->toBeNull()
        ->and($ea->name)->toBe('Each');
});

// ─── Rule 2: Container stores PO number and file path ─────────────────────────

it('stores po_number and po_file_path on container', function () {
    Storage::fake('local');

    $container = Container::factory()->create([
        'po_number'    => 'PO-2026-TEST-001',
        'po_file_path' => 'containers/po/test.pdf',
    ]);

    $container->refresh();
    expect($container->po_number)->toBe('PO-2026-TEST-001')
        ->and($container->po_file_path)->toBe('containers/po/test.pdf');
});

// ─── Rule 3: GRN serials_missing flag is set correctly ────────────────────────

it('sets serials_missing on grn_line when receipt has null serial_nos for serial-tracked item', function () {
    $serialItem = Item::factory()->create([
        'is_active'    => true,
        'track_serial' => true,
        'track_batch'  => false,
        'base_uom_id'  => $this->uom->id,
    ]);

    // Create a minimal GRN line
    $grn = \App\Models\Grn::factory()->create(['status' => \App\Enums\GrnStatus::Receiving]);

    $line = GrnLine::create([
        'grn_id'               => $grn->id,
        'item_id'              => $serialItem->id,
        'virtual_warehouse_id' => $this->vw->id,
        'uom_id'               => $this->uom->id,
        'expected_qty'         => '5.0000',
        'received_good_qty'    => '0.0000',
        'received_damaged_qty' => '0.0000',
        'variance_qty'         => '0.0000',
        'is_unexpected'        => false,
    ]);

    // Add receipt without serials (simulating serials_missing=true)
    GrnReceipt::create([
        'grn_line_id' => $line->id,
        'qty'         => '3.0000',
        'uom_id'      => $this->uom->id,
        'condition'   => 'good',
        'serial_nos'  => null,
        'received_by' => $this->manager->id,
    ]);

    $line->recalculateTotals();
    $line->refresh();

    expect($line->serials_missing)->toBeTrue()
        ->and((float) $line->serials_missing_qty)->toBe(3.0);
});

// ─── Rule 4: attachSerials posts a zero-qty SerialCapture transaction ─────────

it('attachSerials creates a serial_capture transaction without changing qty_on_hand', function () {
    $serialItem = Item::factory()->create([
        'is_active'    => true,
        'track_serial' => true,
        'track_batch'  => false,
        'base_uom_id'  => $this->uom->id,
    ]);

    $bin = Location::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'level'        => LocationLevel::Bin,
        'type'         => LocationType::Storage,
        'status'       => LocationStatus::Active,
    ]);

    // Seed 2 units without serials
    $ledger = app(StockLedger::class);
    $ledger->post(new StockPosting(
        type: MovementType::OpeningBalance,
        reference: new StockReference(),
        lines: [
            new StockLine(
                itemId: $serialItem->id,
                locationId: $bin->id,
                virtualWarehouseId: $this->vw->id,
                stockStatus: StockStatus::Available,
                enteredQty: '2.0000',
                enteredUomId: $this->uom->id,
            ),
        ],
    ));

    $key = new \App\Data\Inventory\StockKey(
        itemId: $serialItem->id,
        batchId: null,
        lpnId: null,
        locationId: $bin->id,
        virtualWarehouseId: $this->vw->id,
        stockStatus: StockStatus::Available,
    );

    $balanceBefore = (float) StockBalance::where('key_hash', $key->hash())->value('qty_on_hand');

    $ledger->attachSerials($key, ['SN-A', 'SN-B'], new StockReference(), $this->manager);

    $balanceAfter = (float) StockBalance::where('key_hash', $key->hash())->value('qty_on_hand');

    expect($balanceAfter)->toBe($balanceBefore); // qty unchanged

    // Serials should now be in stock
    $snA = \App\Models\Inventory\SerialNumber::where('item_id', $serialItem->id)->where('serial_no', 'SN-A')->first();
    expect($snA)->not->toBeNull()
        ->and($snA->status->value)->toBe('in_stock');
});

// ─── Rule 5: Dispatch is blocked if picked_qty != ordered_qty ─────────────────

it('blocks dispatch when a line has picked_qty less than ordered_qty', function () {
    $item = Item::factory()->create([
        'is_active'    => true,
        'track_batch'  => false,
        'track_serial' => false,
        'base_uom_id'  => $this->uom->id,
    ]);

    // Create a DO in Picked status with mismatched qtys
    $do = DeliveryOrder::create([
        'number'               => 'DO-DISP-' . uniqid(),
        'status'               => DeliveryOrderStatus::Picked,
        'virtual_warehouse_id' => $this->vw->id,
        'warehouse_id'         => $this->warehouse->id,
        'priority'             => DeliveryOrderPriority::Normal,
        'allocation_rule'      => AllocationRule::Fefo,
        'requested_by'         => $this->manager->id,
        'required_date'        => now()->addDays(2)->toDateString(),
    ]);

    DeliveryOrderLine::create([
        'delivery_order_id'  => $do->id,
        'line_no'            => 1,
        'item_id'            => $item->id,
        'uom_id'             => $this->uom->id,
        'requested_qty'      => '10.0000',
        'requested_qty_base' => '10.0000',
        'picked_qty'         => '8.0000',  // only 8 of 10 picked
        'status'             => \App\Enums\Outbound\DoLineStatus::Picking,
    ]);

    expect(fn () => app(DispatchService::class)->confirmDispatch($do->fresh(), [
        'vehicle_no'   => 'T123',
        'driver_name'  => 'John',
        'driver_phone' => '0700000000',
    ], $this->manager))
        ->toThrow(\DomainException::class, 'Dispatch blocked');
});

// ─── Rule 6: Dispatch succeeds when all lines fully picked ────────────────────

it('allows dispatch when all lines are fully picked', function () {
    $item = Item::factory()->create([
        'is_active'    => true,
        'track_batch'  => false,
        'track_serial' => false,
        'base_uom_id'  => $this->uom->id,
    ]);

    $stagingLocation = Location::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'level'        => LocationLevel::Bin,
        'type'         => LocationType::Staging,
        'status'       => LocationStatus::Active,
        'code'         => 'STAGE-001',
        'path'         => '/',
    ]);

    $this->warehouse->update(['default_staging_location_id' => $stagingLocation->id]);

    // Seed stock in staging
    $ledger = app(StockLedger::class);
    $ledger->post(new StockPosting(
        type: MovementType::OpeningBalance,
        reference: new StockReference(),
        lines: [
            new StockLine(
                itemId: $item->id,
                locationId: $stagingLocation->id,
                virtualWarehouseId: $this->vw->id,
                stockStatus: StockStatus::Available,
                enteredQty: '5.0000',
                enteredUomId: $this->uom->id,
            ),
        ],
    ));

    $do = DeliveryOrder::create([
        'number'               => 'DO-DISP2-' . uniqid(),
        'status'               => DeliveryOrderStatus::Picked,
        'virtual_warehouse_id' => $this->vw->id,
        'warehouse_id'         => $this->warehouse->id,
        'priority'             => DeliveryOrderPriority::Normal,
        'allocation_rule'      => AllocationRule::Fefo,
        'requested_by'         => $this->manager->id,
        'required_date'        => now()->addDays(2)->toDateString(),
    ]);

    $doLine = DeliveryOrderLine::create([
        'delivery_order_id'  => $do->id,
        'line_no'            => 1,
        'item_id'            => $item->id,
        'uom_id'             => $this->uom->id,
        'requested_qty'      => '5.0000',
        'requested_qty_base' => '5.0000',
        'allocated_qty'      => '5.0000',
        'picked_qty'         => '5.0000',
        'status'             => \App\Enums\Outbound\DoLineStatus::Picked,
    ]);

    $allocation = DeliveryOrderAllocation::create([
        'delivery_order_line_id' => $doLine->id,
        'stock_balance_id'       => StockBalance::where('item_id', $item->id)->first()->id,
        'location_id'            => $stagingLocation->id,
        'item_id'                => $item->id,
        'qty'                    => '5.0000',
        'status'                 => AllocationStatus::Picked,
    ]);

    $pickList = PickList::create([
        'number'            => 'PCK-' . uniqid(),
        'delivery_order_id' => $do->id,
        'status'            => \App\Enums\Outbound\PickListStatus::Completed,
    ]);

    PickTask::create([
        'pick_list_id'                 => $pickList->id,
        'delivery_order_allocation_id' => $allocation->id,
        'sequence'                     => 1,
        'location_id'                  => $stagingLocation->id,
        'item_id'                      => $item->id,
        'qty_to_pick'                  => '5.0000',
        'qty_picked'                   => '5.0000',
        'status'                       => PickTaskStatus::Picked,
    ]);

    $dispatch = app(DispatchService::class)->confirmDispatch($do->fresh(), [
        'vehicle_no'   => 'T123',
        'driver_name'  => 'John',
        'driver_phone' => '0700000000',
    ], $this->manager);

    expect($dispatch)->not->toBeNull()
        ->and($dispatch->delivery_order_id)->toBe($do->id);
});

// ─── Rule 7: unconfirmTask reverses pick and resets task to Open ──────────────

it('unconfirmTask reverses stock movement and resets task to open', function () {
    $item = Item::factory()->create([
        'is_active'    => true,
        'track_batch'  => false,
        'track_serial' => false,
        'base_uom_id'  => $this->uom->id,
    ]);

    $binLoc = Location::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'level'        => LocationLevel::Bin,
        'type'         => LocationType::Storage,
        'status'       => LocationStatus::Active,
    ]);

    $stagingLoc = Location::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'level'        => LocationLevel::Bin,
        'type'         => LocationType::Staging,
        'status'       => LocationStatus::Active,
        'code'         => 'STG-UNCONF',
    ]);
    $this->warehouse->update(['default_staging_location_id' => $stagingLoc->id]);

    // Seed 10 units in bin
    $ledger = app(StockLedger::class);
    $ledger->post(new StockPosting(
        type: MovementType::OpeningBalance,
        reference: new StockReference(),
        lines: [
            new StockLine(
                itemId: $item->id,
                locationId: $binLoc->id,
                virtualWarehouseId: $this->vw->id,
                stockStatus: StockStatus::Available,
                enteredQty: '10.0000',
                enteredUomId: $this->uom->id,
            ),
        ],
    ));

    $binBalance = StockBalance::where('item_id', $item->id)
        ->where('location_id', $binLoc->id)
        ->first();

    // Create reservation
    $reservation = app(\App\Services\Inventory\StockReservations::class)
        ->reserve($binBalance, '3.0000', 'delivery_order_allocation', 999);

    $do = DeliveryOrder::create([
        'number'               => 'DO-UNCONF-' . uniqid(),
        'status'               => DeliveryOrderStatus::Picking,
        'virtual_warehouse_id' => $this->vw->id,
        'warehouse_id'         => $this->warehouse->id,
        'priority'             => DeliveryOrderPriority::Normal,
        'allocation_rule'      => AllocationRule::Fefo,
        'requested_by'         => $this->manager->id,
        'required_date'        => now()->addDays(2)->toDateString(),
    ]);

    $doLine = DeliveryOrderLine::create([
        'delivery_order_id'  => $do->id,
        'line_no'            => 1,
        'item_id'            => $item->id,
        'uom_id'             => $this->uom->id,
        'requested_qty'      => '3.0000',
        'requested_qty_base' => '3.0000',
        'picked_qty'         => '0.0000',
        'status'             => \App\Enums\Outbound\DoLineStatus::Allocated,
    ]);

    $allocation = DeliveryOrderAllocation::create([
        'delivery_order_line_id' => $doLine->id,
        'stock_balance_id'       => $binBalance->id,
        'stock_reservation_id'   => $reservation->id,
        'location_id'            => $binLoc->id,
        'item_id'                => $item->id,
        'qty'                    => '3.0000',
        'status'                 => AllocationStatus::Reserved,
    ]);

    $pickList = PickList::create([
        'number'            => 'PCK-UNCONF-' . uniqid(),
        'delivery_order_id' => $do->id,
        'status'            => \App\Enums\Outbound\PickListStatus::Open,
    ]);

    $task = PickTask::create([
        'pick_list_id'                 => $pickList->id,
        'delivery_order_allocation_id' => $allocation->id,
        'sequence'                     => 1,
        'location_id'                  => $binLoc->id,
        'item_id'                      => $item->id,
        'qty_to_pick'                  => '3.0000',
        'status'                       => PickTaskStatus::Open,
    ]);

    // Execute the pick
    $service = app(PickingService::class);
    $service->executeTask($task, '3.0000', $this->manager);
    $task->refresh();
    expect($task->status)->toBe(PickTaskStatus::Picked);

    $stagingBalance = StockBalance::where('item_id', $item->id)
        ->where('location_id', $stagingLoc->id)
        ->first();
    expect((float) $stagingBalance?->qty_on_hand)->toBe(3.0);

    // Now unconfirm
    $service->unconfirmTask($task->fresh()->load(['stockTransaction', 'allocation.stockBalance']), $this->manager);
    $task->refresh();

    expect($task->status)->toBe(PickTaskStatus::Open)
        ->and((float) $task->qty_picked)->toBe(0.0);

    // Staging should be back to 0 (or row removed)
    $stagingBalance->refresh();
    expect((float) $stagingBalance->qty_on_hand)->toBe(0.0);

    // Bin should be back to 10
    $binBalance->refresh();
    expect((float) $binBalance->qty_on_hand)->toBe(10.0);
});

// ─── Rule 8: Order submit blocked when no stock available ─────────────────────

