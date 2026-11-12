<?php
declare(strict_types=1);

use App\Enums\LabelTemplateType;
use App\Enums\PrinterKind;
use App\Models\Inventory\Lpn;
use App\Models\Inventory\StockBalance;
use App\Models\LabelTemplate;
use App\Models\Location;
use App\Models\Printer;
use App\Models\Warehouse;
use App\Services\Labels\Data\LocationLabelData;
use App\Services\Labels\Data\LpnLabelData;
use App\Services\Labels\LabelRenderer;
use App\Services\Labels\PrintJobService;
use Spatie\Permission\Models\Permission;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(fn () => config(['cache.default' => 'array']));

// ─── LocationLabelData ────────────────────────────────────────────────────────

it('LocationLabelData uses code when barcode is null', function () {
    $wh  = Warehouse::factory()->create();
    $loc = Location::factory()->create(['warehouse_id' => $wh->id, 'barcode' => null]);

    $data = LocationLabelData::build($loc->fresh());

    expect($data['location_code'])->toBe(strtoupper($loc->code))
        ->and($data['barcode'])->toBe(strtoupper($loc->code));
});

it('LocationLabelData uses explicit barcode when set', function () {
    $wh  = Warehouse::factory()->create();
    $loc = Location::factory()->create(['warehouse_id' => $wh->id, 'barcode' => 'CUSTOM-BC']);

    $data = LocationLabelData::build($loc);

    expect($data['location_code'])->toBe('CUSTOM-BC')
        ->and($data['barcode'])->toBe('CUSTOM-BC');
});

it('Location model auto-sets barcode to uppercased code on create', function () {
    $wh  = Warehouse::factory()->create();
    $loc = Location::factory()->create(['warehouse_id' => $wh->id, 'barcode' => null]);

    // The boot hook should have set barcode = strtoupper(code)
    expect($loc->barcode)->toBe(strtoupper($loc->code));
});

// ─── LpnLabelData ─────────────────────────────────────────────────────────────

it('LpnLabelData fills item fields for a single-item LPN', function () {
    $wh   = Warehouse::factory()->create();
    $lpn  = Lpn::factory()->create(['warehouse_id' => $wh->id]);
    $item = \App\Models\Item::factory()->create(['sku' => 'TST-001', 'name' => 'Test Item']);

    StockBalance::factory()->create([
        'lpn_id'      => $lpn->id,
        'item_id'     => $item->id,
        'qty_on_hand' => 5,
    ]);

    $data = LpnLabelData::build($lpn);

    expect($data['item_sku'])->toBe('TST-001')
        ->and($data['item_name'])->toBe('Test Item')
        ->and($data['qty'])->toBe('5');
});

it('LpnLabelData sets MIXED for multiple items on LPN', function () {
    $wh   = Warehouse::factory()->create();
    $lpn  = Lpn::factory()->create(['warehouse_id' => $wh->id]);
    $item1 = \App\Models\Item::factory()->create();
    $item2 = \App\Models\Item::factory()->create();

    StockBalance::factory()->create(['lpn_id' => $lpn->id, 'item_id' => $item1->id, 'qty_on_hand' => 3]);
    StockBalance::factory()->create(['lpn_id' => $lpn->id, 'item_id' => $item2->id, 'qty_on_hand' => 7]);

    $data = LpnLabelData::build($lpn);

    expect($data['item_sku'])->toBe('MIXED')
        ->and($data['item_name'])->toBe('MIXED')
        ->and($data['qty'])->toBe('10');
});

it('LpnLabelData returns empty item fields for empty LPN', function () {
    $wh  = Warehouse::factory()->create();
    $lpn = Lpn::factory()->create(['warehouse_id' => $wh->id]);

    $data = LpnLabelData::build($lpn);

    expect($data['item_sku'])->toBe('')
        ->and($data['lpn_code'])->toBe($lpn->code);
});

// ─── Storekeeper can print location labels ────────────────────────────────────

it('storekeeper can print location labels', function () {
    Permission::findOrCreate('labels.print', 'web');
    Permission::findOrCreate('locations.view', 'web');

    $role = \Spatie\Permission\Models\Role::findOrCreate('storekeeper', 'web');
    $role->givePermissionTo('labels.print');

    $user = \App\Models\User::factory()->create();
    $user->assignRole('storekeeper');

    $wh  = Warehouse::factory()->create();
    $loc = Location::factory()->create(['warehouse_id' => $wh->id]);

    $printer = Printer::factory()->create(['kind' => PrinterKind::PdfBrowser->value]);
    $tpl     = LabelTemplate::factory()->create([
        'type'       => LabelTemplateType::Location->value,
        'width_mm'   => 50,
        'height_mm'  => 30,
        'dpi'        => 203,
        'elements'   => [],
        'is_default' => true,
        'is_active'  => true,
    ]);

    $result = app(PrintJobService::class)->print(
        type:       LabelTemplateType::Location,
        printables: collect([$loc]),
        printer:    $printer,
        copies:     1,
        user:       $user,
        tpl:        $tpl,
    );

    expect($result->outputType)->toBe('pdf');
});
