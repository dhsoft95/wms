<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_six_roles_are_seeded(): void
    {
        $roles = Role::pluck('name');

        $this->assertTrue($roles->contains('system-admin'));
        $this->assertTrue($roles->contains('warehouse-manager'));
        $this->assertTrue($roles->contains('department-user'));
        $this->assertTrue($roles->contains('storekeeper'));
        $this->assertTrue($roles->contains('scanner-operator'));
        $this->assertTrue($roles->contains('viewer-auditor'));
    }

    public function test_system_admin_bypasses_all_gate_checks(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('system-admin');

        $this->actingAs($admin);

        $this->assertTrue($admin->can('users.manage'));
        $this->assertTrue($admin->can('roles.manage'));
        $this->assertTrue($admin->can('departments.manage'));
        $this->assertTrue($admin->can('some.nonexistent.permission'));
    }

    public function test_viewer_auditor_cannot_create_or_approve(): void
    {
        $viewer = User::factory()->create(['status' => 'active']);
        $viewer->assignRole('viewer-auditor');

        $this->actingAs($viewer);

        $this->assertFalse($viewer->can('orders.create'));
        $this->assertFalse($viewer->can('adjustments.approve'));
        $this->assertFalse($viewer->can('users.manage'));
        $this->assertTrue($viewer->can('reports.view'));
        $this->assertTrue($viewer->can('inventory.view'));
    }

    public function test_scanner_operator_has_only_scan_permissions(): void
    {
        $scanner = User::factory()->create(['status' => 'active']);
        $scanner->assignRole('scanner-operator');

        $this->actingAs($scanner);

        $this->assertTrue($scanner->can('scan.receive'));
        $this->assertTrue($scanner->can('scan.putaway'));
        $this->assertFalse($scanner->can('orders.create'));
        $this->assertFalse($scanner->can('users.manage'));
    }
}
