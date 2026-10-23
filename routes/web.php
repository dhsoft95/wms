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

    // Unified Orders
    Route::get('/orders', \App\Livewire\Orders\Index::class)
        ->name('orders.index');

    // Approvals
    Route::get('/approvals', \App\Livewire\Approvals\Index::class)
        ->middleware('permission:approvals.view')
        ->name('approvals.index');

    // Inbound
    Route::prefix('inbound')->name('inbound.')->middleware('permission:containers.view')->group(function () {
        Route::get('/containers',        \App\Livewire\Inbound\Containers\Index::class)->name('containers.index');
        Route::get('/containers/create', \App\Livewire\Inbound\Containers\Create::class)
            ->middleware('permission:containers.create')->name('containers.create');
        Route::get('/containers/{container}',      \App\Livewire\Inbound\Containers\Show::class)->name('containers.show');
        Route::get('/containers/{container}/edit', \App\Livewire\Inbound\Containers\Edit::class)
            ->middleware('permission:containers.update')->name('containers.edit');
        Route::get('/yard',  \App\Livewire\Inbound\Yard\Index::class)->name('yard.index');
        Route::get('/dwell', \App\Livewire\Inbound\Dwell\Index::class)->name('dwell.index');
        Route::get('/grn',               \App\Livewire\Inbound\Grn\Index::class)
            ->middleware('permission:grn.view')->name('grn.index');
        Route::get('/grn/create',        \App\Livewire\Inbound\Grn\Create::class)
            ->middleware('permission:grn.create')->name('grn.create');
        Route::get('/grn/{grn}',         \App\Livewire\Inbound\Grn\Show::class)
            ->middleware('permission:grn.view')->name('grn.show');
        Route::get('/grn/{grn}/receive', \App\Livewire\Inbound\Grn\Receive::class)
            ->middleware('permission:grn.receive')->name('grn.receive');
        Route::get('/grn/{grn}/tally',   \App\Livewire\Inbound\Grn\Tally::class)
            ->middleware('permission:po.receive')->name('grn.tally');
        Route::get('/grn/{grn}/document', [\App\Http\Controllers\GrnDocumentController::class, 'download'])
            ->middleware('permission:grn.view')->name('grn.document');
        Route::get('/putaway',           \App\Livewire\Warehouse\Putaway\Index::class)
            ->middleware('permission:putaway.view')->name('putaway.index'); // inbound.putaway.index
        Route::get('/inspections',       \App\Livewire\Inbound\Inspections\Index::class)
            ->middleware('permission:inspections.view')->name('inspections.index');
    });

    // Inbound — Purchase Orders
    Route::prefix('inbound/orders')->name('inbound.orders.')->middleware(['auth', 'permission:po.view'])->group(function () {
        Route::get('/',        \App\Livewire\Inbound\Orders\Index::class)->name('index');
        Route::get('/create',  \App\Livewire\Inbound\Orders\Create::class)
            ->middleware('permission:po.create')->name('create');
        Route::get('/{purchaseOrder}',      \App\Livewire\Inbound\Orders\Show::class)->name('show');
        Route::get('/{purchaseOrder}/edit', \App\Livewire\Inbound\Orders\Edit::class)
            ->middleware('permission:po.create')->name('edit');
        Route::get('/{purchaseOrder}/document', [\App\Http\Controllers\PurchaseOrderDocumentController::class, 'download'])
            ->name('document.download');
        Route::get('/{purchaseOrder}/pdf', [\App\Http\Controllers\PurchaseOrderPdfController::class, 'download'])
            ->name('pdf');
    });

    // Container document download (private, streams file)
    Route::get('/containers/{container}/documents/{document}/download',
        \App\Http\Controllers\ContainerDocumentController::class)
        ->middleware('auth')
        ->name('containers.documents.download');

    // Container PO file download
    Route::get('/containers/{container}/po/download',
        \App\Http\Controllers\ContainerPoController::class)
        ->middleware('auth')
        ->name('inbound.containers.po.download');

    // Packing list import sample template
    Route::get('/containers/packing-list/sample', function () {
        $headers = ['Item Code', 'Description', 'Quantity', 'Unit', 'Batch No', 'Expiry Date', 'Department'];
        $rows    = [
            ['SKU001',   'Widget A',                 '2',  'CTN', 'BATCH-001', '2027-01-01', 'DEPT-A'],
            ['SKU002',   'Tracked Product',           '10', 'PCS', 'LOT-X99',  '2026-06-30', 'DEPT-B'],
            ['NEWITEM1', 'New Product (map/create)',  '5',  'PCS', '',         '',           'DEPT-A'],
        ];
        $instructions = [
            ['Item Code',   'Required. SKU or barcode. Unknown SKUs can be mapped or created during import.'],
            ['Description', 'Optional product description.'],
            ['Quantity',    'Required numeric quantity in the specified unit.'],
            ['Unit',        'UoM code (e.g. PCS, CTN, KG). Converted to base UoM if a conversion exists for the item.'],
            ['Batch No',    'Batch/lot number. Required for items that track batches.'],
            ['Expiry Date', 'Date format YYYY-MM-DD. Required for items that track expiry.'],
            ['Department',  'Virtual Warehouse code or name. Falls back to the item\'s default warehouse if omitted.'],
        ];

        return response()->streamDownload(function () use ($headers, $rows, $instructions) {
            $writer = new \OpenSpout\Writer\XLSX\Writer();
            $writer->openToFile('php://output');
            $writer->getCurrentSheet()->setName('Packing List');
            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues($headers));
            foreach ($rows as $row) {
                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues($row));
            }
            $writer->addNewSheetAndMakeItCurrent()->setName('Column Guide');
            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Column', 'Description']));
            foreach ($instructions as $row) {
                $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues($row));
            }
            $writer->close();
        }, 'packing_list_sample.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    })->middleware('auth')->name('containers.packing-list.sample');

    // Bulk line template downloads
    Route::get('/bulk-lines/template/{type}', function (string $type) {
        $map = [
            'po'         => \App\Livewire\Inbound\Orders\Create::class,
            'adjustment' => \App\Livewire\Inventory\Adjustments\Create::class,
            'movement'   => \App\Livewire\Warehouse\Movements\Create::class,
            'transfer'   => \App\Livewire\Warehouse\Transfers\Create::class,
            'wtr'        => \App\Livewire\Warehouse\Wtr\Create::class,
        ];
        abort_unless(array_key_exists($type, $map), 404);
        $class  = $map[$type];
        $fields = $class::lineFields();
        $names  = [
            'po'         => 'Purchase Order Lines',
            'adjustment' => 'Adjustment Lines',
            'movement'   => 'Movement Lines',
            'transfer'   => 'Transfer Lines',
            'wtr'        => 'WH Transfer Lines',
        ];
        return app(\App\Services\BulkLineImportService::class)->buildTemplate($fields, $names[$type]);
    })->middleware('auth')->name('bulk-lines.template');

    // Warehouse
    Route::prefix('warehouse')->name('warehouse.')->group(function () {
        // putaway.index kept as alias for backward-compat
        Route::get('/putaway',   \App\Livewire\Warehouse\Putaway\Index::class)
            ->middleware('permission:putaway.view')->name('putaway.index');
        Route::get('/movements', \App\Livewire\Warehouse\Movements\Index::class)
            ->middleware('permission:movements.view')->name('movements.index');
        Route::get('/movements/create', \App\Livewire\Warehouse\Movements\Create::class)
            ->middleware('permission:movements.create')->name('movements.create');
        Route::get('/movements/{movement}', \App\Livewire\Warehouse\Movements\Show::class)
            ->middleware('permission:movements.view')->name('movements.show');
        Route::get('/transfers', \App\Livewire\Warehouse\Transfers\Index::class)
            ->middleware('permission:transfers.view')->name('transfers.index');
        Route::get('/transfers/create', \App\Livewire\Warehouse\Transfers\Create::class)
            ->middleware('permission:transfers.create')->name('transfers.create');
        Route::get('/transfers/{transfer}', \App\Livewire\Warehouse\Transfers\Show::class)
            ->middleware('permission:transfers.view')->name('transfers.show');
        Route::get('/wtr', \App\Livewire\Warehouse\Wtr\Index::class)
            ->middleware('permission:transfers.view')->name('wtr.index');
        Route::get('/wtr/create', \App\Livewire\Warehouse\Wtr\Create::class)
            ->middleware('permission:transfers.create')->name('wtr.create');
        Route::get('/wtr/{wtr}', \App\Livewire\Warehouse\Wtr\Show::class)
            ->middleware('permission:transfers.view')->name('wtr.show');
    });

    // LPN Print
    Route::get('/lpn/{grn}/print', \App\Http\Controllers\LpnPrintController::class)
        ->middleware('permission:lpn.print')
        ->name('lpn.print');

    // Inventory
    Route::prefix('inventory')->name('inventory.')->middleware('permission:stock.view')->group(function () {
        Route::get('/stock',          \App\Livewire\Inventory\Stock\Index::class)->name('stock.index');
        Route::get('/stock/{stockBalance}', \App\Livewire\Inventory\Stock\Show::class)->name('stock.show');
        Route::get('/movements',      \App\Livewire\Inventory\Stock\Movements::class)
            ->middleware('permission:stock.movements.view')->name('movements.index');
        Route::get('/opening-balance', \App\Livewire\Inventory\Stock\OpeningBalance::class)
            ->middleware('permission:stock.opening-balance')->name('opening-balance');
        Route::get('/adjustments', \App\Livewire\Inventory\Adjustments\Index::class)
            ->middleware('permission:adjustments.view')->name('adjustments.index');
        Route::get('/adjustments/create', \App\Livewire\Inventory\Adjustments\Create::class)
            ->middleware('permission:adjustments.create')->name('adjustments.create');
        Route::get('/adjustments/{adjustment}', \App\Livewire\Inventory\Adjustments\Show::class)
            ->middleware('permission:adjustments.view')->name('adjustments.show');
        Route::get('/counts', \App\Livewire\Inventory\Counts\Index::class)
            ->middleware('permission:counts.view')->name('counts.index');
        Route::get('/counts/create', \App\Livewire\Inventory\Counts\Create::class)
            ->middleware('permission:counts.create')->name('counts.create');
        Route::get('/counts/{count}', \App\Livewire\Inventory\Counts\Show::class)
            ->middleware('permission:counts.view')->name('counts.show');
        Route::get('/quarantine', \App\Livewire\Inventory\Quarantine\Index::class)
            ->middleware('permission:quarantine.manage')->name('quarantine.index');
        Route::get('/serials', \App\Livewire\Inventory\Serials\Lookup::class)
            ->middleware('permission:serials.view')->name('serials.lookup');
    });

    // Outbound
    Route::prefix('outbound')->name('outbound.')->group(function () {
        Route::get('/orders', \App\Livewire\Outbound\Orders\Index::class)
            ->middleware('permission:orders.view')->name('orders.index');
        Route::get('/orders/create', \App\Livewire\Outbound\Orders\Create::class)
            ->middleware('permission:orders.create')->name('orders.create');
        Route::get('/orders/{deliveryOrder}', \App\Livewire\Outbound\Orders\Show::class)
            ->middleware('permission:orders.view')->name('orders.show');
        Route::get('/orders/{deliveryOrder}/edit', \App\Livewire\Outbound\Orders\Edit::class)
            ->middleware('permission:orders.create')->name('orders.edit');
        Route::get('/orders/{deliveryOrder}/mrn/download',
            \App\Http\Controllers\DeliveryOrderMrnController::class)
            ->middleware('auth')->name('orders.mrn.download');
        Route::get('/dispatch/{dispatch}/dn/download',
            [\App\Http\Controllers\DispatchDocumentController::class, 'dn'])
            ->middleware('permission:dispatch.view')->name('dispatch.dn.download');
        Route::get('/dispatch/{dispatch}/rcn/download',
            [\App\Http\Controllers\DispatchDocumentController::class, 'rcn'])
            ->middleware('permission:dispatch.view')->name('dispatch.rcn.download');
        Route::get('/gate-passes/{gatePass}/print',
            [\App\Http\Controllers\GatePassDocumentController::class, 'print'])
            ->middleware('permission:dispatch.view|gate.verify')->name('gate-pass.print');
        Route::get('/pick-lists', \App\Livewire\Outbound\PickLists\Index::class)
            ->middleware('permission:picking.view')->name('pick-lists.index');
        Route::get('/dispatch', \App\Livewire\Outbound\Dispatch\Index::class)
            ->middleware('permission:dispatch.view')->name('dispatch.index');
        Route::get('/dispatch/{dispatch}', \App\Livewire\Outbound\Dispatch\Show::class)
            ->middleware('permission:dispatch.view')->name('dispatch.show');
        Route::get('/pod', \App\Livewire\Outbound\Pod\Index::class)
            ->middleware('permission:pod.upload')->name('pod.index');
        Route::get('/gate', \App\Livewire\Outbound\Gate\Check::class)
            ->middleware('permission:gate.verify')->name('gate.check');
    });

    // Module 12 — Disposal Requests
    Route::prefix('inventory/disposals')->name('inventory.disposals.')->middleware('permission:disposals.create')->group(function () {
        Route::get('/',                              \App\Livewire\Inventory\Disposals\Index::class)->name('index');
        Route::get('/create',                        \App\Livewire\Inventory\Disposals\Create::class)->name('create');
        Route::get('/{disposalRequest}',             \App\Livewire\Inventory\Disposals\Show::class)->name('show');
        Route::get('/{disposalRequest}/print',       [\App\Http\Controllers\DisposalNoteController::class, 'show'])->name('print');
    });

    // Module 12 — Stock Status Requests
    Route::prefix('inventory/stock-status')->name('inventory.stock-status.')->middleware('permission:obsolete.mark')->group(function () {
        Route::get('/create',                          \App\Livewire\Inventory\StockStatus\Create::class)->name('create');
        Route::get('/{stockStatusRequest}',            \App\Livewire\Inventory\StockStatus\Show::class)->name('show');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->middleware('permission:reports.view')->group(function () {
        Route::get('/',      \App\Livewire\Reports\Index::class)->name('index');
        Route::get('/print', \App\Livewire\Reports\PrintDocument::class)->name('print');

        // Download or view a queued report
        Route::get('/download/{reportRequest}', [\App\Http\Controllers\ReportPdfController::class, 'download'])->name('download');
        Route::get('/view/{reportRequest}',     [\App\Http\Controllers\ReportPdfController::class, 'view'])->name('view');

        // Token-based streaming export (Excel / PDF)
        Route::get('/export', \App\Http\Controllers\Reports\ExportController::class)
            ->middleware('permission:reports.export')
            ->name('export');
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

    Route::get('/admin/approval-chains', \App\Livewire\Admin\ApprovalChains\Index::class)
        ->middleware(['auth', 'verified', 'permission:settings.manage'])
        ->name('admin.approval-chains.index');

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
        Route::get('/{item}/barcodes/{itemBarcode}/png',   [\App\Http\Controllers\ItemBarcodeController::class, 'png'])
            ->name('barcodes.png');
        Route::get('/{item}/barcodes/{itemBarcode}/label', [\App\Http\Controllers\ItemBarcodeController::class, 'label'])
            ->name('barcodes.label');
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
                \App\Enums\ImportType::Locations               => new \App\Imports\LocationImport($dummy),
                \App\Enums\ImportType::OpeningStock            => new \App\Imports\OpeningStockImport($dummy),
                \App\Enums\ImportType::ContainerPackingList,
                \App\Enums\ImportType::ActiveContainers        => throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException(),
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

    // ─── Label Templates ──────────────────────────────────────────────────────────
    Route::get('/admin/labels/templates', \App\Livewire\Admin\Labels\Templates\Index::class)
        ->middleware('permission:labels.templates.manage')->name('admin.labels.templates.index');
    Route::get('/admin/labels/templates/create', \App\Livewire\Admin\Labels\Templates\Create::class)
        ->middleware('permission:labels.templates.manage')->name('admin.labels.templates.create');
    Route::get('/admin/labels/templates/{template}/edit', \App\Livewire\Admin\Labels\Templates\Edit::class)
        ->middleware('permission:labels.templates.manage')->name('admin.labels.templates.edit');

    // ─── Printers ─────────────────────────────────────────────────────────────────
    Route::get('/admin/printers', \App\Livewire\Admin\Labels\Printers\Index::class)
        ->middleware('permission:printers.manage')->name('admin.printers.index');
    Route::get('/admin/printers/create', \App\Livewire\Admin\Labels\Printers\CreateEdit::class)
        ->middleware('permission:printers.manage')->name('admin.printers.create');
    Route::get('/admin/printers/{printer}/edit', \App\Livewire\Admin\Labels\Printers\CreateEdit::class)
        ->middleware('permission:printers.manage')->name('admin.printers.edit');

    // ─── Scanner Devices ──────────────────────────────────────────────────────────
    Route::get('/admin/scanner-devices', \App\Livewire\Admin\ScannerDevices\Index::class)
        ->middleware('permission:scanner-devices.manage')->name('admin.scanner-devices.index');

    // ─── Label Print Log ──────────────────────────────────────────────────────────
    Route::get('/reports/labels/log', \App\Livewire\Reports\Labels\Log::class)
        ->middleware('permission:labels.log.view')->name('reports.labels.log');

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
Route::middleware(['auth', 'permission:scan.receive|scan.putaway|scan.pick|scan.dispatch|scan.gate|scan.move|scan.lookup|scan.stock-lookup'])->prefix('scan')->name('scan.')->group(function () {
    Route::get('/',             \App\Livewire\Scan\Home::class)->name('home');
    Route::get('/receive',      \App\Livewire\Scan\Receive::class)->name('receive');
    Route::get('/putaway',      \App\Livewire\Scan\Putaway::class)->name('putaway');
    Route::get('/pick',         \App\Livewire\Scan\Pick::class)->name('pick');
    Route::get('/dispatch',     \App\Livewire\Scan\Dispatch::class)->name('dispatch');
    Route::get('/move',         \App\Livewire\Scan\Move::class)->name('move');
    Route::get('/lookup',       \App\Livewire\Scan\Lookup::class)->name('lookup');
    Route::get('/stock-lookup', \App\Livewire\Scan\Lookup::class)->name('stock-lookup');
    Route::get('/gate',         \App\Livewire\Scan\GateCheck::class)->name('gate');
});

require __DIR__.'/auth.php';
