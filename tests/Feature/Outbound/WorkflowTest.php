<?php

declare(strict_types=1);

use App\Enums\Outbound\AllocationRule;
use App\Enums\Outbound\AllocationStatus;
use App\Enums\Outbound\DeliveryOrderPriority;
use App\Enums\Outbound\DeliveryOrderStatus;
use App\Enums\Outbound\DoLineStatus;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderAllocation;
use App\Models\DeliveryOrderApproval;
use App\Models\DeliveryOrderLine;
use App\Models\Department;
use App\Models\DocumentSequence;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockReservation;
use App\Models\Item;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\Foundation\SettingsService;
use App\Services\Outbound\DeliveryOrderWorkflow;
use Database\Seeders\FoundationDefaultsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Cache;

// ─── setUp ────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(FoundationDefaultsSeeder::class);

    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    $this->admin = User::factory()->create(['status' => 'active']);
    $this->admin->assignRole('system-admin');
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

function wfWarehouse(): Warehouse
{
    $wh = Warehouse::firstOrCreate(
        ['code' => 'WF-WH'],
        ['name' => 'Workflow Test Warehouse', 'is_active' => true]
    );

    // Ensure a staging location exists for cancel-allocation tests
    if ($wh->default_staging_location_id === null) {
        $staging = Location::firstOrCreate(
            ['code' => 'WF-STAGING'],
            [
                'warehouse_id' => $wh->id,
                'segment_code' => 'S',
                'level'        => \App\Enums\LocationLevel::Bin,
                'type'         => \App\Enums\LocationType::Staging,
                'status'       => \App\Enums\LocationStatus::Active,
                'is_pickable'  => false,
                'path'         => '/',
            ]
        );
        $wh->update(['default_staging_location_id' => $staging->id]);
        $wh->refresh();
    }

    return $wh;
}

function wfVw(Warehouse $wh): VirtualWarehouse
{
    $dept = Department::firstOrCreate(
        ['code' => 'WF-DEPT'],
        ['name' => 'Workflow Dept', 'is_active' => true]
    );

    return VirtualWarehouse::firstOrCreate(
        ['code' => 'WF-VW'],
        [
            'name'          => 'Workflow VW',
            'department_id' => $dept->id,
            'is_active'     => true,
        ]
    );
}

function wfItem(): Item
{
    $uom = UnitOfMeasure::firstOrCreate(
        ['code' => 'PCS'],
        ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
    );

    return Item::create([
        'sku'          => 'WF-' . uniqid(),
        'name'         => 'Workflow Test Item',
        'base_uom_id'  => $uom->id,
        'is_active'    => true,
        'track_batch'  => false,
        'track_expiry' => false,
        'track_serial' => false,
    ]);
}

function makeDo(Warehouse $wh, VirtualWarehouse $vw, User $user, DeliveryOrderStatus $status = DeliveryOrderStatus::Draft): DeliveryOrder
{
    return DeliveryOrder::create([
        'number'               => 'DO-WF-' . uniqid(),
        'warehouse_id'         => $wh->id,
        'virtual_warehouse_id' => $vw->id,
        'department_id'        => $vw->department_id,
        'status'               => $status,
        'priority'             => DeliveryOrderPriority::Normal,
        'allocation_rule'      => AllocationRule::Default,
        'requested_by'         => $user->id,
        'required_date'        => now()->addDays(3)->toDateString(),
    ]);
}

// ─── Test 1: Draft → Submitted ────────────────────────────────────────────────

test('test_draft_order_can_be_submitted', function () {
    // approval_mode = none so submit immediately auto-approves; we want to stay at submitted
    \App\Models\Setting::updateOrCreate(
        ['key' => 'outbound.approval_mode'],
        ['value' => 'department_head', 'type' => 'string']
    );
    Cache::flush();

    $wh   = wfWarehouse();
    $vw   = wfVw($wh);
    $do   = makeDo($wh, $vw, $this->admin, DeliveryOrderStatus::Draft);
    $item = wfItem();

    DeliveryOrderLine::create([
        'delivery_order_id'  => $do->id,
        'line_no'            => 1,
        'item_id'            => $item->id,
        'uom_id'             => $item->base_uom_id,
        'requested_qty'      => '10.0000',
        'requested_qty_base' => '10.0000',
        'status'             => DoLineStatus::Pending,
    ]);

    $workflow = app(DeliveryOrderWorkflow::class);
    $workflow->submit($do, $this->admin);

    $do->refresh();

    expect($do->status)->toBe(DeliveryOrderStatus::Submitted);
});

// ─── Test 2: Submitted → Approved ─────────────────────────────────────────────

test('test_submitted_order_transitions_to_approved', function () {
    $wh = wfWarehouse();
    $vw = wfVw($wh);
    $do = makeDo($wh, $vw, $this->admin, DeliveryOrderStatus::Submitted);

    // Create a pending approval record (level 1)
    DeliveryOrderApproval::create([
        'delivery_order_id' => $do->id,
        'level'             => 1,
        'approver_role'     => 'department_head',
    ]);

    $workflow = app(DeliveryOrderWorkflow::class);
    $workflow->approve($do, $this->admin, 'department_head', 'Looks good');

    $do->refresh();

    expect($do->status)->toBe(DeliveryOrderStatus::Approved);
});

// ─── Test 3: Submitted → Rejected ─────────────────────────────────────────────

test('test_submitted_order_can_be_rejected', function () {
    $wh = wfWarehouse();
    $vw = wfVw($wh);
    $do = makeDo($wh, $vw, $this->admin, DeliveryOrderStatus::Submitted);

    DeliveryOrderApproval::create([
        'delivery_order_id' => $do->id,
        'level'             => 1,
        'approver_role'     => 'department_head',
    ]);

    $rejectNotes = 'Budget not approved.';
    $workflow    = app(DeliveryOrderWorkflow::class);
    $workflow->reject($do, $this->admin, $rejectNotes);

    $do->refresh();

    // Workflow sets status back to Draft on rejection (per service implementation)
    expect($do->status)->toBe(DeliveryOrderStatus::Draft);

    $approval = DeliveryOrderApproval::where('delivery_order_id', $do->id)->first();
    expect($approval->decision)->toBe('rejected')
        ->and($approval->notes)->toBe($rejectNotes);
});

// ─── Test 4: Reject from Draft throws DomainException ─────────────────────────

test('test_reject_from_draft_throws_domain_exception', function () {
    $wh = wfWarehouse();
    $vw = wfVw($wh);
    $do = makeDo($wh, $vw, $this->admin, DeliveryOrderStatus::Draft);

    $workflow = app(DeliveryOrderWorkflow::class);

    expect(fn () => $workflow->reject($do, $this->admin, 'Should fail'))
        ->toThrow(\DomainException::class);
});

// ─── Test 5: Cancel Draft ─────────────────────────────────────────────────────

test('test_cancel_draft_order_works', function () {
    $wh = wfWarehouse();
    $vw = wfVw($wh);
    $do = makeDo($wh, $vw, $this->admin, DeliveryOrderStatus::Draft);

    $workflow = app(DeliveryOrderWorkflow::class);
    $workflow->cancel($do, $this->admin, 'No longer needed');

    $do->refresh();

    expect($do->status)->toBe(DeliveryOrderStatus::Cancelled);
});

// ─── Test 6: Cancel Allocated Order Releases Reservations ─────────────────────

test('test_cancel_allocated_order_releases_reservations', function () {
    $wh   = wfWarehouse();
    $vw   = wfVw($wh);
    $item = wfItem();
    $do   = makeDo($wh, $vw, $this->admin, DeliveryOrderStatus::Allocated);

    // Create a storage location in the same warehouse
    $binLoc = Location::create([
        'warehouse_id' => $wh->id,
        'code'         => 'WF-BIN-' . uniqid(),
        'segment_code' => 'B',
        'level'        => \App\Enums\LocationLevel::Bin,
        'type'         => \App\Enums\LocationType::Storage,
        'status'       => \App\Enums\LocationStatus::Active,
        'is_pickable'  => true,
        'path'         => '/',
    ]);

    $line = DeliveryOrderLine::create([
        'delivery_order_id'  => $do->id,
        'line_no'            => 1,
        'item_id'            => $item->id,
        'uom_id'             => $item->base_uom_id,
        'requested_qty'      => '5.0000',
        'requested_qty_base' => '5.0000',
        'allocated_qty'      => '5.0000',
        'status'             => DoLineStatus::Allocated,
    ]);

    // Create a stock balance record
    $stockKey = new \App\Data\Inventory\StockKey(
        itemId: $item->id,
        batchId: null,
        lpnId: null,
        locationId: $binLoc->id,
        virtualWarehouseId: $vw->id,
        stockStatus: \App\Enums\Inventory\StockStatus::Available,
    );
    $balance = StockBalance::create([
        'item_id'              => $item->id,
        'location_id'          => $binLoc->id,
        'virtual_warehouse_id' => $vw->id,
        'stock_status'         => \App\Enums\Inventory\StockStatus::Available,
        'key_hash'             => $stockKey->hash(),
        'qty_on_hand'          => '10.0000',
        'qty_allocated'        => '5.0000',
    ]);

    // Create a reservation
    $reservation = StockReservation::create([
        'stock_balance_id'  => $balance->id,
        'item_id'           => $item->id,
        'location_id'       => $binLoc->id,
        'qty'               => '5.0000',
        'reference_type'    => 'delivery_order_line',
        'reference_id'      => $line->id,
        'status'            => \App\Enums\Inventory\ReservationStatus::Active,
    ]);

    // Create allocation linking to the reservation
    DeliveryOrderAllocation::create([
        'delivery_order_line_id' => $line->id,
        'stock_balance_id'       => $balance->id,
        'stock_reservation_id'   => $reservation->id,
        'item_id'                => $item->id,
        'location_id'            => $binLoc->id,
        'qty'                    => '5.0000',
        'status'                 => AllocationStatus::Reserved,
    ]);

    $workflow = app(DeliveryOrderWorkflow::class);
    $workflow->cancel($do, $this->admin, 'Cancelled by test');

    $do->refresh();
    $reservation->refresh();
    $balance->refresh();

    expect($do->status)->toBe(DeliveryOrderStatus::Cancelled)
        ->and($reservation->status)->toBe(\App\Enums\Inventory\ReservationStatus::Released)
        ->and((float) $balance->qty_allocated)->toBe(0.0);
});
