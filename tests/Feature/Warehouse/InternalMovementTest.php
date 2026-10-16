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
use App\Models\DocumentSequence;
use App\Models\InternalMovement;
use App\Models\Inventory\Batch;
use App\Models\Inventory\StockBalance;
use App\Models\Item;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedger;
use App\Services\Warehouse\MovementWorkflow;
use Database\Seeders\FoundationDefaultsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

// ─── setUp ────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(FoundationDefaultsSeeder::class);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    $this->manager = User::factory()->create(['status' => 'active']);
    $this->manager->assignRole('warehouse-manager');

    $this->storekeeper = User::factory()->create(['status' => 'active']);
    $this->storekeeper->assignRole('storekeeper');

    // Warehouse + VW
    $this->warehouse = Warehouse::factory()->create();
    $this->vw        = VirtualWarehouse::factory()->create(['is_active' => true]);

    // UoM
    $this->uom = UnitOfMeasure::factory()->create(['code' => 'PCS', 'name' => 'Pieces']);

    // Bins
    $this->binA = Location::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'level'        => LocationLevel::Bin,
        'type'         => LocationType::Storage,
        'status'       => LocationStatus::Active,
        'code'         => 'BIN-A',
    ]);

    $this->binB = Location::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'level'        => LocationLevel::Bin,
        'type'         => LocationType::Storage,
        'status'       => LocationStatus::Active,
        'code'         => 'BIN-B',
    ]);

    // Item
    $this->item = Item::factory()->create([
        'is_active'    => true,
        'track_batch'  => false,
        'track_serial' => false,
        'track_expiry' => false,
        'base_uom_id'  => $this->uom->id,
    ]);

    // Seed stock into binA
    $ledger = app(StockLedger::class);
    $ledger->post(new StockPosting(
        type: MovementType::OpeningBalance,
        reference: new StockReference(),
        lines: [
            new StockLine(
                itemId: $this->item->id,
                locationId: $this->binA->id,
                virtualWarehouseId: $this->vw->id,
                stockStatus: StockStatus::Available,
                enteredQty: '100.0000',
                enteredUomId: $this->uom->id,
            ),
        ],
    ));
});

// ─── Helper ───────────────────────────────────────────────────────────────────

function getBalance(int $itemId, int $locationId, int $vwId, ?int $batchId = null): ?StockBalance
{
    $key = new \App\Data\Inventory\StockKey(
        itemId: $itemId,
        batchId: $batchId,
        lpnId: null,
        locationId: $locationId,
        virtualWarehouseId: $vwId,
        stockStatus: StockStatus::Available,
    );

    return StockBalance::where('key_hash', $key->hash())->first();
}

// ─── Rule 1: Create → post moves stock (from decremented, to incremented) ────

it('posts movement and updates both balances', function () {
    $workflow = app(MovementWorkflow::class);

    $movement = $workflow->create([
        'virtual_warehouse_id' => $this->vw->id,
        'notes'                => null,
        'lines'                => [[
            'item_id'          => $this->item->id,
            'from_location_id' => $this->binA->id,
            'to_location_id'   => $this->binB->id,
            'batch_no'         => '',
            'expiry_date'      => '',
            'qty'              => '10.0000',
            'uom_id'           => $this->uom->id,
            'serials'          => [],
        ]],
    ], $this->manager);

    $workflow->post($movement, $this->manager);

    $fromBalance = getBalance($this->item->id, $this->binA->id, $this->vw->id);
    $toBalance   = getBalance($this->item->id, $this->binB->id, $this->vw->id);

    expect((float) $fromBalance?->qty_on_hand)->toBe(90.0)
        ->and((float) $toBalance?->qty_on_hand)->toBe(10.0);
});

// ─── Rule 2: Idempotent — double-post no-ops, no double-move ─────────────────

it('is idempotent — double-post does not move stock twice', function () {
    $workflow = app(MovementWorkflow::class);

    $movement = $workflow->create([
        'virtual_warehouse_id' => $this->vw->id,
        'notes'                => null,
        'lines'                => [[
            'item_id'          => $this->item->id,
            'from_location_id' => $this->binA->id,
            'to_location_id'   => $this->binB->id,
            'batch_no'         => '',
            'expiry_date'      => '',
            'qty'              => '10.0000',
            'uom_id'           => $this->uom->id,
            'serials'          => [],
        ]],
    ], $this->manager);

    $workflow->post($movement, $this->manager);

    expect(fn () => $workflow->post($movement->fresh(), $this->manager))
        ->toThrow(\DomainException::class, 'already been posted');
});

// ─── Rule 3: From = To rejected ───────────────────────────────────────────────

it('rejects movement where source and destination are the same bin', function () {
    $workflow = app(MovementWorkflow::class);

    $movement = InternalMovement::create([
        'number'               => 'MOV-TEST-001',
        'virtual_warehouse_id' => $this->vw->id,
        'status'               => MovementStatus::Draft,
        'created_by'           => $this->manager->id,
    ]);

    $movement->lines()->create([
        'item_id'          => $this->item->id,
        'from_location_id' => $this->binA->id,
        'to_location_id'   => $this->binA->id,
        'qty'              => '5.0000',
        'uom_id'           => $this->uom->id,
    ]);

    expect(fn () => $workflow->post($movement, $this->manager))
        ->toThrow(\DomainException::class);
});

// ─── Rule 4: Non-bin / blocked / quarantine location rejected ─────────────────

it('rejects a blocked source location', function () {
    $this->binA->update(['status' => LocationStatus::Blocked]);

    $workflow  = app(MovementWorkflow::class);
    $movement  = InternalMovement::create([
        'number'               => 'MOV-TEST-002',
        'virtual_warehouse_id' => $this->vw->id,
        'status'               => MovementStatus::Draft,
        'created_by'           => $this->manager->id,
    ]);

    $movement->lines()->create([
        'item_id'          => $this->item->id,
        'from_location_id' => $this->binA->id,
        'to_location_id'   => $this->binB->id,
        'qty'              => '5.0000',
        'uom_id'           => $this->uom->id,
    ]);

    expect(fn () => $workflow->post($movement, $this->manager))
        ->toThrow(\DomainException::class, 'not active');
});

it('rejects a quarantine destination location', function () {
    $qBin = Location::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'level'        => LocationLevel::Bin,
        'type'         => LocationType::Quarantine,
        'status'       => LocationStatus::Active,
        'code'         => 'BIN-Q',
    ]);

    $workflow = app(MovementWorkflow::class);
    $movement = InternalMovement::create([
        'number'               => 'MOV-TEST-003',
        'virtual_warehouse_id' => $this->vw->id,
        'status'               => MovementStatus::Draft,
        'created_by'           => $this->manager->id,
    ]);

    $movement->lines()->create([
        'item_id'          => $this->item->id,
        'from_location_id' => $this->binA->id,
        'to_location_id'   => $qBin->id,
        'qty'              => '5.0000',
        'uom_id'           => $this->uom->id,
    ]);

    expect(fn () => $workflow->post($movement, $this->manager))
        ->toThrow(\DomainException::class, 'quarantine');
});

// ─── Rule 5: Qty > available rejected ────────────────────────────────────────

it('rejects qty that exceeds available stock', function () {
    $workflow = app(MovementWorkflow::class);
    $movement = InternalMovement::create([
        'number'               => 'MOV-TEST-004',
        'virtual_warehouse_id' => $this->vw->id,
        'status'               => MovementStatus::Draft,
        'created_by'           => $this->manager->id,
    ]);

    $movement->lines()->create([
        'item_id'          => $this->item->id,
        'from_location_id' => $this->binA->id,
        'to_location_id'   => $this->binB->id,
        'qty'              => '999.0000',
        'uom_id'           => $this->uom->id,
    ]);

    expect(fn () => $workflow->post($movement, $this->manager))
        ->toThrow(\DomainException::class, 'Insufficient stock');
});

// ─── Rule 6: Serial-tracked — missing serials rejected ───────────────────────

it('rejects serial-tracked movement with no serials provided', function () {
    $serialItem = Item::factory()->create([
        'is_active'    => true,
        'track_serial' => true,
        'track_batch'  => false,
        'base_uom_id'  => $this->uom->id,
    ]);

    // Seed serial stock
    $ledger = app(StockLedger::class);
    $ledger->post(new StockPosting(
        type: MovementType::OpeningBalance,
        reference: new StockReference(),
        lines: [
            new StockLine(
                itemId: $serialItem->id,
                locationId: $this->binA->id,
                virtualWarehouseId: $this->vw->id,
                stockStatus: StockStatus::Available,
                enteredQty: '2.0000',
                enteredUomId: $this->uom->id,
                serials: ['SN-001', 'SN-002'],
            ),
        ],
    ));

    $workflow = app(MovementWorkflow::class);
    $movement = InternalMovement::create([
        'number'               => 'MOV-TEST-005',
        'virtual_warehouse_id' => $this->vw->id,
        'status'               => MovementStatus::Draft,
        'created_by'           => $this->manager->id,
    ]);

    $movement->lines()->create([
        'item_id'          => $serialItem->id,
        'from_location_id' => $this->binA->id,
        'to_location_id'   => $this->binB->id,
        'qty'              => '1.0000',
        'uom_id'           => $this->uom->id,
    ]);

    expect(fn () => $workflow->post($movement, $this->manager))
        ->toThrow(\InvalidArgumentException::class);
});

// ─── Rule 7: Serial not in from-bin rejected ─────────────────────────────────

it('rejects serial not present in the source bin', function () {
    $serialItem = Item::factory()->create([
        'is_active'    => true,
        'track_serial' => true,
        'track_batch'  => false,
        'base_uom_id'  => $this->uom->id,
    ]);

    $ledger = app(StockLedger::class);
    $ledger->post(new StockPosting(
        type: MovementType::OpeningBalance,
        reference: new StockReference(),
        lines: [
            new StockLine(
                itemId: $serialItem->id,
                locationId: $this->binA->id,
                virtualWarehouseId: $this->vw->id,
                stockStatus: StockStatus::Available,
                enteredQty: '1.0000',
                enteredUomId: $this->uom->id,
                serials: ['SN-REAL'],
            ),
        ],
    ));

    $workflow = app(MovementWorkflow::class);
    $movement = InternalMovement::create([
        'number'               => 'MOV-TEST-006',
        'virtual_warehouse_id' => $this->vw->id,
        'status'               => MovementStatus::Draft,
        'created_by'           => $this->manager->id,
    ]);

    $line = $movement->lines()->create([
        'item_id'          => $serialItem->id,
        'from_location_id' => $this->binA->id,
        'to_location_id'   => $this->binB->id,
        'qty'              => '1.0000',
        'uom_id'           => $this->uom->id,
    ]);

    $line->serials()->create(['serial_number' => 'SN-GHOST']);

    expect(fn () => $workflow->post($movement, $this->manager))
        ->toThrow(\InvalidArgumentException::class, 'not available');
});

// ─── Rule 8: StockKey (batch_no) passed correctly to ledger ──────────────────

it('resolves batch_no to batch_id and posts correctly', function () {
    $batchItem = Item::factory()->create([
        'is_active'   => true,
        'track_batch' => true,
        'base_uom_id' => $this->uom->id,
    ]);

    $batch = Batch::create([
        'item_id'  => $batchItem->id,
        'batch_no' => 'LOT-ABC',
    ]);

    // Seed batch stock
    $ledger = app(StockLedger::class);
    $ledger->post(new StockPosting(
        type: MovementType::OpeningBalance,
        reference: new StockReference(),
        lines: [
            new StockLine(
                itemId: $batchItem->id,
                locationId: $this->binA->id,
                virtualWarehouseId: $this->vw->id,
                stockStatus: StockStatus::Available,
                enteredQty: '50.0000',
                enteredUomId: $this->uom->id,
                batchId: $batch->id,
            ),
        ],
    ));

    $workflow = app(MovementWorkflow::class);

    $movement = $workflow->create([
        'virtual_warehouse_id' => $this->vw->id,
        'notes'                => null,
        'lines'                => [[
            'item_id'          => $batchItem->id,
            'from_location_id' => $this->binA->id,
            'to_location_id'   => $this->binB->id,
            'batch_no'         => 'LOT-ABC',
            'expiry_date'      => '',
            'qty'              => '20.0000',
            'uom_id'           => $this->uom->id,
            'serials'          => [],
        ]],
    ], $this->manager);

    $workflow->post($movement, $this->manager);

    $key = new \App\Data\Inventory\StockKey(
        itemId: $batchItem->id,
        batchId: $batch->id,
        lpnId: null,
        locationId: $this->binB->id,
        virtualWarehouseId: $this->vw->id,
        stockStatus: StockStatus::Available,
    );

    $toBalance = StockBalance::where('key_hash', $key->hash())->first();
    expect((float) $toBalance?->qty_on_hand)->toBe(20.0);
});

// ─── Index renders correctly ──────────────────────────────────────────────────

it('renders movements index with all column headers', function () {
    Livewire::actingAs($this->manager)
        ->test(\App\Livewire\Warehouse\Movements\Index::class)
        ->assertSee('Number')
        ->assertSee('Virtual Warehouse')
        ->assertSee('Lines')
        ->assertSee('Created By')
        ->assertSee('Status')
        ->assertSee('Date');
});
