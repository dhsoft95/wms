<?php

namespace Tests\Feature\Foundation;

use App\Livewire\Admin\Departments\Index;
use App\Models\Department;
use App\Models\User;
use App\Models\VirtualWarehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DepartmentModalTest extends TestCase
{
    use RefreshDatabase;

    private User       $admin;
    private Department $dept;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('system-admin');

        $this->dept = Department::create(['name' => 'Operations', 'code' => 'OPS', 'is_active' => true]);
    }

    // ─── Dept modal ───────────────────────────────────────────────────────────

    public function test_open_create_dept_modal(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('openCreateDept')
            ->assertSet('showDeptModal', true)
            ->assertSet('editingDeptId', null);
    }

    public function test_open_edit_dept_modal_fills_form(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('openEditDept', $this->dept->id)
            ->assertSet('showDeptModal', true)
            ->assertSet('editingDeptId', $this->dept->id)
            ->assertSet('deptForm.name', 'Operations')
            ->assertSet('deptForm.code', 'OPS');
    }

    public function test_close_dept_modal(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('openCreateDept')
            ->assertSet('showDeptModal', true)
            ->call('closeDeptModal')
            ->assertSet('showDeptModal', false);
    }

    public function test_create_dept_persists_and_closes_modal(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('openCreateDept')
            ->set('deptForm.name', 'Finance')
            ->set('deptForm.code', 'FIN')
            ->call('saveDept')
            ->assertSet('showDeptModal', false)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('departments', ['name' => 'Finance', 'code' => 'FIN']);
    }

    public function test_edit_dept_updates_record(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('openEditDept', $this->dept->id)
            ->set('deptForm.name', 'Operations Updated')
            ->call('saveDept')
            ->assertSet('showDeptModal', false)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('departments', ['id' => $this->dept->id, 'name' => 'Operations Updated']);
    }

    public function test_save_dept_validates_required_fields(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('openCreateDept')
            ->set('deptForm.name', '')
            ->set('deptForm.code', '')
            ->call('saveDept')
            ->assertHasErrors(['deptForm.name', 'deptForm.code']);
    }

    public function test_save_dept_validates_duplicate_code(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('openCreateDept')
            ->set('deptForm.name', 'Another Dept')
            ->set('deptForm.code', 'OPS') // already taken
            ->call('saveDept')
            ->assertHasErrors(['deptForm.code']);
    }

    public function test_edit_dept_allows_same_code(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('openEditDept', $this->dept->id)
            ->set('deptForm.name', 'Operations Renamed')
            ->set('deptForm.code', 'OPS') // same code — should be fine
            ->call('saveDept')
            ->assertHasNoErrors()
            ->assertSet('showDeptModal', false);
    }

    public function test_save_dept_validates_report_email_format(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('openCreateDept')
            ->set('deptForm.name', 'Finance')
            ->set('deptForm.code', 'FIN')
            ->set('deptForm.reportEmail', 'not-an-email')
            ->call('saveDept')
            ->assertHasErrors(['deptForm.reportEmail']);
    }

    // ─── VW modal ─────────────────────────────────────────────────────────────

    public function test_open_create_vw_modal(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('openCreateVw', $this->dept->id)
            ->assertSet('showVwModal', true)
            ->assertSet('vwForDeptId', $this->dept->id)
            ->assertSet('editingVwId', null);
    }

    public function test_create_vw_persists_and_closes_modal(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('openCreateVw', $this->dept->id)
            ->set('vwForm.name', 'Main Store')
            ->set('vwForm.code', 'MS')
            ->call('saveVw')
            ->assertSet('showVwModal', false)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('virtual_warehouses', [
            'name'          => 'Main Store',
            'code'          => 'MS',
            'department_id' => $this->dept->id,
        ]);
    }

    public function test_open_edit_vw_modal_fills_form(): void
    {
        $vw = VirtualWarehouse::create(['name' => 'Old Name', 'code' => 'OLD', 'department_id' => $this->dept->id, 'is_active' => true]);

        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('openEditVw', $vw->id)
            ->assertSet('showVwModal', true)
            ->assertSet('editingVwId', $vw->id)
            ->assertSet('vwForm.name', 'Old Name')
            ->assertSet('vwForm.code', 'OLD');
    }

    public function test_edit_vw_updates_record(): void
    {
        $vw = VirtualWarehouse::create(['name' => 'Old Name', 'code' => 'OLD', 'department_id' => $this->dept->id, 'is_active' => true]);

        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('openEditVw', $vw->id)
            ->set('vwForm.name', 'New Name')
            ->call('saveVw')
            ->assertSet('showVwModal', false)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('virtual_warehouses', ['id' => $vw->id, 'name' => 'New Name']);
    }

    public function test_save_vw_validates_required_fields(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('openCreateVw', $this->dept->id)
            ->set('vwForm.name', '')
            ->set('vwForm.code', '')
            ->call('saveVw')
            ->assertHasErrors(['vwForm.name', 'vwForm.code']);
    }

    public function test_close_vw_modal(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('openCreateVw', $this->dept->id)
            ->assertSet('showVwModal', true)
            ->call('closeVwModal')
            ->assertSet('showVwModal', false);
    }

    // ─── Confirm modal ────────────────────────────────────────────────────────

    public function test_confirm_delete_for_empty_dept_is_not_in_use(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('openConfirmDeleteOrDeactivate', 'dept', $this->dept->id)
            ->assertSet('showConfirmModal', true)
            ->assertSet('confirmInUse', false)
            ->assertSet('confirmName', 'Operations')
            ->assertSet('confirmAction', 'delete-or-deactivate');
    }

    public function test_confirm_for_dept_with_vw_shows_in_use(): void
    {
        VirtualWarehouse::create(['name' => 'VW1', 'code' => 'V1', 'department_id' => $this->dept->id, 'is_active' => true]);

        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('openConfirmDeleteOrDeactivate', 'dept', $this->dept->id)
            ->assertSet('confirmInUse', true)
            ->assertSet('confirmVwsCount', 1);
    }

    public function test_execute_confirm_deletes_empty_dept(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('openConfirmDeleteOrDeactivate', 'dept', $this->dept->id)
            ->call('executeConfirm')
            ->assertSet('showConfirmModal', false);

        $this->assertSoftDeleted('departments', ['id' => $this->dept->id]);
    }

    public function test_execute_confirm_deactivates_in_use_dept(): void
    {
        VirtualWarehouse::create(['name' => 'VW1', 'code' => 'V1', 'department_id' => $this->dept->id, 'is_active' => true]);

        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('openConfirmDeleteOrDeactivate', 'dept', $this->dept->id)
            ->call('executeConfirm');

        $this->assertDatabaseHas('departments', ['id' => $this->dept->id, 'is_active' => false]);
        $this->assertNotSoftDeleted('departments', ['id' => $this->dept->id]);
    }

    public function test_execute_confirm_activates_inactive_dept(): void
    {
        $this->dept->update(['is_active' => false]);

        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('openConfirmActivate', 'dept', $this->dept->id)
            ->assertSet('confirmAction', 'activate')
            ->call('executeConfirm')
            ->assertSet('showConfirmModal', false);

        $this->assertDatabaseHas('departments', ['id' => $this->dept->id, 'is_active' => true]);
    }

    public function test_confirm_delete_for_empty_vw(): void
    {
        $vw = VirtualWarehouse::create(['name' => 'Empty VW', 'code' => 'EV', 'department_id' => $this->dept->id, 'is_active' => true]);

        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('openConfirmDeleteOrDeactivate', 'vw', $vw->id)
            ->assertSet('confirmInUse', false)
            ->call('executeConfirm');

        $this->assertSoftDeleted('virtual_warehouses', ['id' => $vw->id]);
    }

    public function test_confirm_for_in_use_vw_deactivates_not_deletes(): void
    {
        $vw   = VirtualWarehouse::create(['name' => 'Busy VW', 'code' => 'BV', 'department_id' => $this->dept->id, 'is_active' => true]);
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('department-user');
        $user->virtualWarehouses()->attach($vw->id);

        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('openConfirmDeleteOrDeactivate', 'vw', $vw->id)
            ->assertSet('confirmInUse', true)
            ->assertSet('confirmUsersCount', 1)
            ->call('executeConfirm');

        $this->assertDatabaseHas('virtual_warehouses', ['id' => $vw->id, 'is_active' => false]);
        $this->assertNotSoftDeleted('virtual_warehouses', ['id' => $vw->id]);
    }

    public function test_confirm_modal_closes_on_cancel(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('openConfirmDeleteOrDeactivate', 'dept', $this->dept->id)
            ->assertSet('showConfirmModal', true)
            ->call('closeConfirmModal')
            ->assertSet('showConfirmModal', false);
    }

    // ─── Row expand ───────────────────────────────────────────────────────────

    public function test_toggle_expand_adds_dept(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('toggleExpand', $this->dept->id)
            ->assertSet('expandedDepts', [$this->dept->id]);
    }

    public function test_toggle_expand_collapses_expanded_dept(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Index::class)
            ->call('toggleExpand', $this->dept->id)
            ->call('toggleExpand', $this->dept->id)
            ->assertSet('expandedDepts', []);
    }
}
