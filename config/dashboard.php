<?php

/*
 * Dashboard visibility configuration.
 *
 * kpi_visibility   — which KPI cards each role may see.
 *                    Use 'all' to grant every card.
 * action_visibility— which action list panels each role may see.
 *                    Use 'all' to grant every panel.
 * readonly_roles   — these roles see data but never see action buttons
 *                    (Approve, Execute, etc.) inside panels.
 */

return [

    // ── KPI cards ─────────────────────────────────────────────────────────────
    //   stock_on_hand | open_orders | pending_approvals | to_receive
    //   awaiting_putaway | to_pick | in_transit | low_stock | expiring
    //   containers_in_yard

    'kpi_visibility' => [
        'system-admin'         => 'all',
        'warehouse-manager'    => 'all',
        'warehouse-supervisor' => 'all',
        'storekeeper'          => ['stock_on_hand', 'to_receive', 'awaiting_putaway', 'to_pick', 'low_stock', 'expiring'],
        'scanner-operator'     => ['awaiting_putaway', 'to_pick'],
        'department-user'      => ['stock_on_hand', 'open_orders', 'pending_approvals', 'low_stock', 'expiring'],
        'department-manager'   => ['stock_on_hand', 'open_orders', 'pending_approvals', 'low_stock', 'expiring'],
        'department-head'      => ['stock_on_hand', 'open_orders', 'pending_approvals', 'low_stock', 'expiring'],
        'data-analyst'         => 'all',
        'viewer-auditor'       => 'all',
        'gate-security'        => ['containers_in_yard', 'in_transit'],
    ],

    // ── Action lists ──────────────────────────────────────────────────────────
    //   my_approvals | overdue_orders | recent_variances | recent_activity

    'action_visibility' => [
        'system-admin'         => 'all',
        'warehouse-manager'    => 'all',
        'warehouse-supervisor' => 'all',
        'storekeeper'          => ['recent_variances', 'recent_activity'],
        'scanner-operator'     => ['recent_activity'],
        'department-user'      => ['my_approvals', 'overdue_orders'],
        'department-manager'   => ['my_approvals', 'overdue_orders'],
        'department-head'      => ['my_approvals', 'overdue_orders'],
        'data-analyst'         => ['recent_variances', 'recent_activity'],
        'viewer-auditor'       => 'all',
        'gate-security'        => [],
    ],

    // ── Chart visibility ──────────────────────────────────────────────────────
    //   1 inbound_vs_outbound | 2 stock_by_vw     | 3 stock_by_category
    //   4 order_pipeline      | 5 bin_utilisation | 6 expiry_profile
    //   7 top_moving_items    | 8 container_dwell

    'chart_visibility' => [
        'system-admin'         => 'all',
        'warehouse-manager'    => 'all',
        'warehouse-supervisor' => 'all',
        'data-analyst'         => 'all',
        'viewer-auditor'       => 'all',
        'department-user'      => ['stock_by_vw', 'stock_by_category', 'expiry_profile', 'top_moving_items'],
        'department-manager'   => ['stock_by_vw', 'stock_by_category', 'expiry_profile', 'top_moving_items'],
        'department-head'      => ['stock_by_vw', 'stock_by_category', 'expiry_profile', 'top_moving_items'],
        'storekeeper'          => ['order_pipeline', 'bin_utilisation', 'container_dwell'],
        'warehouse-supervisor' => 'all',
        'scanner-operator'     => [],
        'gate-security'        => ['container_dwell'],
    ],

    // ── Roles that see panels but cannot use action buttons ───────────────────
    'readonly_roles' => [
        'data-analyst',
        'viewer-auditor',
        'gate-security',
    ],

];
