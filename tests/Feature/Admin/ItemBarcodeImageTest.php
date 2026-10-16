<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\FoundationDefaultsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(FoundationDefaultsSeeder::class);

    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    $this->admin = User::factory()->create(['status' => 'active']);
    $this->admin->assignRole('system-admin');
});

function bcItem(): Item
{
    $uom = UnitOfMeasure::firstOrCreate(
        ['code' => 'EA'],
        ['name' => 'Each', 'type' => 'count', 'is_active' => true]
    );

    return Item::create([
        'sku'          => 'BC-' . uniqid(),
        'name'         => 'Barcode Test Item',
        'base_uom_id'  => $uom->id,
        'is_active'    => true,
        'track_batch'  => false,
        'track_expiry' => false,
        'track_serial' => false,
    ]);
}

test('png route returns image/png for a code128 barcode', function () {
    $item    = bcItem();
    $barcode = ItemBarcode::create([
        'item_id'    => $item->id,
        'barcode'    => 'CODE128-TEST-' . uniqid(),
        'uom_id'     => $item->base_uom_id,
        'type'       => 'internal',
        'is_primary' => true,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.items.barcodes.png', [$item, $barcode]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

test('png route returns image/png for a valid ean-13 barcode', function () {
    $item = bcItem();
    // 4006381333931 is a well-known valid EAN-13
    $barcode = ItemBarcode::create([
        'item_id'    => $item->id,
        'barcode'    => '4006381333931',
        'uom_id'     => $item->base_uom_id,
        'type'       => 'supplier',
        'is_primary' => true,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.items.barcodes.png', [$item, $barcode]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

test('png route returns 404 when barcode does not belong to item', function () {
    $item1   = bcItem();
    $item2   = bcItem();
    $barcode = ItemBarcode::create([
        'item_id'    => $item2->id,
        'barcode'    => 'WRONG-ITEM-' . uniqid(),
        'uom_id'     => $item2->base_uom_id,
        'type'       => 'internal',
        'is_primary' => false,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.items.barcodes.png', [$item1, $barcode]))
        ->assertNotFound();
});

test('barcodes tab renders img tags for each barcode', function () {
    $item    = bcItem();
    $barcode = ItemBarcode::create([
        'item_id'    => $item->id,
        'barcode'    => 'IMG-TEST-' . uniqid(),
        'uom_id'     => $item->base_uom_id,
        'type'       => 'internal',
        'is_primary' => true,
    ]);

    $this->actingAs($this->admin);

    Livewire::test(\App\Livewire\Admin\Items\Show::class, ['item' => $item])
        ->set('activeTab', 'barcodes')
        ->assertSeeHtml('<img')
        ->assertSeeHtml(route('admin.items.barcodes.png', [$item, $barcode]))
        ->assertSeeHtml(route('admin.items.barcodes.label', [$item, $barcode]));
});
