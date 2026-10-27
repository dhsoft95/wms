<div class="space-y-6">
    {{-- Page header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">My Profile</h2>
            <p class="page-sub">Manage your personal information, security settings, and active sessions.</p>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="tabs">
        <button wire:click="$set('activeTab','personal')" class="tab {{ $activeTab === 'personal' ? 'active' : '' }}">
            <x-ui.icon name="user" class="w-4 h-4" /> Personal Info
        </button>
        <button wire:click="$set('activeTab','password')" class="tab {{ $activeTab === 'password' ? 'active' : '' }}">
            <x-ui.icon name="lock" class="w-4 h-4" /> Password
        </button>
        <button wire:click="$set('activeTab','2fa')" class="tab {{ $activeTab === '2fa' ? 'active' : '' }}">
            <x-ui.icon name="shield" class="w-4 h-4" /> Two-Factor Auth
        </button>
        <button wire:click="$set('activeTab','sessions')" class="tab {{ $activeTab === 'sessions' ? 'active' : '' }}">
            <x-ui.icon name="eye" class="w-4 h-4" /> Sessions
        </button>
    </div>

    {{-- ── TAB: Personal Info ──────────────────────────────────────────── --}}
    @if ($activeTab === 'personal')
        <form wire:submit="savePersonal" class="card max-w-lg">
            <div class="card-header">
                <h3 class="card-title">Personal Information</h3>
            </div>
            <div class="card-body space-y-4">
                <div>
                    <label class="label">Full name *</label>
                    <input wire:model="name" type="text" required placeholder="Your full name" class="input">
                    @error('name') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Email address *</label>
                    <input wire:model="email" type="email" required class="input">
                    @if (! auth()->user()->hasVerifiedEmail())
                        <p class="hint text-amber-600">Your email address is not verified.</p>
                    @endif
                    <p class="hint">Changing your email will require re-verification.</p>
                    @error('email') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Phone</label>
                    <input wire:model="phone" type="tel" placeholder="+255 7xx xxx xxx" class="input mono">
                    @error('phone') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">
                    <x-ui.icon name="check" class="w-4 h-4" /> Save Changes
                </button>
            </div>
        </form>
    @endif

    {{-- ── TAB: Password ───────────────────────────────────────────────── --}}
    @if ($activeTab === 'password')
        <form wire:submit="changePassword" class="card max-w-lg">
            <div class="card-header">
                <h3 class="card-title">Change Password</h3>
            </div>
            <div class="card-body space-y-4">
                <div>
                    <label class="label">Current password *</label>
                    <input wire:model="currentPassword" type="password" required autocomplete="current-password" class="input">
                    @error('currentPassword') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">New password *</label>
                    <input wire:model="newPassword" type="password" required autocomplete="new-password" class="input">
                    <p class="hint">Minimum 8 characters. Use a mix of letters, numbers, and symbols.</p>
                    @error('newPassword') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Confirm new password *</label>
                    <input wire:model="confirmPassword" type="password" required autocomplete="new-password" class="input">
                    @error('confirmPassword') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">
                    <x-ui.icon name="lock" class="w-4 h-4" /> Update Password
                </button>
            </div>
        </form>
    @endif

    {{-- ── TAB: Two-Factor Auth ────────────────────────────────────────── --}}
    @if ($activeTab === '2fa')

        @php
            $user       = auth()->user();
            $appActive  = $twoFactorMethod === 'app'   && $user->two_factor_confirmed_at;
            $emailActive = $twoFactorMethod === 'email';
            $twoFaOn    = $appActive || $emailActive;
        @endphp

        {{-- Status banner when 2FA is active --}}
        @if ($twoFaOn)
            <div class="card max-w-lg">
                <div class="card-body flex items-start gap-4">
                    <div class="rounded-full bg-green-100 dark:bg-green-900/30 p-2 shrink-0">
                        <x-ui.icon name="shield" class="w-5 h-5 text-green-600" />
                    </div>
                    <div class="flex-1">
                        <p class="font-semibold text-slate-800 dark:text-slate-100">Two-factor authentication is enabled</p>
                        <p class="text-sm text-slate-500 mt-0.5">
                            Method: <span class="badge badge-green">{{ $appActive ? 'Authenticator App' : 'Email OTP' }}</span>
                        </p>
                    </div>
                    <button type="button" wire:click="disableTwoFactor"
                        wire:confirm="Are you sure you want to disable two-factor authentication?"
                        class="btn btn-danger btn-sm shrink-0">
                        <x-ui.icon name="x" class="w-4 h-4" /> Disable
                    </button>
                </div>

                {{-- Recovery codes section (app only) --}}
                @if ($appActive)
                    <div class="border-t border-slate-100 dark:border-slate-800">
                        <div class="px-6 py-4 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Recovery Codes</p>
                                <p class="text-xs text-slate-500">Use these if you lose access to your authenticator app.</p>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" wire:click="$toggle('showRecoveryCodes')" class="btn btn-ghost btn-sm">
                                    <x-ui.icon name="eye" class="w-4 h-4" />
                                    {{ $showRecoveryCodes ? 'Hide' : 'View' }} Codes
                                </button>
                                <button type="button" wire:click="regenerateRecoveryCodes"
                                    wire:confirm="This will invalidate your existing recovery codes. Continue?"
                                    class="btn btn-secondary btn-sm">
                                    <x-ui.icon name="refresh" class="w-4 h-4" /> Regenerate
                                </button>
                            </div>
                        </div>

                        @if ($showRecoveryCodes)
                            <div class="px-6 pb-4">
                                <div class="grid grid-cols-2 gap-2 p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
                                    @foreach ($this->recoveryCodes as $code)
                                        <span class="mono text-sm text-slate-700 dark:text-slate-200 select-all">{{ $code }}</span>
                                    @endforeach
                                </div>
                                <p class="hint mt-2">Store these codes somewhere safe. Each code can only be used once.</p>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        {{-- TOTP setup: QR code step --}}
        @if ($showQrCode && ! $appActive)
            <div class="card max-w-lg">
                <div class="card-header">
                    <h3 class="card-title">Set Up Authenticator App</h3>
                </div>
                <div class="card-body space-y-4">
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.),
                        then enter the 6-digit code below to confirm.
                    </p>

                    <div class="flex justify-center p-4 bg-white rounded-lg border border-slate-200">
                        {!! $this->qrCodeSvg !!}
                    </div>

                    @if (auth()->user()->two_factor_secret)
                        <div>
                            <p class="section-label mb-1">Manual entry key</p>
                            <p class="mono text-sm bg-slate-50 dark:bg-slate-800 px-3 py-2 rounded-lg select-all tracking-widest">
                                {{ decrypt(auth()->user()->two_factor_secret) }}
                            </p>
                        </div>
                    @endif

                    <form wire:submit="confirmApp" class="space-y-3">
                        <div>
                            <label class="label">Confirmation code *</label>
                            <input wire:model="confirmCode" type="text" inputmode="numeric" pattern="[0-9]*"
                                maxlength="6" placeholder="000000" required autocomplete="one-time-code"
                                class="input mono tracking-widest w-40">
                            @error('confirmCode') <p class="hint hint-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex gap-3">
                            <button type="submit" wire:loading.attr="disabled" wire:target="confirmApp" class="btn btn-primary">
                                <svg wire:loading wire:target="confirmApp" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                </svg>
                                <x-ui.icon wire:loading.remove wire:target="confirmApp" name="check" class="w-4 h-4" />
                                <span wire:loading wire:target="confirmApp">Verifying…</span>
                                <span wire:loading.remove wire:target="confirmApp">Confirm &amp; Enable</span>
                            </button>
                            <button type="button" wire:click="disableTwoFactor" wire:loading.attr="disabled" class="btn btn-secondary">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        {{-- Email OTP: confirm step --}}
        @elseif ($confirmingEmail && ! $emailActive)
            <div class="card max-w-lg">
                <div class="card-header">
                    <h3 class="card-title">Confirm Email OTP</h3>
                </div>
                <form wire:submit="confirmEmail">
                    <div class="card-body space-y-4">
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            A 6-digit code was sent to <strong>{{ auth()->user()->email }}</strong>.
                            Enter it below to enable email two-factor authentication.
                        </p>
                        <div>
                            <label class="label">Verification code *</label>
                            <input wire:model="confirmCode" type="text" inputmode="numeric" pattern="[0-9]*"
                                maxlength="6" placeholder="000000" required autocomplete="one-time-code"
                                class="input mono tracking-widest w-40">
                            @error('confirmCode') <p class="hint hint-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="card-footer gap-3">
                        <button type="submit" wire:loading.attr="disabled" wire:target="confirmEmail" class="btn btn-primary">
                            <svg wire:loading wire:target="confirmEmail" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                            </svg>
                            <x-ui.icon wire:loading.remove wire:target="confirmEmail" name="check" class="w-4 h-4" />
                            <span wire:loading wire:target="confirmEmail">Verifying…</span>
                            <span wire:loading.remove wire:target="confirmEmail">Confirm</span>
                        </button>
                        <button type="button" wire:click="disableTwoFactor" wire:loading.attr="disabled" class="btn btn-secondary">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

        {{-- No 2FA: choose method --}}
        @elseif (! $twoFaOn && ! $showQrCode && ! $confirmingEmail)
            <div class="card max-w-lg">
                <div class="card-header">
                    <h3 class="card-title">Enable Two-Factor Authentication</h3>
                </div>
                <div class="card-body space-y-4">
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        Add an extra layer of security to your account. Choose your preferred method:
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        {{-- Authenticator App card --}}
                        <label class="relative flex cursor-pointer">
                            <input type="radio" wire:model="twoFactorMethod" value="app" class="peer sr-only">
                            <div class="w-full border-2 border-slate-200 dark:border-slate-700 rounded-xl p-4
                                peer-checked:border-simba-600 peer-checked:bg-simba-50 dark:peer-checked:bg-simba-900/20
                                transition-colors">
                                <div class="flex items-center gap-2 mb-1">
                                    <x-ui.icon name="shield" class="w-5 h-5 text-simba-600 dark:text-simba-400" />
                                    <p class="font-semibold text-sm text-slate-800 dark:text-slate-100">Authenticator App</p>
                                </div>
                                <p class="text-xs text-slate-500">Use Google Authenticator, Authy, or any TOTP app.</p>
                            </div>
                        </label>

                        {{-- Email OTP card --}}
                        <label class="relative flex cursor-pointer">
                            <input type="radio" wire:model="twoFactorMethod" value="email" class="peer sr-only">
                            <div class="w-full border-2 border-slate-200 dark:border-slate-700 rounded-xl p-4
                                peer-checked:border-simba-600 peer-checked:bg-simba-50 dark:peer-checked:bg-simba-900/20
                                transition-colors">
                                <div class="flex items-center gap-2 mb-1">
                                    <x-ui.icon name="lock" class="w-5 h-5 text-simba-600 dark:text-simba-400" />
                                    <p class="font-semibold text-sm text-slate-800 dark:text-slate-100">Email OTP</p>
                                </div>
                                <p class="text-xs text-slate-500">Receive a one-time code to your email each time.</p>
                            </div>
                        </label>
                    </div>
                </div>
                <div class="card-footer">
                    <button
                        type="button"
                        wire:click="enableTwoFactor"
                        wire:loading.attr="disabled"
                        wire:target="enableTwoFactor"
                        @disabled(! $twoFactorMethod)
                        class="btn btn-primary"
                    >
                        <svg wire:loading wire:target="enableTwoFactor" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        <x-ui.icon wire:loading.remove wire:target="enableTwoFactor" name="shield" class="w-4 h-4" />
                        <span wire:loading wire:target="enableTwoFactor">Setting up…</span>
                        <span wire:loading.remove wire:target="enableTwoFactor">Enable Two-Factor Auth</span>
                    </button>
                </div>
            </div>
        @endif
    @endif

    {{-- ── TAB: Sessions ───────────────────────────────────────────────── --}}
    @if ($activeTab === 'sessions')
        <div class="card max-w-2xl">
            <div class="card-header">
                <h3 class="card-title">Active Sessions</h3>
                <button type="button" wire:click="$toggle('confirmingLogout')" class="btn btn-secondary btn-sm">
                    <x-ui.icon name="x" class="w-4 h-4" /> Log Out Other Sessions
                </button>
            </div>

            {{-- Logout confirmation with password --}}
            @if ($confirmingLogout)
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 bg-amber-50 dark:bg-amber-900/20">
                    <p class="text-sm font-medium text-amber-800 dark:text-amber-200 mb-3">
                        Enter your password to confirm logging out all other sessions.
                    </p>
                    <div class="flex gap-3 items-start">
                        <div class="flex-1">
                            <input wire:model="sessionPassword" type="password" placeholder="Your password"
                                autocomplete="current-password" class="input">
                            @error('sessionPassword') <p class="hint hint-error">{{ $message }}</p> @enderror
                        </div>
                        <button type="button" wire:click="logoutOtherSessions" class="btn btn-danger btn-sm shrink-0">
                            <x-ui.icon name="x" class="w-4 h-4" /> Confirm Logout
                        </button>
                        <button type="button" wire:click="$set('confirmingLogout', false)" class="btn btn-ghost btn-sm shrink-0">
                            Cancel
                        </button>
                    </div>
                </div>
            @endif

            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($this->sessions as $sess)
                    <div class="list-row flex items-center gap-4 px-6 py-4">
                        <div class="shrink-0 text-slate-400">
                            @if ($sess['device'] === 'Mobile')
                                <x-ui.icon name="user" class="w-5 h-5" />
                            @else
                                <x-ui.icon name="eye" class="w-5 h-5" />
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                    {{ $sess['device'] }}
                                </p>
                                @if ($sess['is_current'])
                                    <span class="badge badge-green text-xs">This device</span>
                                @endif
                            </div>
                            <p class="text-xs text-slate-400 truncate mt-0.5" title="{{ $sess['user_agent'] }}">
                                {{ $sess['ip_address'] }}
                                &mdash;
                                {{ Str::limit($sess['user_agent'], 60) }}
                            </p>
                        </div>
                        <div class="text-xs text-slate-400 shrink-0 text-right">
                            <p>Last active</p>
                            <p class="font-medium">{{ $sess['last_activity']->diffForHumans() }}</p>
                        </div>
                    </div>
                @empty
                    <p class="px-6 py-8 text-center text-slate-400 text-sm">No active sessions found.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
