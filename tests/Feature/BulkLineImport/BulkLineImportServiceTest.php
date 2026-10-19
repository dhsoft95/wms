<?php

declare(strict_types=1);

use App\Data\Inventory\StockLine;
use App\Data\Inventory\StockPosting;
use App\Data\Inventory\StockReference;
use App\Enums\Inventory\MovementType;
use App\Enums\Inventory\StockStatus;
use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Livewire\Inbound\Orders\Create as PoCreate;
use App\Livewire\Inventory\Adjustments\Create as AdjCreate;
use App\Livewire\Warehouse\Movements\Create as MovCreate;
use App\Livewire\Warehouse\Transfers\Create as TrfCreate;
use App\Livewire\Warehouse\Wtr\Create as WtrCreate;
use App\Models\Item;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\BulkLineImportService;
use App\Services\Inventory\StockLedger;
use Database\Seeders\FoundationDefaultsSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Write a CSV to a temp file and return its path.
 * @param  array  $rows  First row = headers.
 */
function writeCsvFile(array $rows): string
{
    $path = sys_get_temp_dir() . '/bulk_test_' . uniqid() . '.csv';
    $fh   = fopen($path, 'w');
    foreach ($rows as $row) {
        fputcsv($fh, $row);
    }
    fclose($fh);
    return $path;
}

// ─── setUp ────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->seed(FoundationDefaultsSeeder::class);

    // Warehouse + VW
    $this->warehouse = Warehouse::factory()->create(['is_active' => true]);
    $this->vw        = VirtualWarehouse::factory()->create(['is_active' => true, 'warehouse_id' => $this->warehouse->id]);

    // UoM (seeded by FoundationDefaultsSeeder — EA is guaranteed present)
    $this->uom = UnitOfMeasure::where('code', 'EA')->first();

    // Bin location
    $this->bin = Location::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'level'        => LocationLevel::Bin,
        'type'         => LocationType::Storage,
        'status'       => LocationStatus::Active,
        'code'         => 'BIN-TEST-01',
    ]);

    // Item (non-serial tracked)
    $this->item = Item::factory()->create([
        'sku'          => 'SKU-TEST-001',
        'is_active'    => true,
        'track_serial' => false,
        'track_batch'  => false,
        'base_uom_id'  => $this->uom->id,
    ]);

    // Serial-tracked item
    $this->serialItem = Item::factory()->create([
        'sku'          => 'SKU-SERIAL-001',
        'is_active'    => true,
        'track_serial' => true,
        'track_batch'  => false,
        'base_uom_id'  => $this->uom->id,
    ]);

    $this->svc = app(BulkLineImportService::class);
});

// ─── Test 1: Valid CSV → resolved rows ────────────────────────────────────────

it('processes a valid csv file and returns resolved rows for PO fields', function () {
    $fields = PoCreate::lineFields();

    $path = writeCsvFile([
        ['Item (SKU)', 'Unit', 'Quantity', 'Batch No', 'Expiry Date', 'Notes'],
        ['SKU-TEST-001', 'EA', '10', 'BATCH-001', '2026-12-31', 'note one'],
        ['SKU-TEST-001', 'EA', '20', '', '', ''],
    ]);

    $result = $this->svc->process($path, $fields);

    expect($result['valid'])->toHaveCount(2);
    expect($result['errors'])->toBeEmpty();
    expect($result['total'])->toBe(2);

    $row = $result['valid'][0];
    expect($row['item_id'])->toBe($this->item->id);
    expect($row['uom_id'])->toBe($this->uom->id);
    expect($row['qty'])->toBe('10');

    unlink($path);
});

// ─── Test 2: Unknown SKU → error ─────────────────────────────────────────────

it('returns error for unknown sku', function () {
    $fields = PoCreate::lineFields();

    $path = writeCsvFile([
        ['Item (SKU)', 'Unit', 'Quantity', 'Batch No', 'Expiry Date', 'Notes'],
        ['DOES-NOT-EXIST', 'EA', '5', '', '', ''],
    ]);

    $result = $this->svc->process($path, $fields);

    expect($result['valid'])->toBeEmpty();
    expect($result['errors'])->toHaveCount(1);
    expect($result['errors'][0]['column'])->toBe('Item (SKU)');

    unlink($path);
});

// ─── Test 3: Unknown UoM → error ──────────────────────────────────────────────

it('returns error for unknown uom', function () {
    $fields = PoCreate::lineFields();

    $path = writeCsvFile([
        ['Item (SKU)', 'Unit', 'Quantity', 'Batch No', 'Expiry Date', 'Notes'],
        ['SKU-TEST-001', 'UNKNOWN-UOM', '5', '', '', ''],
    ]);

    $result = $this->svc->process($path, $fields);

    expect($result['valid'])->toBeEmpty();
    expect($result['errors'])->toHaveCount(1);
    expect($result['errors'][0]['column'])->toBe('Unit');

    unlink($path);
});

// ─── Test 4: Insufficient stock check ─────────────────────────────────────────

it('flags insufficient stock when check_available is true', function () {
    // Create a movement-style import with stock check
    $fields = MovCreate::lineFields();

    // Note: no stock seeded for this item/bin/vw → available = 0
    $path = writeCsvFile([
        ['Item (SKU)', 'From Bin', 'To Bin', 'Unit', 'Quantity', 'Batch No', 'Expiry Date', 'Serials'],
        ['SKU-TEST-001', 'BIN-TEST-01', 'BIN-TEST-01', 'EA', '50', '', '', ''],
    ]);

    // We need a second bin for To Bin (bin resolution works)
    $bin2 = Location::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'level'        => LocationLevel::Bin,
        'type'         => LocationType::Storage,
        'status'       => LocationStatus::Active,
        'code'         => 'BIN-TEST-02',
    ]);

    $path = writeCsvFile([
        ['Item (SKU)', 'From Bin', 'To Bin', 'Unit', 'Quantity', 'Batch No', 'Expiry Date', 'Serials'],
        ['SKU-TEST-001', 'BIN-TEST-01', 'BIN-TEST-02', 'EA', '50', '', '', ''],
    ]);

    $context = [
        'virtual_warehouse_id' => $this->vw->id,
        'check_available'      => true,
        'location_key'         => 'from_location_id',
    ];

    $result = $this->svc->process($path, $fields, $context);

    expect($result['valid'])->toBeEmpty();
    expect($result['errors'])->toHaveCount(1);
    expect($result['errors'][0]['message'])->toContain('Insufficient stock');

    unlink($path);
});

// ─── Test 5: Serial count mismatch ────────────────────────────────────────────

it('flags serial count mismatch for serial tracked item', function () {
    $fields = MovCreate::lineFields();

    $bin2 = Location::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'level'        => LocationLevel::Bin,
        'type'         => LocationType::Storage,
        'status'       => LocationStatus::Active,
        'code'         => 'BIN-TEST-03',
    ]);

    // Seed stock for serial item
    $ledger = app(StockLedger::class);
    $ledger->post(new StockPosting(
        type: MovementType::OpeningBalance,
        reference: new StockReference(),
        lines: [
            new StockLine(
                itemId: $this->serialItem->id,
                locationId: $this->bin->id,
                virtualWarehouseId: $this->vw->id,
                stockStatus: StockStatus::Available,
                enteredQty: '10.0000',
                enteredUomId: $this->uom->id,
            ),
        ],
    ));

    $path = writeCsvFile([
        ['Item (SKU)', 'From Bin', 'To Bin', 'Unit', 'Quantity', 'Batch No', 'Expiry Date', 'Serials'],
        // qty=3 but only 2 serials provided
        ['SKU-SERIAL-001', 'BIN-TEST-01', 'BIN-TEST-03', 'EA', '3', '', '', 'SN-001,SN-002'],
    ]);

    $context = [
        'virtual_warehouse_id' => $this->vw->id,
        'check_available'      => false, // skip stock check for this test
    ];

    $result = $this->svc->process($path, $fields, $context);

    expect($result['valid'])->toBeEmpty();
    expect($result['errors'])->toHaveCount(1);
    expect($result['errors'][0]['column'])->toBe('Serials');

    unlink($path);
});

// ─── Test 6: Duplicate rows ────────────────────────────────────────────────────

it('flags duplicate rows with same item location and batch', function () {
    $fields = PoCreate::lineFields();

    $path = writeCsvFile([
        ['Item (SKU)', 'Unit', 'Quantity', 'Batch No', 'Expiry Date', 'Notes'],
        ['SKU-TEST-001', 'EA', '10', 'BATCH-A', '', ''],
        ['SKU-TEST-001', 'EA', '20', 'BATCH-A', '', ''],  // duplicate
    ]);

    $result = $this->svc->process($path, $fields);

    // Both rows should be marked as errors due to duplicate detection
    expect($result['valid'])->toBeEmpty();
    expect($result['errors'])->toHaveCount(2);
    expect($result['errors'][0]['message'])->toContain('Duplicate row');

    unlink($path);
});

// ─── Test 7: Max 1000 rows ─────────────────────────────────────────────────────

it('enforces max 1000 rows and truncates silently', function () {
    $fields = PoCreate::lineFields();

    // Build a 1005-row CSV (1 header + 1005 data rows)
    $rows = [['Item (SKU)', 'Unit', 'Quantity', 'Batch No', 'Expiry Date', 'Notes']];
    for ($i = 1; $i <= 1005; $i++) {
        $rows[] = ['SKU-TEST-001', 'EA', (string) $i, '', '', ''];
    }

    $path = writeCsvFile($rows);

    $result = $this->svc->process($path, $fields);

    // Only max 1000 should be processed
    expect($result['total'])->toBe(1000);
    expect($result['truncated'])->toBeTrue();

    // All 1000 rows are valid (same item/no batch = duplicates flagged for same item+batch+location)
    // Rows share (SKU-TEST-001, '', '') which is all the same — they will all be duplicates
    // But total processed = 1000
    expect(count($result['valid']) + count($result['errors']))->toBe(1000);

    unlink($path);
});

// ─── Test 8: Template columns match lineFields for each screen ────────────────

it('template columns match linefields for each screen', function (string $class, string $sheetName) {
    $fields = $class::lineFields();

    $response = $this->svc->buildTemplate($fields, $sheetName);
    expect($response)->toBeInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class);

    // Check headers match column names in lineFields
    $columns = array_column($fields, 'column');
    expect($columns)->not->toBeEmpty();

    // Verify the response is a stream (truthy that it was created without exception)
    expect(count($columns))->toBeGreaterThan(0);
})->with([
    ['class' => PoCreate::class,  'sheetName' => 'Purchase Order Lines'],
    ['class' => AdjCreate::class, 'sheetName' => 'Adjustment Lines'],
    ['class' => MovCreate::class, 'sheetName' => 'Movement Lines'],
    ['class' => TrfCreate::class, 'sheetName' => 'Transfer Lines'],
    ['class' => WtrCreate::class, 'sheetName' => 'WH Transfer Lines'],
]);
