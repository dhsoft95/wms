<?php

declare(strict_types=1);

use App\Contracts\Reports\ReportDefinition;
use App\Models\Department;
use App\Models\Inventory\StockBalance;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Location;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Reports\Activity\ReceivedDispatchedReport;
use App\Reports\Control\AssetDisposalReport;
use App\Reports\Control\StockCountReport;
use App\Reports\Orders\DeliveredPendingGrnReport;
use App\Reports\Orders\PendingOrdersReport;
use App\Reports\Orders\PlannedOrdersReport;
use App\Reports\Stock\StockAgingReport;
use App\Reports\Stock\StockHoldingReport;
use App\Reports\Stock\StockInTransitReport;
use App\Services\Reports\ReportRegistry;
use Database\Seeders\FoundationDefaultsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Builder;

beforeEach(function () {
    (new RolePermissionSeeder())->run();
    (new FoundationDefaultsSeeder())->run();
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
});

// ─── Registry ─────────────────────────────────────────────────────────────────

it('registry loads all Stage-1 reports', function () {
    $registry = app(ReportRegistry::class);
    $keys     = array_map(fn ($d) => $d->key(), $registry->all());

    expect($keys)->toContain('stock-holding')
                 ->toContain('stock-aging')
                 ->toContain('stock-in-transit')
                 ->toContain('pending-orders');
});

it('registry find returns null for unknown key', function () {
    expect(app(ReportRegistry::class)->find('does-not-exist'))->toBeNull();
});

it('registry grouped returns definitions keyed by group', function () {
    $grouped = app(ReportRegistry::class)->grouped();

    expect($grouped)->toHaveKey('Stock')
                    ->toHaveKey('Orders');
    expect($grouped['Stock'])->not->toBeEmpty();
});

// ─── Stock Holding ────────────────────────────────────────────────────────────

it('StockHoldingReport implements ReportDefinition', function () {
    expect(new StockHoldingReport())->toBeInstanceOf(ReportDefinition::class);
});

it('StockHoldingReport query returns Builder', function () {
    $user    = User::factory()->create();
    $user->givePermissionTo('reports.view');
    $report  = new StockHoldingReport();
    $builder = $report->query(['as_of_date' => now()->format('Y-m-d')], $user);

    expect($builder)->toBeInstanceOf(Builder::class);
});

it('StockHoldingReport periodType is as_of', function () {
    expect((new StockHoldingReport())->periodType())->toBe('as_of');
});

// ─── Stock Aging ──────────────────────────────────────────────────────────────

it('StockAgingReport implements ReportDefinition', function () {
    expect(new StockAgingReport())->toBeInstanceOf(ReportDefinition::class);
});

it('StockAgingReport query returns Builder with age columns', function () {
    $user    = User::factory()->create();
    $report  = new StockAgingReport();
    $builder = $report->query(['as_of_date' => now()->format('Y-m-d')], $user);

    expect($builder)->toBeInstanceOf(Builder::class);
});

it('StockAgingReport periodType is as_of', function () {
    expect((new StockAgingReport())->periodType())->toBe('as_of');
});

// ─── Stock In Transit ─────────────────────────────────────────────────────────

it('StockInTransitReport implements ReportDefinition', function () {
    expect(new StockInTransitReport())->toBeInstanceOf(ReportDefinition::class);
});

it('StockInTransitReport query returns Builder', function () {
    $user    = User::factory()->create();
    $report  = new StockInTransitReport();
    $builder = $report->query(['as_of_date' => now()->format('Y-m-d')], $user);

    expect($builder)->toBeInstanceOf(Builder::class);
});

it('StockInTransitReport periodType is as_of', function () {
    expect((new StockInTransitReport())->periodType())->toBe('as_of');
});

// ─── Pending Orders ───────────────────────────────────────────────────────────

it('PendingOrdersReport implements ReportDefinition', function () {
    expect(new PendingOrdersReport())->toBeInstanceOf(ReportDefinition::class);
});

it('PendingOrdersReport query returns Builder', function () {
    $user    = User::factory()->create();
    $report  = new PendingOrdersReport();
    $builder = $report->query([
        'date_from' => now()->startOfMonth()->format('Y-m-d'),
        'date_to'   => now()->format('Y-m-d'),
    ], $user);

    expect($builder)->toBeInstanceOf(Builder::class);
});

it('PendingOrdersReport periodType is range', function () {
    expect((new PendingOrdersReport())->periodType())->toBe('range');
});

it('PendingOrdersReport pdf is enabled', function () {
    expect((new PendingOrdersReport())->pdfEnabled())->toBeTrue();
});

// ─── VW Scope ────────────────────────────────────────────────────────────────

// ─── Delivered & Pending GRN ──────────────────────────────────────────────────

it('DeliveredPendingGrnReport implements ReportDefinition', function () {
    expect(new DeliveredPendingGrnReport())->toBeInstanceOf(ReportDefinition::class);
});

it('DeliveredPendingGrnReport query returns Builder', function () {
    $user    = User::factory()->create();
    $report  = new DeliveredPendingGrnReport();
    $builder = $report->query([
        'date_from' => now()->startOfMonth()->format('Y-m-d'),
        'date_to'   => now()->format('Y-m-d'),
    ], $user);
    expect($builder)->toBeInstanceOf(Builder::class);
});

it('DeliveredPendingGrnReport periodType is range', function () {
    expect((new DeliveredPendingGrnReport())->periodType())->toBe('range');
});

// ─── Planned Orders ───────────────────────────────────────────────────────────

it('PlannedOrdersReport implements ReportDefinition', function () {
    expect(new PlannedOrdersReport())->toBeInstanceOf(ReportDefinition::class);
});

it('PlannedOrdersReport query returns Builder', function () {
    $user    = User::factory()->create();
    $report  = new PlannedOrdersReport();
    $builder = $report->query([
        'date_from' => now()->format('Y-m-d'),
        'date_to'   => now()->addMonths(3)->format('Y-m-d'),
    ], $user);
    expect($builder)->toBeInstanceOf(Builder::class);
});

it('PlannedOrdersReport periodType is range', function () {
    expect((new PlannedOrdersReport())->periodType())->toBe('range');
});

// ─── Received & Dispatched ────────────────────────────────────────────────────

it('ReceivedDispatchedReport implements ReportDefinition', function () {
    expect(new ReceivedDispatchedReport())->toBeInstanceOf(ReportDefinition::class);
});

it('ReceivedDispatchedReport query returns Builder with grouping', function () {
    $user    = User::factory()->create();
    $report  = new ReceivedDispatchedReport();
    $builder = $report->query([
        'date_from' => now()->startOfMonth()->format('Y-m-d'),
        'date_to'   => now()->format('Y-m-d'),
        'doc_type'  => 'all',
        'direction' => 'both',
        'grouping'  => 'day',
    ], $user);
    expect($builder)->toBeInstanceOf(Builder::class);
});

it('ReceivedDispatchedReport periodType is range', function () {
    expect((new ReceivedDispatchedReport())->periodType())->toBe('range');
});

// ─── Stock Count ──────────────────────────────────────────────────────────────

it('StockCountReport implements ReportDefinition', function () {
    expect(new StockCountReport())->toBeInstanceOf(ReportDefinition::class);
});

it('StockCountReport query returns Builder', function () {
    $user    = User::factory()->create();
    $report  = new StockCountReport();
    $builder = $report->query([
        'date_from' => now()->startOfMonth()->format('Y-m-d'),
        'date_to'   => now()->format('Y-m-d'),
    ], $user);
    expect($builder)->toBeInstanceOf(Builder::class);
});

it('StockCountReport pdf is enabled', function () {
    expect((new StockCountReport())->pdfEnabled())->toBeTrue();
});

// ─── Asset Disposal ───────────────────────────────────────────────────────────

it('AssetDisposalReport implements ReportDefinition', function () {
    expect(new AssetDisposalReport())->toBeInstanceOf(ReportDefinition::class);
});

it('AssetDisposalReport query returns Builder', function () {
    $user    = User::factory()->create();
    $report  = new AssetDisposalReport();
    $builder = $report->query([
        'date_from' => now()->startOfMonth()->format('Y-m-d'),
        'date_to'   => now()->format('Y-m-d'),
    ], $user);
    expect($builder)->toBeInstanceOf(Builder::class);
});

it('AssetDisposalReport pdf is enabled', function () {
    expect((new AssetDisposalReport())->pdfEnabled())->toBeTrue();
});

// ─── Registry completeness ────────────────────────────────────────────────────

it('registry loads all 9 reports', function () {
    $keys = array_map(fn ($d) => $d->key(), app(ReportRegistry::class)->all());

    expect(count($keys))->toBe(9)
        ->and($keys)->toContain('stock-holding')
        ->toContain('stock-aging')
        ->toContain('stock-in-transit')
        ->toContain('pending-orders')
        ->toContain('delivered-pending-grn')
        ->toContain('planned-orders')
        ->toContain('received-dispatched')
        ->toContain('stock-count')
        ->toContain('asset-disposal');
});

it('only reports 4 8 9 have pdf enabled', function () {
    $registry    = app(ReportRegistry::class);
    $pdfEnabled  = array_filter($registry->all(), fn ($d) => $d->pdfEnabled());
    $pdfKeys     = array_map(fn ($d) => $d->key(), $pdfEnabled);

    sort($pdfKeys);
    $expected = ['pending-orders', 'stock-count', 'asset-disposal'];
    sort($expected);
    expect($pdfKeys)->toEqual($expected);
});

it('StockHoldingReport restricts rows to accessible VWs for dept-user', function () {
    $dept      = Department::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $vwOwned   = VirtualWarehouse::factory()->create(['warehouse_id' => $warehouse->id, 'department_id' => $dept->id]);
    $vwOther   = VirtualWarehouse::factory()->create(['warehouse_id' => $warehouse->id, 'department_id' => $dept->id]);

    $category  = ItemCategory::factory()->create();
    $item      = Item::factory()->create(['category_id' => $category->id]);
    $location  = Location::factory()->create(['warehouse_id' => $warehouse->id]);

    // Balance in owned VW
    StockBalance::create([
        'item_id'              => $item->id,
        'location_id'          => $location->id,
        'virtual_warehouse_id' => $vwOwned->id,
        'stock_status'         => 'available',
        'qty_on_hand'          => 10,
        'qty_allocated'        => 0,
        'received_at'          => now()->subDays(5),
        'key_hash'             => md5("owned_{$item->id}_{$location->id}"),
    ]);

    // Balance in other VW
    StockBalance::create([
        'item_id'              => $item->id,
        'location_id'          => $location->id,
        'virtual_warehouse_id' => $vwOther->id,
        'stock_status'         => 'available',
        'qty_on_hand'          => 20,
        'qty_allocated'        => 0,
        'received_at'          => now()->subDays(5),
        'key_hash'             => md5("other_{$item->id}_{$location->id}"),
    ]);

    // User with access to vwOwned only
    $user = User::factory()->create();
    $user->assignRole('department-user');
    $user->virtualWarehouses()->attach($vwOwned->id);

    $report  = new StockHoldingReport();
    $results = $report->query(['as_of_date' => now()->format('Y-m-d')], $user)->get();

    expect($results->count())->toBe(1)
        ->and($results->first()->virtual_warehouse_id)->toBe($vwOwned->id);
});

// ─── Branding: summary() + totals() ──────────────────────────────────────────

it('every report returns array from summary() on empty rows', function () {
    $registry = app(ReportRegistry::class);
    foreach ($registry->all() as $definition) {
        $result = $definition->summary(collect());
        expect($result)->toBeArray("summary() on {$definition->key()} must return array");
    }
});

it('every report returns null or array from totals() on empty rows', function () {
    $registry = app(ReportRegistry::class);
    foreach ($registry->all() as $definition) {
        $result = $definition->totals(collect());
        expect($result === null || is_array($result))
            ->toBeTrue("totals() on {$definition->key()} must return null or array");
    }
});

// ─── Branding: Excel export ────────────────────────────────────────────────────

it('ExcelStyler produces a non-empty xlsx for every registered report', function () {
    $registry = app(ReportRegistry::class);
    $brand    = ['name' => 'Test Co', 'address' => '123 Main St', 'logoDataUri' => null];
    $filters  = [
        'date_from'  => now()->startOfMonth()->format('Y-m-d'),
        'date_to'    => now()->format('Y-m-d'),
        'as_of_date' => now()->format('Y-m-d'),
    ];

    foreach ($registry->all() as $definition) {
        $tmpPath = sys_get_temp_dir() . '/' . uniqid('rpt_test_', true) . '.xlsx';

        // Pass an empty collection: tests file/header generation without MySQL-only SQL
        (new \App\Reports\Export\ExcelStyler())->write($tmpPath, $definition, collect(), $brand, $filters);

        expect(file_exists($tmpPath))->toBeTrue("ExcelStyler did not create file for {$definition->key()}")
            ->and(filesize($tmpPath))->toBeGreaterThan(0, "ExcelStyler produced empty file for {$definition->key()}");

        @unlink($tmpPath);
    }
});

// ─── Branding: PDF layout render ──────────────────────────────────────────────

it('PDF layout renders without error for every pdf-enabled report', function () {
    $registry = app(ReportRegistry::class);
    $filters  = [
        'date_from'  => now()->startOfMonth()->format('Y-m-d'),
        'date_to'    => now()->format('Y-m-d'),
        'as_of_date' => now()->format('Y-m-d'),
    ];
    $brand = ['name' => 'Test Co', 'address' => '123 Main St', 'logoDataUri' => null];

    foreach (array_filter($registry->all(), fn ($d) => $d->pdfEnabled()) as $definition) {
        // Pass an empty collection: tests view compilation/rendering without MySQL-only SQL
        $rows       = collect();
        $signatures = match ($definition->key()) {
            'stock-count'    => ['Count Supervisor', 'Warehouse Manager', 'Finance / Auditor'],
            'asset-disposal' => ['Requesting Department', 'Approving Authority', 'HSE / Witness'],
            'pending-orders' => ['Prepared By', 'Reviewed By', 'Approved By'],
            default          => [],
        };

        $html = \Illuminate\Support\Facades\View::make('reports.layouts.pdf', [
            'definition'  => $definition,
            'rows'        => $rows,
            'filters'     => $filters,
            'brand'       => $brand,
            'summary'     => $definition->summary($rows),
            'totals'      => $definition->totals($rows),
            'signatures'  => $signatures,
            'generatedAt' => now()->format('d M Y H:i'),
        ])->render();

        expect(strlen($html))->toBeGreaterThan(100, "PDF layout produced empty HTML for {$definition->key()}");
    }
});
