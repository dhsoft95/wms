<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', fn () => redirect()->route('dashboard'))->name('home');

// ─── Invitation (signed, guest-only) ─────────────────────────────────────────
Route::middleware(['guest', 'signed'])->group(function () {
    Route::get('/invitation/accept/{user}', \App\Livewire\Auth\AcceptInvitation::class)
        ->name('invitation.accept');
});

// ─── 2FA challenge (requires pending 2FA session, guest) ─────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/two-factor-challenge', \App\Livewire\Auth\TwoFactorChallenge::class)
        ->name('two-factor.challenge');
});

// ─── Main authenticated app ───────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard
    Route::get('/dashboard', \App\Livewire\Dashboard\Overview::class)->name('dashboard');

    // Approvals
    Route::get('/approvals', \App\Livewire\Approvals\Index::class)
        ->middleware('permission:approvals.view')
        ->name('approvals.index');

    // Inbound
    Route::prefix('inbound')->name('inbound.')->middleware('permission:containers.view')->group(function () {
        Route::get('/containers',        \App\Livewire\Inbound\Containers\Index::class)->name('containers.index');
        Route::get('/containers/create', \App\Livewire\Inbound\Containers\Create::class)
            ->middleware('permission:containers.create')->name('containers.create');
        Route::get('/grn',               \App\Livewire\Inbound\Grn\Index::class)
            ->middleware('permission:receiving.view')->name('grn.index');
        Route::get('/grn/create',        \App\Livewire\Inbound\Grn\Create::class)
            ->middleware('permission:receiving.create')->name('grn.create');
        Route::get('/inspections',       \App\Livewire\Inbound\Inspections\Index::class)
            ->middleware('permission:inspections.view')->name('inspections.index');
    });

    // Warehouse
    Route::prefix('warehouse')->name('warehouse.')->group(function () {
        Route::get('/putaway',   \App\Livewire\Warehouse\Putaway\Index::class)
            ->middleware('permission:putaway.view')->name('putaway.index');
        Route::get('/movements', \App\Livewire\Warehouse\Movements\Index::class)
            ->middleware('permission:movements.view')->name('movements.index');
        Route::get('/transfers', \App\Livewire\Warehouse\Transfers\Index::class)
            ->middleware('permission:transfers.view')->name('transfers.index');
    });

    // Inventory
    Route::prefix('inventory')->name('inventory.')->middleware('permission:inventory.view')->group(function () {
        Route::get('/stock',       \App\Livewire\Inventory\Stock\Index::class)->name('stock.index');
        Route::get('/adjustments', \App\Livewire\Inventory\Adjustments\Index::class)
            ->middleware('permission:adjustments.view')->name('adjustments.index');
        Route::get('/counts',      \App\Livewire\Inventory\Counts\Index::class)
            ->middleware('permission:cycle-counts.view')->name('counts.index');
    });

    // Outbound
    Route::prefix('outbound')->name('outbound.')->middleware('permission:orders.view')->group(function () {
        Route::get('/orders/create', \App\Livewire\Outbound\Orders\Create::class)
            ->middleware('permission:orders.create')->name('orders.create');
        Route::get('/orders',        \App\Livewire\Outbound\Orders\Create::class)->name('orders.index');
        Route::get('/dispatch',      \App\Livewire\Outbound\Dispatch\Index::class)
            ->middleware('permission:dispatch.view')->name('dispatch.index');
        Route::get('/pod',           \App\Livewire\Outbound\Pod\Index::class)
            ->middleware('permission:pod.view')->name('pod.index');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->middleware('permission:reports.view')->group(function () {
        Route::get('/',      \App\Livewire\Reports\Index::class)->name('index');
        Route::get('/print', \App\Livewire\Reports\PrintDocument::class)->name('print');
    });

    // Admin
    Route::prefix('admin')->name('admin.')->middleware('permission:users.manage')->group(function () {
        Route::get('/users',         \App\Livewire\Admin\Users\Index::class)->name('users.index');
        Route::get('/users/create',  \App\Livewire\Admin\Users\Create::class)->name('users.create');
        Route::get('/users/{user}',  \App\Livewire\Admin\Users\Show::class)->name('users.show');
        Route::get('/users/{user}/edit', \App\Livewire\Admin\Users\Edit::class)->name('users.edit');
        Route::get('/skus',          \App\Livewire\Admin\Skus\Index::class)->name('skus.index');
    });

    Route::get('/admin/roles',       \App\Livewire\Admin\Roles\Index::class)
        ->middleware(['auth', 'verified', 'permission:roles.manage'])
        ->name('admin.roles.index');

    Route::get('/admin/departments', \App\Livewire\Admin\Departments\Index::class)
        ->middleware(['auth', 'verified', 'permission:departments.manage'])
        ->name('admin.departments.index');

    // Profile (self-service)
    Route::get('/profile', \App\Livewire\Profile\Index::class)->name('profile.index');

    // Settings (Flux/Volt — keep intact)
    Route::redirect('settings', 'settings/profile');
    Volt::route('settings/profile',    'settings.profile')->name('settings.profile');
    Volt::route('settings/password',   'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

// ─── Scanner operator portal ──────────────────────────────────────────────────
Route::middleware(['auth', 'permission:scan.receive|scan.putaway|scan.pick|scan.stock-lookup'])->prefix('scan')->name('scan.')->group(function () {
    Route::get('/',            \App\Livewire\Scan\Home::class)->name('home');
    Route::get('/receive',     \App\Livewire\Scan\Receive::class)->name('receive');
    Route::get('/putaway',     \App\Livewire\Scan\Putaway::class)->name('putaway');
    Route::get('/pick',        \App\Livewire\Scan\Pick::class)->name('pick');
    Route::get('/stock-lookup',\App\Livewire\Scan\StockLookup::class)->name('stock-lookup');
});

require __DIR__.'/auth.php';
