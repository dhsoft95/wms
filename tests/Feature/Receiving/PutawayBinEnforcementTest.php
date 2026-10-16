<?php

declare(strict_types=1);

use App\Enums\ContainerStatus;
use App\Enums\GrnStatus;
use App\Enums\LocationLevel;
use App\Enums\LocationType;
use App\Models\Container;
use App\Models\Grn;
use App\Models\Item;
use App\Models\Location;
use App\Models\PutawayTask;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\Inbound\ContainerWorkflow;
use App\Services\Inbound\PutawayEngine;
use App\Services\Inventory\StockLineValidator;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\RolePermissionSeeder())->run();
    (new \Database\Seeders\FoundationDefaultsSeeder())->run();
});

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Returns [warehouse, dock, item, vw] for putaway tests.
 */
function putawayTestFixtures(): array
{
    $wh   = Warehouse::factory()->create(['code' => 'BTEST', 'name' => 'Bin Test', 'is_active' => true]);
    $dock = Location::factory()->create([
        'warehouse_id' => $wh->id,
        'level'        => LocationLevel::Bin->value,
        'type'         => LocationType::ReceivingDock->value,
        'status'       => 'active',
    ]);
    $wh->update(['default_receiving_dock_location_id' => $dock->id]);

    $item = Item::factory()->create();
    $vw   = VirtualWarehouse::factory()->create(['is_active' => true]);
    $grn  = Grn::create([
        'number'       => 'GRN-BIN-001',
        'source'       => 'manual',
        'warehouse_id' => $wh->id,
        'status'       => GrnStatus::Received->value,
    ]);

    $task = PutawayTask::create([
        'grn_id'              => $grn->id,
        'item_id'             => $item->id,
        'virtual_warehouse_id'=> $vw->id,
        'qty'                 => '5.0000',
        'from_location_id'    => $dock->id,
        'status'              => 'open',
    ]);

    return compact('wh', 'dock', 'item', 'vw', 'grn', 'task');
}

// ── PutawayEngine::execute — rejects non-bin destination ─────────────────────

test('execute putaway to zone-level location throws DomainException', function () {
    ['task' => $task, 'wh' => $wh] = putawayTestFixtures();
    $user = User::factory()->create(['status' => 'active']);

    $zone = Location::factory()->create([
        'warehouse_id' => $wh->id,
        'level'        => LocationLevel::Zone->value,
        'type'         => LocationType::Storage->value,
        'status'       => 'active',
    ]);

    expect(fn () => app(PutawayEngine::class)->execute($task, $zone, $user))
        ->toThrow(\DomainException::class, 'not a bin');
});

// ── PutawayEngine::suggest — never returns a non-bin ─────────────────────────

test('suggest returns null when only zone-level storage locations exist in warehouse', function () {
    ['task' => $task, 'wh' => $wh] = putawayTestFixtures();

    // Only a zone (no bins) in this warehouse
    Location::factory()->create([
        'warehouse_id' => $wh->id,
        'level'        => LocationLevel::Zone->value,
        'type'         => LocationType::Storage->value,
        'status'       => 'active',
    ]);

    $suggestion = app(PutawayEngine::class)->suggest($task);

    expect($suggestion)->toBeNull();
});

test('suggest returns a bin-level location, not a zone', function () {
    ['task' => $task, 'wh' => $wh] = putawayTestFixtures();

    // Zone (non-bin) and a storage bin
    Location::factory()->create([
        'warehouse_id' => $wh->id,
        'level'        => LocationLevel::Zone->value,
        'type'         => LocationType::Storage->value,
        'status'       => 'active',
    ]);
    $bin = Location::factory()->create([
        'warehouse_id' => $wh->id,
        'level'        => LocationLevel::Bin->value,
        'type'         => LocationType::Storage->value,
        'status'       => 'active',
    ]);

    $suggestion = app(PutawayEngine::class)->suggest($task);

    expect($suggestion)->not->toBeNull();
    expect($suggestion->id)->toBe($bin->id);
    expect($suggestion->level)->toBe(LocationLevel::Bin);
});

// ── ContainerWorkflow::park — zone-level yard slot rejected ───────────────────

test('park container in zone-level yard_slot throws InvalidArgumentException', function () {
    $user = User::factory()->create(['status' => 'active']);

    $yardZone = Location::factory()->create([
        'level'  => LocationLevel::Zone->value,
        'type'   => LocationType::YardSlot->value,
        'status' => 'active',
    ]);

    $container = Container::factory()->create(['status' => ContainerStatus::Arrived]);

    expect(fn () => app(ContainerWorkflow::class)->park($container, $yardZone, $user))
        ->toThrow(\InvalidArgumentException::class);
});

// ── StockLineValidator — bin-level enforcement ────────────────────────────────

test('StockLineValidator rejects a zone-level location', function () {
    $item = Item::factory()->create(['track_batch' => false, 'track_expiry' => false]);
    $vw   = VirtualWarehouse::factory()->create(['is_active' => true]);
    $wh   = Warehouse::factory()->create(['is_active' => true]);

    $zone = Location::factory()->create([
        'warehouse_id' => $wh->id,
        'level'        => LocationLevel::Zone->value,
        'type'         => LocationType::Storage->value,
        'status'       => 'active',
    ]);

    $errors = StockLineValidator::check($item, $zone, $vw, null, null, null, '10');

    expect($errors)->not->toBeEmpty();
    expect($errors[0]['column'])->toBe('location_code');
    expect($errors[0]['message'])->toContain('not a bin');
});

test('StockLineValidator accepts a bin-level location', function () {
    $item = Item::factory()->create(['track_batch' => false, 'track_expiry' => false]);
    $vw   = VirtualWarehouse::factory()->create(['is_active' => true]);
    $wh   = Warehouse::factory()->create(['is_active' => true]);

    $bin = Location::factory()->create([
        'warehouse_id' => $wh->id,
        'level'        => LocationLevel::Bin->value,
        'type'         => LocationType::Storage->value,
        'status'       => 'active',
    ]);

    $errors = StockLineValidator::check($item, $bin, $vw, null, null, null, '10');

    expect($errors)->toBeEmpty();
});
