<?php

use App\Data\Inventory\StockKey;
use App\Data\Inventory\StockLine;
use App\Data\Inventory\StockReference;
use App\Enums\Inventory\StockStatus;
use App\Enums\Inventory\StockStatusRequestStatus;
use App\Enums\Inventory\StockStatusRequestType;
use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Models\Department;
use App\Models\DocumentSequence;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockTransaction;
use App\Models\Item;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedger;
use App\Services\Inventory\StockStatusRequestWorkflow;
use App\Models\StockStatusRequest;

beforeEach(function () {
    foreach (['STK', 'SSR'] as $type) {
        DocumentSequence::firstOrCreate(
            ['type' => $type],
            ['prefix' => $type, 'include_year' => true, 'padding' => 4, 'reset_yearly' => true, 'next_number' => 1]
        );
    }
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

function obsItem(array $attrs = []): Item
{
    $uom = UnitOfMeasure::firstOrCreate(
        ['code' => 'PCS'],
        ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
    );
    return Item::create(array_merge([
        'sku'          => 'OBS-' . uniqid(),
        'name'         => 'Obs Item',
        'base_uom_id'  => $uom->id,
        'is_active'    => true,
        'track_batch'  => false,
        'track_expiry' => false,
        'track_serial' => false,
    ], $attrs));
}

function obsLocation(LocationType $type = LocationType::Storage): Location
{
    $wh = Warehouse::firstOrCreate(
        ['code' => 'OBSWH'],
        ['name' => 'Obs WH', 'is_active' => true]
    );
    return Location::create([
        'warehouse_id' => $wh->id,
        'code'         => 'OBLOC-' . uniqid(),
        'segment_code' => 'O',
        'level'        => LocationLevel::Bin,
        'type'         => $type,
        'status'       => LocationStatus::Active,
        'is_pickable'  => $type->isPickable(),
        'path'         => '/',
    ]);
}

function obsVw(): VirtualWarehouse
{
    $dept = Department::firstOrCreate(
        ['code' => 'OBSDEPT'],
        ['name' => 'Obs Dept', 'is_active' => true]
    );
    return VirtualWarehouse::create([
        'code'          => 'OBSVW-' . uniqid(),
        'name'          => 'Obs VW',
        'department_id' => $dept->id,
        'is_active'     => true,
    ]);
}

function obsPostInbound(Item $item, Location $loc, VirtualWarehouse $vw, string $qty, StockStatus $status = StockStatus::Available): StockTransaction
{
    $ledger = app(StockLedger::class);
    $line   = new StockLine(
        itemId: $item->id,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: $status,
        enteredQty: $qty,
        enteredUomId: $item->base_uom_id,
    );
    return $ledger->post(new \App\Data\Inventory\StockPosting(\App\Enums\Inventory\MovementType::Receipt, new StockReference('test', 1, 'T1'), [$line]));
}

function obsUser(): \App\Models\User
{
    $u = new \App\Models\User();
    $u->id = 99999;
    $u->name = 'Test Manager';
    $u->email = 'mgr@test.com';
    return $u;
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('obsolete stock status is not pickable', function () {
    expect(StockStatus::Obsolete->isPickable())->toBeFalse();
});

it('obsolete stock is not allocatable', function () {
    expect(StockStatus::Obsolete->isAllocatable())->toBeFalse();
});

it('mark obsolete workflow approves and changes status via ledger', function () {
    $item    = obsItem();
    $loc     = obsLocation();
    $vw      = obsVw();

    // Post some available stock first
    obsPostInbound($item, $loc, $vw, '10.0000');

    $balance = StockBalance::where('item_id', $item->id)
        ->where('location_id', $loc->id)
        ->where('virtual_warehouse_id', $vw->id)
        ->where('stock_status', StockStatus::Available)
        ->first();

    expect((float) $balance->qty_on_hand)->toBe(10.0);

    // Create + submit a stock status request
    $workflow = app(StockStatusRequestWorkflow::class);

    // Use a user with warehouse-manager role via Spatie (or mock)
    $user = \App\Models\User::create([
        'name'     => 'WM-' . uniqid(),
        'email'    => 'wm-' . uniqid() . '@example.com',
        'password' => bcrypt('password'),
    ]);

    // Assign warehouse-manager role
    if (method_exists($user, 'assignRole')) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'warehouse-manager', 'guard_name' => 'web']);
        try { $user->assignRole('warehouse-manager'); } catch (\Throwable) {}
    }

    $req = $workflow->create([
        'virtual_warehouse_id' => $vw->id,
        'type'                 => StockStatusRequestType::MarkObsolete->value,
        'reason'               => 'End of life',
        'lines'                => [[
            'item_id'     => $item->id,
            'location_id' => $loc->id,
            'batch_id'    => null,
            'qty'         => '5.0000',
        ]],
    ], $user);

    $req = $workflow->submit($req, $user);
    expect($req->status)->toBe(StockStatusRequestStatus::Submitted);

    $req = $workflow->approve($req, $user);

    // After approve, obsolete balance should exist with 5 units
    $obsoleteBalance = StockBalance::where('item_id', $item->id)
        ->where('location_id', $loc->id)
        ->where('virtual_warehouse_id', $vw->id)
        ->where('stock_status', StockStatus::Obsolete)
        ->first();

    expect($obsoleteBalance)->not->toBeNull();
    expect((float) $obsoleteBalance->qty_on_hand)->toBe(5.0);

    // Available balance reduced
    $availBalance = StockBalance::where('item_id', $item->id)
        ->where('location_id', $loc->id)
        ->where('virtual_warehouse_id', $vw->id)
        ->where('stock_status', StockStatus::Available)
        ->first();

    expect((float) $availBalance->qty_on_hand)->toBe(5.0);
});

it('restore to available changes status back via ledger', function () {
    $item    = obsItem();
    $loc     = obsLocation();
    $vw      = obsVw();

    // Post obsolete stock directly via changeStatus
    $ledger = app(StockLedger::class);
    $line = new StockLine(
        itemId: $item->id,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
        enteredQty: '8.0000',
        enteredUomId: $item->base_uom_id,
    );
    $ledger->post(new \App\Data\Inventory\StockPosting(\App\Enums\Inventory\MovementType::Receipt, new StockReference('test', 1, 'T2'), [$line]));

    $from = new StockKey(
        itemId: $item->id,
        batchId: null,
        lpnId: null,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
    );

    $ledger->changeStatus($from, StockStatus::Obsolete, '8.0000', $item->base_uom_id, new StockReference('test', 2, 'T3'));

    $obsBalance = StockBalance::where('item_id', $item->id)
        ->where('location_id', $loc->id)
        ->where('virtual_warehouse_id', $vw->id)
        ->where('stock_status', StockStatus::Obsolete)
        ->first();

    expect((float) $obsBalance->qty_on_hand)->toBe(8.0);

    // Now restore
    $fromObs = new StockKey(
        itemId: $item->id,
        batchId: null,
        lpnId: null,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Obsolete,
    );

    $ledger->changeStatus($fromObs, StockStatus::Available, '8.0000', $item->base_uom_id, new StockReference('test', 3, 'T4'));

    $availBalance = StockBalance::where('item_id', $item->id)
        ->where('location_id', $loc->id)
        ->where('virtual_warehouse_id', $vw->id)
        ->where('stock_status', StockStatus::Available)
        ->first();

    expect((float) $availBalance->qty_on_hand)->toBe(8.0);
});

it('mark obsolete is idempotent on double approve', function () {
    $item = obsItem();
    $loc  = obsLocation();
    $vw   = obsVw();

    obsPostInbound($item, $loc, $vw, '6.0000');

    $user = \App\Models\User::create([
        'name'     => 'WM2-' . uniqid(),
        'email'    => 'wm2-' . uniqid() . '@example.com',
        'password' => bcrypt('password'),
    ]);
    if (method_exists($user, 'assignRole')) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'warehouse-manager', 'guard_name' => 'web']);
        try { $user->assignRole('warehouse-manager'); } catch (\Throwable) {}
    }

    $workflow = app(StockStatusRequestWorkflow::class);
    $req = $workflow->create([
        'virtual_warehouse_id' => $vw->id,
        'type'                 => StockStatusRequestType::MarkObsolete->value,
        'reason'               => 'Idempotency test',
        'lines'                => [[
            'item_id'     => $item->id,
            'location_id' => $loc->id,
            'batch_id'    => null,
            'qty'         => '6.0000',
        ]],
    ], $user);

    $req = $workflow->submit($req, $user);
    $req = $workflow->approve($req, $user);

    expect($req->status)->toBe(StockStatusRequestStatus::Approved);

    // Attempting approve again should throw (already approved)
    expect(fn () => $workflow->approve($req, $user))
        ->toThrow(\DomainException::class);
});
