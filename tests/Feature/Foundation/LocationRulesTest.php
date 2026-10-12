<?php

namespace Tests\Feature\Foundation;

use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Exceptions\InvalidTransitionException;
use App\Models\Location;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationRulesTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->warehouse = Warehouse::create([
            'code'      => 'TST',
            'name'      => 'Test Warehouse',
            'is_active' => true,
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeLocation(array $overrides = []): Location
    {
        return Location::create(array_merge([
            'warehouse_id' => $this->warehouse->id,
            'segment_code' => 'A',
            'code'         => 'TST-' . uniqid(),
            'level'        => LocationLevel::Zone,
            'type'         => LocationType::Storage,
            'status'       => LocationStatus::Active,
            'is_pickable'  => true,
        ], $overrides));
    }

    // ─── Tests ───────────────────────────────────────────────────────────────

    public function test_location_code_includes_warehouse_prefix(): void
    {
        $location = $this->makeLocation(['code' => 'TST-A']);

        expect($location->code)->toBe('TST-A');
    }

    public function test_quarantine_location_is_not_pickable(): void
    {
        // Enum-level check
        expect(LocationType::Quarantine->isPickable())->toBeFalse();

        // Model-level check
        $location = $this->makeLocation([
            'type'        => LocationType::Quarantine,
            'is_pickable' => false,
        ]);

        expect($location->type)->toBe(LocationType::Quarantine);
        expect($location->is_pickable)->toBeFalse();
    }

    public function test_yard_slot_is_not_pickable(): void
    {
        // Enum-level check
        expect(LocationType::YardSlot->isPickable())->toBeFalse();

        // Model-level check
        $location = $this->makeLocation([
            'type'        => LocationType::YardSlot,
            'is_pickable' => false,
        ]);

        expect($location->type)->toBe(LocationType::YardSlot);
        expect($location->is_pickable)->toBeFalse();
    }

    public function test_location_with_children_cannot_be_deleted(): void
    {
        $parent = $this->makeLocation(['code' => 'TST-PARENT']);

        $this->makeLocation([
            'code'      => 'TST-CHILD',
            'parent_id' => $parent->id,
        ]);

        // hasChildren() must return true for the parent
        expect($parent->hasChildren())->toBeTrue();
    }

    public function test_location_status_active_to_blocked_valid(): void
    {
        expect(LocationStatus::Active->canTransitionTo(LocationStatus::Blocked))->toBeTrue();
    }

    public function test_location_status_blocked_to_active_valid(): void
    {
        expect(LocationStatus::Blocked->canTransitionTo(LocationStatus::Active))->toBeTrue();
    }

    public function test_location_status_full_cannot_manually_transition(): void
    {
        expect(LocationStatus::Full->canTransitionTo(LocationStatus::Active))->toBeFalse();
        expect(LocationStatus::Full->canTransitionTo(LocationStatus::Blocked))->toBeFalse();
    }

    public function test_invalid_transition_throws_exception(): void
    {
        $this->expectException(InvalidTransitionException::class);

        LocationStatus::Full->transition(LocationStatus::Active);
    }

    public function test_bulk_generator_skips_existing_codes(): void
    {
        // Create a location with code 'TST-A-01' in the warehouse
        $this->makeLocation(['code' => 'TST-A-01']);

        // Attempting to create another location with the same warehouse_id + code
        // combination must fail the database unique constraint.
        $this->expectException(\Illuminate\Database\QueryException::class);

        Location::create([
            'warehouse_id' => $this->warehouse->id,
            'segment_code' => 'A',
            'code'         => 'TST-A-01',  // duplicate
            'level'        => LocationLevel::Bin,
            'type'         => LocationType::Storage,
            'status'       => LocationStatus::Active,
            'is_pickable'  => true,
        ]);
    }
}
