<?php

declare(strict_types=1);

use App\Enums\Inventory\MovementType;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockTransaction;
use App\Models\Item;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Reports\Activity\StockMovementReport;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\RolePermissionSeeder())->run();
    (new \Database\Seeders\FoundationDefaultsSeeder())->run();
});

// ── Helpers ────────────────────────────────────────────────────────────────────

function movementFixtures(): array
{
    $wh   = Warehouse::factory()->create(['is_active' => true]);
    $vw   = VirtualWarehouse::factory()->create(['warehouse_id' => $wh->id, 'is_active' => true]);
    $uom  = UnitOfMeasure::firstOrCreate(['code' => 'EA'], ['name' => 'Each', 'type' => 'count', 'is_active' => true]);
    $item = Item::factory()->create(['base_uom_id' => $uom->id]);
    $loc  = Location::factory()->create(['warehouse_id' => $wh->id]);
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole('warehouse-manager'); // canAccessAllVirtualWarehouses

    return compact('wh', 'vw', 'uom', 'item', 'loc', 'user');
}

function makeTx(string $type, string $referenceType = 'grn', int $referenceId = 1, string $referenceNo = 'REF-001', ?string $postedAt = null): StockTransaction
{
    return StockTransaction::create([
        'number'         => 'STK-TEST-' . uniqid(),
        'type'           => $type,
        'reference_type' => $referenceType,
        'reference_id'   => $referenceId,
        'reference_no'   => $referenceNo,
        'posted_by'      => 1,
        'posted_at'      => $postedAt ?? now()->toDateTimeString(),
        'created_at'     => $postedAt ?? now()->toDateTimeString(),
    ]);
}

function makeMovement(StockTransaction $tx, array $fixtures, string $qty): StockMovement
{
    return StockMovement::create([
        'stock_transaction_id' => $tx->id,
        'item_id'              => $fixtures['item']->id,
        'location_id'          => $fixtures['loc']->id,
        'virtual_warehouse_id' => $fixtures['vw']->id,
        'stock_status'         => \App\Enums\Inventory\StockStatus::Available->value,
        'qty'                  => $qty,
        'entered_qty'          => $qty,
        'entered_uom_id'       => $fixtures['uom']->id,
    ]);
}

// ── 1. display_type label ──────────────────────────────────────────────────────

it('shows Putaway for relocation rows with putaway_task reference', function () {
    $f   = movementFixtures();
    $tx  = makeTx(MovementType::Relocation->value, 'putaway_task', 1, 'PT-1');
    makeMovement($tx, $f, '5.0000');

    $report = new StockMovementReport();
    $rows   = $report->query(['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()], $f['user'])->get();

    expect($rows->first()->display_type)->toBe('Putaway');
});

it('shows Internal Transfer for relocation rows with non-putaway reference', function () {
    $f   = movementFixtures();
    $tx  = makeTx(MovementType::Relocation->value, 'stock_transfer', 1, 'STN-001');
    makeMovement($tx, $f, '3.0000');

    $report = new StockMovementReport();
    $rows   = $report->query(['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()], $f['user'])->get();

    expect($rows->first()->display_type)->toBe('Internal Transfer');
});

it('shows correct labels for receipt and dispatch types', function () {
    $f      = movementFixtures();
    $txIn   = makeTx(MovementType::Receipt->value,  'grn',             1, 'GRN-001');
    $txOut  = makeTx(MovementType::Dispatch->value, 'delivery_order',  2, 'DO-001');

    makeMovement($txIn,  $f, '10.0000');
    makeMovement($txOut, $f, '-6.0000');

    $report = new StockMovementReport();
    $rows   = $report->query(['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()], $f['user'])->get();

    $types = $rows->pluck('display_type')->sort()->values();
    expect($types)->toContain('Receipt')
                  ->toContain('Dispatch');
});

// ── 2. Summary cards ───────────────────────────────────────────────────────────

it('summary Received equals sum of receipt-type qty', function () {
    $f     = movementFixtures();
    $txIn  = makeTx(MovementType::Receipt->value, 'grn', 1, 'GRN-001');
    makeMovement($txIn, $f, '10.0000');

    $report  = new StockMovementReport();
    $rows    = $report->query(['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()], $f['user'])->get();
    $summary = $report->summary($rows);

    expect($summary['Received'])->toBe('10.00');
});

it('summary Dispatched equals absolute sum of dispatch-type qty', function () {
    $f      = movementFixtures();
    $txOut  = makeTx(MovementType::Dispatch->value, 'delivery_order', 1, 'DO-001');
    makeMovement($txOut, $f, '-7.0000');

    $report  = new StockMovementReport();
    $rows    = $report->query(['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()], $f['user'])->get();
    $summary = $report->summary($rows);

    expect($summary['Dispatched'])->toBe('7.00');
});

it('summary Net Change equals totals row qty', function () {
    $f      = movementFixtures();
    $txIn   = makeTx(MovementType::Receipt->value,  'grn',            1, 'GRN-001');
    $txOut  = makeTx(MovementType::Dispatch->value, 'delivery_order', 2, 'DO-001');
    makeMovement($txIn,  $f, '10.0000');
    makeMovement($txOut, $f, '-4.0000');

    $report  = new StockMovementReport();
    $rows    = $report->query(['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()], $f['user'])->get();
    $summary = $report->summary($rows);
    $totals  = $report->totals($rows);

    expect($summary['Net Change'])->toBe('6.00')
        ->and((float) $totals['qty'])->toBe(6.0);
});

// ── 3. Date range filter ───────────────────────────────────────────────────────

it('excludes movements outside the date range', function () {
    $f      = movementFixtures();
    $txOld  = makeTx(MovementType::Receipt->value, 'grn', 1, 'GRN-OLD', '2020-01-15 10:00:00');
    $txNew  = makeTx(MovementType::Receipt->value, 'grn', 2, 'GRN-NEW', now()->toDateTimeString());
    makeMovement($txOld, $f, '99.0000');
    makeMovement($txNew, $f, '5.0000');

    $report = new StockMovementReport();
    $rows   = $report->query(['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()], $f['user'])->get();

    expect($rows)->toHaveCount(1)
        ->and((float) $rows->first()->qty)->toBe(5.0);
});

// ── 4. Warehouse filter ────────────────────────────────────────────────────────

it('warehouse_id filter returns only that warehouse movements', function () {
    $f1 = movementFixtures();
    $f2 = movementFixtures(); // separate warehouse

    $tx1 = makeTx(MovementType::Receipt->value, 'grn', 1, 'GRN-W1');
    makeMovement($tx1, $f1, '8.0000');

    $tx2 = makeTx(MovementType::Receipt->value, 'grn', 2, 'GRN-W2');
    makeMovement($tx2, $f2, '3.0000');

    $report = new StockMovementReport();
    $rows   = $report->query([
        'date_from'    => now()->toDateString(),
        'date_to'      => now()->toDateString(),
        'warehouse_id' => $f1['vw']->id,
    ], $f1['user'])->get();

    expect($rows)->toHaveCount(1)
        ->and((float) $rows->first()->qty)->toBe(8.0);
});
