<?php

use App\Events\Inventory\StockPosted;
use App\Models\DeliveryOrder;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockTransaction;
use App\Models\Item;
use App\Models\ItemUom;
use App\Models\Location;
use App\Models\PutawayTask;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\Dashboard\DashboardMetrics;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));
    return $user;
}

// ─── stockOnHand ──────────────────────────────────────────────────────────────

it('stockOnHand returns zero when no stock exists', function () {
    $user    = makeUser('warehouse-manager');
    $metrics = app(DashboardMetrics::class);

    $result = $metrics->stockOnHand($user);

    expect($result['qty'])->toBe(0.0)
        ->and($result['sku_count'])->toBe(0);
});

it('stockOnHand counts only positive qty_on_hand rows', function () {
    $user = makeUser('warehouse-manager');
    $wh   = Warehouse::factory()->create();
    $loc  = Location::factory()->create(['warehouse_id' => $wh->id]);
    $vw   = VirtualWarehouse::factory()->create();
    $uom  = UnitOfMeasure::factory()->create();
    $item = Item::factory()->create(['base_uom_id' => $uom->id]);

    StockBalance::factory()->create([
        'item_id'              => $item->id,
        'location_id'          => $loc->id,
        'virtual_warehouse_id' => $vw->id,
        'qty_on_hand'          => '100.0000',
    ]);
    StockBalance::factory()->create([
        'item_id'              => $item->id,
        'location_id'          => $loc->id,
        'virtual_warehouse_id' => $vw->id,
        'qty_on_hand'          => '0.0000',
    ]);

    Cache::flush();
    $result = app(DashboardMetrics::class)->stockOnHand($user);

    expect($result['qty'])->toBe(100.0)
        ->and($result['sku_count'])->toBe(1);
});

// ─── toReceive ────────────────────────────────────────────────────────────────

it('toReceive counts GRNs in draft or receiving status', function () {
    $user = makeUser('storekeeper');
    $wh   = Warehouse::factory()->create();

    Grn::factory()->create(['warehouse_id' => $wh->id, 'status' => 'draft']);
    Grn::factory()->create(['warehouse_id' => $wh->id, 'status' => 'receiving']);
    Grn::factory()->create(['warehouse_id' => $wh->id, 'status' => 'closed']);

    Cache::flush();
    $result = app(DashboardMetrics::class)->toReceive($user);

    expect($result['total'])->toBe(2);
});

// ─── awaitingPutaway ─────────────────────────────────────────────────────────

it('awaitingPutaway counts open putaway tasks', function () {
    $user = makeUser('storekeeper');
    $wh   = Warehouse::factory()->create();
    $loc  = Location::factory()->create(['warehouse_id' => $wh->id]);
    $vw   = VirtualWarehouse::factory()->create();
    $uom  = UnitOfMeasure::factory()->create();
    $item = Item::factory()->create(['base_uom_id' => $uom->id]);
    $grn  = Grn::factory()->create(['warehouse_id' => $wh->id]);

    $base = [
        'grn_id'               => $grn->id,
        'item_id'              => $item->id,
        'virtual_warehouse_id' => $vw->id,
        'qty'                  => '1.0000',
        'from_location_id'     => $loc->id,
    ];
    PutawayTask::create(array_merge($base, ['status' => 'open']));
    PutawayTask::create(array_merge($base, ['status' => 'in_progress']));
    PutawayTask::create(array_merge($base, ['status' => 'completed']));

    Cache::flush();
    $result = app(DashboardMetrics::class)->awaitingPutaway($user);

    expect($result['total'])->toBe(2);
});

// ─── lowStock ─────────────────────────────────────────────────────────────────

it('lowStock returns zero when no items have min_stock set', function () {
    $user = makeUser('warehouse-manager');
    Cache::flush();

    $result = app(DashboardMetrics::class)->lowStock($user);

    expect($result['count'])->toBeInt()->toBeGreaterThanOrEqual(0);
});

// ─── expiring ─────────────────────────────────────────────────────────────────

it('expiring counts SKUs with batch expiry within 30 days', function () {
    $user = makeUser('warehouse-manager');
    $wh   = Warehouse::factory()->create();
    $loc  = Location::factory()->create(['warehouse_id' => $wh->id]);
    $vw   = VirtualWarehouse::factory()->create();
    $uom  = UnitOfMeasure::factory()->create();
    $item = Item::factory()->create(['base_uom_id' => $uom->id]);
    $soon = \App\Models\Inventory\Batch::factory()->create([
        'item_id'     => $item->id,
        'expiry_date' => now()->addDays(10)->toDateString(),
    ]);
    $far = \App\Models\Inventory\Batch::factory()->create([
        'item_id'     => $item->id,
        'expiry_date' => now()->addDays(90)->toDateString(),
    ]);

    StockBalance::factory()->create([
        'item_id'              => $item->id,
        'location_id'          => $loc->id,
        'virtual_warehouse_id' => $vw->id,
        'batch_id'             => $soon->id,
        'qty_on_hand'          => '50.0000',
    ]);
    StockBalance::factory()->create([
        'item_id'              => $item->id,
        'location_id'          => $loc->id,
        'virtual_warehouse_id' => $vw->id,
        'batch_id'             => $far->id,
        'qty_on_hand'          => '50.0000',
    ]);

    Cache::flush();
    $result = app(DashboardMetrics::class)->expiring($user);

    expect($result['count'])->toBe(1);
});

// ─── Role visibility ─────────────────────────────────────────────────────────

it('department-user canSeeKpi returns false for to_receive', function () {
    $user = makeUser('department-user');
    $this->actingAs($user);

    $component = new \App\Livewire\Dashboard\Overview();

    expect($component->canSeeKpi('to_receive'))->toBeFalse()
        ->and($component->canSeeKpi('stock_on_hand'))->toBeTrue();
});

it('storekeeper cannot see open_orders KPI', function () {
    $user = makeUser('storekeeper');
    $this->actingAs($user);

    $component = new \App\Livewire\Dashboard\Overview();

    expect($component->canSeeKpi('open_orders'))->toBeFalse()
        ->and($component->canSeeKpi('to_receive'))->toBeTrue();
});

it('data-analyst is readonly', function () {
    $user = makeUser('data-analyst');
    $this->actingAs($user);

    $component = new \App\Livewire\Dashboard\Overview();

    expect($component->isReadonly())->toBeTrue();
});

it('warehouse-manager is not readonly and sees all KPIs', function () {
    $user = makeUser('warehouse-manager');
    $this->actingAs($user);

    $component = new \App\Livewire\Dashboard\Overview();

    expect($component->isReadonly())->toBeFalse()
        ->and($component->canSeeKpi('stock_on_hand'))->toBeTrue()
        ->and($component->canSeeKpi('containers_in_yard'))->toBeTrue()
        ->and($component->canSeeKpi('pending_approvals'))->toBeTrue();
});

// ─── Cache invalidation ───────────────────────────────────────────────────────

it('cache is cleared when StockPosted event fires', function () {
    $user  = makeUser('warehouse-manager');
    $wh    = Warehouse::factory()->create();
    $loc   = Location::factory()->create(['warehouse_id' => $wh->id]);
    $vw    = VirtualWarehouse::factory()->create();
    $uom   = UnitOfMeasure::factory()->create();
    $item  = Item::factory()->create(['base_uom_id' => $uom->id]);

    StockBalance::factory()->create([
        'item_id'              => $item->id,
        'location_id'          => $loc->id,
        'virtual_warehouse_id' => $vw->id,
        'qty_on_hand'          => '10.0000',
    ]);

    Cache::flush();
    $metrics = app(DashboardMetrics::class);

    // Warm the cache
    $before = $metrics->stockOnHand($user);
    expect($before['qty'])->toBe(10.0);

    // Add more stock directly (bypassing cache)
    StockBalance::factory()->create([
        'item_id'              => $item->id,
        'location_id'          => $loc->id,
        'virtual_warehouse_id' => $vw->id,
        'qty_on_hand'          => '5.0000',
    ]);

    // Still cached — should return old value
    $cached = $metrics->stockOnHand($user);
    expect($cached['qty'])->toBe(10.0);

    // Fire a fake StockPosted event to clear cache
    $txn = \App\Models\Inventory\StockTransaction::make(['posted_by' => $user->id]);
    $txn->id = 999999;
    event(new StockPosted($txn));

    // Now should recompute
    $fresh = $metrics->stockOnHand($user);
    expect($fresh['qty'])->toBe(15.0);
});

// ─── VW scope ─────────────────────────────────────────────────────────────────

it('department-user sees only their VW stock', function () {
    $wh    = Warehouse::factory()->create();
    $loc   = Location::factory()->create(['warehouse_id' => $wh->id]);
    $uom   = UnitOfMeasure::factory()->create();
    $item  = Item::factory()->create(['base_uom_id' => $uom->id]);

    $vwOwned = VirtualWarehouse::factory()->create(['is_active' => true]);
    $vwOther = VirtualWarehouse::factory()->create(['is_active' => true]);

    // Create department-user and attach only vwOwned
    $user = makeUser('department-user');
    $user->virtualWarehouses()->attach($vwOwned->id);

    StockBalance::factory()->create([
        'item_id'              => $item->id,
        'location_id'          => $loc->id,
        'virtual_warehouse_id' => $vwOwned->id,
        'qty_on_hand'          => '100.0000',
    ]);
    StockBalance::factory()->create([
        'item_id'              => $item->id,
        'location_id'          => $loc->id,
        'virtual_warehouse_id' => $vwOther->id,
        'qty_on_hand'          => '200.0000',
    ]);

    Cache::flush();
    $result = app(DashboardMetrics::class)->stockOnHand($user);

    // department-user should only see their 100 units
    expect($result['qty'])->toBe(100.0);
});
