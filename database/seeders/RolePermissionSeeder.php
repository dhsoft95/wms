<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    // Full permission list — module.action
    private const PERMISSIONS = [
        // Inbound
        'containers.view', 'containers.create', 'containers.update', 'containers.delete',
        'receiving.view',  'receiving.create',  'receiving.update',  'receiving.execute', 'receiving.approve',
        'inspections.view','inspections.create','inspections.approve',

        // Warehouse
        'putaway.view',    'putaway.execute',
        'movements.view',  'movements.create',  'movements.approve',
        'transfers.view',  'transfers.create',  'transfers.approve',

        // Inventory
        'inventory.view',  'inventory.create',  'inventory.update',  'inventory.delete',
        'adjustments.view','adjustments.create','adjustments.approve',
        'cycle-counts.view','cycle-counts.execute','cycle-counts.approve',

        // Outbound
        'orders.view',     'orders.create',     'orders.update',     'orders.approve',
        'dispatch.view',   'dispatch.execute',
        'pod.view',        'pod.create',

        // Approvals
        'approvals.view',  'approvals.approve',

        // Reports / Traceability
        'reports.view',    'reports.export',
        'traceability.view',
        'audit-logs.view',

        // SKU master
        'skus.view',       'skus.create',       'skus.update',       'skus.delete',

        // Admin
        'users.manage',
        'roles.manage',
        'settings.manage',
        'departments.manage',

        // Scanner
        'scan.receive',
        'scan.putaway',
        'scan.pick',
        'scan.stock-lookup',
    ];

    // Role → permission mapping
    private const ROLE_PERMISSIONS = [
        'system-admin' => [], // bypasses via Gate::before — seeded empty intentionally

        'warehouse-manager' => [
            'containers.view', 'containers.create', 'containers.update', 'containers.delete',
            'receiving.view', 'receiving.create', 'receiving.update', 'receiving.execute', 'receiving.approve',
            'inspections.view', 'inspections.create', 'inspections.approve',
            'putaway.view', 'putaway.execute',
            'movements.view', 'movements.create', 'movements.approve',
            'transfers.view', 'transfers.create', 'transfers.approve',
            'inventory.view', 'inventory.create', 'inventory.update', 'inventory.delete',
            'adjustments.view', 'adjustments.create', 'adjustments.approve',
            'cycle-counts.view', 'cycle-counts.execute', 'cycle-counts.approve',
            'orders.view', 'orders.create', 'orders.update', 'orders.approve',
            'dispatch.view', 'dispatch.execute',
            'pod.view', 'pod.create',
            'approvals.view', 'approvals.approve',
            'reports.view', 'reports.export',
            'traceability.view',
            'skus.view', 'skus.create', 'skus.update',
        ],

        'department-user' => [
            // Filtered to own department_id via DepartmentScope at model level
            'containers.view',
            'receiving.view',
            'movements.view', 'movements.create',
            'transfers.view', 'transfers.create',
            'inventory.view',
            'orders.view', 'orders.create', 'orders.update',
        ],

        'storekeeper' => [
            'containers.view',
            'receiving.view', 'receiving.create', 'receiving.update', 'receiving.execute',
            'inspections.view', 'inspections.create',
            'putaway.view', 'putaway.execute',
            'movements.view', 'movements.create',
            'inventory.view',
            'adjustments.view', 'adjustments.create',
            'cycle-counts.view', 'cycle-counts.execute',
            'orders.view',
            'dispatch.view', 'dispatch.execute',
            'pod.view', 'pod.create',
            'skus.view',
        ],

        'scanner-operator' => [
            'inventory.view',
            'scan.receive',
            'scan.putaway',
            'scan.pick',
            'scan.stock-lookup',
        ],

        'viewer-auditor' => [
            'containers.view',
            'receiving.view',
            'inspections.view',
            'putaway.view',
            'movements.view',
            'transfers.view',
            'inventory.view',
            'adjustments.view',
            'cycle-counts.view',
            'orders.view',
            'dispatch.view',
            'pod.view',
            'approvals.view',
            'reports.view', 'reports.export',
            'traceability.view',
            'audit-logs.view',
            'skus.view',
        ],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create all permissions
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        foreach (self::ROLE_PERMISSIONS as $roleName => $permNames) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permNames);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
