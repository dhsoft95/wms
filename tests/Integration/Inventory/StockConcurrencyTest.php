<?php

/**
 * MySQL-only concurrency tests for StockLedger and StockReservations.
 *
 * Run with:
 *   php artisan test --configuration=phpunit.mysql.xml --group=mysql
 *
 * Lives in tests/Integration/ (not Feature/) because DatabaseMigrations
 * is required here: raw-PDO connections cannot see data that is only inside
 * Laravel's per-test RefreshDatabase transaction.
 */

use App\Data\Inventory\StockLine;
use App\Data\Inventory\StockPosting;
use App\Data\Inventory\StockReference;
use App\Enums\Inventory\MovementType;
use App\Enums\Inventory\ReservationStatus;
use App\Enums\Inventory\StockStatus;
use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Models\Department;
use App\Models\DocumentSequence;
use App\Models\Inventory\StockBalance;
use App\Models\Item;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\Foundation\DocumentNumber;
use App\Services\Inventory\StockLedger;
use App\Services\Inventory\StockReservations;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class)->group('mysql');

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Opens a brand-new raw PDO connection to the same MySQL database Laravel uses.
 * Independent of Laravel's connection pool — has its own transaction state.
 */
function stockConcRawPdo(): PDO
{
    $c   = config('database.connections.' . config('database.default'));
    $dsn = ! empty($c['unix_socket'])
        ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $c['unix_socket'], $c['database'])
        : sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $c['host'], $c['port'] ?? 3306, $c['database']);

    return new PDO($dsn, $c['username'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

function stockConcItem(): Item
{
    $uom = UnitOfMeasure::firstOrCreate(
        ['code' => 'PCS'],
        ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
    );

    return Item::create([
        'sku'          => 'CONC-' . uniqid(),
        'name'         => 'Concurrency Test Item',
        'base_uom_id'  => $uom->id,
        'is_active'    => true,
        'track_batch'  => false,
        'track_expiry' => false,
        'track_serial' => false,
    ]);
}

function stockConcLocation(): Location
{
    $warehouse = Warehouse::firstOrCreate(
        ['code' => 'TWH'],
        ['name' => 'Test Warehouse', 'is_active' => true]
    );

    return Location::create([
        'warehouse_id' => $warehouse->id,
        'code'         => 'LOC-CC-' . uniqid(),
        'segment_code' => 'C',
        'level'        => LocationLevel::Bin,
        'type'         => LocationType::Storage,
        'status'       => LocationStatus::Active,
        'is_pickable'  => true,
        'path'         => '/',
    ]);
}

function stockConcVw(): VirtualWarehouse
{
    $dept = Department::firstOrCreate(
        ['code' => 'TDEPT'],
        ['name' => 'Test Department', 'is_active' => true]
    );

    return VirtualWarehouse::create([
        'code'          => 'VW-CC-' . uniqid(),
        'name'          => 'Concurrency VW ' . uniqid(),
        'department_id' => $dept->id,
        'is_active'     => true,
    ]);
}

function stockConcRef(string $suffix = '001'): StockReference
{
    return new StockReference(referenceType: 'test', referenceId: 1, referenceNo: "CONC-{$suffix}");
}

// ─── beforeEach ───────────────────────────────────────────────────────────────

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Concurrency tests require MySQL / MariaDB with InnoDB.');
    }

    DocumentSequence::firstOrCreate(
        ['type' => 'STK'],
        ['prefix' => 'STK', 'include_year' => true, 'padding' => 4, 'reset_yearly' => true, 'next_number' => 1]
    );
});

// ─── Test 1: FOR UPDATE on stock_balances blocks a second connection ──────────

it('lockForUpdate on stock_balances blocks a competing connection until the holder commits', function () {
    $item   = stockConcItem();
    $loc    = stockConcLocation();
    $vw     = stockConcVw();
    $ledger = app(StockLedger::class);

    // ── Setup: receive 10 units — committed to DB (DatabaseMigrations, no outer tx)
    $ledger->receive(new StockLine(
        itemId: $item->id,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
        enteredQty: '10.0000',
        enteredUomId: $item->base_uom_id,
    ), stockConcRef('LOCK-001'));

    $balance = StockBalance::where('item_id', $item->id)
        ->where('location_id', $loc->id)->first();

    // ── Phase 1: Connection A grabs the FOR UPDATE lock on the balance row
    $connA = stockConcRawPdo();
    $connA->beginTransaction();
    $connA->prepare('SELECT id FROM stock_balances WHERE id = ? FOR UPDATE')
          ->execute([$balance->id]);

    // ── Phase 2: Main Laravel connection (B) tries to dispatch with a 1-second
    //   lock-wait timeout — must fail because A holds the row lock.
    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

    $outLine = new StockLine(
        itemId: $item->id,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
        enteredQty: '-5.0000',
        enteredUomId: $item->base_uom_id,
    );

    $blockedByLock = false;
    try {
        $ledger->post(new StockPosting(MovementType::Dispatch, stockConcRef('LOCK-002'), [$outLine]));
    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        $blockedByLock = str_contains($msg, 'wait timeout')
                      || str_contains($msg, 'Deadlock')
                      || str_contains($msg, '1205')
                      || str_contains($msg, 'Lock wait');
    }

    expect($blockedByLock)->toBeTrue(
        'StockLedger::post() must time out while connection A holds the FOR UPDATE lock.'
    );

    // Balance is untouched — B's transaction was rolled back by the exception
    $balance->refresh();
    expect((float) $balance->qty_on_hand)->toBe(10.0);

    // ── Phase 3: A releases the lock (rollback — no data change, just proves locking)
    $connA->rollBack();

    // ── Phase 4: Reset timeout; A (as the default connection) dispatches all 10 units
    DB::statement('SET SESSION innodb_lock_wait_timeout = 50');

    $ledger->post(new StockPosting(MovementType::Dispatch, stockConcRef('LOCK-003'), [
        new StockLine(
            itemId: $item->id,
            locationId: $loc->id,
            virtualWarehouseId: $vw->id,
            stockStatus: StockStatus::Available,
            enteredQty: '-10.0000',
            enteredUomId: $item->base_uom_id,
        ),
    ]));

    $balance->refresh();
    expect((float) $balance->qty_on_hand)->toBe(0.0);

    // ── Phase 5: B retries the original 5-unit dispatch — must fail (0 stock)
    $wasInsufficient = false;
    try {
        $ledger->post(new StockPosting(MovementType::Dispatch, stockConcRef('LOCK-004'), [$outLine]));
    } catch (\DomainException $e) {
        $wasInsufficient = str_contains($e->getMessage(), 'Insufficient');
    }

    expect($wasInsufficient)->toBeTrue(
        'B\'s retry must fail with DomainException — A already consumed all stock.'
    );

    // Final balance is exactly 0 — never negative
    $balance->refresh();
    expect((float) $balance->qty_on_hand)->toBe(0.0);
});

// ─── Test 2: StockReservations — two reservations cannot exceed available ─────

it('StockReservations::reserve() blocks when another connection holds the balance lock', function () {
    $item         = stockConcItem();
    $loc          = stockConcLocation();
    $vw           = stockConcVw();
    $ledger       = app(StockLedger::class);
    $reservations = app(StockReservations::class);

    // ── Setup: 5 units, committed
    $ledger->receive(new StockLine(
        itemId: $item->id,
        locationId: $loc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: StockStatus::Available,
        enteredQty: '5.0000',
        enteredUomId: $item->base_uom_id,
    ), stockConcRef('RES-001'));

    $balance = StockBalance::where('item_id', $item->id)
        ->where('location_id', $loc->id)->first();

    // ── Phase 1: Connection A holds FOR UPDATE lock on the balance row
    $connA = stockConcRawPdo();
    $connA->beginTransaction();
    $connA->prepare('SELECT id FROM stock_balances WHERE id = ? FOR UPDATE')
          ->execute([$balance->id]);

    // ── Phase 2: B tries to reserve 3 units — must time out (locked by A)
    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

    $blockedByLock = false;
    try {
        $reservations->reserve($balance->fresh(), '3.0000', 'sales_order', 99);
    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        $blockedByLock = str_contains($msg, 'wait timeout')
                      || str_contains($msg, 'Deadlock')
                      || str_contains($msg, '1205')
                      || str_contains($msg, 'Lock wait');
    }

    expect($blockedByLock)->toBeTrue(
        'StockReservations::reserve() must time out while connection A holds the FOR UPDATE lock.'
    );

    // ── Phase 3: A releases the lock (no data change)
    $connA->rollBack();
    DB::statement('SET SESSION innodb_lock_wait_timeout = 50');

    // ── Phase 4: A reserves all 5 available units
    $resA = $reservations->reserve($balance->fresh(), '5.0000', 'order', 1);
    expect($resA->status)->toBe(ReservationStatus::Active);

    $balance->refresh();
    expect((float) $balance->qty_allocated)->toBe(5.0);

    // ── Phase 5: B tries to reserve 3 units — must fail (0 available after A)
    $insufficientStock = false;
    try {
        $reservations->reserve($balance->fresh(), '3.0000', 'order', 2);
    } catch (\DomainException $e) {
        $insufficientStock = str_contains($e->getMessage(), 'available');
    }

    expect($insufficientStock)->toBeTrue(
        'B\'s reserve must fail — A already allocated all available stock.'
    );

    // qty_allocated must never exceed qty_on_hand
    $balance->refresh();
    expect((float) $balance->qty_allocated)->toBeLessThanOrEqual((float) $balance->qty_on_hand);
});

// ─── Test 3: DocumentNumber::next() — no duplicates under contention ──────────

it('DocumentNumber::next() lockForUpdate blocks a competing PDO connection', function () {
    // Connection A: BEGIN; lock the STK sequence row
    $connA = stockConcRawPdo();
    $connA->beginTransaction();
    $connA->prepare('SELECT id FROM document_sequences WHERE type = ? FOR UPDATE')
          ->execute(['STK']);

    // B (main Laravel connection): try DocumentNumber::next('STK') with 1-second timeout
    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

    $blockedByLock = false;
    try {
        DocumentNumber::next('STK');
    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        $blockedByLock = str_contains($msg, 'wait timeout')
                      || str_contains($msg, 'Deadlock')
                      || str_contains($msg, '1205')
                      || str_contains($msg, 'Lock wait');
    }

    expect($blockedByLock)->toBeTrue(
        'DocumentNumber::next() must time out while connection A holds the sequence lock.'
    );

    // A commits (releases lock, no update — just proved locking)
    $connA->rollBack();
    DB::statement('SET SESSION innodb_lock_wait_timeout = 50');

    // After release, next call succeeds normally and advances the counter
    $first  = DocumentNumber::next('STK');
    $second = DocumentNumber::next('STK');

    expect($first)->toStartWith('STK')
        ->and($second)->toStartWith('STK')
        ->and($first)->not->toBe($second);

    $seq = DB::table('document_sequences')->where('type', 'STK')->first();
    expect((int) $seq->next_number)->toBe(3);
});
