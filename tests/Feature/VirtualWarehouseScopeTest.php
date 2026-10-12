<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Scopes\VirtualWarehouseScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

// ---------------------------------------------------------------------------
// Test-only model — uses the BelongsToVirtualWarehouse trait.
// Its table is created inside the test lifecycle.
// ---------------------------------------------------------------------------
class ScopedRecord extends Model
{
    use \App\Models\Concerns\BelongsToVirtualWarehouse;

    protected $table = 'scoped_records';
    protected $fillable = ['virtual_warehouse_id', 'name'];
}

// ---------------------------------------------------------------------------

class VirtualWarehouseScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Schema::create('scoped_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('virtual_warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('scoped_records');
        parent::tearDown();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function dept(): Department
    {
        return Department::create(['name' => 'Test Dept', 'code' => 'TD', 'is_active' => true]);
    }

    private function vw(Department $dept, string $code = 'VW1'): VirtualWarehouse
    {
        return VirtualWarehouse::create([
            'name'          => "Warehouse {$code}",
            'code'          => $code,
            'department_id' => $dept->id,
            'is_active'     => true,
        ]);
    }

    private function departmentUser(VirtualWarehouse ...$vws): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('department-user');
        $user->virtualWarehouses()->sync(collect($vws)->pluck('id'));
        return $user;
    }

    private function managerUser(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('warehouse-manager');
        return $user;
    }

    private function adminUser(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('system-admin');
        return $user;
    }

    private function auditorUser(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('viewer-auditor');
        return $user;
    }

    private function storekeeperUser(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('storekeeper');
        return $user;
    }

    private function record(VirtualWarehouse $vw, string $name = 'rec'): ScopedRecord
    {
        return ScopedRecord::withoutGlobalScope(VirtualWarehouseScope::class)
            ->create(['virtual_warehouse_id' => $vw->id, 'name' => $name]);
    }

    // ─── Tests ───────────────────────────────────────────────────────────────

    public function test_department_user_sees_only_their_vw_records(): void
    {
        $dept = $this->dept();
        $vw1  = $this->vw($dept, 'VW1');
        $vw2  = $this->vw($dept, 'VW2');

        $r1 = $this->record($vw1, 'mine');
        $r2 = $this->record($vw2, 'not mine');

        $user = $this->departmentUser($vw1);
        $this->actingAs($user);
        $user->clearVwCache();

        $results = ScopedRecord::all();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($r1->id);
    }

    public function test_department_user_with_two_vws_sees_both(): void
    {
        $dept = $this->dept();
        $vw1  = $this->vw($dept, 'VW1');
        $vw2  = $this->vw($dept, 'VW2');

        $this->record($vw1, 'a');
        $this->record($vw2, 'b');

        $user = $this->departmentUser($vw1, $vw2);
        $this->actingAs($user);
        $user->clearVwCache();

        expect(ScopedRecord::count())->toBe(2);
    }

    public function test_vw_switcher_narrows_to_selected_vw(): void
    {
        $dept = $this->dept();
        $vw1  = $this->vw($dept, 'VW1');
        $vw2  = $this->vw($dept, 'VW2');

        $this->record($vw1, 'a');
        $this->record($vw2, 'b');

        $user = $this->departmentUser($vw1, $vw2);
        $this->actingAs($user);

        // Simulate session VW selection
        session(['wms_active_vw_id' => $vw1->id]);
        $user->clearVwCache();

        expect(ScopedRecord::count())->toBe(1);
        expect(ScopedRecord::first()->name)->toBe('a');
    }

    public function test_vw_switcher_cannot_widen_beyond_assigned_vws(): void
    {
        $dept  = $this->dept();
        $vw1   = $this->vw($dept, 'VW1');
        $vw2   = $this->vw($dept, 'VW2'); // user NOT assigned

        $this->record($vw1, 'assigned');
        $this->record($vw2, 'not assigned');

        $user = $this->departmentUser($vw1);
        $this->actingAs($user);

        // Attempt to select a VW the user is not assigned to
        session(['wms_active_vw_id' => $vw2->id]);
        $user->clearVwCache();

        // Falls back to assigned VWs, not the unassigned one
        $results = ScopedRecord::all();
        expect($results)->toHaveCount(1);
        expect($results->first()->name)->toBe('assigned');
    }

    public function test_user_with_zero_vws_sees_nothing(): void
    {
        $dept = $this->dept();
        $vw   = $this->vw($dept, 'VW1');

        $this->record($vw);

        $user = $this->departmentUser(); // no VWs assigned
        $this->actingAs($user);
        $user->clearVwCache();

        expect(ScopedRecord::count())->toBe(0);
    }

    public function test_system_admin_sees_all_vws(): void
    {
        $dept = $this->dept();
        $vw1  = $this->vw($dept, 'VW1');
        $vw2  = $this->vw($dept, 'VW2');

        $this->record($vw1);
        $this->record($vw2);

        $this->actingAs($this->adminUser());

        expect(ScopedRecord::count())->toBe(2);
    }

    public function test_warehouse_manager_sees_all_vws(): void
    {
        $dept = $this->dept();
        $vw1  = $this->vw($dept, 'VW1');
        $vw2  = $this->vw($dept, 'VW2');

        $this->record($vw1);
        $this->record($vw2);

        $this->actingAs($this->managerUser());

        expect(ScopedRecord::count())->toBe(2);
    }

    public function test_viewer_auditor_sees_all_vws(): void
    {
        $dept = $this->dept();
        $vw1  = $this->vw($dept, 'VW1');
        $vw2  = $this->vw($dept, 'VW2');

        $this->record($vw1);
        $this->record($vw2);

        $this->actingAs($this->auditorUser());

        expect(ScopedRecord::count())->toBe(2);
    }

    public function test_storekeeper_sees_all_vws(): void
    {
        $dept = $this->dept();
        $vw1  = $this->vw($dept, 'VW1');
        $vw2  = $this->vw($dept, 'VW2');

        $this->record($vw1);
        $this->record($vw2);

        $this->actingAs($this->storekeeperUser());

        expect(ScopedRecord::count())->toBe(2);
    }

    public function test_creating_record_with_unassigned_vw_is_rejected(): void
    {
        $dept = $this->dept();
        $vw1  = $this->vw($dept, 'VW1');
        $vw2  = $this->vw($dept, 'VW2'); // not assigned

        $user = $this->departmentUser($vw1);
        $this->actingAs($user);
        $user->clearVwCache();

        $this->expectException(AuthorizationException::class);

        ScopedRecord::create(['virtual_warehouse_id' => $vw2->id, 'name' => 'bad']);
    }

    public function test_creating_record_auto_fills_vw_when_user_has_exactly_one(): void
    {
        $dept = $this->dept();
        $vw   = $this->vw($dept, 'VW1');

        $user = $this->departmentUser($vw);
        $this->actingAs($user);
        $user->clearVwCache();

        $record = ScopedRecord::create(['name' => 'auto-filled']);

        expect($record->virtual_warehouse_id)->toBe($vw->id);
    }

    public function test_scope_skipped_when_unauthenticated(): void
    {
        $dept = $this->dept();
        $vw   = $this->vw($dept, 'VW1');

        $this->record($vw);

        // No actingAs — scope should be skipped (no auth user)
        expect(ScopedRecord::count())->toBe(1);
    }
}
