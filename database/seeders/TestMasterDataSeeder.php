<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ItemBarcodeType;
use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Enums\UomType;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\ItemCategory;
use App\Models\ItemUom;
use App\Models\Location;
use App\Models\Partner;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class TestMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $forced = $this->command !== null
                && $this->command->hasOption('force')
                && (bool) $this->command->option('force');

            if (! $forced) {
                $this->command?->error('Refusing to seed test data on production. Pass --force to override.');
                return;
            }

            $ok = $this->command->confirm(
                'Write TEST master data to the PRODUCTION database?',
                false,
            );

            if (! $ok) {
                $this->command->info('Aborted.');
                return;
            }
        }

        $this->seedPartners();
        $this->seedDepartmentsAndVWs();
        $this->seedCategories();
        $this->seedItems();
        $this->seedWarehouse();

        $this->command?->info('TestMasterDataSeeder: done.');
    }

    // ── Partners ──────────────────────────────────────────────────────────────

    private function seedPartners(): void
    {
        $rows = [
            ['code' => 'HUAWEI',    'name' => 'Huawei Technologies Co. Ltd', 'type' => ['supplier', 'shipper']],
            ['code' => 'MAERSK',    'name' => 'Maersk Line',                 'type' => ['shipping_line']],
            ['code' => 'TIGO',      'name' => 'Tigo Tanzania',               'type' => ['consignee', 'customer']],
            ['code' => 'FASTTRUCK', 'name' => 'Fast Truck Logistics',        'type' => ['transporter']],
        ];

        foreach ($rows as $row) {
            Partner::updateOrCreate(['code' => $row['code']], [
                'name'      => $row['name'],
                'type'      => $row['type'],
                'is_active' => true,
            ]);
        }

        $this->command?->info('  Partners ✓  (4)');
    }

    // ── Departments + Virtual Warehouses ──────────────────────────────────────

    private function seedDepartmentsAndVWs(): void
    {
        $rows = [
            ['dept_code' => 'TIGO-NET', 'dept_name' => 'Tigo Network', 'vw_code' => 'TIGO-NET', 'vw_name' => 'Tigo Network Stock'],
            ['dept_code' => 'TIGO-RTL', 'dept_name' => 'Tigo Retail',  'vw_code' => 'TIGO-RTL', 'vw_name' => 'Tigo Retail Stock'],
        ];

        foreach ($rows as $row) {
            $dept = Department::updateOrCreate(['code' => $row['dept_code']], [
                'name'      => $row['dept_name'],
                'is_active' => true,
            ]);

            VirtualWarehouse::updateOrCreate(['code' => $row['vw_code']], [
                'name'          => $row['vw_name'],
                'department_id' => $dept->id,
                'is_active'     => true,
            ]);
        }

        $this->command?->info('  Departments + VWs ✓  (2 each)');
    }

    // ── Item Categories ───────────────────────────────────────────────────────

    private function seedCategories(): void
    {
        foreach (['Network Equipment', 'Customer Devices', 'Power'] as $name) {
            ItemCategory::updateOrCreate(['name' => $name], ['is_active' => true]);
        }

        $this->command?->info('  Item categories ✓  (3)');
    }

    // ── UoMs + Items ──────────────────────────────────────────────────────────

    private function seedItems(): void
    {
        $pcs = UnitOfMeasure::updateOrCreate(['code' => 'PCS'], [
            'name'      => 'Pieces',
            'type'      => UomType::Count,
            'is_active' => true,
        ]);

        $ctn = UnitOfMeasure::updateOrCreate(['code' => 'CTN'], [
            'name'      => 'Carton',
            'type'      => UomType::Count,
            'is_active' => true,
        ]);

        $catNetwork = ItemCategory::firstWhere('name', 'Network Equipment');
        $catDevices = ItemCategory::firstWhere('name', 'Customer Devices');
        $catPower   = ItemCategory::firstWhere('name', 'Power');
        $vwNet      = VirtualWarehouse::firstWhere('code', 'TIGO-NET');
        $vwRtl      = VirtualWarehouse::firstWhere('code', 'TIGO-RTL');

        $defs = [
            [
                'sku'     => 'RTL-MIFI-4G',
                'attrs'   => [
                    'name'                         => 'MiFi 4G Router',
                    'category_id'                  => $catDevices?->id,
                    'base_uom_id'                  => $pcs->id,
                    'track_serial'                 => true,
                    'track_batch'                  => false,
                    'track_expiry'                 => false,
                    'default_virtual_warehouse_id' => $vwRtl?->id,
                    'is_active'                    => true,
                ],
                'ctn_factor' => 10,
                'barcode'    => '6901234500011',
            ],
            [
                'sku'     => 'NET-BAT-48V',
                'attrs'   => [
                    'name'                         => '48V Tower Battery',
                    'category_id'                  => $catPower?->id,
                    'base_uom_id'                  => $pcs->id,
                    'track_serial'                 => false,
                    'track_batch'                  => true,
                    'track_expiry'                 => true,
                    'default_virtual_warehouse_id' => $vwNet?->id,
                    'is_active'                    => true,
                ],
                'ctn_factor' => null,
                'barcode'    => '6901234500028',
            ],
            [
                'sku'     => 'NET-ANT-4G',
                'attrs'   => [
                    'name'                         => '4G Panel Antenna',
                    'category_id'                  => $catNetwork?->id,
                    'base_uom_id'                  => $pcs->id,
                    'track_serial'                 => false,
                    'track_batch'                  => false,
                    'track_expiry'                 => false,
                    'default_virtual_warehouse_id' => $vwNet?->id,
                    'is_active'                    => true,
                ],
                'ctn_factor' => 4,
                'barcode'    => '6901234500035',
            ],
        ];

        foreach ($defs as $def) {
            $item = Item::updateOrCreate(['sku' => $def['sku']], $def['attrs']);

            if ($def['ctn_factor'] !== null) {
                ItemUom::updateOrCreate(
                    ['item_id' => $item->id, 'uom_id' => $ctn->id],
                    ['factor_to_base' => $def['ctn_factor']],
                );
            }

            ItemBarcode::updateOrCreate(
                ['barcode' => $def['barcode']],
                [
                    'item_id'    => $item->id,
                    'uom_id'     => $pcs->id,
                    'type'       => ItemBarcodeType::Supplier,
                    'is_primary' => true,
                ],
            );
        }

        $this->command?->info('  Items ✓  (3 items, PCS + CTN UoMs)');
    }

    // ── Warehouse + Locations ─────────────────────────────────────────────────

    private function seedWarehouse(): void
    {
        $wh = Warehouse::updateOrCreate(['code' => 'DAR'], [
            'name'      => 'Dar es Salaam Warehouse',
            'is_active' => true,
        ]);

        // ── Yard: zone Y with slots Y-01..Y-05 ──────────────────────────────
        $zoneY = $this->loc($wh->id, null, LocationLevel::Zone, 'Y', 'DAR-Y', LocationType::YardSlot);
        for ($i = 1; $i <= 5; $i++) {
            $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $this->loc($wh->id, $zoneY->id, LocationLevel::Bin, $n, "DAR-Y-{$n}", LocationType::YardSlot);
        }

        // ── Dock, Quarantine, Staging ────────────────────────────────────────
        $dock = $this->loc($wh->id, null, LocationLevel::Bin, '01', 'DAR-DOCK-01', LocationType::ReceivingDock);
        $qua  = $this->loc($wh->id, null, LocationLevel::Bin, '01', 'DAR-QUA-01',  LocationType::Quarantine);
        $stg  = $this->loc($wh->id, null, LocationLevel::Bin, '01', 'DAR-STG-01',  LocationType::Staging);

        // ── Storage Zone A: aisles 01-02, racks 01-02, levels 01-02, bins A-B ──
        $zoneA = $this->loc($wh->id, null, LocationLevel::Zone, 'A', 'DAR-A', LocationType::Storage);

        foreach (['01', '02'] as $a) {
            $aisle = $this->loc($wh->id, $zoneA->id, LocationLevel::Aisle, $a, "DAR-A-{$a}", LocationType::Storage);

            foreach (['01', '02'] as $r) {
                $rack = $this->loc($wh->id, $aisle->id, LocationLevel::Rack, $r, "DAR-A-{$a}-{$r}", LocationType::Storage);

                foreach (['01', '02'] as $l) {
                    $lvl = $this->loc($wh->id, $rack->id, LocationLevel::ShelfLevel, $l, "DAR-A-{$a}-{$r}-{$l}", LocationType::Storage);

                    foreach (['A', 'B'] as $b) {
                        $this->loc($wh->id, $lvl->id, LocationLevel::Bin, $b, "DAR-A-{$a}-{$r}-{$l}-{$b}", LocationType::Storage, maxPallets: 2);
                    }
                }
            }
        }

        // ── Set warehouse defaults ────────────────────────────────────────────
        $wh->update([
            'default_receiving_dock_location_id' => $dock->id,
            'default_quarantine_location_id'     => $qua->id,
            'default_staging_location_id'        => $stg->id,
        ]);

        $this->command?->info('  Warehouse DAR ✓  (5 yard + dock + qua + stg + 16 storage bins)');
    }

    // ── Location helper ───────────────────────────────────────────────────────

    private function loc(
        int           $warehouseId,
        ?int          $parentId,
        LocationLevel $level,
        string        $segmentCode,
        string        $code,
        LocationType  $type,
        ?int          $maxPallets = null,
    ): Location {
        $isPickable = $level === LocationLevel::Bin
            && in_array($type, [LocationType::Storage, LocationType::Staging], true);

        $location = Location::updateOrCreate(
            ['warehouse_id' => $warehouseId, 'code' => $code],
            [
                'parent_id'    => $parentId,
                'level'        => $level,
                'segment_code' => $segmentCode,
                'type'         => $type,
                'status'       => LocationStatus::Active,
                'is_pickable'  => $isPickable,
                'max_pallets'  => $maxPallets,
            ],
        );

        $parentPath = $parentId !== null
            ? (Location::find($parentId)?->path ?? '/')
            : '/';

        $path = $parentPath . $location->id . '/';

        if ($location->path !== $path) {
            $location->update(['path' => $path]);
            $location->path = $path;
        }

        return $location;
    }
}
