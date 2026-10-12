<?php

namespace Tests\Feature\Foundation;

use App\Actions\Foundation\SaveItemCategoryAction;
use App\Actions\Foundation\SaveUomAction;
use App\Actions\Foundation\ToggleWarehouseActiveAction;
use App\Exceptions\InUseException;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemUom;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InUseProtectionTest extends TestCase
{
    use RefreshDatabase;

    private UnitOfMeasure $pcs;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->pcs = UnitOfMeasure::create([
            'code'      => 'PCS',
            'name'      => 'Pieces',
            'type'      => 'count',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'code'      => 'TST',
            'name'      => 'Test Warehouse',
            'is_active' => true,
        ]);
    }

    // ─── UoM ─────────────────────────────────────────────────────────────────

    public function test_uom_deletion_blocked_when_used_as_base_uom(): void
    {
        Item::withoutGlobalScopes()->create([
            'sku'          => 'ITEM-001',
            'name'         => 'Test Item',
            'base_uom_id'  => $this->pcs->id,
            'track_batch'  => false,
            'track_expiry' => false,
            'track_serial' => false,
            'is_active'    => true,
        ]);

        $this->expectException(InUseException::class);

        SaveUomAction::delete($this->pcs->id);
    }

    public function test_uom_deletion_blocked_when_used_in_item_uoms(): void
    {
        $ctn = UnitOfMeasure::create(['code' => 'CTN', 'name' => 'Carton', 'type' => 'count', 'is_active' => true]);

        $item = Item::withoutGlobalScopes()->create([
            'sku'          => 'ITEM-002',
            'name'         => 'Test Item 2',
            'base_uom_id'  => $this->pcs->id,
            'track_batch'  => false,
            'track_expiry' => false,
            'track_serial' => false,
            'is_active'    => true,
        ]);

        ItemUom::create([
            'item_id'         => $item->id,
            'uom_id'          => $ctn->id,
            'factor_to_base'  => 12,
            'is_default_purchase' => false,
            'is_default_issue'    => false,
        ]);

        $this->expectException(InUseException::class);

        SaveUomAction::delete($ctn->id);
    }

    public function test_uom_deletion_allowed_when_not_in_use(): void
    {
        $spare = UnitOfMeasure::create(['code' => 'SPARE', 'name' => 'Spare', 'type' => 'count', 'is_active' => true]);

        SaveUomAction::delete($spare->id);

        expect(UnitOfMeasure::find($spare->id))->toBeNull();
    }

    public function test_uom_deactivation_blocked_when_in_use(): void
    {
        Item::withoutGlobalScopes()->create([
            'sku'          => 'ITEM-003',
            'name'         => 'Test Item 3',
            'base_uom_id'  => $this->pcs->id,
            'track_batch'  => false,
            'track_expiry' => false,
            'track_serial' => false,
            'is_active'    => true,
        ]);

        $this->expectException(InUseException::class);

        SaveUomAction::deactivate($this->pcs->id);
    }

    // ─── ItemCategory ─────────────────────────────────────────────────────────

    public function test_category_deactivation_blocked_when_items_use_it(): void
    {
        $category = ItemCategory::create(['name' => 'In-Use Category', 'is_active' => true]);

        Item::withoutGlobalScopes()->create([
            'sku'          => 'ITEM-004',
            'name'         => 'Categorised Item',
            'base_uom_id'  => $this->pcs->id,
            'category_id'  => $category->id,
            'track_batch'  => false,
            'track_expiry' => false,
            'track_serial' => false,
            'is_active'    => true,
        ]);

        $this->expectException(InUseException::class);

        SaveItemCategoryAction::toggle($category);
    }

    public function test_category_deactivation_allowed_when_no_items(): void
    {
        $category = ItemCategory::create(['name' => 'Unused Category', 'is_active' => true]);

        $state = SaveItemCategoryAction::toggle($category);

        expect($state)->toBe('deactivated');
        expect($category->fresh()->is_active)->toBeFalse();
    }

    public function test_category_activation_always_allowed(): void
    {
        $category = ItemCategory::create(['name' => 'Inactive Category', 'is_active' => false]);

        Item::withoutGlobalScopes()->create([
            'sku'          => 'ITEM-005',
            'name'         => 'Item with inactive category',
            'base_uom_id'  => $this->pcs->id,
            'category_id'  => $category->id,
            'track_batch'  => false,
            'track_expiry' => false,
            'track_serial' => false,
            'is_active'    => true,
        ]);

        $state = SaveItemCategoryAction::toggle($category);

        expect($state)->toBe('activated');
        expect($category->fresh()->is_active)->toBeTrue();
    }

    // ─── Warehouse ────────────────────────────────────────────────────────────

    public function test_warehouse_deactivation_blocked_when_has_locations(): void
    {
        Location::create([
            'warehouse_id' => $this->warehouse->id,
            'segment_code' => 'A',
            'code'         => 'TST-A',
            'level'        => 'zone',
            'type'         => 'storage',
            'status'       => 'active',
            'path'         => '/',
            'is_pickable'  => true,
        ]);

        $this->expectException(InUseException::class);

        ToggleWarehouseActiveAction::deactivate($this->warehouse);
    }

    public function test_warehouse_deactivation_allowed_when_no_locations(): void
    {
        $empty = Warehouse::create(['code' => 'EMP', 'name' => 'Empty Warehouse', 'is_active' => true]);

        ToggleWarehouseActiveAction::deactivate($empty);

        expect($empty->fresh()->is_active)->toBeFalse();
    }

    public function test_warehouse_activation_always_allowed(): void
    {
        $inactive = Warehouse::create(['code' => 'OFF', 'name' => 'Offline Warehouse', 'is_active' => false]);

        ToggleWarehouseActiveAction::activate($inactive);

        expect($inactive->fresh()->is_active)->toBeTrue();
    }
}
