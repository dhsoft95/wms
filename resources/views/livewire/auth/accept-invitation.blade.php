<div class="flex flex-col gap-5">
    <x-auth-header
        title="{{ __('Activate your account') }}"
        description="{{ __('Set a password to finish setting up your account.') }}"
    />

    @if (! $tokenValid)
        <x-auth.alert
            status="{{ __('This invitation link is no longer valid. Ask your administrator to resend the invitation.') }}"
            type="warning"
        />
        <p class="text-center">
            <x-auth.link href="{{ route('login') }}" wire:navigate>{{ __('Return to sign in') }}</x-auth.link>
        </p>
    @else
        @if ($invitedUser)
            <div class="glass-input rounded-xl px-4 py-3 space-y-0.5">
                <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $invitedUser->name }}</p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $invitedUser->email }}</p>
            </div>
        @endif

        <form wire:submit="save" class="flex flex-col gap-4">
            <x-auth.password
                wire:model="password"
                name="password"
                label="{{ __('New password') }}"
                required
                autocomplete="new-password"
                placeholder="{{ __('At least 8 characters') }}"
            />

            <x-auth.password
                wire:model="password_confirmation"
                name="password_confirmation"
                label="{{ __('Confirm password') }}"
                required
                autocomplete="new-password"
                placeholder="{{ __('Repeat password') }}"
            />

            <x-auth.button wire:loading.attr="disabled" loading-text="{{ __('Activating…') }}">
                {{ __('Activate account') }}
            </x-auth.button>
        </form>
    @endif
</div>
