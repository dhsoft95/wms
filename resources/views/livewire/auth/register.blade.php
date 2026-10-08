<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered(($user = User::create($validated))));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="{{ __('Create an account') }}" description="{{ __('Enter your details to get started.') }}" />

    <x-auth-session-status :status="session('status')" />

    <form wire:submit="register" class="flex flex-col gap-5">
        <flux:input
            wire:model="name"
            label="{{ __('Full name') }}"
            type="text"
            name="name"
            required
            autofocus
            autocomplete="name"
            placeholder="{{ __('Your name') }}"
        />

        <flux:input
            wire:model="email"
            label="{{ __('Email address') }}"
            type="email"
            name="email"
            required
            autocomplete="email"
            placeholder="you@example.com"
        />

        <flux:input
            wire:model="password"
            label="{{ __('Password') }}"
            type="password"
            viewable
            name="password"
            required
            autocomplete="new-password"
            placeholder="{{ __('At least 8 characters') }}"
        />

        <flux:input
            wire:model="password_confirmation"
            label="{{ __('Confirm password') }}"
            type="password"
            viewable
            name="password_confirmation"
            required
            autocomplete="new-password"
            placeholder="{{ __('Repeat password') }}"
        />

        <button type="submit" class="btn btn-primary w-full py-2.5" wire:loading.attr="disabled">
            <svg wire:loading class="animate-spin w-4 h-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 5.373 22 12h-4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
            </svg>
            <span wire:loading.remove>{{ __('Create account') }}</span>
            <span wire:loading>{{ __('Creating…') }}</span>
        </button>
    </form>

    <p class="text-center text-sm text-slate-500">
        {{ __('Already have an account?') }}
        <x-text-link href="{{ route('login') }}">{{ __('Sign in') }}</x-text-link>
    </p>
</div>
