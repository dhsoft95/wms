<?php

use App\Data\Inventory\StockKey;
use App\Data\Inventory\StockLine;
use App\Data\Inventory\StockPosting;
use App\Data\Inventory\StockReference;
use App\Enums\Inventory\MovementType;
use App\Enums\Inventory\ReservationStatus;
use App\Enums\Inventory\StockStatus;
use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Events\Inventory\StockPosted;
use App\Models\Department;
use App\Models\Inventory\Batch;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockReservation;
use App\Models\Inventory\StockTransaction;
use App\Models\Item;
use App\Models\ItemUom;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedger;
use App\Services\Inventory\StockReservations;
use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

// Seed the STK document sequence before every test
beforeEach(function () {
    DocumentSequence::firstOrCreate(
        ['type' => 'STK'],
        ['prefix' => 'STK', 'include_year' => true, 'padding' => 4, 'reset_yearly' => true, 'next_number' => 1]
    );
});

// ─── Test Helpers ─────────────────────────────────────────────────────────────

function makeItem(array $attrs = []): Item
{
    $uom = UnitOfMeasure::firstOrCreate(
        ['code' => 'PCS'],
        ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
    );

    return Item::create(array_merge([
        'sku'          => 'TEST-' . uniqid(),
        'name'         => 'Test Item',
        'base_uom_id'  => $uom->id,
        'is_active'    => true,
        'track_batch'  => false,
        'track_expiry' => false,
        'track_serial' => false,
    ], $attrs));
}

function makeLocation(array $attrs = []): Location
{
    $warehouse = Warehouse::firstOrCreate(
        ['code' => 'TWH'],
        ['name' => 'Test Warehouse', 'is_active' => true]
    );

    return Location::create(array_merge([
        'warehouse_id' => $warehouse->id,
        'code'         => 'LOC-' . uniqid(),
        'segment_code' => 'T',
        'level'        => LocationLevel::Bin,
        'type'         => LocationType::Storage,
        'status'       => LocationStatus::Active,
        'is_pickable'  => true,
        'path'         => '/',
    ], $attrs));
}

function makeVw(array $attrs = []): VirtualWarehouse
{
    $dept = Department::firstOrCreate(
        ['code' => 'TDEPT'],
        ['name' => 'Test Department', 'is_active' => true]
    );

    return VirtualWarehouse::create(array_merge([
        'code'          => 'VW-' . uniqid(),
        'name'          => 'Test VW ' . uniqid(),
        'department_id' => $dept->id,
        'is_active'     => true,
    ], $attrs));
}

function makeRef(): StockReference
{
    return new StockReference(referenceType: 'test', referenceId: 1, referenceNo: 'TEST-001');
}

function postInbound(Item $item, Location $location, VirtualWarehouse $vw, string $qty, StockStatus $status = StockStatus::Available, ?string $idempotencyKey = null): StockTransaction
{
    $ledger = app(StockLedger::class);
    $line   = new StockLine(
        itemId: $item->id,
        locationId: $location->id,
        virtualWarehouseId: $vw->id,
        stockStatus: $status,
        enteredQty: $qty,
        enteredUomId: $item->base_uom_id,
    );

    return $ledger->receive($line, makeRef(), $idempotencyKey);
}

// ─── Test Group 1: Balance = Ledger Sum ───────────────────────────────────────

it('balance always equals sum of ledger lines after many postings', function () {
    $item = makeItem();
    $loc  = makeLocation();
    $vw   = makeVw();

    $ledger = app(StockLedger::class);

    // Five inbound postings of varying amounts
    $amounts = ['10.0000', '25.0000', '15.0000', '5.0000', '30.0000'];
    foreach ($amounts as $qty) {
        $line = new StockLine(
            itemId: $item->id,
            locationId: $loc->id,
            virtualWarehouseId: $vw->id,
            stockStatus: StockStatus::Available,
            enteredQty: $qty,
            enteredUomId: $item->base_uom_id,
        );
        $ledger->receive($line, makeRef());
    }

    $balance = StockBalance::where('item_id', $item->id)
        ->where('location_id', $loc->id)
        ->where('virtual_warehouse_id', $vw->id)
        ->where('stock_status', StockStatus::Available)
        ->first();

    $ledgerSum = DB::table('stock_movements')
        ->where('item_id', $item->id)
        ->where('location_id', $loc->id)
        ->where('virtual_warehouse_id', $vw->id)
        ->where('stock_status', StockStatus::Available->value)
        ->sum('qty');

    expect($balance)->not->toBeNull()
        ->and(bccomp((string) $balance->qty_on_hand, number_format((float) $ledgerSum, 4, '.', ''), 4))->toBe(0);
});

// ─── Test Group 2: Negative Stock Rejected ────────────────────────────────────

it('rejects outbound posting that would make stock negative', function () {
    $item = makeItem();
    $loc  = makeLocation();
    $vw   = makeVw();

    postInbound($item, $loc, $vw, '10.0000');

    $ledger = app(StockLedger::class);
    $line   = new StockLine(
        itemId: $item->id,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
        enteredQty: '-15.0000',
        enteredUomId: $item->base_uom_id,
    );

    expect(fn () => $ledger->post(new StockPosting(MovementType::Dispatch, makeRef(), [$line])))
        ->toThrow(\DomainException::class);
});

it('allows outbound up to available qty when no reservation exists', function () {
    $item = makeItem();
    $loc  = makeLocation();
    $vw   = makeVw();

    postInbound($item, $loc, $vw, '10.0000');

    $ledger = app(StockLedger::class);
    $line   = new StockLine(
        itemId: $item->id,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
        enteredQty: '-10.0000',
        enteredUomId: $item->base_uom_id,
    );

    $tx = $ledger->post(new StockPosting(MovementType::Dispatch, makeRef(), [$line]));

    $balance = StockBalance::where('item_id', $item->id)
        ->where('location_id', $loc->id)
        ->first();

    expect($tx)->toBeInstanceOf(StockTransaction::class)
        ->and((float) $balance->qty_on_hand)->toBe(0.0);
});

it('outbound without reservation fails when available qty is insufficient due to allocation', function () {
    $item = makeItem();
    $loc  = makeLocation();
    $vw   = makeVw();

    postInbound($item, $loc, $vw, '10.0000');

    $balance = StockBalance::where('item_id', $item->id)->where('location_id', $loc->id)->first();

    $reservations = app(StockReservations::class);
    $reservations->reserve($balance, '8.0000', 'test', 1);

    // Now only 2 units are free; attempting to dispatch 10 (no reservation) should fail
    $ledger = app(StockLedger::class);
    $line   = new StockLine(
        itemId: $item->id,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
        enteredQty: '-10.0000',
        enteredUomId: $item->base_uom_id,
    );

    expect(fn () => $ledger->post(new StockPosting(MovementType::Dispatch, makeRef(), [$line])))
        ->toThrow(\DomainException::class);
});

// ─── Test Group 3: Move Nets to Zero ──────────────────────────────────────────

it('move creates two movements that net to zero and updates both balances', function () {
    $item = makeItem();
    $locA = makeLocation();
    $locB = makeLocation();
    $vw   = makeVw();

    postInbound($item, $locA, $vw, '10.0000');

    $ledger = app(StockLedger::class);
    $key    = new StockKey(
        itemId: $item->id,
        batchId: null,
        lpnId: null,
        locationId: $locA->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
    );

    $moveTx = $ledger->move($key, $locB->id, '5.0000', $item->base_uom_id, makeRef());

    $balA = StockBalance::where('item_id', $item->id)->where('location_id', $locA->id)->first();
    $balB = StockBalance::where('item_id', $item->id)->where('location_id', $locB->id)->first();

    expect($balA)->not->toBeNull()
        ->and($balB)->not->toBeNull()
        ->and((float) $balA->qty_on_hand)->toBe(5.0)
        ->and((float) $balB->qty_on_hand)->toBe(5.0);

    $movementsNet = DB::table('stock_movements')
        ->where('stock_transaction_id', $moveTx->id)
        ->sum('qty');

    expect(bccomp(number_format((float) $movementsNet, 4, '.', ''), '0.0000', 4))->toBe(0);
});

// ─── Test Group 4: Quarantine Forces On Hold ──────────────────────────────────

it('inbound to quarantine location forces stock_status to on_hold', function () {
    $item    = makeItem();
    $qrtLoc  = makeLocation(['type' => LocationType::Quarantine, 'is_pickable' => false]);
    $vw      = makeVw();

    postInbound($item, $qrtLoc, $vw, '10.0000', StockStatus::Available);

    $balance = StockBalance::where('item_id', $item->id)
        ->where('location_id', $qrtLoc->id)
        ->first();

    expect($balance)->not->toBeNull()
        ->and($balance->stock_status)->toBe(StockStatus::OnHold);
});

it('rejects inbound to a blocked location', function () {
    $item       = makeItem();
    $blockedLoc = makeLocation(['status' => LocationStatus::Blocked]);
    $vw         = makeVw();

    expect(fn () => postInbound($item, $blockedLoc, $vw, '10.0000'))
        ->toThrow(\InvalidArgumentException::class);
});

// ─── Test Group 5: Batch / Expiry Rules ──────────────────────────────────────

it('requires batch when item has track_batch', function () {
    $item = makeItem(['track_batch' => true]);
    $loc  = makeLocation();
    $vw   = makeVw();

    // No batchNo or batchId provided
    expect(fn () => postInbound($item, $loc, $vw, '10.0000'))
        ->toThrow(\InvalidArgumentException::class);
});

it('requires expiry when item has track_expiry and batch is new', function () {
    $item = makeItem(['track_batch' => true, 'track_expiry' => true]);
    $loc  = makeLocation();
    $vw   = makeVw();

    $ledger = app(StockLedger::class);
    $line   = new StockLine(
        itemId: $item->id,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
        enteredQty: '10.0000',
        enteredUomId: $item->base_uom_id,
        batchNo: 'BATCH-NOEXP',
        expiryDate: null, // no expiry provided
    );

    expect(fn () => $ledger->receive($line, makeRef()))
        ->toThrow(\InvalidArgumentException::class);
});

it('accepts batch with expiry when item requires it', function () {
    $item = makeItem(['track_batch' => true, 'track_expiry' => true]);
    $loc  = makeLocation();
    $vw   = makeVw();

    $ledger = app(StockLedger::class);
    $line   = new StockLine(
        itemId: $item->id,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
        enteredQty: '10.0000',
        enteredUomId: $item->base_uom_id,
        batchNo: 'BATCH-WEXP',
        expiryDate: \Carbon\Carbon::now()->addMonths(6),
    );

    $tx = $ledger->receive($line, makeRef());

    expect($tx)->toBeInstanceOf(StockTransaction::class);

    $batch = Batch::where('item_id', $item->id)->where('batch_no', 'BATCH-WEXP')->first();
    expect($batch)->not->toBeNull()
        ->and($batch->expiry_date)->not->toBeNull();
});

// ─── Test Group 6: UoM Conversion ────────────────────────────────────────────

it('converts entered qty to base UoM', function () {
    $pcs = UnitOfMeasure::firstOrCreate(
        ['code' => 'PCS'],
        ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
    );
    $ctn = UnitOfMeasure::firstOrCreate(
        ['code' => 'CTN'],
        ['name' => 'Carton', 'type' => 'count', 'is_active' => true]
    );

    $item = makeItem(['base_uom_id' => $pcs->id]);

    // Register CTN = 24 PCS for this item
    ItemUom::create([
        'item_id'             => $item->id,
        'uom_id'              => $pcs->id,
        'factor_to_base'      => '1.0000',
        'is_default_purchase' => false,
        'is_default_issue'    => true,
    ]);
    ItemUom::create([
        'item_id'             => $item->id,
        'uom_id'              => $ctn->id,
        'factor_to_base'      => '24.0000',
        'is_default_purchase' => true,
        'is_default_issue'    => false,
    ]);

    $loc    = makeLocation();
    $vw     = makeVw();
    $ledger = app(StockLedger::class);

    $line = new StockLine(
        itemId: $item->id,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
        enteredQty: '2.0000',
        enteredUomId: $ctn->id, // entered in cartons
    );

    $ledger->receive($line, makeRef());

    $balance = StockBalance::where('item_id', $item->id)->where('location_id', $loc->id)->first();

    expect($balance)->not->toBeNull()
        ->and((float) $balance->qty_on_hand)->toBe(48.0); // 2 CTN × 24 PCS
});

// ─── Test Group 7: Idempotency ────────────────────────────────────────────────

it('returns existing transaction for duplicate idempotency key', function () {
    $item = makeItem();
    $loc  = makeLocation();
    $vw   = makeVw();

    $idemKey = 'idem-' . uniqid();

    $tx1 = postInbound($item, $loc, $vw, '10.0000', StockStatus::Available, $idemKey);
    $tx2 = postInbound($item, $loc, $vw, '10.0000', StockStatus::Available, $idemKey);

    expect($tx1->id)->toBe($tx2->id);

    $balance = StockBalance::where('item_id', $item->id)->where('location_id', $loc->id)->first();
    expect((float) $balance->qty_on_hand)->toBe(10.0); // only increased once
});

// ─── Test Group 8: Reversal ───────────────────────────────────────────────────

it('reversal creates exact opposite and links both ways', function () {
    $item = makeItem();
    $loc  = makeLocation();
    $vw   = makeVw();

    $tx      = postInbound($item, $loc, $vw, '10.0000');
    $ledger  = app(StockLedger::class);
    $reversal = $ledger->reverse($tx, 'Test reversal');

    $balance = StockBalance::where('item_id', $item->id)->where('location_id', $loc->id)->first();

    expect((float) $balance->qty_on_hand)->toBe(0.0);

    // Reload fresh copies (these use DB::table internally so we re-query)
    $txFresh      = DB::table('stock_transactions')->where('id', $tx->id)->first();
    $reversalFresh = DB::table('stock_transactions')->where('id', $reversal->id)->first();

    expect($txFresh->reversed_by_transaction_id)->toBe($reversal->id)
        ->and($reversalFresh->reverses_transaction_id)->toBe($tx->id);
});

it('cannot reverse a transaction twice', function () {
    $item = makeItem();
    $loc  = makeLocation();
    $vw   = makeVw();

    $tx     = postInbound($item, $loc, $vw, '10.0000');
    $ledger = app(StockLedger::class);

    $ledger->reverse($tx, 'First reversal');

    // Re-query so the guard check sees the updated reversed_by_transaction_id
    // (the ledger used DB::table to update, so the original Eloquent model is stale)
    $storedTx = StockTransaction::find($tx->id);

    expect(fn () => $ledger->reverse($storedTx, 'Second reversal'))
        ->toThrow(\DomainException::class);
});

// ─── Test Group 9: Immutability ───────────────────────────────────────────────

it('StockMovement throws on update', function () {
    $item = makeItem();
    $loc  = makeLocation();
    $vw   = makeVw();

    postInbound($item, $loc, $vw, '10.0000');

    $movement = StockMovement::first();

    expect(fn () => $movement->update(['qty' => '99.0000']))
        ->toThrow(\RuntimeException::class);
});

it('StockTransaction throws on delete', function () {
    $item = makeItem();
    $loc  = makeLocation();
    $vw   = makeVw();

    postInbound($item, $loc, $vw, '10.0000');

    $tx = StockTransaction::first();

    expect(fn () => $tx->delete())
        ->toThrow(\RuntimeException::class);
});

// ─── Test Group 10: Reservations ─────────────────────────────────────────────

it('reserve reduces available stock', function () {
    $item = makeItem();
    $loc  = makeLocation();
    $vw   = makeVw();

    postInbound($item, $loc, $vw, '10.0000');

    $balance = StockBalance::where('item_id', $item->id)->where('location_id', $loc->id)->first();

    $reservations = app(StockReservations::class);
    $reservations->reserve($balance, '3.0000', 'sales_order', 99);

    $balance->refresh();

    expect((float) $balance->qty_allocated)->toBe(3.0)
        ->and((float) $balance->qty_on_hand - (float) $balance->qty_allocated)->toBe(7.0);
});

it('release restores available stock', function () {
    $item = makeItem();
    $loc  = makeLocation();
    $vw   = makeVw();

    postInbound($item, $loc, $vw, '10.0000');

    $balance = StockBalance::where('item_id', $item->id)->where('location_id', $loc->id)->first();

    $reservations = app(StockReservations::class);
    $reservation  = $reservations->reserve($balance, '3.0000', 'sales_order', 99);

    $reservations->release($reservation);

    $balance->refresh();

    expect((float) $balance->qty_allocated)->toBe(0.0)
        ->and($reservation->fresh()->status)->toBe(ReservationStatus::Released);
});

it('partial release reduces reservation qty', function () {
    $item = makeItem();
    $loc  = makeLocation();
    $vw   = makeVw();

    postInbound($item, $loc, $vw, '10.0000');

    $balance = StockBalance::where('item_id', $item->id)->where('location_id', $loc->id)->first();

    $reservations = app(StockReservations::class);
    $reservation  = $reservations->reserve($balance, '6.0000', 'sales_order', 99);

    $reservations->release($reservation, '4.0000');

    $balance->refresh();
    $reservation->refresh();

    expect((float) $balance->qty_allocated)->toBe(2.0)
        ->and((float) $reservation->qty)->toBe(2.0)
        ->and($reservation->status)->toBe(ReservationStatus::Active);
});

// ─── Test Group 11: StockPosted Event ────────────────────────────────────────

it('fires StockPosted event after successful post', function () {
    Event::fake();

    $item = makeItem();
    $loc  = makeLocation();
    $vw   = makeVw();

    postInbound($item, $loc, $vw, '10.0000');

    Event::assertDispatched(StockPosted::class);
});

// ─── Test Group 12: Module 1 Hooks ───────────────────────────────────────────

it('item with movements reports hasTransactions as true', function () {
    $item = makeItem();
    $loc  = makeLocation();
    $vw   = makeVw();

    expect($item->hasTransactions())->toBeFalse();

    postInbound($item, $loc, $vw, '5.0000');

    expect($item->fresh()->hasTransactions())->toBeTrue();
});

// ─── Test Group 13: Zero Qty Rejected ────────────────────────────────────────

it('rejects a stock line with zero quantity', function () {
    $item = makeItem();
    $loc  = makeLocation();
    $vw   = makeVw();

    $ledger = app(StockLedger::class);
    $line   = new StockLine(
        itemId: $item->id,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
        enteredQty: '0.0000',
        enteredUomId: $item->base_uom_id,
    );

    expect(fn () => $ledger->receive($line, makeRef()))
        ->toThrow(\InvalidArgumentException::class, 'cannot be zero');
});

// ─── Test Group 14: Consumed/Released Reservation Rejected ───────────────────

it('rejects consuming an already-consumed reservation', function () {
    $item = makeItem();
    $loc  = makeLocation();
    $vw   = makeVw();

    postInbound($item, $loc, $vw, '20.0000');

    $balance      = StockBalance::where('item_id', $item->id)->where('location_id', $loc->id)->first();
    $reservations = app(\App\Services\Inventory\StockReservations::class);
    $reservation  = $reservations->reserve($balance, '5.0000', 'test', 1);

    // Consume it the first time
    $ledger = app(StockLedger::class);
    $line   = new StockLine(
        itemId: $item->id,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
        enteredQty: '-5.0000',
        enteredUomId: $item->base_uom_id,
        reservationId: $reservation->id,
    );
    $ledger->post(new StockPosting(MovementType::Dispatch, makeRef(), [$line]));

    // Attempt a second consumption — must fail
    postInbound($item, $loc, $vw, '5.0000'); // replenish so stock is not the constraint
    $line2 = new StockLine(
        itemId: $item->id,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
        enteredQty: '-5.0000',
        enteredUomId: $item->base_uom_id,
        reservationId: $reservation->id,
    );

    expect(fn () => $ledger->post(new StockPosting(MovementType::Dispatch, makeRef(), [$line2])))
        ->toThrow(\DomainException::class, 'cannot be consumed');
});

it('rejects consuming a released reservation', function () {
    $item = makeItem();
    $loc  = makeLocation();
    $vw   = makeVw();

    postInbound($item, $loc, $vw, '20.0000');

    $balance      = StockBalance::where('item_id', $item->id)->where('location_id', $loc->id)->first();
    $reservations = app(\App\Services\Inventory\StockReservations::class);
    $reservation  = $reservations->reserve($balance, '5.0000', 'test', 1);
    $reservations->release($reservation);

    $ledger = app(StockLedger::class);
    $line   = new StockLine(
        itemId: $item->id,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
        enteredQty: '-5.0000',
        enteredUomId: $item->base_uom_id,
        reservationId: $reservation->id,
    );

    expect(fn () => $ledger->post(new StockPosting(MovementType::Dispatch, makeRef(), [$line])))
        ->toThrow(\DomainException::class, 'cannot be consumed');
});

// ─── Test Group 15: Opening Balance Reference No ──────────────────────────────

it('opening balance sets its own STK number as reference_no', function () {
    $item = makeItem();
    $loc  = makeLocation();
    $vw   = makeVw();

    $ledger = app(StockLedger::class);
    $tx     = $ledger->openingBalance([
        new StockLine(
            itemId: $item->id,
            locationId: $loc->id,
            virtualWarehouseId: $vw->id,
            stockStatus: StockStatus::Available,
            enteredQty: '10.0000',
            enteredUomId: $item->base_uom_id,
        ),
    ]);

    expect($tx->reference_no)->toBe($tx->number)
        ->and($tx->reference_no)->toStartWith('STK');
});

// ─── Test Group 16: Tracking Flag Lock ───────────────────────────────────────

it('rejects changing track_batch when item has stock', function () {
    $item = makeItem(['track_batch' => false]);
    $loc  = makeLocation();
    $vw   = makeVw();

    postInbound($item, $loc, $vw, '10.0000');

    expect(fn () => \App\Actions\Foundation\UpdateItemAction::execute(
        item: $item,
        itemData: ['track_batch' => true],
        uoms: [],
        barcodes: [],
    ))->toThrow(\DomainException::class, 'track_batch');
});

it('rejects changing track_expiry when item has stock', function () {
    $item = makeItem(['track_batch' => true, 'track_expiry' => false]);
    $loc  = makeLocation();
    $vw   = makeVw();

    $ledger = app(StockLedger::class);
    $ledger->receive(
        new StockLine(
            itemId: $item->id,
            locationId: $loc->id,
            virtualWarehouseId: $vw->id,
            stockStatus: StockStatus::Available,
            enteredQty: '5.0000',
            enteredUomId: $item->base_uom_id,
            batchNo: 'B-LOCK',
        ),
        makeRef()
    );

    expect(fn () => \App\Actions\Foundation\UpdateItemAction::execute(
        item: $item,
        itemData: ['track_expiry' => true],
        uoms: [],
        barcodes: [],
    ))->toThrow(\DomainException::class, 'track_expiry');
});

it('allows changing track_batch when item has no stock', function () {
    $item = makeItem(['track_batch' => false]);

    expect(fn () => \App\Actions\Foundation\UpdateItemAction::execute(
        item: $item,
        itemData: ['track_batch' => true],
        uoms: [],
        barcodes: [],
    ))->not->toThrow(\DomainException::class);
});

// ─────────────────────────────────────────────────────────────────────────────

it('opening balance sets qty_on_hand correctly for multiple lines in one call', function () {
    $item1 = makeItem();
    $item2 = makeItem();
    $loc   = makeLocation();
    $vw    = makeVw();

    $ledger = app(StockLedger::class);

    $lines = [
        new StockLine(
            itemId: $item1->id,
            locationId: $loc->id,
            virtualWarehouseId: $vw->id,
            stockStatus: StockStatus::Available,
            enteredQty: '50.0000',
            enteredUomId: $item1->base_uom_id,
        ),
        new StockLine(
            itemId: $item2->id,
            locationId: $loc->id,
            virtualWarehouseId: $vw->id,
            stockStatus: StockStatus::Available,
            enteredQty: '75.0000',
            enteredUomId: $item2->base_uom_id,
        ),
    ];

    $tx = $ledger->openingBalance($lines, 'Multi-line opening balance');

    expect($tx)->toBeInstanceOf(StockTransaction::class)
        ->and($tx->type)->toBe(MovementType::OpeningBalance);

    $bal1 = StockBalance::where('item_id', $item1->id)->where('location_id', $loc->id)->first();
    $bal2 = StockBalance::where('item_id', $item2->id)->where('location_id', $loc->id)->first();

    expect((float) $bal1->qty_on_hand)->toBe(50.0)
        ->and((float) $bal2->qty_on_hand)->toBe(75.0);
});
