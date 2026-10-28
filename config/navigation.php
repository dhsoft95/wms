<?php

return [
    'groups' => [

        // ── Overview ──────────────────────────────────────────────────────────
        [
            'key'   => 'overview',
            'label' => 'Overview',
            'can'   => null,
            'items' => [
                [
                    'key'           => 'dashboard',
                    'label'         => 'Dashboard',
                    'icon'          => 'squares-2x2',
                    'route'         => 'dashboard',
                    'active_routes' => ['dashboard'],
                    'can'           => null,
                ],
                [
                    'key'           => 'approvals',
                    'label'         => 'Approvals Inbox',
                    'icon'          => 'inbox',
                    'route'         => 'approvals.index',
                    'active_routes' => ['approvals.index'],
                    'can'           => 'approvals.view',
                    'badge'         => 'approvals',
                ],
            ],
        ],

        // ── Orders ────────────────────────────────────────────────────────────
        [
            'key'   => 'orders',
            'label' => 'Orders',
            'can'   => null,
            'items' => [
                [
                    'key'           => 'all-orders',
                    'label'         => 'Orders',
                    'icon'          => 'clipboard-list',
                    'route'         => 'orders.index',
                    'active_routes' => ['orders.index'],
                    'can'           => null,
                ],
            ],
        ],

        // ── Inbound ───────────────────────────────────────────────────────────
        [
            'key'   => 'inbound',
            'label' => 'Inbound',
            'can'   => 'grn.view',
            'items' => [
                [
                    'key'           => 'grn',
                    'label'         => 'GRN',
                    'icon'          => 'clipboard',
                    'route'         => 'inbound.grn.index',
                    'active_routes' => ['inbound.grn.*'],
                    'can'           => 'grn.view',
                ],
                [
                    'key'           => 'putaway',
                    'label'         => 'Putaway',
                    'icon'          => 'archive',
                    'route'         => 'inbound.putaway.index',
                    'active_routes' => ['inbound.putaway.*', 'warehouse.putaway.*'],
                    'can'           => 'putaway.view',
                ],
                [
                    'key'           => 'inbound-return',
                    'label'         => 'Return',
                    'icon'          => 'arrow-uturn-left',
                    'route'         => 'dashboard',        // placeholder – no route yet
                    'active_routes' => ['inbound.returns.*'],
                    'can'           => 'grn.view',
                    'enabled'       => false,
                ],
            ],
        ],

        // ── Outbound ──────────────────────────────────────────────────────────
        [
            'key'   => 'outbound',
            'label' => 'Outbound',
            'can'   => 'orders.view',
            'items' => [
                [
                    'key'           => 'mrn',
                    'label'         => 'MRN',
                    'icon'          => 'document-text',
                    'route'         => 'outbound.orders.index',
                    'active_routes' => ['outbound.orders.*'],
                    'can'           => 'orders.view',
                ],
                [
                    'key'           => 'picking',
                    'label'         => 'Picking',
                    'icon'          => 'clipboard-list',
                    'route'         => 'outbound.pick-lists.index',
                    'active_routes' => ['outbound.pick-lists.*'],
                    'can'           => 'picking.view',
                ],
                [
                    'key'           => 'dispatch',
                    'label'         => 'Dispatch',
                    'icon'          => 'truck',
                    'route'         => 'outbound.dispatch.index',
                    'active_routes' => ['outbound.dispatch.*'],
                    'can'           => 'dispatch.view',
                ],
                [
                    'key'           => 'gate-check',
                    'label'         => 'Gate Check',
                    'icon'          => 'shield-check',
                    'route'         => 'outbound.gate.check',
                    'active_routes' => ['outbound.gate.*'],
                    'can'           => 'gate.verify',
                ],
                [
                    'key'           => 'pod',
                    'label'         => 'Proof of Delivery',
                    'icon'          => 'badge-check',
                    'route'         => 'outbound.pod.index',
                    'active_routes' => ['outbound.pod.*'],
                    'can'           => 'pod.upload',
                ],
            ],
        ],

        // ── Stock Transfers (was: Operations) ─────────────────────────────────
        [
            'key'   => 'stock-transfers',
            'label' => 'Stock Transfers',
            'can'   => null,
            'items' => [
                [
                    'key'           => 'movements',
                    'label'         => 'Internal Movements',
                    'icon'          => 'arrows',
                    'route'         => 'warehouse.movements.index',
                    'active_routes' => ['warehouse.movements.*'],
                    'can'           => 'movements.view',
                ],
                [
                    'key'           => 'transfers',
                    'label'         => 'Dept. Transfers',
                    'icon'          => 'arrows',
                    'route'         => 'warehouse.transfers.index',
                    'active_routes' => ['warehouse.transfers.*'],
                    'can'           => 'transfers.view',
                ],
                [
                    'key'           => 'wtr',
                    'label'         => 'WH Transfers',
                    'icon'          => 'truck',
                    'route'         => 'warehouse.wtr.index',
                    'active_routes' => ['warehouse.wtr.*'],
                    'can'           => 'transfers.view',
                ],
                [
                    'key'           => 'stock-return',
                    'label'         => 'Return',
                    'icon'          => 'arrow-uturn-left',
                    'route'         => 'dashboard',        // placeholder – no route yet
                    'active_routes' => ['warehouse.returns.*'],
                    'can'           => 'movements.view',
                    'enabled'       => false,
                ],
            ],
        ],

        // ── Inventory Control ─────────────────────────────────────────────────
        [
            'key'   => 'inventory',
            'label' => 'Inventory Control',
            'can'   => 'stock.view',
            'items' => [
                [
                    'key'           => 'adjustments',
                    'label'         => 'Stock Adjustments',
                    'icon'          => 'adjustments',
                    'route'         => 'inventory.adjustments.index',
                    'active_routes' => ['inventory.adjustments.*'],
                    'can'           => 'adjustments.view',
                ],
                [
                    'key'           => 'counts',
                    'label'         => 'Cycle Counts',
                    'icon'          => 'refresh',
                    'route'         => 'inventory.counts.index',
                    'active_routes' => ['inventory.counts.*'],
                    'can'           => 'counts.view',
                ],
                [
                    'key'           => 'quarantine',
                    'label'         => 'Quarantine',
                    'icon'          => 'lock',
                    'route'         => 'inventory.quarantine.index',
                    'active_routes' => ['inventory.quarantine.*'],
                    'can'           => 'quarantine.manage',
                ],
                [
                    'key'           => 'serials',
                    'label'         => 'Serial Lookup',
                    'icon'          => 'qr-code',
                    'route'         => 'inventory.serials.lookup',
                    'active_routes' => ['inventory.serials.*'],
                    'can'           => 'serials.view',
                ],
                [
                    'key'           => 'sku-tracker',
                    'label'         => 'SKU Tracker',
                    'icon'          => 'search',
                    'route'         => 'inventory.sku-tracker.index',
                    'active_routes' => ['inventory.sku-tracker.*'],
                    'can'           => 'stock.view',
                ],
                [
                    'key'           => 'disposals',
                    'label'         => 'Disposals',
                    'icon'          => 'trash',
                    'route'         => 'inventory.disposals.index',
                    'active_routes' => ['inventory.disposals.*'],
                    'can'           => 'disposals.create',
                ],
            ],
        ],

        // ── Reports & Documents ───────────────────────────────────────────────
        [
            'key'   => 'reports',
            'label' => 'Reports & Documents',
            'can'   => 'stock.view',
            'items' => [
                [
                    'key'           => 'stock',
                    'label'         => 'Stock on Hand',
                    'icon'          => 'list',
                    'route'         => 'inventory.stock.index',
                    'active_routes' => ['inventory.stock.*'],
                    'can'           => 'stock.view',
                ],
                [
                    'key'           => 'yard',
                    'label'         => 'Yard Map',
                    'icon'          => 'map-pin',
                    'route'         => 'inbound.yard.index',
                    'active_routes' => ['inbound.yard.*'],
                    'can'           => 'containers.view',
                ],
                [
                    'key'           => 'containers',
                    'label'         => 'Containers',
                    'icon'          => 'package',
                    'route'         => 'inbound.containers.index',
                    'active_routes' => ['inbound.containers.*'],
                    'can'           => 'containers.view',
                ],
                [
                    'key'           => 'reports-library',
                    'label'         => 'Reports Library',
                    'icon'          => 'chart-bar',
                    'route'         => 'reports.index',
                    'active_routes' => ['reports.index'],
                    // 'can'           => 'reports.view', // TODO: restore after DB seeder runs
                ],
                [
                    'key'           => 'scheduled-reports',
                    'label'         => 'Scheduled Reports',
                    'icon'          => 'clock',
                    'route'         => 'admin.scheduled-reports.index',
                    'active_routes' => ['admin.scheduled-reports.*'],
                    'can'           => 'reports.manage',
                ],
                [
                    'key'           => 'print',
                    'label'         => 'Printable Documents',
                    'icon'          => 'printer',
                    'route'         => 'reports.print',
                    'active_routes' => ['reports.print'],
                    'can'           => 'reports.view',
                ],
                [
                    'key'           => 'labels-log',
                    'label'         => 'Label Print Log',
                    'icon'          => 'printer',
                    'route'         => 'reports.labels.log',
                    'active_routes' => ['reports.labels.log'],
                    'can'           => 'labels.log.view',
                ],
            ],
        ],

        // ── Master Data ───────────────────────────────────────────────────────
        [
            'key'   => 'master-data',
            'label' => 'Master Data',
            'can'   => 'items.view',
            'items' => [
                [
                    'key'           => 'partners',
                    'label'         => 'Partners',
                    'icon'          => 'users',
                    'route'         => 'admin.partners.index',
                    'active_routes' => ['admin.partners.*'],
                    'can'           => 'partners.view',
                ],
                [
                    'key'           => 'warehouses',
                    'label'         => 'Warehouses',
                    'icon'          => 'archive',
                    'route'         => 'admin.warehouses.index',
                    'active_routes' => ['admin.warehouses.*'],
                    'can'           => 'locations.view',
                ],
                [
                    'key'           => 'locations',
                    'label'         => 'Locations',
                    'icon'          => 'flag',
                    'route'         => 'admin.locations.index',
                    'active_routes' => ['admin.locations.*'],
                    'can'           => 'locations.view',
                ],
                [
                    'key'           => 'item-categories',
                    'label'         => 'Item Categories',
                    'icon'          => 'list',
                    'route'         => 'admin.item-categories.index',
                    'active_routes' => ['admin.item-categories.*'],
                    'can'           => 'items.create',
                ],
                [
                    'key'           => 'items',
                    'label'         => 'Items',
                    'icon'          => 'cube',
                    'route'         => 'admin.items.index',
                    'active_routes' => ['admin.items.*'],
                    'can'           => 'items.view',
                ],
            ],
        ],

        // ── Administration ────────────────────────────────────────────────────
        [
            'key'   => 'admin',
            'label' => 'Administration',
            'can'   => null,
            'items' => [
                [
                    'key'           => 'settings',
                    'label'         => 'Account Configuration',
                    'icon'          => 'adjustments',
                    'route'         => 'admin.settings.index',
                    'active_routes' => ['admin.settings.*'],
                    'can'           => 'settings.manage',
                ],
                [
                    'key'           => 'roles',
                    'label'         => 'Roles & Permissions',
                    'icon'          => 'shield',
                    'route'         => 'admin.roles.index',
                    'active_routes' => ['admin.roles.*'],
                    'can'           => 'roles.manage',
                ],
                [
                    'key'           => 'departments',
                    'label'         => 'Departments',
                    'icon'          => 'cog',
                    'route'         => 'admin.departments.index',
                    'active_routes' => ['admin.departments.*'],
                    'can'           => 'departments.manage',
                ],
                [
                    'key'           => 'approval-chains',
                    'label'         => 'Approval Chains',
                    'icon'          => 'check-badge',
                    'route'         => 'admin.approval-chains.index',
                    'active_routes' => ['admin.approval-chains.*'],
                    'can'           => 'settings.manage',
                ],
                [
                    'key'           => 'users',
                    'label'         => 'Users',
                    'icon'          => 'users',
                    'route'         => 'admin.users.index',
                    'active_routes' => ['admin.users.*'],
                    'can'           => 'users.manage',
                ],
                [
                    'key'           => 'data-import',
                    'label'         => 'Data Import',
                    'icon'          => 'upload',
                    'route'         => 'admin.imports.index',
                    'active_routes' => ['admin.imports.*'],
                    'can'           => 'imports.manage',
                ],
                [
                    'key'           => 'label-templates',
                    'label'         => 'Label Templates',
                    'icon'          => 'tag',
                    'route'         => 'admin.labels.templates.index',
                    'active_routes' => ['admin.labels.templates.*'],
                    'can'           => 'labels.templates.manage',
                ],
                [
                    'key'           => 'printers',
                    'label'         => 'Printers',
                    'icon'          => 'printer',
                    'route'         => 'admin.printers.index',
                    'active_routes' => ['admin.printers.*'],
                    'can'           => 'printers.manage',
                ],
                [
                    'key'           => 'scanner-devices',
                    'label'         => 'Scanner Devices',
                    'icon'          => 'device-mobile',
                    'route'         => 'admin.scanner-devices.index',
                    'active_routes' => ['admin.scanner-devices.*'],
                    'can'           => 'scanner-devices.manage',
                ],
                [
                    'key'           => 'scheduled-reports-admin',
                    'label'         => 'Scheduled Reports',
                    'icon'          => 'clock',
                    'route'         => 'admin.scheduled-reports.index',
                    'active_routes' => ['admin.scheduled-reports.*'],
                    'can'           => 'reports.manage',
                ],
            ],
        ],

    ],
];
