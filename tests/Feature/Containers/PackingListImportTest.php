<?php

use App\Models\Container;
use App\Models\ContainerExpectedLine;
use App\Models\DocumentSequence;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemUom;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use App\Services\Inbound\PackingListParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ── Helpers ────────────────────────────────────────────────────────────────────

function packingListCsv(array $extraRows = []): string
{
    $rows = [
        // Two title rows above the real header
        'Packing List - Container MSKU7712044,,,,,,,',
        'Date Prepared: 14-Oct-2026,,,,,,,',
        // Header row (3+ aliases detected here)
        'Item Code,Description,Quantity,Unit,Batch No,Expiry Date,Department',
        // Data rows
        'ABC001,Widget A,2,CTN,BATCH-001,2027-01-01,VW-A',
        'DEF002,Widget B (tracked),10,PCS,LOT-X99,2026-06-30,VW-B',
        'UNKNOWN001,Mystery Product,5,PCS,,,VW-A',
    ];

    return implode("\n", array_merge($rows, $extraRows));
}

function writeTempCsv(string $content): string
{
    @mkdir(storage_path('app/packing-lists'), 0755, true);
    $path = storage_path('app/packing-lists/test_' . uniqid() . '.csv');
    file_put_contents($path, $content);
    return $path;
}

// ── Setup ─────────────────────────────────────────────────────────────────────

beforeEach(function () {
    DocumentSequence::firstOrCreate(
        ['type' => 'CNT'],
        ['prefix' => 'CNT', 'include_year' => true, 'padding' => 4, 'reset_yearly' => true, 'next_number' => 1]
    );

    // Units of Measure
    $pcs = UnitOfMeasure::factory()->create(['code' => 'PCS', 'name' => 'Pieces', 'is_active' => true]);
    $ctn = UnitOfMeasure::factory()->create(['code' => 'CTN', 'name' => 'Carton', 'is_active' => true]);

    // Category
    $cat = ItemCategory::create(['name' => 'General', 'is_active' => true]);

    // Department + Virtual Warehouses
    $dept = \App\Models\Department::firstOrCreate(['code' => 'TEST'], ['name' => 'Test Dept', 'is_active' => true]);
    $vwA  = VirtualWarehouse::create(['code' => 'VW-A', 'name' => 'Warehouse A', 'department_id' => $dept->id, 'is_active' => true]);
    $vwB  = VirtualWarehouse::create(['code' => 'VW-B', 'name' => 'Warehouse B', 'department_id' => $dept->id, 'is_active' => true]);

    // Item ABC001: base PCS, alternate UoM CTN=10, no batch/expiry tracking
    $abc = Item::create([
        'sku'          => 'ABC001',
        'name'         => 'Widget A',
        'category_id'  => $cat->id,
        'base_uom_id'  => $pcs->id,
        'track_batch'  => false,
        'track_expiry' => false,
        'track_serial' => false,
        'is_active'    => true,
    ]);
    $abc->uoms()->create(['uom_id' => $pcs->id, 'factor_to_base' => '1.0000']);
    $abc->uoms()->create(['uom_id' => $ctn->id, 'factor_to_base' => '10.0000']);

    // Item DEF002: base PCS, tracks batch AND expiry
    $def = Item::create([
        'sku'          => 'DEF002',
        'name'         => 'Widget B (tracked)',
        'category_id'  => $cat->id,
        'base_uom_id'  => $pcs->id,
        'track_batch'  => true,
        'track_expiry' => true,
        'track_serial' => false,
        'is_active'    => true,
    ]);
    $def->uoms()->create(['uom_id' => $pcs->id, 'factor_to_base' => '1.0000']);
});

afterEach(function () {
    foreach (glob(storage_path('app/packing-lists/test_*.csv')) ?: [] as $f) {
        @unlink($f);
    }
});

// ── Tests ─────────────────────────────────────────────────────────────────────

it('detects header at row 2 when 2 title rows precede it', function () {
    $path   = writeTempCsv(packingListCsv());
    $parser = app(PackingListParser::class);

    $rawRows    = $parser->readRawRows($path, 20);
    $headerIdx  = $parser->detectHeaderRow($rawRows);

    expect($headerIdx)->toBe(2);
});

it('auto-maps Item Code → sku, Quantity → qty, Unit → uom', function () {
    $parser  = app(PackingListParser::class);
    $columns = ['Item Code', 'Description', 'Quantity', 'Unit', 'Batch No', 'Expiry Date', 'Department'];
    $mapping = $parser->autoMapColumns($columns);

    expect($mapping['Item Code'])->toBe('sku');
    expect($mapping['Quantity'])->toBe('qty');
    expect($mapping['Unit'])->toBe('uom');
    expect($mapping['Batch No'])->toBe('batch');
    expect($mapping['Expiry Date'])->toBe('expiry');
    expect($mapping['Department'])->toBe('vw');
});

it('converts 2 CTN to 20 PCS for ABC001', function () {
    $path   = writeTempCsv(packingListCsv());
    $parser = app(PackingListParser::class);

    $rawRows    = $parser->readRawRows($path, 20);
    $headerIdx  = $parser->detectHeaderRow($rawRows);
    $columns    = array_values(array_filter(array_map('strval', $rawRows[$headerIdx]), fn ($v) => $v !== ''));
    $mapping    = $parser->autoMapColumns($columns);
    $parsedRows = $parser->parseDataRows($path, $headerIdx, $mapping);

    $abcRow = $parser->resolveRow($parsedRows[0]); // first data row = ABC001

    expect($abcRow['raw_sku'])->toBe('ABC001');
    expect($abcRow['raw_uom'])->toBe('CTN');
    expect($abcRow['base_uom_code'])->toBe('PCS');
    expect((float) $abcRow['qty_base'])->toBe(20.0); // 2 CTN × 10 = 20 PCS
    expect($abcRow['unknown_sku'])->toBeFalsy();
    expect($abcRow['errors'])->toBeEmpty(); // VW-A is in DB
});

it('flags batch and expiry required for DEF002', function () {
    // Make a file where DEF002 is missing batch and expiry
    $csvContent = implode("\n", [
        'Packing List - Test,,,,,,',
        'Date: today,,,,,,',
        'Item Code,Description,Quantity,Unit,Batch No,Expiry Date,Department',
        'DEF002,Widget B,10,PCS,,,VW-B', // no batch, no expiry
    ]);
    $path   = writeTempCsv($csvContent);
    $parser = app(PackingListParser::class);

    $rawRows    = $parser->readRawRows($path, 20);
    $headerIdx  = $parser->detectHeaderRow($rawRows);
    $columns    = array_values(array_filter(array_map('strval', $rawRows[$headerIdx]), fn ($v) => $v !== ''));
    $mapping    = $parser->autoMapColumns($columns);
    $parsedRows = $parser->parseDataRows($path, $headerIdx, $mapping);
    $row        = $parser->resolveRow($parsedRows[0]);

    expect($row['track_batch'])->toBeTrue();
    expect($row['track_expiry'])->toBeTrue();
    expect($row['errors'])->toContain('Batch required');
    expect($row['errors'])->toContain('Expiry required');
});

it('flags unknown SKU as unknown_sku', function () {
    $path   = writeTempCsv(packingListCsv());
    $parser = app(PackingListParser::class);

    $rawRows    = $parser->readRawRows($path, 20);
    $headerIdx  = $parser->detectHeaderRow($rawRows);
    $columns    = array_values(array_filter(array_map('strval', $rawRows[$headerIdx]), fn ($v) => $v !== ''));
    $mapping    = $parser->autoMapColumns($columns);
    $parsedRows = $parser->parseDataRows($path, $headerIdx, $mapping);

    $unknownRow = $parser->resolveRow($parsedRows[2]); // third data row = UNKNOWN001

    expect($unknownRow['raw_sku'])->toBe('UNKNOWN001');
    expect($unknownRow['unknown_sku'])->toBeTrue();
    expect($unknownRow['item_id'])->toBeNull();
});

it('full import: parses 3 rows, resolves abc and def, flags unknown, and saves lines', function () {
    $path      = writeTempCsv(packingListCsv());
    $parser    = app(PackingListParser::class);
    $container = Container::factory()->create(['status' => 'expected']);

    $rawRows    = $parser->readRawRows($path, 20);
    $headerIdx  = $parser->detectHeaderRow($rawRows);
    $columns    = array_values(array_filter(array_map('strval', $rawRows[$headerIdx]), fn ($v) => $v !== ''));
    $mapping    = $parser->autoMapColumns($columns);
    $parsedRows = $parser->parseDataRows($path, $headerIdx, $mapping);
    $rows       = array_map(fn ($r) => $parser->resolveRow($r), $parsedRows);

    expect(count($rows))->toBe(3);

    // ABC001: CTN conversion
    expect((float) $rows[0]['qty_base'])->toBe(20.0);
    expect($rows[0]['unknown_sku'])->toBeFalsy();

    // DEF002: tracked item, has batch + expiry in the fixture CSV
    expect($rows[1]['track_batch'])->toBeTrue();
    expect($rows[1]['raw_batch'])->toBe('LOT-X99');
    expect($rows[1]['raw_expiry'])->toBe('2026-06-30');
    expect($rows[1]['errors'])->toBeEmpty();

    // UNKNOWN001: unknown SKU
    expect($rows[2]['unknown_sku'])->toBeTrue();

    // Simulate confirm: save resolved rows (skip unknowns by setting raw_sku line)
    $vwA = VirtualWarehouse::where('code', 'VW-A')->first();
    DB::transaction(function () use ($container, $rows, $vwA) {
        foreach ($rows as $i => $row) {
            ContainerExpectedLine::create([
                'container_id'         => $container->id,
                'line_no'              => $i + 1,
                'item_id'              => $row['item_id'],
                'raw_sku'              => $row['raw_sku'] ?: null,
                'raw_description'      => $row['raw_description'] ?: null,
                'qty'                  => $row['qty_base'] > 0 ? $row['qty_base'] : (float) $row['raw_qty'],
                'uom_id'               => $row['base_uom_id'] ?? $row['uom_id'],
                'batch_no'             => $row['raw_batch'] ?: null,
                'expiry_date'          => $row['raw_expiry'] ?: null,
                'weight_kg'            => null,
                'virtual_warehouse_id' => $row['vw_id'] ?? $vwA->id,
            ]);
        }
    });

    expect(ContainerExpectedLine::where('container_id', $container->id)->count())->toBe(3);

    $abcLine = ContainerExpectedLine::where('container_id', $container->id)->where('line_no', 1)->first();
    expect((float) $abcLine->qty)->toBe(20.0);

    $unknownLine = ContainerExpectedLine::where('container_id', $container->id)->where('line_no', 3)->first();
    expect($unknownLine->item_id)->toBeNull();
    expect($unknownLine->raw_sku)->toBe('UNKNOWN001');
});
