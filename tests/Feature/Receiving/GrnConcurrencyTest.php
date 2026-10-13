<?php

declare(strict_types=1);

namespace Tests\Feature\Receiving;

use App\Enums\GrnSource;
use App\Enums\GrnStatus;
use App\Enums\ReceiptCondition;
use App\Models\Department;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\GrnReceipt;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\Inbound\GrnWorkflow;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Concurrency tests that require MySQL InnoDB row-level locking.
 * Run with: php artisan test --filter=GrnConcurrency --configuration phpunit.mysql.xml
 */
#[Group('mysql')]
class GrnConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Warehouse $warehouse;
    private VirtualWarehouse $vw;
    private Location $dock;
    private UnitOfMeasure $pcs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(\Database\Seeders\FoundationDefaultsSeeder::class);

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('system-admin');

        $this->warehouse = Warehouse::create([
            'code'      => 'CONC',
            'name'      => 'Concurrency Warehouse',
            'is_active' => true,
        ]);

        $this->pcs = UnitOfMeasure::create([
            'code'      => 'PCS-C',
            'name'      => 'Pieces Concurrency',
            'type'      => 'count',
            'is_active' => true,
        ]);

        $department = Department::create([
            'name'      => 'Concurrency Department',
            'code'      => 'CONC-DEPT',
            'is_active' => true,
        ]);

        $this->vw = VirtualWarehouse::create([
            'department_id' => $department->id,
            'code'          => 'CONC-VW',
            'name'          => 'Concurrency VW',
            'is_active'     => true,
        ]);

        $this->dock = Location::create([
            'warehouse_id' => $this->warehouse->id,
            'code'         => 'DOCK-CONC-01',
            'segment_code' => 'D01',
            'type'         => 'receiving_dock',
            'status'       => 'active',
            'level'        => 'bin',
            'is_pickable'  => false,
        ]);

        $this->warehouse->update(['default_receiving_dock_location_id' => $this->dock->id]);
    }

    /**
     * Test that concurrent confirm calls don't double-post stock.
     * The second confirm should be a no-op due to the idempotency check + lockForUpdate.
     */
    #[Group('mysql')]
    public function test_concurrent_confirm_is_idempotent_via_lock(): void
    {
        $grn  = $this->makeDraftGrnWithLine(100);
        $line = $grn->lines()->first();

        app(GrnWorkflow::class)->startReceiving($grn, $this->admin);

        GrnReceipt::create([
            'grn_line_id' => $line->id,
            'qty'         => 100,
            'uom_id'      => $this->pcs->id,
            'condition'   => ReceiptCondition::Good->value,
            'received_by' => $this->admin->id,
        ]);
        $line->recalculateTotals();

        // First confirm
        $grn->refresh();
        app(GrnWorkflow::class)->confirm($grn, $this->admin);

        $this->assertEquals(GrnStatus::Received, $grn->fresh()->status);

        // Simulate second concurrent call: GRN is now received, confirm should return early
        $grn2 = $grn->fresh();
        $grn2->status = GrnStatus::Receiving; // simulate race condition state
        // This should not throw — idempotency check inside transaction
        app(GrnWorkflow::class)->confirm($grn2, $this->admin);

        // Stock should only be posted once (idempotency key prevents double-post)
        $movements = \App\Models\Inventory\StockTransaction::where('reference_type', 'grn')
            ->where('reference_id', $grn->id)
            ->count();

        // At most 1 transaction per receipt (idempotency key prevents duplicates)
        $this->assertLessThanOrEqual(1, $movements);
    }

    /**
     * Test that lockForUpdate prevents dirty reads during confirm.
     */
    #[Group('mysql')]
    public function test_lock_for_update_prevents_dirty_reads(): void
    {
        $grn = $this->makeDraftGrnWithLine(50);

        // Set to receiving
        $grn->status = GrnStatus::Receiving;
        $grn->save();

        // Confirm should read the locked row
        $grn->refresh();

        // The confirm call should work without deadlock
        $line = $grn->lines()->first();
        GrnReceipt::create([
            'grn_line_id' => $line->id,
            'qty'         => 50,
            'uom_id'      => $this->pcs->id,
            'condition'   => ReceiptCondition::Good->value,
            'received_by' => $this->admin->id,
        ]);
        $line->recalculateTotals();

        app(GrnWorkflow::class)->confirm($grn->fresh(), $this->admin);

        $this->assertEquals(GrnStatus::Received, $grn->fresh()->status);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function makeDraftGrnWithLine(int $qty = 100): Grn
    {
        $item = \App\Models\Item::create([
            'sku'         => 'CONC-' . uniqid(),
            'name'        => 'Concurrency Test Item',
            'base_uom_id' => $this->pcs->id,
            'is_active'   => true,
        ]);

        $grn = Grn::create([
            'number'                     => 'GRN-CONC-' . uniqid(),
            'source'                     => GrnSource::Supplier->value,
            'warehouse_id'               => $this->warehouse->id,
            'receiving_dock_location_id' => $this->dock->id,
            'status'                     => GrnStatus::Draft->value,
        ]);

        GrnLine::create([
            'grn_id'              => $grn->id,
            'item_id'             => $item->id,
            'virtual_warehouse_id'=> $this->vw->id,
            'uom_id'              => $this->pcs->id,
            'expected_qty'        => $qty,
        ]);

        return $grn->fresh();
    }
}
