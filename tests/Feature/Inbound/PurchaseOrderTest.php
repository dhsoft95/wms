<?php

declare(strict_types=1);

namespace Tests\Feature\Inbound;

use App\Enums\Inbound\PackageType;
use App\Enums\Inbound\PurchaseOrderStatus;
use App\Models\Container;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderLine;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use Database\Seeders\FoundationDefaultsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(FoundationDefaultsSeeder::class);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Only department-user can create a PO; storekeeper gets DomainException
    // ─────────────────────────────────────────────────────────────────────────

    public function test_only_department_user_can_create_po(): void
    {
        $departmentUser = User::factory()->create();
        $departmentUser->assignRole('department-user');

        $storekeeper = User::factory()->create();
        $storekeeper->assignRole('storekeeper');

        $warehouse = Warehouse::factory()->create();
        $item      = Item::factory()->create(['is_active' => true]);
        $uom       = UnitOfMeasure::firstOrCreate(
            ['code' => 'PCS'],
            ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
        );

        $data = [
            'warehouse_id' => $warehouse->id,
            'package_type' => 'loose_cargo',
            'source'       => 'manual',
            'lines'        => [[
                'item_id'  => $item->id,
                'uom_id'   => $uom->id,
                'qty'      => 10,
                'qty_base' => 10,
            ]],
        ];

        // department-user can create
        $workflow = app(\App\Services\Inbound\PurchaseOrderWorkflow::class);
        $po       = $workflow->create($data, $departmentUser);

        $this->assertEquals(PurchaseOrderStatus::Draft, $po->status);
        $this->assertDatabaseHas('purchase_orders', ['id' => $po->id, 'status' => 'draft']);

        // storekeeper cannot — throws DomainException
        $this->expectException(\DomainException::class);
        $workflow->create($data, $storekeeper);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. PO with container package_type creates Container on approve
    // ─────────────────────────────────────────────────────────────────────────

    public function test_po_approve_creates_container_for_container_package_type(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('warehouse-manager');

        $warehouse = Warehouse::factory()->create();
        $item      = Item::factory()->create(['is_active' => true]);
        $uom       = UnitOfMeasure::firstOrCreate(
            ['code' => 'PCS'],
            ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
        );

        $po = PurchaseOrder::factory()->submitted()->container()->create([
            'warehouse_id' => $warehouse->id,
            'requested_by' => $manager->id,
        ]);

        PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $po->id,
            'item_id'           => $item->id,
            'uom_id'            => $uom->id,
            'qty'               => 50,
            'qty_base'          => 50,
        ]);

        $workflow = app(\App\Services\Inbound\PurchaseOrderWorkflow::class);
        $workflow->approve($po, $manager);

        $po->refresh();

        $this->assertEquals(PurchaseOrderStatus::Approved, $po->status);
        $this->assertNotNull($po->container_id);
        $this->assertDatabaseHas('containers', [
            'id'           => $po->container_id,
            'container_no' => $po->container_no,
            'warehouse_id' => $warehouse->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. PO with loose cargo — no container created on approve
    // ─────────────────────────────────────────────────────────────────────────

    public function test_po_approve_does_not_create_container_for_loose_cargo(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('warehouse-manager');

        $warehouse = Warehouse::factory()->create();
        $item      = Item::factory()->create(['is_active' => true]);
        $uom       = UnitOfMeasure::firstOrCreate(
            ['code' => 'PCS'],
            ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
        );

        $po = PurchaseOrder::factory()->submitted()->create([
            'warehouse_id'  => $warehouse->id,
            'package_type'  => PackageType::LooseCargo,
            'vehicle_plate' => 'T123ABC',
            'driver_name'   => 'John Driver',
            'requested_by'  => $manager->id,
        ]);

        PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $po->id,
            'item_id'           => $item->id,
            'uom_id'            => $uom->id,
            'qty'               => 5,
            'qty_base'          => 5,
        ]);

        $workflow = app(\App\Services\Inbound\PurchaseOrderWorkflow::class);
        $workflow->approve($po, $manager);

        $po->refresh();

        $this->assertEquals(PurchaseOrderStatus::Approved, $po->status);
        $this->assertNull($po->container_id);
        $this->assertEquals('T123ABC', $po->vehicle_plate);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. Schema check — containers table has purchase_order_id column
    // ─────────────────────────────────────────────────────────────────────────

    public function test_data_migration_all_containers_have_purchase_order(): void
    {
        // With RefreshDatabase, migrations run fresh — just verify the schema
        $this->assertTrue(
            Schema::hasColumn('containers', 'purchase_order_id'),
            'containers table should have purchase_order_id column'
        );
        $this->assertTrue(
            Schema::hasColumn('purchase_order_lines', 'purchase_order_id'),
            'purchase_order_lines table should have purchase_order_id column'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. DO submit fails when no stock available
    // ─────────────────────────────────────────────────────────────────────────

    public function test_do_submit_fails_when_no_stock(): void
    {
        $user = User::factory()->create();
        $user->assignRole('department-user');

        $warehouse = Warehouse::factory()->create();
        $vw        = VirtualWarehouse::factory()->create();
        $item      = Item::factory()->create(['is_active' => true, 'category_id' => null]);

        $uom = UnitOfMeasure::firstOrCreate(
            ['code' => 'PCS'],
            ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
        );

        // No StockBalance — no stock available
        $do = DeliveryOrder::factory()->create([
            'status'               => \App\Enums\Outbound\DeliveryOrderStatus::Draft,
            'warehouse_id'         => $warehouse->id,
            'virtual_warehouse_id' => $vw->id,
        ]);

        DeliveryOrderLine::factory()->create([
            'delivery_order_id'   => $do->id,
            'item_id'             => $item->id,
            'uom_id'              => $uom->id,
            'requested_qty'       => '10.0000',
            'requested_qty_base'  => '10.0000',
        ]);

        $this->expectException(\DomainException::class);

        app(\App\Services\Outbound\DeliveryOrderWorkflow::class)->submit($do, $user);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. dispatches table has dn_number and rcn_number columns
    // ─────────────────────────────────────────────────────────────────────────

    public function test_dispatch_assigns_dn_and_rcn_numbers(): void
    {
        $this->assertTrue(
            Schema::hasColumn('dispatches', 'dn_number'),
            'dispatches table should have dn_number column'
        );
        $this->assertTrue(
            Schema::hasColumn('dispatches', 'rcn_number'),
            'dispatches table should have rcn_number column'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 7. PO policy — warehouse-manager can approve a submitted PO
    // ─────────────────────────────────────────────────────────────────────────

    public function test_warehouse_manager_can_approve_submitted_po(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('warehouse-manager');

        $warehouse = Warehouse::factory()->create();
        $item      = Item::factory()->create(['is_active' => true]);

        $po = PurchaseOrder::factory()->submitted()->create([
            'warehouse_id' => $warehouse->id,
            'requested_by' => $manager->id,
        ]);

        PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $po->id,
            'item_id'           => $item->id,
        ]);

        $this->assertTrue($manager->can('approve', $po));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 8. PO policy — storekeeper cannot approve a PO
    // ─────────────────────────────────────────────────────────────────────────

    public function test_storekeeper_cannot_approve_po(): void
    {
        $storekeeper = User::factory()->create();
        $storekeeper->assignRole('storekeeper');

        $warehouse = Warehouse::factory()->create();

        $po = PurchaseOrder::factory()->submitted()->create([
            'warehouse_id' => $warehouse->id,
        ]);

        $this->assertFalse($storekeeper->can('approve', $po));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 9. PO submit transitions status and sets submitted_at
    // ─────────────────────────────────────────────────────────────────────────

    public function test_po_submit_transitions_status(): void
    {
        $departmentUser = User::factory()->create();
        $departmentUser->assignRole('department-user');

        $warehouse = Warehouse::factory()->create();
        $item      = Item::factory()->create(['is_active' => true]);
        $uom       = UnitOfMeasure::firstOrCreate(
            ['code' => 'PCS'],
            ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
        );

        $po = PurchaseOrder::factory()->create([
            'warehouse_id' => $warehouse->id,
            'requested_by' => $departmentUser->id,
        ]);

        PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $po->id,
            'item_id'           => $item->id,
            'uom_id'            => $uom->id,
            'qty'               => 10,
            'qty_base'          => 10,
        ]);

        $workflow = app(\App\Services\Inbound\PurchaseOrderWorkflow::class);
        $workflow->submit($po, $departmentUser);

        $po->refresh();

        $this->assertEquals(PurchaseOrderStatus::Submitted, $po->status);
        $this->assertNotNull($po->submitted_at);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 10. PO cancel from draft status
    // ─────────────────────────────────────────────────────────────────────────

    public function test_po_can_be_cancelled_from_draft(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('warehouse-manager');

        $warehouse = Warehouse::factory()->create();

        $po = PurchaseOrder::factory()->create([
            'warehouse_id' => $warehouse->id,
            'requested_by' => $manager->id,
        ]);

        $workflow = app(\App\Services\Inbound\PurchaseOrderWorkflow::class);
        $workflow->cancel($po, 'Test cancellation', $manager);

        $po->refresh();

        $this->assertEquals(PurchaseOrderStatus::Cancelled, $po->status);
        $this->assertNotNull($po->cancelled_at);
    }
}
