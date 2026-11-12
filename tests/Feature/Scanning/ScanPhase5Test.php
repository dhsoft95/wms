<?php

declare(strict_types=1);

use App\Enums\Outbound\DeliveryOrderStatus;
use App\Livewire\Outbound\Dispatch\Index as DispatchIndex;
use App\Models\DeliveryOrder;
use App\Models\ScanEvent;
use App\Models\ScannerDevice;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    (new \Database\Seeders\RolePermissionSeeder())->run();
    (new \Database\Seeders\FoundationDefaultsSeeder())->run();
});

// ── Shared fixture ─────────────────────────────────────────────────────────────

function p5BaseFixtures(): array
{
    $wh   = Warehouse::factory()->create(['is_active' => true]);
    $vw   = VirtualWarehouse::factory()->create(['warehouse_id' => $wh->id, 'is_active' => true]);
    $user = User::factory()->create(['status' => 'active']);
    return compact('wh', 'vw', 'user');
}

// ── 1. Scan event logged on success ───────────────────────────────────────────

it('logs a ScanEvent with success=true when a valid DO barcode is scanned', function () {
    ['wh' => $wh, 'vw' => $vw, 'user' => $user] = p5BaseFixtures();

    $do = DeliveryOrder::factory()->create([
        'warehouse_id'         => $wh->id,
        'virtual_warehouse_id' => $vw->id,
        'status'               => DeliveryOrderStatus::Picked->value,
    ]);

    Livewire::actingAs($user)
        ->test(DispatchIndex::class)
        ->set('scanInput', $do->number)
        ->call('processScan');

    expect(ScanEvent::count())->toBe(1);

    $event = ScanEvent::first();
    expect($event->success)->toBeTrue()
        ->and($event->user_id)->toBe($user->id)
        ->and($event->raw_value)->toBe($do->number)
        ->and($event->resolved_type)->toBe('dispatch_order')
        ->and($event->error_message)->toBeNull();
});

// ── 2. Scan event logged on failure ───────────────────────────────────────────

it('logs a ScanEvent with success=false when an unknown barcode is scanned', function () {
    ['user' => $user] = p5BaseFixtures();

    Livewire::actingAs($user)
        ->test(DispatchIndex::class)
        ->set('scanInput', 'TOTALLY-UNKNOWN-BARCODE-XYZ-99')
        ->call('processScan');

    expect(ScanEvent::count())->toBe(1);

    $event = ScanEvent::first();
    expect($event->success)->toBeFalse()
        ->and($event->user_id)->toBe($user->id)
        ->and($event->raw_value)->toBe('TOTALLY-UNKNOWN-BARCODE-XYZ-99')
        ->and($event->resolved_type)->toBeNull()
        ->and($event->error_message)->not->toBeNull();
});

// ── 3. ScanEvent links to scanner device when device session is set ────────────

it('links ScanEvent to scanner_device when scan_device_id is in session', function () {
    ['wh' => $wh, 'vw' => $vw, 'user' => $user] = p5BaseFixtures();

    $device = ScannerDevice::factory()->create(['is_active' => true]);

    $do = DeliveryOrder::factory()->create([
        'warehouse_id'         => $wh->id,
        'virtual_warehouse_id' => $vw->id,
        'status'               => DeliveryOrderStatus::Picked->value,
    ]);

    // Simulate the scan_device_id session key set by the scanner middleware
    session(['scan_device_id' => $device->id]);

    Livewire::actingAs($user)
        ->test(DispatchIndex::class)
        ->set('scanInput', $do->number)
        ->call('processScan');

    $event = ScanEvent::first();
    expect($event?->scanner_device_id)->toBe($device->id);
});

// ── 4. StockLedger::move records device on the transaction ────────────────────

it('StockLedger::move records device on the StockTransaction', function () {
    // Set up minimal stock data needed for a move
    $seeder = new \Database\Seeders\FoundationDefaultsSeeder();

    $uom  = \App\Models\UnitOfMeasure::firstOrCreate(
        ['code' => 'P5EA'],
        ['name' => 'Each', 'type' => 'count', 'is_active' => true]
    );
    $wh   = Warehouse::factory()->create(['is_active' => true]);
    $vw   = VirtualWarehouse::factory()->create(['warehouse_id' => $wh->id, 'is_active' => true]);
    $item = \App\Models\Item::factory()->create(['base_uom_id' => $uom->id, 'is_active' => true]);

    $fromLoc = \App\Models\Location::factory()->create(['warehouse_id' => $wh->id]);
    $toLoc   = \App\Models\Location::factory()->create(['warehouse_id' => $wh->id]);

    // Plant stock balance so the debit is legal — use the same hash formula as StockKey::hash()
    $keyHash = hash('sha256', implode('|', [$item->id, 0, 0, $fromLoc->id, $vw->id, \App\Enums\Inventory\StockStatus::Available->value]));
    \App\Models\Inventory\StockBalance::create([
        'item_id'              => $item->id,
        'location_id'          => $fromLoc->id,
        'virtual_warehouse_id' => $vw->id,
        'stock_status'         => \App\Enums\Inventory\StockStatus::Available,
        'key_hash'             => $keyHash,
        'qty_on_hand'          => '10.0000',
        'qty_allocated'        => '0.0000',
    ]);

    \App\Models\DocumentSequence::firstOrCreate(
        ['type' => 'STK'],
        ['prefix' => 'STK', 'include_year' => true, 'padding' => 4, 'reset_yearly' => true, 'next_number' => 1]
    );

    $fromKey = new \App\Data\Inventory\StockKey(
        itemId:             $item->id,
        batchId:            null,
        lpnId:              null,
        locationId:         $fromLoc->id,
        virtualWarehouseId: $vw->id,
        stockStatus:        \App\Enums\Inventory\StockStatus::Available,
    );
    $ref = new \App\Data\Inventory\StockReference(
        referenceType: 'relocation',
        referenceId:   null,
        referenceNo:   'TEST-MOVE-001',
    );

    $tx = app(\App\Services\Inventory\StockLedger::class)->move(
        from:        $fromKey,
        toLocationId: $toLoc->id,
        qty:          '5.0000',
        uomId:        $uom->id,
        ref:          $ref,
        device:       'SCN-TEST-01',
    );

    expect($tx->device)->toBe('SCN-TEST-01');
});
