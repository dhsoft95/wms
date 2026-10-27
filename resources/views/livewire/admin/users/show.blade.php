<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-col gap-5 pb-6 sm:flex-row sm:items-center sm:justify-between
                border-b border-white/40 dark:border-white/[0.08]">

        <div class="flex min-w-0 items-center gap-4">
            {{-- Avatar --}}
            <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full
                        bg-simba-50 dark:bg-simba-500/20
                        text-simba-700 dark:text-simba-300
                        ring-4 ring-white/70 dark:ring-white/[0.08] shadow-sm">
                <span class="text-lg font-bold tracking-wide mono">{{ $user->initials() }}</span>
            </div>

            <div class="min-w-0">
                <h2 class="truncate text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                    {{ $user->name }}
                </h2>
                <p class="mt-1 truncate text-sm text-slate-500 dark:text-slate-400 mono">
                    {{ $user->email }}
                </p>
                <div class="mt-2">
                    @if ($user->isActive())
                        <span class="badge badge-green">
                            <span class="dot"></span> Active
                        </span>
                    @elseif ($user->isInvited())
                        <span class="badge badge-amber">
                            <span class="dot"></span> Invited
                        </span>
                    @elseif ($user->isSuspended())
                        <span class="badge badge-red">
                            <span class="dot"></span> Suspended
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Header Actions --}}
        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
            <a href="{{ route('admin.users.index') }}" wire:navigate class="btn btn-secondary btn-sm">
                <x-ui.icon name="arrows" class="h-4 w-4" /> Back
            </a>

            @can('users.manage')
                <a href="{{ route('admin.users.edit', $user) }}" wire:navigate class="btn btn-secondary">
                    <x-ui.icon name="cog" class="h-4 w-4" /> Edit Profile
                </a>

                @if ($user->isActive())
                    <button wire:click="suspend"
                            wire:confirm="Suspend {{ $user->name }}? They will no longer be able to log in."
                            class="btn btn-primary">
                        <x-ui.icon name="x" class="h-4 w-4" /> Suspend Account
                    </button>
                @elseif ($user->isSuspended())
                    <button wire:click="activate" class="btn btn-success">
                        <x-ui.icon name="check" class="h-4 w-4" /> Activate Account
                    </button>
                @endif

                @if ($user->isInvited())
                    <button wire:click="resendInvitation" class="btn btn-secondary">
                        <x-ui.icon name="refresh" class="h-4 w-4" /> Resend Invitation
                    </button>
                @endif

                @if ($user->hasTwoFactorEnabled())
                    <button wire:click="resetTwoFactor"
                            wire:confirm="Reset 2FA for {{ $user->name }}? They will need to set it up again."
                            class="btn btn-danger">
                        <x-ui.icon name="shield" class="h-4 w-4" /> Reset 2FA
                    </button>
                @endif

                <button wire:click="openPasswordModal" class="btn btn-secondary">
                    <x-ui.icon name="lock-closed" class="h-4 w-4" /> Set Password
                </button>
            @endcan
        </div>
    </div>


    {{-- Main Content: Two Columns --}}
    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">

        {{-- LEFT COLUMN --}}
        <div class="space-y-6 lg:col-span-2">

            {{-- Profile Card --}}
            <section class="card overflow-hidden">
                <div class="card-header sm:px-7 py-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl
                                    bg-simba-50 dark:bg-simba-500/15
                                    text-simba-600 dark:text-simba-400">
                            <x-ui.icon name="user" class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">Profile</h3>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Personal information and account details</p>
                        </div>
                    </div>

                    @if ($user->isActive())
                        <span class="badge badge-green shrink-0">
                            <span class="dot"></span> Active
                        </span>
                    @elseif ($user->isInvited())
                        <span class="badge badge-amber shrink-0">Invited</span>
                    @elseif ($user->isSuspended())
                        <span class="badge badge-red shrink-0">Suspended</span>
                    @endif
                </div>

                <dl class="divide-y divide-white/50 dark:divide-white/[0.06] px-5 sm:px-7">

                    <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                        <dt class="section-label">Name</dt>
                        <dd class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $user->name }}</dd>
                    </div>

                    <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                        <dt class="section-label">Email Address</dt>
                        <dd class="flex min-w-0 flex-wrap items-center justify-between gap-3">
                            <span class="break-all text-sm text-slate-700 dark:text-slate-300 mono">{{ $user->email }}</span>
                            @if ($user->email_verified_at)
                                <span class="badge badge-green shrink-0">
                                    <x-ui.icon name="check" class="h-3 w-3" /> Verified
                                </span>
                            @else
                                <span class="badge badge-amber shrink-0">Unverified</span>
                            @endif
                        </dd>
                    </div>

                    <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                        <dt class="section-label">Phone</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300 mono">{{ $user->phone ?? '—' }}</dd>
                    </div>

                    <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                        <dt class="section-label">Role</dt>
                        <dd>
                            @if ($user->roleName())
                                <span class="badge badge-slate">{{ $user->roleName() }}</span>
                            @else
                                <span class="text-sm text-slate-400 dark:text-slate-500">No role assigned</span>
                            @endif
                        </dd>
                    </div>

                    <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                        <dt class="section-label">Department</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">
                            {{ $user->department?->name ?? '—' }}
                            @if ($user->department)
                                <span class="ml-1 text-xs text-slate-400 dark:text-slate-500 mono">({{ $user->department->code }})</span>
                            @endif
                        </dd>
                    </div>

                </dl>
            </section>


            {{-- Security Card --}}
            <section class="card overflow-hidden">
                <div class="card-header sm:px-7 py-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl
                                    bg-simba-50 dark:bg-simba-500/15
                                    text-simba-600 dark:text-simba-400">
                            <x-ui.icon name="shield" class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">Security</h3>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Authentication and sign-in information</p>
                        </div>
                    </div>
                </div>

                <dl class="divide-y divide-white/50 dark:divide-white/[0.06] px-5 sm:px-7">

                    <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                        <dt class="section-label">Two-Factor Auth</dt>
                        <dd class="flex flex-wrap items-center gap-2">
                            @if ($user->hasTwoFactorEnabled())
                                <span class="badge badge-green">Enabled</span>
                                <span class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $user->two_factor_method === 'app' ? 'Authenticator App' : 'Email OTP' }}
                                </span>
                                @if ($user->two_factor_confirmed_at)
                                    <span class="text-xs text-slate-400 dark:text-slate-500">
                                        Confirmed {{ $user->two_factor_confirmed_at->diffForHumans() }}
                                    </span>
                                @endif
                            @else
                                <span class="badge badge-slate">Disabled</span>
                            @endif
                        </dd>
                    </div>

                    <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                        <dt class="section-label">2FA Required</dt>
                        <dd class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ $user->require_two_factor ? 'Yes' : 'No' }}
                        </dd>
                    </div>

                    <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                        <dt class="section-label">Last Login</dt>
                        <dd>
                            @if ($user->last_login_at)
                                <p class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                    {{ $user->last_login_at->diffForHumans() }}
                                </p>
                                @if ($user->last_login_ip)
                                    <p class="mt-1 text-xs text-slate-400 dark:text-slate-500 mono">
                                        IP: {{ $user->last_login_ip }}
                                    </p>
                                @endif
                            @else
                                <span class="text-sm text-slate-400 dark:text-slate-500">Never logged in</span>
                            @endif
                        </dd>
                    </div>

                </dl>
            </section>

        </div>


        {{-- RIGHT COLUMN: Account Timeline --}}
        <aside class="space-y-5">

            <section class="card overflow-hidden">
                <div class="card-header py-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl
                                    bg-simba-50 dark:bg-simba-500/15
                                    text-simba-600 dark:text-simba-400">
                            <x-ui.icon name="arrows" class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">Account Timeline</h3>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Important account events</p>
                        </div>
                    </div>
                </div>

                <ol class="divide-y divide-white/50 dark:divide-white/[0.06] px-5">

                    {{-- Account Created --}}
                    <li class="flex gap-3 py-5">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl
                                    bg-white/50 dark:bg-white/[0.06] text-slate-500 dark:text-slate-400">
                            <x-ui.icon name="refresh" class="h-5 w-5" />
                            <x-ui.icon name="user-round-plus" class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="section-label">Account Created</p>
                            <p class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-200">
                                {{ $user->created_at->format('d M Y, H:i') }}
                            </p>
                            <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">
                                {{ $user->created_at->diffForHumans() }}
                            </p>
                        </div>
                    </li>

                    {{-- Invitation Sent --}}
                    <li class="flex gap-3 py-5">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl
                                    bg-white/50 dark:bg-white/[0.06] text-slate-500 dark:text-slate-400">
                            <x-ui.icon name="refresh" class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="section-label">Invitation Sent</p>
                            @if ($user->invitation_sent_at)
                                <p class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-200">
                                    {{ $user->invitation_sent_at->format('d M Y, H:i') }}
                                </p>
                                <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">
                                    {{ $user->invitation_sent_at->diffForHumans() }}
                                </p>
                            @else
                                <p class="mt-1 text-sm text-slate-400 dark:text-slate-500">Not sent</p>
                            @endif
                        </div>
                    </li>

                    {{-- Invited By --}}
                    <li class="flex gap-3 py-5">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl
                                    bg-white/50 dark:bg-white/[0.06] text-slate-500 dark:text-slate-400">
                            <x-ui.icon name="user" class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="section-label">Invited By</p>
                            @if ($user->invitedBy)
                                <div class="mt-2 flex items-center gap-2.5">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full
                                                bg-simba-50 dark:bg-simba-500/20
                                                text-xs font-bold text-simba-700 dark:text-simba-300">
                                        {{ $user->invitedBy->initials() }}
                                    </div>
                                    <span class="break-words text-sm font-medium text-slate-800 dark:text-slate-200">
                                        {{ $user->invitedBy->name }}
                                    </span>
                                </div>
                            @else
                                <p class="mt-1 text-sm text-slate-400 dark:text-slate-500">—</p>
                            @endif
                        </div>
                    </li>

                    {{-- Email Verified --}}
                    <li class="flex gap-3 py-5">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl
                                    bg-white/50 dark:bg-white/[0.06] text-slate-500 dark:text-slate-400">
                            <x-ui.icon name="check" class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="section-label">Email Verified</p>
                            @if ($user->email_verified_at)
                                <p class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-200">
                                    {{ $user->email_verified_at->format('d M Y, H:i') }}
                                </p>
                                <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">
                                    {{ $user->email_verified_at->diffForHumans() }}
                                </p>
                            @else
                                <p class="mt-1 text-sm font-medium text-amber-600 dark:text-amber-400">Not verified</p>
                            @endif
                        </div>
                    </li>

                    {{-- Last Login --}}
                    <li class="flex gap-3 py-5">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl
                                    bg-white/50 dark:bg-white/[0.06] text-slate-500 dark:text-slate-400">
                            <x-ui.icon name="user" class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="section-label">Last Login</p>
                            @if ($user->last_login_at)
                                <p class="mt-1 text-sm font-medium text-slate-800 dark:text-slate-200">
                                    {{ $user->last_login_at->format('d M Y, H:i') }}
                                </p>
                                <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">
                                    {{ $user->last_login_at->diffForHumans() }}
                                </p>
                            @else
                                <p class="mt-1 text-sm text-slate-400 dark:text-slate-500">Never</p>
                            @endif
                        </div>
                    </li>
                </ol>
            </section>
        </aside>
    </div>

    {{-- Set Password Modal --}}
    @if ($showPasswordModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm p-4"
             x-data wire:click.self="closePasswordModal">
            <div class="card w-full max-w-sm p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-slate-900 dark:text-white">Set Password</h3>
                    <button wire:click="closePasswordModal" class="icon-btn">
                        <x-ui.icon name="x" class="w-5 h-5" />
                    </button>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Setting a new password for <strong>{{ $user->name }}</strong>.
                    They will be able to log in with it immediately.
                </p>
                <div class="space-y-3">
                    <div>
                        <label class="label">New Password <span class="required-mark">*</span></label>
                        <input wire:model="newPassword" type="password" class="input"
                               placeholder="Min. 8 characters" autocomplete="new-password" />
                        @error('newPassword') <p class="hint hint-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Confirm Password <span class="required-mark">*</span></label>
                        <input wire:model="newPasswordConfirmation" type="password" class="input"
                               placeholder="Repeat password" autocomplete="new-password" />
                        @error('newPasswordConfirmation') <p class="hint hint-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button wire:click="closePasswordModal" class="btn btn-ghost">Cancel</button>
                    <button wire:click="updatePassword" wire:loading.attr="disabled" class="btn btn-primary">
                        <span wire:loading.remove wire:target="updatePassword">Save Password</span>
                        <span wire:loading wire:target="updatePassword">Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
