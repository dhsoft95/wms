<?php

namespace Tests\Feature\Foundation;

use App\Actions\Foundation\DeleteDepartmentAction;
use App\Actions\Foundation\DeleteVirtualWarehouseAction;
use App\Exceptions\InUseException;
use App\Models\Department;
use App\Models\Item;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for Bug 3: VirtualWarehouses and Departments with users or items
 * must not be hard-deleted; they should be deactivated or blocked.
 */
class DepartmentVwDeletionTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;
    private VirtualWarehouse $vw;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->department = Department::create(['name' => 'Test Dept', 'code' => 'TD', 'is_active' => true]);
        $this->vw = VirtualWarehouse::create([
            'name'          => 'Test VW',
            'code'          => 'TVW',
            'department_id' => $this->department->id,
            'is_active'     => true,
        ]);
    }

    // ─── VW deletion ─────────────────────────────────────────────────────────

    public function test_vw_with_users_cannot_be_deleted(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('department-user');
        $user->virtualWarehouses()->attach($this->vw->id);

        $this->expectException(InUseException::class);

        DeleteVirtualWarehouseAction::execute($this->vw);
    }

    public function test_vw_with_items_cannot_be_deleted(): void
    {
        $uom = UnitOfMeasure::create(['code' => 'PCS', 'name' => 'Piece', 'type' => 'count', 'is_active' => true]);

        Item::create([
            'sku'                          => 'TST-001',
            'name'                         => 'Test Item',
            'base_uom_id'                  => $uom->id,
            'default_virtual_warehouse_id' => $this->vw->id,
            'is_active'                    => true,
        ]);

        $this->expectException(InUseException::class);

        DeleteVirtualWarehouseAction::execute($this->vw);
    }

    public function test_vw_with_soft_deleted_items_cannot_be_deleted(): void
    {
        $uom = UnitOfMeasure::create(['code' => 'PCS', 'name' => 'Piece', 'type' => 'count', 'is_active' => true]);

        $item = Item::create([
            'sku'                          => 'TST-002',
            'name'                         => 'Deleted Item',
            'base_uom_id'                  => $uom->id,
            'default_virtual_warehouse_id' => $this->vw->id,
            'is_active'                    => true,
        ]);
        $item->delete();

        $this->expectException(InUseException::class);

        DeleteVirtualWarehouseAction::execute($this->vw);
    }

    public function test_vw_without_users_or_items_is_deleted(): void
    {
        DeleteVirtualWarehouseAction::execute($this->vw);

        $this->assertSoftDeleted('virtual_warehouses', ['id' => $this->vw->id]);
    }

    // ─── Department deletion ──────────────────────────────────────────────────

    public function test_department_with_vws_is_deactivated_not_deleted(): void
    {
        $result = DeleteDepartmentAction::execute($this->department);

        expect($result)->toBe('deactivated');
        $this->assertDatabaseHas('departments', ['id' => $this->department->id, 'is_active' => false]);
        $this->assertNotSoftDeleted('departments', ['id' => $this->department->id]);
    }

    public function test_department_with_users_is_deactivated_not_deleted(): void
    {
        // No VWs — only a user
        $this->vw->delete();

        $user = User::factory()->create(['department_id' => $this->department->id, 'status' => 'active']);

        $result = DeleteDepartmentAction::execute($this->department->fresh());

        expect($result)->toBe('deactivated');
    }

    public function test_department_without_users_or_vws_is_deleted(): void
    {
        // Remove the VW that was created in setUp
        $this->vw->forceDelete();

        $result = DeleteDepartmentAction::execute($this->department->fresh());

        expect($result)->toBe('deleted');
        $this->assertSoftDeleted('departments', ['id' => $this->department->id]);
    }

    // ─── Policy ──────────────────────────────────────────────────────────────

    public function test_department_policy_denies_delete_when_vws_exist(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('system-admin');

        // Gate::before allows system-admin to bypass; test with a manager instead
        $manager = User::factory()->create(['status' => 'active']);
        $manager->syncPermissions(['departments.manage']);

        expect($manager->can('delete', $this->department))->toBeFalse();
    }

    public function test_department_policy_allows_delete_when_empty(): void
    {
        $this->vw->forceDelete();

        $manager = User::factory()->create(['status' => 'active']);
        $manager->syncPermissions(['departments.manage']);

        expect($manager->can('delete', $this->department->fresh()))->toBeTrue();
    }
}
