<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DepartmentScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_scanner_operator_redirected_to_scan_portal(): void
    {
        $scanner = User::factory()->create([
            'status'   => 'active',
            'password' => Hash::make('password'),
        ]);
        $scanner->assignRole('scanner-operator');

        Volt::test('auth.login')
            ->set('email', $scanner->email)
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('scan.home'));
    }

    public function test_scanner_cannot_access_main_app_routes(): void
    {
        $scanner = User::factory()->create(['status' => 'active']);
        $scanner->assignRole('scanner-operator');

        $this->actingAs($scanner)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_warehouse_manager_can_access_dashboard(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $manager->assignRole('warehouse-manager');

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_departments_are_seeded(): void
    {
        $this->seed(DepartmentSeeder::class);

        $this->assertGreaterThan(0, Department::count());
        $this->assertTrue(Department::where('code', 'GW')->exists());
    }
}
