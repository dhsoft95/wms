<?php

namespace Tests\Feature\Orders;

use App\Livewire\Orders\Index;
use App\Models\DeliveryOrder;
use App\Models\PurchaseOrder;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use Database\Seeders\FoundationDefaultsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrdersIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(FoundationDefaultsSeeder::class);
    }

    public function test_department_user_sees_new_order_button(): void
    {
        $user = User::factory()->create();
        $user->assignRole('department-user');

        $component = Livewire::actingAs($user)->test(Index::class);
        $component->assertSee('New Order');
    }

    public function test_storekeeper_does_not_see_new_order_button(): void
    {
        $user = User::factory()->create();
        $user->assignRole('storekeeper');

        $component = Livewire::actingAs($user)->test(Index::class);
        $component->assertDontSee('New Order');
    }

    public function test_po_tab_shows_only_purchase_orders(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('warehouse-manager');

        $wh = Warehouse::factory()->create();
        $vw = VirtualWarehouse::factory()->create();

        PurchaseOrder::factory()->create(['number' => 'PO-TEST-001', 'warehouse_id' => $wh->id]);
        DeliveryOrder::factory()->create(['number' => 'DO-TEST-001', 'virtual_warehouse_id' => $vw->id, 'warehouse_id' => $wh->id]);

        $component = Livewire::actingAs($manager)
            ->test(Index::class, ['tab' => 'po']);

        $component->assertSee('PO-TEST-001');
        $component->assertDontSee('DO-TEST-001');
    }

    public function test_mrn_tab_shows_only_delivery_orders(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('warehouse-manager');

        $wh = Warehouse::factory()->create();
        $vw = VirtualWarehouse::factory()->create();

        PurchaseOrder::factory()->create(['number' => 'PO-TEST-002', 'warehouse_id' => $wh->id]);
        DeliveryOrder::factory()->create(['number' => 'DO-TEST-002', 'virtual_warehouse_id' => $vw->id, 'warehouse_id' => $wh->id]);

        $component = Livewire::actingAs($manager)
            ->test(Index::class, ['tab' => 'mrn']);

        $component->assertSee('DO-TEST-002');
        $component->assertDontSee('PO-TEST-002');
    }

    public function test_type_modal_shows_cards_based_on_permissions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('department-user');

        $component = Livewire::actingAs($user)
            ->test(Index::class)
            ->set('showTypeModal', true);

        // department-user has po.create and orders.create but NOT transfers.create
        $component->assertSee('Purchase Order');
        $component->assertSee('MRN');
    }

    public function test_all_tab_shows_all_order_types(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('warehouse-manager');

        $wh = Warehouse::factory()->create();
        $vw = VirtualWarehouse::factory()->create();

        PurchaseOrder::factory()->create(['number' => 'PO-COMBINED', 'warehouse_id' => $wh->id]);
        DeliveryOrder::factory()->create(['number' => 'DO-COMBINED', 'virtual_warehouse_id' => $vw->id, 'warehouse_id' => $wh->id]);

        $component = Livewire::actingAs($manager)->test(Index::class);
        $component->assertSee('PO-COMBINED');
        $component->assertSee('DO-COMBINED');
    }
}
