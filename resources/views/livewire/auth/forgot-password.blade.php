<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink($this->only('email'));

        session()->flash('status', __('A reset link will be sent if the account exists.'));
    }
}; ?>

<div class="flex flex-col gap-5">
    <x-auth-header
        title="{{ __('Forgot your password?') }}"
        description="{{ __('Enter your email and we will send you a reset link.') }}"
    />

    @if (session('status'))
        <x-auth.alert :status="session('status')" type="success" />
    @endif

    <form wire:submit="sendPasswordResetLink" class="flex flex-col gap-4">
        <x-auth.input
            wire:model="email"
            name="email"
            label="{{ __('Email address') }}"
            icon="mail"
            type="email"
            required
            autofocus
            placeholder="you@example.com"
        />

        <x-auth.button wire:loading.attr="disabled" loading-text="{{ __('Sending…') }}">
            {{ __('Send reset link') }}
        </x-auth.button>
    </form>

    <p class="text-center text-sm text-slate-500">
        <x-auth.link href="{{ route('login') }}" wire:navigate>{{ __('Back to sign in') }}</x-auth.link>
    </p>
</div>
