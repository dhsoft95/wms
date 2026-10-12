<?php

use App\Artisan\Commands\VerifyStockCommand;
use App\Console\Commands\VerifyStockCommand as WmsVerifyStockCommand;
use App\Data\Inventory\StockKey;
use App\Data\Inventory\StockLine;
use App\Data\Inventory\StockPosting;
use App\Data\Inventory\StockReference;
use App\Enums\Inventory\MovementType;
use App\Enums\Inventory\SerialStatus;
use App\Enums\Inventory\StockStatus;
use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Enums\UomType;
use App\Models\Department;
use App\Models\DocumentSequence;
use App\Models\Inventory\SerialNumber;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Item;
use App\Models\ItemUom;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedger;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DocumentSequence::firstOrCreate(
        ['type' => 'STK'],
        ['prefix' => 'STK', 'include_year' => true, 'padding' => 4, 'reset_yearly' => true, 'next_number' => 1]
    );
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

function serialItem(bool $trackSerial = true): Item
{
    $uom = UnitOfMeasure::firstOrCreate(
        ['code' => 'PCS'],
        ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
    );
    return Item::create([
        'sku'          => 'SER-' . uniqid(),
        'name'         => 'Serial Item',
        'base_uom_id'  => $uom->id,
        'is_active'    => true,
        'track_batch'  => false,
        'track_expiry' => false,
        'track_serial' => $trackSerial,
    ]);
}

function serialLoc(): Location
{
    $wh = Warehouse::firstOrCreate(['code' => 'SWH'], ['name' => 'Serial WH', 'is_active' => true]);
    return Location::create([
        'warehouse_id' => $wh->id,
        'code'         => 'SL-' . uniqid(),
        'segment_code' => 'S',
        'level'        => LocationLevel::Bin,
        'type'         => LocationType::Storage,
        'status'       => LocationStatus::Active,
        'is_pickable'  => true,
        'path'         => '/',
    ]);
}

function serialVw(): VirtualWarehouse
{
    $dept = Department::firstOrCreate(['code' => 'SDEPT'], ['name' => 'Serial Dept', 'is_active' => true]);
    return VirtualWarehouse::create([
        'code'          => 'SVW-' . uniqid(),
        'name'          => 'Serial VW',
        'department_id' => $dept->id,
        'is_active'     => true,
    ]);
}

function postSerialInbound(Item $item, Location $loc, VirtualWarehouse $vw, array $serials): \App\Models\Inventory\StockTransaction
{
    $ledger = app(StockLedger::class);
    $qty    = count($serials);

    return $ledger->openingBalance([
        new StockLine(
            itemId: $item->id,
            locationId: $loc->id,
            virtualWarehouseId: $vw->id,
            stockStatus: StockStatus::Available,
            enteredQty: (string) $qty,
            enteredUomId: $item->base_uom_id,
            serials: $serials,
        ),
    ]);
}

// ─── Group 1: Serial count validation ─────────────────────────────────────────

it('serial count must equal base qty for serial-tracked item', function () {
    $item = serialItem();
    $loc  = serialLoc();
    $vw   = serialVw();

    expect(fn () => app(StockLedger::class)->openingBalance([
        new StockLine(
            itemId: $item->id,
            locationId: $loc->id,
            virtualWarehouseId: $vw->id,
            stockStatus: StockStatus::Available,
            enteredQty: '3',
            enteredUomId: $item->base_uom_id,
            serials: ['SN001', 'SN002'], // only 2, qty=3
        ),
    ]))->toThrow(\InvalidArgumentException::class, 'requires 3 serial');
});

it('duplicate serial within one posting is rejected', function () {
    $item = serialItem();
    $loc  = serialLoc();
    $vw   = serialVw();

    expect(fn () => app(StockLedger::class)->openingBalance([
        new StockLine(
            itemId: $item->id,
            locationId: $loc->id,
            virtualWarehouseId: $vw->id,
            stockStatus: StockStatus::Available,
            enteredQty: '2',
            enteredUomId: $item->base_uom_id,
            serials: ['SN001', 'SN001'], // duplicate
        ),
    ]))->toThrow(\InvalidArgumentException::class, 'Duplicate serial');
});

it('serial already in_stock is rejected on inbound', function () {
    $item = serialItem();
    $loc  = serialLoc();
    $vw   = serialVw();

    postSerialInbound($item, $loc, $vw, ['SN001']);

    expect(fn () => postSerialInbound($item, $loc, $vw, ['SN001']))
        ->toThrow(\InvalidArgumentException::class, 'already in stock');
});

it('non-serial item rejects serials', function () {
    $item = serialItem(false);
    $loc  = serialLoc();
    $vw   = serialVw();

    expect(fn () => app(StockLedger::class)->openingBalance([
        new StockLine(
            itemId: $item->id,
            locationId: $loc->id,
            virtualWarehouseId: $vw->id,
            stockStatus: StockStatus::Available,
            enteredQty: '1',
            enteredUomId: $item->base_uom_id,
            serials: ['SN001'],
        ),
    ]))->toThrow(\InvalidArgumentException::class, 'does not use serial tracking');
});

it('serial is normalized to uppercase trim before storage', function () {
    $item = serialItem();
    $loc  = serialLoc();
    $vw   = serialVw();

    postSerialInbound($item, $loc, $vw, ['  sn-abc  ']);

    $serial = SerialNumber::where('item_id', $item->id)->first();
    expect($serial->serial_no)->toBe('SN-ABC');
});

it('empty serial is rejected', function () {
    $item = serialItem();
    $loc  = serialLoc();
    $vw   = serialVw();

    expect(fn () => app(StockLedger::class)->openingBalance([
        new StockLine(
            itemId: $item->id,
            locationId: $loc->id,
            virtualWarehouseId: $vw->id,
            stockStatus: StockStatus::Available,
            enteredQty: '1',
            enteredUomId: $item->base_uom_id,
            serials: ['   '],
        ),
    ]))->toThrow(\InvalidArgumentException::class, 'invalid');
});

// ─── Group 2: Outbound / move ─────────────────────────────────────────────────

it('outbound only works with serials at the correct source key', function () {
    $item = serialItem();
    $loc  = serialLoc();
    $loc2 = serialLoc();
    $vw   = serialVw();

    postSerialInbound($item, $loc, $vw, ['SN001', 'SN002', 'SN003']);

    // Try to dispatch a serial that is at loc2 (which it isn't)
    expect(fn () => app(StockLedger::class)->post(new StockPosting(
        MovementType::Dispatch,
        new StockReference(referenceType: 'test', referenceId: 1, referenceNo: 'D001'),
        [new StockLine(
            itemId: $item->id,
            locationId: $loc2->id,  // wrong location
            virtualWarehouseId: $vw->id,
            stockStatus: StockStatus::Available,
            enteredQty: '-1',
            enteredUomId: $item->base_uom_id,
            serials: ['SN001'],
        )],
    )))->toThrow(\InvalidArgumentException::class, 'not available at the source stock key');
});

it('partial move: 3 of 10 serials move to new location', function () {
    $item = serialItem();
    $loc1 = serialLoc();
    $loc2 = serialLoc();
    $vw   = serialVw();

    $all = array_map(fn ($i) => sprintf('SN%04d', $i), range(1, 10));
    postSerialInbound($item, $loc1, $vw, $all);

    $moving = ['SN0001', 'SN0002', 'SN0003'];

    // Relocation: outbound from loc1, inbound to loc2
    app(StockLedger::class)->post(new StockPosting(
        MovementType::Relocation,
        new StockReference(referenceType: 'test', referenceId: 1, referenceNo: 'MV001'),
        [
            new StockLine(
                itemId: $item->id,
                locationId: $loc1->id,
                virtualWarehouseId: $vw->id,
                stockStatus: StockStatus::Available,
                enteredQty: '-3',
                enteredUomId: $item->base_uom_id,
                serials: $moving,
            ),
            new StockLine(
                itemId: $item->id,
                locationId: $loc2->id,
                virtualWarehouseId: $vw->id,
                stockStatus: StockStatus::Available,
                enteredQty: '3',
                enteredUomId: $item->base_uom_id,
                serials: $moving,
            ),
        ],
    ));

    // Three moved serials now at loc2
    foreach ($moving as $sn) {
        $serial = SerialNumber::where('item_id', $item->id)->where('serial_no', $sn)->first();
        expect($serial->location_id)->toBe($loc2->id);
        expect($serial->status)->toBe(SerialStatus::InStock);
    }

    // Remaining 7 still at loc1
    $staying = array_diff($all, $moving);
    foreach ($staying as $sn) {
        $serial = SerialNumber::where('item_id', $item->id)->where('serial_no', $sn)->first();
        expect($serial->location_id)->toBe($loc1->id);
    }
});

// ─── Group 3: Dispatch & return ───────────────────────────────────────────────

it('dispatched serial can be received again and history is preserved', function () {
    $item = serialItem();
    $loc  = serialLoc();
    $vw   = serialVw();

    // Receive
    postSerialInbound($item, $loc, $vw, ['SN-RETURN']);
    $movementCount = SerialNumber::where('item_id', $item->id)
        ->where('serial_no', 'SN-RETURN')
        ->first()
        ->movements()
        ->count();

    // Dispatch
    app(StockLedger::class)->post(new StockPosting(
        MovementType::Dispatch,
        new StockReference(referenceType: 'test', referenceId: 1, referenceNo: 'DSP001'),
        [new StockLine(
            itemId: $item->id,
            locationId: $loc->id,
            virtualWarehouseId: $vw->id,
            stockStatus: StockStatus::Available,
            enteredQty: '-1',
            enteredUomId: $item->base_uom_id,
            serials: ['SN-RETURN'],
        )],
    ));

    $serial = SerialNumber::where('item_id', $item->id)->where('serial_no', 'SN-RETURN')->first();
    expect($serial->status)->toBe(SerialStatus::Dispatched);

    // Re-receive the same serial
    postSerialInbound($item, $loc, $vw, ['SN-RETURN']);

    $serial->refresh();
    expect($serial->status)->toBe(SerialStatus::InStock);
    // History: original receive + dispatch + return = 3 movements
    expect($serial->movements()->count())->toBe($movementCount + 2);
});

// ─── Group 4: Reversal ────────────────────────────────────────────────────────

it('reversal restores serial position and status', function () {
    $item = serialItem();
    $loc  = serialLoc();
    $vw   = serialVw();

    $tx = postSerialInbound($item, $loc, $vw, ['SN-REV']);

    // Dispatch
    $dispatchTx = app(StockLedger::class)->post(new StockPosting(
        MovementType::Dispatch,
        new StockReference(referenceType: 'test', referenceId: 1, referenceNo: 'DSP-REV'),
        [new StockLine(
            itemId: $item->id,
            locationId: $loc->id,
            virtualWarehouseId: $vw->id,
            stockStatus: StockStatus::Available,
            enteredQty: '-1',
            enteredUomId: $item->base_uom_id,
            serials: ['SN-REV'],
        )],
    ));

    $serial = SerialNumber::where('item_id', $item->id)->where('serial_no', 'SN-REV')->first();
    expect($serial->status)->toBe(SerialStatus::Dispatched);

    // Reverse the dispatch
    app(StockLedger::class)->reverse($dispatchTx, 'Test reversal');

    $serial->refresh();
    expect($serial->status)->toBe(SerialStatus::InStock);
    expect($serial->location_id)->toBe($loc->id);
});

// ─── Group 5: track_serial guard ──────────────────────────────────────────────

it('track_serial cannot be enabled on a non-count UoM item', function () {
    $kgUom = UnitOfMeasure::firstOrCreate(
        ['code' => 'KG'],
        ['name' => 'Kilogram', 'type' => 'weight', 'is_active' => true]
    );
    $item = Item::create([
        'sku'          => 'KGITEM-' . uniqid(),
        'name'         => 'KG Item',
        'base_uom_id'  => $kgUom->id,
        'is_active'    => true,
        'track_serial' => false,
    ]);

    expect(fn () => \App\Actions\Foundation\UpdateItemAction::execute(
        $item,
        ['track_serial' => true, 'base_uom_id' => $kgUom->id],
        [],
        []
    ))->toThrow(\DomainException::class, 'count-type');
});

it('track_serial flag is locked once stock exists', function () {
    $item = serialItem();
    $loc  = serialLoc();
    $vw   = serialVw();

    postSerialInbound($item, $loc, $vw, ['SN-LOCK']);

    expect(fn () => \App\Actions\Foundation\UpdateItemAction::execute(
        $item,
        ['track_serial' => false],
        [],
        []
    ))->toThrow(\DomainException::class, 'Cannot change track_serial');
});

// ─── Group 6: wms:stock:verify detects serial mismatch ────────────────────────

it('wms:stock:verify detects a serial/balance mismatch', function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $item = serialItem();
    $loc  = serialLoc();
    $vw   = serialVw();

    postSerialInbound($item, $loc, $vw, ['SN-VM1', 'SN-VM2']);

    // Manually corrupt the serial count — mark one as dispatched directly
    DB::table('serial_numbers')
        ->where('item_id', $item->id)
        ->where('serial_no', 'SN-VM1')
        ->update(['status' => 'dispatched']);

    $result = \Artisan::call('wms:stock:verify');
    expect($result)->toBe(1); // FAILURE
    expect(\Artisan::output())->toContain('mismatch');
});

// ─── Group 7: serial movement links ───────────────────────────────────────────

it('serials are linked to their stock movements', function () {
    $item = serialItem();
    $loc  = serialLoc();
    $vw   = serialVw();

    $tx = postSerialInbound($item, $loc, $vw, ['LINK-A', 'LINK-B']);

    $movement = $tx->movements()->first();
    $serials  = $movement->serials()->pluck('serial_no')->all();

    expect($serials)->toContain('LINK-A');
    expect($serials)->toContain('LINK-B');
});

// ─── Group 8: adjustment outbound writes off ──────────────────────────────────

it('negative adjustment marks serials as written_off', function () {
    $item = serialItem();
    $loc  = serialLoc();
    $vw   = serialVw();

    postSerialInbound($item, $loc, $vw, ['WO-001']);

    app(StockLedger::class)->post(new StockPosting(
        MovementType::Adjustment,
        new StockReference(referenceType: 'test', referenceId: 1, referenceNo: 'ADJ001'),
        [new StockLine(
            itemId: $item->id,
            locationId: $loc->id,
            virtualWarehouseId: $vw->id,
            stockStatus: StockStatus::Available,
            enteredQty: '-1',
            enteredUomId: $item->base_uom_id,
            serials: ['WO-001'],
        )],
    ));

    $serial = SerialNumber::where('item_id', $item->id)->where('serial_no', 'WO-001')->first();
    expect($serial->status)->toBe(SerialStatus::WrittenOff);
});

// ─── Group 9: Paired moves with serials ───────────────────────────────────────

it('move() with serials relocates them to the new location', function () {
    $item = serialItem();
    $loc  = serialLoc();
    $loc2 = serialLoc();
    $vw   = serialVw();

    postSerialInbound($item, $loc, $vw, ['MV-A', 'MV-B', 'MV-C']);

    $from = new StockKey(
        itemId: $item->id,
        batchId: null,
        lpnId: null,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
    );

    app(StockLedger::class)->move(
        $from, $loc2->id, '2', $item->base_uom_id,
        new StockReference(referenceType: 'test', referenceId: 1, referenceNo: 'MV001'),
        ['MV-A', 'MV-B'],
    );

    expect(SerialNumber::where('item_id', $item->id)->where('serial_no', 'MV-A')->first()->location_id)->toBe($loc2->id);
    expect(SerialNumber::where('item_id', $item->id)->where('serial_no', 'MV-B')->first()->location_id)->toBe($loc2->id);
    expect(SerialNumber::where('item_id', $item->id)->where('serial_no', 'MV-C')->first()->location_id)->toBe($loc->id);
});

it('changeOwner() with serials transfers them to the new virtual warehouse', function () {
    $item = serialItem();
    $loc  = serialLoc();
    $vw   = serialVw();
    $vw2  = serialVw();

    postSerialInbound($item, $loc, $vw, ['CO-A', 'CO-B']);

    $from = new StockKey(
        itemId: $item->id,
        batchId: null,
        lpnId: null,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
    );

    app(StockLedger::class)->changeOwner(
        $from, $vw2->id, '2', $item->base_uom_id,
        new StockReference(referenceType: 'test', referenceId: 1, referenceNo: 'CO001'),
        ['CO-A', 'CO-B'],
    );

    $snA = SerialNumber::where('item_id', $item->id)->where('serial_no', 'CO-A')->first();
    expect($snA->virtual_warehouse_id)->toBe($vw2->id);
    expect($snA->status)->toBe(SerialStatus::InStock);
});

it('changeStatus() with serials keeps them in stock at same position', function () {
    $item = serialItem();
    $loc  = serialLoc();
    $vw   = serialVw();

    postSerialInbound($item, $loc, $vw, ['CS-A', 'CS-B']);

    $from = new StockKey(
        itemId: $item->id,
        batchId: null,
        lpnId: null,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
    );

    app(StockLedger::class)->changeStatus(
        $from, StockStatus::OnHold, '2', $item->base_uom_id,
        new StockReference(referenceType: 'test', referenceId: 1, referenceNo: 'CS001'),
        ['CS-A', 'CS-B'],
    );

    // Status stays in_stock (position-only update), stock_status switches to on_hold
    $snA = SerialNumber::where('item_id', $item->id)->where('serial_no', 'CS-A')->first();
    expect($snA->status)->toBe(SerialStatus::InStock);
    expect($snA->stock_status->value)->toBe('on_hold');
});

it('move() partial (3 of 5) serials leaves the rest at the source', function () {
    $item = serialItem();
    $loc  = serialLoc();
    $loc2 = serialLoc();
    $vw   = serialVw();

    postSerialInbound($item, $loc, $vw, ['P1', 'P2', 'P3', 'P4', 'P5']);

    $from = new StockKey(
        itemId: $item->id,
        batchId: null,
        lpnId: null,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
    );

    app(StockLedger::class)->move(
        $from, $loc2->id, '3', $item->base_uom_id,
        new StockReference(referenceType: 'test', referenceId: 1, referenceNo: 'PM001'),
        ['P1', 'P2', 'P3'],
    );

    // 3 moved to loc2
    expect(SerialNumber::where('item_id', $item->id)->where('location_id', $loc2->id)->count())->toBe(3);
    // 2 remain at loc
    expect(SerialNumber::where('item_id', $item->id)->where('location_id', $loc->id)->count())->toBe(2);
});
