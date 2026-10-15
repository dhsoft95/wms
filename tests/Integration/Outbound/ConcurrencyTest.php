<?php

declare(strict_types=1);

/**
 * MySQL-only concurrency tests for AllocationService.
 *
 * Run with:
 *   php artisan test --configuration=phpunit.mysql.xml --group=mysql
 *
 * Lives in tests/Integration/ (not Feature/) because DatabaseMigrations
 * is required here: raw-PDO connections cannot see data that is only inside
 * Laravel's per-test RefreshDatabase transaction.
 */

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
use App\Models\Inventory\StockBalance;
use App\Models\Item;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\Foundation\DocumentNumber;
use App\Services\Inventory\StockLedger;
use App\Services\Outbound\AllocationService;
use Database\Seeders\FoundationDefaultsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class)->group('mysql');

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Opens a brand-new raw PDO connection to the same MySQL database Laravel uses.
 * Independent of Laravel's connection pool — has its own transaction state.
 */
function allocConcRawPdo(): PDO
{
    $c   = config('database.connections.' . config('database.default'));
    $dsn = ! empty($c['unix_socket'])
        ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $c['unix_socket'], $c['database'])
        : sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $c['host'], $c['port'] ?? 3306, $c['database']);

    return new PDO($dsn, $c['username'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

function allocConcUom(): UnitOfMeasure
{
    return UnitOfMeasure::firstOrCreate(
        ['code' => 'PCS'],
        ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
    );
}

function allocConcItem(): Item
{
    return Item::create([
        'sku'          => 'CONC-ALLOC-' . uniqid(),
        'name'         => 'Concurrency Alloc Item',
        'base_uom_id'  => allocConcUom()->id,
        'is_active'    => true,
        'track_batch'  => false,
        'track_expiry' => false,
        'track_serial' => false,
    ]);
}

function allocConcWarehouse(): Warehouse
{
    return Warehouse::firstOrCreate(
        ['code' => 'CONC-ALLOC-WH'],
        ['name' => 'Concurrency Alloc Warehouse', 'is_active' => true]
    );
}

function allocConcLocation(Warehouse $wh): Location
{
    return Location::create([
        'warehouse_id' => $wh->id,
        'code'         => 'CONC-ALLOC-LOC-' . uniqid(),
        'segment_code' => 'C',
        'level'        => LocationLevel::Bin,
        'type'         => LocationType::Storage,
        'status'       => LocationStatus::Active,
        'is_pickable'  => true,
        'path'         => '/',
    ]);
}

function allocConcVw(Warehouse $wh): VirtualWarehouse
{
    $dept = Department::firstOrCreate(
        ['code' => 'CONC-ALLOC-DEPT'],
        ['name' => 'Concurrency Alloc Dept', 'is_active' => true]
    );

    return VirtualWarehouse::create([
        'code'          => 'CONC-VW-' . uniqid(),
        'name'          => 'Concurrency VW ' . uniqid(),
        'department_id' => $dept->id,
        'is_active'     => true,
    ]);
}

function allocConcUser(): User
{
    return User::factory()->create(['status' => 'active']);
}

function makeAllocConcDo(Warehouse $wh, VirtualWarehouse $vw, User $user): DeliveryOrder
{
    return DeliveryOrder::create([
        'number'               => DocumentNumber::next('DO'),
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

// ─── beforeEach ───────────────────────────────────────────────────────────────

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Concurrency tests require MySQL / MariaDB with InnoDB.');
    }

    $this->seed(RolePermissionSeeder::class);
    $this->seed(FoundationDefaultsSeeder::class);

    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
});

// ─── Test: concurrent allocations do not double-reserve ───────────────────────

it('test_concurrent_allocations_do_not_double_reserve', function () {
    $wh   = allocConcWarehouse();
    $vw   = allocConcVw($wh);
    $item = allocConcItem();
    $loc  = allocConcLocation($wh);
    $user = allocConcUser();

    $ledger = app(StockLedger::class);

    // ── Setup: receive only 5 units (committed to DB — DatabaseMigrations, no outer tx)
    $ledger->receive(
        new StockLine(
            itemId:             $item->id,
            locationId:         $loc->id,
            virtualWarehouseId: $vw->id,
            stockStatus:        StockStatus::Available,
            enteredQty:         '5.0000',
            enteredUomId:       $item->base_uom_id,
        ),
        new StockReference('test', 1, 'CONC-ALLOC-RECV-' . uniqid())
    );

    $balance = StockBalance::where('item_id', $item->id)
        ->where('location_id', $loc->id)
        ->first();

    // ── Create two DOs each requesting 5 units (total 10 — more than available)
    $doA = makeAllocConcDo($wh, $vw, $user);
    $doB = makeAllocConcDo($wh, $vw, $user);

    DeliveryOrderLine::create([
        'delivery_order_id'  => $doA->id,
        'line_no'            => 1,
        'item_id'            => $item->id,
        'uom_id'             => $item->base_uom_id,
        'requested_qty'      => '5.0000',
        'requested_qty_base' => '5.0000',
        'status'             => DoLineStatus::Pending,
    ]);

    DeliveryOrderLine::create([
        'delivery_order_id'  => $doB->id,
        'line_no'            => 1,
        'item_id'            => $item->id,
        'uom_id'             => $item->base_uom_id,
        'requested_qty'      => '5.0000',
        'requested_qty_base' => '5.0000',
        'status'             => DoLineStatus::Pending,
    ]);

    // ── Phase 1: Connection A grabs a FOR UPDATE lock on the balance row ─────
    //   This simulates the first allocation process entering its DB::transaction.
    $connA = allocConcRawPdo();
    $connA->beginTransaction();
    $connA->prepare('SELECT id FROM stock_balances WHERE id = ? FOR UPDATE')
          ->execute([$balance->id]);

    // ── Phase 2: Connection B (Laravel main) tries to allocate doB with a
    //   1-second lock-wait timeout — must block because A holds the row lock.
    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

    $service = app(AllocationService::class);

    $blockedByLock = false;
    try {
        $service->allocate($doB->fresh());
    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        $blockedByLock = str_contains($msg, 'wait timeout')
                      || str_contains($msg, 'Deadlock')
                      || str_contains($msg, '1205')
                      || str_contains($msg, 'Lock wait');
    }

    expect($blockedByLock)->toBeTrue(
        'AllocationService::allocate() must time out while connection A holds the FOR UPDATE lock on stock_balances.'
    );

    // Balance is untouched — B's transaction was rolled back by the exception
    $balance->refresh();
    expect((float) $balance->qty_allocated)->toBe(0.0);

    // ── Phase 3: A releases the lock (rollback — no data change, just proves locking)
    $connA->rollBack();

    // ── Phase 4: Reset timeout; allocate doA — must succeed and reserve all 5 units
    DB::statement('SET SESSION innodb_lock_wait_timeout = 50');

    $service->allocate($doA->fresh());

    $balance->refresh();
    expect((float) $balance->qty_allocated)->toBe(5.0);

    // ── Phase 5: Now try to allocate doB — it requests 5 units but 0 are free.
    //   AllocationService must leave the DO in Approved status (no stock available)
    //   and must NOT over-reserve the balance.
    $service->allocate($doB->fresh());

    $doB->refresh();
    $balance->refresh();

    expect($doB->status)->toBe(DeliveryOrderStatus::Approved);

    // CRITICAL: total reserved must never exceed qty_on_hand
    expect((float) $balance->qty_allocated)
        ->toBeLessThanOrEqual((float) $balance->qty_on_hand);

    // Only doA should have an allocation record
    $allocsA = DeliveryOrderAllocation::whereHas('line', fn ($q) => $q->where('delivery_order_id', $doA->id))->count();
    $allocsB = DeliveryOrderAllocation::whereHas('line', fn ($q) => $q->where('delivery_order_id', $doB->id))->count();

    expect($allocsA)->toBeGreaterThan(0)
        ->and($allocsB)->toBe(0);
});
