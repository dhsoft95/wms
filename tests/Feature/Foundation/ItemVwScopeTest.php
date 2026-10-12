<?php

namespace Tests\Feature\Foundation;

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Partner;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Scopes\VirtualWarehouseScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemVwScopeTest extends TestCase
{
    use RefreshDatabase;

    private Department $dept;
    private VirtualWarehouse $vw1;
    private VirtualWarehouse $vw2;
    private UnitOfMeasure $pcs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->dept = Department::create([
            'name'      => 'Test Dept',
            'code'      => 'TD',
            'is_active' => true,
        ]);

        $this->vw1 = VirtualWarehouse::create([
            'code'          => 'VW1',
            'name'          => 'Virtual Warehouse 1',
            'department_id' => $this->dept->id,
            'is_active'     => true,
        ]);

        $this->vw2 = VirtualWarehouse::create([
            'code'          => 'VW2',
            'name'          => 'Virtual Warehouse 2',
            'department_id' => $this->dept->id,
            'is_active'     => true,
        ]);

        $this->pcs = UnitOfMeasure::create([
            'code' => 'PCS',
            'name' => 'Pieces',
            'type' => 'count',
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeItem(?int $vwId, string $sku): Item
    {
        // Create without auth so no VW scope restriction is applied during seeding
        return Item::withoutGlobalScopes()->create([
            'sku'                          => $sku,
            'name'                         => "Item {$sku}",
            'base_uom_id'                  => $this->pcs->id,
            'track_batch'                  => false,
            'track_expiry'                 => false,
            'track_serial'                 => false,
            'is_active'                    => true,
            'default_virtual_warehouse_id' => $vwId,
        ]);
    }

    private function departmentUser(VirtualWarehouse ...$vws): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('department-user');
        $user->virtualWarehouses()->sync(collect($vws)->pluck('id'));
        return $user;
    }

    private function adminUser(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('system-admin');
        return $user;
    }

    private function managerUser(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('warehouse-manager');
        return $user;
    }

    // ─── Tests ───────────────────────────────────────────────────────────────

    public function test_department_user_sees_only_their_vw_items(): void
    {
        $this->makeItem($this->vw1->id, 'SKU-A');
        $this->makeItem($this->vw2->id, 'SKU-B');
        $this->makeItem(null, 'SKU-C');  // no VW — not visible to restricted users

        $user = $this->departmentUser($this->vw1);
        $this->actingAs($user);
        $user->clearVwCache();

        $items = Item::all();

        expect($items)->toHaveCount(1);
        expect($items->first()->sku)->toBe('SKU-A');
    }

    public function test_admin_sees_all_items(): void
    {
        $this->makeItem($this->vw1->id, 'SKU-A');
        $this->makeItem($this->vw2->id, 'SKU-B');
        $this->makeItem(null, 'SKU-C');

        $admin = $this->adminUser();
        $this->actingAs($admin);

        expect(Item::count())->toBe(3);
    }

    public function test_warehouse_manager_sees_all_items(): void
    {
        $this->makeItem($this->vw1->id, 'SKU-A');
        $this->makeItem($this->vw2->id, 'SKU-B');
        $this->makeItem(null, 'SKU-C');

        $manager = $this->managerUser();
        $this->actingAs($manager);

        expect(Item::count())->toBe(3);
    }

    public function test_department_user_gets_404_on_other_vw_item_show_page(): void
    {
        // Item belongs to vw2; user only has access to vw1
        $item = $this->makeItem($this->vw2->id, 'SKU-FOREIGN');

        $user = $this->departmentUser($this->vw1);
        $this->actingAs($user);
        $user->clearVwCache();

        // The global VW scope filters out vw2 items, so route model binding returns 404
        $this->get(route('admin.items.show', $item->id))
            ->assertStatus(404);
    }

    public function test_department_user_can_access_own_vw_item_show_page(): void
    {
        $item = $this->makeItem($this->vw1->id, 'SKU-OWN');

        $user = $this->departmentUser($this->vw1);
        $this->actingAs($user);
        $user->clearVwCache();

        $this->get(route('admin.items.show', $item->id))
            ->assertStatus(200);
    }

    public function test_partners_are_global_no_vw_scope(): void
    {
        Partner::create([
            'code'      => 'SUPP-1',
            'name'      => 'Supplier One',
            'type'      => ['supplier'],
            'is_active' => true,
        ]);

        Partner::create([
            'code'      => 'CUST-1',
            'name'      => 'Customer One',
            'type'      => ['customer'],
            'is_active' => true,
        ]);

        $deptUser = $this->departmentUser($this->vw1);
        $this->actingAs($deptUser);
        $deptUser->clearVwCache();

        // Partners have no VW scope — dept user should see all
        expect(Partner::count())->toBe(2);
    }
}
