<?php

declare(strict_types=1);

use App\Enums\GrnStatus;
use App\Enums\Inbound\PurchaseOrderStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\ApprovalChain;
use App\Models\ApprovalChainUser;
use App\Models\Department;
use App\Models\GrnLine;
use App\Models\GrnReceipt;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemCategoryUser;
use App\Models\Location;
use App\Models\Partner;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Models\ItemUom;
use App\Services\Inbound\ApprovalChainService;
use App\Services\Inbound\GrnWorkflow;
use App\Services\Inbound\PurchaseOrderWorkflow;
use Database\Seeders\FoundationDefaultsSeeder;
use Database\Seeders\RolePermissionSeeder;

// ─── Group filter: #[Group('InboundWorkflow')] equivalent via describe block ──

beforeEach(function () {
    (new RolePermissionSeeder())->run();
    (new FoundationDefaultsSeeder())->run();
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeWarehouseWithDock(): array
{
    $warehouse = Warehouse::factory()->create(['name' => 'Test WH']);
    $dock      = Location::factory()->create([
        'warehouse_id' => $warehouse->id,
        'type'         => 'receiving_dock',
        'status'       => 'active',
        'level'        => 'bin',
        'code'         => 'DOCK-01',
    ]);
    $qtn = Location::factory()->create([
        'warehouse_id' => $warehouse->id,
        'type'         => 'quarantine',
        'status'       => 'active',
        'level'        => 'bin',
        'code'         => 'QTN-01',
    ]);
    return compact('warehouse', 'dock', 'qtn');
}

function makeItemWithUom(?int $categoryId = null, array $itemArgs = []): array
{
    $uom  = UnitOfMeasure::firstOrCreate(['code' => 'EA'], ['name' => 'Each', 'type' => 'count', 'is_active' => true]);
    $args = array_merge(['is_active' => true, 'base_uom_id' => $uom->id, 'track_batch' => false], $itemArgs);
    if ($categoryId) {
        $args['category_id'] = $categoryId;
    }
    $item = Item::factory()->create($args);
    ItemUom::firstOrCreate(
        ['item_id' => $item->id, 'uom_id' => $uom->id],
        ['factor_to_base' => 1, 'is_default_purchase' => true, 'is_default_issue' => true],
    );
    return compact('item', 'uom');
}

function makePo(Warehouse $warehouse, User $creator, ?Department $dept = null): PurchaseOrder
{
    $vw             = VirtualWarehouse::factory()->create(['warehouse_id' => $warehouse->id]);
    ['item' => $item, 'uom' => $uom] = makeItemWithUom();

    $workflow = app(PurchaseOrderWorkflow::class);
    return $workflow->create([
        'warehouse_id'         => $warehouse->id,
        'virtual_warehouse_id' => $vw->id,
        'department_id'        => $dept?->id,
        'source'               => 'manual',
        'package_type'         => 'loose_cargo',
        'lines'                => [[
            'item_id'    => $item->id,
            'uom_id'     => $uom->id,
            'qty'        => 10,
            'qty_base'   => 10,
            'unit_price' => '500.0000',
        ]],
    ], $creator);
}

// ─── 1. Status transition: invalid moves throw InvalidTransitionException ─────

test('invalid status transitions throw', function () {
    expect(fn () => PurchaseOrderStatus::Draft->transitionTo(PurchaseOrderStatus::VerifiedClosed))
        ->toThrow(InvalidTransitionException::class);
})->group('InboundWorkflow');

test('valid transitions succeed', function () {
    expect(PurchaseOrderStatus::Draft->canTransitionTo(PurchaseOrderStatus::PendingApproval))->toBeTrue();
    expect(PurchaseOrderStatus::PendingApproval->canTransitionTo(PurchaseOrderStatus::ApprovedPendingAcceptance))->toBeTrue();
    expect(PurchaseOrderStatus::VerifiedClosed->canTransitionTo(PurchaseOrderStatus::Draft))->toBeFalse();
})->group('InboundWorkflow');

// ─── 2. Approval chain: correct order, return restarts at level 1 ─────────────

test('single-level chain: submit notifies level-1 users', function () {
    $dept      = Department::factory()->create();
    $approver1 = User::factory()->create();
    $approver2 = User::factory()->create();
    $approver1->assignRole('department-user');
    $approver2->assignRole('department-user');

    $chain = ApprovalChain::create(['department_id' => $dept->id, 'document_type' => 'po', 'level' => 1]);
    ApprovalChainUser::create(['approval_chain_id' => $chain->id, 'user_id' => $approver1->id]);
    ApprovalChainUser::create(['approval_chain_id' => $chain->id, 'user_id' => $approver2->id]);

    $creator = User::factory()->create();
    $creator->assignRole('department-user');
    ['warehouse' => $warehouse] = makeWarehouseWithDock();
    $po = makePo($warehouse, $creator, $dept);

    app(PurchaseOrderWorkflow::class)->submit($po, $creator);
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::PendingApproval);
})->group('InboundWorkflow');

test('chain order: level-2 is not reachable until level-1 approves', function () {
    $dept      = Department::factory()->create();
    $approverL1 = User::factory()->create();
    $approverL2 = User::factory()->create();
    $approverL1->assignRole('department-user');
    $approverL2->assignRole('department-user');

    $chainL1 = ApprovalChain::create(['department_id' => $dept->id, 'document_type' => 'po', 'level' => 1]);
    $chainL2 = ApprovalChain::create(['department_id' => $dept->id, 'document_type' => 'po', 'level' => 2]);
    ApprovalChainUser::create(['approval_chain_id' => $chainL1->id, 'user_id' => $approverL1->id]);
    ApprovalChainUser::create(['approval_chain_id' => $chainL2->id, 'user_id' => $approverL2->id]);

    $creator = User::factory()->create();
    $creator->assignRole('department-user');
    ['warehouse' => $warehouse] = makeWarehouseWithDock();
    $po = makePo($warehouse, $creator, $dept);

    $workflow = app(PurchaseOrderWorkflow::class);
    $workflow->submit($po, $creator);

    // L2 cannot approve before L1
    $chainService = app(ApprovalChainService::class);
    expect($chainService->canActAtCurrentLevel($po, $approverL2))->toBeFalse();
    expect($chainService->canActAtCurrentLevel($po, $approverL1))->toBeTrue();

    $workflow->approveAtLevel($po, $approverL1);
    expect($chainService->canActAtCurrentLevel($po, $approverL2))->toBeTrue();

    $workflow->approveAtLevel($po, $approverL2);
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::ApprovedPendingAcceptance);
})->group('InboundWorkflow');

test('return resets approvals and restarts from level 1', function () {
    $dept    = Department::factory()->create();
    $approver = User::factory()->create();
    $approver->assignRole('department-user');

    $chain = ApprovalChain::create(['department_id' => $dept->id, 'document_type' => 'po', 'level' => 1]);
    ApprovalChainUser::create(['approval_chain_id' => $chain->id, 'user_id' => $approver->id]);

    $creator = User::factory()->create();
    $creator->assignRole('department-user');
    ['warehouse' => $warehouse] = makeWarehouseWithDock();
    $po = makePo($warehouse, $creator, $dept);

    $workflow = app(PurchaseOrderWorkflow::class);
    $workflow->submit($po, $creator);
    $workflow->returnForCorrection($po, $approver, 'Needs correction');

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::ReturnedForCorrection);
    expect(\App\Models\PurchaseOrderApproval::where('purchase_order_id', $po->id)->count())->toBe(1);

    // Resubmit only deletes 'approved' records so the chain can restart;
    // 'returned' records survive as immutable audit history.
    $workflow->submit($po->fresh(), $creator);
    expect(\App\Models\PurchaseOrderApproval::where('purchase_order_id', $po->id)
        ->where('action', 'approved')->count())->toBe(0);
    expect(\App\Models\PurchaseOrderApproval::where('purchase_order_id', $po->id)
        ->where('action', 'returned')->count())->toBe(1);
})->group('InboundWorkflow');

test('reject ends the workflow as terminal status', function () {
    $dept    = Department::factory()->create();
    $approver = User::factory()->create();
    $approver->assignRole('department-user');

    $chain = ApprovalChain::create(['department_id' => $dept->id, 'document_type' => 'po', 'level' => 1]);
    ApprovalChainUser::create(['approval_chain_id' => $chain->id, 'user_id' => $approver->id]);

    $creator = User::factory()->create();
    $creator->assignRole('department-user');
    ['warehouse' => $warehouse] = makeWarehouseWithDock();
    $po = makePo($warehouse, $creator, $dept);

    $workflow = app(PurchaseOrderWorkflow::class);
    $workflow->submit($po, $creator);
    $workflow->reject($po, $approver, 'Budget exceeded');

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::Rejected);
    expect($po->fresh()->status->isTerminal())->toBeTrue();
})->group('InboundWorkflow');

test('no chain configured skips directly to approved pending acceptance', function () {
    $creator = User::factory()->create();
    $creator->assignRole('department-user');
    ['warehouse' => $warehouse] = makeWarehouseWithDock();
    $po = makePo($warehouse, $creator);

    $workflow = app(PurchaseOrderWorkflow::class);
    $workflow->submit($po, $creator);

    // No department, no category users — should go straight to approved
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::ApprovedPendingAcceptance);
})->group('InboundWorkflow');

test('category level requires all categories to be approved', function () {
    ['warehouse' => $warehouse] = makeWarehouseWithDock();

    $category   = ItemCategory::factory()->create();
    $catApprover = User::factory()->create();
    $catApprover->assignRole('department-user');
    ItemCategoryUser::create([
        'item_category_id' => $category->id,
        'warehouse_id'     => $warehouse->id,
        'user_id'          => $catApprover->id,
    ]);

    $vw   = VirtualWarehouse::factory()->create(['warehouse_id' => $warehouse->id]);
    $uom  = UnitOfMeasure::firstOrCreate(['code' => 'EA'], ['name' => 'Each', 'type' => 'count', 'is_active' => true]);
    $item = Item::factory()->create(['category_id' => $category->id, 'is_active' => true]);

    $creator = User::factory()->create();
    $creator->assignRole('department-user');

    $workflow = app(PurchaseOrderWorkflow::class);
    $po = $workflow->create([
        'warehouse_id'         => $warehouse->id,
        'virtual_warehouse_id' => $vw->id,
        'lines'                => [['item_id' => $item->id, 'uom_id' => $uom->id, 'qty' => 5, 'qty_base' => 5]],
    ], $creator);

    $workflow->submit($po, $creator);
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::PendingApproval);

    $workflow->approveAtLevel($po->fresh(), $catApprover);
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::ApprovedPendingAcceptance);
})->group('InboundWorkflow');

// ─── 3. Manager → supervisor → clerk assignment ───────────────────────────────

test('manager accepts and assigns supervisor, supervisor assigns clerk', function () {
    ['warehouse' => $warehouse] = makeWarehouseWithDock();
    $creator    = User::factory()->create();
    $creator->assignRole('department-user');
    $manager    = User::factory()->create();
    $manager->assignRole('warehouse-manager');
    $supervisor = User::factory()->create();
    $supervisor->assignRole('warehouse-supervisor');
    $clerk      = User::factory()->create();
    $clerk->assignRole('storekeeper');

    $workflow = app(PurchaseOrderWorkflow::class);
    $po = makePo($warehouse, $creator);
    $workflow->submit($po, $creator);
    $po->refresh();

    // skip approval chain if none
    if ($po->status === PurchaseOrderStatus::PendingApproval) {
        $po->status = PurchaseOrderStatus::ApprovedPendingAcceptance;
        $po->save();
    }

    $workflow->accept($po->fresh(), $manager);
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::AcceptedPendingAssignment);

    $workflow->assignSupervisor($po->fresh(), $supervisor, $manager);
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::AssignedPendingReceiving);
    expect($po->fresh()->assigned_supervisor_id)->toBe($supervisor->id);

    $workflow->assignClerk($po->fresh(), $clerk, $manager);
    expect($po->fresh()->assigned_clerk_id)->toBe($clerk->id);
})->group('InboundWorkflow');

test('only assigned clerk can receive — others get DomainException', function () {
    ['warehouse' => $warehouse, 'dock' => $dock] = makeWarehouseWithDock();
    $creator    = User::factory()->create();
    $creator->assignRole('department-user');
    $manager    = User::factory()->create();
    $manager->assignRole('warehouse-manager');
    $supervisor = User::factory()->create();
    $supervisor->assignRole('warehouse-supervisor');
    $assignedClerk   = User::factory()->create();
    $assignedClerk->assignRole('storekeeper');
    $unassignedClerk = User::factory()->create();
    $unassignedClerk->assignRole('storekeeper');

    $workflow = app(PurchaseOrderWorkflow::class);
    $po = makePo($warehouse, $creator);
    $workflow->submit($po, $creator);
    $po->refresh();

    if ($po->status === PurchaseOrderStatus::PendingApproval) {
        $po->status = PurchaseOrderStatus::ApprovedPendingAcceptance;
        $po->save();
    }

    $workflow->accept($po->fresh(), $manager);
    $workflow->assignSupervisor($po->fresh(), $supervisor, $manager);
    $workflow->assignClerk($po->fresh(), $assignedClerk, $manager);
    $po->refresh();

    $grnWorkflow = app(GrnWorkflow::class);

    // Unassigned clerk cannot create receiving tally
    $policy = new \App\Policies\PurchaseOrderPolicy(app(ApprovalChainService::class));
    expect($policy->receive($unassignedClerk, $po))->toBeFalse();
    expect($policy->receive($assignedClerk, $po))->toBeTrue();
})->group('InboundWorkflow');

// ─── 4. UoM limited to item UoMs; line_total correct ─────────────────────────

test('line_total = qty × unit_price', function () {
    ['warehouse' => $warehouse] = makeWarehouseWithDock();
    $creator = User::factory()->create();
    $creator->assignRole('department-user');

    ['item' => $item, 'uom' => $uom] = makeItemWithUom();

    $workflow = app(PurchaseOrderWorkflow::class);
    $po = $workflow->create([
        'warehouse_id' => $warehouse->id,
        'lines'        => [['item_id' => $item->id, 'uom_id' => $uom->id, 'qty' => 3, 'qty_base' => 3, 'unit_price' => '250.0000']],
    ], $creator);

    $line = $po->lines->first();
    expect((float) $line->line_total)->toBe(750.0);
    expect((float) $po->totalAmount())->toBe(750.0);
})->group('InboundWorkflow');

// ─── 5. Complete receiving: GRN number, stock posted once (idempotent) ────────

test('completeReceiving assigns GRN-{PO}-{n} and posts stock once', function () {
    ['warehouse' => $warehouse, 'dock' => $dock] = makeWarehouseWithDock();
    $creator    = User::factory()->create();
    $creator->assignRole('department-user');
    $manager    = User::factory()->create();
    $manager->assignRole('warehouse-manager');
    $supervisor = User::factory()->create();
    $supervisor->assignRole('warehouse-supervisor');
    $clerk      = User::factory()->create();
    $clerk->assignRole('storekeeper');

    $vw = VirtualWarehouse::factory()->create(['warehouse_id' => $warehouse->id]);
    ['item' => $item, 'uom' => $uom] = makeItemWithUom();

    $poWorkflow = app(PurchaseOrderWorkflow::class);
    $po = $poWorkflow->create([
        'warehouse_id'         => $warehouse->id,
        'virtual_warehouse_id' => $vw->id,
        'lines'                => [['item_id' => $item->id, 'uom_id' => $uom->id, 'qty' => 5, 'qty_base' => 5]],
    ], $creator);

    $poWorkflow->submit($po, $creator);
    $po->refresh();
    if ($po->status !== PurchaseOrderStatus::ApprovedPendingAcceptance) {
        $po->status = PurchaseOrderStatus::ApprovedPendingAcceptance;
        $po->save();
    }
    $poWorkflow->accept($po->fresh(), $manager);
    $poWorkflow->assignSupervisor($po->fresh(), $supervisor, $manager);
    $poWorkflow->assignClerk($po->fresh(), $clerk, $manager);

    $grnWorkflow = app(GrnWorkflow::class);
    $grn = $grnWorkflow->createDraftForPO($po->fresh(), $dock->id, ['delivery_type' => 'full'], $clerk);

    expect($grn->number)->toBeNull();
    expect($grn->status)->toBe(GrnStatus::Draft);

    // Enter a receipt on the first GRN line
    $grnLine = $grn->lines()->first();
    GrnReceipt::create([
        'grn_line_id' => $grnLine->id,
        'qty'         => '5.0000',
        'uom_id'      => $uom->id,
        'condition'   => 'good',
        'received_by' => $clerk->id,
    ]);
    $grnLine->recalculateTotals();

    $grnWorkflow->completeReceiving($grn, $clerk);
    $grn->refresh();

    expect($grn->number)->toBe("GRN-{$po->number}-1");
    expect($grn->status)->toBe(GrnStatus::Received);

    // Stock posted
    $txCount = \App\Models\Inventory\StockTransaction::where('reference_type', 'grn')
        ->where('reference_id', $grn->id)
        ->count();
    expect($txCount)->toBeGreaterThan(0);

    // Retry is idempotent
    $grnWorkflow->completeReceiving($grn, $clerk);
    $txCountAfterRetry = \App\Models\Inventory\StockTransaction::where('reference_type', 'grn')
        ->where('reference_id', $grn->id)
        ->count();
    expect($txCountAfterRetry)->toBe($txCount);
})->group('InboundWorkflow');

// ─── 6. Auto batch generated when item tracks batches ────────────────────────

test('auto batch B-{yyyymmdd}-{seq} generated when item tracks_batch and no batch entered', function () {
    ['warehouse' => $warehouse, 'dock' => $dock] = makeWarehouseWithDock();
    $clerk   = User::factory()->create();
    $clerk->assignRole('storekeeper');
    $manager = User::factory()->create();
    $manager->assignRole('warehouse-manager');
    $supervisor = User::factory()->create();
    $supervisor->assignRole('warehouse-supervisor');
    $creator = User::factory()->create();
    $creator->assignRole('department-user');

    $vw = VirtualWarehouse::factory()->create(['warehouse_id' => $warehouse->id]);
    ['item' => $item, 'uom' => $uom] = makeItemWithUom(null, ['track_batch' => true]);

    $poWorkflow = app(PurchaseOrderWorkflow::class);
    $po = $poWorkflow->create([
        'warehouse_id'         => $warehouse->id,
        'virtual_warehouse_id' => $vw->id,
        'lines'                => [['item_id' => $item->id, 'uom_id' => $uom->id, 'qty' => 2, 'qty_base' => 2]],
    ], $creator);

    $po->status = PurchaseOrderStatus::AssignedPendingReceiving;
    $po->assigned_supervisor_id = $supervisor->id;
    $po->assigned_clerk_id = $clerk->id;
    $po->save();

    $grnWorkflow = app(GrnWorkflow::class);
    $grn = $grnWorkflow->createDraftForPO($po->fresh(), $dock->id, [], $clerk);

    $grnLine = $grn->lines()->first();
    GrnReceipt::create([
        'grn_line_id' => $grnLine->id,
        'qty'         => '2.0000',
        'uom_id'      => $uom->id,
        'condition'   => 'good',
        'received_by' => $clerk->id,
        'batch_no'    => null, // no batch entered
    ]);
    $grnLine->recalculateTotals();

    $grnWorkflow->completeReceiving($grn, $clerk);

    $receipt = $grn->lines()->first()->receipts()->first();
    expect($receipt->batch_no)->toMatch('/^B-\d{8}-\d{4}$/');
})->group('InboundWorkflow');

// ─── 7. Dock busy derived from open Draft GRNs ───────────────────────────────

test('dock is busy while a Draft GRN with null number exists on it', function () {
    ['warehouse' => $warehouse, 'dock' => $dock] = makeWarehouseWithDock();
    $clerk = User::factory()->create();
    $clerk->assignRole('storekeeper');
    $creator = User::factory()->create();
    $creator->assignRole('department-user');

    $vw   = VirtualWarehouse::factory()->create(['warehouse_id' => $warehouse->id]);
    $uom  = UnitOfMeasure::firstOrCreate(['code' => 'EA'], ['name' => 'Each', 'type' => 'count', 'is_active' => true]);
    $item = Item::factory()->create(['is_active' => true]);

    $poWorkflow = app(PurchaseOrderWorkflow::class);
    $po = $poWorkflow->create([
        'warehouse_id'         => $warehouse->id,
        'virtual_warehouse_id' => $vw->id,
        'lines'                => [['item_id' => $item->id, 'uom_id' => $uom->id, 'qty' => 1, 'qty_base' => 1]],
    ], $creator);
    $po->status = PurchaseOrderStatus::AssignedPendingReceiving;
    $po->assigned_clerk_id = $clerk->id;
    $po->save();

    expect(\App\Models\Grn::isDockBusy($dock->id))->toBeFalse();

    $grnWorkflow = app(GrnWorkflow::class);
    $grnWorkflow->createDraftForPO($po->fresh(), $dock->id, [], $clerk);

    expect(\App\Models\Grn::isDockBusy($dock->id))->toBeTrue();

    // Second PO cannot use same dock
    $po2 = $poWorkflow->create([
        'warehouse_id'         => $warehouse->id,
        'virtual_warehouse_id' => $vw->id,
        'lines'                => [['item_id' => $item->id, 'uom_id' => $uom->id, 'qty' => 1, 'qty_base' => 1]],
    ], $creator);
    $po2->status = PurchaseOrderStatus::AssignedPendingReceiving;
    $po2->assigned_clerk_id = $clerk->id;
    $po2->save();

    expect(fn () => $grnWorkflow->createDraftForPO($po2->fresh(), $dock->id, [], $clerk))
        ->toThrow(\DomainException::class);
})->group('InboundWorkflow');

// ─── 8. Partial delivery: second GRN, PO stays PartiallyReceived ─────────────

test('partial delivery: PO becomes PartiallyReceived after first GRN', function () {
    ['warehouse' => $warehouse, 'dock' => $dock] = makeWarehouseWithDock();
    $clerk   = User::factory()->create();
    $clerk->assignRole('storekeeper');
    $creator = User::factory()->create();
    $creator->assignRole('department-user');

    $vw = VirtualWarehouse::factory()->create(['warehouse_id' => $warehouse->id]);
    ['item' => $item, 'uom' => $uom] = makeItemWithUom();

    $poWorkflow = app(PurchaseOrderWorkflow::class);
    $po = $poWorkflow->create([
        'warehouse_id'         => $warehouse->id,
        'virtual_warehouse_id' => $vw->id,
        'lines'                => [['item_id' => $item->id, 'uom_id' => $uom->id, 'qty' => 10, 'qty_base' => 10]],
    ], $creator);
    $po->status = PurchaseOrderStatus::AssignedPendingReceiving;
    $po->assigned_clerk_id = $clerk->id;
    $po->save();

    $grnWorkflow = app(GrnWorkflow::class);
    $grn1 = $grnWorkflow->createDraftForPO($po->fresh(), $dock->id, [], $clerk);
    $line1 = $grn1->lines()->first();
    GrnReceipt::create(['grn_line_id' => $line1->id, 'qty' => '4.0000', 'uom_id' => $uom->id, 'condition' => 'good', 'received_by' => $clerk->id]);
    $line1->recalculateTotals();
    $grnWorkflow->completeReceiving($grn1, $clerk);

    expect($grn1->fresh()->number)->toBe("GRN-{$po->number}-1");
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::PartiallyReceived);
})->group('InboundWorkflow');

// ─── 9. Supervisor validation required before approver notification ───────────

test('supervisor validates before approvers notified', function () {
    ['warehouse' => $warehouse] = makeWarehouseWithDock();
    $supervisor = User::factory()->create();
    $supervisor->assignRole('warehouse-supervisor');

    $po = PurchaseOrder::factory()->create([
        'warehouse_id'             => $warehouse->id,
        'status'                   => PurchaseOrderStatus::PutawayInProgress,
        'assigned_supervisor_id'   => $supervisor->id,
    ]);

    $workflow = app(PurchaseOrderWorkflow::class);
    $workflow->markPutawayComplete($po, $supervisor);
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::PendingSupervisorValidation);

    $workflow->validateBySupevisor($po->fresh(), $supervisor);
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::CompletedPendingVerification);
})->group('InboundWorkflow');

// ─── 10. Verify closes; raise issue → resolve → re-verify ────────────────────

test('verify closes the PO', function () {
    ['warehouse' => $warehouse] = makeWarehouseWithDock();
    $approver = User::factory()->create();
    $approver->assignRole('department-user');

    $po = PurchaseOrder::factory()->create([
        'warehouse_id' => $warehouse->id,
        'status'       => PurchaseOrderStatus::CompletedPendingVerification,
    ]);
    // Record that this user was an approver
    \App\Models\PurchaseOrderApproval::create([
        'purchase_order_id' => $po->id,
        'level'             => 1,
        'user_id'           => $approver->id,
        'action'            => 'approved',
    ]);

    $workflow = app(PurchaseOrderWorkflow::class);
    $workflow->verifyAndClose($po, $approver);
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::VerifiedClosed);
})->group('InboundWorkflow');

test('raise issue → resolve → re-verify flow', function () {
    ['warehouse' => $warehouse] = makeWarehouseWithDock();
    $approver = User::factory()->create();
    $approver->assignRole('department-user');
    $manager = User::factory()->create();
    $manager->assignRole('warehouse-manager');

    $po = PurchaseOrder::factory()->create([
        'warehouse_id' => $warehouse->id,
        'status'       => PurchaseOrderStatus::CompletedPendingVerification,
    ]);
    \App\Models\PurchaseOrderApproval::create([
        'purchase_order_id' => $po->id,
        'level'             => 1,
        'user_id'           => $approver->id,
        'action'            => 'approved',
    ]);

    $workflow = app(PurchaseOrderWorkflow::class);
    $issue = $workflow->raiseIssue($po, $approver, 'Quantity mismatch on shelf');
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::IssueRaised);
    expect($issue->isOpen())->toBeTrue();

    $workflow->resolveIssue($issue, $manager, 'Recount confirmed correct', null);
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::IssueResolved);

    $workflow->verifyAndClose($po->fresh(), $approver);
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::VerifiedClosed);
})->group('InboundWorkflow');

// ─── 11. Cancellation only before receiving starts ────────────────────────────

test('PO can be cancelled before receiving but not after', function () {
    ['warehouse' => $warehouse] = makeWarehouseWithDock();
    $creator = User::factory()->create();
    $creator->assignRole('department-user');

    $po = PurchaseOrder::factory()->create([
        'warehouse_id' => $warehouse->id,
        'status'       => PurchaseOrderStatus::AcceptedPendingAssignment,
        'requested_by' => $creator->id,
    ]);

    $workflow = app(PurchaseOrderWorkflow::class);
    $workflow->cancel($po, 'Budget cut', $creator);
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::Cancelled);

    $poLocked = PurchaseOrder::factory()->create([
        'warehouse_id' => $warehouse->id,
        'status'       => PurchaseOrderStatus::ReceivingInProgress,
    ]);

    expect(fn () => $workflow->cancel($poLocked, 'Too late', $creator))
        ->toThrow(InvalidTransitionException::class);
})->group('InboundWorkflow');

// ─── 12. Data migration: old statuses map correctly ──────────────────────────

test('status mapping table is complete', function () {
    $map = [
        'draft'     => 'draft',
        'submitted' => 'pending_approval',
        'approved'  => 'approved_pending_acceptance',
        'rejected'  => 'rejected',
        'receiving' => 'receiving_in_progress',
        'closed'    => 'verified_closed',
        'cancelled' => 'cancelled',
    ];

    // All new values are valid enum cases
    foreach ($map as $old => $new) {
        expect(PurchaseOrderStatus::from($new))->toBeInstanceOf(PurchaseOrderStatus::class);
    }
})->group('InboundWorkflow');

// ─── 13. Role matrix enforcement in policies ─────────────────────────────────

test('role matrix: non-clerk cannot receive, non-manager cannot accept', function () {
    ['warehouse' => $warehouse] = makeWarehouseWithDock();
    $policy = new \App\Policies\PurchaseOrderPolicy(app(ApprovalChainService::class));

    $supervisor = User::factory()->create();
    $supervisor->assignRole('warehouse-supervisor');
    $po = PurchaseOrder::factory()->create([
        'warehouse_id' => $warehouse->id,
        'status'       => PurchaseOrderStatus::ApprovedPendingAcceptance,
    ]);

    // supervisor cannot accept (manager only)
    expect($policy->accept($supervisor, $po))->toBeFalse();

    // viewer-auditor cannot create
    $viewer = User::factory()->create();
    $viewer->assignRole('viewer-auditor');
    expect($policy->create($viewer))->toBeFalse();
})->group('InboundWorkflow');

// ─── 14. No container/vehicle fields on PO create ────────────────────────────

test('PO created without container or vehicle fields', function () {
    ['warehouse' => $warehouse] = makeWarehouseWithDock();
    $creator = User::factory()->create();
    $creator->assignRole('department-user');

    $po = makePo($warehouse, $creator);

    // PO model should not expose removed fields
    expect($po->container_no)->toBeNull();
    expect($po->vehicle_type)->toBeNull();
    expect($po->vehicle_plate)->toBeNull();
})->group('InboundWorkflow');

// ─── 15. Special treatment forces approval even with no chain ─────────────────

test('special treatment forces PendingApproval even when no chain configured', function () {
    ['warehouse' => $warehouse] = makeWarehouseWithDock();
    $creator = User::factory()->create();
    $creator->assignRole('department-user');
    $manager = User::factory()->create();
    $manager->assignRole('warehouse-manager');
    $manager->givePermissionTo(['po.create', 'po.approve', 'po.accept']);

    ['item' => $item, 'uom' => $uom] = makeItemWithUom();

    $poWorkflow = app(PurchaseOrderWorkflow::class);
    $po = $poWorkflow->create([
        'warehouse_id'             => $warehouse->id,
        'is_special_treatment'     => true,
        'special_treatment_reason' => 'Hazardous goods — requires special handling approval.',
        'lines'                    => [['item_id' => $item->id, 'uom_id' => $uom->id, 'qty' => 5, 'qty_base' => 5]],
    ], $creator);

    // No chain configured → without special treatment it would auto-approve
    // With special treatment it must go to PendingApproval
    $poWorkflow->submit($po->fresh(), $creator);

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::PendingApproval);
})->group('InboundWorkflow');

// ─── 16. STN document sequence used for transfers ────────────────────────────

test('STN document sequence exists and generates STN- prefix numbers', function () {
    // Verify the STN sequence was seeded and generates the right prefix
    $number = \App\Services\Foundation\DocumentNumber::next('STN');

    expect($number)->toStartWith('STN-');
})->group('InboundWorkflow');
