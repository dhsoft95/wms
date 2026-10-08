<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-5">
    <x-auth-header
        title="{{ __('Verify your email') }}"
        description="{{ __('Check your inbox for the verification link we sent you. Check your spam folder if you don\'t see it.') }}"
    />

    @if (session('status') === 'verification-link-sent')
        <x-auth.alert status="{{ __('A new verification link has been sent to your email address.') }}" type="success" />
    @endif

    <div class="flex flex-col gap-3">
        <x-auth.button
            wire:click="sendVerification"
            wire:loading.attr="disabled"
            wire:target="sendVerification"
            type="button"
            loading-text="{{ __('Sending…') }}"
        >
            {{ __('Resend verification email') }}
        </x-auth.button>

        <button
            wire:click="logout"
            type="button"
            class="text-sm text-slate-500 hover:text-slate-700 text-center transition-colors"
        >
            {{ __('Log out') }}
        </button>
    </div>
</div>
