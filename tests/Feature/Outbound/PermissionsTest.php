<?php

declare(strict_types=1);

use App\Enums\Outbound\AllocationRule;
use App\Enums\Outbound\DeliveryOrderPriority;
use App\Enums\Outbound\DeliveryOrderStatus;
use App\Models\DeliveryOrder;
use App\Models\Department;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Policies\DeliveryOrderPolicy;
use Database\Seeders\FoundationDefaultsSeeder;
use Database\Seeders\RolePermissionSeeder;

// ─── setUp ────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(FoundationDefaultsSeeder::class);

    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

function permWarehouse(): Warehouse
{
    return Warehouse::firstOrCreate(
        ['code' => 'PERM-WH'],
        ['name' => 'Permission Test Warehouse', 'is_active' => true]
    );
}

function permDept(): Department
{
    return Department::firstOrCreate(
        ['code' => 'PERM-DEPT'],
        ['name' => 'Permission Dept', 'is_active' => true]
    );
}

function permVw(Warehouse $wh, Department $dept): VirtualWarehouse
{
    return VirtualWarehouse::firstOrCreate(
        ['code' => 'PERM-VW'],
        [
            'name'          => 'Permission VW',
            'department_id' => $dept->id,
            'is_active'     => true,
        ]
    );
}

function makePermDo(Warehouse $wh, VirtualWarehouse $vw, User $requestedBy, DeliveryOrderStatus $status = DeliveryOrderStatus::Draft): DeliveryOrder
{
    return DeliveryOrder::create([
        'number'               => 'DO-PERM-' . uniqid(),
        'warehouse_id'         => $wh->id,
        'virtual_warehouse_id' => $vw->id,
        'department_id'        => $vw->department_id,
        'status'               => $status,
        'priority'             => DeliveryOrderPriority::Normal,
        'allocation_rule'      => AllocationRule::Default,
        'requested_by'         => $requestedBy->id,
        'required_date'        => now()->addDays(3)->toDateString(),
    ]);
}

function makeUser(string $role): User
{
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole($role);
    return $user;
}

// ─── Test 1: Order owner (department-user with orders.submit) can submit ──────

test('test_order_owner_can_submit', function () {
    $wh   = permWarehouse();
    $dept = permDept();
    $vw   = permVw($wh, $dept);

    $owner = makeUser('department-user');
    $do    = makePermDo($wh, $vw, $owner, DeliveryOrderStatus::Draft);

    $policy = new DeliveryOrderPolicy();

    // department-user has orders.submit per RolePermissionSeeder
    expect($policy->submit($owner, $do))->toBeTrue();
});

// ─── Test 2: Non-owner without orders.submit cannot submit ────────────────────

test('test_non_owner_cannot_submit', function () {
    $wh     = permWarehouse();
    $dept   = permDept();
    $vw     = permVw($wh, $dept);
    $owner  = makeUser('department-user');
    $do     = makePermDo($wh, $vw, $owner);

    // scanner-operator does not have orders.submit
    $other = makeUser('scanner-operator');

    $policy = new DeliveryOrderPolicy();

    expect($policy->submit($other, $do))->toBeFalse();
});

// ─── Test 3: Department manager can approve at department level ───────────────

test('test_department_manager_can_approve_department_level', function () {
    $wh   = permWarehouse();
    $dept = permDept();
    $vw   = permVw($wh, $dept);

    $manager = makeUser('department-user');
    // Assign the manager as department head
    $dept->update(['head_user_id' => $manager->id]);
    $dept->refresh();

    $requestor = makeUser('department-user');
    $do = makePermDo($wh, $vw, $requestor, DeliveryOrderStatus::Submitted);
    $do->update(['department_id' => $dept->id]);
    $do->load('department');

    $policy = new DeliveryOrderPolicy();

    // orders.approve.department is granted to department-user
    expect($policy->approveDepartment($manager, $do))->toBeTrue();
});

// ─── Test 4: Warehouse manager can approve at warehouse level ─────────────────

test('test_warehouse_manager_can_approve_warehouse_level', function () {
    $wh   = permWarehouse();
    $dept = permDept();
    $vw   = permVw($wh, $dept);

    $wManager   = makeUser('warehouse-manager');
    $requestor  = makeUser('department-user');
    $do         = makePermDo($wh, $vw, $requestor, DeliveryOrderStatus::Submitted);

    $policy = new DeliveryOrderPolicy();

    // orders.approve.warehouse is granted to warehouse-manager
    expect($policy->approveWarehouse($wManager, $do))->toBeTrue();
});

// ─── Test 5: System admin can do everything ───────────────────────────────────

test('test_system_admin_can_do_everything', function () {
    $wh        = permWarehouse();
    $dept      = permDept();
    $vw        = permVw($wh, $dept);
    $admin     = makeUser('system-admin');
    $do        = makePermDo($wh, $vw, $admin, DeliveryOrderStatus::Draft);

    // Gate::before bypass: system-admin returns true for any ability
    $this->actingAs($admin);

    expect($admin->can('orders.view'))->toBeTrue()
        ->and($admin->can('orders.create'))->toBeTrue()
        ->and($admin->can('orders.submit'))->toBeTrue()
        ->and($admin->can('orders.approve.department'))->toBeTrue()
        ->and($admin->can('orders.approve.warehouse'))->toBeTrue()
        ->and($admin->can('orders.allocate'))->toBeTrue()
        ->and($admin->can('orders.cancel'))->toBeTrue()
        ->and($admin->can('dispatch.execute'))->toBeTrue()
        ->and($admin->can('gate.verify'))->toBeTrue();
});
