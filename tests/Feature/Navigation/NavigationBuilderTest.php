<?php

use App\Support\Navigation\NavigationBadges;
use App\Support\Navigation\NavigationBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Stub every route referenced in config/navigation.php
    Route::get('/dashboard',                fn () => '')->name('dashboard');
    Route::get('/approvals',                fn () => '')->name('approvals.index');
    Route::get('/orders',                   fn () => '')->name('orders.index');
    // Warehouse Tasks group
    Route::get('/inbound/grn',              fn () => '')->name('inbound.grn.index');
    Route::get('/inbound/putaway',          fn () => '')->name('inbound.putaway.index');
    Route::get('/outbound/pick-lists',      fn () => '')->name('outbound.pick-lists.index');
    Route::get('/outbound/dispatch',        fn () => '')->name('outbound.dispatch.index');
    Route::get('/inbound/yard',             fn () => '')->name('inbound.yard.index');
    // Hidden items (enabled => false) — routes still exist, just hidden from nav
    Route::get('/inbound/containers',       fn () => '')->name('inbound.containers.index');
    Route::get('/inbound/dwell',            fn () => '')->name('inbound.dwell.index');
    Route::get('/inbound/inspections',      fn () => '')->name('inbound.inspections.index');
    // Operations group
    Route::get('/warehouse/putaway',        fn () => '')->name('warehouse.putaway.index');
    Route::get('/warehouse/movements',      fn () => '')->name('warehouse.movements.index');
    Route::get('/warehouse/transfers',      fn () => '')->name('warehouse.transfers.index');
    Route::get('/warehouse/wtr',            fn () => '')->name('warehouse.wtr.index');
    // Inventory
    Route::get('/inventory/stock',          fn () => '')->name('inventory.stock.index');
    Route::get('/inventory/movements',      fn () => '')->name('inventory.movements.index');
    Route::get('/inventory/opening',        fn () => '')->name('inventory.opening-balance');
    Route::get('/inventory/adjustments',    fn () => '')->name('inventory.adjustments.index');
    Route::get('/inventory/counts',         fn () => '')->name('inventory.counts.index');
    Route::get('/inventory/quarantine',     fn () => '')->name('inventory.quarantine.index');
    Route::get('/inventory/serials',        fn () => '')->name('inventory.serials.lookup');
    // Outbound (POD only now)
    Route::get('/outbound/pod',             fn () => '')->name('outbound.pod.index');
    // Reports
    Route::get('/reports',                  fn () => '')->name('reports.index');
    Route::get('/reports/print',            fn () => '')->name('reports.print');
    Route::get('/reports/labels',           fn () => '')->name('reports.labels.log');
    // Master data
    Route::get('/admin/items',              fn () => '')->name('admin.items.index');
    Route::get('/admin/item-categories',    fn () => '')->name('admin.item-categories.index');
    Route::get('/admin/warehouses',         fn () => '')->name('admin.warehouses.index');
    Route::get('/admin/locations',          fn () => '')->name('admin.locations.index');
    Route::get('/admin/partners',           fn () => '')->name('admin.partners.index');
    // Admin
    Route::get('/admin/imports',            fn () => '')->name('admin.imports.index');
    Route::get('/admin/users',              fn () => '')->name('admin.users.index');
    Route::get('/admin/roles',              fn () => '')->name('admin.roles.index');
    Route::get('/admin/departments',        fn () => '')->name('admin.departments.index');
    Route::get('/admin/settings',           fn () => '')->name('admin.settings.index');
    Route::get('/admin/label-templates',    fn () => '')->name('admin.labels.templates.index');
    Route::get('/admin/printers',           fn () => '')->name('admin.printers.index');
    Route::get('/admin/scanner-devices',    fn () => '')->name('admin.scanner-devices.index');
});

function makeBuilder(): NavigationBuilder
{
    return new NavigationBuilder(new NavigationBadges());
}

// ─── Core builder behaviour ───────────────────────────────────────────────────

it('returns all groups when unauthenticated (no can checks)', function () {
    Auth::shouldReceive('user')->andReturn(null)->byDefault();

    $groups = makeBuilder()->build();

    $keys = array_column($groups, 'key');
    expect($keys)->toContain('overview');
});

it('includes items with no can restriction', function () {
    Auth::shouldReceive('user')->andReturn(null)->byDefault();

    $groups = makeBuilder()->build();

    $overview = collect($groups)->firstWhere('key', 'overview');
    expect($overview)->not->toBeNull();

    $itemKeys = array_column($overview['items'], 'key');
    expect($itemKeys)->toContain('dashboard');
});

it('excludes items the user cannot access', function () {
    $user = Mockery::mock(\App\Models\User::class);
    $user->shouldReceive('can')->with('approvals.view')->andReturn(false);
    $user->shouldReceive('can')->andReturn(false)->byDefault();
    Auth::shouldReceive('user')->andReturn($user)->byDefault();

    $groups = makeBuilder()->build();

    $overview = collect($groups)->firstWhere('key', 'overview');
    $itemKeys = array_column($overview['items'] ?? [], 'key');
    expect($itemKeys)->not->toContain('approvals');
});

it('excludes entire groups when no items are visible', function () {
    $user = Mockery::mock(\App\Models\User::class);
    $user->shouldReceive('can')->andReturn(false)->byDefault();
    Auth::shouldReceive('user')->andReturn($user)->byDefault();

    $groups = makeBuilder()->build();

    $keys = array_column($groups, 'key');
    expect($keys)
        ->not->toContain('warehouse-tasks')
        ->not->toContain('warehouse')
        ->not->toContain('admin');
});

it('marks the active item based on current route', function () {
    Auth::shouldReceive('user')->andReturn(null)->byDefault();

    $request = \Illuminate\Http\Request::create('/dashboard');
    app()->instance('request', $request);
    $this->app['router']->getRoutes()->match($request);
    $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));

    $groups = makeBuilder()->build();

    $overview = collect($groups)->firstWhere('key', 'overview');
    $dashboard = collect($overview['items'])->firstWhere('key', 'dashboard');

    expect($dashboard['is_active'])->toBeTrue();
});

it('sets has_active on the group when an item is active', function () {
    Auth::shouldReceive('user')->andReturn(null)->byDefault();

    $request = \Illuminate\Http\Request::create('/dashboard');
    app()->instance('request', $request);
    $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));

    $groups = makeBuilder()->build();

    $overview = collect($groups)->firstWhere('key', 'overview');
    expect($overview['has_active'])->toBeTrue();
});

it('each item has required keys', function () {
    Auth::shouldReceive('user')->andReturn(null)->byDefault();

    $groups = makeBuilder()->build();

    foreach ($groups as $group) {
        expect($group)->toHaveKeys(['key', 'label', 'items', 'has_active']);
        foreach ($group['items'] as $item) {
            expect($item)->toHaveKeys(['key', 'label', 'icon', 'url', 'route', 'is_active', 'badge']);
        }
    }
});

// ─── enabled flag ─────────────────────────────────────────────────────────────

it('hides items with enabled => false from the sidebar', function () {
    $user = Mockery::mock(\App\Models\User::class);
    $user->shouldReceive('can')->andReturn(true)->byDefault();
    Auth::shouldReceive('user')->andReturn($user)->byDefault();

    $groups  = makeBuilder()->build();
    $wt      = collect($groups)->firstWhere('key', 'warehouse-tasks');

    expect($wt)->not->toBeNull();

    $itemKeys = array_column($wt['items'], 'key');
    expect($itemKeys)
        ->not->toContain('containers')
        ->not->toContain('dwell')
        ->not->toContain('inspections');
});

it('shows enabled items in warehouse-tasks group', function () {
    $user = Mockery::mock(\App\Models\User::class);
    $user->shouldReceive('can')->andReturn(true)->byDefault();
    Auth::shouldReceive('user')->andReturn($user)->byDefault();

    $groups   = makeBuilder()->build();
    $wt       = collect($groups)->firstWhere('key', 'warehouse-tasks');
    $itemKeys = array_column($wt['items'], 'key');

    expect($itemKeys)
        ->toContain('grn')
        ->toContain('putaway')
        ->toContain('pick-lists')
        ->toContain('dispatch')
        ->toContain('yard');
});

// ─── Warehouse Tasks visibility ───────────────────────────────────────────────

it('warehouse-tasks group is visible to a user with grn.view', function () {
    $user = Mockery::mock(\App\Models\User::class);
    $user->shouldReceive('can')->with('grn.view')->andReturn(true);
    $user->shouldReceive('can')->andReturn(false)->byDefault();
    Auth::shouldReceive('user')->andReturn($user)->byDefault();

    $groups = makeBuilder()->build();
    $keys   = array_column($groups, 'key');

    expect($keys)->toContain('warehouse-tasks');
});

it('department-user without grn.view does not see warehouse-tasks group', function () {
    $user = Mockery::mock(\App\Models\User::class);
    // dept-user has stock.view and orders.view but NOT grn.view
    $user->shouldReceive('can')->with('grn.view')->andReturn(false);
    $user->shouldReceive('can')->andReturn(false)->byDefault();
    Auth::shouldReceive('user')->andReturn($user)->byDefault();

    $groups = makeBuilder()->build();
    $keys   = array_column($groups, 'key');

    expect($keys)->not->toContain('warehouse-tasks');
});

it('inbound group no longer exists in config', function () {
    Auth::shouldReceive('user')->andReturn(null)->byDefault();

    $groups = makeBuilder()->build();
    $keys   = array_column($groups, 'key');

    expect($keys)->not->toContain('inbound');
});

it('pick-lists and dispatch are no longer in the outbound group', function () {
    $user = Mockery::mock(\App\Models\User::class);
    $user->shouldReceive('can')->with('orders.view')->andReturn(true);
    $user->shouldReceive('can')->with('pod.upload')->andReturn(true);
    $user->shouldReceive('can')->andReturn(false)->byDefault();
    Auth::shouldReceive('user')->andReturn($user)->byDefault();

    $groups   = makeBuilder()->build();
    $outbound = collect($groups)->firstWhere('key', 'outbound');
    $itemKeys = array_column($outbound['items'] ?? [], 'key');

    expect($itemKeys)
        ->not->toContain('pick-lists')
        ->not->toContain('dispatch')
        ->toContain('pod');
});
