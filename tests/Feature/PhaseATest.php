<?php

declare(strict_types=1);

use App\Actions\Foundation\CreateItemAction;
use App\Enums\Inventory\StockStatus;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderLine;
use App\Models\Inventory\Batch;
use App\Models\Inventory\StockBalance;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\ItemCategory;
use App\Models\ItemCategoryUser;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Rules\NoConflictingAllocationRules;
use App\Services\Outbound\AllocationService;
use Database\Seeders\FoundationDefaultsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(FoundationDefaultsSeeder::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// 1. FIFO sort
// ─────────────────────────────────────────────────────────────────────────────

it('FIFO sort picks oldest received_at first', function () {
    $pcs  = UnitOfMeasure::factory()->create(['code' => 'PCS1', 'type' => 'count']);
    $item = Item::factory()->create(['base_uom_id' => $pcs->id, 'allocation_rules' => ['fifo']]);

    $older = StockBalance::factory()->create([
        'item_id'   => $item->id,
        'qty_on_hand' => '10.0000',
        'qty_allocated' => '0.0000',
        'stock_status' => StockStatus::Available,
        'received_at' => now()->subDays(10),
    ]);

    $newer = StockBalance::factory()->create([
        'item_id'   => $item->id,
        'qty_on_hand' => '10.0000',
        'qty_allocated' => '0.0000',
        'stock_status' => StockStatus::Available,
        'received_at' => now()->subDays(1),
    ]);

    $balances = StockBalance::where('item_id', $item->id)->get();

    $service  = app(AllocationService::class);
    $do       = mockDoWithVw($item);
    $line     = mockLine($item, $do);

    // Use reflection to access private sortCandidates
    $ref    = new ReflectionClass($service);
    $method = $ref->getMethod('sortCandidates');
    $method->setAccessible(true);

    $sorted = $method->invoke($service, $balances, $do, $line);

    expect($sorted->first()->id)->toBe($older->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// 2. LIFO sort
// ─────────────────────────────────────────────────────────────────────────────

it('LIFO sort picks newest received_at first', function () {
    $pcs  = UnitOfMeasure::factory()->create(['code' => 'PCS2', 'type' => 'count']);
    $item = Item::factory()->create(['base_uom_id' => $pcs->id, 'allocation_rules' => ['lifo']]);

    $older = StockBalance::factory()->create([
        'item_id'   => $item->id,
        'qty_on_hand' => '10.0000',
        'qty_allocated' => '0.0000',
        'stock_status' => StockStatus::Available,
        'received_at' => now()->subDays(10),
    ]);

    $newer = StockBalance::factory()->create([
        'item_id'   => $item->id,
        'qty_on_hand' => '10.0000',
        'qty_allocated' => '0.0000',
        'stock_status' => StockStatus::Available,
        'received_at' => now()->subDays(1),
    ]);

    $balances = StockBalance::where('item_id', $item->id)->get();

    $service = app(AllocationService::class);
    $do      = mockDoWithVw($item);
    $line    = mockLine($item, $do);

    $ref    = new ReflectionClass($service);
    $method = $ref->getMethod('sortCandidates');
    $method->setAccessible(true);

    $sorted = $method->invoke($service, $balances, $do, $line);

    expect($sorted->first()->id)->toBe($newer->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// 3. FEFO sort
// ─────────────────────────────────────────────────────────────────────────────

it('FEFO sort picks earliest expiry first, null expiry last', function () {
    $pcs  = UnitOfMeasure::factory()->create(['code' => 'PCS3', 'type' => 'count']);
    $item = Item::factory()->create(['base_uom_id' => $pcs->id, 'allocation_rules' => ['fefo']]);

    $batchEarly = Batch::factory()->create(['item_id' => $item->id, 'expiry_date' => now()->addDays(10)]);
    $batchLate  = Batch::factory()->create(['item_id' => $item->id, 'expiry_date' => now()->addDays(100)]);
    $batchNull  = Batch::factory()->create(['item_id' => $item->id, 'expiry_date' => null]);

    $balEarly = StockBalance::factory()->create([
        'item_id' => $item->id, 'batch_id' => $batchEarly->id,
        'qty_on_hand' => '5.0000', 'qty_allocated' => '0.0000', 'stock_status' => StockStatus::Available,
    ]);
    $balLate = StockBalance::factory()->create([
        'item_id' => $item->id, 'batch_id' => $batchLate->id,
        'qty_on_hand' => '5.0000', 'qty_allocated' => '0.0000', 'stock_status' => StockStatus::Available,
    ]);
    $balNull = StockBalance::factory()->create([
        'item_id' => $item->id, 'batch_id' => $batchNull->id,
        'qty_on_hand' => '5.0000', 'qty_allocated' => '0.0000', 'stock_status' => StockStatus::Available,
    ]);

    $balances = StockBalance::with('batch')->where('item_id', $item->id)->get();

    $service = app(AllocationService::class);
    $do      = mockDoWithVw($item);
    $line    = mockLine($item, $do);

    $ref    = new ReflectionClass($service);
    $method = $ref->getMethod('sortCandidates');
    $method->setAccessible(true);

    $sorted = $method->invoke($service, $balances, $do, $line);

    expect($sorted->first()->id)->toBe($balEarly->id);
    expect($sorted->last()->id)->toBe($balNull->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// 4. FIFO+LIFO validation fails
// ─────────────────────────────────────────────────────────────────────────────

it('NoConflictingAllocationRules rejects fifo+lifo combination', function () {
    $rule = new NoConflictingAllocationRules();
    $failed = false;
    $rule->validate('allocation_rules', ['fifo', 'lifo'], function ($msg) use (&$failed) {
        $failed = true;
    });
    expect($failed)->toBeTrue();
});

it('NoConflictingAllocationRules passes for valid single rule', function () {
    $rule = new NoConflictingAllocationRules();
    $failed = false;
    $rule->validate('allocation_rules', ['fifo'], function ($msg) use (&$failed) {
        $failed = true;
    });
    expect($failed)->toBeFalse();
});

// ─────────────────────────────────────────────────────────────────────────────
// 5. Combined priority — FEFO primary, FIFO tiebreaker
// ─────────────────────────────────────────────────────────────────────────────

it('fefo+fifo: same expiry date — fifo tiebreaker picks older received_at', function () {
    $pcs  = UnitOfMeasure::factory()->create(['code' => 'PCS5', 'type' => 'count']);
    $item = Item::factory()->create(['base_uom_id' => $pcs->id, 'allocation_rules' => ['fefo', 'fifo']]);

    $sameExpiry = now()->addDays(30)->toDateString();
    $batchA = Batch::factory()->create(['item_id' => $item->id, 'expiry_date' => $sameExpiry]);
    $batchB = Batch::factory()->create(['item_id' => $item->id, 'expiry_date' => $sameExpiry]);

    $balOld = StockBalance::factory()->create([
        'item_id' => $item->id, 'batch_id' => $batchA->id,
        'qty_on_hand' => '5.0000', 'qty_allocated' => '0.0000', 'stock_status' => StockStatus::Available,
        'received_at' => now()->subDays(20),
    ]);
    $balNew = StockBalance::factory()->create([
        'item_id' => $item->id, 'batch_id' => $batchB->id,
        'qty_on_hand' => '5.0000', 'qty_allocated' => '0.0000', 'stock_status' => StockStatus::Available,
        'received_at' => now()->subDays(5),
    ]);

    $balances = StockBalance::with('batch')->where('item_id', $item->id)->get();

    $service = app(AllocationService::class);
    $do      = mockDoWithVw($item);
    $line    = mockLine($item, $do);

    $ref    = new ReflectionClass($service);
    $method = $ref->getMethod('sortCandidates');
    $method->setAccessible(true);

    $sorted = $method->invoke($service, $balances, $do, $line);

    // Same expiry, so FIFO tiebreaker: older received_at wins
    expect($sorted->first()->id)->toBe($balOld->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// 6. Category user sees only their items
// ─────────────────────────────────────────────────────────────────────────────

it('category user sees only items in their assigned category', function () {
    $pcs = UnitOfMeasure::factory()->create(['code' => 'PCS6', 'type' => 'count']);
    $vw  = VirtualWarehouse::factory()->create();
    $wh  = \App\Models\Warehouse::factory()->create();

    $catA = ItemCategory::factory()->create(['name' => 'Category A']);
    $catB = ItemCategory::factory()->create(['name' => 'Category B']);

    $itemA = Item::factory()->create([
        'category_id'                  => $catA->id,
        'base_uom_id'                  => $pcs->id,
        'default_virtual_warehouse_id' => $vw->id,
    ]);
    $itemB = Item::factory()->create([
        'category_id'                  => $catB->id,
        'base_uom_id'                  => $pcs->id,
        'default_virtual_warehouse_id' => $vw->id,
    ]);

    $user = User::factory()->create();
    $user->assignRole('department-user');
    $user->virtualWarehouses()->attach($vw->id);

    ItemCategoryUser::create([
        'user_id'          => $user->id,
        'item_category_id' => $catA->id,
        'warehouse_id'     => $wh->id,
    ]);

    $this->actingAs($user);

    $items = Item::all();

    expect($items->pluck('id'))->toContain($itemA->id);
    expect($items->pluck('id'))->not->toContain($itemB->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// 7. Category user does not see null-category items
// ─────────────────────────────────────────────────────────────────────────────

it('category user cannot see items with no category', function () {
    $pcs = UnitOfMeasure::factory()->create(['code' => 'PCS7', 'type' => 'count']);
    $vw  = VirtualWarehouse::factory()->create();
    $wh  = \App\Models\Warehouse::factory()->create();
    $catA = ItemCategory::factory()->create(['name' => 'Category A7']);

    $noCategory = Item::factory()->create([
        'category_id'                  => null,
        'base_uom_id'                  => $pcs->id,
        'default_virtual_warehouse_id' => $vw->id,
    ]);

    $user = User::factory()->create();
    $user->assignRole('department-user');
    $user->virtualWarehouses()->attach($vw->id);

    ItemCategoryUser::create([
        'user_id'          => $user->id,
        'item_category_id' => $catA->id,
        'warehouse_id'     => $wh->id,
    ]);

    $this->actingAs($user);

    $ids = Item::all()->pluck('id');
    expect($ids)->not->toContain($noCategory->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// 8. Manager sees all items
// ─────────────────────────────────────────────────────────────────────────────

it('warehouse-manager bypasses category scope and sees all items', function () {
    $pcs = UnitOfMeasure::factory()->create(['code' => 'PCS8', 'type' => 'count']);
    $vw  = VirtualWarehouse::factory()->create();
    $catA = ItemCategory::factory()->create(['name' => 'Category A8']);
    $catB = ItemCategory::factory()->create(['name' => 'Category B8']);

    $itemA = Item::factory()->create([
        'category_id'                  => $catA->id,
        'base_uom_id'                  => $pcs->id,
        'default_virtual_warehouse_id' => $vw->id,
    ]);
    $itemB = Item::factory()->create([
        'category_id'                  => $catB->id,
        'base_uom_id'                  => $pcs->id,
        'default_virtual_warehouse_id' => $vw->id,
    ]);
    $nocat = Item::factory()->create([
        'category_id'                  => null,
        'base_uom_id'                  => $pcs->id,
        'default_virtual_warehouse_id' => $vw->id,
    ]);

    $manager = User::factory()->create();
    $manager->assignRole('warehouse-manager');

    $this->actingAs($manager);

    $ids = Item::all()->pluck('id');
    expect($ids)->toContain($itemA->id);
    expect($ids)->toContain($itemB->id);
    expect($ids)->toContain($nocat->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// 9. Stock balance filtered by category
// ─────────────────────────────────────────────────────────────────────────────

it('category user only sees stock balances in their assigned category and warehouse', function () {
    $pcs  = UnitOfMeasure::factory()->create(['code' => 'PCS9', 'type' => 'count']);
    $vw   = VirtualWarehouse::factory()->create();
    $wh   = \App\Models\Warehouse::factory()->create();
    $loc  = Location::factory()->create(['warehouse_id' => $wh->id]);
    $catA = ItemCategory::factory()->create(['name' => 'CatA9']);
    $catB = ItemCategory::factory()->create(['name' => 'CatB9']);

    $itemA = Item::factory()->create([
        'category_id'                  => $catA->id,
        'base_uom_id'                  => $pcs->id,
        'default_virtual_warehouse_id' => $vw->id,
    ]);
    $itemB = Item::factory()->create([
        'category_id'                  => $catB->id,
        'base_uom_id'                  => $pcs->id,
        'default_virtual_warehouse_id' => $vw->id,
    ]);

    $balA = StockBalance::create([
        'item_id'              => $itemA->id,
        'location_id'          => $loc->id,
        'virtual_warehouse_id' => $vw->id,
        'stock_status'         => StockStatus::Available,
        'key_hash'             => hash('sha256', "balA-{$itemA->id}-{$vw->id}-{$loc->id}"),
        'qty_on_hand'          => '10.0000',
        'qty_allocated'        => '0.0000',
    ]);
    $balB = StockBalance::create([
        'item_id'              => $itemB->id,
        'location_id'          => $loc->id,
        'virtual_warehouse_id' => $vw->id,
        'stock_status'         => StockStatus::Available,
        'key_hash'             => hash('sha256', "balB-{$itemB->id}-{$vw->id}-{$loc->id}"),
        'qty_on_hand'          => '10.0000',
        'qty_allocated'        => '0.0000',
    ]);

    $user = User::factory()->create();
    $user->assignRole('department-user');
    $user->virtualWarehouses()->attach($vw->id);

    ItemCategoryUser::create([
        'user_id'          => $user->id,
        'item_category_id' => $catA->id,
        'warehouse_id'     => $wh->id,
    ]);

    $this->actingAs($user);

    $ids = StockBalance::all()->pluck('id');
    expect($ids)->toContain($balA->id);
    expect($ids)->not->toContain($balB->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// 10. Delivery order filtered by category
// ─────────────────────────────────────────────────────────────────────────────

it('category user cannot see delivery orders with lines outside their category', function () {
    $pcs  = UnitOfMeasure::factory()->create(['code' => 'PCS10', 'type' => 'count']);
    $vw   = VirtualWarehouse::factory()->create();
    $wh   = \App\Models\Warehouse::factory()->create();
    $catA = ItemCategory::factory()->create(['name' => 'CatA10']);
    $catB = ItemCategory::factory()->create(['name' => 'CatB10']);

    $itemB = Item::factory()->create(['category_id' => $catB->id, 'base_uom_id' => $pcs->id]);

    $do = DeliveryOrder::factory()->create([
        'virtual_warehouse_id' => $vw->id,
        'warehouse_id'         => $wh->id,
    ]);
    DeliveryOrderLine::factory()->create([
        'delivery_order_id'  => $do->id,
        'item_id'            => $itemB->id,
        'uom_id'             => $pcs->id,
        'requested_qty'      => '1.0000',
        'requested_qty_base' => '1.0000',
    ]);

    $user = User::factory()->create();
    $user->assignRole('department-user');
    $user->virtualWarehouses()->attach($vw->id);

    ItemCategoryUser::create([
        'user_id'          => $user->id,
        'item_category_id' => $catA->id,
        'warehouse_id'     => $wh->id,
    ]);

    $this->actingAs($user);

    $ids = DeliveryOrder::all()->pluck('id');
    expect($ids)->not->toContain($do->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// 11. Auto barcode on create
// ─────────────────────────────────────────────────────────────────────────────

it('CreateItemAction auto-generates an internal barcode equal to the SKU', function () {
    $pcs = UnitOfMeasure::factory()->create(['code' => 'EA11', 'type' => 'count']);

    $item = CreateItemAction::execute(
        itemData: [
            'sku'         => 'TEST-SKU-001',
            'name'        => 'Test Item',
            'base_uom_id' => $pcs->id,
            'is_active'   => true,
        ],
        uoms: [],
        baseUomId: $pcs->id,
    );

    $barcode = ItemBarcode::where('item_id', $item->id)->where('type', 'internal')->first();

    expect($barcode)->not->toBeNull();
    expect($barcode->barcode)->toBe('TEST-SKU-001');
});

// ─────────────────────────────────────────────────────────────────────────────
// 12. Auto barcode suffix when SKU already taken by another item
// ─────────────────────────────────────────────────────────────────────────────

it('CreateItemAction appends suffix when SKU barcode is already taken', function () {
    $pcs = UnitOfMeasure::factory()->create(['code' => 'EA12', 'type' => 'count']);

    // Pre-insert a barcode whose value equals the target SKU, but belonging to a different item
    // (simulates a supplier barcode that happens to match our new item's SKU)
    $other = Item::factory()->create(['base_uom_id' => $pcs->id, 'sku' => 'OTHER-CONFLICT']);
    ItemBarcode::factory()->create([
        'item_id' => $other->id,
        'barcode' => 'CONFLICT-001',
        'uom_id'  => $pcs->id,
        'type'    => 'supplier',
    ]);

    $item = CreateItemAction::execute(
        itemData: [
            'sku'         => 'CONFLICT-001',
            'name'        => 'Another Item',
            'base_uom_id' => $pcs->id,
            'is_active'   => true,
        ],
        uoms: [],
        baseUomId: $pcs->id,
    );

    $barcode = ItemBarcode::where('item_id', $item->id)->where('type', 'internal')->first();
    expect($barcode)->not->toBeNull();
    expect($barcode->barcode)->toBe('CONFLICT-001-01');
});

// ─────────────────────────────────────────────────────────────────────────────
// 13. Backfill migration — items get barcodes
// ─────────────────────────────────────────────────────────────────────────────

it('backfill migration creates internal barcodes for items that have none', function () {
    $pcs  = UnitOfMeasure::factory()->create(['code' => 'EA13', 'type' => 'count']);
    $item = Item::factory()->create(['sku' => 'BACKFILL-001', 'base_uom_id' => $pcs->id]);

    // Ensure no internal barcode exists
    expect(ItemBarcode::where('item_id', $item->id)->where('type', 'internal')->exists())->toBeFalse();

    // Run the backfill migration directly
    $migration = require database_path('migrations/2026_10_17_000007_backfill_item_internal_barcodes.php');
    $migration->up();

    expect(ItemBarcode::where('item_id', $item->id)->where('type', 'internal')->exists())->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// 14. gate-security role gone
// ─────────────────────────────────────────────────────────────────────────────

it('gate-security role no longer exists after rename migration', function () {
    // The migration renames the role; seeder now uses warehouse-officer
    expect(Role::where('name', 'gate-security')->exists())->toBeFalse();
});

// ─────────────────────────────────────────────────────────────────────────────
// 15. warehouse-officer role exists with gate.verify permission
// ─────────────────────────────────────────────────────────────────────────────

it('warehouse-officer role exists and has gate.verify permission', function () {
    $role = Role::where('name', 'warehouse-officer')->first();
    expect($role)->not->toBeNull();
    expect($role->hasPermissionTo('gate.verify'))->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// 16. Notification sent on DO submission with approval required
// ─────────────────────────────────────────────────────────────────────────────

it('CategoryApprovalNeeded notification sent to category users when DO is submitted for approval', function () {
    Notification::fake();

    $pcs = UnitOfMeasure::factory()->create(['code' => 'PCS16', 'type' => 'count']);
    $vw  = VirtualWarehouse::factory()->create();
    $wh  = \App\Models\Warehouse::factory()->create();
    $cat = ItemCategory::factory()->create(['name' => 'Cat16']);

    $item = Item::factory()->create([
        'base_uom_id'                => $pcs->id,
        'category_id'                => $cat->id,
        'dispatch_requires_approval' => true,
        'dispatch_approval_qty'      => null,
    ]);

    // Create submitter
    $submitter = User::factory()->create();
    $submitter->assignRole('department-user');

    // Create category responsible user
    $catUser = User::factory()->create();
    $catUser->assignRole('storekeeper');
    ItemCategoryUser::create([
        'user_id'          => $catUser->id,
        'item_category_id' => $cat->id,
        'warehouse_id'     => $wh->id,
    ]);

    $do = DeliveryOrder::factory()->create([
        'virtual_warehouse_id' => $vw->id,
        'warehouse_id'         => $wh->id,
        'status'               => \App\Enums\Outbound\DeliveryOrderStatus::Draft,
    ]);

    DeliveryOrderLine::factory()->create([
        'delivery_order_id'   => $do->id,
        'item_id'             => $item->id,
        'uom_id'              => $pcs->id,
        'requested_qty'       => '5.0000',
        'requested_qty_base'  => '5.0000',
    ]);

    $loc = \App\Models\Location::factory()->create(['warehouse_id' => $wh->id]);
    \App\Models\Inventory\StockBalance::create([
        'item_id'              => $item->id,
        'location_id'          => $loc->id,
        'virtual_warehouse_id' => $vw->id,
        'stock_status'         => StockStatus::Available,
        'key_hash'             => hash('sha256', "cat-approval-{$item->id}-{$vw->id}"),
        'qty_on_hand'          => '10.0000',
        'qty_allocated'        => '0.0000',
    ]);

    $workflow = app(\App\Services\Outbound\DeliveryOrderWorkflow::class);
    $workflow->submit($do, $submitter);

    Notification::assertSentTo($catUser, \App\Notifications\CategoryApprovalNeeded::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// 17. Notification sent on adjustment submission
// ─────────────────────────────────────────────────────────────────────────────

it('CategoryApprovalNeeded notification sent when adjustment is submitted for approval', function () {
    Notification::fake();

    $pcs = UnitOfMeasure::factory()->create(['code' => 'PCS17', 'type' => 'count']);
    $vw  = VirtualWarehouse::factory()->create();
    $wh  = \App\Models\Warehouse::factory()->create();
    $cat = ItemCategory::factory()->create(['name' => 'Cat17']);

    $item = Item::factory()->create([
        'base_uom_id'         => $pcs->id,
        'category_id'         => $cat->id,
        'adjustment_approval' => 'required',
    ]);

    $submitter = User::factory()->create();
    $submitter->assignRole('storekeeper');

    $catUser = User::factory()->create();
    $catUser->assignRole('storekeeper');
    ItemCategoryUser::create([
        'user_id'          => $catUser->id,
        'item_category_id' => $cat->id,
        'warehouse_id'     => $wh->id,
    ]);

    $reasonCode = \App\Models\ReasonCode::factory()->create(['applies_to' => 'adjustment']);
    $location   = Location::factory()->create(['warehouse_id' => $wh->id, 'type' => 'storage', 'status' => 'active']);

    $workflow = app(\App\Services\Inventory\AdjustmentWorkflow::class);

    $adj = $workflow->create([
        'virtual_warehouse_id' => $vw->id,
        'lines' => [[
            'item_id'        => $item->id,
            'location_id'    => $location->id,
            'reason_code_id' => $reasonCode->id,
            'qty'            => '5.0000',
            'uom_id'         => $pcs->id,
        ]],
    ], $submitter);

    $workflow->submit($adj, $submitter);

    Notification::assertSentTo($catUser, \App\Notifications\CategoryApprovalNeeded::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// 18. Mixed-category DO — category user cannot approve
// ─────────────────────────────────────────────────────────────────────────────

it('category user cannot approve a DO with lines outside their assigned categories', function () {
    $pcs  = UnitOfMeasure::factory()->create(['code' => 'PCS18', 'type' => 'count']);
    $wh   = \App\Models\Warehouse::factory()->create();
    $vw   = VirtualWarehouse::factory()->create();
    $catA = ItemCategory::factory()->create(['name' => 'CatA18']);
    $catB = ItemCategory::factory()->create(['name' => 'CatB18']);

    $itemA = Item::factory()->create(['category_id' => $catA->id, 'base_uom_id' => $pcs->id]);
    $itemB = Item::factory()->create(['category_id' => $catB->id, 'base_uom_id' => $pcs->id]);

    $do = DeliveryOrder::factory()->create([
        'virtual_warehouse_id' => $vw->id,
        'warehouse_id'         => $wh->id,
        'status'               => \App\Enums\Outbound\DeliveryOrderStatus::Submitted,
    ]);
    DeliveryOrderLine::factory()->create([
        'delivery_order_id'  => $do->id,
        'item_id'            => $itemA->id,
        'uom_id'             => $pcs->id,
        'requested_qty'      => '1.0000',
        'requested_qty_base' => '1.0000',
    ]);
    DeliveryOrderLine::factory()->create([
        'delivery_order_id'  => $do->id,
        'item_id'            => $itemB->id,
        'uom_id'             => $pcs->id,
        'requested_qty'      => '1.0000',
        'requested_qty_base' => '1.0000',
    ]);

    // Category user assigned to catA only
    $catUser = User::factory()->create();
    $catUser->assignRole('storekeeper');
    $catUser->givePermissionTo('orders.approve.warehouse');
    ItemCategoryUser::create([
        'user_id'          => $catUser->id,
        'item_category_id' => $catA->id,
        'warehouse_id'     => $wh->id,
    ]);

    expect($catUser->can('approveWarehouse', $do))->toBeFalse();
});

it('category user can approve a DO when all lines are in their assigned categories', function () {
    $pcs  = UnitOfMeasure::factory()->create(['code' => 'PCS18B', 'type' => 'count']);
    $wh   = \App\Models\Warehouse::factory()->create();
    $vw   = VirtualWarehouse::factory()->create();
    $catA = ItemCategory::factory()->create(['name' => 'CatA18B']);

    $itemA = Item::factory()->create(['category_id' => $catA->id, 'base_uom_id' => $pcs->id]);

    $do = DeliveryOrder::factory()->create([
        'virtual_warehouse_id' => $vw->id,
        'warehouse_id'         => $wh->id,
        'status'               => \App\Enums\Outbound\DeliveryOrderStatus::Submitted,
    ]);
    DeliveryOrderLine::factory()->create([
        'delivery_order_id'  => $do->id,
        'item_id'            => $itemA->id,
        'uom_id'             => $pcs->id,
        'requested_qty'      => '5.0000',
        'requested_qty_base' => '5.0000',
    ]);

    $catUser = User::factory()->create();
    $catUser->assignRole('storekeeper');
    $catUser->givePermissionTo('orders.approve.warehouse');
    ItemCategoryUser::create([
        'user_id'          => $catUser->id,
        'item_category_id' => $catA->id,
        'warehouse_id'     => $wh->id,
    ]);

    expect($catUser->can('approveWarehouse', $do))->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// Helper functions
// ─────────────────────────────────────────────────────────────────────────────

function mockDoWithVw(Item $item): DeliveryOrder
{
    $vw = VirtualWarehouse::factory()->create();
    $wh = \App\Models\Warehouse::factory()->create();

    return DeliveryOrder::factory()->make([
        'virtual_warehouse_id' => $vw->id,
        'warehouse_id'         => $wh->id,
    ]);
}

function mockLine(Item $item, DeliveryOrder $do): DeliveryOrderLine
{
    $line = new DeliveryOrderLine();
    $line->item_id = $item->id;
    $line->item    = $item;
    return $line;
}
