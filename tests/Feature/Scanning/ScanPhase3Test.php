<?php

declare(strict_types=1);

use App\Enums\CycleCountStatus;
use App\Enums\GrnStatus;
use App\Enums\Inventory\StockStatus;
use App\Enums\Outbound\AllocationStatus;
use App\Enums\Outbound\DeliveryOrderStatus;
use App\Enums\Outbound\DoLineStatus;
use App\Enums\Outbound\GatePassStatus;
use App\Enums\Outbound\PickListStatus;
use App\Enums\Outbound\PickTaskStatus;
use App\Livewire\Inbound\Grn\Receive;
use App\Livewire\Inventory\Counts\Show as CountsShow;
use App\Livewire\Outbound\Dispatch\Index as DispatchIndex;
use App\Livewire\Outbound\Gate\Check;
use App\Livewire\Outbound\PickLists\Index as PickListsIndex;
use App\Models\CycleCount;
use App\Models\CycleCountLine;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderAllocation;
use App\Models\DeliveryOrderLine;
use App\Models\Dispatch as DispatchModel;
use App\Models\GatePass;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\Inventory\Lpn;
use App\Models\Inventory\StockBalance;
use App\Models\Item;
use App\Models\Location;
use App\Models\PickList;
use App\Models\PickTask;
use App\Models\PurchaseOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\RolePermissionSeeder())->run();
    (new \Database\Seeders\FoundationDefaultsSeeder())->run();
});

// ── Shared fixtures ────────────────────────────────────────────────────────────

function p3BaseFixtures(): array
{
    $wh   = Warehouse::factory()->create(['is_active' => true]);
    $vw   = VirtualWarehouse::factory()->create(['warehouse_id' => $wh->id, 'is_active' => true]);
    $uom  = UnitOfMeasure::firstOrCreate(['code' => 'P3EA'], ['name' => 'Each', 'type' => 'count', 'is_active' => true]);
    $item = Item::factory()->create(['base_uom_id' => $uom->id, 'is_active' => true]);
    $user = User::factory()->create(['status' => 'active']);
    return compact('wh', 'vw', 'uom', 'item', 'user');
}

// ── 1. Inbound/Grn/Receive ─────────────────────────────────────────────────────

it('Grn/Receive processScan sets activeLine when item SKU matches a GRN line', function () {
    ['wh' => $wh, 'vw' => $vw, 'uom' => $uom, 'item' => $item, 'user' => $user] = p3BaseFixtures();

    $po   = PurchaseOrder::factory()->create(['warehouse_id' => $wh->id]);
    $grn  = Grn::factory()->create(['warehouse_id' => $wh->id, 'purchase_order_id' => $po->id, 'status' => GrnStatus::Receiving->value]);
    $line = GrnLine::create([
        'grn_id'               => $grn->id,
        'item_id'              => $item->id,
        'uom_id'               => $uom->id,
        'virtual_warehouse_id' => $vw->id,
        'expected_qty'         => '10.0000',
    ]);

    Livewire::actingAs($user)
        ->test(Receive::class, ['grn' => $grn])
        ->set('scanInput', $item->sku)
        ->call('processScan')
        ->assertSet('activeLine', $line->id);
});

it('Grn/Receive processScan dispatches scan-error for unrecognised barcode', function () {
    ['wh' => $wh, 'vw' => $vw, 'uom' => $uom, 'user' => $user] = p3BaseFixtures();

    $po  = PurchaseOrder::factory()->create(['warehouse_id' => $wh->id]);
    $grn = Grn::factory()->create(['warehouse_id' => $wh->id, 'purchase_order_id' => $po->id, 'status' => GrnStatus::Receiving->value]);

    Livewire::actingAs($user)
        ->test(Receive::class, ['grn' => $grn])
        ->set('scanInput', 'TOTALLY-UNKNOWN-BARCODE-XYZ99')
        ->call('processScan')
        ->assertSet('activeLine', null)
        ->assertDispatched('scan-error');
});

// ── 2. Outbound/Gate/Check ─────────────────────────────────────────────────────

it('Gate/Check processScan populates found when scanning a valid GP- number', function () {
    ['wh' => $wh, 'vw' => $vw, 'user' => $user, 'uom' => $uom] = p3BaseFixtures();

    $do       = DeliveryOrder::factory()->create(['warehouse_id' => $wh->id, 'virtual_warehouse_id' => $vw->id, 'status' => DeliveryOrderStatus::Dispatched->value]);
    $dispatch = DispatchModel::create([
        'number'           => 'DSP-GATE-001',
        'delivery_order_id'=> $do->id,
        'vehicle_reg_no'   => 'T123ABC',
        'driver_name'      => 'Test Driver',
        'dispatched_at'    => now(),
        'dispatched_by'    => $user->id,
    ]);
    $gp = GatePass::create([
        'number'      => 'GP-GATE-001',
        'dispatch_id' => $dispatch->id,
        'status'      => GatePassStatus::Issued->value,
    ]);

    Livewire::actingAs($user)
        ->test(Check::class)
        ->set('scanInput', 'GP-GATE-001')
        ->call('processScan')
        ->assertSet('found.id', $gp->id)
        ->assertDispatched('scan-success');
});

it('Gate/Check processScan dispatches scan-error for unknown GP- number', function () {
    ['user' => $user] = p3BaseFixtures();

    Livewire::actingAs($user)
        ->test(Check::class)
        ->set('scanInput', 'GP-DOESNOTEXIST-9999')
        ->call('processScan')
        ->assertSet('found', null)
        ->assertDispatched('scan-error');
});

// ── 3. Inventory/Counts/Show ───────────────────────────────────────────────────

it('Counts/Show processScan dispatches focus-count-line event when item is on the sheet', function () {
    ['wh' => $wh, 'vw' => $vw, 'uom' => $uom, 'item' => $item, 'user' => $user] = p3BaseFixtures();

    $loc = Location::factory()->create(['warehouse_id' => $wh->id]);

    $count = CycleCount::create([
        'number'               => 'CC-P3-001',
        'virtual_warehouse_id' => $vw->id,
        'status'               => CycleCountStatus::Counting->value,
        'created_by'           => $user->id,
    ]);
    $line = CycleCountLine::create([
        'cycle_count_id' => $count->id,
        'location_id'    => $loc->id,
        'item_id'        => $item->id,
        'uom_id'         => $uom->id,
        'system_qty'     => '5.0000',
    ]);

    Livewire::actingAs($user)
        ->test(CountsShow::class, ['count' => $count])
        ->set('scanInput', $item->sku)
        ->call('processScan')
        ->assertSet('lastScannedLineId', $line->id)
        ->assertDispatched('focus-count-line')
        ->assertDispatched('scan-success');
});

it('Counts/Show processScan dispatches scan-error when item is not on the sheet', function () {
    ['wh' => $wh, 'vw' => $vw, 'uom' => $uom, 'user' => $user] = p3BaseFixtures();

    $count = CycleCount::create([
        'number'               => 'CC-P3-002',
        'virtual_warehouse_id' => $vw->id,
        'status'               => CycleCountStatus::Counting->value,
        'created_by'           => $user->id,
    ]);

    $otherItem = Item::factory()->create(['base_uom_id' => $uom->id, 'is_active' => true]);

    Livewire::actingAs($user)
        ->test(CountsShow::class, ['count' => $count])
        ->set('scanInput', $otherItem->sku)
        ->call('processScan')
        ->assertSet('lastScannedLineId', null)
        ->assertDispatched('scan-error');
});

// ── 4. Outbound/PickLists/Index ────────────────────────────────────────────────

it('PickLists/Index processScan opens pick task modal when scanning a valid PCK- number', function () {
    ['wh' => $wh, 'vw' => $vw, 'uom' => $uom, 'item' => $item, 'user' => $user] = p3BaseFixtures();

    $loc   = Location::factory()->create(['warehouse_id' => $wh->id]);
    $do    = DeliveryOrder::factory()->create(['warehouse_id' => $wh->id, 'virtual_warehouse_id' => $vw->id]);
    $doline = DeliveryOrderLine::create([
        'delivery_order_id'   => $do->id,
        'item_id'             => $item->id,
        'uom_id'              => $uom->id,
        'line_no'             => 1,
        'requested_qty'       => '5.0000',
        'requested_qty_base'  => '5.0000',
        'status'              => DoLineStatus::Allocated->value,
    ]);
    $stock = StockBalance::factory()->create([
        'item_id'              => $item->id,
        'location_id'          => $loc->id,
        'virtual_warehouse_id' => $vw->id,
        'stock_status'         => StockStatus::Available,
        'qty_on_hand'          => '10.0000',
        'qty_allocated'        => '5.0000',
    ]);
    $alloc = DeliveryOrderAllocation::create([
        'delivery_order_line_id' => $doline->id,
        'stock_balance_id'       => $stock->id,
        'item_id'                => $item->id,
        'location_id'            => $loc->id,
        'qty'                    => '5.0000',
        'status'                 => AllocationStatus::Reserved->value,
    ]);
    $pl = PickList::create([
        'number'            => 'PCK-P3-001',
        'delivery_order_id' => $do->id,
        'status'            => PickListStatus::Open->value,
    ]);
    $task = PickTask::create([
        'pick_list_id'                  => $pl->id,
        'delivery_order_allocation_id'  => $alloc->id,
        'item_id'                       => $item->id,
        'location_id'                   => $loc->id,
        'qty_to_pick'                   => '5.0000',
        'status'                        => PickTaskStatus::Open->value,
    ]);

    Livewire::actingAs($user)
        ->test(PickListsIndex::class)
        ->set('scanInput', 'PCK-P3-001')
        ->call('processScan')
        ->assertSet('showPickModal', true)
        ->assertSet('activeTaskId', $task->id)
        ->assertDispatched('scan-success');
});

it('PickLists/Index processScan dispatches scan-error for unknown barcode', function () {
    ['user' => $user] = p3BaseFixtures();

    Livewire::actingAs($user)
        ->test(PickListsIndex::class)
        ->set('scanInput', 'UNKNOWN-BARCODE-XYZ-99999')
        ->call('processScan')
        ->assertSet('showPickModal', false)
        ->assertDispatched('scan-error');
});

// ── 5. Outbound/Dispatch/Index ─────────────────────────────────────────────────

it('Dispatch/Index processScan opens dispatch modal when scanning a valid DO- number', function () {
    ['wh' => $wh, 'vw' => $vw, 'user' => $user] = p3BaseFixtures();

    $do = DeliveryOrder::factory()->create([
        'warehouse_id'         => $wh->id,
        'virtual_warehouse_id' => $vw->id,
        'status'               => DeliveryOrderStatus::Picked->value,
    ]);

    Livewire::actingAs($user)
        ->test(DispatchIndex::class)
        ->set('scanInput', $do->number)
        ->call('processScan')
        ->assertSet('showDispatchModal', true)
        ->assertSet('actionDoId', $do->id)
        ->assertDispatched('scan-success');
});

it('Dispatch/Index processScan adds LPN code to loadedLpnCodes when modal is open', function () {
    ['wh' => $wh, 'vw' => $vw, 'user' => $user] = p3BaseFixtures();

    $do  = DeliveryOrder::factory()->create(['warehouse_id' => $wh->id, 'virtual_warehouse_id' => $vw->id, 'status' => DeliveryOrderStatus::Picked->value]);
    $lpn = Lpn::factory()->create(['code' => 'LPN-DSP-TEST01', 'warehouse_id' => $wh->id]);

    Livewire::actingAs($user)
        ->test(DispatchIndex::class)
        ->call('openDispatchModal', $do->id)
        ->set('scanInput', 'LPN-DSP-TEST01')
        ->call('processScan')
        ->assertSet('loadedLpnCodes', ['LPN-DSP-TEST01'])
        ->assertDispatched('scan-success');
});
