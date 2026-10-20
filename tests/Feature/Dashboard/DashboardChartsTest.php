<?php

use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\Dashboard\DashboardMetrics;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeChartUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));
    return $user;
}

// ─── chartStockByVw ───────────────────────────────────────────────────────────

it('chartStockByVw returns categories and series arrays', function () {
    $user = makeChartUser('warehouse-manager');
    $wh   = Warehouse::factory()->create();
    $loc  = Location::factory()->create(['warehouse_id' => $wh->id]);
    $vw   = VirtualWarehouse::factory()->create(['name' => 'Test VW']);
    $uom  = UnitOfMeasure::factory()->create();
    $item = Item::factory()->create(['base_uom_id' => $uom->id]);

    StockBalance::factory()->create([
        'item_id'              => $item->id,
        'location_id'          => $loc->id,
        'virtual_warehouse_id' => $vw->id,
        'qty_on_hand'          => '50.0000',
    ]);

    Cache::flush();
    $result = app(DashboardMetrics::class)->chartStockByVw($user);

    expect($result)->toHaveKeys(['categories', 'series'])
        ->and($result['categories'])->toContain('Test VW')
        ->and($result['series'])->not->toBeEmpty();
});

it('chartStockByVw respects VW scope for department-user', function () {
    $wh    = Warehouse::factory()->create();
    $loc   = Location::factory()->create(['warehouse_id' => $wh->id]);
    $uom   = UnitOfMeasure::factory()->create();
    $item  = Item::factory()->create(['base_uom_id' => $uom->id]);

    $vwOwned = VirtualWarehouse::factory()->create(['name' => 'Owned VW', 'is_active' => true]);
    $vwOther = VirtualWarehouse::factory()->create(['name' => 'Other VW', 'is_active' => true]);

    $user = makeChartUser('department-user');
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
    $result = app(DashboardMetrics::class)->chartStockByVw($user);

    expect($result['categories'])->toContain('Owned VW')
        ->and($result['categories'])->not->toContain('Other VW');
});

// ─── chartStockByCategory ─────────────────────────────────────────────────────

it('chartStockByCategory returns top categories', function () {
    $user = makeChartUser('warehouse-manager');
    $wh   = Warehouse::factory()->create();
    $loc  = Location::factory()->create(['warehouse_id' => $wh->id]);
    $vw   = VirtualWarehouse::factory()->create();
    $uom  = UnitOfMeasure::factory()->create();
    $cat  = ItemCategory::factory()->create(['name' => 'Electronics']);
    $item = Item::factory()->create(['base_uom_id' => $uom->id, 'category_id' => $cat->id]);

    StockBalance::factory()->create([
        'item_id'              => $item->id,
        'location_id'          => $loc->id,
        'virtual_warehouse_id' => $vw->id,
        'qty_on_hand'          => '75.0000',
    ]);

    Cache::flush();
    $result = app(DashboardMetrics::class)->chartStockByCategory($user);

    expect($result)->toHaveKeys(['categories', 'series'])
        ->and($result['categories'])->toContain('Electronics');
});

// ─── chartOrderPipeline ───────────────────────────────────────────────────────

it('chartOrderPipeline has 4 status labels', function () {
    $user   = makeChartUser('warehouse-manager');
    Cache::flush();
    $result = app(DashboardMetrics::class)->chartOrderStatusPipeline($user);

    expect($result['categories'])->toHaveCount(4)
        ->and($result['series'][0]['data'])->toHaveCount(4);
});

// ─── chartExpiryProfile ───────────────────────────────────────────────────────

it('chartExpiryProfile returns 4 buckets', function () {
    $user   = makeChartUser('warehouse-manager');
    Cache::flush();
    $result = app(DashboardMetrics::class)->chartExpiryProfile($user);

    expect($result['categories'])->toHaveCount(4)
        ->and($result['series'][0]['data'])->toHaveCount(4);
});

// ─── chartTopMovingItems ──────────────────────────────────────────────────────

it('chartTopMovingItems returns empty series when no dispatches', function () {
    $user   = makeChartUser('warehouse-manager');
    Cache::flush();
    $result = app(DashboardMetrics::class)->chartTopMovingItems($user, 30);

    expect($result)->toHaveKeys(['categories', 'series'])
        ->and($result['categories'])->toBeArray();
});

// ─── chartContainerDwell ──────────────────────────────────────────────────────

it('chartContainerDwell returns empty when no active containers', function () {
    $user   = makeChartUser('warehouse-manager');
    Cache::flush();
    $result = app(DashboardMetrics::class)->chartContainerDwell($user);

    expect($result['categories'])->toBeArray()
        ->and($result['series'])->toBeArray();
});

// ─── canSeeChart role visibility ──────────────────────────────────────────────

it('scanner-operator cannot see any charts', function () {
    $user = makeChartUser('scanner-operator');
    $this->actingAs($user);

    $component = new \App\Livewire\Dashboard\Overview();

    expect($component->canSeeChart('inbound_vs_outbound'))->toBeFalse()
        ->and($component->canSeeChart('stock_by_vw'))->toBeFalse();
});

it('warehouse-manager sees all charts', function () {
    $user = makeChartUser('warehouse-manager');
    $this->actingAs($user);

    $component = new \App\Livewire\Dashboard\Overview();

    expect($component->canSeeChart('inbound_vs_outbound'))->toBeTrue()
        ->and($component->canSeeChart('container_dwell'))->toBeTrue()
        ->and($component->canSeeChart('stock_by_category'))->toBeTrue();
});

it('department-user sees stock charts but not operational charts', function () {
    $user = makeChartUser('department-user');
    $this->actingAs($user);

    $component = new \App\Livewire\Dashboard\Overview();

    expect($component->canSeeChart('stock_by_vw'))->toBeTrue()
        ->and($component->canSeeChart('stock_by_category'))->toBeTrue()
        ->and($component->canSeeChart('inbound_vs_outbound'))->toBeFalse()
        ->and($component->canSeeChart('order_pipeline'))->toBeFalse()
        ->and($component->canSeeChart('container_dwell'))->toBeFalse();
});

it('storekeeper sees operational charts but not stock analysis charts', function () {
    $user = makeChartUser('storekeeper');
    $this->actingAs($user);

    $component = new \App\Livewire\Dashboard\Overview();

    expect($component->canSeeChart('order_pipeline'))->toBeTrue()
        ->and($component->canSeeChart('bin_utilisation'))->toBeTrue()
        ->and($component->canSeeChart('stock_by_category'))->toBeFalse()
        ->and($component->canSeeChart('inbound_vs_outbound'))->toBeFalse();
});
