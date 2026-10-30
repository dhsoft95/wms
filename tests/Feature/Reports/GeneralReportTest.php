<?php

declare(strict_types=1);

use App\Contracts\Reports\ReportDefinition;
use App\Enums\GrnStatus;
use App\Enums\Inventory\StockStatus;
use App\Models\Department;
use App\Models\Dispatch;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderLine;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\GrnReceipt;
use App\Models\Inventory\StockBalance;
use App\Models\Item;
use App\Models\Location;
use App\Models\Partner;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Reports\Activity\GeneralReport;
use App\Services\Reports\GeneralReportService;
use App\Services\Reports\ReportRegistry;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ── Shared fixtures ────────────────────────────────────────────────────────────

function generalFixtures(): array
{
    (new \Database\Seeders\RolePermissionSeeder())->run();
    (new \Database\Seeders\FoundationDefaultsSeeder())->run();

    $uom  = UnitOfMeasure::firstOrCreate(['code' => 'EA'], ['name' => 'Each', 'type' => 'count', 'is_active' => true]);
    $dept = Department::factory()->create(['name' => 'Engineering']);
    $wh   = Warehouse::factory()->create();
    $vw   = VirtualWarehouse::factory()->create(['warehouse_id' => $wh->id, 'department_id' => $dept->id]);
    $item = Item::factory()->create(['base_uom_id' => $uom->id, 'part_number' => 'P-001']);
    $loc  = Location::factory()->create(['warehouse_id' => $wh->id]);
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole('warehouse-manager');

    return compact('uom', 'dept', 'wh', 'vw', 'item', 'loc', 'user');
}

function makeGrn(array $fx, string $confirmedAt, ?string $status = null): Grn
{
    return Grn::factory()->create([
        'warehouse_id' => $fx['wh']->id,
        'status'       => $status ?? GrnStatus::Received->value,
        'confirmed_at' => $confirmedAt,
    ]);
}

function makeGrnLine(Grn $grn, array $fx, float $qty = 5.0): GrnLine
{
    return GrnLine::create([
        'grn_id'               => $grn->id,
        'item_id'              => $fx['item']->id,
        'uom_id'               => $fx['uom']->id,
        'virtual_warehouse_id' => $fx['vw']->id,
        'received_good_qty'    => $qty,
        'received_damaged_qty' => 0,
        'variance_qty'         => 0,
        'variance_type'        => 'none',
        'is_unexpected'        => false,
        'serials_missing'      => false,
        'serials_missing_qty'  => 0,
    ]);
}

function makeGrnReceipt(GrnLine $line, array $fx, string $condition = 'good', float $qty = 5.0): GrnReceipt
{
    return GrnReceipt::create([
        'grn_line_id' => $line->id,
        'qty'         => $qty,
        'uom_id'      => $fx['uom']->id,
        'condition'   => $condition,
        'received_by' => $fx['user']->id,
    ]);
}

function makeStockBalance(array $fx, float $qty, string $receivedAt): StockBalance
{
    $hash = hash('sha256', implode('|', [
        $fx['item']->id, '', '', $fx['loc']->id, $fx['vw']->id, StockStatus::Available->value,
    ]));
    return StockBalance::create([
        'item_id'              => $fx['item']->id,
        'location_id'          => $fx['loc']->id,
        'virtual_warehouse_id' => $fx['vw']->id,
        'stock_status'         => StockStatus::Available->value,
        'key_hash'             => $hash . '_' . uniqid(),
        'qty_on_hand'          => $qty,
        'qty_allocated'        => 0,
        'received_at'          => $receivedAt,
    ]);
}

// ── Registry ───────────────────────────────────────────────────────────────────

it('registry contains the general report', function () {
    (new \Database\Seeders\RolePermissionSeeder())->run();
    (new \Database\Seeders\FoundationDefaultsSeeder())->run();

    $definition = app(ReportRegistry::class)->find('general');

    expect($definition)->toBeInstanceOf(GeneralReport::class)
        ->and($definition)->toBeInstanceOf(ReportDefinition::class)
        ->and($definition->key())->toBe('general')
        ->and($definition->label())->toBe('General / Comprehensive Report')
        ->and($definition->group())->toBe('Activity')
        ->and($definition->periodType())->toBe('range')
        ->and($definition->pdfEnabled())->toBeFalse();
});

it('GeneralReport has writeExcel method for multi-sheet export', function () {
    expect(method_exists(new GeneralReport(), 'writeExcel'))->toBeTrue();
});

// ── Sheet 1 – Receipts: date range ────────────────────────────────────────────

it('receipts includes GRN lines whose confirmed_at is within the period', function () {
    $fx = generalFixtures();

    $grnIn  = makeGrn($fx, '2026-10-15 09:00:00');
    $lineIn = makeGrnLine($grnIn, $fx, 10.0);
    makeGrnReceipt($lineIn, $fx, 'good', 10.0);

    $grnOut  = makeGrn($fx, '2026-10-05 09:00:00'); // before range
    $lineOut = makeGrnLine($grnOut, $fx, 3.0);
    makeGrnReceipt($lineOut, $fx, 'good', 3.0);

    $svc    = app(GeneralReportService::class);
    $result = $svc->receipts(
        ['date_from' => '2026-10-10', 'date_to' => '2026-10-20'],
        $fx['user'],
    );

    expect($result)->toHaveCount(1)
        ->and($result->first()->grn_number)->toBe($grnIn->number)
        ->and($result->first()->quantity_received)->toBe(10.0);
});

it('receipts excludes cancelled GRNs', function () {
    $fx  = generalFixtures();
    $grn = makeGrn($fx, '2026-10-15 09:00:00', GrnStatus::Cancelled->value);
    makeGrnLine($grn, $fx);

    $result = app(GeneralReportService::class)->receipts(
        ['date_from' => '2026-10-10', 'date_to' => '2026-10-20'],
        $fx['user'],
    );

    expect($result)->toHaveCount(0);
});

// ── Sheet 1 – Receipts: department filter ────────────────────────────────────

it('receipts filters to the specified department', function () {
    $fx = generalFixtures();

    $dept2  = Department::factory()->create(['name' => 'Finance']);
    $wh2    = Warehouse::factory()->create();
    $vw2    = VirtualWarehouse::factory()->create(['warehouse_id' => $wh2->id, 'department_id' => $dept2->id]);
    $uom    = $fx['uom'];
    $item   = $fx['item'];

    // Line in dept1 (Engineering)
    $grn1  = makeGrn($fx, '2026-10-15 09:00:00');
    $line1 = makeGrnLine($grn1, $fx);
    makeGrnReceipt($line1, $fx);

    // Line in dept2 (Finance)
    $grn2  = Grn::factory()->create(['warehouse_id' => $wh2->id, 'status' => GrnStatus::Received->value, 'confirmed_at' => '2026-10-15 09:00:00']);
    $line2 = GrnLine::create([
        'grn_id' => $grn2->id, 'item_id' => $item->id, 'uom_id' => $uom->id,
        'virtual_warehouse_id' => $vw2->id, 'received_good_qty' => 7,
        'received_damaged_qty' => 0, 'variance_qty' => 0, 'variance_type' => 'none',
        'is_unexpected' => false, 'serials_missing' => false, 'serials_missing_qty' => 0,
    ]);
    makeGrnReceipt($line2, $fx);

    $filters = ['date_from' => '2026-10-10', 'date_to' => '2026-10-20', 'department_id' => $fx['dept']->id];
    $result  = app(GeneralReportService::class)->receipts($filters, $fx['user']);

    expect($result)->toHaveCount(1)
        ->and($result->first()->department)->toBe('Engineering');
});

// ── Sheet 1 – Receipts: conformity ───────────────────────────────────────────

it('receipts marks conformity as Damaged when any receipt is damaged', function () {
    $fx  = generalFixtures();
    $grn = makeGrn($fx, '2026-10-15 09:00:00');
    $ln  = makeGrnLine($grn, $fx, 4.0);
    makeGrnReceipt($ln, $fx, 'good', 2.0);
    makeGrnReceipt($ln, $fx, 'damaged', 2.0);

    $result = app(GeneralReportService::class)->receipts(
        ['date_from' => '2026-10-10', 'date_to' => '2026-10-20'],
        $fx['user'],
    );

    expect($result->first()->conformity)->toBe('Damaged');
});

// ── Sheet 1 – Receipts: totals ────────────────────────────────────────────────

it('receipts total_value equals quantity × single_value', function () {
    $fx  = generalFixtures();
    $po  = \App\Models\PurchaseOrder::factory()->create(['warehouse_id' => $fx['wh']->id]);
    $pol = \App\Models\PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'item_id'           => $fx['item']->id,
        'uom_id'            => $fx['uom']->id,
        'unit_price'        => 25.00,
        'line_total'        => 250.00,
    ]);
    $grn  = makeGrn($fx, '2026-10-15 09:00:00');
    $grn->update(['purchase_order_id' => $po->id]);
    $line = GrnLine::create([
        'grn_id' => $grn->id, 'item_id' => $fx['item']->id, 'uom_id' => $fx['uom']->id,
        'virtual_warehouse_id' => $fx['vw']->id, 'received_good_qty' => 4,
        'received_damaged_qty' => 0, 'variance_qty' => 0, 'variance_type' => 'none',
        'is_unexpected' => false, 'serials_missing' => false, 'serials_missing_qty' => 0,
        'purchase_order_line_id' => $pol->id,
    ]);
    makeGrnReceipt($line, $fx, 'good', 4.0);

    $result = app(GeneralReportService::class)->receipts(
        ['date_from' => '2026-10-10', 'date_to' => '2026-10-20'],
        $fx['user'],
    );

    expect($result->first()->single_value)->toBe(25.0)
        ->and($result->first()->total_value)->toBe(100.0);
});

// ── Sheet 2 – Despatches: date range ─────────────────────────────────────────

it('despatches includes lines whose dispatch date is within the period', function () {
    $fx = generalFixtures();

    $do     = DeliveryOrder::factory()->create([
        'virtual_warehouse_id' => $fx['vw']->id,
        'warehouse_id'         => $fx['wh']->id,
        'status'               => \App\Enums\Outbound\DeliveryOrderStatus::Dispatched->value,
        'requested_by'         => $fx['user']->id,
    ]);
    $dol = DeliveryOrderLine::factory()->create([
        'delivery_order_id' => $do->id,
        'item_id'           => $fx['item']->id,
        'uom_id'            => $fx['uom']->id,
        'requested_qty'     => 3,
        'dispatched_qty'    => 3,
        'status'            => \App\Enums\Outbound\DoLineStatus::Dispatched->value,
    ]);
    Dispatch::create([
        'number'              => 'DSP-TEST-001',
        'delivery_order_id'   => $do->id,
        'vehicle_reg_no'      => 'TZA-001',
        'driver_name'         => 'Driver A',
        'dispatched_at'       => '2026-10-16 08:00:00',
        'dispatched_by'       => $fx['user']->id,
    ]);

    $result = app(GeneralReportService::class)->despatches(
        ['date_from' => '2026-10-15', 'date_to' => '2026-10-20'],
        $fx['user'],
    );

    expect($result)->toHaveCount(1)
        ->and($result->first()->order_number)->toBe($do->number);
});

it('despatches excludes lines whose dispatch date is outside the period', function () {
    $fx = generalFixtures();

    $do  = DeliveryOrder::factory()->create([
        'virtual_warehouse_id' => $fx['vw']->id,
        'warehouse_id'         => $fx['wh']->id,
        'status'               => \App\Enums\Outbound\DeliveryOrderStatus::Dispatched->value,
        'requested_by'         => $fx['user']->id,
    ]);
    DeliveryOrderLine::factory()->create([
        'delivery_order_id' => $do->id,
        'item_id'           => $fx['item']->id,
        'uom_id'            => $fx['uom']->id,
        'requested_qty'     => 2,
        'dispatched_qty'    => 2,
        'status'            => \App\Enums\Outbound\DoLineStatus::Dispatched->value,
    ]);
    Dispatch::create([
        'number'            => 'DSP-TEST-002',
        'delivery_order_id' => $do->id,
        'vehicle_reg_no'    => 'TZA-002',
        'driver_name'       => 'Driver B',
        'dispatched_at'     => '2026-10-25 08:00:00', // outside range
        'dispatched_by'     => $fx['user']->id,
    ]);

    $result = app(GeneralReportService::class)->despatches(
        ['date_from' => '2026-10-15', 'date_to' => '2026-10-20'],
        $fx['user'],
    );

    expect($result)->toHaveCount(0);
});

// ── Sheet 2 – Despatches: department filter ───────────────────────────────────

it('despatches filters to the specified department', function () {
    $fx = generalFixtures();

    $dept2 = Department::factory()->create(['name' => 'Sales']);
    $wh2   = Warehouse::factory()->create();
    $vw2   = VirtualWarehouse::factory()->create(['warehouse_id' => $wh2->id, 'department_id' => $dept2->id]);

    // DO in Engineering
    $do1 = DeliveryOrder::factory()->create([
        'virtual_warehouse_id' => $fx['vw']->id,
        'warehouse_id'         => $fx['wh']->id,
        'department_id'        => $fx['dept']->id,
        'status'               => \App\Enums\Outbound\DeliveryOrderStatus::Dispatched->value,
        'requested_by'         => $fx['user']->id,
    ]);
    $dol1 = DeliveryOrderLine::factory()->create([
        'delivery_order_id' => $do1->id, 'item_id' => $fx['item']->id, 'uom_id' => $fx['uom']->id,
        'requested_qty' => 5, 'dispatched_qty' => 5, 'status' => \App\Enums\Outbound\DoLineStatus::Dispatched->value,
    ]);
    Dispatch::create(['number' => 'DSP-TEST-003', 'delivery_order_id' => $do1->id, 'vehicle_reg_no' => 'TZA-003', 'driver_name' => 'D', 'dispatched_at' => '2026-10-16 08:00:00', 'dispatched_by' => $fx['user']->id]);

    // DO in Sales
    $do2 = DeliveryOrder::factory()->create([
        'virtual_warehouse_id' => $vw2->id,
        'warehouse_id'         => $wh2->id,
        'department_id'        => $dept2->id,
        'status'               => \App\Enums\Outbound\DeliveryOrderStatus::Dispatched->value,
        'requested_by'         => $fx['user']->id,
    ]);
    $dol2 = DeliveryOrderLine::factory()->create([
        'delivery_order_id' => $do2->id, 'item_id' => $fx['item']->id, 'uom_id' => $fx['uom']->id,
        'requested_qty' => 2, 'dispatched_qty' => 2, 'status' => \App\Enums\Outbound\DoLineStatus::Dispatched->value,
    ]);
    Dispatch::create(['number' => 'DSP-TEST-004', 'delivery_order_id' => $do2->id, 'vehicle_reg_no' => 'TZA-004', 'driver_name' => 'E', 'dispatched_at' => '2026-10-16 08:00:00', 'dispatched_by' => $fx['user']->id]);

    $result = app(GeneralReportService::class)->despatches(
        ['date_from' => '2026-10-15', 'date_to' => '2026-10-20', 'department_id' => $fx['dept']->id],
        $fx['user'],
    );

    expect($result)->toHaveCount(1)
        ->and($result->first()->order_number)->toBe($do1->number);
});

// ── Sheet 3 – Stock Ageing: stay_in_days ─────────────────────────────────────

it('stockAgeing calculates stay_in_days as date_to minus received_at', function () {
    $fx = generalFixtures();
    makeStockBalance($fx, 10.0, '2026-10-10 00:00:00');

    $result = app(GeneralReportService::class)->stockAgeing(
        ['date_to' => '2026-10-20'],
        $fx['user'],
    );

    expect($result)->toHaveCount(1)
        ->and($result->first()->stay_in_days)->toBe(10)
        ->and($result->first()->quantity)->toBe(10.0);
});

it('stockAgeing sorts by stay_in_days descending', function () {
    $fx = generalFixtures();

    $item2 = Item::factory()->create(['base_uom_id' => $fx['uom']->id]);
    $loc2  = Location::factory()->create(['warehouse_id' => $fx['wh']->id]);
    $hashA = hash('sha256', 'balA_' . uniqid());
    $hashB = hash('sha256', 'balB_' . uniqid());

    StockBalance::create([
        'item_id' => $fx['item']->id, 'location_id' => $fx['loc']->id,
        'virtual_warehouse_id' => $fx['vw']->id, 'stock_status' => StockStatus::Available->value,
        'key_hash' => $hashA, 'qty_on_hand' => 5, 'qty_allocated' => 0,
        'received_at' => '2026-10-18 00:00:00', // 2 days
    ]);
    StockBalance::create([
        'item_id' => $item2->id, 'location_id' => $loc2->id,
        'virtual_warehouse_id' => $fx['vw']->id, 'stock_status' => StockStatus::Available->value,
        'key_hash' => $hashB, 'qty_on_hand' => 3, 'qty_allocated' => 0,
        'received_at' => '2026-10-05 00:00:00', // 15 days
    ]);

    $result = app(GeneralReportService::class)->stockAgeing(
        ['date_to' => '2026-10-20'],
        $fx['user'],
    );

    expect($result->first()->stay_in_days)->toBeGreaterThan($result->last()->stay_in_days);
});

// ── Sheet 3 – Stock Ageing: date_to filter ───────────────────────────────────

it('stockAgeing excludes balances whose received_at is after date_to', function () {
    $fx = generalFixtures();
    makeStockBalance($fx, 8.0, '2026-10-25 00:00:00'); // after date_to

    $result = app(GeneralReportService::class)->stockAgeing(
        ['date_to' => '2026-10-20'],
        $fx['user'],
    );

    expect($result)->toHaveCount(0);
});

// ── Sheet 3 – Stock Ageing: totals ────────────────────────────────────────────

it('stockAgeing stock_value is quantity × single_value', function () {
    $fx  = generalFixtures();
    $po  = \App\Models\PurchaseOrder::factory()->create(['warehouse_id' => $fx['wh']->id]);
    $pol = \App\Models\PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'item_id'           => $fx['item']->id,
        'uom_id'            => $fx['uom']->id,
        'unit_price'        => 10.00,
        'line_total'        => 50.00,
    ]);
    makeStockBalance($fx, 5.0, '2026-10-10 00:00:00');

    // Create a GRN + GrnLine so buildGrnLookup can find the PO line
    $grn  = makeGrn($fx, '2026-10-10 00:00:00');
    $grn->update(['purchase_order_id' => $po->id]);
    $line = GrnLine::create([
        'grn_id' => $grn->id, 'item_id' => $fx['item']->id, 'uom_id' => $fx['uom']->id,
        'virtual_warehouse_id' => $fx['vw']->id, 'received_good_qty' => 5,
        'received_damaged_qty' => 0, 'variance_qty' => 0, 'variance_type' => 'none',
        'is_unexpected' => false, 'serials_missing' => false, 'serials_missing_qty' => 0,
        'purchase_order_line_id' => $pol->id,
    ]);
    makeGrnReceipt($line, $fx, 'good', 5.0);

    // Create stock movement for GRN lookup to work
    $st = \App\Models\Inventory\StockTransaction::create([
        'number'          => 'ST-GR-TEST-' . uniqid(),
        'type'            => \App\Enums\Inventory\MovementType::Receipt->value,
        'reference_type'  => 'grn',
        'reference_id'    => $grn->id,
        'reference_no'    => $grn->number,
        'posted_by'       => $fx['user']->id,
        'posted_at'       => now(),
    ]);
    \App\Models\Inventory\StockMovement::create([
        'stock_transaction_id' => $st->id,
        'item_id'              => $fx['item']->id,
        'location_id'          => $fx['loc']->id,
        'virtual_warehouse_id' => $fx['vw']->id,
        'stock_status'         => StockStatus::Available->value,
        'qty'                  => 5,
        'entered_qty'          => 5,
        'entered_uom_id'       => $fx['uom']->id,
    ]);

    $result = app(GeneralReportService::class)->stockAgeing(
        ['date_to' => '2026-10-20'],
        $fx['user'],
    );

    expect($result)->toHaveCount(1)
        ->and($result->first()->single_value)->toBe(10.0)
        ->and($result->first()->stock_value)->toBe(50.0);
});

// ── Scheduler: can run 'general' type ────────────────────────────────────────

it('GenerateReportJob can resolve and handle the general report type via registry', function () {
    (new \Database\Seeders\RolePermissionSeeder())->run();
    (new \Database\Seeders\FoundationDefaultsSeeder())->run();

    $registry = app(ReportRegistry::class);
    $def      = $registry->find('general');

    expect($def)->not->toBeNull()
        ->and($def->key())->toBe('general')
        ->and(method_exists($def, 'writeExcel'))->toBeTrue();
});
