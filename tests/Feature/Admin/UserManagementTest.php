<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Users\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_system_admin_can_view_user_list(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('system-admin');

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk();
    }

    public function test_viewer_auditor_cannot_access_user_management(): void
    {
        $viewer = User::factory()->create(['status' => 'active']);
        $viewer->assignRole('viewer-auditor');

        $this->actingAs($viewer)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_admin_can_suspend_a_user(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('system-admin');

        $target = User::factory()->create(['status' => 'active']);
        $target->assignRole('storekeeper');

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('suspend', $target->id);

        $this->assertSame('suspended', $target->fresh()->status);
    }

    public function test_admin_cannot_suspend_themselves(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('system-admin');

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('suspend', $admin->id)
            ->assertForbidden();
    }

    public function test_last_system_admin_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('system-admin');

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('deleteUser', $admin->id)
            ->assertForbidden();
    }
}
