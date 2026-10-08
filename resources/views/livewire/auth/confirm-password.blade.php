<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-5">
    <x-auth-header
        title="{{ __('Confirm your password') }}"
        description="{{ __('This is a secure area. Confirm your password to continue.') }}"
    />

    @if (session('status'))
        <x-auth.alert :status="session('status')" type="info" />
    @endif

    <form wire:submit="confirmPassword" class="flex flex-col gap-4">
        <x-auth.password
            wire:model="password"
            name="password"
            label="{{ __('Password') }}"
            required
            autocomplete="current-password"
            placeholder="{{ __('Enter your password') }}"
        />

        <x-auth.button wire:loading.attr="disabled" loading-text="{{ __('Confirming…') }}">
            {{ __('Confirm') }}
        </x-auth.button>
    </form>
</div>
