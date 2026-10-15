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
        // Inbound — Containers
        'containers.view', 'containers.create', 'containers.update',
        'containers.arrive', 'containers.park', 'containers.unload',
        'containers.release', 'containers.cancel',
        'containers.documents', 'containers.documents.delete',
        'containers.discrepancies', 'containers.discrepancies.resolve',
        'containers.override-number',

        // Inbound — GRN (replaces old receiving.*)
        'grn.view', 'grn.create', 'grn.receive', 'grn.confirm',
        'grn.variance.approve', 'grn.cancel', 'grn.close',

        // LPN
        'lpn.print',

        // Inspections
        'inspections.view', 'inspections.create', 'inspections.approve',

        // Warehouse
        'putaway.view', 'putaway.execute', 'putaway.assign',
        'movements.view',  'movements.create',  'movements.approve',
        'transfers.view',  'transfers.create',  'transfers.approve',

        // Stock Ledger (Module 2)
        'stock.view', 'stock.movements.view', 'stock.opening-balance', 'stock.reverse', 'stock.verify',

        // Inventory
        'inventory.view',  'inventory.create',  'inventory.update',  'inventory.delete',
        'adjustments.view','adjustments.create','adjustments.approve',
        'cycle-counts.view','cycle-counts.execute','cycle-counts.approve',

        // Outbound — Delivery Orders
        'orders.view', 'orders.create', 'orders.update',
        'orders.submit', 'orders.approve.department', 'orders.approve.warehouse',
        'orders.allocate', 'orders.cancel',
        // Outbound — Picking
        'picking.view', 'picking.assign', 'picking.execute',
        // Outbound — Dispatch & Gate
        'dispatch.view', 'dispatch.execute',
        'gate.verify',
        // Outbound — POD & Returns
        'pod.upload',
        'returns.create',
        // Outbound — Scanner
        'scan.dispatch', 'scan.gate',

        // Approvals
        'approvals.view',  'approvals.approve',

        // Reports / Traceability
        'reports.view',    'reports.export',
        'traceability.view',
        'audit-logs.view',

        // Items (SKU master)
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

        // Labels & Printing (Module 5)
        'labels.templates.manage',
        'labels.print',
        'labels.reprint',
        'labels.log.view',
        'printers.manage',
        'scanner-devices.manage',
        'scan.move',
        'scan.lookup',
    ];

    // Role → permission mapping
    private const ROLE_PERMISSIONS = [
        'system-admin' => [], // bypasses via Gate::before — seeded empty intentionally

        'warehouse-manager' => [
            'containers.view', 'containers.create', 'containers.update',
            'containers.arrive', 'containers.park', 'containers.unload',
            'containers.release', 'containers.cancel',
            'containers.documents', 'containers.documents.delete',
            'containers.discrepancies', 'containers.discrepancies.resolve',
            'containers.override-number',
            'grn.view', 'grn.create', 'grn.receive', 'grn.confirm',
            'grn.variance.approve', 'grn.cancel', 'grn.close',
            'lpn.print',
            'inspections.view', 'inspections.create', 'inspections.approve',
            'putaway.view', 'putaway.execute', 'putaway.assign',
            'movements.view', 'movements.create', 'movements.approve',
            'transfers.view', 'transfers.create', 'transfers.approve',
            'inventory.view', 'inventory.create', 'inventory.update', 'inventory.delete',
            'adjustments.view', 'adjustments.create', 'adjustments.approve',
            'cycle-counts.view', 'cycle-counts.execute', 'cycle-counts.approve',
            'orders.view', 'orders.create', 'orders.update',
            'orders.submit', 'orders.approve.department', 'orders.approve.warehouse',
            'orders.allocate', 'orders.cancel',
            'picking.view', 'picking.assign', 'picking.execute',
            'dispatch.view', 'dispatch.execute',
            'gate.verify', 'pod.upload', 'returns.create',
            'scan.dispatch', 'scan.gate',
            'approvals.view', 'approvals.approve',
            'reports.view', 'reports.export',
            'traceability.view',
            'items.view', 'items.create', 'items.update',
            'partners.view', 'partners.create', 'partners.update',
            'locations.view', 'locations.create', 'locations.update', 'locations.block',
            'virtual-warehouses.view',
            'stock.view', 'stock.movements.view', 'stock.verify',
            'serials.view', 'imports.manage',
            'labels.templates.manage', 'labels.print', 'labels.reprint', 'labels.log.view',
            'printers.manage', 'scanner-devices.manage', 'scan.move', 'scan.lookup',
        ],

        'department-user' => [
            'inventory.view',
            'orders.view', 'orders.create', 'orders.update', 'orders.submit',
            'orders.approve.department',
            'items.view',
            'virtual-warehouses.view',
            'stock.view', 'stock.movements.view',
            'serials.view',
        ],

        'storekeeper' => [
            'containers.view', 'containers.arrive', 'containers.park', 'containers.unload',
            'containers.documents', 'containers.discrepancies',
            'grn.view', 'grn.create', 'grn.receive', 'grn.confirm',
            'grn.cancel',
            'lpn.print',
            'inspections.view', 'inspections.create',
            'putaway.view', 'putaway.execute', 'putaway.assign',
            'movements.view', 'movements.create',
            'inventory.view',
            'adjustments.view', 'adjustments.create',
            'cycle-counts.view', 'cycle-counts.execute',
            'orders.view', 'orders.allocate',
            'picking.view', 'picking.assign', 'picking.execute',
            'dispatch.view', 'dispatch.execute',
            'pod.upload', 'returns.create',
            'scan.pick', 'scan.dispatch',
            'items.view',
            'partners.view',
            'locations.view',
            'virtual-warehouses.view',
            'stock.view', 'stock.movements.view',
            'serials.view',
            'labels.print', 'labels.log.view', 'scan.move', 'scan.lookup',
        ],

        'scanner-operator' => [
            'inventory.view',
            'scan.receive',
            'scan.putaway',
            'scan.pick',
            'scan.dispatch',
            'scan.stock-lookup',
            'items.view',
            'locations.view',
            'virtual-warehouses.view',
            'labels.print', 'scan.move', 'scan.lookup',
        ],

        'gate-security' => [
            'gate.verify',
            'scan.gate',
        ],

        'viewer-auditor' => [
            'containers.view',
            'grn.view',
            'inspections.view',
            'putaway.view',
            'movements.view',
            'transfers.view',
            'inventory.view',
            'adjustments.view',
            'cycle-counts.view',
            'orders.view',
            'picking.view',
            'dispatch.view',
            'approvals.view',
            'reports.view', 'reports.export',
            'traceability.view',
            'audit-logs.view',
            'items.view',
            'partners.view',
            'locations.view',
            'virtual-warehouses.view',
            'stock.view', 'stock.movements.view',
            'serials.view',
            'labels.log.view',
        ],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Remove old receiving.* permissions
        $oldPerms = ['receiving.view', 'receiving.create', 'receiving.update', 'receiving.execute', 'receiving.approve'];
        foreach ($oldPerms as $name) {
            Permission::where('name', $name)->where('guard_name', 'web')->delete();
        }

        // Create all new permissions
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        foreach (self::ROLE_PERMISSIONS as $roleName => $permNames) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permNames);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Delete legacy permissions
        Permission::where('name', 'containers.delete')->where('guard_name', 'web')->delete();
        foreach (['orders.approve', 'pod.view', 'pod.create'] as $old) {
            Permission::where('name', $old)->where('guard_name', 'web')->delete();
        }
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
