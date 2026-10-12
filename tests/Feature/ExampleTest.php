<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_get_root_redirects_to_login(): void
    {
        $this->get('/')->assertRedirectToRoute('login');
    }

    public function test_authenticated_user_get_root_redirects_to_dashboard(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->get('/')->assertRedirectToRoute('dashboard');
    }

    public function test_scanner_operator_get_root_redirects_to_scan_portal(): void
    {
        $scanner = User::factory()->create(['status' => 'active']);
        $scanner->assignRole('scanner-operator');

        $this->actingAs($scanner)->get('/')->assertRedirectToRoute('scan.home');
    }
}
