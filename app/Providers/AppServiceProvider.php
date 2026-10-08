<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Prevent Fortify from registering its own auth routes (login, register, etc.)
        // We manage all auth pages with our own Livewire/Volt components.
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        // System Admin bypasses every Gate check
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('system-admin')) {
                return true;
            }
        });

        // Password rules applied everywhere via Password::defaults()
        Password::defaults(function () {
            return app()->isProduction()
                ? Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised()
                : Password::min(8)->mixedCase()->numbers()->symbols();
        });
    }
}
