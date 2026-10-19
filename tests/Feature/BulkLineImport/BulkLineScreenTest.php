<?php

declare(strict_types=1);

use App\Livewire\Inbound\Orders\Create as PoCreate;
use App\Livewire\Inventory\Adjustments\Create as AdjCreate;
use App\Livewire\Warehouse\Movements\Create as MovCreate;
use App\Livewire\Warehouse\Transfers\Create as TrfCreate;
use App\Livewire\Warehouse\Wtr\Create as WtrCreate;
use App\Models\Item;
use App\Models\Location;
use App\Models\ReasonCode;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\BulkLineImportService;
use Database\Seeders\FoundationDefaultsSeeder;
use Database\Seeders\RolePermissionSeeder;
use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Enums\LocationType;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeScreenCsv(array $rows): string
{
    $path = sys_get_temp_dir() . '/screen_test_' . uniqid() . '.csv';
    $fh   = fopen($path, 'w');
    foreach ($rows as $row) {
        fputcsv($fh, $row);
    }
    fclose($fh);
    return $path;
}

// ─── setUp ────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(FoundationDefaultsSeeder::class);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    $this->warehouse = Warehouse::factory()->create(['is_active' => true]);
    $this->vw        = VirtualWarehouse::factory()->create(['is_active' => true, 'warehouse_id' => $this->warehouse->id]);

    $this->uom = UnitOfMeasure::where('code', 'EA')->first();

    $this->item1 = Item::factory()->create([
        'sku'         => 'SCRSKT-001',
        'is_active'   => true,
        'track_serial'=> false,
        'base_uom_id' => $this->uom->id,
    ]);
    $this->item2 = Item::factory()->create([
        'sku'         => 'SCRSKT-002',
        'is_active'   => true,
        'track_serial'=> false,
        'base_uom_id' => $this->uom->id,
    ]);

    $this->bin1 = Location::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'level'        => LocationLevel::Bin,
        'type'         => LocationType::Storage,
        'status'       => LocationStatus::Active,
        'code'         => 'SCR-BIN-A',
    ]);
    $this->bin2 = Location::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'level'        => LocationLevel::Bin,
        'type'         => LocationType::Storage,
        'status'       => LocationStatus::Active,
        'code'         => 'SCR-BIN-B',
    ]);

    $this->reasonCode = ReasonCode::where('code', 'ADJ-LOSS')->first()
        ?? ReasonCode::factory()->create(['code' => 'ADJ-SCREEN', 'name' => 'Screen Test', 'applies_to' => 'adjustment', 'is_active' => true]);

    $this->svc = app(BulkLineImportService::class);
});

// ─── PO Screen ────────────────────────────────────────────────────────────────

it('po screen: blkUpload populates blkValid with 2 rows and blkConfirm applies them', function () {
    $fields = PoCreate::lineFields();

    $path = makeScreenCsv([
        ['Item (SKU)', 'Unit', 'Quantity', 'Batch No', 'Expiry Date', 'Notes'],
        ['SCRSKT-001', 'EA', '10', '', '', ''],
        ['SCRSKT-002', 'EA', '20', '', '', ''],
    ]);

    $result = $this->svc->process($path, $fields, []);

    expect($result['valid'])->toHaveCount(2);
    expect($result['errors'])->toBeEmpty();

    // Simulate applyBulkLines (append mode)
    $lines = [];
    foreach ($result['valid'] as $row) {
        $lines[] = [
            'item_id'     => $row['item_id']    ?? '',
            'uom_id'      => $row['uom_id']     ?? '',
            'qty'         => $row['qty']         ?? '',
            'batch_no'    => $row['batch_no']    ?? '',
            'expiry_date' => $row['expiry_date'] ?? '',
            'notes'       => $row['notes']       ?? '',
        ];
    }

    expect($lines)->toHaveCount(2);
    expect($lines[0]['item_id'])->toBe($this->item1->id);
    expect($lines[1]['item_id'])->toBe($this->item2->id);

    unlink($path);
});

// ─── Adjustment Screen ────────────────────────────────────────────────────────

it('adjustment screen: blkUpload populates blkValid with 2 rows', function () {
    $fields = AdjCreate::lineFields();

    $path = makeScreenCsv([
        ['Item (SKU)', 'Bin', 'Unit', 'Quantity (+/-)', 'Batch No', 'Reason Code', 'Serials'],
        ['SCRSKT-001', 'SCR-BIN-A', 'EA', '5', '', $this->reasonCode->code, ''],
        ['SCRSKT-002', 'SCR-BIN-A', 'EA', '10', '', $this->reasonCode->code, ''],
    ]);

    $result = $this->svc->process($path, $fields, []);

    expect($result['valid'])->toHaveCount(2);
    expect($result['errors'])->toBeEmpty();

    expect($result['valid'][0]['item_id'])->toBe($this->item1->id);
    expect($result['valid'][0]['location_id'])->toBe($this->bin1->id);
    expect($result['valid'][0]['reason_code_id'])->toBe($this->reasonCode->id);
    expect($result['valid'][1]['item_id'])->toBe($this->item2->id);

    unlink($path);
});

// ─── Movement Screen ──────────────────────────────────────────────────────────

it('movement screen: blkUpload populates blkValid with 2 rows', function () {
    $fields = MovCreate::lineFields();

    $path = makeScreenCsv([
        ['Item (SKU)', 'From Bin', 'To Bin', 'Unit', 'Quantity', 'Batch No', 'Expiry Date', 'Serials'],
        ['SCRSKT-001', 'SCR-BIN-A', 'SCR-BIN-B', 'EA', '5', '', '', ''],
        ['SCRSKT-002', 'SCR-BIN-A', 'SCR-BIN-B', 'EA', '10', '', '', ''],
    ]);

    // No stock check for this test
    $result = $this->svc->process($path, $fields, []);

    expect($result['valid'])->toHaveCount(2);
    expect($result['errors'])->toBeEmpty();

    expect($result['valid'][0]['item_id'])->toBe($this->item1->id);
    expect($result['valid'][0]['from_location_id'])->toBe($this->bin1->id);
    expect($result['valid'][0]['to_location_id'])->toBe($this->bin2->id);
    expect($result['valid'][1]['item_id'])->toBe($this->item2->id);

    unlink($path);
});

// ─── Transfer Screen ──────────────────────────────────────────────────────────

it('transfer screen: blkUpload populates blkValid with 2 rows', function () {
    $fields = TrfCreate::lineFields();

    $path = makeScreenCsv([
        ['Item (SKU)', 'Bin', 'Unit', 'Quantity', 'Batch No'],
        ['SCRSKT-001', 'SCR-BIN-A', 'EA', '5', ''],
        ['SCRSKT-002', 'SCR-BIN-A', 'EA', '8', ''],
    ]);

    $result = $this->svc->process($path, $fields, []);

    expect($result['valid'])->toHaveCount(2);
    expect($result['errors'])->toBeEmpty();

    expect($result['valid'][0]['item_id'])->toBe($this->item1->id);
    expect($result['valid'][0]['location_id'])->toBe($this->bin1->id);
    expect($result['valid'][1]['item_id'])->toBe($this->item2->id);

    unlink($path);
});

// ─── WTR Screen ───────────────────────────────────────────────────────────────

it('wtr screen: blkUpload populates blkValid with 2 rows', function () {
    $fields = WtrCreate::lineFields();

    $path = makeScreenCsv([
        ['Item (SKU)', 'Unit', 'Quantity', 'Batch No', 'Expiry Date'],
        ['SCRSKT-001', 'EA', '15', '', ''],
        ['SCRSKT-002', 'EA', '25', '', ''],
    ]);

    $result = $this->svc->process($path, $fields, []);

    expect($result['valid'])->toHaveCount(2);
    expect($result['errors'])->toBeEmpty();

    expect($result['valid'][0]['item_id'])->toBe($this->item1->id);
    expect($result['valid'][0]['uom_id'])->toBe($this->uom->id);
    expect($result['valid'][1]['item_id'])->toBe($this->item2->id);

    unlink($path);
});
