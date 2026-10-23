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
        'movements.view',  'movements.create',  'movements.approve',  'movements.execute',
        'transfers.view',  'transfers.create',  'transfers.approve',
        'transfers.accept', 'transfers.execute',

        // Stock Ledger (Module 2)
        'stock.view', 'stock.movements.view', 'stock.opening-balance', 'stock.reverse', 'stock.verify',

        // Inventory
        'inventory.view',  'inventory.create',  'inventory.update',  'inventory.delete',
        'adjustments.view','adjustments.create','adjustments.approve',
        'cycle-counts.view','cycle-counts.execute','cycle-counts.approve',
        'counts.view','counts.create','counts.count','counts.approve',
        'quarantine.manage',

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
        'reports.view',    'reports.export',    'reports.ai_summary',
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

        // Purchase Orders (Module 10 — Inbound Workflow)
        'po.view', 'po.create', 'po.approve', 'po.accept', 'po.assign',
        'po.receive', 'po.validate', 'po.verify',
        'po.excess.approve', 'po.close.short',
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
            'movements.view', 'movements.create', 'movements.approve', 'movements.execute',
            'transfers.view', 'transfers.create', 'transfers.accept', 'transfers.execute',
            'inventory.view', 'inventory.create', 'inventory.update', 'inventory.delete',
            'adjustments.view', 'adjustments.create', 'adjustments.approve',
            'cycle-counts.view', 'cycle-counts.execute', 'cycle-counts.approve',
            'counts.view', 'counts.create', 'counts.count', 'counts.approve',
            'quarantine.manage',
            'orders.view', 'orders.create', 'orders.update',
            'orders.submit', 'orders.approve.department', 'orders.approve.warehouse',
            'orders.allocate', 'orders.cancel',
            'picking.view', 'picking.assign', 'picking.execute',
            'dispatch.view', 'dispatch.execute',
            'gate.verify', 'pod.upload', 'returns.create',
            'scan.dispatch', 'scan.gate',
            'approvals.view', 'approvals.approve',
            'reports.view', 'reports.export', 'reports.ai_summary',
            'traceability.view',
            'items.view', 'items.create', 'items.update',
            'partners.view', 'partners.create', 'partners.update',
            'locations.view', 'locations.create', 'locations.update', 'locations.block',
            'virtual-warehouses.view',
            'stock.view', 'stock.movements.view', 'stock.verify',
            'serials.view', 'imports.manage',
            'labels.templates.manage', 'labels.print', 'labels.reprint', 'labels.log.view',
            'printers.manage', 'scanner-devices.manage', 'scan.move', 'scan.lookup',
            // PO workflow
            'po.view', 'po.accept', 'po.assign', 'po.receive',
            'po.validate', 'po.excess.approve', 'po.close.short',
        ],

        'warehouse-supervisor' => [
            'containers.view',
            'grn.view', 'grn.receive', 'grn.confirm',
            'putaway.view', 'putaway.execute', 'putaway.assign',
            'movements.view', 'movements.execute',
            'transfers.view', 'transfers.execute',
            'inventory.view',
            'adjustments.view', 'adjustments.create',
            'cycle-counts.view', 'cycle-counts.execute',
            'counts.view', 'counts.count',
            'items.view',
            'partners.view',
            'locations.view',
            'virtual-warehouses.view',
            'stock.view', 'stock.movements.view',
            'serials.view',
            'labels.print', 'labels.log.view', 'scan.move', 'scan.lookup',
            // PO workflow
            'po.view', 'po.assign', 'po.receive', 'po.validate',
        ],

        'department-user' => [
            'inventory.view',
            'orders.view', 'orders.create', 'orders.update', 'orders.submit',
            'orders.approve.department',
            'items.view',
            'virtual-warehouses.view',
            'stock.view', 'stock.movements.view',
            'serials.view',
            'transfers.view', 'transfers.create',
            'movements.view', 'movements.create',
            // PO workflow
            'po.view', 'po.create', 'po.approve',
            // GRN
            'grn.view',
            // Approvals inbox
            'approvals.view',
        ],

        'storekeeper' => [
            'containers.view', 'containers.arrive', 'containers.park', 'containers.unload',
            'containers.documents', 'containers.discrepancies',
            'grn.view', 'grn.create', 'grn.receive', 'grn.confirm',
            'grn.cancel',
            // PO workflow
            'po.view', 'po.receive',
            'lpn.print',
            'inspections.view', 'inspections.create',
            'putaway.view', 'putaway.execute', 'putaway.assign',
            'movements.view', 'movements.execute',
            'transfers.view', 'transfers.execute',
            'inventory.view',
            'adjustments.view', 'adjustments.create',
            'cycle-counts.view', 'cycle-counts.execute',
            'counts.view', 'counts.count',
            'quarantine.manage',
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
            'po.view', 'po.approve', 'po.verify',
            'containers.view',
            'grn.view',
            'inspections.view',
            'putaway.view',
            'movements.view',
            'transfers.view',
            'inventory.view',
            'adjustments.view',
            'cycle-counts.view',
            'counts.view',
            'orders.view',
            'picking.view',
            'dispatch.view',
            'approvals.view',
            'reports.view', 'reports.export', 'reports.ai_summary',
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

        // Department approvers — can approve/reject DOs at department level
        'department-manager' => [
            'orders.view', 'orders.create', 'orders.update', 'orders.submit',
            'orders.approve.department',
            'approvals.view', 'approvals.approve',
            'po.view', 'po.approve',
            'inventory.view',
            'items.view',
            'virtual-warehouses.view',
            'stock.view', 'stock.movements.view',
            'transfers.view', 'transfers.create',
        ],

        'department-head' => [
            'orders.view', 'orders.create', 'orders.update', 'orders.submit',
            'orders.approve.department',
            'approvals.view', 'approvals.approve',
            'po.view', 'po.approve',
            'inventory.view',
            'items.view',
            'virtual-warehouses.view',
            'stock.view', 'stock.movements.view',
            'transfers.view', 'transfers.create',
        ],

        // Read-only analytics role
        'data-analyst' => [
            'po.view',
            'containers.view',
            'grn.view',
            'movements.view',
            'transfers.view',
            'inventory.view',
            'adjustments.view',
            'counts.view',
            'orders.view',
            'dispatch.view',
            'reports.view', 'reports.export', 'reports.ai_summary',
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

        // Remove the old transfers.approve permission (replaced by transfers.accept)
        Permission::where('name', 'transfers.approve')->where('guard_name', 'web')->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
