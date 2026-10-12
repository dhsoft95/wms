<?php

namespace Tests\Feature\Foundation;

use App\Models\User;
use Database\Seeders\FoundationDefaultsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(FoundationDefaultsSeeder::class);
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    private function createUser(string $role, string $status = 'active'): User
    {
        $user = User::factory()->create(['status' => $status]);
        $user->assignRole($role);
        return $user;
    }

    // ─── Settings ────────────────────────────────────────────────────────────

    public function test_system_admin_can_access_settings(): void
    {
        $admin = $this->createUser('system-admin');

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertStatus(200);
    }

    public function test_warehouse_manager_cannot_access_settings(): void
    {
        $manager = $this->createUser('warehouse-manager');

        $this->actingAs($manager)
            ->get('/admin/settings')
            ->assertStatus(403);
    }

    // ─── Items ───────────────────────────────────────────────────────────────

    public function test_system_admin_can_access_items(): void
    {
        $admin = $this->createUser('system-admin');

        $this->actingAs($admin)
            ->get('/admin/items')
            ->assertStatus(200);
    }

    public function test_warehouse_manager_can_access_items(): void
    {
        $manager = $this->createUser('warehouse-manager');

        $this->actingAs($manager)
            ->get('/admin/items')
            ->assertStatus(200);
    }

    public function test_department_user_can_access_items(): void
    {
        $deptUser = $this->createUser('department-user');

        $this->actingAs($deptUser)
            ->get('/admin/items')
            ->assertStatus(200);
    }

    public function test_department_user_cannot_create_items(): void
    {
        // department-user has items.view but not items.create
        $deptUser = $this->createUser('department-user');

        $this->actingAs($deptUser)
            ->get('/admin/items/create')
            ->assertStatus(403);
    }

    // ─── Partners ────────────────────────────────────────────────────────────

    public function test_warehouse_manager_can_access_partners(): void
    {
        $manager = $this->createUser('warehouse-manager');

        $this->actingAs($manager)
            ->get('/admin/partners')
            ->assertStatus(200);
    }

    public function test_scanner_operator_cannot_access_partners(): void
    {
        // scanner-operator has no partners.view permission
        $scanner = $this->createUser('scanner-operator');

        $this->actingAs($scanner)
            ->get('/admin/partners')
            ->assertStatus(403);
    }

    // ─── Locations ───────────────────────────────────────────────────────────

    public function test_system_admin_can_access_locations(): void
    {
        $admin = $this->createUser('system-admin');

        $this->actingAs($admin)
            ->get('/admin/locations')
            ->assertStatus(200);
    }

    public function test_department_user_cannot_access_locations(): void
    {
        // department-user has no locations.view permission
        $deptUser = $this->createUser('department-user');

        $this->actingAs($deptUser)
            ->get('/admin/locations')
            ->assertStatus(403);
    }
}
