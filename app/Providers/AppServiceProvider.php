<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
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

        $this->app->bind(
            \App\Contracts\ContainerReceivingGate::class,
            \App\Services\Inbound\RealContainerReceivingGate::class,
        );
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

        // Container module: event listeners
        Event::listen(
            \App\Events\Inbound\ContainerArrived::class,
            \App\Listeners\Inbound\NotifyContainerArrived::class,
        );
        Event::listen(
            \App\Events\Inbound\ContainerSealMismatch::class,
            \App\Listeners\Inbound\NotifySealMismatch::class,
        );
        Event::listen(
            \App\Events\Inbound\ContainerUnloadingStarted::class,
            \App\Listeners\Inbound\CreateGrnFromContainer::class,
        );

        // GRN module: event listeners
        Event::listen(
            \App\Events\Inbound\GrnConfirmed::class,
            \App\Listeners\Inbound\NotifyGrnConfirmed::class,
        );

        // Container module: policies
        Gate::policy(\App\Models\Container::class, \App\Policies\ContainerPolicy::class);
        Gate::policy(\App\Models\ContainerDocument::class, \App\Policies\ContainerDocumentPolicy::class);

        // GRN module: policies
        Gate::policy(\App\Models\Grn::class, \App\Policies\GrnPolicy::class);
        Gate::policy(\App\Models\PutawayTask::class, \App\Policies\PutawayTaskPolicy::class);
    }
}
