<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_scanner_operator_redirected_to_scan_portal_after_login(): void
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

    public function test_scanner_operator_cannot_access_main_dashboard(): void
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
}
