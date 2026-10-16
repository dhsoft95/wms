<?php

declare(strict_types=1);

use App\Data\Inventory\StockLine;
use App\Data\Inventory\StockReference;
use App\Enums\Inventory\StockStatus;
use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Enums\Outbound\AllocationRule;
use App\Enums\Outbound\DeliveryOrderPriority;
use App\Enums\Outbound\DeliveryOrderStatus;
use App\Enums\Outbound\DoLineStatus;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderAllocation;
use App\Models\DeliveryOrderLine;
use App\Models\Department;
use App\Models\DocumentSequence;
use App\Models\Inventory\Batch;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockReservation;
use App\Models\Item;
use App\Models\Location;
use App\Models\Setting;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedger;
use App\Services\Outbound\AllocationService;
use Database\Seeders\FoundationDefaultsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Cache;

// ─── setUp ────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(FoundationDefaultsSeeder::class);

    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    $this->actor = User::factory()->create(['status' => 'active']);
    $this->actor->assignRole('warehouse-manager');
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

function allocUom(): UnitOfMeasure
{
    return UnitOfMeasure::firstOrCreate(
        ['code' => 'PCS'],
        ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
    );
}

function allocItem(array $attrs = []): Item
{
    return Item::create(array_merge([
        'sku'          => 'ALLOC-' . uniqid(),
        'name'         => 'Alloc Test Item',
        'base_uom_id'  => allocUom()->id,
        'is_active'    => true,
        'track_batch'  => false,
        'track_expiry' => false,
        'track_serial' => false,
    ], $attrs));
}

function allocWarehouse(): Warehouse
{
    return Warehouse::firstOrCreate(
        ['code' => 'ALLOC-WH'],
        ['name' => 'Alloc Warehouse', 'is_active' => true]
    );
}

function allocVw(Warehouse $wh): VirtualWarehouse
{
    $dept = Department::firstOrCreate(
        ['code' => 'ALLOC-DEPT'],
        ['name' => 'Alloc Dept', 'is_active' => true]
    );

    return VirtualWarehouse::firstOrCreate(
        ['code' => 'ALLOC-VW'],
        [
            'name'          => 'Alloc VW',
            'department_id' => $dept->id,
            'is_active'     => true,
        ]
    );
}

function allocBinLocation(Warehouse $wh, array $extra = []): Location
{
    return Location::create(array_merge([
        'warehouse_id' => $wh->id,
        'code'         => 'ALLOC-LOC-' . uniqid(),
        'segment_code' => 'A',
        'level'        => LocationLevel::Bin,
        'type'         => LocationType::Storage,
        'status'       => LocationStatus::Active,
        'is_pickable'  => true,
        'path'         => '/',
    ], $extra));
}

function postAllocInbound(Item $item, Location $loc, VirtualWarehouse $vw, string $qty, ?string $batchNo = null, ?\Carbon\Carbon $expiry = null): void
{
    $ledger = app(StockLedger::class);

    $line = new StockLine(
        itemId:             $item->id,
        locationId:         $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus:        StockStatus::Available,
        enteredQty:         $qty,
        enteredUomId:       $item->base_uom_id,
        batchNo:            $batchNo,
        expiryDate:         $expiry,
    );

    $ledger->receive($line, new StockReference('test', 1, 'TEST-ALLOC-' . uniqid()));
}

function makeApprovedDo(Warehouse $wh, VirtualWarehouse $vw, User $user): DeliveryOrder
{
    return DeliveryOrder::create([
        'number'               => 'DO-ALLOC-' . uniqid(),
        'warehouse_id'         => $wh->id,
        'virtual_warehouse_id' => $vw->id,
        'department_id'        => $vw->department_id,
        'status'               => DeliveryOrderStatus::Approved,
        'priority'             => DeliveryOrderPriority::Normal,
        'allocation_rule'      => AllocationRule::Default,
        'requested_by'         => $user->id,
        'required_date'        => now()->addDays(3)->toDateString(),
    ]);
}

// ─── Test 1: Allocation creates reservation and allocation record ──────────────

test('test_allocation_creates_reservation_and_allocation_record', function () {
    $wh   = allocWarehouse();
    $vw   = allocVw($wh);
    $item = allocItem();
    $loc  = allocBinLocation($wh);
    $do   = makeApprovedDo($wh, $vw, $this->actor);

    // Post inbound stock so there is a real balance
    postAllocInbound($item, $loc, $vw, '20.0000');

    $line = DeliveryOrderLine::create([
        'delivery_order_id'  => $do->id,
        'line_no'            => 1,
        'item_id'            => $item->id,
        'uom_id'             => $item->base_uom_id,
        'requested_qty'      => '10.0000',
        'requested_qty_base' => '10.0000',
        'status'             => DoLineStatus::Pending,
    ]);

    $service = app(AllocationService::class);
    $service->allocate($do);

    $do->refresh();
    $line->refresh();

    $allocation  = DeliveryOrderAllocation::where('delivery_order_line_id', $line->id)->first();
    $reservation = StockReservation::where('reference_type', 'delivery_order_line')
        ->where('reference_id', $line->id)
        ->first();

    expect($do->status)->toBe(DeliveryOrderStatus::Allocated)
        ->and($allocation)->not->toBeNull()
        ->and((float) $allocation->qty)->toBe(10.0)
        ->and($reservation)->not->toBeNull()
        ->and((float) $reservation->qty)->toBe(10.0);
});

// ─── Test 2: FEFO ordering ────────────────────────────────────────────────────

test('test_allocation_respects_fefo_ordering', function () {
    $wh   = allocWarehouse();
    $vw   = allocVw($wh);
    $item = allocItem(['track_batch' => true, 'track_expiry' => true]);
    $loc  = allocBinLocation($wh);
    $do   = makeApprovedDo($wh, $vw, $this->actor);
    $do->update(['allocation_rule' => AllocationRule::Fefo]);

    $soonerExpiry = now()->addDays(30);
    $laterExpiry  = now()->addDays(90);

    postAllocInbound($item, $loc, $vw, '5.0000', 'BATCH-LATER',  $laterExpiry);
    postAllocInbound($item, $loc, $vw, '5.0000', 'BATCH-SOONER', $soonerExpiry);

    $line = DeliveryOrderLine::create([
        'delivery_order_id'  => $do->id,
        'line_no'            => 1,
        'item_id'            => $item->id,
        'uom_id'             => $item->base_uom_id,
        'requested_qty'      => '5.0000',
        'requested_qty_base' => '5.0000',
        'status'             => DoLineStatus::Pending,
    ]);

    $service = app(AllocationService::class);
    $service->allocate($do);

    $allocation = DeliveryOrderAllocation::where('delivery_order_line_id', $line->id)->first();
    expect($allocation)->not->toBeNull();

    $pickedBatch = Batch::find($allocation->batch_id);
    expect($pickedBatch)->not->toBeNull()
        ->and($pickedBatch->batch_no)->toBe('BATCH-SOONER');
});

// ─── Test 3: Expired stock is skipped ─────────────────────────────────────────

test('test_allocation_skips_expired_stock', function () {
    $wh   = allocWarehouse();
    $vw   = allocVw($wh);
    $item = allocItem(['track_batch' => true, 'track_expiry' => true]);
    $loc  = allocBinLocation($wh);
    $do   = makeApprovedDo($wh, $vw, $this->actor);

    // Post only expired stock
    $expiredDate = now()->subDays(5);
    postAllocInbound($item, $loc, $vw, '10.0000', 'BATCH-EXPIRED', $expiredDate);

    $line = DeliveryOrderLine::create([
        'delivery_order_id'  => $do->id,
        'line_no'            => 1,
        'item_id'            => $item->id,
        'uom_id'             => $item->base_uom_id,
        'requested_qty'      => '5.0000',
        'requested_qty_base' => '5.0000',
        'status'             => DoLineStatus::Pending,
    ]);

    $service = app(AllocationService::class);

    // Expired batch is invisible to the allocator → throws with no-stock message
    expect(fn () => $service->allocate($do))->toThrow(\DomainException::class);

    $allocations = DeliveryOrderAllocation::where('delivery_order_line_id', $line->id)->count();
    expect($allocations)->toBe(0);
});

// ─── Test 4: Partial allocation creates backorder when setting enabled ─────────

test('test_partial_allocation_creates_backorder_when_setting_enabled', function () {
    $wh   = allocWarehouse();
    $vw   = allocVw($wh);
    $item = allocItem();
    $loc  = allocBinLocation($wh);
    $do   = makeApprovedDo($wh, $vw, $this->actor);

    // Enable partial allocation and auto-backorder via settings
    Setting::updateOrCreate(
        ['key' => 'outbound.allow_partial_allocation'],
        ['value' => '1', 'type' => 'boolean']
    );
    Setting::updateOrCreate(
        ['key' => 'outbound.auto_backorder'],
        ['value' => '1', 'type' => 'boolean']
    );
    Cache::flush();

    // Only 3 units available but 10 requested
    postAllocInbound($item, $loc, $vw, '3.0000');

    DeliveryOrderLine::create([
        'delivery_order_id'  => $do->id,
        'line_no'            => 1,
        'item_id'            => $item->id,
        'uom_id'             => $item->base_uom_id,
        'requested_qty'      => '10.0000',
        'requested_qty_base' => '10.0000',
        'status'             => DoLineStatus::Pending,
    ]);

    $service = app(AllocationService::class);
    $service->allocate($do);

    $do->refresh();

    // Original order should be partially allocated
    expect($do->status)->toBe(DeliveryOrderStatus::PartiallyAllocated);

    // A backorder DO should have been created pointing to the original
    $backorder = DeliveryOrder::where('backorder_of_id', $do->id)->first();
    expect($backorder)->not->toBeNull()
        ->and($backorder->status)->toBe(DeliveryOrderStatus::Approved);

    // Backorder line remainder should be 7 (10 requested − 3 allocated)
    $backorderLine = DeliveryOrderLine::where('delivery_order_id', $backorder->id)->first();
    expect($backorderLine)->not->toBeNull()
        ->and((float) $backorderLine->requested_qty_base)->toBe(7.0);
});
