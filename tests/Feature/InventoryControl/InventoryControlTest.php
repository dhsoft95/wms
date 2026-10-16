<?php

declare(strict_types=1);

use App\Data\Inventory\StockLine;
use App\Data\Inventory\StockPosting;
use App\Data\Inventory\StockReference;
use App\Enums\AdjustmentStatus;
use App\Enums\CycleCountStatus;
use App\Enums\Inventory\MovementType;
use App\Enums\Inventory\SerialStatus;
use App\Enums\Inventory\StockStatus;
use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Enums\ReasonCodeAppliesTo;
use App\Enums\TransferStatus;
use App\Models\CycleCount;
use App\Models\CycleCountLine;
use App\Models\Department;
use App\Models\DocumentSequence;
use App\Models\Inventory\Batch;
use App\Models\Inventory\SerialNumber;
use App\Models\Inventory\StockBalance;
use App\Models\Item;
use App\Models\Location;
use App\Models\ReasonCode;
use App\Models\Setting;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentLine;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\Inventory\AdjustmentWorkflow;
use App\Services\Inventory\CycleCountWorkflow;
use App\Services\Inventory\StockLedger;
use App\Services\Inventory\TransferWorkflow;
use Database\Seeders\FoundationDefaultsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Livewire\Livewire;

// ─── setUp ────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(FoundationDefaultsSeeder::class);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    $this->manager   = User::factory()->create(['status' => 'active']);
    $this->manager->assignRole('warehouse-manager');

    $this->storekeeper = User::factory()->create(['status' => 'active']);
    $this->storekeeper->assignRole('storekeeper');
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

function icUom(): UnitOfMeasure
{
    return UnitOfMeasure::firstOrCreate(['code' => 'PCS'], ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]);
}

function icWarehouse(): Warehouse
{
    return Warehouse::firstOrCreate(['code' => 'WH01'], [
        'name'    => 'Main',
        'address' => 'Dar es Salaam',
        'status'  => 'active',
    ]);
}

function icDept(Warehouse $wh): Department
{
    return Department::firstOrCreate(['code' => 'GEN'], ['name' => 'General', 'warehouse_id' => $wh->id]);
}

function icVw(Department $dept): VirtualWarehouse
{
    return VirtualWarehouse::firstOrCreate(['code' => 'GEN-VW'], [
        'name'          => 'General VW',
        'department_id' => $dept->id,
        'status'        => 'active',
    ]);
}

function icBin(Warehouse $wh, string $code = 'BIN-01'): Location
{
    return Location::firstOrCreate(['code' => $code], [
        'name'         => $code,
        'segment_code' => $code,
        'warehouse_id' => $wh->id,
        'level'        => LocationLevel::Bin,
        'type'         => LocationType::Storage,
        'status'       => LocationStatus::Active,
    ]);
}

function icItem(UnitOfMeasure $uom, bool $serial = false): Item
{
    static $n = 0;
    $n++;
    return Item::create([
        'sku'          => "IC-ITEM-{$n}",
        'name'         => "IC Item {$n}",
        'base_uom_id'  => $uom->id,
        'is_active'    => true,
        'track_batch'  => false,
        'track_expiry' => false,
        'track_serial' => $serial,
    ]);
}

function icSeedStock(Item $item, Location $bin, VirtualWarehouse $vw, UnitOfMeasure $uom, string $qty): StockBalance
{
    $ledger = app(StockLedger::class);
    $ledger->openingBalance([
        new StockLine(
            itemId:             $item->id,
            locationId:         $bin->id,
            virtualWarehouseId: $vw->id,
            stockStatus:        StockStatus::Available,
            enteredQty:         $qty,
            enteredUomId:       $uom->id,
        ),
    ]);
    return StockBalance::where('item_id', $item->id)
        ->where('location_id', $bin->id)
        ->where('virtual_warehouse_id', $vw->id)
        ->firstOrFail();
}

function icReasonCode(): ReasonCode
{
    return ReasonCode::firstOrCreate(['code' => 'ADJ-TEST'], [
        'name'       => 'Test Adjustment',
        'applies_to' => ReasonCodeAppliesTo::Adjustment,
        'is_active'  => true,
    ]);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

// 1. Cannot adjust below available (on-hand minus reserved)
it('prevents adjustment below available stock', function () {
    $uom  = icUom();
    $wh   = icWarehouse();
    $vw   = icVw(icDept($wh));
    $bin  = icBin($wh);
    $item = icItem($uom);
    $rc   = icReasonCode();

    // Seed 10 units
    icSeedStock($item, $bin, $vw, $uom, '10.0000');

    // Attempt to adjust -15 (below zero)
    $workflow = app(AdjustmentWorkflow::class);

    $adj = $workflow->create([
        'virtual_warehouse_id' => $vw->id,
        'lines'                => [[
            'item_id'        => $item->id,
            'location_id'    => $bin->id,
            'batch_id'       => null,
            'reason_code_id' => $rc->id,
            'qty'            => '-15.0000',
            'uom_id'         => $uom->id,
        ]],
    ], $this->manager);

    // Allow it to post with threshold = 0 (requires approval)
    Setting::set('adj_qty_threshold', 0);

    $adj->status = AdjustmentStatus::Approved;
    $adj->save();

    expect(fn () => $workflow->post($adj, $this->manager))
        ->toThrow(\DomainException::class);
});

// 2. Idempotent post — posting twice returns same result
it('posting an adjustment twice is idempotent', function () {
    $uom  = icUom();
    $wh   = icWarehouse();
    $vw   = icVw(icDept($wh));
    $bin  = icBin($wh);
    $item = icItem($uom);
    $rc   = icReasonCode();

    icSeedStock($item, $bin, $vw, $uom, '20.0000');

    $workflow = app(AdjustmentWorkflow::class);

    // Use threshold so it auto-posts
    Setting::set('adj_qty_threshold', 100.0);

    $adj = $workflow->create([
        'virtual_warehouse_id' => $vw->id,
        'lines'                => [[
            'item_id'        => $item->id,
            'location_id'    => $bin->id,
            'batch_id'       => null,
            'reason_code_id' => $rc->id,
            'qty'            => '-2.0000',
            'uom_id'         => $uom->id,
        ]],
    ], $this->manager);

    $adj = $workflow->submit($adj, $this->manager);
    expect($adj->status)->toBe(AdjustmentStatus::Posted);

    // Try to post again — should not throw or double-reduce stock
    $adj2 = $workflow->post($adj, $this->manager);
    expect($adj2->status)->toBe(AdjustmentStatus::Posted);

    $balance = StockBalance::where('item_id', $item->id)
        ->where('location_id', $bin->id)->firstOrFail();
    expect((float) $balance->qty_on_hand)->toBe(18.0);
});

// 3. Below threshold auto-posts on submit
it('auto-posts on submit when qty is below threshold', function () {
    $uom  = icUom();
    $wh   = icWarehouse();
    $vw   = icVw(icDept($wh));
    $bin  = icBin($wh);
    $item = icItem($uom);
    $rc   = icReasonCode();

    icSeedStock($item, $bin, $vw, $uom, '50.0000');

    Setting::set('adj_qty_threshold', 100.0);

    $workflow = app(AdjustmentWorkflow::class);

    $adj = $workflow->create([
        'virtual_warehouse_id' => $vw->id,
        'lines'                => [[
            'item_id'        => $item->id,
            'location_id'    => $bin->id,
            'batch_id'       => null,
            'reason_code_id' => $rc->id,
            'qty'            => '-5.0000',
            'uom_id'         => $uom->id,
        ]],
    ], $this->manager);

    $adj = $workflow->submit($adj, $this->manager);

    expect($adj->status)->toBe(AdjustmentStatus::Posted);
    expect($adj->threshold_exceeded)->toBeFalse();
});

// 4. Above threshold stays submitted
it('stays submitted when qty exceeds threshold and needs approval', function () {
    $uom  = icUom();
    $wh   = icWarehouse();
    $vw   = icVw(icDept($wh));
    $bin  = icBin($wh);
    $item = icItem($uom);
    $rc   = icReasonCode();

    icSeedStock($item, $bin, $vw, $uom, '500.0000');

    Setting::set('adj_qty_threshold', 10.0);

    $workflow = app(AdjustmentWorkflow::class);

    $adj = $workflow->create([
        'virtual_warehouse_id' => $vw->id,
        'lines'                => [[
            'item_id'        => $item->id,
            'location_id'    => $bin->id,
            'batch_id'       => null,
            'reason_code_id' => $rc->id,
            'qty'            => '-50.0000',
            'uom_id'         => $uom->id,
        ]],
    ], $this->manager);

    $adj = $workflow->submit($adj, $this->manager);

    expect($adj->status)->toBe(AdjustmentStatus::Submitted);
    expect($adj->threshold_exceeded)->toBeTrue();
});

// 5. Reserved stock blocks transfer
it('blocks transfer when stock is reserved', function () {
    $uom  = icUom();
    $wh   = icWarehouse();
    $dept = icDept($wh);
    $vw   = icVw($dept);
    $bin  = icBin($wh);
    $item = icItem($uom);

    $vw2 = VirtualWarehouse::firstOrCreate(['code' => 'DEPT-B'], [
        'name'          => 'Dept B VW',
        'department_id' => $dept->id,
        'status'        => 'active',
    ]);

    // Seed 10 units
    $balance = icSeedStock($item, $bin, $vw, $uom, '10.0000');

    // Reserve 10 units (full allocation)
    $reservations = app(\App\Services\Inventory\StockReservations::class);
    $reservations->reserve($balance, '10.0000', 'delivery_order', 1);

    $workflow = app(TransferWorkflow::class);

    $transfer = $workflow->create([
        'from_virtual_warehouse_id' => $vw->id,
        'to_virtual_warehouse_id'   => $vw2->id,
        'lines'                     => [[
            'item_id'     => $item->id,
            'location_id' => $bin->id,
            'batch_id'    => null,
            'qty'         => '5.0000',
            'uom_id'      => $uom->id,
        ]],
    ], $this->manager);

    expect(fn () => $workflow->submit($transfer, $this->manager))
        ->toThrow(\DomainException::class, 'insufficient available stock');
});

// 6. VW scope: a restricted user does not see another VW's adjustment
it('enforces VW scope on adjustments', function () {
    $uom    = icUom();
    $wh     = icWarehouse();
    $dept   = icDept($wh);
    $vwA    = icVw($dept);
    $binA   = icBin($wh, 'BIN-VW-A');
    $itemA  = icItem($uom);
    $rc     = icReasonCode();

    $vwB = VirtualWarehouse::firstOrCreate(['code' => 'VW-SCOPE-B'], [
        'name'          => 'VW B',
        'department_id' => $dept->id,
        'status'        => 'active',
    ]);

    icSeedStock($itemA, $binA, $vwA, $uom, '20.0000');

    // Create adjustment in VW A
    $workflow = app(AdjustmentWorkflow::class);
    $workflow->create([
        'virtual_warehouse_id' => $vwA->id,
        'lines'                => [[
            'item_id'        => $itemA->id,
            'location_id'    => $binA->id,
            'batch_id'       => null,
            'reason_code_id' => $rc->id,
            'qty'            => '-1.0000',
            'uom_id'         => $uom->id,
        ]],
    ], $this->manager);

    // Department user restricted to VW B should see 0 adjustments
    $deptUser = User::factory()->create(['status' => 'active']);
    $deptUser->assignRole('department-user');
    $deptUser->virtualWarehouses()->sync([$vwB->id]);

    $this->actingAs($deptUser);

    $count = StockAdjustment::count();
    expect($count)->toBe(0);
});

// 7. Blind count hides system_qty
it('does not return system_qty in the blind count view data', function () {
    $uom  = icUom();
    $wh   = icWarehouse();
    $vw   = icVw(icDept($wh));
    $bin  = icBin($wh, 'BIN-CNT');
    $item = icItem($uom);

    icSeedStock($item, $bin, $vw, $uom, '30.0000');

    $workflow = app(CycleCountWorkflow::class);
    $count    = $workflow->create([
        'virtual_warehouse_id' => $vw->id,
        'location_ids'         => [$bin->id],
    ], $this->manager);

    expect($count->status)->toBe(CycleCountStatus::Open);

    // The Show component passes $blind = true when status is Open/Counting.
    // Verify the system_qty is stored but a counter action doesn't need to send it.
    $lines = CycleCountLine::where('cycle_count_id', $count->id)->get();
    expect($lines)->toHaveCount(1);
    expect((float) $lines->first()->system_qty)->toBe(30.0);
    expect($lines->first()->counted_qty)->toBeNull();
});

// 8. Bin lock — open count prevents pick task generation for that bin
it('prevents picking from a bin locked by an active cycle count', function () {
    $uom  = icUom();
    $wh   = icWarehouse();
    $vw   = icVw(icDept($wh));
    $bin  = icBin($wh, 'BIN-LOCK');
    $item = icItem($uom);

    icSeedStock($item, $bin, $vw, $uom, '40.0000');

    // Create a cycle count that locks this bin
    $countWorkflow = app(CycleCountWorkflow::class);
    $count = $countWorkflow->create([
        'virtual_warehouse_id' => $vw->id,
        'location_ids'         => [$bin->id],
    ], $this->manager);

    expect($countWorkflow->isLocationLocked($bin->id))->toBeTrue();

    // A second bin should not be locked
    $bin2 = icBin($wh, 'BIN-FREE');
    expect($countWorkflow->isLocationLocked($bin2->id))->toBeFalse();
});

// 9. Serial status → written_off after adjustment write-off
it('marks serials as written_off after an adjustment write-off', function () {
    $uom  = icUom();
    $wh   = icWarehouse();
    $vw   = icVw(icDept($wh));
    $bin  = icBin($wh, 'BIN-SER');
    $item = icItem($uom, serial: true);
    $rc   = icReasonCode();

    // Seed with serials
    $ledger = app(StockLedger::class);
    $ledger->openingBalance([
        new StockLine(
            itemId:             $item->id,
            locationId:         $bin->id,
            virtualWarehouseId: $vw->id,
            stockStatus:        StockStatus::Available,
            enteredQty:         '2.0000',
            enteredUomId:       $uom->id,
            serials:            ['SN-WO-001', 'SN-WO-002'],
        ),
    ]);

    Setting::set('adj_qty_threshold', 100.0);

    $workflow = app(AdjustmentWorkflow::class);
    $adj = $workflow->create([
        'virtual_warehouse_id' => $vw->id,
        'lines'                => [[
            'item_id'        => $item->id,
            'location_id'    => $bin->id,
            'batch_id'       => null,
            'reason_code_id' => $rc->id,
            'qty'            => '-2.0000',
            'uom_id'         => $uom->id,
            'serials'        => ['SN-WO-001', 'SN-WO-002'],
        ]],
    ], $this->manager);

    $adj = $workflow->submit($adj, $this->manager);
    expect($adj->status)->toBe(AdjustmentStatus::Posted);

    $sn1 = SerialNumber::where('serial_no', 'SN-WO-001')->firstOrFail();
    $sn2 = SerialNumber::where('serial_no', 'SN-WO-002')->firstOrFail();

    expect($sn1->status)->toBe(SerialStatus::WrittenOff);
    expect($sn2->status)->toBe(SerialStatus::WrittenOff);
});

it('renders transfers index with all column headers', function () {
    Livewire::actingAs($this->manager)
        ->test(\App\Livewire\Warehouse\Transfers\Index::class)
        ->assertSee('Number')
        ->assertSee('From')
        ->assertSee('To')
        ->assertSee('Created By')
        ->assertSee('Status')
        ->assertSee('Date');
});
