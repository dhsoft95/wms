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

        // Stock Ledger (Module 2)
        'stock.view', 'stock.movements.view', 'stock.opening-balance', 'stock.reverse', 'stock.verify',

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

        // Items (SKU master — replaces old skus.*)
        'items.view',      'items.create',      'items.update',      'items.delete',

        // Partners
        'partners.view',   'partners.create',   'partners.update',   'partners.delete',

        // Locations & Warehouses
        'locations.view',  'locations.create',  'locations.update',  'locations.delete',
        'locations.block',

        // Admin
        'users.manage',
        'roles.manage',
        'settings.manage',
        'departments.manage',
        'virtual-warehouses.view',
        'virtual-warehouses.manage',

        // Scanner
        'scan.receive',
        'scan.putaway',
        'scan.pick',
        'scan.stock-lookup',

        // Serials (Module 2B)
        'serials.view',

        // Data Import (Module 2B)
        'imports.manage',
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
            'items.view', 'items.create', 'items.update',
            'partners.view', 'partners.create', 'partners.update',
            'locations.view', 'locations.create', 'locations.update', 'locations.block',
            'virtual-warehouses.view',
            // Stock Ledger (Module 2)
            'stock.view', 'stock.movements.view', 'stock.verify',
            // Module 2B
            'serials.view', 'imports.manage',
        ],

        'department-user' => [
            // Scoped to assigned virtual warehouses via BelongsToVirtualWarehouse global scope
            'inventory.view',
            'orders.view', 'orders.create', 'orders.update',
            'items.view',
            'virtual-warehouses.view',
            // Stock Ledger (Module 2)
            'stock.view', 'stock.movements.view',
            // Module 2B
            'serials.view',
        ],

        'storekeeper' => [
            // Unrestricted by VW — executes receiving, putaway and picking for every department
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
            'items.view',
            'partners.view',
            'locations.view',
            'virtual-warehouses.view',
            // Stock Ledger (Module 2)
            'stock.view', 'stock.movements.view',
            // Module 2B
            'serials.view',
        ],

        'scanner-operator' => [
            // Unrestricted by VW — scans for every department
            'inventory.view',
            'scan.receive',
            'scan.putaway',
            'scan.pick',
            'scan.stock-lookup',
            'items.view',
            'locations.view',
            'virtual-warehouses.view',
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
            'items.view',
            'partners.view',
            'locations.view',
            'virtual-warehouses.view',
            // Stock Ledger (Module 2)
            'stock.view', 'stock.movements.view',
            // Module 2B
            'serials.view',
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
