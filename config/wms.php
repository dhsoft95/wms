<?php

return [
    // Invitation link expiry in hours
    'invitation_expiry_hours' => env('WMS_INVITATION_EXPIRY_HOURS', 72),

    // Email OTP expiry in minutes
    'otp_expiry_minutes' => env('WMS_OTP_EXPIRY_MINUTES', 10),

    // Branding — override via .env or a child config to white-label the product
    'branding' => [
        'name'            => env('WMS_BRAND_NAME', null), // null → falls back to config('app.name')
        'subtitle'        => env('WMS_BRAND_SUBTITLE', 'Warehouse Management System'),
        'initials'        => env('WMS_BRAND_INITIALS', 'SC'),
        'logo'            => env('WMS_BRAND_LOGO'),
        'auth_background' => env('WMS_AUTH_BACKGROUND', 'images/auth/warehouse-bg.jpg'),
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
