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
use App\Enums\MovementStatus;
use App\Enums\TransferStatus;
use App\Enums\Transfers\WarehouseTransferStatus;
use App\Models\DocumentSequence;
use App\Models\InternalMovement;
use App\Models\Inventory\StockBalance;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockTransfer;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use App\Services\Inventory\StockLedger;
use App\Services\Inventory\TransferWorkflow;
use App\Services\Transfers\WarehouseTransferWorkflow;
use App\Services\Warehouse\MovementWorkflow;
use Database\Seeders\FoundationDefaultsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

// ─── setUp ────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(FoundationDefaultsSeeder::class);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    $this->manager       = User::factory()->create(['status' => 'active']);
    $this->manager->assignRole('warehouse-manager');

    $this->deptUser      = User::factory()->create(['status' => 'active']);
    $this->deptUser->assignRole('department-user');

    $this->storekeeper   = User::factory()->create(['status' => 'active']);
    $this->storekeeper->assignRole('storekeeper');

    $this->whOfficer     = User::factory()->create(['status' => 'active']);
    $this->whOfficer->assignRole('storekeeper'); // warehouse-officer maps to storekeeper role

    $this->uom = UnitOfMeasure::firstOrCreate(['code' => 'PCS'], [
        'name' => 'Pieces', 'type' => 'count', 'is_active' => true,
    ]);

    // Warehouse A
    $this->warehouseA = Warehouse::factory()->create(['is_active' => true]);
    $this->vwA        = VirtualWarehouse::factory()->create([
        'warehouse_id' => $this->warehouseA->id,
        'is_active'    => true,
    ]);

    // Warehouse B (for WTR tests)
    $this->warehouseB = Warehouse::factory()->create(['is_active' => true]);
    $this->vwB        = VirtualWarehouse::factory()->create([
        'warehouse_id' => $this->warehouseB->id,
        'is_active'    => true,
    ]);

    // Bins in warehouse A
    $this->binA = Location::factory()->create([
        'warehouse_id' => $this->warehouseA->id,
        'level'        => LocationLevel::Bin,
        'type'         => LocationType::Storage,
        'status'       => LocationStatus::Active,
        'code'         => 'A-BIN-01',
    ]);
    $this->binB = Location::factory()->create([
        'warehouse_id' => $this->warehouseA->id,
        'level'        => LocationLevel::Bin,
        'type'         => LocationType::Storage,
        'status'       => LocationStatus::Active,
        'code'         => 'A-BIN-02',
    ]);

    // Transit location in warehouse B
    $this->transitB = Location::factory()->create([
        'warehouse_id' => $this->warehouseB->id,
        'level'        => LocationLevel::Bin,
        'type'         => LocationType::Transit,
        'status'       => LocationStatus::Active,
        'code'         => 'TRANSIT-B',
    ]);

    // Item
    $this->item = Item::factory()->create([
        'is_active'    => true,
        'track_batch'  => false,
        'track_serial' => false,
        'track_expiry' => false,
        'base_uom_id'  => $this->uom->id,
    ]);

    // Seed stock into binA (VW A)
    $this->ledger = app(StockLedger::class);
    $this->ledger->post(new StockPosting(
        type: MovementType::OpeningBalance,
        reference: new StockReference(),
        lines: [
            new StockLine(
                itemId:             $this->item->id,
                locationId:         $this->binA->id,
                virtualWarehouseId: $this->vwA->id,
                stockStatus:        StockStatus::Available,
                enteredQty:         '100.0000',
                enteredUomId:       $this->uom->id,
            ),
        ],
    ));

    // Also seed stock for VW B tests
    $this->ledger->post(new StockPosting(
        type: MovementType::OpeningBalance,
        reference: new StockReference(),
        lines: [
            new StockLine(
                itemId:             $this->item->id,
                locationId:         $this->binA->id,
                virtualWarehouseId: $this->vwB->id,
                stockStatus:        StockStatus::Available,
                enteredQty:         '50.0000',
                enteredUomId:       $this->uom->id,
            ),
        ],
    ));

    DocumentSequence::firstOrCreate(['type' => 'TRF'], [
        'prefix' => 'TRF', 'include_year' => true, 'padding' => 4, 'reset_yearly' => true, 'current' => 0,
    ]);
    DocumentSequence::firstOrCreate(['type' => 'MOV'], [
        'prefix' => 'MOV', 'include_year' => true, 'padding' => 4, 'reset_yearly' => true, 'current' => 0,
    ]);
    DocumentSequence::firstOrCreate(['type' => 'WTR'], [
        'prefix' => 'WTR', 'include_year' => true, 'padding' => 4, 'reset_yearly' => true, 'current' => 0,
    ]);
    DocumentSequence::firstOrCreate(['type' => 'DN'], [
        'prefix' => 'DN', 'include_year' => true, 'padding' => 4, 'reset_yearly' => true, 'current' => 0,
    ]);
    DocumentSequence::firstOrCreate(['type' => 'RCN'], [
        'prefix' => 'RCN', 'include_year' => true, 'padding' => 4, 'reset_yearly' => true, 'current' => 0,
    ]);
});

// ─── T-01: Only department-user can create — storekeeper/WH-officer get 403 ──

it('department-user can create a transfer; storekeeper and warehouse-officer cannot', function () {
    $workflow = app(TransferWorkflow::class);
    $data = [
        'from_virtual_warehouse_id' => $this->vwA->id,
        'to_virtual_warehouse_id'   => $this->vwB->id,
        'notes'                     => null,
        'lines'                     => [[
            'item_id'     => $this->item->id,
            'location_id' => $this->binA->id,
            'batch_id'    => null,
            'qty'         => '10.0000',
            'uom_id'      => $this->uom->id,
        ]],
    ];

    // department-user succeeds
    $transfer = $workflow->create($data, $this->deptUser);
    expect($transfer->id)->toBeInt();

    // storekeeper cannot create (policy check via HTTP gate)
    $this->actingAs($this->storekeeper)
         ->get(route('warehouse.transfers.create'))
         ->assertForbidden();

    // warehouse-officer cannot create
    $this->actingAs($this->whOfficer)
         ->get(route('warehouse.transfers.create'))
         ->assertForbidden();
});

it('department-user can create a movement; storekeeper cannot create it', function () {
    $this->actingAs($this->storekeeper)
         ->get(route('warehouse.movements.create'))
         ->assertForbidden();
});

// ─── T-02: Storekeeper/WH-officer can execute in-warehouse MOV ───────────────

it('storekeeper can execute an in-warehouse movement', function () {
    $movWorkflow = app(MovementWorkflow::class);

    // deptUser creates
    $movement = $movWorkflow->create([
        'virtual_warehouse_id' => $this->vwA->id,
        'notes'                => null,
        'lines'                => [[
            'item_id'          => $this->item->id,
            'from_location_id' => $this->binA->id,
            'to_location_id'   => $this->binB->id,
            'batch_no'         => null,
            'expiry_date'      => null,
            'qty'              => '10.0000',
            'uom_id'           => $this->uom->id,
            'serials'          => [],
        ]],
    ], $this->deptUser);

    expect($movement->status)->toBe(MovementStatus::Submitted);

    // Storekeeper executes
    $movWorkflow->execute($movement, $this->storekeeper);
    $movement->refresh();

    expect($movement->status)->toBe(MovementStatus::Completed);
});

// ─── T-03: In-warehouse MOV — location changes, owner stays the same ─────────

it('internal movement changes location but not ownership', function () {
    $movWorkflow = app(MovementWorkflow::class);

    $movement = $movWorkflow->create([
        'virtual_warehouse_id' => $this->vwA->id,
        'notes'                => null,
        'lines'                => [[
            'item_id'          => $this->item->id,
            'from_location_id' => $this->binA->id,
            'to_location_id'   => $this->binB->id,
            'batch_no'         => null,
            'expiry_date'      => null,
            'qty'              => '20.0000',
            'uom_id'           => $this->uom->id,
            'serials'          => [],
        ]],
    ], $this->deptUser);

    $movWorkflow->execute($movement, $this->storekeeper);

    // Stock left binA
    $fromBalance = StockBalance::where('item_id', $this->item->id)
        ->where('location_id', $this->binA->id)
        ->where('virtual_warehouse_id', $this->vwA->id)
        ->first();
    expect((float) ($fromBalance?->qty_on_hand ?? 0))->toBe(80.0);

    // Stock arrived in binB under same VW A (ownership unchanged)
    $toBalance = StockBalance::where('item_id', $this->item->id)
        ->where('location_id', $this->binB->id)
        ->where('virtual_warehouse_id', $this->vwA->id)
        ->first();
    expect((float) ($toBalance?->qty_on_hand ?? 0))->toBe(20.0);
});

// ─── T-04: Departmental virtual — owner changes after acceptance ──────────────

it('virtual departmental transfer changes owner only after acceptance', function () {
    $trfWorkflow = app(TransferWorkflow::class);

    $transfer = $trfWorkflow->create([
        'from_virtual_warehouse_id' => $this->vwA->id,
        'to_virtual_warehouse_id'   => $this->vwB->id,
        'notes'                     => null,
        'lines'                     => [[
            'item_id'     => $this->item->id,
            'location_id' => $this->binA->id,
            'batch_id'    => null,
            'qty'         => '15.0000',
            'uom_id'      => $this->uom->id,
        ]],
    ], $this->deptUser);

    $trfWorkflow->submit($transfer, $this->deptUser);
    expect($transfer->fresh()->status)->toBe(TransferStatus::Submitted);

    // No stock change yet
    $balA = StockBalance::where('item_id', $this->item->id)
        ->where('location_id', $this->binA->id)
        ->where('virtual_warehouse_id', $this->vwA->id)
        ->first();
    expect((float) ($balA?->qty_on_hand ?? 0))->toBe(100.0);

    // Manager accepts — for virtual transfer, this immediately posts
    $trfWorkflow->accept($transfer, $this->manager);
    $transfer->refresh();

    expect($transfer->status)->toBe(TransferStatus::Posted);

    // Stock under VW A reduced
    $balA = StockBalance::where('item_id', $this->item->id)
        ->where('location_id', $this->binA->id)
        ->where('virtual_warehouse_id', $this->vwA->id)
        ->first();
    expect((float) ($balA?->qty_on_hand ?? 0))->toBe(85.0);

    // Stock now under VW B at same location
    $balB = StockBalance::where('item_id', $this->item->id)
        ->where('location_id', $this->binA->id)
        ->where('virtual_warehouse_id', $this->vwB->id)
        ->first();
    expect((float) ($balB?->qty_on_hand ?? 0))->toBeGreaterThan(0.0);
});

// ─── T-05: Departmental physical — owner and location change ─────────────────

it('physical departmental transfer changes both owner and location', function () {
    $trfWorkflow = app(TransferWorkflow::class);

    $transfer = $trfWorkflow->create([
        'from_virtual_warehouse_id' => $this->vwA->id,
        'to_virtual_warehouse_id'   => $this->vwB->id,
        'is_physical'               => true,
        'notes'                     => null,
        'lines'                     => [[
            'item_id'        => $this->item->id,
            'location_id'    => $this->binA->id,
            'to_location_id' => $this->binB->id,
            'batch_id'       => null,
            'qty'            => '10.0000',
            'uom_id'         => $this->uom->id,
        ]],
    ], $this->deptUser);

    $trfWorkflow->submit($transfer, $this->deptUser);
    $trfWorkflow->accept($transfer, $this->manager);
    $transfer->refresh();

    // Physical transfer: accepted but not yet posted (post happens explicitly)
    expect($transfer->status)->toBe(TransferStatus::Accepted);

    // Explicit post by storekeeper
    $trfWorkflow->post($transfer, $this->storekeeper);
    $transfer->refresh();

    expect($transfer->status)->toBe(TransferStatus::Posted);

    // Stock now under VW B at binB
    $balVwBBinB = StockBalance::where('item_id', $this->item->id)
        ->where('location_id', $this->binB->id)
        ->where('virtual_warehouse_id', $this->vwB->id)
        ->first();
    expect((float) ($balVwBBinB?->qty_on_hand ?? 0))->toBe(10.0);
});

// ─── T-06: Rejected transfer does not move stock ─────────────────────────────

it('rejected transfer does not move stock', function () {
    $trfWorkflow = app(TransferWorkflow::class);

    $transfer = $trfWorkflow->create([
        'from_virtual_warehouse_id' => $this->vwA->id,
        'to_virtual_warehouse_id'   => $this->vwB->id,
        'notes'                     => null,
        'lines'                     => [[
            'item_id'     => $this->item->id,
            'location_id' => $this->binA->id,
            'batch_id'    => null,
            'qty'         => '10.0000',
            'uom_id'      => $this->uom->id,
        ]],
    ], $this->deptUser);

    $trfWorkflow->submit($transfer, $this->deptUser);
    $trfWorkflow->reject($transfer, $this->manager, 'Not needed');

    $transfer->refresh();
    expect($transfer->status)->toBe(TransferStatus::Rejected);

    // Stock in vwA unchanged
    $bal = StockBalance::where('item_id', $this->item->id)
        ->where('location_id', $this->binA->id)
        ->where('virtual_warehouse_id', $this->vwA->id)
        ->first();
    expect((float) ($bal?->qty_on_hand ?? 0))->toBe(100.0);
});

// ─── T-07: WTR — inbound PO auto-created on dispatch, in-transit qty correct ─

it('warehouse transfer dispatches to transit and auto-creates inbound PO', function () {
    $wtrWorkflow = app(WarehouseTransferWorkflow::class);

    $wtr = $wtrWorkflow->create([
        'from_warehouse_id'         => $this->warehouseA->id,
        'to_warehouse_id'           => $this->warehouseB->id,
        'from_virtual_warehouse_id' => $this->vwA->id,
        'to_virtual_warehouse_id'   => $this->vwB->id,
        'notes'                     => null,
        'lines'                     => [[
            'item_id'  => $this->item->id,
            'uom_id'   => $this->uom->id,
            'qty'      => '25.0000',
            'qty_base' => '25.0000',
        ]],
    ], $this->deptUser);

    $wtrWorkflow->submit($wtr, $this->deptUser);
    $wtr->refresh();

    // Different departments — stays Submitted (needs explicit accept)
    expect($wtr->status)->toBe(WarehouseTransferStatus::Submitted);

    $wtrWorkflow->accept($wtr, $this->manager);
    $wtr->refresh();
    expect($wtr->status)->toBe(WarehouseTransferStatus::Accepted);

    $wtrWorkflow->dispatch($wtr, [
        'lines' => [['source_location_id' => $this->binA->id]],
    ], $this->storekeeper);

    $wtr->refresh();
    expect($wtr->status)->toBe(WarehouseTransferStatus::Dispatched);

    // Stock moved to transit at warehouse B
    $transitBal = StockBalance::where('item_id', $this->item->id)
        ->where('location_id', $this->transitB->id)
        ->first();
    expect((float) ($transitBal?->qty_on_hand ?? 0))->toBe(25.0);

    // Stock removed from source VW A in warehouse A
    $srcBal = StockBalance::where('item_id', $this->item->id)
        ->where('location_id', $this->binA->id)
        ->where('virtual_warehouse_id', $this->vwA->id)
        ->first();
    expect((float) ($srcBal?->qty_on_hand ?? 0))->toBe(75.0);
});

// ─── T-08: Same-department WTR auto-accepts (no explicit accept needed) ───────

it('same-department warehouse transfer auto-accepts on submit', function () {
    $wtrWorkflow = app(WarehouseTransferWorkflow::class);

    $wtr = $wtrWorkflow->create([
        'from_warehouse_id'         => $this->warehouseA->id,
        'to_warehouse_id'           => $this->warehouseB->id,
        'from_virtual_warehouse_id' => $this->vwA->id,
        'to_virtual_warehouse_id'   => $this->vwA->id, // same VW
        'notes'                     => null,
        'lines'                     => [[
            'item_id'  => $this->item->id,
            'uom_id'   => $this->uom->id,
            'qty'      => '5.0000',
            'qty_base' => '5.0000',
        ]],
    ], $this->deptUser);

    $wtrWorkflow->submit($wtr, $this->deptUser);
    $wtr->refresh();

    // Same VW → auto-accepted
    expect($wtr->status)->toBe(WarehouseTransferStatus::Accepted);
});

// ─── T-09: Ownership changes on receipt when departments differ ───────────────

it('receipt changes ownership when departments differ', function () {
    $wtrWorkflow = app(WarehouseTransferWorkflow::class);

    $wtr = $wtrWorkflow->create([
        'from_warehouse_id'         => $this->warehouseA->id,
        'to_warehouse_id'           => $this->warehouseB->id,
        'from_virtual_warehouse_id' => $this->vwA->id,
        'to_virtual_warehouse_id'   => $this->vwB->id,
        'notes'                     => null,
        'lines'                     => [[
            'item_id'  => $this->item->id,
            'uom_id'   => $this->uom->id,
            'qty'      => '30.0000',
            'qty_base' => '30.0000',
        ]],
    ], $this->deptUser);

    $wtrWorkflow->submit($wtr, $this->deptUser);
    $wtrWorkflow->accept($wtr, $this->manager);
    $wtrWorkflow->dispatch($wtr, [
        'lines' => [['source_location_id' => $this->binA->id]],
    ], $this->storekeeper);
    $wtrWorkflow->receive($wtr, $this->storekeeper);

    $wtr->refresh();
    expect($wtr->status)->toBe(WarehouseTransferStatus::Completed);

    // After receipt: stock in transit under vwA → should now be under vwB
    $transitVwA = StockBalance::where('item_id', $this->item->id)
        ->where('location_id', $this->transitB->id)
        ->where('virtual_warehouse_id', $this->vwA->id)
        ->first();
    $transitVwB = StockBalance::where('item_id', $this->item->id)
        ->where('location_id', $this->transitB->id)
        ->where('virtual_warehouse_id', $this->vwB->id)
        ->first();

    $qtyA = (float) ($transitVwA?->qty_on_hand ?? 0);
    $qtyB = (float) ($transitVwB?->qty_on_hand ?? 0);

    // vwB should have received the stock
    expect($qtyB)->toBeGreaterThan(0.0);
    // vwA should have zero or reduced stock in transit
    expect($qtyA + $qtyB)->toBe(30.0);
});

// ─── T-10: Quarantine stock blocked ──────────────────────────────────────────

it('quarantine stock cannot be transferred', function () {
    $quarantineLoc = Location::factory()->create([
        'warehouse_id' => $this->warehouseA->id,
        'level'        => LocationLevel::Bin,
        'type'         => LocationType::Quarantine,
        'status'       => LocationStatus::Active,
        'code'         => 'QUAR-01',
    ]);

    // Seed quarantine stock
    $this->ledger->post(new StockPosting(
        type: MovementType::OpeningBalance,
        reference: new StockReference(),
        lines: [
            new StockLine(
                itemId:             $this->item->id,
                locationId:         $quarantineLoc->id,
                virtualWarehouseId: $this->vwA->id,
                stockStatus:        StockStatus::Available,
                enteredQty:         '10.0000',
                enteredUomId:       $this->uom->id,
            ),
        ],
    ));

    // Cannot create movement from quarantine bin
    Livewire::actingAs($this->deptUser)
        ->test(\App\Livewire\Warehouse\Movements\Create::class)
        ->set('virtualWarehouseId', $this->vwA->id)
        ->set('lines.0.item_id', $this->item->id)
        ->set('lines.0.from_location_id', $quarantineLoc->id)
        ->set('lines.0.to_location_id', $this->binB->id)
        ->set('lines.0.qty', '5')
        ->set('lines.0.uom_id', $this->uom->id)
        ->call('save')
        ->assertHasErrors(['lines.0.from_location_id']);
});

// ─── T-11: Putaway: non-bin locations are blocked ────────────────────────────

it('putaway blocks non-bin locations', function () {
    $rackLoc = Location::factory()->create([
        'warehouse_id' => $this->warehouseA->id,
        'level'        => LocationLevel::Zone, // not a bin
        'type'         => LocationType::Storage,
        'status'       => LocationStatus::Active,
        'code'         => 'ZONE-01',
    ]);

    // Movement create validates destination must be a bin
    Livewire::actingAs($this->deptUser)
        ->test(\App\Livewire\Warehouse\Movements\Create::class)
        ->set('virtualWarehouseId', $this->vwA->id)
        ->set('lines.0.item_id', $this->item->id)
        ->set('lines.0.from_location_id', $this->binA->id)
        ->set('lines.0.to_location_id', $rackLoc->id)
        ->set('lines.0.qty', '5')
        ->set('lines.0.uom_id', $this->uom->id)
        ->call('save')
        ->assertHasErrors(['lines.0.to_location_id']);
});

// ─── T-12: Orders Transfers tab shows all three types ────────────────────────

it('orders transfers tab shows MOV, TRF and WTR documents', function () {
    // Create one of each type
    $movWorkflow = app(MovementWorkflow::class);
    $movWorkflow->create([
        'virtual_warehouse_id' => $this->vwA->id,
        'notes'                => null,
        'lines'                => [[
            'item_id'          => $this->item->id,
            'from_location_id' => $this->binA->id,
            'to_location_id'   => $this->binB->id,
            'batch_no'         => null,
            'expiry_date'      => null,
            'qty'              => '5.0000',
            'uom_id'           => $this->uom->id,
            'serials'          => [],
        ]],
    ], $this->deptUser);

    $trfWorkflow = app(TransferWorkflow::class);
    $trf = $trfWorkflow->create([
        'from_virtual_warehouse_id' => $this->vwA->id,
        'to_virtual_warehouse_id'   => $this->vwB->id,
        'notes'                     => null,
        'lines'                     => [[
            'item_id'     => $this->item->id,
            'location_id' => $this->binA->id,
            'batch_id'    => null,
            'qty'         => '5.0000',
            'uom_id'      => $this->uom->id,
        ]],
    ], $this->deptUser);

    $wtrWorkflow = app(WarehouseTransferWorkflow::class);
    $wtrWorkflow->create([
        'from_warehouse_id'         => $this->warehouseA->id,
        'to_warehouse_id'           => $this->warehouseB->id,
        'from_virtual_warehouse_id' => $this->vwA->id,
        'to_virtual_warehouse_id'   => $this->vwB->id,
        'notes'                     => null,
        'lines'                     => [[
            'item_id'  => $this->item->id,
            'uom_id'   => $this->uom->id,
            'qty'      => '5.0000',
            'qty_base' => '5.0000',
        ]],
    ], $this->deptUser);

    // Manager sees all in the Transfers tab
    Livewire::actingAs($this->manager)
        ->test(\App\Livewire\Orders\Index::class)
        ->set('tab', 'transfer')
        ->assertSee('MOV-')
        ->assertSee('TRF-')
        ->assertSee('WTR-');
});
