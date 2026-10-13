<div class="space-y-6" x-data="{ compact: $persist(false).as('table-density') }">

    {{-- Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">
                User Management
                <span class="ml-2 align-middle text-sm font-medium text-slate-400 dark:text-slate-500">{{ $this->users->total() }}</span>
            </h2>
            <p class="page-sub">Manage system access, roles, and warehouse assignments.</p>
        </div>

        @can('users.manage')
            <a href="{{ route('admin.users.create') }}" wire:navigate class="btn btn-primary">
                <x-ui.icon name="plus" class="h-4 w-4" />
                Add User
            </a>
        @endcan
    </div>

    {{-- card:has(table) auto-upgrades to glass-solid tier (white/85 blur-16 light, zinc-950/85 dark) --}}
    <div class="card overflow-hidden" :class="compact ? 'density-compact' : ''">

        {{-- Filters --}}
        <div class="flex flex-col gap-3 p-4 lg:flex-row
                    border-b border-white/40 dark:border-white/[0.08]">
            <div class="relative flex-1">
                <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    placeholder="Search by name or email"
                    class="input w-full pl-9"
                />
            </div>

            <select wire:model.live="filterRole" class="input lg:w-44">
                <option value="">All roles</option>
                <option value="system-admin">System Admin</option>
                <option value="warehouse-manager">Warehouse Manager</option>
                <option value="department-user">Department User</option>
                <option value="storekeeper">Storekeeper</option>
                <option value="scanner-operator">Scanner Operator</option>
                <option value="viewer-auditor">Viewer / Auditor</option>
            </select>

            <select wire:model.live="filterVw" class="input lg:w-44">
                <option value="">All warehouses</option>
                @foreach ($this->virtualWarehouses as $vw)
                    <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterStatus" class="input lg:w-36">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="invited">Invited</option>
                <option value="suspended">Suspended</option>
            </select>

            <div class="flex items-center gap-1 tabs shrink-0">
                <button type="button" @click="compact = false" :class="!compact ? 'tab-active' : ''" class="tab text-xs">Comfortable</button>
                <button type="button" @click="compact = true"  :class="compact  ? 'tab-active' : ''" class="tab text-xs">Compact</button>
            </div>
        </div>

        {{-- Table --}}
        <div wire:loading.class="opacity-50">
            <x-ui.table class="min-w-[900px]">

                <thead>
                    <tr>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Department</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Last Login</th>
                        <th class="px-4 py-3 w-14 sr-only">Actions</th>
                    </tr>
                </thead>

                <tbody wire:loading.remove class="divide-y divide-white/50 dark:divide-white/[0.06]">
                @forelse ($this->users as $user)
                    <tr wire:key="user-{{ $user->id }}"
                        class="transition hover:bg-white/30 dark:hover:bg-white/[0.04]">

                        {{-- User --}}
                        <td class="px-4 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full
                                            bg-white/50 dark:bg-white/[0.08]
                                            ring-1 ring-white/60 dark:ring-white/[0.12]
                                            text-xs font-semibold text-slate-600 dark:text-slate-300">
                                    {{ $user->initials() }}
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <a href="{{ route('admin.users.show', $user) }}" wire:navigate
                                           class="truncate font-medium text-slate-900 dark:text-slate-100
                                                  hover:text-simba-600 dark:hover:text-simba-400 transition-colors">
                                            {{ $user->name }}
                                        </a>
                                        @if ($user->hasTwoFactorEnabled())
                                            <span title="2FA enabled"
                                                  class="rounded bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-semibold
                                                         text-emerald-700 dark:text-emerald-400
                                                         ring-1 ring-emerald-500/25">
                                                2FA
                                            </span>
                                        @endif
                                    </div>
                                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $user->email }}</p>
                                </div>
                            </div>
                        </td>

                        {{-- Role --}}
                        <td class="px-4 py-3.5 text-slate-700 dark:text-slate-300">
                            {{ $user->roleName() ?? '—' }}
                        </td>

                        {{-- Department / VWs --}}
                        <td class="px-4 py-3.5">
                            <p class="text-slate-700 dark:text-slate-300">{{ $user->department?->name ?? '—' }}</p>
                            @if ($user->virtualWarehouses->isNotEmpty())
                                <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">
                                    {{ $user->virtualWarehouses->pluck('code')->join(', ') }}
                                </p>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3.5">
                            @if ($user->isActive())
                                <span class="badge badge-green">Active</span>
                            @elseif ($user->isInvited())
                                <span class="badge badge-amber">Invited</span>
                            @elseif ($user->isSuspended())
                                <span class="badge badge-red">Suspended</span>
                            @endif
                        </td>

                        {{-- Last Login --}}
                        <td class="whitespace-nowrap px-4 py-3.5 text-slate-500 dark:text-slate-400">
                            {{ $user->last_login_at?->diffForHumans() ?? 'Never' }}
                        </td>

                        {{-- Actions --}}
                        <td class="px-4 py-3.5 w-14">
                            <x-ui.row-actions :label="'Actions for ' . $user->name">

                                {{-- View (always) --}}
                                <x-ui.row-actions.item
                                    icon="eye"
                                    label="View"
                                    :href="route('admin.users.show', $user)"
                                    wire:navigate
                                />

                                @can('users.manage')
                                    {{-- Edit --}}
                                    <x-ui.row-actions.item
                                        icon="pencil"
                                        label="Edit"
                                        :href="route('admin.users.edit', $user)"
                                        wire:navigate
                                    />

                                    {{-- Resend Invitation (invited users only) --}}
                                    @if ($user->isInvited())
                                        <x-ui.row-actions.item
                                            icon="refresh"
                                            label="Resend Invite"
                                            tone="info"
                                            wire:click="resendInvitation({{ $user->id }})"
                                        />
                                    @endif

                                    <x-ui.row-actions.divider />

                                    {{-- Activate (suspended users only) --}}
                                    @if ($user->isSuspended())
                                        <x-ui.row-actions.item
                                            icon="check"
                                            label="Activate"
                                            tone="success"
                                            wire:click="activate({{ $user->id }})"
                                        />
                                    @endif

                                    {{-- Suspend (active users only) --}}
                                    @if ($user->isActive())
                                        <x-ui.row-actions.item
                                            icon="x"
                                            label="Suspend"
                                            tone="warning"
                                            wire:confirm="Suspend {{ $user->name }}? They will no longer be able to log in."
                                            wire:click="suspend({{ $user->id }})"
                                        />
                                    @endif

                                    <x-ui.row-actions.divider />

                                    {{-- Delete --}}
                                    <x-ui.row-actions.item
                                        icon="trash"
                                        label="Delete"
                                        tone="danger"
                                        wire:click="$set('confirmingDelete', {{ $user->id }})"
                                    />
                                @endcan

                            </x-ui.row-actions>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-16 text-center">
                            <x-ui.icon name="users" class="mx-auto h-8 w-8 text-slate-300 dark:text-slate-600" />
                            @if ($search || $filterRole || $filterVw || $filterStatus)
                                <p class="mt-3 font-medium text-slate-900 dark:text-white">No users match your filters</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    <button wire:click="$set('search', ''); $set('filterRole', ''); $set('filterVw', ''); $set('filterStatus', '')"
                                            class="text-simba-600 dark:text-simba-400 hover:underline">
                                        Clear filters
                                    </button>
                                </p>
                            @else
                                <p class="mt-3 font-medium text-slate-900 dark:text-white">No users yet</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Get started by adding your first user.</p>
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>

                <tbody wire:loading class="divide-y divide-white/50 dark:divide-white/[0.06]">
                    @for ($i = 0; $i < 5; $i++)
                        <tr class="skeleton-row">
                            <td class="px-4"><div class="skeleton-cell w-32"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-48"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-24"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-16"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-16"></div></td>
                            <td class="px-4 w-14"><div class="skeleton-cell w-8 ml-auto"></div></td>
                        </tr>
                    @endfor
                </tbody>

            </x-ui.table>
        </div>

        {{-- Pagination --}}
        @if ($this->users->hasPages())
            <div class="px-4 py-3 border-t border-white/40 dark:border-white/[0.08]">
                {{ $this->users->links() }}
            </div>
        @endif

    </div>

</div>
