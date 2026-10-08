<div class="flex flex-col gap-5">
    <x-auth-header
        title="{{ __('Two-factor verification') }}"
        description="{{ __('Enter the code to continue.') }}"
    />

    @if (! $challengeUser)
        <x-auth.alert status="{{ __('Your session has expired.') }}" type="warning" />
        <p class="text-sm text-slate-500 text-center">
            <a href="{{ route('login') }}" class="font-semibold text-simba-600 hover:text-simba-700 transition-colors" wire:navigate>{{ __('Sign in again') }}</a>
        </p>
    @else
        {{-- Method toggle --}}
        <div class="glass-input rounded-xl p-1 flex gap-1">
            <button
                type="button"
                wire:click="$set('usingRecovery', false)"
                @class([
                    'flex-1 rounded-lg px-3 py-2 text-xs font-semibold transition-all duration-150',
                    'glass shadow-sm text-slate-900' => ! $usingRecovery,
                    'text-slate-500 hover:text-slate-700' => $usingRecovery,
                ])
            >
                {{ $challengeUser->two_factor_method === 'app' ? __('Authenticator') : __('Email code') }}
            </button>
            <button
                type="button"
                wire:click="$set('usingRecovery', true)"
                @class([
                    'flex-1 rounded-lg px-3 py-2 text-xs font-semibold transition-all duration-150',
                    'glass shadow-sm text-slate-900' => $usingRecovery,
                    'text-slate-500 hover:text-slate-700' => ! $usingRecovery,
                ])
            >
                {{ __('Recovery code') }}
            </button>
        </div>

        <form wire:submit="verify" class="flex flex-col gap-4">
            @if (! $usingRecovery)
                <x-auth.input
                    wire:model="code"
                    name="code"
                    icon="key"
                    label="{{ $challengeUser->two_factor_method === 'app' ? __('Authenticator code') : __('Email verification code') }}"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    required
                    autofocus
                    autocomplete="one-time-code"
                    placeholder="000 000"
                />
            @else
                <x-auth.input
                    wire:model="recoveryCode"
                    name="recoveryCode"
                    icon="key"
                    label="{{ __('Recovery code') }}"
                    type="text"
                    required
                    autofocus
                    autocomplete="off"
                    placeholder="xxxx-xxxx"
                />
            @endif

            <x-auth.button
                wire:loading.attr="disabled"
                wire:target="verify"
                loading-text="{{ __('Verifying…') }}"
            >
                {{ __('Verify') }}
            </x-auth.button>
        </form>

        @if (! $usingRecovery && $challengeUser->two_factor_method === 'email')
            <div class="text-center">
                <button
                    type="button"
                    wire:click="resendCode"
                    wire:loading.attr="disabled"
                    wire:target="resendCode"
                    class="text-sm font-semibold text-simba-600 hover:text-simba-700 transition-colors"
                >
                    {{ __('Resend code') }}
                </button>
            </div>
        @endif
    @endif
</div>
