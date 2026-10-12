<?php

use App\Data\Inventory\StockLine;
use App\Data\Inventory\StockReference;
use App\Enums\Inventory\StockStatus;
use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Models\Department;
use App\Models\Inventory\StockBalance;
use App\Models\Item;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Models\DocumentSequence;
use App\Services\Inventory\StockLedger;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    DocumentSequence::firstOrCreate(
        ['type' => 'STK'],
        ['prefix' => 'STK', 'include_year' => true, 'padding' => 4, 'reset_yearly' => true, 'next_number' => 1]
    );
    (new RolePermissionSeeder)->run();
});

// ─── Helpers (scoped to this file) ───────────────────────────────────────────

function verifyCmdItem(array $attrs = []): Item
{
    $uom = UnitOfMeasure::firstOrCreate(
        ['code' => 'PCS'],
        ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
    );

    return Item::create(array_merge([
        'sku'          => 'VFY-' . uniqid(),
        'name'         => 'Verify Item',
        'base_uom_id'  => $uom->id,
        'is_active'    => true,
        'track_batch'  => false,
        'track_expiry' => false,
        'track_serial' => false,
    ], $attrs));
}

function verifyCmdLocation(array $attrs = []): Location
{
    $warehouse = Warehouse::firstOrCreate(
        ['code' => 'TWH'],
        ['name' => 'Test Warehouse', 'is_active' => true]
    );

    return Location::create(array_merge([
        'warehouse_id' => $warehouse->id,
        'code'         => 'LOC-VFY-' . uniqid(),
        'segment_code' => 'V',
        'level'        => LocationLevel::Bin,
        'type'         => LocationType::Storage,
        'status'       => LocationStatus::Active,
        'is_pickable'  => true,
        'path'         => '/',
    ], $attrs));
}

function verifyCmdVw(array $attrs = []): VirtualWarehouse
{
    $dept = Department::firstOrCreate(
        ['code' => 'TDEPT'],
        ['name' => 'Test Department', 'is_active' => true]
    );

    return VirtualWarehouse::create(array_merge([
        'code'          => 'VW-VFY-' . uniqid(),
        'name'          => 'Verify VW ' . uniqid(),
        'department_id' => $dept->id,
        'is_active'     => true,
    ], $attrs));
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('detects corrupted balance and reports mismatch', function () {
    $item   = verifyCmdItem();
    $loc    = verifyCmdLocation();
    $vw     = verifyCmdVw();
    $ledger = app(StockLedger::class);

    $line = new StockLine(
        itemId: $item->id,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
        enteredQty: '10.0000',
        enteredUomId: $item->base_uom_id,
    );
    $ledger->receive($line, new StockReference(referenceType: 'test', referenceId: 1, referenceNo: 'TEST-001'));

    // Manually corrupt the balance
    $balance = StockBalance::where('item_id', $item->id)
        ->where('location_id', $loc->id)
        ->first();

    \Illuminate\Support\Facades\DB::table('stock_balances')
        ->where('id', $balance->id)
        ->update(['qty_on_hand' => '99.0000']);

    $this->artisan('wms:stock:verify')
        ->assertExitCode(1)
        ->expectsOutputToContain('mismatch');
});

it('--fix repairs the corrupted balance', function () {
    $item   = verifyCmdItem();
    $loc    = verifyCmdLocation();
    $vw     = verifyCmdVw();
    $ledger = app(StockLedger::class);

    $line = new StockLine(
        itemId: $item->id,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
        enteredQty: '10.0000',
        enteredUomId: $item->base_uom_id,
    );
    $ledger->receive($line, new StockReference(referenceType: 'test', referenceId: 1, referenceNo: 'TEST-001'));

    $balance = StockBalance::where('item_id', $item->id)
        ->where('location_id', $loc->id)
        ->first();

    \Illuminate\Support\Facades\DB::table('stock_balances')
        ->where('id', $balance->id)
        ->update(['qty_on_hand' => '99.0000']);

    $this->artisan('wms:stock:verify', ['--fix' => true])
        ->assertExitCode(0);

    $balance->refresh();
    expect((float) $balance->qty_on_hand)->toBe(10.0);
});

it('reports success when all balances are correct', function () {
    $item   = verifyCmdItem();
    $loc    = verifyCmdLocation();
    $vw     = verifyCmdVw();
    $ledger = app(StockLedger::class);

    $line = new StockLine(
        itemId: $item->id,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
        enteredQty: '25.0000',
        enteredUomId: $item->base_uom_id,
    );
    $ledger->receive($line, new StockReference(referenceType: 'test', referenceId: 1, referenceNo: 'TEST-001'));

    $this->artisan('wms:stock:verify')
        ->assertExitCode(0);
});
