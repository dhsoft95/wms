<?php

namespace Tests\Feature\Foundation;

use App\Enums\ItemBarcodeType;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\ItemUom;
use App\Models\UnitOfMeasure;
use App\Services\Foundation\UomConverter;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class ItemRulesTest extends TestCase
{
    use RefreshDatabase;

    private UnitOfMeasure $pcs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->pcs = UnitOfMeasure::create([
            'code' => 'PCS',
            'name' => 'Pieces',
            'type' => 'count',
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeItem(array $overrides = []): Item
    {
        return Item::withoutGlobalScopes()->create(array_merge([
            'sku'          => 'SKU-' . uniqid(),
            'name'         => 'Test Item',
            'base_uom_id'  => $this->pcs->id,
            'track_batch'  => false,
            'track_expiry' => false,
            'track_serial' => false,
            'is_active'    => true,
        ], $overrides));
    }

    // ─── Tests ───────────────────────────────────────────────────────────────

    public function test_enabling_track_expiry_forces_track_batch(): void
    {
        // track_expiry=true requires track_batch=true; both set → model saves fine
        $item = $this->makeItem([
            'track_expiry' => true,
            'track_batch'  => true,
        ]);

        expect($item->track_expiry)->toBeTrue();
        expect($item->track_batch)->toBeTrue();

        // Module-1 stubs should return false (no stock/transactions yet)
        expect($item->hasTransactions())->toBeFalse();
        expect($item->hasStock())->toBeFalse();
    }

    public function test_uom_converter_to_base(): void
    {
        $item = $this->makeItem();

        $ctn = UnitOfMeasure::create(['code' => 'CTN', 'name' => 'Carton', 'type' => 'count']);

        ItemUom::create([
            'item_id'             => $item->id,
            'uom_id'              => $ctn->id,
            'factor_to_base'      => 24,
            'is_default_purchase' => false,
            'is_default_issue'    => false,
        ]);

        $result = UomConverter::toBase($item, $ctn->id, 2);

        expect($result)->toBe('48.0000');
    }

    public function test_uom_converter_convert_between_uoms(): void
    {
        $item = $this->makeItem();

        $ctn = UnitOfMeasure::create(['code' => 'CTN', 'name' => 'Carton',  'type' => 'count']);
        $plt = UnitOfMeasure::create(['code' => 'PLT', 'name' => 'Pallet',  'type' => 'count']);

        ItemUom::create([
            'item_id'             => $item->id,
            'uom_id'              => $ctn->id,
            'factor_to_base'      => 24,   // 1 CTN = 24 PCS
            'is_default_purchase' => false,
            'is_default_issue'    => false,
        ]);

        ItemUom::create([
            'item_id'             => $item->id,
            'uom_id'              => $plt->id,
            'factor_to_base'      => 480,  // 1 PLT = 480 PCS
            'is_default_purchase' => false,
            'is_default_issue'    => false,
        ]);

        // 20 CTN → 20 * 24 = 480 PCS → 480 / 480 = 1 PLT
        $result = UomConverter::convert($item, $ctn->id, $plt->id, 20);

        expect($result)->toBe('1.0000');
    }

    public function test_uom_converter_base_to_base(): void
    {
        $item = $this->makeItem();

        // Converting from base (PCS) to base (PCS) — factor is effectively 1
        $result = UomConverter::toBase($item, $this->pcs->id, 5);

        expect($result)->toBe('5.0000');
    }

    public function test_barcode_must_be_globally_unique_across_items(): void
    {
        $item1 = $this->makeItem(['sku' => 'SKU-001']);
        $item2 = $this->makeItem(['sku' => 'SKU-002']);

        // Attach barcode '123456' to item 1
        ItemBarcode::create([
            'item_id'    => $item1->id,
            'barcode'    => '123456',
            'uom_id'     => $this->pcs->id,
            'type'       => ItemBarcodeType::Internal,
            'is_primary' => true,
        ]);

        // Attempting the same barcode on item 2 must violate a unique constraint
        $this->expectException(\Illuminate\Database\QueryException::class);

        ItemBarcode::create([
            'item_id'    => $item2->id,
            'barcode'    => '123456',
            'uom_id'     => $this->pcs->id,
            'type'       => ItemBarcodeType::Internal,
            'is_primary' => true,
        ]);
    }
}
