<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }
    if (auth()->user()->hasRole('scanner-operator')) {
        return redirect()->route('scan.home');
    }
    return redirect()->route('dashboard');
})->name('home');

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
    Route::prefix('inventory')->name('inventory.')->middleware('permission:stock.view')->group(function () {
        Route::get('/stock',          \App\Livewire\Inventory\Stock\Index::class)->name('stock.index');
        Route::get('/movements',      \App\Livewire\Inventory\Stock\Movements::class)
            ->middleware('permission:stock.movements.view')->name('movements.index');
        Route::get('/opening-balance', \App\Livewire\Inventory\Stock\OpeningBalance::class)
            ->middleware('permission:stock.opening-balance')->name('opening-balance');
        Route::get('/adjustments', \App\Livewire\Inventory\Adjustments\Index::class)
            ->middleware('permission:adjustments.view')->name('adjustments.index');
        Route::get('/counts',      \App\Livewire\Inventory\Counts\Index::class)
            ->middleware('permission:cycle-counts.view')->name('counts.index');
        Route::get('/serials',     \App\Livewire\Inventory\Serials\Lookup::class)
            ->middleware('permission:serials.view')->name('serials.lookup');
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

    // Admin — user/role management
    Route::prefix('admin')->name('admin.')->middleware('permission:users.manage')->group(function () {
        Route::get('/users',             \App\Livewire\Admin\Users\Index::class)->name('users.index');
        Route::get('/users/create',      \App\Livewire\Admin\Users\Create::class)->name('users.create');
        Route::get('/users/{user}',      \App\Livewire\Admin\Users\Show::class)->name('users.show');
        Route::get('/users/{user}/edit', \App\Livewire\Admin\Users\Edit::class)->name('users.edit');
    });

    Route::get('/admin/roles',       \App\Livewire\Admin\Roles\Index::class)
        ->middleware(['auth', 'verified', 'permission:roles.manage'])
        ->name('admin.roles.index');

    Route::get('/admin/departments', \App\Livewire\Admin\Departments\Index::class)
        ->middleware(['auth', 'verified', 'permission:departments.manage'])
        ->name('admin.departments.index');

    // Settings — System Admin only
    Route::get('/admin/settings', \App\Livewire\Admin\Settings\Index::class)
        ->middleware('permission:settings.manage')
        ->name('admin.settings.index');

    // Master Data — Partners
    Route::prefix('admin/partners')->name('admin.partners.')->middleware('permission:partners.view')->group(function () {
        Route::get('/',              \App\Livewire\Admin\Partners\Index::class)->name('index');
        Route::get('/create',        \App\Livewire\Admin\Partners\Create::class)
            ->middleware('permission:partners.create')->name('create');
        Route::get('/{partner}',     \App\Livewire\Admin\Partners\Show::class)->name('show');
        Route::get('/{partner}/edit',\App\Livewire\Admin\Partners\Edit::class)
            ->middleware('permission:partners.update')->name('edit');
    });

    // Master Data — Items
    Route::prefix('admin/items')->name('admin.items.')->middleware('permission:items.view')->group(function () {
        Route::get('/',          \App\Livewire\Admin\Items\Index::class)->name('index');
        Route::get('/create',    \App\Livewire\Admin\Items\Create::class)
            ->middleware('permission:items.create')->name('create');
        Route::get('/{item}',    \App\Livewire\Admin\Items\Show::class)->name('show');
        Route::get('/{item}/edit',\App\Livewire\Admin\Items\Edit::class)
            ->middleware('permission:items.update')->name('edit');
    });

    // Master Data — Item Categories
    Route::get('/admin/item-categories', \App\Livewire\Admin\ItemCategories\Index::class)
        ->middleware('permission:items.create')
        ->name('admin.item-categories.index');

    // Master Data — Warehouses
    Route::prefix('admin/warehouses')->name('admin.warehouses.')->middleware('permission:locations.view')->group(function () {
        Route::get('/',               \App\Livewire\Admin\Warehouses\Index::class)->name('index');
        Route::get('/create',         \App\Livewire\Admin\Warehouses\Create::class)
            ->middleware('permission:locations.create')->name('create');
        Route::get('/{warehouse}/edit',\App\Livewire\Admin\Warehouses\Edit::class)
            ->middleware('permission:locations.update')->name('edit');
    });

    // Master Data — Locations
    Route::prefix('admin/locations')->name('admin.locations.')->middleware('permission:locations.view')->group(function () {
        Route::get('/',              \App\Livewire\Admin\Locations\Index::class)->name('index');
        Route::get('/create',        \App\Livewire\Admin\Locations\Create::class)
            ->middleware('permission:locations.create')->name('create');
        Route::get('/bulk-generate', \App\Livewire\Admin\Locations\BulkGenerate::class)
            ->middleware('permission:locations.create')->name('bulk-generate');
        Route::get('/{location}/edit',\App\Livewire\Admin\Locations\Edit::class)
            ->middleware('permission:locations.update')->name('edit');
    });

    // Data Import
    Route::prefix('admin/imports')->name('admin.imports.')->middleware('permission:imports.manage')->group(function () {
        Route::get('/',                          \App\Livewire\Admin\DataImport\Index::class)->name('index');
        Route::get('/template/{type}', function (string $type) {
            $importType = \App\Enums\ImportType::from($type);
            $dummy      = new \App\Models\DataImport(['type' => $importType, 'original_filename' => '', 'stored_path' => '', 'status' => 'uploaded', 'mode' => 'create_only']);
            $handler    = match ($importType) {
                \App\Enums\ImportType::DepartmentsVws  => new \App\Imports\DepartmentVwImport($dummy),
                \App\Enums\ImportType::Partners        => new \App\Imports\PartnerImport($dummy),
                \App\Enums\ImportType::ItemCategories  => new \App\Imports\ItemCategoryImport($dummy),
                \App\Enums\ImportType::Items           => new \App\Imports\ItemImport($dummy),
                \App\Enums\ImportType::Locations       => new \App\Imports\LocationImport($dummy),
                \App\Enums\ImportType::OpeningStock    => new \App\Imports\OpeningStockImport($dummy),
            };
            return response()->streamDownload(function () use ($handler) {
                $writer = new \OpenSpout\Writer\XLSX\Writer();
                $writer->openToFile('php://output');
                $writer->getCurrentSheet()->setName('Template');
                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues($handler->headers()));
                foreach ($handler->templateRows() as $row) {
                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(array_values($row)));
                }
                $writer->addNewSheetAndMakeItCurrent()->setName('Instructions');
                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Column', 'Description']));
                foreach ($handler->columnInstructions() as $col => $desc) {
                    $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([$col, $desc]));
                }
                $writer->close();
            }, 'template_' . $type . '.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        })->name('template');
        Route::get('/{import}',                  \App\Livewire\Admin\DataImport\Show::class)->name('show');
    });

    // Legacy SKU redirect — keep for backward compatibility
    Route::redirect('/admin/skus', '/admin/items')->name('admin.skus.index');

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
