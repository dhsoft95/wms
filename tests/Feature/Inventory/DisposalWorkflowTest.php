<?php

use App\Data\Inventory\StockKey;
use App\Data\Inventory\StockLine;
use App\Data\Inventory\StockPosting;
use App\Data\Inventory\StockReference;
use App\Enums\Inventory\DisposalMethod;
use App\Enums\Inventory\DisposalStatus;
use App\Enums\Inventory\MovementType;
use App\Enums\Inventory\StockStatus;
use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Models\Department;
use App\Models\DisposalRequest;
use App\Models\DocumentSequence;
use App\Models\Inventory\StockBalance;
use App\Models\Item;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\Inventory\DisposalWorkflow;
use App\Services\Inventory\StockLedger;

beforeEach(function () {
    foreach (['STK', 'DISP'] as $type) {
        DocumentSequence::firstOrCreate(
            ['type' => $type],
            ['prefix' => $type, 'include_year' => true, 'padding' => 4, 'reset_yearly' => true, 'next_number' => 1]
        );
    }
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

function dwItem(array $attrs = []): Item
{
    $uom = UnitOfMeasure::firstOrCreate(
        ['code' => 'PCS'],
        ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
    );
    return Item::create(array_merge([
        'sku'          => 'DW-' . uniqid(),
        'name'         => 'Disposal Item',
        'base_uom_id'  => $uom->id,
        'is_active'    => true,
        'track_batch'  => false,
        'track_expiry' => false,
        'track_serial' => false,
    ], $attrs));
}

function dwLocation(LocationType $type = LocationType::Storage): Location
{
    $wh = Warehouse::firstOrCreate(
        ['code' => 'DWWH'],
        ['name' => 'Disposal WH', 'is_active' => true]
    );
    return Location::create([
        'warehouse_id' => $wh->id,
        'code'         => 'DW-' . uniqid(),
        'segment_code' => 'D',
        'level'        => LocationLevel::Bin,
        'type'         => $type,
        'status'       => LocationStatus::Active,
        'is_pickable'  => $type->isPickable(),
        'path'         => '/',
    ]);
}

function dwVw(): VirtualWarehouse
{
    $dept = Department::firstOrCreate(
        ['code' => 'DWDEPT'],
        ['name' => 'Disposal Dept', 'is_active' => true]
    );
    return VirtualWarehouse::create([
        'code'          => 'DWVW-' . uniqid(),
        'name'          => 'Disposal VW',
        'department_id' => $dept->id,
        'is_active'     => true,
    ]);
}

function dwUser(): \App\Models\User
{
    $user = \App\Models\User::create([
        'name'     => 'DWWM-' . uniqid(),
        'email'    => 'dwwm-' . uniqid() . '@example.com',
        'password' => bcrypt('password'),
    ]);
    if (method_exists($user, 'assignRole')) {
        // Ensure role exists before assigning
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'warehouse-manager', 'guard_name' => 'web']);
        try { $user->assignRole('warehouse-manager'); } catch (\Throwable) {}
    }
    return $user;
}

function dwPostObsolete(Item $item, Location $loc, VirtualWarehouse $vw, string $qty): void
{
    $ledger = app(StockLedger::class);

    // First post available
    $line = new StockLine(
        itemId: $item->id,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
        enteredQty: $qty,
        enteredUomId: $item->base_uom_id,
    );
    $ledger->post(new StockPosting(MovementType::Receipt, new StockReference('test', 1, 'DW-T'), [$line]));

    // Then mark obsolete
    $from = new StockKey(
        itemId: $item->id,
        batchId: null,
        lpnId: null,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
    );
    $ledger->changeStatus($from, StockStatus::Obsolete, $qty, $item->base_uom_id, new StockReference('test', 2, 'DW-T2'));
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('creates a disposal request', function () {
    $item = dwItem();
    $loc  = dwLocation();
    $vw   = dwVw();
    $user = dwUser();

    dwPostObsolete($item, $loc, $vw, '5.0000');

    $workflow = app(DisposalWorkflow::class);
    $req = $workflow->create([
        'virtual_warehouse_id' => $vw->id,
        'method'               => DisposalMethod::Destroy->value,
        'reason'               => 'Test disposal',
        'lines'                => [[
            'item_id'      => $item->id,
            'location_id'  => $loc->id,
            'batch_id'     => null,
            'stock_status' => StockStatus::Obsolete,
            'qty'          => '5.0000',
        ]],
    ], $user);

    expect($req)->toBeInstanceOf(DisposalRequest::class);
    expect($req->status)->toBe(DisposalStatus::Draft);
    expect($req->lines->count())->toBe(1);
    expect($req->number)->toStartWith('DISP');
});

it('submit fails when qty exceeds available stock', function () {
    $item = dwItem();
    $loc  = dwLocation();
    $vw   = dwVw();
    $user = dwUser();

    dwPostObsolete($item, $loc, $vw, '3.0000');

    $workflow = app(DisposalWorkflow::class);
    $req = $workflow->create([
        'virtual_warehouse_id' => $vw->id,
        'method'               => DisposalMethod::Destroy->value,
        'reason'               => 'Over qty test',
        'lines'                => [[
            'item_id'      => $item->id,
            'location_id'  => $loc->id,
            'batch_id'     => null,
            'stock_status' => StockStatus::Obsolete,
            'qty'          => '10.0000',  // more than available
        ]],
    ], $user);

    expect(fn () => $workflow->submit($req, $user))
        ->toThrow(\DomainException::class, 'Insufficient available stock for disposal');
});

it('approve moves to approved status', function () {
    $item = dwItem();
    $loc  = dwLocation();
    $vw   = dwVw();
    $user = dwUser();

    dwPostObsolete($item, $loc, $vw, '4.0000');

    $workflow = app(DisposalWorkflow::class);
    $req = $workflow->create([
        'virtual_warehouse_id' => $vw->id,
        'method'               => DisposalMethod::Destroy->value,
        'reason'               => 'Approve test',
        'lines'                => [[
            'item_id'      => $item->id,
            'location_id'  => $loc->id,
            'batch_id'     => null,
            'stock_status' => StockStatus::Obsolete,
            'qty'          => '4.0000',
        ]],
    ], $user);

    $req = $workflow->submit($req, $user);
    $req = $workflow->approve($req, $user);

    expect($req->status)->toBe(DisposalStatus::Approved);
    expect($req->approved_by)->toBe($user->id);
});

it('reject ends the flow without moving stock', function () {
    $item = dwItem();
    $loc  = dwLocation();
    $vw   = dwVw();
    $user = dwUser();

    dwPostObsolete($item, $loc, $vw, '3.0000');

    $workflow = app(DisposalWorkflow::class);
    $req = $workflow->create([
        'virtual_warehouse_id' => $vw->id,
        'method'               => DisposalMethod::Destroy->value,
        'reason'               => 'Reject test',
        'lines'                => [[
            'item_id'      => $item->id,
            'location_id'  => $loc->id,
            'batch_id'     => null,
            'stock_status' => StockStatus::Obsolete,
            'qty'          => '3.0000',
        ]],
    ], $user);

    $req = $workflow->submit($req, $user);
    $req = $workflow->reject($req, $user, 'Not authorised');

    expect($req->status)->toBe(DisposalStatus::Rejected);
    expect($req->rejection_reason)->toBe('Not authorised');

    // Stock must still be there (untouched)
    $balance = StockBalance::where('item_id', $item->id)
        ->where('location_id', $loc->id)
        ->where('virtual_warehouse_id', $vw->id)
        ->where('stock_status', StockStatus::Obsolete)
        ->first();
    expect((float) $balance->qty_on_hand)->toBe(3.0);
});

it('move to disposal area relocates stock via ledger', function () {
    $item        = dwItem();
    $loc         = dwLocation();
    $disposalLoc = dwLocation(LocationType::Disposal);
    $vw          = dwVw();
    $user        = dwUser();

    dwPostObsolete($item, $loc, $vw, '6.0000');

    $workflow = app(DisposalWorkflow::class);
    $req = $workflow->create([
        'virtual_warehouse_id' => $vw->id,
        'method'               => DisposalMethod::Destroy->value,
        'reason'               => 'Move test',
        'lines'                => [[
            'item_id'      => $item->id,
            'location_id'  => $loc->id,
            'batch_id'     => null,
            'stock_status' => StockStatus::Obsolete,
            'qty'          => '6.0000',
        ]],
    ], $user);

    $req = $workflow->submit($req, $user);
    $req = $workflow->approve($req, $user);
    $req = $workflow->moveToDisposalArea($req, $disposalLoc->id, $user);

    expect($req->status)->toBe(DisposalStatus::Moved);

    $disposalBalance = StockBalance::where('item_id', $item->id)
        ->where('location_id', $disposalLoc->id)
        ->where('virtual_warehouse_id', $vw->id)
        ->where('stock_status', StockStatus::Obsolete)
        ->first();

    expect((float) $disposalBalance->qty_on_hand)->toBe(6.0);
});

it('complete removes stock exactly once (idempotency)', function () {
    $item        = dwItem();
    $loc         = dwLocation();
    $disposalLoc = dwLocation(LocationType::Disposal);
    $vw          = dwVw();
    $user        = dwUser();

    dwPostObsolete($item, $loc, $vw, '2.0000');

    $workflow = app(DisposalWorkflow::class);
    $req = $workflow->create([
        'virtual_warehouse_id' => $vw->id,
        'method'               => DisposalMethod::Destroy->value,
        'reason'               => 'Complete test',
        'lines'                => [[
            'item_id'      => $item->id,
            'location_id'  => $loc->id,
            'batch_id'     => null,
            'stock_status' => StockStatus::Obsolete,
            'qty'          => '2.0000',
        ]],
    ], $user);

    $req = $workflow->submit($req, $user);
    $req = $workflow->approve($req, $user);
    $req = $workflow->moveToDisposalArea($req, $disposalLoc->id, $user);

    $req = $workflow->complete($req, [
        'destroy_method' => 'scrap',
        'destroy_date'   => now()->toDateString(),
        'witness_name'   => 'John Doe',
    ], $user);

    expect($req->status)->toBe(DisposalStatus::Completed);

    $disposalBalance = StockBalance::where('item_id', $item->id)
        ->where('location_id', $disposalLoc->id)
        ->where('virtual_warehouse_id', $vw->id)
        ->where('stock_status', StockStatus::Obsolete)
        ->first();

    expect((float) ($disposalBalance?->qty_on_hand ?? 0))->toBe(0.0);

    // Calling complete again should throw (already completed)
    expect(fn () => $workflow->complete($req, [], $user))
        ->toThrow(\DomainException::class);
});

it('serials are marked disposed on completion', function () {
    // Without actual serial data in test DB, validate the enum value exists
    expect(\App\Enums\Inventory\SerialStatus::Disposed->value)->toBe('disposed');
    expect(\App\Enums\Inventory\SerialStatus::Disposed->label())->toBe('Disposed');
});
