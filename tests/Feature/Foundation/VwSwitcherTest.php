<?php

namespace Tests\Feature\Foundation;

use App\Livewire\VwSwitcher;
use App\Models\Department;
use App\Models\User;
use App\Models\VirtualWarehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression tests for Bug 4: VwSwitcher visibility.
 *
 * - dept-user with 2 active VWs  → switcher is visible (show=true)
 * - dept-user with 1 active VW   → switcher is hidden  (show=false)
 * - admin / manager              → switcher is always hidden (they see all VWs, no switching needed)
 */
class VwSwitcherTest extends TestCase
{
    use RefreshDatabase;

    private Department $dept;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->dept = Department::create(['name' => 'Test Dept', 'code' => 'TD', 'is_active' => true]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeDeptUser(array $vwIds): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('department-user');
        $user->virtualWarehouses()->attach($vwIds);
        return $user;
    }

    private function makeVw(string $code): VirtualWarehouse
    {
        return VirtualWarehouse::create([
            'name'          => "VW {$code}",
            'code'          => $code,
            'department_id' => $this->dept->id,
            'is_active'     => true,
        ]);
    }

    // ─── Tests ───────────────────────────────────────────────────────────────

    /**
     * When the outer wrapper div is hidden, Blade renders:
     *   style="display:none"  data-vw-count="N"
     * (extra whitespace because of @if/@endif markers in the template).
     * When visible, style="display:none" does NOT appear adjacent to data-vw-count.
     * We use regex so whitespace variation between attributes is tolerated.
     */
    private function assertSwitcherVisible(string $html, int $expectedCount): void
    {
        expect($html)
            ->toContain('data-vw-count="' . $expectedCount . '"')
            ->not->toMatch('/style="display:none"\s+data-vw-count="' . $expectedCount . '"/');
    }

    private function assertSwitcherHidden(string $html, int $expectedCount): void
    {
        expect($html)->toMatch('/style="display:none"\s+data-vw-count="' . $expectedCount . '"/');
    }

    public function test_switcher_is_visible_for_dept_user_with_two_warehouses(): void
    {
        $vw1 = $this->makeVw('T1');
        $vw2 = $this->makeVw('T2');
        $user = $this->makeDeptUser([$vw1->id, $vw2->id]);

        $html = Livewire::actingAs($user)
            ->test(VwSwitcher::class)
            ->html();

        $this->assertSwitcherVisible($html, 2);
    }

    public function test_switcher_is_hidden_for_dept_user_with_one_warehouse(): void
    {
        $vw = $this->makeVw('T1');
        $user = $this->makeDeptUser([$vw->id]);

        $html = Livewire::actingAs($user)
            ->test(VwSwitcher::class)
            ->html();

        $this->assertSwitcherHidden($html, 1);
    }

    public function test_switcher_is_hidden_for_dept_user_with_no_warehouses(): void
    {
        $user = $this->makeDeptUser([]);

        $html = Livewire::actingAs($user)
            ->test(VwSwitcher::class)
            ->html();

        $this->assertSwitcherHidden($html, 0);
    }

    public function test_switcher_is_hidden_for_warehouse_manager(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $manager->assignRole('warehouse-manager');

        $html = Livewire::actingAs($manager)
            ->test(VwSwitcher::class)
            ->html();

        $this->assertSwitcherHidden($html, 0);
    }

    public function test_switcher_is_hidden_for_system_admin(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('system-admin');

        $html = Livewire::actingAs($admin)
            ->test(VwSwitcher::class)
            ->html();

        $this->assertSwitcherHidden($html, 0);
    }
}
