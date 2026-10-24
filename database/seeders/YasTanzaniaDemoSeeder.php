<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Data\Inventory\StockKey;
use App\Data\Inventory\StockLine;
use App\Data\Inventory\StockPosting;
use App\Data\Inventory\StockReference;
use App\Enums\ContainerStatus;
use App\Enums\Inbound\ApprovalChainDocumentType;
use App\Enums\Inventory\DisposalMethod;
use App\Enums\Inventory\MovementType;
use App\Enums\Inventory\SerialStatus;
use App\Enums\Inventory\StockStatus;
use App\Enums\Inventory\StockStatusRequestType;
use App\Enums\ItemBarcodeType;
use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Enums\Outbound\AllocationRule;
use App\Enums\Outbound\DeliveryOrderStatus;
use App\Enums\ReceiptCondition;
use App\Enums\UomType;
use App\Models\ApprovalChain;
use App\Models\ApprovalChainUser;
use App\Models\Container;
use App\Models\Department;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderLine;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\GrnReceipt;
use App\Models\Inventory\SerialNumber;
use App\Models\Inventory\StockBalance;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\ItemCategory;
use App\Models\ItemCategoryUser;
use App\Models\ItemUom;
use App\Models\Location;
use App\Models\Partner;
use App\Models\ReasonCode;
use App\Models\Setting;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\Foundation\DocumentNumber;
use App\Services\Inbound\GrnWorkflow;
use App\Services\Inbound\PurchaseOrderWorkflow;
use App\Services\Inventory\AdjustmentWorkflow;
use App\Services\Inventory\CycleCountWorkflow;
use App\Services\Inventory\DisposalWorkflow;
use App\Services\Inventory\StockLedger;
use App\Services\Inventory\StockStatusRequestWorkflow;
use App\Services\Inventory\TransferWorkflow;
use Carbon\Carbon;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class YasTanzaniaDemoSeeder extends Seeder
{
    // ── Config ────────────────────────────────────────────────────────────────
    private const MONTHS       = 5;
    private const PO_PER_WEEK  = 3;
    private const MRN_PER_WEEK = 6;
    private const STN_PER_WEEK = 2;
    private const PASSWORD     = 'Password@123';

    // ── Runtime state ─────────────────────────────────────────────────────────
    private Generator $faker;
    private array $whs   = [];
    private array $vws   = [];
    private array $depts = [];
    private array $items = [];
    private array $locs  = [];
    private array $rcodes = [];
    private array $users = [];
    private array $deptApprovers = [];
    private array $suppliers = [];

    private User $admin;
    private User $wm;
    private User $ws;
    private User $clerk1;
    private User $clerk2;

    private int $cntPo   = 0;
    private int $cntGrn  = 0;
    private int $cntMrn  = 0;
    private int $cntStn  = 0;
    private int $cntAdj  = 0;
    private int $cntCnt  = 0;
    private int $cntDisp = 0;

    // ─────────────────────────────────────────────────────────────────────────

    public function run(): void
    {
        Notification::fake();
        config(['ai.enabled' => false]);

        mt_srand(2026);
        $this->faker = Factory::create();
        $this->faker->seed(2026);

        $this->command?->info('─── Yas Tanzania Demo Seeder ───');

        $this->clearStockAndDocuments();
        DB::transaction(fn () => $this->seedSettings());
        DB::transaction(fn () => $this->extendWarehouses());
        DB::transaction(fn () => $this->extendDepts());
        DB::transaction(fn () => $this->extendPartners());
        DB::transaction(fn () => $this->extendItems());
        DB::transaction(fn () => $this->seedUsers());
        DB::transaction(fn () => $this->seedApprovalChains());
        DB::transaction(fn () => $this->seedCategoryUsers());

        $this->loadState();
        $this->seedHistory();

        Carbon::setTestNow(); // reset clock

        $this->printTotals();
    }

    // ─── Cleanup ─────────────────────────────────────────────────────────────

    private function clearStockAndDocuments(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $tables = [
            'activity_log',
            'batches',
            'containers', 'container_discrepancies', 'container_documents',
            'container_expected_lines', 'container_status_history', 'container_yard_moves',
            'cycle_counts', 'cycle_count_lines', 'stock_count_requests',
            'delivery_orders', 'delivery_order_lines', 'delivery_order_allocations', 'delivery_order_approvals',
            'dispatches', 'dispatch_items',
            'disposal_requests', 'disposal_request_lines', 'disposal_request_approvals', 'disposal_request_line_serials',
            'gate_passes',
            'grns', 'grn_lines', 'grn_receipts', 'grn_receipt_serials', 'grn_variance_approvals',
            'internal_movements', 'internal_movement_lines', 'internal_movement_line_serials',
            'lpns',
            'notifications',
            'outbound_delivery_notes', 'outbound_delivery_note_lines',
            'pick_lists', 'pick_tasks', 'pick_task_serials',
            'po_issues',
            'proof_of_deliveries', 'pod_files',
            'purchase_orders', 'purchase_order_lines', 'purchase_order_approvals', 'purchase_order_assignments',
            'putaway_tasks', 'putaway_task_splits',
            'report_requests', 'report_summaries',
            'serial_numbers',
            'stock_adjustments', 'stock_adjustment_lines',
            'stock_balances', 'stock_movements', 'stock_movement_serials', 'stock_transactions',
            'stock_reservations',
            'stock_status_requests', 'stock_status_request_lines',
            'stock_status_request_approvals', 'stock_status_request_line_serials',
            'stock_transfers', 'stock_transfer_lines', 'stock_transfer_line_serials',
            'warehouse_transfers', 'warehouse_transfer_lines',
        ];
        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $this->command?->info('  Stock & documents cleared ✓');
    }

    // ─── Settings ────────────────────────────────────────────────────────────

    private function seedSettings(): void
    {
        foreach ([
            'company_name'     => 'Yas Tanzania',
            'company_address'  => 'Dar es Salaam, Tanzania',
            'default_currency' => 'TZS',
        ] as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        $this->command?->info('  Settings ✓');
    }

    // ─── Warehouses ──────────────────────────────────────────────────────────

    private function extendWarehouses(): void
    {
        $dar = Warehouse::updateOrCreate(['code' => 'DAR'], [
            'name'      => 'Dar es Salaam Main Warehouse',
            'address'   => 'Port Area, Dar es Salaam',
            'is_active' => true,
        ]);
        $this->buildDarExtensions($dar);

        foreach (['ARU' => ['Arusha Depot', 'Industrial Area, Arusha'],
                  'MWZ' => ['Mwanza Depot',  'Kirumba, Mwanza']] as $code => [$name, $addr]) {
            $wh = Warehouse::updateOrCreate(['code' => $code], [
                'name'      => $name,
                'address'   => $addr,
                'is_active' => true,
            ]);
            $this->buildDepot($wh, $code);
        }

        $this->command?->info('  Warehouses ✓  (DAR extended + ARU + MWZ)');
    }

    private function buildDarExtensions(Warehouse $wh): void
    {
        $c = $wh->code;

        // Second receiving dock
        $this->loc($wh->id, null, LocationLevel::Bin, '02', "{$c}-DOCK-02", LocationType::ReceivingDock);

        // Zone B: 2 aisles × 2 racks × 2 levels × 2 bins
        $zB = $this->loc($wh->id, null, LocationLevel::Zone, 'B', "{$c}-B", LocationType::Storage);
        foreach (['01', '02'] as $a) {
            $ai = $this->loc($wh->id, $zB->id, LocationLevel::Aisle, $a, "{$c}-B-{$a}", LocationType::Storage);
            foreach (['01', '02'] as $r) {
                $ra = $this->loc($wh->id, $ai->id, LocationLevel::Rack, $r, "{$c}-B-{$a}-{$r}", LocationType::Storage);
                foreach (['01', '02'] as $l) {
                    $lv = $this->loc($wh->id, $ra->id, LocationLevel::ShelfLevel, $l, "{$c}-B-{$a}-{$r}-{$l}", LocationType::Storage);
                    foreach (['A', 'B'] as $b) {
                        $this->loc($wh->id, $lv->id, LocationLevel::Bin, $b, "{$c}-B-{$a}-{$r}-{$l}-{$b}", LocationType::Storage, 2);
                    }
                }
            }
        }

        // Hazardous zone (2 bins)
        $hz = $this->loc($wh->id, null, LocationLevel::Zone, 'HAZ', "{$c}-HAZ", LocationType::Hazardous);
        $this->loc($wh->id, $hz->id, LocationLevel::Bin, '01', "{$c}-HAZ-01", LocationType::Hazardous);
        $this->loc($wh->id, $hz->id, LocationLevel::Bin, '02', "{$c}-HAZ-02", LocationType::Hazardous);

        // Disposal and transit
        $this->loc($wh->id, null, LocationLevel::Bin, '01',     "{$c}-DISP-01", LocationType::Disposal);
        $this->loc($wh->id, null, LocationLevel::Bin, 'TRANSIT', "{$c}-TRANSIT", LocationType::Transit);

        // Ensure defaults
        $dock1 = Location::where('warehouse_id', $wh->id)->where('code', "{$c}-DOCK-01")->first();
        $qua   = Location::where('warehouse_id', $wh->id)->where('code', "{$c}-QUA-01")->first();
        $stg   = Location::where('warehouse_id', $wh->id)->where('code', "{$c}-STG-01")->first();

        $wh->update(array_filter([
            'default_receiving_dock_location_id' => $dock1?->id ?? $wh->default_receiving_dock_location_id,
            'default_quarantine_location_id'     => $qua?->id  ?? $wh->default_quarantine_location_id,
            'default_staging_location_id'        => $stg?->id  ?? $wh->default_staging_location_id,
        ]));
    }

    private function buildDepot(Warehouse $wh, string $c): void
    {
        $dock1 = $this->loc($wh->id, null, LocationLevel::Bin, '01', "{$c}-DOCK-01", LocationType::ReceivingDock);
        $this->loc($wh->id, null, LocationLevel::Bin, '02', "{$c}-DOCK-02", LocationType::ReceivingDock);
        $stg   = $this->loc($wh->id, null, LocationLevel::Bin, '01', "{$c}-STG-01",  LocationType::Staging);
        $qua   = $this->loc($wh->id, null, LocationLevel::Bin, '01', "{$c}-QUA-01",  LocationType::Quarantine);

        $hz = $this->loc($wh->id, null, LocationLevel::Zone, 'HAZ', "{$c}-HAZ", LocationType::Hazardous);
        $this->loc($wh->id, $hz->id, LocationLevel::Bin, '01', "{$c}-HAZ-01", LocationType::Hazardous);
        $this->loc($wh->id, $hz->id, LocationLevel::Bin, '02', "{$c}-HAZ-02", LocationType::Hazardous);

        $this->loc($wh->id, null, LocationLevel::Bin, '01',     "{$c}-DISP-01", LocationType::Disposal);
        $this->loc($wh->id, null, LocationLevel::Bin, 'TRANSIT', "{$c}-TRANSIT", LocationType::Transit);

        // Storage zones A + B: 1 aisle × 2 racks × 2 levels × 2 bins
        foreach (['A', 'B'] as $z) {
            $zn = $this->loc($wh->id, null, LocationLevel::Zone, $z, "{$c}-{$z}", LocationType::Storage);
            $ai = $this->loc($wh->id, $zn->id, LocationLevel::Aisle, '01', "{$c}-{$z}-01", LocationType::Storage);
            foreach (['01', '02'] as $r) {
                $ra = $this->loc($wh->id, $ai->id, LocationLevel::Rack, $r, "{$c}-{$z}-01-{$r}", LocationType::Storage);
                foreach (['01', '02'] as $l) {
                    $lv = $this->loc($wh->id, $ra->id, LocationLevel::ShelfLevel, $l, "{$c}-{$z}-01-{$r}-{$l}", LocationType::Storage);
                    foreach (['A', 'B'] as $b) {
                        $this->loc($wh->id, $lv->id, LocationLevel::Bin, $b, "{$c}-{$z}-01-{$r}-{$l}-{$b}", LocationType::Storage, 2);
                    }
                }
            }
        }

        $wh->update([
            'default_receiving_dock_location_id' => $dock1->id,
            'default_quarantine_location_id'     => $qua->id,
            'default_staging_location_id'        => $stg->id,
        ]);
    }

    private function loc(int $whId, ?int $parentId, LocationLevel $level, string $seg, string $code, LocationType $type, ?int $maxPallets = null): Location
    {
        $isPickable = $level === LocationLevel::Bin
            && in_array($type, [LocationType::Storage, LocationType::Staging, LocationType::Hazardous], true);

        $loc = Location::updateOrCreate(
            ['warehouse_id' => $whId, 'code' => $code],
            ['parent_id' => $parentId, 'level' => $level, 'segment_code' => $seg,
             'type' => $type, 'status' => LocationStatus::Active,
             'is_pickable' => $isPickable, 'max_pallets' => $maxPallets],
        );

        $parentPath = $parentId ? (Location::find($parentId)?->path ?? '/') : '/';
        $path = $parentPath . $loc->id . '/';
        if ($loc->path !== $path) {
            $loc->update(['path' => $path]);
            $loc->path = $path;
        }
        return $loc;
    }

    // ─── Departments & VWs ───────────────────────────────────────────────────

    private function extendDepts(): void
    {
        foreach ([
            ['YAS-RTL', 'Yas Retail',     'YAS-RTL', 'Yas Retail Stock'],
            ['YAS-NET', 'Yas Network',    'YAS-NET', 'Yas Network Stock'],
            ['YAS-ENT', 'Yas Enterprise', 'YAS-ENT', 'Yas Enterprise Stock'],
        ] as [$dc, $dn, $vc, $vn]) {
            $dept = Department::updateOrCreate(['code' => $dc], ['name' => $dn, 'is_active' => true]);
            VirtualWarehouse::updateOrCreate(['code' => $vc], [
                'name' => $vn, 'department_id' => $dept->id, 'is_active' => true,
            ]);
        }
        $this->command?->info('  Departments + VWs ✓  (RTL, NET, ENT)');
    }

    // ─── Partners ────────────────────────────────────────────────────────────

    private function extendPartners(): void
    {
        foreach ([
            ['HUAWEI',    'Huawei Technologies', ['supplier', 'shipper']],
            ['ZTE',       'ZTE Corporation',      ['supplier']],
            ['NOKIA',     'Nokia',                ['supplier']],
            ['MAERSK',    'Maersk Line',          ['shipping_line']],
            ['FASTTRUCK', 'FastTruck Logistics',  ['transporter']],
            ['YAS',       'Yas Tanzania',         ['consignee', 'customer']],
        ] as [$code, $name, $type]) {
            Partner::updateOrCreate(['code' => $code], [
                'name' => $name, 'type' => $type, 'is_active' => true, 'country' => 'Tanzania',
            ]);
        }
        $this->command?->info('  Partners ✓  (6 partners)');
    }

    // ─── Items ───────────────────────────────────────────────────────────────

    private function extendItems(): void
    {
        $pcs  = UnitOfMeasure::updateOrCreate(['code' => 'PCS'],  ['name' => 'Pieces',  'type' => UomType::Count,  'is_active' => true]);
        $ctn  = UnitOfMeasure::updateOrCreate(['code' => 'CTN'],  ['name' => 'Carton',  'type' => UomType::Count,  'is_active' => true]);
        $box  = UnitOfMeasure::updateOrCreate(['code' => 'BOX'],  ['name' => 'Box',     'type' => UomType::Count,  'is_active' => true]);
        $roll = UnitOfMeasure::updateOrCreate(['code' => 'ROLL'], ['name' => 'Roll',    'type' => UomType::Count,  'is_active' => true]);
        $m    = UnitOfMeasure::updateOrCreate(['code' => 'M'],    ['name' => 'Metre',   'type' => UomType::Length, 'is_active' => true]);

        $catDev = ItemCategory::updateOrCreate(['name' => 'Devices'],           ['is_active' => true]);
        $catNet = ItemCategory::updateOrCreate(['name' => 'Network Equipment'], ['is_active' => true]);
        $catPow = ItemCategory::updateOrCreate(['name' => 'Power'],             ['is_active' => true]);
        $catAcc = ItemCategory::updateOrCreate(['name' => 'Accessories'],       ['is_active' => true]);

        $vwRtl = VirtualWarehouse::firstWhere('code', 'YAS-RTL');
        $vwNet = VirtualWarehouse::firstWhere('code', 'YAS-NET');
        $vwEnt = VirtualWarehouse::firstWhere('code', 'YAS-ENT');

        // [sku, name, cat, base_uom, serial, batch, expiry, hazardous, min, max, vw, packs, alloc, barcode]
        $defs = [
            ['RTL-MIFI-4G',  'MiFi 4G Router',      $catDev, $pcs,  true,  false, false, false, 20,  500,  $vwRtl, [[$ctn, 10]],   ['fifo'], '6901234500011'],
            ['RTL-PHONE-A1', 'Smartphone A1',        $catDev, $pcs,  true,  false, false, false, 10,  300,  $vwRtl, [[$ctn, 20]],   ['fifo'], '6901234500043'],
            ['RTL-SIM-PACK', 'SIM Starter Pack',     $catAcc, $pcs,  false, true,  false, false, 100, 5000, $vwRtl, [[$box, 100]],  ['fifo'], '6901234500050'],
            ['NET-ANT-4G',   '4G Antenna',           $catNet, $pcs,  false, false, false, false, 5,   200,  $vwNet, [[$ctn, 4]],    ['fifo'], '6901234500035'],
            ['NET-RRU-01',   'Radio Unit',           $catNet, $pcs,  true,  false, false, false, 2,   50,   $vwNet, [],             ['fifo'], '6901234500067'],
            ['NET-BAT-48V',  '48V Tower Battery',    $catPow, $pcs,  false, true,  true,  true,  5,   100,  $vwNet, [],             ['fefo'], '6901234500028'],
            ['NET-CBL-RF1',  'RF Cable',             $catAcc, $m,    false, false, false, false, 50,  2000, $vwNet, [[$roll, 100]], ['fifo'], '6901234500074'],
            ['ENT-RTR-01',   'Enterprise Router',    $catNet, $pcs,  true,  false, false, false, 1,   30,   $vwEnt, [],             ['fifo'], '6901234500081'],
        ];

        foreach ($defs as [$sku, $name, $cat, $uom, $serial, $batch, $expiry, $haz, $min, $max, $vw, $packs, $alloc, $bc]) {
            $item = Item::updateOrCreate(['sku' => $sku], [
                'name'                         => $name,
                'category_id'                  => $cat?->id,
                'base_uom_id'                  => $uom->id,
                'track_serial'                 => $serial,
                'track_batch'                  => $batch,
                'track_expiry'                 => $expiry,
                'is_hazardous'                 => $haz,
                'min_stock'                    => $min,
                'max_stock'                    => $max,
                'allocation_rules'             => $alloc,
                'default_virtual_warehouse_id' => $vw?->id,
                'is_active'                    => true,
            ]);

            ItemUom::updateOrCreate(
                ['item_id' => $item->id, 'uom_id' => $uom->id],
                ['factor_to_base' => '1.0000', 'is_default_issue' => true, 'is_default_purchase' => empty($packs)],
            );

            foreach ($packs as [$packUom, $factor]) {
                ItemUom::updateOrCreate(
                    ['item_id' => $item->id, 'uom_id' => $packUom->id],
                    ['factor_to_base' => (string) $factor, 'is_default_purchase' => true, 'is_default_issue' => false],
                );
            }

            ItemBarcode::updateOrCreate(['barcode' => $bc], [
                'item_id' => $item->id, 'uom_id' => $uom->id,
                'type' => ItemBarcodeType::Supplier, 'is_primary' => true,
            ]);
        }

        $this->command?->info('  Items ✓  (8 items with UoMs + barcodes)');
    }

    // ─── Users ───────────────────────────────────────────────────────────────

    private function seedUsers(): void
    {
        $hash = Hash::make(self::PASSWORD);

        $rows = [
            ['rtl@yas.test',        'Salma Mbeki (RTL)',          'department-user'],
            ['net@yas.test',        'Hassan Juma (NET)',           'department-user'],
            ['ent@yas.test',        'Grace Kimaro (ENT)',          'department-user'],
            ['rtlmgr@yas.test',     'Amina Rashid (RTL Mgr)',      'department-manager'],
            ['netmgr@yas.test',     'John Mwita (NET Mgr)',        'department-manager'],
            ['entmgr@yas.test',     'Diana Chiwawa (ENT Mgr)',     'department-manager'],
            ['wm@yas.test',         'Emmanuel Kaaya (WM)',         'warehouse-manager'],
            ['ws@yas.test',         'Rehema Mwanga (WS)',          'warehouse-supervisor'],
            ['clerk1@yas.test',     'Peter Makunda (Clerk)',       'storekeeper'],
            ['clerk2@yas.test',     'Faith Banda (Clerk)',         'storekeeper'],
            ['officer@yas.test',    'Abel Nyerere (Officer)',      'storekeeper'],
            ['analyst@yas.test',    'Neema Sanga (Analyst)',       'data-analyst'],
            ['hse@yas.test',        'Joseph Mushi (HSE)',          'storekeeper'],
            ['auditor@yas.test',    'Ruth Kileo (Auditor)',        'viewer-auditor'],
            ['procurement@yas.test','Kevin Mwambona (Proc)',       'department-user'],
        ];

        $deptMap = ['rtl' => 'YAS-RTL', 'net' => 'YAS-NET', 'ent' => 'YAS-ENT'];

        foreach ($rows as [$email, $name, $role]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => $hash, 'status' => 'active', 'email_verified_at' => now()],
            );
            $user->syncRoles([$role]);

            // Attach dept users to their VW
            $prefix = explode('@', $email)[0];
            if ($role === 'department-user' && isset($deptMap[$prefix])) {
                $vw = VirtualWarehouse::firstWhere('code', $deptMap[$prefix]);
                if ($vw) {
                    $user->virtualWarehouses()->syncWithoutDetaching([$vw->id]);
                }
            }
        }

        $this->command?->info('  Users ✓  (15 Yas test users, Password@123)');
    }

    // ─── Approval Chains ─────────────────────────────────────────────────────

    private function seedApprovalChains(): void
    {
        $map = [
            'YAS-RTL' => 'rtlmgr@yas.test',
            'YAS-NET' => 'netmgr@yas.test',
            'YAS-ENT' => 'entmgr@yas.test',
        ];
        $docTypes = [
            ApprovalChainDocumentType::Po,
            ApprovalChainDocumentType::Mrn,
            ApprovalChainDocumentType::Transfer,
            ApprovalChainDocumentType::Disposal,
            ApprovalChainDocumentType::Do,
        ];

        foreach ($map as $deptCode => $approverEmail) {
            $dept     = Department::firstWhere('code', $deptCode);
            $approver = User::firstWhere('email', $approverEmail);
            if (! $dept || ! $approver) continue;

            foreach ($docTypes as $docType) {
                $chain = ApprovalChain::firstOrCreate(
                    ['department_id' => $dept->id, 'document_type' => $docType, 'level' => 1],
                );
                ApprovalChainUser::firstOrCreate(
                    ['approval_chain_id' => $chain->id, 'user_id' => $approver->id],
                );
            }
        }

        $this->command?->info('  Approval chains ✓  (3 depts × 4 doc types, level 1)');
    }

    // ─── Category Users ──────────────────────────────────────────────────────

    private function seedCategoryUsers(): void
    {
        $dar   = Warehouse::firstWhere('code', 'DAR');
        $clerk = User::firstWhere('email', 'clerk1@yas.test');
        if (! $dar || ! $clerk) return;

        foreach (ItemCategory::where('is_active', true)->get() as $cat) {
            ItemCategoryUser::firstOrCreate(
                ['item_category_id' => $cat->id, 'warehouse_id' => $dar->id, 'user_id' => $clerk->id],
            );
        }

        $this->command?->info('  Category users ✓  (clerk1 → all categories in DAR)');
    }

    // ─── Load runtime state ───────────────────────────────────────────────────

    private function loadState(): void
    {
        $this->whs   = Warehouse::all()->keyBy('code')->all();
        $this->vws   = VirtualWarehouse::all()->keyBy('code')->all();
        $this->depts = Department::all()->keyBy('code')->all();
        $this->items = Item::with('baseUom')->get()->keyBy('sku')->all();
        $this->locs  = Location::all()->keyBy('code')->all();
        $this->rcodes = ReasonCode::where('is_active', true)->get()->keyBy('code')->all();

        $this->admin  = User::role('system-admin')->first()
            ?? User::where('email', 'davidhaule3@gmail.com')->firstOrFail();
        $this->wm     = User::firstWhere('email', 'wm@yas.test')     ?? $this->admin;
        $this->ws     = User::firstWhere('email', 'ws@yas.test')     ?? $this->wm;
        $this->clerk1 = User::firstWhere('email', 'clerk1@yas.test') ?? $this->wm;
        $this->clerk2 = User::firstWhere('email', 'clerk2@yas.test') ?? $this->clerk1;

        $this->users = [
            'YAS-RTL' => User::firstWhere('email', 'rtl@yas.test') ?? $this->wm,
            'YAS-NET' => User::firstWhere('email', 'net@yas.test') ?? $this->wm,
            'YAS-ENT' => User::firstWhere('email', 'ent@yas.test') ?? $this->wm,
        ];
        $this->deptApprovers = [
            'YAS-RTL' => User::firstWhere('email', 'rtlmgr@yas.test') ?? $this->wm,
            'YAS-NET' => User::firstWhere('email', 'netmgr@yas.test') ?? $this->wm,
            'YAS-ENT' => User::firstWhere('email', 'entmgr@yas.test') ?? $this->wm,
        ];

        $this->suppliers = Partner::whereJsonContains('type', 'supplier')->get()->all();
    }

    // ─── History orchestrator ─────────────────────────────────────────────────

    private function seedHistory(): void
    {
        $ledger     = app(StockLedger::class);
        $poWf       = app(PurchaseOrderWorkflow::class);
        $grnWf      = app(GrnWorkflow::class);
        $transferWf = app(TransferWorkflow::class);
        $adjWf      = app(AdjustmentWorkflow::class);
        $countWf    = app(CycleCountWorkflow::class);
        $disposalWf = app(DisposalWorkflow::class);
        $ssrWf      = app(StockStatusRequestWorkflow::class);

        $start      = now()->subMonths(self::MONTHS)->startOfDay();
        $today      = now()->startOfDay();
        $totalWeeks = (int) ceil($start->diffInDays($today) / 7);

        $month3Start       = $start->copy()->addMonths(2)->startOfMonth();
        $month4Start       = $start->copy()->addMonths(3)->startOfMonth();
        $lastTwoWeeksStart = $today->copy()->subWeeks(2);

        // Day 1: opening balance
        Carbon::setTestNow($start);
        DB::transaction(fn () => $this->seedOpeningBalance($ledger));

        $didObsolete  = false;
        $didDisposal  = false;
        $seenMonths   = [];

        for ($w = 0; $w <= $totalWeeks; $w++) {
            $wkStart = $start->copy()->addWeeks($w);
            $wkEnd   = $wkStart->copy()->addDays(6)->min($today);
            if ($wkStart->gt($today)) break;

            $isOpen = $wkStart->gte($lastTwoWeeksStart);

            // Monthly tasks (first full week of each month)
            $mKey = $wkStart->format('Y-m');
            if (! isset($seenMonths[$mKey])) {
                $seenMonths[$mKey] = true;
                $mDay = $wkStart->copy()->addDays(2)->min($today);
                Carbon::setTestNow($mDay);
                if (! $isOpen) {
                    DB::transaction(fn () => $this->createCycleCount($countWf));
                    DB::transaction(fn () => $this->createAdjustment($adjWf));
                }

                if (! $didObsolete && $wkStart->gte($month3Start)) {
                    $didObsolete = true;
                    DB::transaction(fn () => $this->seedObsolete($ssrWf));
                }
                if (! $didDisposal && $wkStart->gte($month4Start)) {
                    $didDisposal = true;
                    DB::transaction(fn () => $this->seedDisposal($disposalWf));
                }
            }

            // Weekly POs
            for ($p = 0; $p < self::PO_PER_WEEK; $p++) {
                $day = $this->randDay($wkStart, $wkEnd);
                Carbon::setTestNow($day);
                DB::transaction(fn () => $isOpen
                    ? $this->createOpenPo($poWf, $p)
                    : $this->createCompletedPo($poWf, $grnWf, $ledger));
            }

            // Weekly MRNs
            for ($m = 0; $m < self::MRN_PER_WEEK; $m++) {
                $day = $this->randDay($wkStart, $wkEnd);
                Carbon::setTestNow($day);
                DB::transaction(fn () => $isOpen
                    ? $this->createOpenMrn($m)
                    : $this->createCompletedMrn($ledger));
            }

            // Weekly transfers
            for ($t = 0; $t < self::STN_PER_WEEK; $t++) {
                $day = $this->randDay($wkStart, $wkEnd);
                Carbon::setTestNow($day);
                $leaveOpen = $isOpen && $t === 0;
                DB::transaction(fn () => $this->createTransfer($transferWf, $leaveOpen));
            }

            $this->command?->info(
                "  Week {$w}/{$totalWeeks} ({$wkStart->format('d M')}): " .
                "POs={$this->cntPo} MRNs={$this->cntMrn} STNs={$this->cntStn}"
            );
        }

        // Extra open-docs set for last 2 weeks
        Carbon::setTestNow($lastTwoWeeksStart->copy()->addDays(3));
        DB::transaction(fn () => $this->seedOpenDocExtras($adjWf, $countWf, $ledger, $disposalWf));
    }

    // ─── Opening balance ─────────────────────────────────────────────────────

    private function seedOpeningBalance(StockLedger $ledger): void
    {
        $darBin = $this->firstStorageBin('DAR');
        $aruBin = $this->firstStorageBin('ARU');
        $mwzBin = $this->firstStorageBin('MWZ');
        $hazBin = $this->firstHazBin('DAR') ?? $darBin;
        if (! $darBin) return;

        $serialSets = [
            // [sku, vw, bin, qty, batchNo?, expiryDays?, serialPrefix?]
            ['RTL-MIFI-4G',  'YAS-RTL', $darBin, 150, null, null, 'MIFI-OB'],
            ['RTL-PHONE-A1', 'YAS-RTL', $darBin, 80,  null, null, 'PHN-OB'],
            ['RTL-SIM-PACK', 'YAS-RTL', $darBin, 2000,'SIM-OB-0001', null, null],
            ['NET-ANT-4G',   'YAS-NET', $darBin, 80,  null, null, null],
            ['NET-RRU-01',   'YAS-NET', $darBin, 20,  null, null, 'RRU-OB'],
            ['NET-BAT-48V',  'YAS-NET', $hazBin, 40,  'BAT-OB-0001', 730, null],
            ['NET-CBL-RF1',  'YAS-NET', $darBin, 1000,null, null, null],
            ['ENT-RTR-01',   'YAS-ENT', $darBin, 15,  null, null, 'RTR-OB'],
            // ARU
            ['RTL-MIFI-4G',  'YAS-RTL', $aruBin, 30,  null, null, 'MIFI-ARU'],
            ['NET-ANT-4G',   'YAS-NET', $aruBin, 20,  null, null, null],
            // MWZ
            ['RTL-MIFI-4G',  'YAS-RTL', $mwzBin, 20,  null, null, 'MIFI-MWZ'],
            ['RTL-SIM-PACK', 'YAS-RTL', $mwzBin, 500, 'SIM-OB-MWZ', null, null],
        ];

        foreach ($serialSets as $i => [$sku, $vwCode, $bin, $qty, $batchNo, $expiryDays, $serialPfx]) {
            if (! $bin) continue;
            $item = $this->items[$sku] ?? null;
            $vw   = $this->vws[$vwCode] ?? null;
            if (! $item || ! $vw) continue;

            $serials    = [];
            if ($serialPfx) {
                for ($s = 1; $s <= $qty; $s++) {
                    $serials[] = $serialPfx . '-' . str_pad((string) $s, 5, '0', STR_PAD_LEFT);
                }
            }

            $expiry = $expiryDays ? now()->addDays($expiryDays) : null;

            $ledger->openingBalance(
                [new StockLine(
                    itemId:             $item->id,
                    locationId:         $bin->id,
                    virtualWarehouseId: $vw->id,
                    stockStatus:        StockStatus::Available,
                    enteredQty:         (string) $qty,
                    enteredUomId:       $item->base_uom_id,
                    batchNo:            $batchNo,
                    expiryDate:         $expiry,
                    serials:            $serials,
                )],
                'Opening balance — Yas Tanzania',
                "ob:yas:{$sku}:{$vwCode}:{$bin->id}",
            );
        }

        $this->command?->info('  Opening balances ✓  (12 stock lines, serials included)');
    }

    // ─── Completed PO ────────────────────────────────────────────────────────

    private function createCompletedPo(PurchaseOrderWorkflow $poWf, GrnWorkflow $grnWf, StockLedger $ledger): void
    {
        $deptCode = $this->faker->randomElement(['YAS-RTL', 'YAS-NET', 'YAS-ENT']);
        $dept     = $this->depts[$deptCode] ?? null;
        $vw       = $this->vws[$deptCode] ?? null;
        $deptUser = $this->users[$deptCode] ?? $this->wm;
        $approver = $this->deptApprovers[$deptCode] ?? $this->wm;
        $supplier = $this->faker->randomElement($this->suppliers);
        $dar      = $this->whs['DAR'] ?? null;
        if (! $dept || ! $vw || ! $dar) return;

        $lineItems = $this->itemsForDept($deptCode);
        if (empty($lineItems)) return;
        shuffle($lineItems);
        $lineItems = array_slice($lineItems, 0, $this->faker->numberBetween(1, min(2, count($lineItems))));

        $lines = [];
        foreach ($lineItems as $item) {
            $qty = $item->track_serial ? $this->faker->numberBetween(2, 10) : $this->faker->numberBetween(5, 30);
            $lines[] = [
                'item_id'    => $item->id,
                'uom_id'     => $item->base_uom_id,
                'qty'        => (string) $qty,
                'qty_base'   => (string) $qty,
                'unit_price' => $this->randPrice($item->sku),
                'currency'   => 'TZS',
            ];
        }

        $po = $poWf->create([
            'warehouse_id'         => $dar->id,
            'virtual_warehouse_id' => $vw->id,
            'department_id'        => $dept->id,
            'supplier_partner_id'  => $supplier->id,
            'currency'             => 'TZS',
            'expected_date'        => now()->addDays(14),
            'lines'                => $lines,
        ], $deptUser, true);

        $poWf->submit($po, $deptUser);
        $poWf->approveAtLevel($po, $approver);
        // Category-level approval (clerk1 is category user for all categories in DAR)
        $po->refresh();
        if ($po->status->value === 'pending_approval') {
            try { $poWf->approveAtLevel($po, $this->clerk1); } catch (\Throwable) {}
        }
        $poWf->accept($po, $this->wm);
        $poWf->assignSupervisor($po, $this->ws, $this->wm);
        $poWf->assignClerk($po, $this->clerk1, $this->ws);

        $dock = $this->locs['DAR-DOCK-01'] ?? null;
        if (! $dock) { $this->cntPo++; return; }

        $grn = $grnWf->createDraftForPO($po, $dock->id, [
            'arrival_datetime' => now(),
            'vehicle_type'     => 'truck',
            'vehicle_plate'    => 'T' . $this->faker->numerify('###-###'),
            'driver_name'      => $this->faker->name(),
        ], $this->clerk1);

        $grn->load('lines.item');
        $hasVariance = $this->faker->boolean(12);
        $snSeq = $grn->id * 1000;

        foreach ($grn->lines as $grnLine) {
            $item = $grnLine->item;
            $expectedQty = (int) $grnLine->expected_qty;
            $receivedQty = $hasVariance
                ? max(1, (int) round($expectedQty * $this->faker->randomFloat(2, 0.85, 0.95)))
                : $expectedQty;

            $batchNo    = null;
            $expiryDate = null;
            if ($item->track_batch) {
                $batchNo    = 'B-' . now()->format('Ymd') . '-' . str_pad((string) ($grnLine->id), 4, '0', STR_PAD_LEFT);
                $expiryDate = $item->track_expiry ? now()->addDays($this->faker->numberBetween(365, 730)) : null;
            }

            $serials = [];
            if ($item->track_serial) {
                $pfx = strtoupper(str_replace('-', '', substr($item->sku, -4)));
                for ($s = 0; $s < $receivedQty; $s++) {
                    $serials[] = $pfx . '-PO' . str_pad((string) ($snSeq++), 6, '0', STR_PAD_LEFT);
                }
            }

            GrnReceipt::create([
                'grn_line_id' => $grnLine->id,
                'qty'         => $receivedQty,
                'uom_id'      => $grnLine->uom_id,
                'condition'   => ReceiptCondition::Good,
                'batch_no'    => $batchNo,
                'expiry_date' => $expiryDate,
                'serial_nos'  => empty($serials) ? null : $serials,
                'received_by' => $this->clerk1->id,
            ]);
        }

        $grnWf->completeReceiving($grn, $this->clerk1);
        $this->putawayGrn($grn, $ledger);
        $po->refresh();
        // Only close if status allows it (partial receipts stay open)
        $closeableStatuses = ['pending_putaway','putaway_in_progress','pending_supervisor_validation','completed_pending_verification','issue_raised','issue_resolved'];
        if (in_array($po->status->value, $closeableStatuses, true)) {
            $poWf->closeOrder($po, $this->wm);
        }

        $this->cntPo++;
        $this->cntGrn++;
    }

    private function putawayGrn(Grn $grn, StockLedger $ledger): void
    {
        $dock = $grn->receivingDock;
        if (! $dock) return;

        $grn->load('lines.item');
        foreach ($grn->lines as $line) {
            $item  = $line->item;
            $toBin = $item->is_hazardous
                ? ($this->firstHazBin('DAR') ?? $this->firstStorageBin('DAR'))
                : $this->firstStorageBin('DAR');
            if (! $toBin || $toBin->id === $dock->id) continue;

            $balances = StockBalance::where('item_id', $item->id)
                ->where('location_id', $dock->id)
                ->where('virtual_warehouse_id', $line->virtual_warehouse_id)
                ->where('qty_on_hand', '>', 0)
                ->get();

            foreach ($balances as $balance) {
                $qty     = (string) $balance->qty_on_hand;
                $serials = [];

                if ($item->track_serial) {
                    $serials = SerialNumber::where('item_id', $item->id)
                        ->where('location_id', $dock->id)
                        ->where('virtual_warehouse_id', $line->virtual_warehouse_id)
                        ->where('status', SerialStatus::InStock->value)
                        ->pluck('serial_no')
                        ->toArray();
                    if (empty($serials)) continue;
                    $qty = (string) count($serials);
                }

                $fromKey = new StockKey(
                    itemId:             $item->id,
                    batchId:            $balance->batch_id,
                    lpnId:              null,
                    locationId:         $dock->id,
                    virtualWarehouseId: $line->virtual_warehouse_id,
                    stockStatus:        $balance->stock_status,
                );

                try {
                    $ledger->move(
                        $fromKey, $toBin->id, $qty, $item->base_uom_id,
                        new StockReference('grn', $grn->id, $grn->number),
                        $serials,
                        "putaway:grn:{$grn->id}:bal:{$balance->id}",
                    );
                } catch (\Throwable) {
                    // skip on lock/serial issue
                }
            }
        }
    }

    // ─── Open PO (last 2 weeks) ───────────────────────────────────────────────

    private function createOpenPo(PurchaseOrderWorkflow $poWf, int $idx): void
    {
        static $seq = 0;
        $targetStatuses = ['draft', 'pending_approval', 'approved', 'assigned', 'partial', 'pending_verification', 'issue'];
        $target = $targetStatuses[$seq++ % count($targetStatuses)];

        $deptCode = $this->faker->randomElement(['YAS-RTL', 'YAS-NET', 'YAS-ENT']);
        $dept     = $this->depts[$deptCode] ?? null;
        $vw       = $this->vws[$deptCode] ?? null;
        $deptUser = $this->users[$deptCode] ?? $this->wm;
        $approver = $this->deptApprovers[$deptCode] ?? $this->wm;
        $supplier = $this->faker->randomElement($this->suppliers);
        $dar      = $this->whs['DAR'] ?? null;
        if (! $dept || ! $vw || ! $dar) return;

        $items = $this->itemsForDept($deptCode);
        if (empty($items)) return;
        $item = $this->faker->randomElement($items);

        $po = $poWf->create([
            'warehouse_id'         => $dar->id,
            'virtual_warehouse_id' => $vw->id,
            'department_id'        => $dept->id,
            'supplier_partner_id'  => $supplier->id,
            'currency'             => 'TZS',
            'expected_date'        => now()->addDays($this->faker->numberBetween(7, 30)),
            'lines'                => [[
                'item_id'    => $item->id,
                'uom_id'     => $item->base_uom_id,
                'qty'        => '10',
                'qty_base'   => '10',
                'unit_price' => $this->randPrice($item->sku),
                'currency'   => 'TZS',
            ]],
        ], $deptUser, true);

        if ($target === 'draft') { $this->cntPo++; return; }

        $poWf->submit($po, $deptUser);
        if ($target === 'pending_approval') { $this->cntPo++; return; }

        $poWf->approveAtLevel($po, $approver);
        $po->refresh();
        if ($po->status->value === 'pending_approval') {
            try { $poWf->approveAtLevel($po, $this->clerk1); } catch (\Throwable) {}
            $po->refresh();
        }
        if ($target === 'approved') { $this->cntPo++; return; }

        $poWf->accept($po, $this->wm);
        $poWf->assignSupervisor($po, $this->ws, $this->wm);
        $poWf->assignClerk($po, $this->clerk1, $this->ws);
        if ($target === 'assigned') { $this->cntPo++; return; }

        // Partial receive
        $dock = $this->locs['DAR-DOCK-01'] ?? null;
        if (! $dock) { $this->cntPo++; return; }

        try {
            $grnWf = app(GrnWorkflow::class);
            $grn   = $grnWf->createDraftForPO($po, $dock->id, ['arrival_datetime' => now()], $this->clerk1);
            $grn->load('lines.item');
            $snSeq = $grn->id * 2000;
            foreach ($grn->lines as $grnLine) {
                $rcvQty  = max(1, (int) round((float) $grnLine->expected_qty * 0.5));
                $lineItem = $grnLine->item;
                $serials  = [];
                if ($lineItem?->track_serial) {
                    $pfx = strtoupper(str_replace('-', '', substr($lineItem->sku, -4)));
                    for ($s = 0; $s < $rcvQty; $s++) {
                        $serials[] = $pfx . '-OP' . str_pad((string) ($snSeq++), 6, '0', STR_PAD_LEFT);
                    }
                }
                GrnReceipt::create([
                    'grn_line_id' => $grnLine->id,
                    'qty'         => $rcvQty,
                    'uom_id'      => $grnLine->uom_id,
                    'condition'   => ReceiptCondition::Good,
                    'serial_nos'  => empty($serials) ? null : $serials,
                    'received_by' => $this->clerk1->id,
                ]);
            }
            $grnWf->completeReceiving($grn, $this->clerk1);
            $this->cntGrn++;
        } catch (\Throwable) {}

        if (in_array($target, ['partial', 'pending_verification', 'issue'])) {
            $this->cntPo++;
            return;
        }

        $this->cntPo++;
    }

    // ─── Completed MRN ───────────────────────────────────────────────────────

    private function createCompletedMrn(StockLedger $ledger): void
    {
        $deptCode = $this->faker->randomElement(['YAS-RTL', 'YAS-NET', 'YAS-ENT']);
        $vw       = $this->vws[$deptCode] ?? null;
        $dept     = $this->depts[$deptCode] ?? null;
        $deptUser = $this->users[$deptCode] ?? $this->wm;
        $dar      = $this->whs['DAR'] ?? null;
        if (! $vw || ! $dept || ! $dar) return;

        $items = $this->itemsForDept($deptCode);
        if (empty($items)) return;

        $item  = $this->faker->randomElement($items);
        $avail = $this->availableQty($item->id, $vw->id);
        if ($avail < 1) return;

        $qty = max(1, min((int) floor($avail * 0.15), $this->faker->numberBetween(1, 10)));

        // For serial items, pick actual serials
        $serials = [];
        if ($item->track_serial) {
            $balance = $this->pickBalance($item->id, $vw->id);
            if (! $balance) return;
            $serials = SerialNumber::where('item_id', $item->id)
                ->where('location_id', $balance->location_id)
                ->where('virtual_warehouse_id', $vw->id)
                ->where('status', SerialStatus::InStock->value)
                ->limit($qty)
                ->pluck('serial_no')
                ->toArray();
            if (count($serials) < $qty) {
                $qty = count($serials);
            }
            if ($qty === 0) return;
        }

        $balance = $this->pickBalance($item->id, $vw->id);
        if (! $balance) return;

        $yas      = Partner::firstWhere('code', 'YAS');
        $doNumber = DocumentNumber::next('DO');

        $do = DeliveryOrder::create([
            'number'               => $doNumber,
            'virtual_warehouse_id' => $vw->id,
            'warehouse_id'         => $dar->id,
            'department_id'        => $dept->id,
            'consignee_partner_id' => $yas?->id,
            'required_date'        => now()->addDays(3),
            'allocation_rule'      => AllocationRule::Fifo,
            'status'               => DeliveryOrderStatus::Delivered,
            'requested_by'         => $deptUser->id,
        ]);

        DeliveryOrderLine::create([
            'delivery_order_id'  => $do->id,
            'line_no'            => 1,
            'item_id'            => $item->id,
            'uom_id'             => $item->base_uom_id,
            'requested_qty'      => (string) $qty,
            'requested_qty_base' => (string) $qty,
            'dispatched_qty'     => (string) $qty,
        ]);

        try {
            $ledger->post(new StockPosting(
                type: MovementType::Dispatch,
                reference: new StockReference('delivery_order', $do->id, $doNumber),
                lines: [new StockLine(
                    itemId:             $item->id,
                    locationId:         $balance->location_id,
                    virtualWarehouseId: $vw->id,
                    stockStatus:        StockStatus::Available,
                    enteredQty:         (string) (-$qty),
                    enteredUomId:       $item->base_uom_id,
                    batchId:            $balance->batch_id,
                    serials:            $serials,
                )],
                idempotencyKey: "mrn:{$do->id}:dispatch",
            ));
        } catch (\Throwable) {
            $do->forceDelete();
            return;
        }

        $this->cntMrn++;
    }

    // ─── Open MRN (last 2 weeks) ──────────────────────────────────────────────

    private function createOpenMrn(int $m): void
    {
        $statuses = [
            DeliveryOrderStatus::Draft,
            DeliveryOrderStatus::Submitted,
            DeliveryOrderStatus::Approved,
            DeliveryOrderStatus::Picking,
        ];
        $status   = $statuses[$m % count($statuses)];
        $deptCode = $this->faker->randomElement(['YAS-RTL', 'YAS-NET', 'YAS-ENT']);
        $vw       = $this->vws[$deptCode] ?? null;
        $dept     = $this->depts[$deptCode] ?? null;
        $deptUser = $this->users[$deptCode] ?? $this->wm;
        $dar      = $this->whs['DAR'] ?? null;
        if (! $vw || ! $dept || ! $dar) return;

        $items = $this->itemsForDept($deptCode);
        if (empty($items)) return;
        $item = $this->faker->randomElement($items);
        $yas  = Partner::firstWhere('code', 'YAS');

        DeliveryOrder::create([
            'number'               => DocumentNumber::next('DO'),
            'virtual_warehouse_id' => $vw->id,
            'warehouse_id'         => $dar->id,
            'department_id'        => $dept->id,
            'consignee_partner_id' => $yas?->id,
            'required_date'        => now()->addDays($this->faker->numberBetween(1, 14)),
            'allocation_rule'      => AllocationRule::Fifo,
            'status'               => $status,
            'requested_by'         => $deptUser->id,
        ]);

        $this->cntMrn++;
    }

    // ─── Transfer ────────────────────────────────────────────────────────────

    private function createTransfer(TransferWorkflow $wf, bool $leaveOpen = false): void
    {
        $codes = ['YAS-RTL', 'YAS-NET', 'YAS-ENT'];
        $fromCode = $this->faker->randomElement($codes);
        $toCodes  = array_values(array_filter($codes, fn ($c) => $c !== $fromCode));
        $toCode   = $this->faker->randomElement($toCodes);

        $fromVw = $this->vws[$fromCode] ?? null;
        $toVw   = $this->vws[$toCode]   ?? null;
        if (! $fromVw || ! $toVw) return;

        $items = $this->itemsForDept($fromCode);
        if (empty($items)) return;

        $item  = $this->faker->randomElement($items);
        $avail = $this->availableQty($item->id, $fromVw->id);
        if ($avail < 2) return;

        $balance = $this->pickBalance($item->id, $fromVw->id);
        if (! $balance) return;

        $qty = max(1, (int) floor($avail * 0.1));

        try {
            $transfer = $wf->create([
                'from_virtual_warehouse_id' => $fromVw->id,
                'to_virtual_warehouse_id'   => $toVw->id,
                'is_physical'               => false,
                'notes'                     => "Demo STN {$fromCode}→{$toCode}",
                'lines' => [[
                    'item_id'     => $item->id,
                    'location_id' => $balance->location_id,
                    'batch_id'    => $balance->batch_id,
                    'qty'         => (string) $qty,
                    'uom_id'      => $item->base_uom_id,
                ]],
            ], $this->wm);

            $wf->submit($transfer, $this->wm);
            if (! $leaveOpen) {
                $wf->accept($transfer, $this->wm);
            }
            $this->cntStn++;
        } catch (\Throwable) {}
    }

    // ─── Cycle count ─────────────────────────────────────────────────────────

    private function createCycleCount(CycleCountWorkflow $wf): void
    {
        $deptCode = $this->faker->randomElement(['YAS-RTL', 'YAS-NET', 'YAS-ENT']);
        $vw       = $this->vws[$deptCode] ?? null;
        if (! $vw) return;

        try {
            $count = $wf->create([
                'virtual_warehouse_id' => $vw->id,
                'notes'                => "Monthly count — {$deptCode}",
            ], $this->ws);

            $count->load('lines.item');
            if ($count->lines->isEmpty()) return;

            foreach ($count->lines as $line) {
                // Serial items: count exact system qty (no variance — serials would be required)
                if ($line->item?->track_serial) {
                    $counted = (float) $line->system_qty;
                } else {
                    $variance = $this->faker->boolean(25) ? $this->faker->numberBetween(-2, 2) : 0;
                    $counted  = max(0.0, (float) $line->system_qty + $variance);
                }
                $wf->recordCount($line, (string) $counted, $this->clerk1);
            }

            $count->refresh();
            $wf->submitForReview($count, $this->clerk1);
            $count->refresh();
            $wf->post($count, $this->ws);
            $this->cntCnt++;
        } catch (\Throwable) {}
    }

    // ─── Adjustment ──────────────────────────────────────────────────────────

    private function createAdjustment(AdjustmentWorkflow $wf): void
    {
        $deptCode = $this->faker->randomElement(['YAS-RTL', 'YAS-NET', 'YAS-ENT']);
        $vw       = $this->vws[$deptCode] ?? null;
        if (! $vw) return;

        $items = $this->itemsForDept($deptCode);
        if (empty($items)) return;

        $item    = $this->faker->randomElement($items);
        if ($item->track_serial) return; // Skip serial items for adjustments
        $balance = $this->pickBalance($item->id, $vw->id);
        if (! $balance || (float) $balance->qty_on_hand < 2) return;

        $sign = $this->faker->randomElement([1, -1]);
        $qty  = (string) ($sign * $this->faker->numberBetween(1, 2));
        $rc   = $this->faker->randomElement(array_values($this->rcodes) ?: [null]);

        try {
            $adj = $wf->create([
                'virtual_warehouse_id' => $vw->id,
                'notes'                => $this->faker->randomElement(['Damage found', 'Surplus count', 'Reconciliation']),
                'lines' => [[
                    'item_id'        => $item->id,
                    'location_id'    => $balance->location_id,
                    'batch_id'       => $balance->batch_id,
                    'reason_code_id' => $rc?->id,
                    'qty'            => $qty,
                    'uom_id'         => $item->base_uom_id,
                ]],
            ], $this->ws);
            $wf->submit($adj, $this->ws);
            $adj->refresh();
            // Auto-posts on submit when below threshold; only approve if still pending
            if ($adj->status->value === 'pending_approval') {
                $wf->approve($adj, $this->wm);
            }
            $this->cntAdj++;
        } catch (\Throwable) {}
    }

    // ─── Month 3: Obsolete ───────────────────────────────────────────────────

    private function seedObsolete(StockStatusRequestWorkflow $ssrWf): void
    {
        $vw       = $this->vws['YAS-RTL'] ?? null;
        $dept     = $this->depts['YAS-RTL'] ?? null;
        $deptUser = $this->users['YAS-RTL'] ?? $this->wm;
        $item     = $this->items['RTL-SIM-PACK'] ?? null;
        if (! $vw || ! $dept || ! $item) return;

        $balance = $this->pickBalance($item->id, $vw->id);
        if (! $balance || (float) $balance->qty_on_hand < 5) return;

        try {
            $ssr = $ssrWf->create([
                'virtual_warehouse_id' => $vw->id,
                'department_id'        => $dept->id,
                'type'                 => StockStatusRequestType::MarkObsolete,
                'reason'               => 'End of product lifecycle — SIM v1 discontinued',
                'lines' => [[
                    'item_id'     => $item->id,
                    'location_id' => $balance->location_id,
                    'batch_id'    => $balance->batch_id,
                    'qty'         => '5',
                ]],
            ], $deptUser);
            $ssrWf->submit($ssr, $deptUser);
            $ssrWf->approve($ssr, $this->wm);
        } catch (\Throwable) {}
    }

    // ─── Month 4: Disposal ───────────────────────────────────────────────────

    private function seedDisposal(DisposalWorkflow $wf): void
    {
        $vw       = $this->vws['YAS-NET'] ?? null;
        $dept     = $this->depts['YAS-NET'] ?? null;
        $deptUser = $this->users['YAS-NET'] ?? $this->wm;
        $approver = $this->deptApprovers['YAS-NET'] ?? $this->wm;
        $item     = $this->items['NET-ANT-4G'] ?? null;
        $dispBin  = $this->locs['DAR-DISP-01'] ?? null;
        if (! $vw || ! $dept || ! $item) return;

        $balance = $this->pickBalance($item->id, $vw->id);
        if (! $balance || (float) $balance->qty_on_hand < 3) return;

        try {
            $req = $wf->create([
                'virtual_warehouse_id' => $vw->id,
                'department_id'        => $dept->id,
                'method'               => DisposalMethod::Auction,
                'reason'               => 'Damaged — non-repairable units',
                'lines' => [[
                    'item_id'      => $item->id,
                    'location_id'  => $balance->location_id,
                    'batch_id'     => $balance->batch_id,
                    'qty'          => '3',
                    'stock_status' => StockStatus::Available,
                ]],
            ], $deptUser);

            $wf->submit($req, $deptUser);
            $wf->approve($req, $approver);
            $wf->approve($req, $this->wm);

            if ($dispBin) {
                $wf->moveToDisposalArea($req, $dispBin->id, $this->wm);
            }

            $wf->complete($req, [
                'buyer_name' => 'ABC Scrap Dealers',
                'sale_price' => '150000',
                'sale_date'  => now()->format('Y-m-d'),
            ], $this->wm);

            $this->cntDisp++;
        } catch (\Throwable) {}
    }

    // ─── Open-doc extras (last 2 weeks) ──────────────────────────────────────

    private function seedOpenDocExtras(AdjustmentWorkflow $adjWf, CycleCountWorkflow $countWf, StockLedger $ledger, DisposalWorkflow $dispWf): void
    {
        // Pending adjustment
        $vwNet = $this->vws['YAS-NET'] ?? null;
        if ($vwNet) {
            $item = $this->items['NET-ANT-4G'] ?? null;
            if ($item) {
                $balance = $this->pickBalance($item->id, $vwNet->id);
                if ($balance && (float) $balance->qty_on_hand >= 2) {
                    try {
                        $adj = $adjWf->create([
                            'virtual_warehouse_id' => $vwNet->id,
                            'notes'                => 'Pending — spot check',
                            'lines' => [[
                                'item_id'     => $item->id,
                                'location_id' => $balance->location_id,
                                'batch_id'    => $balance->batch_id,
                                'qty'         => '-1',
                                'uom_id'      => $item->base_uom_id,
                            ]],
                        ], $this->ws);
                        $adjWf->submit($adj, $this->ws); // Leaves pending approval
                    } catch (\Throwable) {}
                }
            }

            // Open cycle count
            try {
                $countWf->create(['virtual_warehouse_id' => $vwNet->id, 'notes' => 'Open count'], $this->ws);
                $this->cntCnt++;
            } catch (\Throwable) {}
        }

        // Disposal pending (destroy method) - for NET-BAT-48V
        $batItem = $this->items['NET-BAT-48V'] ?? null;
        $batVw   = $this->vws['YAS-NET'] ?? null;
        $batDept = $this->depts['YAS-NET'] ?? null;
        if ($batItem && $batVw && $batDept) {
            $balance = $this->pickBalance($batItem->id, $batVw->id);
            if ($balance && (float) $balance->qty_on_hand >= 2) {
                try {
                    $req = $dispWf->create([
                        'virtual_warehouse_id' => $batVw->id,
                        'department_id'        => $batDept->id,
                        'method'               => DisposalMethod::Destroy,
                        'reason'               => 'End-of-life — safe disposal required',
                        'lines' => [[
                            'item_id'      => $batItem->id,
                            'location_id'  => $balance->location_id,
                            'batch_id'     => $balance->batch_id,
                            'qty'          => '2',
                            'stock_status' => StockStatus::Available,
                        ]],
                    ], $this->users['YAS-NET'] ?? $this->wm);
                    $dispWf->submit($req, $this->users['YAS-NET'] ?? $this->wm);
                    $dispWf->approve($req, $this->deptApprovers['YAS-NET'] ?? $this->wm);
                    $dispWf->approve($req, $this->wm);
                    // Leave at Approved (pending move)
                } catch (\Throwable) {}
            }
        }

        // Container over dwell warning (created 35 days ago)
        $dar      = $this->whs['DAR'] ?? null;
        $yardSlot = $this->locs['DAR-Y-01'] ?? null;
        if ($dar && $yardSlot) {
            Carbon::setTestNow(now()->subDays(35));
            Container::firstOrCreate(
                ['container_no' => 'MSCU9876543'],
                [
                    'number'              => DocumentNumber::next('CTR'),
                    'size_type'           => '40HC',
                    'seal_no'             => 'SL99999',
                    'warehouse_id'        => $dar->id,
                    'status'              => ContainerStatus::Parked,
                    'yard_location_id'    => $yardSlot->id,
                ],
            );
            Carbon::setTestNow(now()->addDays(35));
        }

        // Expiring + expired batches for NET-BAT-48V
        $hazBin = $this->firstHazBin('DAR') ?? $this->firstStorageBin('DAR');
        if ($batItem && $batVw && $hazBin) {
            foreach ([
                ['BAT-EXPIRING-30D', 20,  '5'],
                ['BAT-EXPIRED',      -10, '3'],
            ] as [$batchNo, $expiryOffsetDays, $qty]) {
                try {
                    $ledger->openingBalance(
                        [new StockLine(
                            itemId:             $batItem->id,
                            locationId:         $hazBin->id,
                            virtualWarehouseId: $batVw->id,
                            stockStatus:        StockStatus::Available,
                            enteredQty:         $qty,
                            enteredUomId:       $batItem->base_uom_id,
                            batchNo:            $batchNo,
                            expiryDate:         now()->addDays($expiryOffsetDays),
                        )],
                        'Demo: expiry test batch',
                        "ob:demo:{$batchNo}",
                    );
                } catch (\Throwable) {}
            }
        }

        // Items with no movement for 90+ days (slow movers)
        // NET-CBL-RF1 opening balance was done on day 1; by now it has no outbound, so it's already a slow mover
        // ENT-RTR-01 similarly — no action needed, the report will show them
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function itemsForDept(string $deptCode): array
    {
        $map = [
            'YAS-RTL' => ['RTL-MIFI-4G', 'RTL-PHONE-A1', 'RTL-SIM-PACK'],
            'YAS-NET' => ['NET-ANT-4G',  'NET-RRU-01',   'NET-BAT-48V', 'NET-CBL-RF1'],
            'YAS-ENT' => ['ENT-RTR-01'],
        ];
        return array_values(array_filter(
            array_map(fn ($s) => $this->items[$s] ?? null, $map[$deptCode] ?? [])
        ));
    }

    private function availableQty(int $itemId, int $vwId): float
    {
        return (float) StockBalance::where('item_id', $itemId)
            ->where('virtual_warehouse_id', $vwId)
            ->where('stock_status', StockStatus::Available->value)
            ->selectRaw('COALESCE(SUM(qty_on_hand - qty_allocated), 0) as avail')
            ->value('avail');
    }

    private function pickBalance(int $itemId, int $vwId): ?StockBalance
    {
        return StockBalance::where('item_id', $itemId)
            ->where('virtual_warehouse_id', $vwId)
            ->where('stock_status', StockStatus::Available->value)
            ->whereRaw('qty_on_hand - qty_allocated > 0')
            ->orderByDesc('qty_on_hand')
            ->first();
    }

    private function firstStorageBin(string $whCode): ?Location
    {
        $wh = $this->whs[$whCode] ?? null;
        if (! $wh) return null;
        return Location::where('warehouse_id', $wh->id)
            ->where('type', LocationType::Storage->value)
            ->where('level', LocationLevel::Bin->value)
            ->where('status', LocationStatus::Active->value)
            ->first();
    }

    private function firstHazBin(string $whCode): ?Location
    {
        $wh = $this->whs[$whCode] ?? null;
        if (! $wh) return null;
        return Location::where('warehouse_id', $wh->id)
            ->where('type', LocationType::Hazardous->value)
            ->where('level', LocationLevel::Bin->value)
            ->first();
    }

    private function randDay(Carbon $from, Carbon $to): Carbon
    {
        $diff = max(0, (int) $from->diffInDays($to));
        return $from->copy()->addDays($this->faker->numberBetween(0, $diff));
    }

    private function randPrice(string $sku): string
    {
        $ranges = [
            'RTL-MIFI-4G'  => [80000,   150000],
            'RTL-PHONE-A1' => [250000,  700000],
            'RTL-SIM-PACK' => [1500,      3000],
            'NET-ANT-4G'   => [50000,   120000],
            'NET-RRU-01'   => [800000, 2000000],
            'NET-BAT-48V'  => [500000, 1500000],
            'NET-CBL-RF1'  => [5000,    15000],
            'ENT-RTR-01'   => [1500000, 4000000],
        ];
        [$lo, $hi] = $ranges[$sku] ?? [10000, 100000];
        return (string) $this->faker->numberBetween($lo, $hi);
    }

    // ─── Summary ─────────────────────────────────────────────────────────────

    private function printTotals(): void
    {
        $start = now()->subMonths(self::MONTHS)->format('d M Y');
        $end   = now()->format('d M Y');

        $this->command?->info('');
        $this->command?->info('═══════════ Yas Tanzania Demo Data ═══════════');
        $this->command?->info("  Period       : {$start} → {$end}");
        $this->command?->info("  POs          : {$this->cntPo}");
        $this->command?->info("  GRNs         : {$this->cntGrn}");
        $this->command?->info("  MRNs (DOs)   : {$this->cntMrn}");
        $this->command?->info("  STNs         : {$this->cntStn}");
        $this->command?->info("  Adjustments  : {$this->cntAdj}");
        $this->command?->info("  Cycle Counts : {$this->cntCnt}");
        $this->command?->info("  Disposals    : {$this->cntDisp}");
        $this->command?->info('');
        $this->command?->info('  Test users (Password@123):');
        $this->command?->info('    rtl / net / ent @yas.test              — department-user');
        $this->command?->info('    rtlmgr / netmgr / entmgr @yas.test     — department-manager');
        $this->command?->info('    wm@yas.test                            — warehouse-manager');
        $this->command?->info('    ws@yas.test                            — warehouse-supervisor');
        $this->command?->info('    clerk1 / clerk2 @yas.test              — storekeeper');
        $this->command?->info('    hse / auditor / analyst / procurement @yas.test');
        $this->command?->info('═══════════════════════════════════════════════');
    }
}
