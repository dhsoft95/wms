<?php

declare(strict_types=1);

use App\Enums\Inbound\PurchaseOrderApprovalAction;
use App\Enums\Inbound\PurchaseOrderStatus;
use App\Models\ApprovalChain;
use App\Models\ApprovalChainUser;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemUom;
use App\Models\Location;
use App\Models\Partner;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderApproval;
use App\Models\PurchaseOrderLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\Inbound\PurchaseOrderWorkflow;
use Database\Seeders\FoundationDefaultsSeeder;
use Database\Seeders\RolePermissionSeeder;

// ─── Setup ────────────────────────────────────────────────────────────────────

beforeEach(function () {
    (new RolePermissionSeeder())->run();
    (new FoundationDefaultsSeeder())->run();
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeAuditScenario(): array
{
    $warehouse = Warehouse::factory()->create();
    Location::factory()->create(['warehouse_id' => $warehouse->id, 'type' => 'receiving_dock', 'status' => 'active', 'level' => 'bin', 'code' => 'DOCK-AT']);

    $uom  = UnitOfMeasure::firstOrCreate(['code' => 'EA'], ['name' => 'Each', 'type' => 'count', 'is_active' => true]);
    $item = Item::factory()->create(['is_active' => true, 'base_uom_id' => $uom->id, 'track_batch' => false]);
    ItemUom::firstOrCreate(
        ['item_id' => $item->id, 'uom_id' => $uom->id],
        ['factor_to_base' => 1, 'is_default_purchase' => true, 'is_default_issue' => true],
    );

    $hod    = User::factory()->create();
    $hod->assignRole('department-head');

    $dept   = Department::factory()->create(['head_user_id' => $hod->id]);
    $chain  = ApprovalChain::create(['department_id' => $dept->id, 'document_type' => 'po', 'level' => 1]);
    ApprovalChainUser::create(['approval_chain_id' => $chain->id, 'user_id' => $hod->id]);

    $vw      = VirtualWarehouse::factory()->create(['warehouse_id' => $warehouse->id]);
    $creator = User::factory()->create();
    $creator->assignRole('department-user');

    $workflow = app(PurchaseOrderWorkflow::class);
    $po = $workflow->create([
        'warehouse_id'         => $warehouse->id,
        'virtual_warehouse_id' => $vw->id,
        'department_id'        => $dept->id,
        'source'               => 'manual',
        'package_type'         => 'loose_cargo',
        'lines'                => [[
            'item_id'    => $item->id,
            'uom_id'     => $uom->id,
            'qty'        => 5,
            'qty_base'   => 5,
            'unit_price' => '100.0000',
        ]],
    ], $creator);

    $wm = User::factory()->create();
    $wm->assignRole('warehouse-manager');

    return compact('workflow', 'po', 'creator', 'hod', 'dept', 'chain', 'wm', 'warehouse');
}

// ─── Tests ────────────────────────────────────────────────────────────────────

test('approving once produces exactly one approval record', function () {
    ['workflow' => $workflow, 'po' => $po, 'creator' => $creator, 'hod' => $hod] = makeAuditScenario();

    $workflow->submit($po, $creator);
    $workflow->approveAtLevel($po, $hod);

    $approvals = PurchaseOrderApproval::where('purchase_order_id', $po->id)
        ->where('action', PurchaseOrderApprovalAction::Approved->value)
        ->get();

    expect($approvals)->toHaveCount(1);
    expect($approvals->first()->user_id)->toBe($hod->id);
    expect($approvals->first()->level)->toBe(1);
})->group('AuditTrail');

test('approved record stores the chain id so the rich label is available', function () {
    ['workflow' => $workflow, 'po' => $po, 'creator' => $creator, 'hod' => $hod, 'chain' => $chain] = makeAuditScenario();

    $workflow->submit($po, $creator);
    $workflow->approveAtLevel($po, $hod);

    $approval = PurchaseOrderApproval::where('purchase_order_id', $po->id)
        ->where('action', PurchaseOrderApprovalAction::Approved->value)
        ->first();

    expect($approval->approval_chain_id)->toBe($chain->id);
})->group('AuditTrail');

test('send back then resubmit then approve again yields two separate approval entries', function () {
    ['workflow' => $workflow, 'po' => $po, 'creator' => $creator, 'hod' => $hod, 'wm' => $wm] = makeAuditScenario();

    // Round 1: submit → approve → warehouse sends back → HOD forwards → requester resubmits
    $workflow->submit($po, $creator);
    $workflow->approveAtLevel($po, $hod);

    // Warehouse manager sends it back for correction
    $workflow->rejectByWarehouse($po->fresh(), $wm, 'Missing attachments');
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::ReturnedForCorrection);

    // HOD forwards to requester
    $workflow->forwardReturnToRequester($po->fresh(), $hod, 'Please fix and resubmit');
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::AwaitingRequesterCorrection);

    // Requester resubmits — approved record from round 1 is deleted; forwarded record is preserved
    $workflow->submit($po->fresh(), $creator);
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::PendingApproval);

    $approvedAfterReset = PurchaseOrderApproval::where('purchase_order_id', $po->id)
        ->where('action', PurchaseOrderApprovalAction::Approved->value)
        ->count();
    expect($approvedAfterReset)->toBe(0, 'approved records should be cleared on resubmit');

    // Round 2: approve again
    $workflow->approveAtLevel($po->fresh(), $hod);

    // There should be exactly 1 approved record (round 2 only; round 1 was reset)
    $totalApproved = PurchaseOrderApproval::where('purchase_order_id', $po->id)
        ->where('action', PurchaseOrderApprovalAction::Approved->value)
        ->count();
    expect($totalApproved)->toBe(1);

    // The forwarded record from round 1 must still be present (audit history preserved)
    $forwardedCount = PurchaseOrderApproval::where('purchase_order_id', $po->id)
        ->where('action', PurchaseOrderApprovalAction::Forwarded->value)
        ->count();
    expect($forwardedCount)->toBe(1, 'forwarded record must survive resubmit for the audit trail');
})->group('AuditTrail');

test('forwardReturnToRequester creates a forwarded record, not a returned record', function () {
    ['workflow' => $workflow, 'po' => $po, 'creator' => $creator, 'hod' => $hod, 'wm' => $wm] = makeAuditScenario();

    $workflow->submit($po, $creator);
    $workflow->approveAtLevel($po, $hod);
    $workflow->rejectByWarehouse($po->fresh(), $wm, 'Missing docs');
    $workflow->forwardReturnToRequester($po->fresh(), $hod, 'Please correct and resubmit');

    expect(
        PurchaseOrderApproval::where('purchase_order_id', $po->id)
            ->where('action', PurchaseOrderApprovalAction::Forwarded->value)
            ->count()
    )->toBe(1);

    // Must NOT have written a duplicate 'returned' record for the forward action
    // (the only returned record should be from the warehouse rejectByWarehouse — but
    // rejectByWarehouse doesn't write to purchase_order_approvals, so count = 0)
    expect(
        PurchaseOrderApproval::where('purchase_order_id', $po->id)
            ->where('action', PurchaseOrderApprovalAction::Returned->value)
            ->count()
    )->toBe(0);
})->group('AuditTrail');

test('forwardReturnToRequester does not write a duplicate activity_log entry', function () {
    ['workflow' => $workflow, 'po' => $po, 'creator' => $creator, 'hod' => $hod, 'wm' => $wm] = makeAuditScenario();

    $workflow->submit($po, $creator);
    $workflow->approveAtLevel($po, $hod);
    $workflow->rejectByWarehouse($po->fresh(), $wm, 'Missing docs');

    $activityCountBefore = $po->activities()->count();
    $workflow->forwardReturnToRequester($po->fresh(), $hod, 'Please fix');
    $activityCountAfter  = $po->activities()->count();

    // Status update triggers a Spatie 'updated' write, but no explicit log() call should fire.
    // The only change must be the Spatie auto-log (description = 'updated'), not a named event.
    $forbiddenEntry = $po->activities()
        ->where('description', 'Return forwarded to requester by dept head')
        ->exists();

    expect($forbiddenEntry)->toBeFalse('forwardReturnToRequester must not write a named activity_log entry');
})->group('AuditTrail');

test('approveAtLevel does not write a duplicate activity_log entry', function () {
    ['workflow' => $workflow, 'po' => $po, 'creator' => $creator, 'hod' => $hod] = makeAuditScenario();

    $workflow->submit($po, $creator);

    $namedActivitiesBefore = $po->activities()
        ->whereNotIn('description', ['updated', 'created', 'Purchase order created', 'Submitted for approval'])
        ->count();

    $workflow->approveAtLevel($po, $hod);

    $namedActivitiesAfter = $po->activities()
        ->whereNotIn('description', ['updated', 'created', 'Purchase order created', 'Submitted for approval'])
        ->count();

    expect($namedActivitiesAfter)->toBe($namedActivitiesBefore,
        'approveAtLevel must not write any named activity_log entry');
})->group('AuditTrail');
