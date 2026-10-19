<?php

namespace Tests\Feature\Foundation;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for Bug 2: department-user should only see
 * Dashboard, Stock on Hand, Orders, and Items in the nav.
 *
 * Verifies that the seeder and route middleware are correctly set up so that
 * a department-user cannot reach Inbound, Item Categories, or other restricted pages.
 */
class DepartmentUserNavTest extends TestCase
{
    use RefreshDatabase;

    private User $deptUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->deptUser = User::factory()->create(['status' => 'active']);
        $this->deptUser->assignRole('department-user');
    }

    // ─── Pages dept-user should NOT reach ────────────────────────────────────

    public function test_dept_user_cannot_access_inbound_containers(): void
    {
        $this->actingAs($this->deptUser)
            ->get(route('inbound.containers.index'))
            ->assertStatus(403);
    }

    public function test_dept_user_cannot_access_inbound_grn(): void
    {
        $this->actingAs($this->deptUser)
            ->get(route('inbound.grn.index'))
            ->assertStatus(403);
    }

    public function test_dept_user_cannot_access_item_categories(): void
    {
        $this->actingAs($this->deptUser)
            ->get(route('admin.item-categories.index'))
            ->assertStatus(403);
    }

    public function test_dept_user_can_access_warehouse_movements(): void
    {
        // dept-user has movements.view (they create movements from the Orders module).
        // The nav hides the direct link; the route itself remains accessible.
        $this->actingAs($this->deptUser)
            ->get(route('warehouse.movements.index'))
            ->assertOk();
    }

    public function test_dept_user_can_access_warehouse_transfers(): void
    {
        // Department-user has transfers.view and transfers.create — they create transfers
        $this->actingAs($this->deptUser)
            ->get(route('warehouse.transfers.index'))
            ->assertOk();
    }

    // ─── Pages dept-user SHOULD reach ────────────────────────────────────────

    public function test_dept_user_can_access_dashboard(): void
    {
        $this->actingAs($this->deptUser)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_dept_user_can_access_inventory_stock(): void
    {
        $this->actingAs($this->deptUser)
            ->get(route('inventory.stock.index'))
            ->assertOk();
    }

    public function test_dept_user_can_access_orders(): void
    {
        $this->actingAs($this->deptUser)
            ->get(route('outbound.orders.index'))
            ->assertOk();
    }

    public function test_dept_user_can_access_items(): void
    {
        $this->actingAs($this->deptUser)
            ->get(route('admin.items.index'))
            ->assertOk();
    }

    // ─── Warehouse-manager CAN access item categories ─────────────────────────

    public function test_warehouse_manager_can_access_item_categories(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $manager->assignRole('warehouse-manager');

        $this->actingAs($manager)
            ->get(route('admin.item-categories.index'))
            ->assertOk();
    }
}
