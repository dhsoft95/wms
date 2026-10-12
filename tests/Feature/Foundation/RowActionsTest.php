<?php

namespace Tests\Feature\Foundation;

use App\Livewire\Admin\Items\Index as ItemsIndex;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Models\Item;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RowActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('system-admin');
    }

    private function createUser(string $role, string $status = 'active'): User
    {
        $user = User::factory()->create(['status' => $status]);
        $user->assignRole($role);
        return $user;
    }

    private function createItem(bool $active = true): Item
    {
        $uom = UnitOfMeasure::firstOrCreate(
            ['code' => 'EA'],
            ['name' => 'Each', 'type' => 'count']
        );

        return Item::create([
            'sku'         => 'TST-' . uniqid(),
            'name'        => 'Test Item',
            'base_uom_id' => $uom->id,
            'is_active'   => $active,
        ]);
    }

    // ─── Users table ─────────────────────────────────────────────────────────

    public function test_admin_sees_manage_actions_for_active_user(): void
    {
        $this->createUser('department-user', 'active');

        Livewire::actingAs($this->admin)
            ->test(UsersIndex::class)
            ->assertSee('Edit')
            ->assertSee('Suspend')
            ->assertSee('Delete')
            ->assertDontSee('Resend Invite');
    }

    public function test_admin_sees_activate_for_suspended_user(): void
    {
        $this->createUser('department-user', 'suspended');

        Livewire::actingAs($this->admin)
            ->test(UsersIndex::class)
            ->assertSee('Activate');
    }

    public function test_admin_sees_resend_invite_for_invited_user(): void
    {
        $this->createUser('department-user', 'invited');

        Livewire::actingAs($this->admin)
            ->test(UsersIndex::class)
            ->assertSee('Resend Invite');
    }

    public function test_admin_does_not_see_resend_invite_for_active_user(): void
    {
        $this->createUser('department-user', 'active');

        Livewire::actingAs($this->admin)
            ->test(UsersIndex::class)
            ->assertDontSee('Resend Invite');
    }

    // ─── Items table ─────────────────────────────────────────────────────────

    public function test_admin_sees_edit_and_deactivate_for_active_item(): void
    {
        $this->createItem(true);

        Livewire::actingAs($this->admin)
            ->test(ItemsIndex::class)
            ->assertSee('Edit')
            ->assertSee('Deactivate')
            ->assertDontSee('Activate');
    }

    public function test_admin_sees_edit_and_activate_for_inactive_item(): void
    {
        $this->createItem(false);

        Livewire::actingAs($this->admin)
            ->test(ItemsIndex::class)
            ->assertSee('Edit')
            ->assertSee('Activate')
            ->assertDontSee('Deactivate');
    }

    public function test_warehouse_manager_cannot_see_edit_in_items(): void
    {
        $manager = $this->createUser('warehouse-manager');
        $this->createItem(true);

        Livewire::actingAs($manager)
            ->test(ItemsIndex::class)
            ->assertDontSee('Edit')
            ->assertDontSee('Deactivate');
    }

    public function test_viewer_auditor_cannot_see_edit_in_items(): void
    {
        $auditor = $this->createUser('viewer-auditor');
        $this->createItem(true);

        Livewire::actingAs($auditor)
            ->test(ItemsIndex::class)
            ->assertDontSee('Edit')
            ->assertDontSee('Deactivate');
    }
}
