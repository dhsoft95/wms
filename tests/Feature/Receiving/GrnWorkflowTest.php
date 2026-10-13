<?php

declare(strict_types=1);

namespace Tests\Feature\Receiving;

use App\Enums\GrnSource;
use App\Enums\GrnStatus;
use App\Enums\ReceiptCondition;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\GrnReceipt;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\Inbound\GrnWorkflow;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrnWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Warehouse $warehouse;
    private VirtualWarehouse $vw;
    private Location $dock;
    private Location $storage;
    private UnitOfMeasure $pcs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(\Database\Seeders\FoundationDefaultsSeeder::class);

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('system-admin');

        $this->warehouse = Warehouse::create([
            'code'      => 'TEST',
            'name'      => 'Test Warehouse',
            'is_active' => true,
        ]);

        $department = \App\Models\Department::create([
            'name'      => 'Test Department',
            'code'      => 'TEST-DEPT',
            'is_active' => true,
        ]);

        $this->vw = VirtualWarehouse::create([
            'department_id' => $department->id,
            'code'          => 'TEST-VW',
            'name'          => 'Test VW',
            'is_active'     => true,
        ]);

        $this->pcs = UnitOfMeasure::create([
            'code'      => 'PCS-T',
            'name'      => 'Pieces Test',
            'type'      => 'count',
            'is_active' => true,
        ]);

        $this->dock = Location::create([
            'warehouse_id'  => $this->warehouse->id,
            'code'          => 'DOCK-01',
            'segment_code'  => 'DOCK-01',
            'type'          => 'receiving_dock',
            'status'        => 'active',
            'level'         => 'bin',
            'is_pickable'   => false,
        ]);

        $this->storage = Location::create([
            'warehouse_id'  => $this->warehouse->id,
            'code'          => 'BIN-A-01',
            'segment_code'  => 'A-01',
            'type'          => 'storage',
            'status'        => 'active',
            'level'         => 'bin',
            'is_pickable'   => true,
        ]);

        $this->warehouse->update([
            'default_receiving_dock_location_id' => $this->dock->id,
        ]);
    }

    // ─── Test: Draft GRN can be created ──────────────────────────────────────

    public function test_grn_can_be_created_in_draft(): void
    {
        $grn = Grn::create([
            'number'       => 'GRN-TEST-001',
            'source'       => GrnSource::Supplier->value,
            'warehouse_id' => $this->warehouse->id,
            'status'       => GrnStatus::Draft->value,
        ]);

        $this->assertDatabaseHas('grns', ['number' => 'GRN-TEST-001', 'status' => 'draft']);
    }

    // ─── Test: Start Receiving transitions Draft → Receiving ─────────────────

    public function test_start_receiving_transitions_draft_to_receiving(): void
    {
        $grn = $this->makeDraftGrnWithLine();

        app(GrnWorkflow::class)->startReceiving($grn, $this->admin);

        $this->assertEquals(GrnStatus::Receiving, $grn->fresh()->status);
    }

    // ─── Test: Cannot start receiving from invalid state ─────────────────────

    public function test_cannot_start_receiving_when_already_received(): void
    {
        $grn = $this->makeDraftGrnWithLine();
        $grn->status = GrnStatus::Received;
        $grn->save();

        $this->expectException(\App\Exceptions\InvalidTransitionException::class);
        app(GrnWorkflow::class)->startReceiving($grn, $this->admin);
    }

    // ─── Test: No-variance GRN can be directly confirmed ─────────────────────

    public function test_no_variance_grn_confirms_without_approval(): void
    {
        $grn  = $this->makeDraftGrnWithLine(100);
        $line = $grn->lines()->first();

        app(GrnWorkflow::class)->startReceiving($grn, $this->admin);

        GrnReceipt::create([
            'grn_line_id' => $line->id,
            'qty'         => 100,
            'uom_id'      => $this->pcs->id,
            'condition'   => ReceiptCondition::Good->value,
            'received_by' => $this->admin->id,
        ]);
        $line->recalculateTotals();

        app(GrnWorkflow::class)->finishReceiving($grn->fresh(), $this->admin);

        $this->assertEquals(GrnStatus::Received, $grn->fresh()->status);
    }

    // ─── Test: Variance GRN goes to PendingVarianceApproval ──────────────────

    public function test_variance_grn_requires_approval(): void
    {
        $grn  = $this->makeDraftGrnWithLine(100);
        $line = $grn->lines()->first();

        app(GrnWorkflow::class)->startReceiving($grn, $this->admin);

        // Receive short — 80 of 100
        GrnReceipt::create([
            'grn_line_id' => $line->id,
            'qty'         => 80,
            'uom_id'      => $this->pcs->id,
            'condition'   => ReceiptCondition::Good->value,
            'received_by' => $this->admin->id,
        ]);
        $line->recalculateTotals();

        app(GrnWorkflow::class)->finishReceiving($grn->fresh(), $this->admin);

        $this->assertEquals(GrnStatus::PendingVarianceApproval, $grn->fresh()->status);
    }

    // ─── Test: Approving variance confirms GRN ────────────────────────────────

    public function test_approving_variance_confirms_grn(): void
    {
        $grn  = $this->makeDraftGrnWithLine(100);
        $line = $grn->lines()->first();

        app(GrnWorkflow::class)->startReceiving($grn, $this->admin);

        GrnReceipt::create([
            'grn_line_id' => $line->id,
            'qty'         => 80,
            'uom_id'      => $this->pcs->id,
            'condition'   => ReceiptCondition::Good->value,
            'received_by' => $this->admin->id,
        ]);
        $line->recalculateTotals();
        app(GrnWorkflow::class)->finishReceiving($grn->fresh(), $this->admin);

        app(GrnWorkflow::class)->approveVariance($grn->fresh(), $this->admin, 'Approved by test');

        $this->assertEquals(GrnStatus::Received, $grn->fresh()->status);
    }

    // ─── Test: Cancel transitions any active status → Cancelled ──────────────

    public function test_cancel_transitions_to_cancelled(): void
    {
        $grn = $this->makeDraftGrnWithLine();
        app(GrnWorkflow::class)->cancel($grn, 'Test cancel', $this->admin);

        $this->assertEquals(GrnStatus::Cancelled, $grn->fresh()->status);
    }

    // ─── Test: Idempotency check on confirm ───────────────────────────────────

    public function test_confirm_is_idempotent(): void
    {
        $grn  = $this->makeDraftGrnWithLine(100);
        $line = $grn->lines()->first();

        app(GrnWorkflow::class)->startReceiving($grn, $this->admin);

        GrnReceipt::create([
            'grn_line_id' => $line->id,
            'qty'         => 100,
            'uom_id'      => $this->pcs->id,
            'condition'   => ReceiptCondition::Good->value,
            'received_by' => $this->admin->id,
        ]);
        $line->recalculateTotals();

        app(GrnWorkflow::class)->finishReceiving($grn->fresh(), $this->admin);
        // Second call should not throw
        $grn2 = $grn->fresh();
        $grn2->status = GrnStatus::Receiving; // artificially set back
        app(GrnWorkflow::class)->confirm($grn2, $this->admin);

        // Should still be received (idempotency returned early from the second confirm in the original)
        $this->assertEquals(GrnStatus::Received, $grn->fresh()->status);
    }

    // ─── Test: GrnLine recalculate totals ─────────────────────────────────────

    public function test_grn_line_recalculates_totals_correctly(): void
    {
        $grn  = $this->makeDraftGrnWithLine(100);
        $line = $grn->lines()->first();

        GrnReceipt::create(['grn_line_id' => $line->id, 'qty' => 60, 'uom_id' => $this->pcs->id, 'condition' => 'good', 'received_by' => $this->admin->id]);
        GrnReceipt::create(['grn_line_id' => $line->id, 'qty' => 10, 'uom_id' => $this->pcs->id, 'condition' => 'damaged', 'received_by' => $this->admin->id]);

        $line->recalculateTotals();
        $line->refresh();

        $this->assertEquals('60.0000', $line->received_good_qty);
        $this->assertEquals('10.0000', $line->received_damaged_qty);
        $this->assertEquals('-30.0000', $line->variance_qty); // 70 received - 100 expected
        $this->assertEquals('mixed', $line->variance_type->value);
    }

    // ─── Test: RealContainerReceivingGate blocks unload when GRN not received ─

    public function test_real_gate_blocks_unload_when_grn_not_received(): void
    {
        $container = \App\Models\Container::create([
            'number'       => 'CTR-TEST-001',
            'container_no' => 'TCKU1234501',
            'size_type'    => '20GP',
            'status'       => 'unloading',
        ]);

        Grn::create([
            'number'       => 'GRN-GATE-TEST',
            'source'       => GrnSource::Container->value,
            'container_id' => $container->id,
            'warehouse_id' => $this->warehouse->id,
            'status'       => GrnStatus::Receiving->value,
        ]);

        $gate   = app(\App\Services\Inbound\RealContainerReceivingGate::class);
        $result = $gate->checkReadyForUnloaded($container->id);

        $this->assertNotNull($result);
        $this->assertStringContainsString('1 GRN', $result);
    }

    // ─── Test: RealContainerReceivingGate allows unload when all GRNs received ─

    public function test_real_gate_allows_unload_when_all_grns_received(): void
    {
        $container = \App\Models\Container::create([
            'number'       => 'CTR-TEST-002',
            'container_no' => 'TCKU1234502',
            'size_type'    => '20GP',
            'status'       => 'unloading',
        ]);

        Grn::create([
            'number'       => 'GRN-GATE-OK',
            'source'       => GrnSource::Container->value,
            'container_id' => $container->id,
            'warehouse_id' => $this->warehouse->id,
            'status'       => GrnStatus::Received->value,
        ]);

        $gate   = app(\App\Services\Inbound\RealContainerReceivingGate::class);
        $result = $gate->checkReadyForUnloaded($container->id);

        $this->assertNull($result);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function makeDraftGrnWithLine(int $qty = 100): Grn
    {
        $item = \App\Models\Item::create([
            'sku'         => 'TEST-' . uniqid(),
            'name'        => 'Test Item',
            'base_uom_id' => $this->pcs->id,
            'is_active'   => true,
        ]);

        $grn = Grn::create([
            'number'                     => 'GRN-' . uniqid(),
            'source'                     => GrnSource::Supplier->value,
            'warehouse_id'               => $this->warehouse->id,
            'receiving_dock_location_id' => $this->dock->id,
            'status'                     => GrnStatus::Draft->value,
        ]);

        GrnLine::create([
            'grn_id'              => $grn->id,
            'item_id'             => $item->id,
            'virtual_warehouse_id'=> $this->vw->id,
            'uom_id'              => $this->pcs->id,
            'expected_qty'        => $qty,
        ]);

        return $grn->fresh();
    }
}
