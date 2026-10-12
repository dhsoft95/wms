<div x-data="{ compact: $persist(false).as('table-density') }" class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">
                Warehouses
                <span class="ml-2 align-middle text-sm font-medium text-slate-400 dark:text-slate-500">{{ $this->warehouses->count() }}</span>
            </h2>
            <p class="page-sub">Manage physical warehouse locations and their active status.</p>
        </div>
        @can('locations.create')
            <a href="{{ route('admin.warehouses.create') }}" wire:navigate class="btn btn-primary">
                <x-ui.icon name="plus" class="w-4 h-4" /> Add Warehouse
            </a>
        @endcan
    </div>

    {{-- Filters --}}
    <div class="card overflow-hidden" :class="compact ? 'density-compact' : ''">

        <div class="flex flex-col gap-3 p-4 lg:flex-row border-b border-white/40 dark:border-white/[0.08]">
            {{-- Search --}}
            <div class="relative flex-1">
                <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    wire:model.live="search"
                    type="search"
                    placeholder="Search by code, name or address…"
                    class="input w-full pl-9"
                />
            </div>

            {{-- Status filter --}}
            <div class="lg:w-44">
                <select wire:model.live="filterStatus" class="input w-full">
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="flex items-center gap-1 tabs shrink-0">
                <button type="button" @click="compact = false" :class="!compact ? 'tab-active' : ''" class="tab text-xs">Comfortable</button>
                <button type="button" @click="compact = true"  :class="compact  ? 'tab-active' : ''" class="tab text-xs">Compact</button>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto" wire:loading.class="opacity-50">
            <table class="table min-w-[640px]">

                <thead>
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Address</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 w-14 sr-only">Actions</th>
                    </tr>
                </thead>

                <tbody wire:loading.remove class="divide-y divide-white/50 dark:divide-white/[0.06]">
                @forelse ($this->warehouses as $warehouse)
                    <tr wire:key="wh-{{ $warehouse->id }}"
                        class="transition hover:bg-white/30 dark:hover:bg-white/[0.04]">

                        {{-- Code --}}
                        <td class="px-4 py-3.5">
                            <span class="badge badge-slate mono">{{ $warehouse->code }}</span>
                        </td>

                        {{-- Name --}}
                        <td class="px-4 py-3.5 font-medium text-slate-900 dark:text-slate-100">
                            {{ $warehouse->name }}
                        </td>

                        {{-- Address --}}
                        <td class="px-4 py-3.5 text-slate-600 dark:text-slate-400 max-w-xs truncate">
                            {{ $warehouse->address ?? '—' }}
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3.5">
                            @if ($warehouse->is_active)
                                <span class="badge badge-green">Active</span>
                            @else
                                <span class="badge badge-slate">Inactive</span>
                            @endif
                        </td>

                        {{-- Actions --}}
                        <td class="px-4 py-3.5">
                            <x-ui.row-actions :label="'Actions for ' . $warehouse->name">
                                @can('locations.create')
                                    <x-ui.row-actions.item icon="pencil" label="Edit" :href="route('admin.warehouses.edit', $warehouse)" wire:navigate />
                                    <x-ui.row-actions.divider />
                                @endcan
                                @if (!$warehouse->is_active)
                                    <x-ui.row-actions.item icon="check" label="Activate" tone="success" wire:click="activate({{ $warehouse->id }})" />
                                @else
                                    <x-ui.row-actions.item icon="x" label="Deactivate" tone="warning" wire:confirm="Deactivate {{ $warehouse->name }}?" wire:click="deactivate({{ $warehouse->id }})" />
                                @endif
                            </x-ui.row-actions>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-16 text-center">
                            <x-ui.icon name="home" class="mx-auto h-8 w-8 text-slate-300 dark:text-slate-600" />
                            <p class="mt-3 font-medium text-slate-900 dark:text-white">No warehouses found</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ $search || $filterStatus ? 'Try adjusting your filters.' : 'Add your first warehouse to get started.' }}
                            </p>
                        </td>
                    </tr>
                @endforelse
                </tbody>

                <tbody wire:loading class="divide-y divide-white/50 dark:divide-white/[0.06]">
                    @for ($i = 0; $i < 5; $i++)
                        <tr class="skeleton-row">
                            <td class="px-4"><div class="skeleton-cell w-16"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-36"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-48"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-14"></div></td>
                            <td class="px-4 w-14"><div class="skeleton-cell w-8 ml-auto"></div></td>
                        </tr>
                    @endfor
                </tbody>

            </table>
        </div>

    </div>

</div>
