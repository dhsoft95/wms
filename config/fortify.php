<?php

use Laravel\Fortify\Features;

return [
    'guard'    => 'web',
    'passwords' => 'users',
    'username' => 'email',
    'email'    => 'email',
    'lowercase_usernames' => true,
    'home'     => '/dashboard',
    'prefix'   => '',
    'domain'   => null,
    'middleware' => ['web'],

    'limiters' => [
        'login'      => 'login',
        'two-factor' => 'two-factor',
    ],

    'views' => false,

    // Only 2FA — all auth routes (login, register, reset, etc.) are handled by our own Volt pages.
    'features' => [
        Features::twoFactorAuthentication([
            'confirm'         => true,
            'confirmPassword' => true,
        ]),
    ],
];
