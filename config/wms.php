<?php

return [
    // Invitation link expiry in hours
    'invitation_expiry_hours' => env('WMS_INVITATION_EXPIRY_HOURS', 72),

    // Roles that bypass virtual warehouse scoping and see all VWs.
    // Only 'department-user' is restricted by default.
    // Storekeepers and scanner operators need to execute tasks for every department.
    'vw_unrestricted_roles' => [
        'system-admin',
        'warehouse-manager',
        'warehouse-supervisor',
        'viewer-auditor',
        'storekeeper',
        'scanner-operator',
    ],

    // Email OTP expiry in minutes
    'otp_expiry_minutes' => env('WMS_OTP_EXPIRY_MINUTES', 10),

    // PO approver verification reminder — how many days between reminders
    'po_approver_reminder_days' => env('WMS_PO_APPROVER_REMINDER_DAYS', 2),

    // Default values pre-filled on new items (copied from old global settings)
    'item_defaults' => [
        'variance_threshold_pct'     => env('WMS_VARIANCE_THRESHOLD_PCT', null),
        'variance_threshold_qty'     => env('WMS_VARIANCE_THRESHOLD_QTY', null),
        'dispatch_requires_approval' => env('WMS_DISPATCH_REQUIRES_APPROVAL', true),
    ],

    // Branding — override via .env or a child config to white-label the product
    'branding' => [
        'name'            => env('WMS_BRAND_NAME', 'SIMBA WMS'), // null → falls back to config('app.name')
        'subtitle'        => env('WMS_BRAND_SUBTITLE', 'Warehouse Management System'),
        'initials'        => env('WMS_BRAND_INITIALS', 'SC'),
        'logo'            => env('WMS_BRAND_LOGO', 'images/logo/logo.png'),
        'auth_background' => env('WMS_AUTH_BACKGROUND', 'images/auth/warehouse-bg.jpg'),
        'app_background'  => env('WMS_APP_BACKGROUND',  'images/app/warehouse-bg.webp'),
        'headline'        => 'Warehouse operations,',
        'headline_accent' => 'from yard to doorstep.',
        'tagline'         => 'Smarter warehouse management for greater visibility, accuracy and efficiency, all in one powerful system.',
        'features'        => [
            ['icon' => 'container',         'title' => 'Container Management',   'text' => 'Track arrivals, parking and container status in real time.'],
            ['icon' => 'arrows-right-left', 'title' => 'Inbound & Outbound',     'text' => 'Manage receiving, put-away, picking and dispatch.'],
            ['icon' => 'cube',              'title' => 'Stock Movements',        'text' => 'Relocations, transfers, adjustments and more.'],
            ['icon' => 'chart-bar',         'title' => 'Reporting & Visibility', 'text' => 'Accurate data, better decisions, full warehouse oversight.'],
        ],
        'footer_pillars'  => ['Safe', 'Efficient', 'Connected'],
    ],
];
