<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div class="flex items-center gap-4">
            {{-- Avatar --}}
            <div class="w-12 h-12 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center flex-shrink-0">
                <span class="text-base font-bold text-slate-600 dark:text-slate-300 mono">{{ $user->initials() }}</span>
            </div>
            <div>
                <h2 class="page-title">{{ $user->name }}</h2>
                <p class="page-sub mono">{{ $user->email }}</p>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center gap-2">
            @can('users.manage')
                <a href="{{ route('admin.users.edit', $user) }}" wire:navigate class="btn btn-secondary btn-sm">
                    <x-ui.icon name="cog" class="w-4 h-4" /> Edit
                </a>

                @if ($user->isActive())
                    <button
                        wire:click="suspend"
                        wire:confirm="Suspend {{ $user->name }}? They will no longer be able to log in."
                        class="btn btn-secondary btn-sm text-amber-600"
                    >
                        <x-ui.icon name="x" class="w-4 h-4" /> Suspend
                    </button>
                @elseif ($user->isSuspended())
                    <button wire:click="activate" class="btn btn-secondary btn-sm text-green-600">
                        <x-ui.icon name="check" class="w-4 h-4" /> Activate
                    </button>
                @endif

                @if ($user->isInvited())
                    <button wire:click="resendInvitation" class="btn btn-secondary btn-sm">
                        <x-ui.icon name="refresh" class="w-4 h-4" /> Resend Invitation
                    </button>
                @endif

                @if ($user->hasTwoFactorEnabled())
                    <button
                        wire:click="resetTwoFactor"
                        wire:confirm="Reset 2FA for {{ $user->name }}? They will need to set it up again."
                        class="btn btn-secondary btn-sm text-red-500"
                    >
                        <x-ui.icon name="shield" class="w-4 h-4" /> Reset 2FA
                    </button>
                @endif
            @endcan
        </div>
    </div>

    {{-- Two-column layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left: Profile Card --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <x-ui.icon name="user" class="w-4 h-4" /> Profile
                    </h3>
                    {{-- Status badge --}}
                    @if ($user->isActive())
                        <span class="badge badge-green ml-auto">Active</span>
                    @elseif ($user->isInvited())
                        <span class="badge badge-amber ml-auto">Invited</span>
                    @elseif ($user->isSuspended())
                        <span class="badge badge-red ml-auto">Suspended</span>
                    @endif
                </div>

                <ul>
                    <li class="list-row">
                        <span class="section-label">Name</span>
                        <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $user->name }}</span>
                    </li>

                    <li class="list-row">
                        <span class="section-label">Email</span>
                        <span class="text-sm mono text-slate-600 dark:text-slate-300">{{ $user->email }}</span>
                        @if ($user->email_verified_at)
                            <span class="badge badge-green ml-2 text-xs">Verified</span>
                        @else
                            <span class="badge badge-amber ml-2 text-xs">Unverified</span>
                        @endif
                    </li>

                    <li class="list-row">
                        <span class="section-label">Phone</span>
                        <span class="text-sm text-slate-600 dark:text-slate-300">
                            {{ $user->phone ?? '—' }}
                        </span>
                    </li>

                    <li class="list-row">
                        <span class="section-label">Role</span>
                        @if ($user->roleName())
                            <span class="badge badge-slate">{{ $user->roleName() }}</span>
                        @else
                            <span class="text-slate-400 text-sm">No role assigned</span>
                        @endif
                    </li>

                    <li class="list-row">
                        <span class="section-label">Department</span>
                        <span class="text-sm text-slate-600 dark:text-slate-300">
                            {{ $user->department?->name ?? '—' }}
                            @if ($user->department)
                                <span class="text-slate-400 mono text-xs ml-1">({{ $user->department->code }})</span>
                            @endif
                        </span>
                    </li>
                </ul>
            </div>

            {{-- Security Card --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <x-ui.icon name="shield" class="w-4 h-4" /> Security
                    </h3>
                </div>
                <ul>
                    <li class="list-row">
                        <span class="section-label">Two-Factor Auth</span>
                        <div class="flex items-center gap-2">
                            @if ($user->hasTwoFactorEnabled())
                                <span class="badge badge-green">Enabled</span>
                                <span class="text-xs text-slate-400 mono">
                                    {{ $user->two_factor_method === 'app' ? 'Authenticator App' : 'Email OTP' }}
                                </span>
                                @if ($user->two_factor_confirmed_at)
                                    <span class="text-xs text-slate-400">· confirmed {{ $user->two_factor_confirmed_at->diffForHumans() }}</span>
                                @endif
                            @else
                                <span class="badge badge-slate">Disabled</span>
                            @endif
                        </div>
                    </li>

                    <li class="list-row">
                        <span class="section-label">2FA Required</span>
                        <span class="text-sm text-slate-600 dark:text-slate-300">
                            {{ $user->require_two_factor ? 'Yes' : 'No' }}
                        </span>
                    </li>

                    <li class="list-row">
                        <span class="section-label">Last Login</span>
                        <div>
                            @if ($user->last_login_at)
                                <span class="text-sm text-slate-700 dark:text-slate-200">{{ $user->last_login_at->diffForHumans() }}</span>
                                @if ($user->last_login_ip)
                                    <span class="text-xs text-slate-400 mono ml-2">{{ $user->last_login_ip }}</span>
                                @endif
                            @else
                                <span class="text-sm text-slate-400">Never logged in</span>
                            @endif
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Right: Activity Panel --}}
        <div class="space-y-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <x-ui.icon name="arrows" class="w-4 h-4" /> Account Timeline
                    </h3>
                </div>
                <ul>
                    <li class="list-row flex-col items-start gap-0.5">
                        <span class="section-label">Account Created</span>
                        <span class="text-sm text-slate-700 dark:text-slate-200">{{ $user->created_at->format('d M Y, H:i') }}</span>
                        <span class="text-xs text-slate-400">{{ $user->created_at->diffForHumans() }}</span>
                    </li>

                    <li class="list-row flex-col items-start gap-0.5">
                        <span class="section-label">Invitation Sent</span>
                        @if ($user->invitation_sent_at)
                            <span class="text-sm text-slate-700 dark:text-slate-200">{{ $user->invitation_sent_at->format('d M Y, H:i') }}</span>
                            <span class="text-xs text-slate-400">{{ $user->invitation_sent_at->diffForHumans() }}</span>
                        @else
                            <span class="text-sm text-slate-400">Not sent</span>
                        @endif
                    </li>

                    <li class="list-row flex-col items-start gap-0.5">
                        <span class="section-label">Invited By</span>
                        @if ($user->invitedBy)
                            <div class="flex items-center gap-2 mt-0.5">
                                <div class="w-5 h-5 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center flex-shrink-0">
                                    <span class="text-xs font-semibold text-slate-500 mono">{{ $user->invitedBy->initials() }}</span>
                                </div>
                                <span class="text-sm text-slate-700 dark:text-slate-200">{{ $user->invitedBy->name }}</span>
                            </div>
                        @else
                            <span class="text-sm text-slate-400">—</span>
                        @endif
                    </li>

                    <li class="list-row flex-col items-start gap-0.5">
                        <span class="section-label">Email Verified</span>
                        @if ($user->email_verified_at)
                            <span class="text-sm text-slate-700 dark:text-slate-200">{{ $user->email_verified_at->format('d M Y, H:i') }}</span>
                            <span class="text-xs text-slate-400">{{ $user->email_verified_at->diffForHumans() }}</span>
                        @else
                            <span class="text-sm text-amber-600">Not verified</span>
                        @endif
                    </li>

                    <li class="list-row flex-col items-start gap-0.5">
                        <span class="section-label">Last Login</span>
                        @if ($user->last_login_at)
                            <span class="text-sm text-slate-700 dark:text-slate-200">{{ $user->last_login_at->format('d M Y, H:i') }}</span>
                            <span class="text-xs text-slate-400">{{ $user->last_login_at->diffForHumans() }}</span>
                        @else
                            <span class="text-sm text-slate-400">Never</span>
                        @endif
                    </li>
                </ul>
            </div>

            {{-- Back link --}}
            <a href="{{ route('admin.users.index') }}" wire:navigate class="btn btn-ghost btn-sm w-full justify-center">
                <x-ui.icon name="arrows" class="w-4 h-4" /> Back to Users
            </a>
        </div>

    </div>

</div>
