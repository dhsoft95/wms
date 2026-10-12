<?php

namespace Tests\Feature\Foundation;

use App\Actions\Foundation\BlockLocationAction;
use App\Actions\Foundation\UnblockLocationAction;
use App\Enums\LocationStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Location;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockUnblockLocationTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->warehouse = Warehouse::create([
            'code'      => 'TST',
            'name'      => 'Test Warehouse',
            'is_active' => true,
        ]);

        $this->manager = User::factory()->create(['status' => 'active']);
        $this->manager->assignRole('warehouse-manager');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeLocation(string $status = 'active'): Location
    {
        return Location::create([
            'warehouse_id' => $this->warehouse->id,
            'segment_code' => 'A',
            'code'         => 'TST-A',
            'level'        => 'zone',
            'type'         => 'storage',
            'status'       => $status,
            'path'         => '/',
            'is_pickable'  => true,
        ]);
    }

    // ─── Block ────────────────────────────────────────────────────────────────

    public function test_block_active_location_sets_all_fields(): void
    {
        $location = $this->makeLocation('active');

        BlockLocationAction::execute($location, 'Damaged rack', $this->manager->id);

        $location->refresh();

        expect($location->status)->toBe(LocationStatus::Blocked)
            ->and($location->blocked_reason)->toBe('Damaged rack')
            ->and($location->blocked_by)->toBe($this->manager->id)
            ->and($location->blocked_at)->not->toBeNull();
    }

    public function test_block_records_activity_log(): void
    {
        $location = $this->makeLocation('active');

        BlockLocationAction::execute($location, 'Flooding', $this->manager->id);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Location::class,
            'subject_id'   => $location->id,
            'event'        => 'updated',
        ]);
    }

    public function test_block_blocked_location_throws_invalid_transition(): void
    {
        $location = $this->makeLocation('blocked');

        $this->expectException(InvalidTransitionException::class);

        BlockLocationAction::execute($location, 'Reason', $this->manager->id);
    }

    public function test_block_full_location_throws_invalid_transition(): void
    {
        $location = $this->makeLocation('full');

        $this->expectException(InvalidTransitionException::class);

        BlockLocationAction::execute($location, 'Reason', $this->manager->id);
    }

    // ─── Unblock ──────────────────────────────────────────────────────────────

    public function test_unblock_blocked_location_clears_all_fields(): void
    {
        $location = $this->makeLocation('blocked');
        $location->update([
            'blocked_reason' => 'Flooding',
            'blocked_by'     => $this->manager->id,
            'blocked_at'     => now(),
        ]);

        UnblockLocationAction::execute($location);

        $location->refresh();

        expect($location->status)->toBe(LocationStatus::Active)
            ->and($location->blocked_reason)->toBeNull()
            ->and($location->blocked_by)->toBeNull()
            ->and($location->blocked_at)->toBeNull();
    }

    public function test_unblock_records_activity_log(): void
    {
        $location = $this->makeLocation('blocked');
        $location->update(['blocked_reason' => 'Test', 'blocked_by' => $this->manager->id, 'blocked_at' => now()]);

        UnblockLocationAction::execute($location);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Location::class,
            'subject_id'   => $location->id,
            'event'        => 'updated',
        ]);
    }

    public function test_unblock_active_location_throws_invalid_transition(): void
    {
        $location = $this->makeLocation('active');

        $this->expectException(InvalidTransitionException::class);

        UnblockLocationAction::execute($location);
    }
}
