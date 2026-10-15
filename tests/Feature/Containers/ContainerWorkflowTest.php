<?php

use App\Enums\ContainerStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Container;
use App\Models\ContainerExpectedLine;
use App\Models\ContainerStatusHistory;
use App\Models\Location;
use App\Models\Partner;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Services\Foundation\ISO6346Validator;
use App\Services\Inbound\ContainerWorkflow;

// ── Group: ISO 6346 ─────────────────────────────────────────────────────────

test('ISO6346 valid numbers pass', function () {
    expect(ISO6346Validator::isValid('MSCU4134998'))->toBeTrue();
});

test('ISO6346 invalid check digit fails', function () {
    expect(ISO6346Validator::isValid('MSCU4134990'))->toBeFalse();
});

test('ISO6346 normalize strips spaces', function () {
    expect(ISO6346Validator::normalize('MSCU 413499 7'))->toBe('MSCU4134997');
});

// ── Group: status transitions ────────────────────────────────────────────────

test('allowed transitions succeed', function (ContainerStatus $from, ContainerStatus $to) {
    expect($from->canTransitionTo($to))->toBeTrue();
})->with([
    [ContainerStatus::Expected,  ContainerStatus::Arrived],
    [ContainerStatus::Expected,  ContainerStatus::Cancelled],
    [ContainerStatus::Arrived,   ContainerStatus::Parked],
    [ContainerStatus::Arrived,   ContainerStatus::Unloading],
    [ContainerStatus::Parked,    ContainerStatus::Unloading],
    [ContainerStatus::Unloading, ContainerStatus::Unloaded],
    [ContainerStatus::Unloaded,  ContainerStatus::Released],
]);

test('forbidden transitions fail', function (ContainerStatus $from, ContainerStatus $to) {
    expect($from->canTransitionTo($to))->toBeFalse();
})->with([
    [ContainerStatus::Expected,  ContainerStatus::Unloading],
    [ContainerStatus::Arrived,   ContainerStatus::Released],
    [ContainerStatus::Released,  ContainerStatus::Expected],
    [ContainerStatus::Cancelled, ContainerStatus::Arrived],
    [ContainerStatus::Parked,    ContainerStatus::Arrived],
]);

// ── Group: workflow (uses RefreshDatabase) ───────────────────────────────────

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(fn () => (new \Database\Seeders\RolePermissionSeeder())->run());

test('confirmArrival transitions to arrived and writes history', function () {
    $user      = User::factory()->create();
    $container = Container::factory()->create(['status' => ContainerStatus::Expected]);
    $workflow  = app(ContainerWorkflow::class);

    $workflow->confirmArrival($container, now(), 'SEAL-001', $user);

    expect($container->fresh()->status)->toBe(ContainerStatus::Arrived);
    expect(
        ContainerStatusHistory::where('container_id', $container->id)
            ->where('to_status', ContainerStatus::Arrived->value)
            ->exists()
    )->toBeTrue();
});

test('seal mismatch creates discrepancy', function () {
    $user      = User::factory()->create();
    $container = Container::factory()->create([
        'status'  => ContainerStatus::Expected,
        'seal_no' => 'ORIGINAL-SEAL',
    ]);
    $workflow = app(ContainerWorkflow::class);

    $workflow->confirmArrival($container, now(), 'DIFFERENT-SEAL', $user);

    expect(
        $container->fresh()->discrepancies()->where('type', 'seal_mismatch')->exists()
    )->toBeTrue();
});

test('park requires yard_slot type location', function () {
    $user      = User::factory()->create();
    $container = Container::factory()->create(['status' => ContainerStatus::Arrived]);
    $location  = Location::factory()->create(['type' => 'storage', 'status' => 'active']);
    $workflow  = app(ContainerWorkflow::class);

    expect(fn() => $workflow->park($container, $location, $user))
        ->toThrow(\InvalidArgumentException::class);
});

test('park succeeds on free yard slot', function () {
    $user      = User::factory()->create();
    $container = Container::factory()->create(['status' => ContainerStatus::Arrived]);
    $location  = Location::factory()->yardSlot()->create();
    $workflow  = app(ContainerWorkflow::class);

    $workflow->park($container, $location, $user);

    expect($container->fresh()->status)->toBe(ContainerStatus::Parked);
    expect($container->fresh()->yard_location_id)->toBe($location->id);
});

test('park fails when slot already occupied', function () {
    $user      = User::factory()->create();
    $location  = Location::factory()->yardSlot()->create();
    Container::factory()->create([
        'status'           => ContainerStatus::Parked,
        'yard_location_id' => $location->id,
    ]);
    $container = Container::factory()->create(['status' => ContainerStatus::Arrived]);
    $workflow  = app(ContainerWorkflow::class);

    expect(fn() => $workflow->park($container, $location, $user))
        ->toThrow(\RuntimeException::class);
});

test('start unloading fails with no lines', function () {
    $user      = User::factory()->create();
    $container = Container::factory()->create(['status' => ContainerStatus::Arrived]);
    $workflow  = app(ContainerWorkflow::class);

    expect(fn() => $workflow->startUnloading($container, $user))
        ->toThrow(\RuntimeException::class);
});

test('markUnloaded blocked by default gate', function () {
    $user      = User::factory()->create();
    $container = Container::factory()->create(['status' => ContainerStatus::Unloading]);
    $workflow  = app(ContainerWorkflow::class);

    expect(fn() => $workflow->markUnloaded($container, $user))
        ->toThrow(\RuntimeException::class);
});

test('release frees yard slot', function () {
    $user      = User::factory()->create();
    $location  = Location::factory()->yardSlot()->create();
    $partner   = Partner::factory()->create();
    $container = Container::factory()->create([
        'status'           => ContainerStatus::Unloaded,
        'yard_location_id' => $location->id,
    ]);
    $workflow = app(ContainerWorkflow::class);

    $workflow->release($container, now(), $partner->id, null, $user);

    expect($container->fresh()->status)->toBe(ContainerStatus::Released);
    expect($container->fresh()->yard_location_id)->toBeNull();
});

test('cancel only allowed from expected', function () {
    $user      = User::factory()->create();
    $container = Container::factory()->create(['status' => ContainerStatus::Arrived]);
    $workflow  = app(ContainerWorkflow::class);

    expect(fn() => $workflow->cancel($container, 'reason here please', $user))
        ->toThrow(InvalidTransitionException::class);
});

test('active container_no uniqueness: same container_no allowed after release', function () {
    $container1 = Container::factory()->create([
        'container_no' => 'MSCU4134997',
        'status'       => ContainerStatus::Released,
    ]);
    // Should be able to create another with same number (released is not active)
    $container2 = Container::factory()->create([
        'container_no' => 'MSCU4134997',
        'status'       => ContainerStatus::Expected,
    ]);
    expect($container2)->toBeInstanceOf(Container::class);
});

test('VW scoping: department user only sees containers with their lines', function () {
    $vw   = VirtualWarehouse::factory()->create();
    $user = User::factory()->create();
    $user->virtualWarehouses()->attach($vw->id);
    $user->assignRole('department-user');

    $visibleContainer = Container::factory()->create();
    ContainerExpectedLine::factory()->create([
        'container_id'         => $visibleContainer->id,
        'virtual_warehouse_id' => $vw->id,
    ]);

    $hiddenContainer = Container::factory()->create(); // no lines for this VW

    $queryService = app(\App\Services\Inbound\ContainerQueryService::class);
    $ids = $queryService->queryFor($user)->pluck('id');

    expect($ids)->toContain($visibleContainer->id);
    expect($ids)->not->toContain($hiddenContainer->id);
});
