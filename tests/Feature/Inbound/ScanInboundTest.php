<?php

declare(strict_types=1);

use App\Enums\GrnStatus;
use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Enums\ScanResultType;
use App\Livewire\Inbound\Grn\Tally;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\GrnReceipt;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\PutawayTask;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\Inbound\PutawayEngine;
use App\Services\Scanning\BarcodeResolver;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\RolePermissionSeeder())->run();
    (new \Database\Seeders\FoundationDefaultsSeeder())->run();
});

// ── Helpers ────────────────────────────────────────────────────────────────────

function scanTallyFixtures(): array
{
    $wh  = Warehouse::factory()->create(['is_active' => true]);
    $vw  = VirtualWarehouse::factory()->create(['warehouse_id' => $wh->id, 'is_active' => true]);
    $uom = UnitOfMeasure::firstOrCreate(['code' => 'EA'], ['name' => 'Each', 'type' => 'count', 'is_active' => true]);
    $item = Item::factory()->create(['base_uom_id' => $uom->id, 'is_active' => true]);
    $po   = PurchaseOrder::factory()->create(['warehouse_id' => $wh->id, 'number' => 'PO-TEST-SCAN-001']);
    $grn  = Grn::factory()->create([
        'warehouse_id'      => $wh->id,
        'purchase_order_id' => $po->id,
        'status'            => GrnStatus::Draft->value,
    ]);
    $line = GrnLine::create([
        'grn_id'               => $grn->id,
        'item_id'              => $item->id,
        'uom_id'               => $uom->id,
        'virtual_warehouse_id' => $vw->id,
        'expected_qty'         => '10.0000',
    ]);
    $user = User::factory()->create(['status' => 'active']);

    return compact('wh', 'vw', 'uom', 'item', 'po', 'grn', 'line', 'user');
}

// ── 1. PO- prefix ──────────────────────────────────────────────────────────────

it('resolves PO- prefix to a PurchaseOrder', function () {
    $wh = Warehouse::factory()->create();
    $po = PurchaseOrder::factory()->create(['number' => 'PO-2026-SCAN99', 'warehouse_id' => $wh->id]);

    $r = app(BarcodeResolver::class)->resolve('PO-2026-SCAN99');

    expect($r->type)->toBe(ScanResultType::PurchaseOrder)
        ->and($r->record->id)->toBe($po->id);
});

it('returns unknown for a PO- prefix that does not exist', function () {
    $r = app(BarcodeResolver::class)->resolve('PO-DOESNOTEXIST');
    expect($r->type)->toBe(ScanResultType::Unknown);
});

// ── 2. Tally: scan selects correct line ────────────────────────────────────────

it('tally scan by SKU selects the correct GRN line', function () {
    ['grn' => $grn, 'line' => $line, 'item' => $item, 'user' => $user] = scanTallyFixtures();

    Livewire::actingAs($user)
        ->test(Tally::class, ['grn' => $grn])
        ->set('scanInput', $item->sku)
        ->call('processScan')
        ->assertSet('activeLine', $line->id);
});

it('tally scan by item barcode selects the correct GRN line', function () {
    ['grn' => $grn, 'line' => $line, 'item' => $item, 'user' => $user] = scanTallyFixtures();

    $barcode = ItemBarcode::factory()->create(['item_id' => $item->id, 'barcode' => 'BC-TALLY-001']);

    Livewire::actingAs($user)
        ->test(Tally::class, ['grn' => $grn])
        ->set('scanInput', 'BC-TALLY-001')
        ->call('processScan')
        ->assertSet('activeLine', $line->id);
});

// ── 3. Tally: repeat scan increments qty ───────────────────────────────────────

it('scanning the same item again when line is active increments qty by 1', function () {
    ['grn' => $grn, 'line' => $line, 'item' => $item, 'user' => $user] = scanTallyFixtures();

    Livewire::actingAs($user)
        ->test(Tally::class, ['grn' => $grn])
        ->call('selectLine', $line->id)
        ->set('receiptQty', '5')
        ->set('scanInput', $item->sku)
        ->call('processScan')
        ->assertSet('receiptQty', '6');
});

// ── 4. Tally: unknown item rejected ────────────────────────────────────────────

it('scanning an item not on the GRN leaves activeLine unchanged', function () {
    ['grn' => $grn, 'user' => $user] = scanTallyFixtures();

    Livewire::actingAs($user)
        ->test(Tally::class, ['grn' => $grn])
        ->set('scanInput', 'UNKNOWN-SKU-NOTHERE-XYZ')
        ->call('processScan')
        ->assertSet('activeLine', null);
});

// ── 5. Tally: over-receipt blocked server-side ─────────────────────────────────

it('addReceipt is blocked when total would exceed expected qty', function () {
    ['grn' => $grn, 'line' => $line, 'uom' => $uom, 'user' => $user] = scanTallyFixtures();

    // Already received 9 of expected 10
    GrnReceipt::create([
        'grn_line_id' => $line->id,
        'qty'         => '9.0000',
        'uom_id'      => $uom->id,
        'condition'   => 'good',
        'received_by' => $user->id,
    ]);
    $line->recalculateTotals();

    Livewire::actingAs($user)
        ->test(Tally::class, ['grn' => $grn])
        ->call('selectLine', $line->id)
        ->set('receiptUomId', (string) $uom->id)
        ->set('receiptQty', '5')    // 9 + 5 = 14 > 10 → should block
        ->set('receiptCondition', 'good')
        ->call('addReceipt')
        ->assertHasErrors('receiptQty');

    expect(GrnReceipt::where('grn_line_id', $line->id)->count())->toBe(1); // no new receipt created
});

it('addReceipt succeeds when qty fits within expected', function () {
    ['grn' => $grn, 'line' => $line, 'uom' => $uom, 'user' => $user] = scanTallyFixtures();

    Livewire::actingAs($user)
        ->test(Tally::class, ['grn' => $grn])
        ->call('selectLine', $line->id)
        ->set('receiptUomId', (string) $uom->id)
        ->set('receiptQty', '5')
        ->set('receiptCondition', 'good')
        ->call('addReceipt')
        ->assertHasNoErrors();

    expect(GrnReceipt::where('grn_line_id', $line->id)->count())->toBe(1);
});

// ── 6. Putaway: wrong/blocked bin rejected ─────────────────────────────────────

it('putaway to a non-bin location throws DomainException', function () {
    $wh   = Warehouse::factory()->create(['is_active' => true]);
    $uom  = UnitOfMeasure::firstOrCreate(['code' => 'EA'], ['name' => 'Each', 'type' => 'count', 'is_active' => true]);
    $item = Item::factory()->create(['base_uom_id' => $uom->id]);
    $grn  = Grn::factory()->create(['warehouse_id' => $wh->id, 'status' => GrnStatus::Received->value]);
    $dock = Location::factory()->create([
        'warehouse_id' => $wh->id,
        'level'        => LocationLevel::Bin->value,
        'type'         => LocationType::ReceivingDock->value,
        'status'       => LocationStatus::Active->value,
    ]);
    $vw   = VirtualWarehouse::factory()->create(['warehouse_id' => $wh->id, 'is_active' => true]);
    $task = PutawayTask::create([
        'grn_id'               => $grn->id,
        'item_id'              => $item->id,
        'virtual_warehouse_id' => $vw->id,
        'qty'                  => '5.0000',
        'from_location_id'     => $dock->id,
        'status'               => 'open',
    ]);
    $user = User::factory()->create(['status' => 'active']);

    $zone = Location::factory()->create([
        'warehouse_id' => $wh->id,
        'level'        => LocationLevel::Zone->value,
        'type'         => LocationType::Storage->value,
        'status'       => LocationStatus::Active->value,
    ]);

    expect(fn () => app(PutawayEngine::class)->execute($task, $zone, $user))
        ->toThrow(\DomainException::class);
});

it('putaway to a blocked bin is rejected by the cycle-count guard', function () {
    $wh   = Warehouse::factory()->create(['is_active' => true]);
    $uom  = UnitOfMeasure::firstOrCreate(['code' => 'EA'], ['name' => 'Each', 'type' => 'count', 'is_active' => true]);
    $item = Item::factory()->create(['base_uom_id' => $uom->id]);
    $grn  = Grn::factory()->create(['warehouse_id' => $wh->id, 'status' => GrnStatus::Received->value]);
    $dock = Location::factory()->create([
        'warehouse_id' => $wh->id,
        'level'        => LocationLevel::Bin->value,
        'type'         => LocationType::ReceivingDock->value,
        'status'       => LocationStatus::Active->value,
    ]);
    $bin  = Location::factory()->create([
        'warehouse_id' => $wh->id,
        'level'        => LocationLevel::Bin->value,
        'type'         => LocationType::Storage->value,
        'status'       => LocationStatus::Blocked->value,
    ]);
    $vw   = VirtualWarehouse::factory()->create(['warehouse_id' => $wh->id, 'is_active' => true]);
    $task = PutawayTask::create([
        'grn_id'               => $grn->id,
        'item_id'              => $item->id,
        'virtual_warehouse_id' => $vw->id,
        'qty'                  => '5.0000',
        'from_location_id'     => $dock->id,
        'status'               => 'open',
    ]);
    $user = User::factory()->create(['status' => 'active']);

    // Blocked location has status != Active — PutawayEngine checks isBin() first,
    // but a blocked bin IS a bin; location status check is in the scan layer.
    // Test that the bin scan handler rejects inactive locations via processBinScan.
    $component = Livewire::actingAs($user)
        ->test(\App\Livewire\Warehouse\Putaway\Index::class);

    $component->call('openExecute', $task->id);
    $component->set('scanBinInput', $bin->code);
    $component->call('processBinScan', 0);

    // Blocked bin should NOT have been selected (locationCode stays empty)
    $splitRows = $component->get('splitRows');
    expect($splitRows[0]['locationCode'] ?? '')->toBe('');
});

// ── 7. Putaway: correct bin completes the task ─────────────────────────────────

it('putaway to a valid bin completes the task via PutawayEngine', function () {
    $wh   = Warehouse::factory()->create(['is_active' => true]);
    $uom  = UnitOfMeasure::firstOrCreate(['code' => 'EA'], ['name' => 'Each', 'type' => 'count', 'is_active' => true]);
    $item = Item::factory()->create(['base_uom_id' => $uom->id]);
    $grn  = Grn::factory()->create(['warehouse_id' => $wh->id, 'status' => GrnStatus::PutawayInProgress->value]);
    $dock = Location::factory()->create([
        'warehouse_id' => $wh->id,
        'level'        => LocationLevel::Bin->value,
        'type'         => LocationType::ReceivingDock->value,
        'status'       => LocationStatus::Active->value,
    ]);
    $bin  = Location::factory()->create([
        'warehouse_id' => $wh->id,
        'level'        => LocationLevel::Bin->value,
        'type'         => LocationType::Storage->value,
        'status'       => LocationStatus::Active->value,
    ]);
    $vw   = VirtualWarehouse::factory()->create(['warehouse_id' => $wh->id, 'is_active' => true]);
    $task = PutawayTask::create([
        'grn_id'               => $grn->id,
        'item_id'              => $item->id,
        'virtual_warehouse_id' => $vw->id,
        'qty'                  => '5.0000',
        'from_location_id'     => $dock->id,
        'status'               => 'open',
    ]);
    $user = User::factory()->create(['status' => 'active']);

    // Seed stock at dock so the ledger move has something to move
    $stockKey = new \App\Data\Inventory\StockKey(
        itemId:             $item->id,
        batchId:            null,
        lpnId:              null,
        locationId:         $dock->id,
        virtualWarehouseId: $vw->id,
        stockStatus:        \App\Enums\Inventory\StockStatus::Available,
    );
    \App\Models\Inventory\StockBalance::create([
        'key_hash'             => $stockKey->hash(),
        'item_id'              => $item->id,
        'location_id'          => $dock->id,
        'uom_id'               => $uom->id,
        'virtual_warehouse_id' => $vw->id,
        'stock_status'         => \App\Enums\Inventory\StockStatus::Available->value,
        'qty_on_hand'          => '5.0000',
        'qty_reserved'         => '0.0000',
        'qty_available'        => '5.0000',
    ]);

    app(PutawayEngine::class)->execute($task, $bin, $user);

    $task->refresh();
    expect($task->status->value)->toBe('completed')
        ->and($task->actual_location_id)->toBe($bin->id);
});
