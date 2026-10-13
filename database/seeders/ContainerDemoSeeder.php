<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ContainerDiscrepancyType;
use App\Enums\ContainerSizeType;
use App\Enums\ContainerStatus;
use App\Models\Container;
use App\Models\ContainerDiscrepancy;
use App\Models\ContainerExpectedLine;
use App\Models\ContainerStatusHistory;
use App\Models\Item;
use App\Models\Location;
use App\Models\Partner;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Services\Foundation\DocumentNumber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContainerDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $admin  = User::where('email', 'davidhaule3@gmail.com')->first()
                ?? User::first();

            // Reference data
            $shippingLine = Partner::where('code', 'SHP-001')->first();
            $shipper      = Partner::where('code', 'SUP-003')->first()
                ?? Partner::where('code', 'SUP-001')->first();
            $consignee    = Partner::where('code', 'CSG-001')->first();
            $consignee2   = Partner::where('code', 'CSG-002')->first();

            $yardSlots = Location::where('type', 'yard_slot')
                ->where('code', 'like', 'MAIN-YARD-%')
                ->where('code', 'not like', 'MAIN-YARD')
                ->orderBy('code')
                ->get();

            $vws  = VirtualWarehouse::all();
            $vw1  = $vws->first();
            $vw2  = $vws->count() > 1 ? $vws->skip(1)->first() : $vws->first();

            $pcs = UnitOfMeasure::where('code', 'PCS')->first();
            $ctn = UnitOfMeasure::where('code', 'CTN')->first();

            $items = Item::whereIn('sku', ['DRY-001', 'DRY-002', 'BEV-001', 'HH-001', 'HH-002', 'PC-001'])->get()->keyBy('sku');

            // Pre-compute a sequence-style number helper (no DocumentNumber call to keep seeder self-contained)
            $ctrNum = 1;
            $makeNumber = function () use (&$ctrNum): string {
                $n = 'CTR-2026-' . str_pad((string) $ctrNum++, 4, '0', STR_PAD_LEFT);
                return $n;
            };

            // ── 1. EXPECTED containers (2) ─────────────────────────────────────
            $c1 = Container::create([
                'number'                  => $makeNumber(),
                'container_no'            => 'MSCU4134998',
                'seal_no'                 => 'SL-100001',
                'size_type'               => ContainerSizeType::FortyHC,
                'bl_no'                   => 'MSCUTZ2600001',
                'vessel'                  => 'MSC DIANA V.12',
                'voyage'                  => '12E',
                'shipper_partner_id'      => $shipper?->id,
                'consignee_partner_id'    => $consignee?->id,
                'shipping_line_partner_id'=> $shippingLine?->id,
                'eta'                     => now()->addDays(3)->toDateString(),
                'free_days'               => 7,
                'status'                  => ContainerStatus::Expected,
                'notes'                   => 'First expected container — Dry Goods mixed.',
                'created_by'              => $admin?->id,
            ]);
            $this->addStatusHistory($c1, null, ContainerStatus::Expected, $admin);
            $this->addLines($c1, $items, $pcs, $ctn, $vw1, mapped: false);

            $c2 = Container::create([
                'number'                  => $makeNumber(),
                'container_no'            => 'TGHU7712046',
                'seal_no'                 => 'SL-100002',
                'size_type'               => ContainerSizeType::TwentyGP,
                'bl_no'                   => 'TGHUTZ2600002',
                'vessel'                  => 'COSCO PRIDE V.42',
                'voyage'                  => '42W',
                'shipper_partner_id'      => $shipper?->id,
                'consignee_partner_id'    => $consignee2?->id,
                'shipping_line_partner_id'=> $shippingLine?->id,
                'eta'                     => now()->addDays(5)->toDateString(),
                'free_days'               => 7,
                'status'                  => ContainerStatus::Expected,
                'notes'                   => 'Beverages — batch + expiry tracked.',
                'created_by'              => $admin?->id,
            ]);
            $this->addStatusHistory($c2, null, ContainerStatus::Expected, $admin);
            // Unmapped lines (raw_sku only — not yet linked to item)
            ContainerExpectedLine::create([
                'container_id'       => $c2->id,
                'line_no'            => 1,
                'item_id'            => null,
                'raw_sku'            => 'BEV-MINERAL-500',
                'raw_description'    => 'Mineral Water 500ml 24pk',
                'qty'                => '200.0000',
                'uom_id'             => $ctn?->id,
                'virtual_warehouse_id' => null,
            ]);
            ContainerExpectedLine::create([
                'container_id'       => $c2->id,
                'line_no'            => 2,
                'item_id'            => null,
                'raw_sku'            => 'BEV-JUICE-1L',
                'raw_description'    => 'Juice Mango 1L',
                'qty'                => '500.0000',
                'uom_id'             => $pcs?->id,
                'virtual_warehouse_id' => null,
            ]);

            // ── 2. ARRIVED containers (2) ──────────────────────────────────────
            $c3 = Container::create([
                'number'                  => $makeNumber(),
                'container_no'            => 'HLXU3729831',
                'seal_no'                 => 'SL-100003',
                'size_type'               => ContainerSizeType::FortyGP,
                'bl_no'                   => 'HLXUTZ2600003',
                'vessel'                  => 'CMA CGM AFRICA V.08',
                'voyage'                  => '08N',
                'shipper_partner_id'      => $shipper?->id,
                'consignee_partner_id'    => $consignee?->id,
                'shipping_line_partner_id'=> $shippingLine?->id,
                'eta'                     => now()->subDay()->toDateString(),
                'arrived_at'              => now()->subHours(6),
                'arrival_seal_no'         => 'SL-100003',
                'seal_verified'           => true,
                'free_days'               => 7,
                'status'                  => ContainerStatus::Arrived,
                'created_by'              => $admin?->id,
            ]);
            $this->addStatusHistory($c3, null, ContainerStatus::Expected, $admin, now()->subDays(5));
            $this->addStatusHistory($c3, ContainerStatus::Expected, ContainerStatus::Arrived, $admin);
            $this->addLines($c3, $items, $pcs, $ctn, $vw1, mapped: true);

            // Arrived with seal mismatch
            $c4 = Container::create([
                'number'                  => $makeNumber(),
                'container_no'            => 'GESU3082619',
                'seal_no'                 => 'SL-200010',
                'size_type'               => ContainerSizeType::TwentyGP,
                'bl_no'                   => 'GESUTZ2600004',
                'vessel'                  => 'MAERSK KOWLOON V.31',
                'voyage'                  => '31S',
                'shipper_partner_id'      => $shipper?->id,
                'consignee_partner_id'    => $consignee2?->id,
                'shipping_line_partner_id'=> $shippingLine?->id,
                'eta'                     => now()->subDays(2)->toDateString(),
                'arrived_at'              => now()->subHours(12),
                'arrival_seal_no'         => 'SL-999999',   // MISMATCH
                'seal_verified'           => false,
                'free_days'               => 7,
                'status'                  => ContainerStatus::Arrived,
                'notes'                   => 'Seal mismatch detected on arrival.',
                'created_by'              => $admin?->id,
            ]);
            $this->addStatusHistory($c4, null, ContainerStatus::Expected, $admin, now()->subDays(7));
            $this->addStatusHistory($c4, ContainerStatus::Expected, ContainerStatus::Arrived, $admin);
            // Create seal mismatch discrepancy
            ContainerDiscrepancy::create([
                'container_id' => $c4->id,
                'type'         => ContainerDiscrepancyType::SealMismatch,
                'description'  => 'Expected seal: SL-200010. Arrival seal: SL-999999.',
                'reported_by'  => $admin?->id,
            ]);
            $this->addLines($c4, $items, $pcs, $ctn, $vw2, mapped: false);

            // ── 3. PARKED containers (3) ───────────────────────────────────────
            $yard1 = $yardSlots->get(0);
            $yard2 = $yardSlots->get(1);
            $yard3 = $yardSlots->get(2);

            $c5 = Container::create([
                'number'                  => $makeNumber(),
                'container_no'            => 'MSKU0352007',
                'seal_no'                 => 'SL-100005',
                'size_type'               => ContainerSizeType::FortyHC,
                'bl_no'                   => 'MSKUTZ2600005',
                'vessel'                  => 'MSC DIANA V.12',
                'voyage'                  => '12E',
                'shipper_partner_id'      => $shipper?->id,
                'consignee_partner_id'    => $consignee?->id,
                'shipping_line_partner_id'=> $shippingLine?->id,
                'eta'                     => now()->subDays(4)->toDateString(),
                'arrived_at'              => now()->subDays(4),
                'arrival_seal_no'         => 'SL-100005',
                'seal_verified'           => true,
                'free_days'               => 7,
                'yard_location_id'        => $yard1?->id,
                'status'                  => ContainerStatus::Parked,
                'created_by'              => $admin?->id,
            ]);
            $this->addStatusHistory($c5, null, ContainerStatus::Expected, $admin, now()->subDays(6));
            $this->addStatusHistory($c5, ContainerStatus::Expected, ContainerStatus::Arrived, $admin, now()->subDays(4));
            $this->addStatusHistory($c5, ContainerStatus::Arrived, ContainerStatus::Parked, $admin, now()->subDays(4)->addHours(2));
            $this->addLines($c5, $items, $pcs, $ctn, $vw1, mapped: true);

            // Parked — dwell at warning threshold (10 days)
            $c6 = Container::create([
                'number'                  => $makeNumber(),
                'container_no'            => 'CMAU1234564',
                'seal_no'                 => 'SL-100006',
                'size_type'               => ContainerSizeType::FortyGP,
                'bl_no'                   => 'CMAUTZ2600006',
                'vessel'                  => 'COSCO PRIDE V.42',
                'voyage'                  => '42W',
                'shipper_partner_id'      => $shipper?->id,
                'consignee_partner_id'    => $consignee?->id,
                'shipping_line_partner_id'=> $shippingLine?->id,
                'eta'                     => now()->subDays(10)->toDateString(),
                'arrived_at'              => now()->subDays(10),
                'arrival_seal_no'         => 'SL-100006',
                'seal_verified'           => true,
                'free_days'               => 7,
                'yard_location_id'        => $yard2?->id,
                'status'                  => ContainerStatus::Parked,
                'notes'                   => 'Dwell warning threshold reached.',
                'created_by'              => $admin?->id,
            ]);
            $this->addStatusHistory($c6, null, ContainerStatus::Expected, $admin, now()->subDays(12));
            $this->addStatusHistory($c6, ContainerStatus::Expected, ContainerStatus::Arrived, $admin, now()->subDays(10));
            $this->addStatusHistory($c6, ContainerStatus::Arrived, ContainerStatus::Parked, $admin, now()->subDays(10)->addHours(3));
            $this->addLines($c6, $items, $pcs, $ctn, $vw1, mapped: true);

            // Parked — dwell at critical threshold (18 days)
            $c7 = Container::create([
                'number'                  => $makeNumber(),
                'container_no'            => 'MRKU0102937',
                'seal_no'                 => 'SL-100007',
                'size_type'               => ContainerSizeType::TwentyGP,
                'bl_no'                   => 'MRKUTZ2600007',
                'vessel'                  => 'CMA CGM AFRICA V.08',
                'voyage'                  => '08N',
                'shipper_partner_id'      => $shipper?->id,
                'consignee_partner_id'    => $consignee2?->id,
                'shipping_line_partner_id'=> $shippingLine?->id,
                'eta'                     => now()->subDays(18)->toDateString(),
                'arrived_at'              => now()->subDays(18),
                'arrival_seal_no'         => 'SL-100007',
                'seal_verified'           => true,
                'free_days'               => 7,
                'yard_location_id'        => $yard3?->id,
                'status'                  => ContainerStatus::Parked,
                'notes'                   => 'CRITICAL: Container has been in yard 18 days.',
                'created_by'              => $admin?->id,
            ]);
            $this->addStatusHistory($c7, null, ContainerStatus::Expected, $admin, now()->subDays(20));
            $this->addStatusHistory($c7, ContainerStatus::Expected, ContainerStatus::Arrived, $admin, now()->subDays(18));
            $this->addStatusHistory($c7, ContainerStatus::Arrived, ContainerStatus::Parked, $admin, now()->subDays(18)->addHours(1));
            $this->addLines($c7, $items, $pcs, $ctn, $vw2, mapped: true);

            // ── 4. UNLOADING container (1) — all lines mapped ─────────────────
            $c8 = Container::create([
                'number'                  => $makeNumber(),
                'container_no'            => 'CCLU4537833',
                'seal_no'                 => 'SL-100008',
                'size_type'               => ContainerSizeType::FortyHC,
                'bl_no'                   => 'CCLUTZ2600008',
                'vessel'                  => 'MAERSK KOWLOON V.31',
                'voyage'                  => '31S',
                'shipper_partner_id'      => $shipper?->id,
                'consignee_partner_id'    => $consignee?->id,
                'shipping_line_partner_id'=> $shippingLine?->id,
                'eta'                     => now()->subDays(6)->toDateString(),
                'arrived_at'              => now()->subDays(6),
                'arrival_seal_no'         => 'SL-100008',
                'seal_verified'           => true,
                'free_days'               => 7,
                'yard_location_id'        => $yardSlots->get(3)?->id,
                'status'                  => ContainerStatus::Unloading,
                'unloading_started_at'    => now()->subHours(2),
                'created_by'              => $admin?->id,
            ]);
            $this->addStatusHistory($c8, null, ContainerStatus::Expected, $admin, now()->subDays(8));
            $this->addStatusHistory($c8, ContainerStatus::Expected, ContainerStatus::Arrived, $admin, now()->subDays(6));
            $this->addStatusHistory($c8, ContainerStatus::Arrived, ContainerStatus::Parked, $admin, now()->subDays(6)->addHours(1));
            $this->addStatusHistory($c8, ContainerStatus::Parked, ContainerStatus::Unloading, $admin, now()->subHours(2));
            $this->addLines($c8, $items, $pcs, $ctn, $vw1, mapped: true);

            // ── 5. UNLOADED container (1) ──────────────────────────────────────
            $c9 = Container::create([
                'number'                  => $makeNumber(),
                'container_no'            => 'TCKU3070830',
                'seal_no'                 => 'SL-100009',
                'size_type'               => ContainerSizeType::TwentyGP,
                'bl_no'                   => 'TCKUTZ2600009',
                'vessel'                  => 'MSC DIANA V.12',
                'voyage'                  => '11E',
                'shipper_partner_id'      => $shipper?->id,
                'consignee_partner_id'    => $consignee?->id,
                'shipping_line_partner_id'=> $shippingLine?->id,
                'eta'                     => now()->subDays(9)->toDateString(),
                'arrived_at'              => now()->subDays(9),
                'arrival_seal_no'         => 'SL-100009',
                'seal_verified'           => true,
                'free_days'               => 7,
                'yard_location_id'        => $yardSlots->get(4)?->id,
                'status'                  => ContainerStatus::Unloaded,
                'unloading_started_at'    => now()->subDays(1)->subHours(4),
                'unloaded_at'             => now()->subDays(1),
                'created_by'              => $admin?->id,
            ]);
            $this->addStatusHistory($c9, null, ContainerStatus::Expected, $admin, now()->subDays(11));
            $this->addStatusHistory($c9, ContainerStatus::Expected, ContainerStatus::Arrived, $admin, now()->subDays(9));
            $this->addStatusHistory($c9, ContainerStatus::Arrived, ContainerStatus::Parked, $admin, now()->subDays(9)->addHours(2));
            $this->addStatusHistory($c9, ContainerStatus::Parked, ContainerStatus::Unloading, $admin, now()->subDays(1)->subHours(4));
            $this->addStatusHistory($c9, ContainerStatus::Unloading, ContainerStatus::Unloaded, $admin, now()->subDays(1));
            $this->addLines($c9, $items, $pcs, $ctn, $vw1, mapped: true);

            // ── 6. RELEASED containers (2) ─────────────────────────────────────
            $c10 = Container::create([
                'number'                   => $makeNumber(),
                'container_no'             => 'FSCU5062452',
                'seal_no'                  => 'SL-100010',
                'size_type'                => ContainerSizeType::FortyHC,
                'bl_no'                    => 'FSCUTZ2600010',
                'vessel'                   => 'COSCO PRIDE V.42',
                'voyage'                   => '41W',
                'shipper_partner_id'       => $shipper?->id,
                'consignee_partner_id'     => $consignee?->id,
                'shipping_line_partner_id' => $shippingLine?->id,
                'eta'                      => now()->subDays(15)->toDateString(),
                'arrived_at'               => now()->subDays(15),
                'arrival_seal_no'          => 'SL-100010',
                'seal_verified'            => true,
                'free_days'                => 7,
                'yard_location_id'         => null,
                'status'                   => ContainerStatus::Released,
                'unloading_started_at'     => now()->subDays(5)->subHours(3),
                'unloaded_at'              => now()->subDays(5),
                'released_at'              => now()->subDays(2),
                'released_to_partner_id'   => $consignee?->id,
                'release_notes'            => 'Released after customs clearance.',
                'created_by'               => $admin?->id,
            ]);
            $this->addStatusHistory($c10, null, ContainerStatus::Expected, $admin, now()->subDays(17));
            $this->addStatusHistory($c10, ContainerStatus::Expected, ContainerStatus::Arrived, $admin, now()->subDays(15));
            $this->addStatusHistory($c10, ContainerStatus::Arrived, ContainerStatus::Parked, $admin, now()->subDays(15)->addHours(2));
            $this->addStatusHistory($c10, ContainerStatus::Parked, ContainerStatus::Unloading, $admin, now()->subDays(5)->subHours(3));
            $this->addStatusHistory($c10, ContainerStatus::Unloading, ContainerStatus::Unloaded, $admin, now()->subDays(5));
            $this->addStatusHistory($c10, ContainerStatus::Unloaded, ContainerStatus::Released, $admin, now()->subDays(2));
            $this->addLines($c10, $items, $pcs, $ctn, $vw1, mapped: true);

            $c11 = Container::create([
                'number'                   => $makeNumber(),
                'container_no'             => 'OOLU8765436',
                'seal_no'                  => 'SL-100011',
                'size_type'                => ContainerSizeType::FortyGP,
                'bl_no'                    => 'OOLUTZ2600011',
                'vessel'                   => 'CMA CGM AFRICA V.07',
                'voyage'                   => '07N',
                'shipper_partner_id'       => $shipper?->id,
                'consignee_partner_id'     => $consignee2?->id,
                'shipping_line_partner_id' => $shippingLine?->id,
                'eta'                      => now()->subDays(20)->toDateString(),
                'arrived_at'               => now()->subDays(20),
                'arrival_seal_no'          => 'SL-100011',
                'seal_verified'            => true,
                'free_days'                => 5,
                'yard_location_id'         => null,
                'status'                   => ContainerStatus::Released,
                'unloading_started_at'     => now()->subDays(10)->subHours(2),
                'unloaded_at'              => now()->subDays(10),
                'released_at'              => now()->subDays(8),
                'released_to_partner_id'   => $consignee2?->id,
                'release_notes'            => 'Delivered to consignee warehouse.',
                'created_by'               => $admin?->id,
            ]);
            $this->addStatusHistory($c11, null, ContainerStatus::Expected, $admin, now()->subDays(22));
            $this->addStatusHistory($c11, ContainerStatus::Expected, ContainerStatus::Arrived, $admin, now()->subDays(20));
            $this->addStatusHistory($c11, ContainerStatus::Arrived, ContainerStatus::Parked, $admin, now()->subDays(20)->addHours(1));
            $this->addStatusHistory($c11, ContainerStatus::Parked, ContainerStatus::Unloading, $admin, now()->subDays(10)->subHours(2));
            $this->addStatusHistory($c11, ContainerStatus::Unloading, ContainerStatus::Unloaded, $admin, now()->subDays(10));
            $this->addStatusHistory($c11, ContainerStatus::Unloaded, ContainerStatus::Released, $admin, now()->subDays(8));
            $this->addLines($c11, $items, $pcs, $ctn, $vw2, mapped: true);

            // ── 7. CANCELLED container (1) ─────────────────────────────────────
            $c12 = Container::create([
                'number'                  => $makeNumber(),
                'container_no'            => 'APZU3100846',
                'seal_no'                 => null,
                'size_type'               => ContainerSizeType::TwentyGP,
                'bl_no'                   => 'APZUTZ2600012',
                'vessel'                  => 'MAERSK KOWLOON V.30',
                'voyage'                  => '30S',
                'shipper_partner_id'      => $shipper?->id,
                'consignee_partner_id'    => $consignee?->id,
                'shipping_line_partner_id'=> $shippingLine?->id,
                'eta'                     => now()->subDays(3)->toDateString(),
                'free_days'               => 7,
                'status'                  => ContainerStatus::Cancelled,
                'notes'                   => 'Shipment diverted — vessel rerouted.',
                'created_by'              => $admin?->id,
            ]);
            $this->addStatusHistory($c12, null, ContainerStatus::Expected, $admin, now()->subDays(5));
            $this->addStatusHistory(
                $c12,
                ContainerStatus::Expected,
                ContainerStatus::Cancelled,
                $admin,
                now()->subDays(2),
                'Vessel rerouted; cargo will not arrive at this port.'
            );
        });
    }

    private function addStatusHistory(
        Container     $container,
        ?ContainerStatus $from,
        ContainerStatus  $to,
        ?User            $user,
        ?\DateTimeInterface $at = null,
        ?string          $notes = null
    ): void {
        \App\Models\ContainerStatusHistory::create([
            'container_id' => $container->id,
            'from_status'  => $from?->value,
            'to_status'    => $to->value,
            'user_id'      => $user?->id,
            'notes'        => $notes,
            'created_at'   => $at ?? now(),
        ]);
    }

    private function addLines(
        Container    $container,
        \Illuminate\Support\Collection $items,
        ?UnitOfMeasure $pcs,
        ?UnitOfMeasure $ctn,
        ?VirtualWarehouse $vw,
        bool $mapped = true
    ): void {
        $dryItem  = $items->get('DRY-001');
        $hhItem   = $items->get('HH-001');

        if ($mapped && $dryItem) {
            ContainerExpectedLine::create([
                'container_id'        => $container->id,
                'line_no'             => 1,
                'item_id'             => $dryItem->id,
                'raw_sku'             => $dryItem->sku,
                'raw_description'     => $dryItem->name,
                'qty'                 => '100.0000',
                'uom_id'              => $ctn?->id ?? $pcs?->id,
                'virtual_warehouse_id'=> $vw?->id,
            ]);
        } else {
            ContainerExpectedLine::create([
                'container_id'        => $container->id,
                'line_no'             => 1,
                'item_id'             => null,
                'raw_sku'             => 'DRY-001',
                'raw_description'     => 'Wheat Flour 2kg',
                'qty'                 => '100.0000',
                'uom_id'              => $ctn?->id ?? $pcs?->id,
                'virtual_warehouse_id'=> null,
            ]);
        }

        if ($mapped && $hhItem) {
            ContainerExpectedLine::create([
                'container_id'        => $container->id,
                'line_no'             => 2,
                'item_id'             => $hhItem->id,
                'raw_sku'             => $hhItem->sku,
                'raw_description'     => $hhItem->name,
                'qty'                 => '50.0000',
                'uom_id'              => $ctn?->id ?? $pcs?->id,
                'virtual_warehouse_id'=> $vw?->id,
            ]);
        } else {
            ContainerExpectedLine::create([
                'container_id'        => $container->id,
                'line_no'             => 2,
                'item_id'             => null,
                'raw_sku'             => 'HH-001',
                'raw_description'     => 'Dish Soap 500ml',
                'qty'                 => '50.0000',
                'uom_id'              => $ctn?->id ?? $pcs?->id,
                'virtual_warehouse_id'=> null,
            ]);
        }
    }
}
