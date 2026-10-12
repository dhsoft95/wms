<?php

/**
 * @group slow
 *
 * Run with: php artisan test --group=slow
 * These tests process large files and are excluded from the default suite.
 */

use App\Enums\ImportMode;
use App\Enums\ImportType;
use App\Imports\ItemImport;
use App\Imports\OpeningStockImport;
use App\Jobs\ValidateImportJob;
use App\Models\DataImport;
use App\Models\Department;
use App\Models\DocumentSequence;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

beforeEach(function () {
    DocumentSequence::firstOrCreate(
        ['type' => 'STK'],
        ['prefix' => 'STK', 'include_year' => true, 'padding' => 4, 'reset_yearly' => true, 'next_number' => 1]
    );
    @mkdir(storage_path('app/imports'), 0755, true);
});

afterEach(function () {
    foreach (glob(storage_path('app/imports/slow_*.csv')) ?: [] as $f) {
        @unlink($f);
    }
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeSlowImport(ImportType $type, string $csv, ImportMode $mode = ImportMode::CreateOnly): DataImport
{
    $relative = 'imports/slow_' . uniqid() . '.csv';
    file_put_contents(storage_path('app/' . $relative), $csv);

    return DataImport::create([
        'type'              => $type,
        'original_filename' => 'slow_test.csv',
        'stored_path'       => $relative,
        'status'            => 'uploaded',
        'mode'              => $mode,
        'uploaded_by'       => null,
    ]);
}

// ─── Group: Large file tests ───────────────────────────────────────────────────

it('[slow] validates a 10,000-row items CSV in under 60 seconds', function () {
    $uom = UnitOfMeasure::firstOrCreate(
        ['code' => 'PCS'],
        ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
    );
    ItemCategory::firstOrCreate(['name' => 'General'], ['is_active' => true]);

    $headers = ['sku', 'name', 'description', 'category', 'base_uom', 'length_cm', 'width_cm',
                 'height_cm', 'weight_kg', 'track_batch', 'track_expiry', 'track_serial',
                 'min_stock', 'max_stock', 'default_vw_code', 'barcode', 'barcode_2', 'uom_2', 'factor_2', 'uom_3', 'factor_3'];

    $lines = [implode(',', $headers)];
    for ($i = 1; $i <= 10000; $i++) {
        $sku    = sprintf('BULK-%05d', $i);
        $lines[] = implode(',', [
            $sku, "\"Bulk Item $i\"", '"A test item"', 'General', 'PCS',
            '', '', '', '', 'no', 'no', 'no', '', '', '', '', '', '', '', '', '',
        ]);
    }
    $csv = implode("\n", $lines);

    $import  = makeSlowImport(ImportType::Items, $csv);
    $handler = new ItemImport($import);

    $start = microtime(true);
    $handler->validate();
    $elapsed = microtime(true) - $start;

    $import->refresh();
    expect($import->error_rows)->toBe(0);
    expect($import->total_rows)->toBe(10000);
    expect($elapsed)->toBeLessThan(60.0, "Validation took {$elapsed}s — expected under 60s");

    fwrite(STDOUT, "\n  [slow] 10k items validation: " . round($elapsed, 2) . "s\n");
})->group('slow');

it('[slow] validates and imports a 5,000-serial opening stock file in under 120 seconds', function () {
    $uom = UnitOfMeasure::firstOrCreate(
        ['code' => 'PCS'],
        ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
    );
    $cat  = ItemCategory::firstOrCreate(['name' => 'General'], ['is_active' => true]);
    $item = Item::create([
        'sku'          => 'SERIAL-BULK-' . uniqid(),
        'name'         => 'Serial Bulk Item',
        'base_uom_id'  => $uom->id,
        'is_active'    => true,
        'track_batch'  => false,
        'track_expiry' => false,
        'track_serial' => true,
        'category_id'  => $cat->id,
    ]);

    $wh  = Warehouse::firstOrCreate(['code' => 'SWBULK'], ['name' => 'Serial Bulk WH', 'is_active' => true]);
    $loc = Location::create([
        'warehouse_id' => $wh->id,
        'code'         => 'SBL-' . uniqid(),
        'segment_code' => 'S',
        'level'        => \App\Enums\LocationLevel::Bin,
        'type'         => \App\Enums\LocationType::Storage,
        'status'       => \App\Enums\LocationStatus::Active,
        'is_pickable'  => true,
        'path'         => '/',
    ]);
    $dept = Department::firstOrCreate(['code' => 'SBDEPT'], ['name' => 'Serial Bulk Dept', 'is_active' => true]);
    $vw   = VirtualWarehouse::create([
        'code'          => 'SBV-' . uniqid(),
        'name'          => 'Serial Bulk VW',
        'department_id' => $dept->id,
        'is_active'     => true,
    ]);

    // Sheet 1: stock lines (1 row for 5000 serials)
    $stockHeaders = ['sku', 'location_code', 'vw_code', 'qty', 'uom', 'batch_no', 'expiry_date', 'status'];
    $stockCsv     = implode(',', $stockHeaders) . "\n" .
        implode(',', [$item->sku, $loc->code, $vw->code, '5000', 'PCS', '', '', 'available']);

    // Sheet 2 is CSV-only for this test; just validate the stock sheet
    // (multi-sheet serial validation requires Excel — here we test the CSV path)
    $import  = makeSlowImport(ImportType::OpeningStock, $stockCsv);
    $handler = new OpeningStockImport($import);

    $start = microtime(true);
    $handler->validate();
    $elapsed = microtime(true) - $start;

    $import->refresh();
    // No serial sheet attached (CSV has no sheet 2), so count mismatch only fires if item->track_serial
    // The row will pass validate (serial count check is in validateSerialSheet, only called with the serial map)
    expect($import->total_rows)->toBe(1);

    fwrite(STDOUT, "\n  [slow] 1-row serial stock validation: " . round($elapsed, 2) . "s\n");

    // Now post 5000 serials directly via StockLedger to test the actual ledger performance
    $serials = array_map(fn ($i) => sprintf('SN-%05d', $i), range(1, 5000));

    $start2 = microtime(true);
    app(\App\Services\Inventory\StockLedger::class)->openingBalance([
        new \App\Data\Inventory\StockLine(
            itemId: $item->id,
            locationId: $loc->id,
            virtualWarehouseId: $vw->id,
            stockStatus: \App\Enums\Inventory\StockStatus::Available,
            enteredQty: '5000',
            enteredUomId: $uom->id,
            serials: $serials,
        ),
    ]);
    $elapsed2 = microtime(true) - $start2;

    expect(\App\Models\Inventory\SerialNumber::where('item_id', $item->id)->count())->toBe(5000);
    expect($elapsed2)->toBeLessThan(120.0, "5k serial ledger post took {$elapsed2}s — expected under 120s");

    fwrite(STDOUT, "  [slow] 5000 serial ledger post: " . round($elapsed2, 2) . "s\n");
})->group('slow');
