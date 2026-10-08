<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">User Management</h2>
            <p class="page-sub">Manage system access, roles, and permissions for all users.</p>
        </div>
        @can('users.manage')
            <a href="{{ route('admin.users.create') }}" wire:navigate class="btn btn-primary">
                <x-ui.icon name="plus" class="w-4 h-4" /> New User
            </a>
        @endcan
    </div>

    {{-- Filter Bar --}}
    <div class="card">
        <div class="card-header flex-wrap gap-3">
            <div class="relative flex-1 min-w-48">
                <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 pointer-events-none">
                    <x-ui.icon name="search" class="w-4 h-4" />
                </span>
                <input
                    wire:model.live="search"
                    type="search"
                    placeholder="Search name or email…"
                    class="input pl-9 w-full"
                />
            </div>

            <select wire:model.live="filterRole" class="input input-sm w-44">
                <option value="">All Roles</option>
                <option value="system-admin">System Admin</option>
                <option value="warehouse-manager">Warehouse Manager</option>
                <option value="department-user">Department User</option>
                <option value="storekeeper">Storekeeper</option>
                <option value="scanner-operator">Scanner Operator</option>
                <option value="viewer-auditor">Viewer / Auditor</option>
            </select>

            <select wire:model.live="filterDepartment" class="input input-sm w-44">
                <option value="">All Departments</option>
                @foreach ($this->departments as $dept)
                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterStatus" class="input input-sm w-36">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="invited">Invited</option>
                <option value="suspended">Suspended</option>
            </select>

            <span class="badge badge-slate ml-auto">{{ $this->users->total() }} users</span>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th class="text-center">2FA</th>
                        <th>Last Login</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->users as $user)
                        <tr>
                            {{-- Avatar + Name --}}
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center flex-shrink-0">
                                        <span class="text-xs font-semibold text-slate-600 dark:text-slate-300 mono">{{ $user->initials() }}</span>
                                    </div>
                                    <div>
                                        <a href="{{ route('admin.users.show', $user) }}" wire:navigate class="font-semibold text-slate-800 dark:text-slate-100 hover:underline">
                                            {{ $user->name }}
                                        </a>
                                        @if ($user->phone)
                                            <p class="text-xs text-slate-400">{{ $user->phone }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- Email --}}
                            <td class="text-slate-500 text-sm mono">{{ $user->email }}</td>

                            {{-- Role --}}
                            <td>
                                @if ($user->roleName())
                                    <span class="badge badge-slate text-xs">{{ $user->roleName() }}</span>
                                @else
                                    <span class="text-slate-400 text-xs">—</span>
                                @endif
                            </td>

                            {{-- Department --}}
                            <td class="text-sm text-slate-500">
                                {{ $user->department?->name ?? '—' }}
                            </td>

                            {{-- Status --}}
                            <td>
                                @if ($user->isActive())
                                    <span class="badge badge-green">Active</span>
                                @elseif ($user->isInvited())
                                    <span class="badge badge-amber">Invited</span>
                                @elseif ($user->isSuspended())
                                    <span class="badge badge-red">Suspended</span>
                                @endif
                            </td>

                            {{-- 2FA --}}
                            <td class="text-center">
                                @if ($user->hasTwoFactorEnabled())
                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-green-100 dark:bg-green-900/30" title="{{ $user->two_factor_method }}">
                                        <x-ui.icon name="check" class="w-3 h-3 text-green-600 dark:text-green-400" />
                                    </span>
                                @else
                                    <span class="inline-block w-2 h-2 rounded-full bg-slate-200 dark:bg-slate-600" title="Disabled"></span>
                                @endif
                            </td>

                            {{-- Last Login --}}
                            <td class="text-xs text-slate-400">
                                {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}
                            </td>

                            {{-- Actions --}}
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.users.show', $user) }}" wire:navigate class="btn btn-ghost btn-sm" title="View">
                                        <x-ui.icon name="eye" class="w-4 h-4" />
                                    </a>
                                    @can('users.manage')
                                        <a href="{{ route('admin.users.edit', $user) }}" wire:navigate class="btn btn-ghost btn-sm" title="Edit">
                                            <x-ui.icon name="cog" class="w-4 h-4" />
                                        </a>

                                        @if ($user->isActive())
                                            <button
                                                wire:click="suspend({{ $user->id }})"
                                                wire:confirm="Suspend {{ $user->name }}? They will no longer be able to log in."
                                                class="btn btn-ghost btn-sm text-amber-600"
                                                title="Suspend"
                                            >
                                                <x-ui.icon name="x" class="w-4 h-4" />
                                            </button>
                                        @elseif ($user->isSuspended())
                                            <button
                                                wire:click="activate({{ $user->id }})"
                                                class="btn btn-ghost btn-sm text-green-600"
                                                title="Activate"
                                            >
                                                <x-ui.icon name="check" class="w-4 h-4" />
                                            </button>
                                        @endif

                                        @if ($user->isInvited())
                                            <button
                                                wire:click="resendInvitation({{ $user->id }})"
                                                class="btn btn-ghost btn-sm text-simba-600"
                                                title="Resend invitation"
                                            >
                                                <x-ui.icon name="refresh" class="w-4 h-4" />
                                            </button>
                                        @endif

                                        {{-- Delete confirm --}}
                                        @if ($confirmingDelete === $user->id)
                                            <span class="text-xs text-slate-500 mr-1">Sure?</span>
                                            <button wire:click="deleteUser({{ $user->id }})" class="btn btn-danger btn-sm">Yes, delete</button>
                                            <button wire:click="$set('confirmingDelete', null)" class="btn btn-ghost btn-sm">Cancel</button>
                                        @else
                                            <button wire:click="$set('confirmingDelete', {{ $user->id }})" class="btn btn-ghost btn-sm text-red-500" title="Delete">
                                                <x-ui.icon name="trash" class="w-4 h-4" />
                                            </button>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-400">
                                <x-ui.icon name="users" class="w-8 h-8 mx-auto mb-2 opacity-40" />
                                <p class="text-sm">No users found matching your filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->users->hasPages())
            <div class="card-footer justify-center">
                {{ $this->users->links() }}
            </div>
        @endif
    </div>

</div>
