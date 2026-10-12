<?php

use App\Support\Navigation\NavigationBadges;
use App\Support\Navigation\NavigationBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Ensure all routes referenced in config exist for testing
    Route::get('/dashboard', fn () => '')->name('dashboard');
    Route::get('/approvals', fn () => '')->name('approvals.index');
    Route::get('/inbound/containers', fn () => '')->name('inbound.containers.index');
    Route::get('/inbound/grn', fn () => '')->name('inbound.grn.index');
    Route::get('/inbound/inspections', fn () => '')->name('inbound.inspections.index');
    Route::get('/warehouse/putaway', fn () => '')->name('warehouse.putaway.index');
    Route::get('/warehouse/movements', fn () => '')->name('warehouse.movements.index');
    Route::get('/warehouse/transfers', fn () => '')->name('warehouse.transfers.index');
    Route::get('/inventory/stock', fn () => '')->name('inventory.stock.index');
    Route::get('/inventory/adjustments', fn () => '')->name('inventory.adjustments.index');
    Route::get('/inventory/counts', fn () => '')->name('inventory.counts.index');
    Route::get('/outbound/orders', fn () => '')->name('outbound.orders.index');
    Route::get('/outbound/dispatch', fn () => '')->name('outbound.dispatch.index');
    Route::get('/outbound/pod', fn () => '')->name('outbound.pod.index');
    Route::get('/reports', fn () => '')->name('reports.index');
    Route::get('/reports/print', fn () => '')->name('reports.print');
    Route::get('/admin/skus', fn () => '')->name('admin.skus.index');
    Route::get('/admin/users', fn () => '')->name('admin.users.index');
    Route::get('/admin/roles', fn () => '')->name('admin.roles.index');
    Route::get('/admin/departments', fn () => '')->name('admin.departments.index');
});

function makeBuilder(): NavigationBuilder
{
    return new NavigationBuilder(new NavigationBadges());
}

it('returns all groups when unauthenticated (no can checks)', function () {
    Auth::shouldReceive('user')->andReturn(null)->byDefault();

    $groups = makeBuilder()->build();

    // Every group whose 'can' is null should appear (overview, and any group
    // that a null user passes — since we skip groups that require can)
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

    // Groups whose items all require permissions should be excluded
    $keys = array_column($groups, 'key');
    expect($keys)->not->toContain('inbound')
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
