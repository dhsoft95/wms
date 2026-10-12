<?php

use App\Enums\ImportMode;
use App\Enums\ImportStatus;
use App\Enums\ImportType;
use App\Imports\DepartmentVwImport;
use App\Imports\ItemCategoryImport;
use App\Imports\ItemImport;
use App\Imports\PartnerImport;
use App\Jobs\ConfirmImportJob;
use App\Jobs\ValidateImportJob;
use App\Models\DataImport;
use App\Models\Department;
use App\Models\DocumentSequence;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Partner;
use App\Models\User;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

beforeEach(function () {
    DocumentSequence::firstOrCreate(
        ['type' => 'STK'],
        ['prefix' => 'STK', 'include_year' => true, 'padding' => 4, 'reset_yearly' => true, 'next_number' => 1]
    );

    // Create the imports directory for test files
    @mkdir(storage_path('app/imports'), 0755, true);
});

afterEach(function () {
    // Clean up test CSV files
    foreach (glob(storage_path('app/imports/test_*.csv')) ?: [] as $f) {
        @unlink($f);
    }
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeCsv(array $headers, array $rows): string
{
    $lines = [implode(',', $headers)];
    foreach ($rows as $row) {
        $lines[] = implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', $v) . '"', $row));
    }
    return implode("\n", $lines);
}

function makeImport(ImportType $type, string $csv, ImportMode $mode = ImportMode::CreateOnly): DataImport
{
    $relative = 'imports/test_' . uniqid() . '.csv';
    file_put_contents(storage_path('app/' . $relative), $csv);

    return DataImport::create([
        'type'              => $type,
        'original_filename' => 'test.csv',
        'stored_path'       => $relative,
        'status'            => 'uploaded',
        'mode'              => $mode,
        'uploaded_by'       => null,
    ]);
}

// ─── Group 1: Department & VW import ──────────────────────────────────────────

it('departments_vws: valid file creates departments and virtual warehouses', function () {
    $csv = makeCsv(
        ['department_code', 'department_name', 'report_email', 'vw_code', 'vw_name'],
        [['TEST-D1', 'Test Dept 1', 'td1@ex.com', 'TD1-VW', 'Test VW 1']]
    );

    $import  = makeImport(ImportType::DepartmentsVws, $csv);
    $handler = new DepartmentVwImport($import);
    $handler->validate();

    $import->refresh();
    expect($import->status)->toBe(ImportStatus::Validated);
    expect($import->error_rows)->toBe(0);

    $handler->import();

    expect(Department::where('code', 'TEST-D1')->exists())->toBeTrue();
    expect(VirtualWarehouse::where('code', 'TD1-VW')->exists())->toBeTrue();
});

it('departments_vws: create_only mode skips existing departments', function () {
    Department::create(['code' => 'EXIST-D', 'name' => 'Original Name', 'is_active' => true]);

    $csv = makeCsv(
        ['department_code', 'department_name', 'report_email', 'vw_code', 'vw_name'],
        [['EXIST-D', 'Updated Name', '', 'EXIST-VW', 'Exist VW']]
    );

    $import  = makeImport(ImportType::DepartmentsVws, $csv, ImportMode::CreateOnly);
    $handler = new DepartmentVwImport($import);
    $handler->validate();
    $handler->import();

    expect(Department::where('code', 'EXIST-D')->first()->name)->toBe('Original Name');
});

// ─── Group 2: Partners import ─────────────────────────────────────────────────

it('partners: valid file creates partner records', function () {
    $csv = makeCsv(
        ['code', 'name', 'types', 'tin', 'vrn', 'contact_person', 'phone', 'email', 'address', 'city', 'country'],
        [['P001', 'Acme Corp', 'customer', '', '', 'John', '+255700000000', 'j@acme.com', 'Main St', 'DSM', 'TZ']]
    );

    $import  = makeImport(ImportType::Partners, $csv);
    $handler = new PartnerImport($import);
    $handler->validate();
    $import->refresh();
    expect($import->error_rows)->toBe(0);

    $handler->import();
    expect(Partner::where('code', 'P001')->exists())->toBeTrue();
});

it('partners: invalid email produces validation error', function () {
    $csv = makeCsv(
        ['code', 'name', 'types', 'tin', 'vrn', 'contact_person', 'phone', 'email', 'address', 'city', 'country'],
        [['P002', 'Bad Email Co', 'supplier', '', '', '', '', 'not-an-email', '', '', '']]
    );

    $import  = makeImport(ImportType::Partners, $csv);
    $handler = new PartnerImport($import);
    $handler->validate();
    $import->refresh();

    expect($import->error_rows)->toBe(1);
    expect($import->errors[0]['column'])->toBe('email');
});

it('partners: create_or_update updates existing partner', function () {
    Partner::create(['code' => 'P-UPD', 'name' => 'Old Name', 'type' => ['customer'], 'is_active' => true]);

    $csv = makeCsv(
        ['code', 'name', 'types', 'tin', 'vrn', 'contact_person', 'phone', 'email', 'address', 'city', 'country'],
        [['P-UPD', 'New Name', 'customer', '', '', '', '', '', '', '', '']]
    );

    $import  = makeImport(ImportType::Partners, $csv, ImportMode::CreateOrUpdate);
    $handler = new PartnerImport($import);
    $handler->validate();
    $handler->import();

    expect(Partner::where('code', 'P-UPD')->first()->name)->toBe('New Name');
});

// ─── Group 3: Item Categories ──────────────────────────────────────────────────

it('item_categories: valid file creates categories and parent link', function () {
    $csv = makeCsv(
        ['name', 'parent_name'],
        [['Electronics', ''], ['Routers', 'Electronics']]
    );

    $import  = makeImport(ImportType::ItemCategories, $csv);
    $handler = new ItemCategoryImport($import);
    $handler->validate();
    $handler->import();

    $parent = ItemCategory::where('name', 'Electronics')->first();
    $child  = ItemCategory::where('name', 'Routers')->first();
    expect($parent)->not->toBeNull();
    expect($child->parent_id)->toBe($parent->id);
});

// ─── Group 4: Items ───────────────────────────────────────────────────────────

it('items: valid file creates an item record', function () {
    $uom = UnitOfMeasure::firstOrCreate(
        ['code' => 'PCS'],
        ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
    );
    ItemCategory::firstOrCreate(['name' => 'General'], ['is_active' => true]);

    $csv = makeCsv(
        ['sku', 'name', 'description', 'category', 'base_uom', 'length_cm', 'width_cm', 'height_cm', 'weight_kg', 'track_batch', 'track_expiry', 'track_serial', 'min_stock', 'max_stock', 'default_vw_code', 'barcode', 'barcode_2', 'uom_2', 'factor_2', 'uom_3', 'factor_3'],
        [['SKU-IMP-001', 'Imported Item', 'A widget', 'General', 'PCS', '', '', '', '', 'no', 'no', 'no', '', '', '', '', '', '', '', '', '']]
    );

    $import  = makeImport(ImportType::Items, $csv);
    $handler = new ItemImport($import);
    $handler->validate();
    $import->refresh();
    expect($import->error_rows)->toBe(0);

    $handler->import();
    expect(Item::where('sku', 'SKU-IMP-001')->exists())->toBeTrue();
});

it('items: track_serial on weight uom produces validation error', function () {
    $kgUom = UnitOfMeasure::firstOrCreate(
        ['code' => 'KG'],
        ['name' => 'Kilogram', 'type' => 'weight', 'is_active' => true]
    );
    ItemCategory::firstOrCreate(['name' => 'General'], ['is_active' => true]);

    $csv = makeCsv(
        ['sku', 'name', 'description', 'category', 'base_uom', 'length_cm', 'width_cm', 'height_cm', 'weight_kg', 'track_batch', 'track_expiry', 'track_serial', 'min_stock', 'max_stock', 'default_vw_code', 'barcode', 'barcode_2', 'uom_2', 'factor_2', 'uom_3', 'factor_3'],
        [['SKU-BAD-SER', 'Bad Item', '', 'General', 'KG', '', '', '', '', 'no', 'no', 'yes', '', '', '', '', '', '', '', '', '']]
    );

    $import  = makeImport(ImportType::Items, $csv);
    $handler = new ItemImport($import);
    $handler->validate();
    $import->refresh();

    expect($import->error_rows)->toBe(1);
    expect($import->errors[0]['column'])->toBe('track_serial');
});

// ─── Group 5: Validation error format ─────────────────────────────────────────

it('validation errors include row number and column name', function () {
    $csv = makeCsv(
        ['code', 'name', 'types', 'tin', 'vrn', 'contact_person', 'phone', 'email', 'address', 'city', 'country'],
        [['', '', '', '', '', '', '', '', '', '', '']] // missing code and name
    );

    $import  = makeImport(ImportType::Partners, $csv);
    $handler = new PartnerImport($import);
    $handler->validate();
    $import->refresh();

    expect($import->errors)->not->toBeEmpty();
    expect($import->errors[0])->toHaveKey('row');
    expect($import->errors[0])->toHaveKey('column');
    expect($import->errors[0])->toHaveKey('message');
    expect($import->errors[0]['row'])->toBe(1);
});

// ─── Group 6: Queue jobs ───────────────────────────────────────────────────────

it('ValidateImportJob updates status to validated on success', function () {
    $uom = UnitOfMeasure::firstOrCreate(
        ['code' => 'PCS'],
        ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
    );
    $csv = makeCsv(
        ['department_code', 'department_name', 'report_email', 'vw_code', 'vw_name'],
        [['JOB-D1', 'Job Dept', '', 'JOB-VW1', 'Job VW 1']]
    );

    $import = makeImport(ImportType::DepartmentsVws, $csv);
    (new ValidateImportJob($import))->handle();

    $import->refresh();
    expect($import->status)->toBe(ImportStatus::Validated);
});

it('ConfirmImportJob is blocked when errors exist', function () {
    // create an import in validated status but inject an error
    $csv    = makeCsv(['department_code', 'department_name', 'report_email', 'vw_code', 'vw_name'], [['', 'No Code', '', 'VW1', 'VW Name']]);
    $import = makeImport(ImportType::DepartmentsVws, $csv);
    $import->update(['status' => 'validated', 'error_rows' => 1, 'errors' => [['row' => 1, 'column' => 'department_code', 'message' => 'required']]]);

    (new ConfirmImportJob($import))->handle();

    $import->refresh();
    expect($import->status)->toBe(ImportStatus::FailedValidation);
});

// ─── Group 7: Opening stock idempotency ───────────────────────────────────────

it('running ConfirmImportJob twice for opening stock does not double the stock', function () {
    $uom = \App\Models\UnitOfMeasure::firstOrCreate(
        ['code' => 'PCS'],
        ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
    );
    $cat = \App\Models\ItemCategory::firstOrCreate(['name' => 'General'], ['is_active' => true]);
    $item = \App\Models\Item::create([
        'sku'          => 'IDEM-' . uniqid(),
        'name'         => 'Idempotency Item',
        'base_uom_id'  => $uom->id,
        'is_active'    => true,
        'track_batch'  => false,
        'track_expiry' => false,
        'track_serial' => false,
        'category_id'  => $cat->id,
    ]);
    $wh  = \App\Models\Warehouse::firstOrCreate(['code' => 'WHX'], ['name' => 'WH X', 'is_active' => true]);
    $loc = \App\Models\Location::create([
        'warehouse_id' => $wh->id,
        'code'         => 'IDEM-LOC-' . uniqid(),
        'segment_code' => 'X',
        'level'        => \App\Enums\LocationLevel::Bin,
        'type'         => \App\Enums\LocationType::Storage,
        'status'       => \App\Enums\LocationStatus::Active,
        'is_pickable'  => true,
        'path'         => '/',
    ]);
    $dept = \App\Models\Department::firstOrCreate(['code' => 'IDEPT'], ['name' => 'Idem Dept', 'is_active' => true]);
    $vw   = \App\Models\VirtualWarehouse::create([
        'code'          => 'IDEM-VW-' . uniqid(),
        'name'          => 'Idem VW',
        'department_id' => $dept->id,
        'is_active'     => true,
    ]);

    $csv = makeCsv(
        ['sku', 'location_code', 'vw_code', 'qty', 'uom', 'batch_no', 'expiry_date', 'status'],
        [[$item->sku, $loc->code, $vw->code, '10', 'PCS', '', '', 'available']]
    );

    $import = makeImport(\App\Enums\ImportType::OpeningStock, $csv);

    // Validate, then confirm twice
    (new \App\Jobs\ValidateImportJob($import))->handle();
    $import->refresh();
    expect($import->status)->toBe(ImportStatus::Validated);

    (new \App\Jobs\ConfirmImportJob($import))->handle();
    (new \App\Jobs\ConfirmImportJob($import))->handle(); // second run must be idempotent

    $balance = \App\Models\Inventory\StockBalance::where('item_id', $item->id)->first();
    expect((float) $balance->qty_on_hand)->toBe(10.0); // not 20
});

// ─── Group 9: Opening stock validation rules (shared validator) ───────────────

function makeOpeningStockFixture(): array
{
    $uom  = \App\Models\UnitOfMeasure::firstOrCreate(['code' => 'PCS'], ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]);
    $cat  = \App\Models\ItemCategory::firstOrCreate(['name' => 'General'], ['is_active' => true]);
    $wh   = \App\Models\Warehouse::firstOrCreate(['code' => 'VALWH'], ['name' => 'Val WH', 'is_active' => true]);
    $dept = \App\Models\Department::firstOrCreate(['code' => 'VALDEPT'], ['name' => 'Val Dept', 'is_active' => true]);

    return compact('uom', 'cat', 'wh', 'dept');
}

it('opening_stock: inactive item produces validation error on sku', function () {
    ['uom' => $uom, 'cat' => $cat, 'wh' => $wh, 'dept' => $dept] = makeOpeningStockFixture();

    $item = \App\Models\Item::create([
        'sku' => 'INACT-' . uniqid(), 'name' => 'Inactive Item',
        'base_uom_id' => $uom->id, 'is_active' => false,
        'track_batch' => false, 'track_expiry' => false, 'track_serial' => false, 'category_id' => $cat->id,
    ]);
    $loc = \App\Models\Location::create([
        'warehouse_id' => $wh->id, 'code' => 'INACT-LOC-' . uniqid(), 'segment_code' => 'I',
        'level' => \App\Enums\LocationLevel::Bin, 'type' => \App\Enums\LocationType::Storage,
        'status' => \App\Enums\LocationStatus::Active, 'is_pickable' => true, 'path' => '/',
    ]);
    $vw = \App\Models\VirtualWarehouse::create([
        'code' => 'INACT-VW-' . uniqid(), 'name' => 'Inact VW', 'department_id' => $dept->id, 'is_active' => true,
    ]);

    $csv    = makeCsv(['sku', 'location_code', 'vw_code', 'qty', 'uom', 'batch_no', 'expiry_date', 'status'],
                      [[$item->sku, $loc->code, $vw->code, '5', 'PCS', '', '', 'available']]);
    $import = makeImport(\App\Enums\ImportType::OpeningStock, $csv);
    (new \App\Imports\OpeningStockImport($import))->validate();
    $import->refresh();

    expect($import->error_rows)->toBe(1);
    expect($import->errors[0]['column'])->toBe('sku');
});

it('opening_stock: blocked location produces validation error on location_code', function () {
    ['uom' => $uom, 'cat' => $cat, 'wh' => $wh, 'dept' => $dept] = makeOpeningStockFixture();

    $item = \App\Models\Item::create([
        'sku' => 'BLOC-' . uniqid(), 'name' => 'Block Test Item',
        'base_uom_id' => $uom->id, 'is_active' => true,
        'track_batch' => false, 'track_expiry' => false, 'track_serial' => false, 'category_id' => $cat->id,
    ]);
    $loc = \App\Models\Location::create([
        'warehouse_id' => $wh->id, 'code' => 'BLOC-LOC-' . uniqid(), 'segment_code' => 'B',
        'level' => \App\Enums\LocationLevel::Bin, 'type' => \App\Enums\LocationType::Storage,
        'status' => \App\Enums\LocationStatus::Blocked, 'is_pickable' => false, 'path' => '/',
    ]);
    $vw = \App\Models\VirtualWarehouse::create([
        'code' => 'BLOC-VW-' . uniqid(), 'name' => 'Block VW', 'department_id' => $dept->id, 'is_active' => true,
    ]);

    $csv    = makeCsv(['sku', 'location_code', 'vw_code', 'qty', 'uom', 'batch_no', 'expiry_date', 'status'],
                      [[$item->sku, $loc->code, $vw->code, '5', 'PCS', '', '', 'available']]);
    $import = makeImport(\App\Enums\ImportType::OpeningStock, $csv);
    (new \App\Imports\OpeningStockImport($import))->validate();
    $import->refresh();

    expect($import->error_rows)->toBe(1);
    expect($import->errors[0]['column'])->toBe('location_code');
});

it('opening_stock: inactive vw produces validation error on vw_code', function () {
    ['uom' => $uom, 'cat' => $cat, 'wh' => $wh, 'dept' => $dept] = makeOpeningStockFixture();

    $item = \App\Models\Item::create([
        'sku' => 'INVW-' . uniqid(), 'name' => 'Inactive VW Test',
        'base_uom_id' => $uom->id, 'is_active' => true,
        'track_batch' => false, 'track_expiry' => false, 'track_serial' => false, 'category_id' => $cat->id,
    ]);
    $loc = \App\Models\Location::create([
        'warehouse_id' => $wh->id, 'code' => 'INVW-LOC-' . uniqid(), 'segment_code' => 'V',
        'level' => \App\Enums\LocationLevel::Bin, 'type' => \App\Enums\LocationType::Storage,
        'status' => \App\Enums\LocationStatus::Active, 'is_pickable' => true, 'path' => '/',
    ]);
    $vw = \App\Models\VirtualWarehouse::create([
        'code' => 'INVW-VW-' . uniqid(), 'name' => 'Inactive VW', 'department_id' => $dept->id, 'is_active' => false,
    ]);

    $csv    = makeCsv(['sku', 'location_code', 'vw_code', 'qty', 'uom', 'batch_no', 'expiry_date', 'status'],
                      [[$item->sku, $loc->code, $vw->code, '5', 'PCS', '', '', 'available']]);
    $import = makeImport(\App\Enums\ImportType::OpeningStock, $csv);
    (new \App\Imports\OpeningStockImport($import))->validate();
    $import->refresh();

    expect($import->error_rows)->toBe(1);
    expect($import->errors[0]['column'])->toBe('vw_code');
});

it('opening_stock: conflicting batch expiry produces validation error on expiry_date', function () {
    ['uom' => $uom, 'cat' => $cat, 'wh' => $wh, 'dept' => $dept] = makeOpeningStockFixture();

    $item = \App\Models\Item::create([
        'sku' => 'BEXP-' . uniqid(), 'name' => 'Batch Expiry Test',
        'base_uom_id' => $uom->id, 'is_active' => true,
        'track_batch' => true, 'track_expiry' => true, 'track_serial' => false, 'category_id' => $cat->id,
    ]);
    // Pre-create the batch with a specific expiry date
    \App\Models\Inventory\Batch::create([
        'item_id' => $item->id, 'batch_no' => 'BATCHX', 'expiry_date' => '2025-01-15',
    ]);
    $loc = \App\Models\Location::create([
        'warehouse_id' => $wh->id, 'code' => 'BEXP-LOC-' . uniqid(), 'segment_code' => 'E',
        'level' => \App\Enums\LocationLevel::Bin, 'type' => \App\Enums\LocationType::Storage,
        'status' => \App\Enums\LocationStatus::Active, 'is_pickable' => true, 'path' => '/',
    ]);
    $vw = \App\Models\VirtualWarehouse::create([
        'code' => 'BEXP-VW-' . uniqid(), 'name' => 'Batch Exp VW', 'department_id' => $dept->id, 'is_active' => true,
    ]);

    $csv    = makeCsv(['sku', 'location_code', 'vw_code', 'qty', 'uom', 'batch_no', 'expiry_date', 'status'],
                      [[$item->sku, $loc->code, $vw->code, '10', 'PCS', 'BATCHX', '2026-06-01', 'available']]);
    $import = makeImport(\App\Enums\ImportType::OpeningStock, $csv);
    (new \App\Imports\OpeningStockImport($import))->validate();
    $import->refresh();

    expect($import->error_rows)->toBe(1);
    expect($import->errors[0]['column'])->toBe('expiry_date');
});

// ─── Group 8: Permissions ─────────────────────────────────────────────────────

it('imports.manage is required to access the import index', function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $viewer = User::factory()->create();
    $viewer->assignRole('viewer-auditor'); // does not have imports.manage

    $response = $this->actingAs($viewer)->get(route('admin.imports.index'));
    $response->assertForbidden();
});

it('warehouse manager can access import page', function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $wm = User::factory()->create();
    $wm->assignRole('warehouse-manager');

    $response = $this->actingAs($wm)->get(route('admin.imports.index'));
    $response->assertOk();
});
