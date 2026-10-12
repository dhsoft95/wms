<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\ItemCategory;
use App\Models\ItemUom;
use App\Models\Location;
use App\Models\Partner;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\Foundation\DocumentNumber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FoundationDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedUoMs();
            $this->seedCategories();
            $this->seedWarehouses();
            $this->seedPartners();
            $this->seedItems();
        });
    }

    private function seedUoMs(): void
    {
        $uoms = [
            ['code' => 'PCS',  'name' => 'Piece',      'type' => 'count'],
            ['code' => 'CTN',  'name' => 'Carton',     'type' => 'count'],
            ['code' => 'PLT',  'name' => 'Pallet',     'type' => 'count'],
            ['code' => 'BOX',  'name' => 'Box',        'type' => 'count'],
            ['code' => 'BAG',  'name' => 'Bag',        'type' => 'count'],
            ['code' => 'KG',   'name' => 'Kilogram',   'type' => 'weight'],
            ['code' => 'G',    'name' => 'Gram',       'type' => 'weight'],
            ['code' => 'L',    'name' => 'Litre',      'type' => 'volume'],
            ['code' => 'ML',   'name' => 'Millilitre', 'type' => 'volume'],
            ['code' => 'M',    'name' => 'Metre',      'type' => 'length'],
        ];

        foreach ($uoms as $uom) {
            UnitOfMeasure::firstOrCreate(['code' => $uom['code']], $uom);
        }
    }

    private function seedCategories(): array
    {
        $categories = [
            'Food & Beverage'    => ['Dry Goods', 'Beverages', 'Frozen Foods'],
            'Consumer Goods'     => ['Household Supplies', 'Personal Care'],
            'Electronics'        => ['Mobile Accessories'],
            'Industrial Supplies'=> [],
        ];

        $created = [];
        foreach ($categories as $parentName => $children) {
            $parent = ItemCategory::firstOrCreate(['name' => $parentName], ['is_active' => true]);
            $created[$parentName] = $parent;
            foreach ($children as $child) {
                $created[$child] = ItemCategory::firstOrCreate(
                    ['name' => $child, 'parent_id' => $parent->id],
                    ['is_active' => true]
                );
            }
        }

        return $created;
    }

    private function seedWarehouses(): void
    {
        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'Main Warehouse', 'address' => '1 Industrial Road, Dar es Salaam', 'is_active' => true]
        );

        $this->seedLocations($warehouse);
    }

    private function seedLocations(Warehouse $warehouse): void
    {
        // Zone A — general storage
        $zoneA = Location::firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'code' => 'MAIN-A'],
            [
                'parent_id'    => null,
                'level'        => 'zone',
                'segment_code' => 'A',
                'type'         => 'storage',
                'status'       => 'active',
                'path'         => '/',
                'is_pickable'  => true,
            ]
        );
        $zoneA->path = '/';
        $zoneA->save();

        // Zone B — general storage
        $zoneB = Location::firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'code' => 'MAIN-B'],
            [
                'parent_id'    => null,
                'level'        => 'zone',
                'segment_code' => 'B',
                'type'         => 'storage',
                'status'       => 'active',
                'path'         => '/',
                'is_pickable'  => true,
            ]
        );

        // Create bins in Zone A (A-01 through A-10)
        for ($i = 1; $i <= 10; $i++) {
            $seg = str_pad($i, 2, '0', STR_PAD_LEFT);
            Location::firstOrCreate(
                ['warehouse_id' => $warehouse->id, 'code' => "MAIN-A-{$seg}"],
                [
                    'parent_id'    => $zoneA->id,
                    'level'        => 'bin',
                    'segment_code' => $seg,
                    'type'         => 'storage',
                    'status'       => 'active',
                    'path'         => "/{$zoneA->id}/",
                    'is_pickable'  => true,
                    'max_pallets'  => 4,
                ]
            );
        }

        // Create bins in Zone B (B-01 through B-10)
        for ($i = 1; $i <= 10; $i++) {
            $seg = str_pad($i, 2, '0', STR_PAD_LEFT);
            Location::firstOrCreate(
                ['warehouse_id' => $warehouse->id, 'code' => "MAIN-B-{$seg}"],
                [
                    'parent_id'    => $zoneB->id,
                    'level'        => 'bin',
                    'segment_code' => $seg,
                    'type'         => 'storage',
                    'status'       => 'active',
                    'path'         => "/{$zoneB->id}/",
                    'is_pickable'  => true,
                    'max_pallets'  => 4,
                ]
            );
        }

        // Yard — 20 slots
        $yard = Location::firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'code' => 'MAIN-YARD'],
            [
                'parent_id'    => null,
                'level'        => 'zone',
                'segment_code' => 'YARD',
                'type'         => 'yard_slot',
                'status'       => 'active',
                'path'         => '/',
                'is_pickable'  => false,
            ]
        );

        for ($i = 1; $i <= 20; $i++) {
            $seg = str_pad($i, 2, '0', STR_PAD_LEFT);
            Location::firstOrCreate(
                ['warehouse_id' => $warehouse->id, 'code' => "MAIN-YARD-{$seg}"],
                [
                    'parent_id'    => $yard->id,
                    'level'        => 'bin',
                    'segment_code' => $seg,
                    'type'         => 'yard_slot',
                    'status'       => 'active',
                    'path'         => "/{$yard->id}/",
                    'is_pickable'  => false,
                ]
            );
        }

        // 2 receiving docks
        for ($i = 1; $i <= 2; $i++) {
            Location::firstOrCreate(
                ['warehouse_id' => $warehouse->id, 'code' => "MAIN-DOCK-{$i}"],
                [
                    'parent_id'    => null,
                    'level'        => 'zone',
                    'segment_code' => "DOCK-{$i}",
                    'type'         => 'receiving_dock',
                    'status'       => 'active',
                    'path'         => '/',
                    'is_pickable'  => false,
                ]
            );
        }

        // 2 staging bays
        for ($i = 1; $i <= 2; $i++) {
            Location::firstOrCreate(
                ['warehouse_id' => $warehouse->id, 'code' => "MAIN-STAGE-{$i}"],
                [
                    'parent_id'    => null,
                    'level'        => 'zone',
                    'segment_code' => "STAGE-{$i}",
                    'type'         => 'staging',
                    'status'       => 'active',
                    'path'         => '/',
                    'is_pickable'  => true,
                ]
            );
        }

        // 1 quarantine area
        Location::firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'code' => 'MAIN-QRT'],
            [
                'parent_id'    => null,
                'level'        => 'zone',
                'segment_code' => 'QRT',
                'type'         => 'quarantine',
                'status'       => 'active',
                'path'         => '/',
                'is_pickable'  => false,
            ]
        );
    }

    private function seedPartners(): void
    {
        $partners = [
            ['code' => 'SUP-001', 'name' => 'Alpha Supplies Ltd',     'type' => ['supplier'],              'tin' => '100-000-001', 'city' => 'Dar es Salaam'],
            ['code' => 'SUP-002', 'name' => 'Beta Trading Co.',       'type' => ['supplier'],              'tin' => '100-000-002', 'city' => 'Dar es Salaam'],
            ['code' => 'SUP-003', 'name' => 'Gamma Importers Ltd',    'type' => ['supplier', 'shipper'],   'tin' => '100-000-003', 'city' => 'Mombasa'],
            ['code' => 'SHP-001', 'name' => 'Delta Shipping Lines',   'type' => ['shipping_line'],         'tin' => '100-000-004', 'city' => 'Dar es Salaam'],
            ['code' => 'SHP-002', 'name' => 'Epsilon Cargo Ltd',      'type' => ['transporter'],           'tin' => '100-000-005', 'city' => 'Dar es Salaam'],
            ['code' => 'CSG-001', 'name' => 'Zeta Retail Group',      'type' => ['consignee', 'customer'], 'tin' => '100-000-006', 'city' => 'Arusha'],
            ['code' => 'CSG-002', 'name' => 'Eta Distribution Ltd',   'type' => ['customer'],              'tin' => '100-000-007', 'city' => 'Dodoma'],
            ['code' => 'CSG-003', 'name' => 'Theta Wholesale Co.',    'type' => ['consignee'],             'tin' => '100-000-008', 'city' => 'Mwanza'],
            ['code' => 'MIX-001', 'name' => 'Iota Logistics Ltd',     'type' => ['supplier', 'transporter'], 'tin' => '100-000-009', 'city' => 'Dar es Salaam'],
            ['code' => 'MIX-002', 'name' => 'Kappa Trade & Supply',   'type' => ['supplier', 'customer'],  'tin' => '100-000-010', 'city' => 'Zanzibar'],
        ];

        foreach ($partners as $partner) {
            Partner::firstOrCreate(['code' => $partner['code']], array_merge($partner, ['is_active' => true, 'country' => 'Tanzania']));
        }
    }

    private function seedItems(): void
    {
        $pcs  = UnitOfMeasure::where('code', 'PCS')->first();
        $ctn  = UnitOfMeasure::where('code', 'CTN')->first();
        $plt  = UnitOfMeasure::where('code', 'PLT')->first();
        $kg   = UnitOfMeasure::where('code', 'KG')->first();
        $bag  = UnitOfMeasure::where('code', 'BAG')->first();
        $box  = UnitOfMeasure::where('code', 'BOX')->first();

        $catFood     = ItemCategory::where('name', 'Dry Goods')->first();
        $catBev      = ItemCategory::where('name', 'Beverages')->first();
        $catHH       = ItemCategory::where('name', 'Household Supplies')->first();
        $catPC       = ItemCategory::where('name', 'Personal Care')->first();
        $catMobile   = ItemCategory::where('name', 'Mobile Accessories')->first();

        $vws = VirtualWarehouse::all()->keyBy('code');
        $defaultVw = $vws->first();

        $items = [
            // Dry goods — no batch tracking
            ['sku' => 'DRY-001', 'name' => 'Wheat Flour 2kg',      'cat' => $catFood,  'uom' => $pcs, 'track_batch' => false, 'track_expiry' => false, 'uoms' => [[$ctn, 24], [$plt, 960]]],
            ['sku' => 'DRY-002', 'name' => 'Rice Premium 5kg',     'cat' => $catFood,  'uom' => $bag, 'track_batch' => false, 'track_expiry' => false, 'uoms' => [[$plt, 160]]],
            ['sku' => 'DRY-003', 'name' => 'Sugar White 1kg',      'cat' => $catFood,  'uom' => $pcs, 'track_batch' => true,  'track_expiry' => false, 'uoms' => [[$ctn, 20], [$plt, 800]]],

            // Beverages — batch + expiry tracking
            ['sku' => 'BEV-001', 'name' => 'Mineral Water 500ml 24pk', 'cat' => $catBev, 'uom' => $ctn, 'track_batch' => true, 'track_expiry' => true, 'uoms' => [[$plt, 40]]],
            ['sku' => 'BEV-002', 'name' => 'Juice Mango 1L',       'cat' => $catBev,  'uom' => $pcs, 'track_batch' => true,  'track_expiry' => true,  'uoms' => [[$ctn, 12], [$plt, 480]]],
            ['sku' => 'BEV-003', 'name' => 'Energy Drink 250ml',   'cat' => $catBev,  'uom' => $pcs, 'track_batch' => true,  'track_expiry' => true,  'uoms' => [[$ctn, 24]]],

            // Household supplies — no tracking
            ['sku' => 'HH-001',  'name' => 'Dish Soap 500ml',      'cat' => $catHH,   'uom' => $pcs, 'track_batch' => false, 'track_expiry' => false, 'uoms' => [[$ctn, 12], [$plt, 480]]],
            ['sku' => 'HH-002',  'name' => 'Laundry Detergent 1kg','cat' => $catHH,   'uom' => $box, 'track_batch' => false, 'track_expiry' => false, 'uoms' => [[$plt, 40]]],
            ['sku' => 'HH-003',  'name' => 'Floor Cleaner 1L',     'cat' => $catHH,   'uom' => $pcs, 'track_batch' => false, 'track_expiry' => false, 'uoms' => [[$ctn, 6]]],

            // Personal care — batch tracking
            ['sku' => 'PC-001',  'name' => 'Shampoo 200ml',        'cat' => $catPC,   'uom' => $pcs, 'track_batch' => true,  'track_expiry' => false, 'uoms' => [[$ctn, 24]]],
            ['sku' => 'PC-002',  'name' => 'Lotion 250ml',         'cat' => $catPC,   'uom' => $pcs, 'track_batch' => true,  'track_expiry' => true,  'uoms' => [[$ctn, 12]]],

            // Electronics — serial tracking
            ['sku' => 'MOB-001', 'name' => 'USB-C Cable 1m',       'cat' => $catMobile,'uom' => $pcs, 'track_batch' => false, 'track_expiry' => false, 'track_serial' => true, 'uoms' => [[$box, 50]]],
            ['sku' => 'MOB-002', 'name' => 'Phone Charger 18W',    'cat' => $catMobile,'uom' => $pcs, 'track_batch' => false, 'track_expiry' => false, 'track_serial' => true, 'uoms' => [[$box, 20]]],

            // Industrial
            ['sku' => 'IND-001', 'name' => 'Packing Tape Roll',    'cat' => null,     'uom' => $pcs, 'track_batch' => false, 'track_expiry' => false, 'uoms' => [[$box, 36]]],
            ['sku' => 'IND-002', 'name' => 'Stretch Wrap Film 500m','cat' => null,    'uom' => $pcs, 'track_batch' => false, 'track_expiry' => false, 'uoms' => []],
        ];

        foreach ($items as $itemData) {
            if (Item::withTrashed()->where('sku', $itemData['sku'])->exists()) {
                continue;
            }

            $item = Item::create([
                'sku'                        => $itemData['sku'],
                'name'                       => $itemData['name'],
                'category_id'               => $itemData['cat']?->id,
                'base_uom_id'               => $itemData['uom']->id,
                'track_batch'               => $itemData['track_batch'],
                'track_expiry'              => $itemData['track_expiry'] ?? false,
                'track_serial'              => $itemData['track_serial'] ?? false,
                'default_virtual_warehouse_id' => $defaultVw?->id,
                'is_active'                 => true,
                'min_stock'                 => 10,
                'max_stock'                 => 500,
            ]);

            // Base UoM row (factor = 1)
            ItemUom::create([
                'item_id'              => $item->id,
                'uom_id'               => $itemData['uom']->id,
                'factor_to_base'       => '1.0000',
                'is_default_purchase'  => false,
                'is_default_issue'     => true,
            ]);

            // Additional pack UoMs
            foreach (($itemData['uoms'] ?? []) as [$uom, $factor]) {
                if (! $uom) continue;
                ItemUom::firstOrCreate(
                    ['item_id' => $item->id, 'uom_id' => $uom->id],
                    [
                        'factor_to_base'      => number_format($factor, 4, '.', ''),
                        'is_default_purchase' => true,
                        'is_default_issue'    => false,
                    ]
                );
            }

            // Primary barcode
            ItemBarcode::create([
                'item_id'    => $item->id,
                'barcode'    => '6' . str_pad($item->id, 11, '0', STR_PAD_LEFT),
                'uom_id'     => $itemData['uom']->id,
                'type'       => 'internal',
                'is_primary' => true,
            ]);
        }
    }
}
