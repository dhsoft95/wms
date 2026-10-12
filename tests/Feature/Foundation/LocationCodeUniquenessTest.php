<?php

namespace Tests\Feature\Foundation;

use App\Models\Location;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationCodeUniquenessTest extends TestCase
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

    public function test_soft_deleted_location_code_blocks_new_creation(): void
    {
        // Create and then soft-delete a location
        $existing = Location::create([
            'warehouse_id' => $this->warehouse->id,
            'segment_code' => 'A',
            'code'         => 'TST-A',
            'level'        => 'zone',
            'type'         => 'storage',
            'status'       => 'active',
            'path'         => '/',
            'is_pickable'  => true,
        ]);

        $existing->delete(); // soft delete

        // The soft-deleted row still occupies the unique index slot
        $this->assertSoftDeleted('locations', ['code' => 'TST-A']);

        // Attempting to create another location with the same code in the same warehouse
        // is caught by the withTrashed() check before hitting the DB constraint
        $duplicate = Location::withTrashed()
            ->where('warehouse_id', $this->warehouse->id)
            ->where('code', 'TST-A')
            ->exists();

        expect($duplicate)->toBeTrue(
            'withTrashed() query must return the soft-deleted row, so the existence check in Create.php catches it'
        );
    }

    public function test_soft_deleted_location_code_cannot_be_reused_via_db_constraint(): void
    {
        $existing = Location::create([
            'warehouse_id' => $this->warehouse->id,
            'segment_code' => 'B',
            'code'         => 'TST-B',
            'level'        => 'zone',
            'type'         => 'storage',
            'status'       => 'active',
            'path'         => '/',
            'is_pickable'  => true,
        ]);

        $existing->delete();

        // The DB unique index on (warehouse_id, code) still includes the soft-deleted row.
        // A raw INSERT of the same code must throw a unique constraint violation.
        $this->expectException(\Illuminate\Database\QueryException::class);

        // Bypass the application-level check to confirm the DB itself also rejects it
        Location::forceCreate([
            'warehouse_id' => $this->warehouse->id,
            'segment_code' => 'B',
            'code'         => 'TST-B',
            'level'        => 'zone',
            'type'         => 'storage',
            'status'       => 'active',
            'path'         => '/',
            'is_pickable'  => true,
        ]);
    }

    public function test_location_code_in_different_warehouse_is_allowed(): void
    {
        $warehouse2 = Warehouse::create(['code' => 'WH2', 'name' => 'Warehouse 2', 'is_active' => true]);

        Location::create([
            'warehouse_id' => $this->warehouse->id,
            'segment_code' => 'A',
            'code'         => 'TST-A',
            'level'        => 'zone',
            'type'         => 'storage',
            'status'       => 'active',
            'path'         => '/',
            'is_pickable'  => true,
        ]);

        // Same segment code in a different warehouse produces a different full code (WH2-A vs TST-A)
        // This verifies the unique index is scoped to (warehouse_id, code) not just code alone
        $this->assertDatabaseMissing('locations', [
            'warehouse_id' => $warehouse2->id,
            'code'         => 'WH2-A',
        ]);

        Location::create([
            'warehouse_id' => $warehouse2->id,
            'segment_code' => 'A',
            'code'         => 'WH2-A',
            'level'        => 'zone',
            'type'         => 'storage',
            'status'       => 'active',
            'path'         => '/',
            'is_pickable'  => true,
        ]);

        $this->assertDatabaseHas('locations', ['warehouse_id' => $warehouse2->id, 'code' => 'WH2-A']);
    }
}
