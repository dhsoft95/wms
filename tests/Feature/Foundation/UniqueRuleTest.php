<?php

namespace Tests\Feature\Foundation;

use App\Livewire\Admin\Departments\Index as DepartmentsIndex;
use App\Livewire\Admin\Partners\Create as PartnerCreate;
use App\Models\Department;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression tests for Bug 1: Rule::unique() crashes with BadMethodCallException
 * when ->withTrashed() / ->withoutTrashed() are called on it.
 *
 * Each test proves the form returns a clean validation error instead of crashing.
 */
class UniqueRuleTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->manager = User::factory()->create(['status' => 'active']);
        $this->manager->assignRole('warehouse-manager');
    }

    // ─── Partner Create ───────────────────────────────────────────────────────

    public function test_partner_create_duplicate_code_returns_validation_error(): void
    {
        Partner::create(['code' => 'P001', 'name' => 'Existing Partner', 'type' => ['supplier'], 'is_active' => true]);

        Livewire::actingAs($this->manager)
            ->test(PartnerCreate::class)
            ->set('code', 'P001')
            ->set('name', 'New Partner')
            ->set('types', ['supplier'])
            ->call('save')
            ->assertHasErrors(['code' => 'unique']);
    }

    public function test_partner_create_unique_code_passes_validation(): void
    {
        Livewire::actingAs($this->manager)
            ->test(PartnerCreate::class)
            ->set('code', 'P-UNIQUE')
            ->set('name', 'Unique Partner')
            ->set('types', ['supplier'])
            ->set('country', 'Tanzania')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('partners', ['code' => 'P-UNIQUE']);
    }

    public function test_partner_create_soft_deleted_code_is_also_rejected(): void
    {
        $existing = Partner::create(['code' => 'P-DEL', 'name' => 'Deleted Partner', 'type' => ['supplier'], 'is_active' => true]);
        $existing->delete();

        // Rule::unique queries all rows including soft-deleted — must still reject
        Livewire::actingAs($this->manager)
            ->test(PartnerCreate::class)
            ->set('code', 'P-DEL')
            ->set('name', 'Revived Partner')
            ->set('types', ['supplier'])
            ->call('save')
            ->assertHasErrors(['code' => 'unique']);
    }

    // ─── Department code (was using withoutTrashed) ───────────────────────────

    public function test_department_create_duplicate_code_returns_validation_error(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('system-admin');

        Department::create(['name' => 'Existing Dept', 'code' => 'EX', 'is_active' => true]);

        Livewire::actingAs($admin)
            ->test(DepartmentsIndex::class)
            ->call('openCreateDept')
            ->set('deptForm.name', 'Duplicate Dept')
            ->set('deptForm.code', 'EX')
            ->call('saveDept')
            ->assertHasErrors(['deptForm.code' => 'unique']);
    }
}
