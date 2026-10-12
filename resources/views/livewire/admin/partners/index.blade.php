<div x-data="{ compact: $persist(false).as('table-density') }" class="space-y-6">

    {{-- Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">
                Partners
                <span class="ml-2 align-middle text-sm font-medium text-slate-400 dark:text-slate-500">{{ $this->partners->total() }}</span>
            </h2>
            <p class="page-sub">Manage suppliers, shippers, consignees, customers, and other trading partners.</p>
        </div>

        @can('partners.create')
            <a href="{{ route('admin.partners.create') }}" wire:navigate class="btn btn-primary">
                <x-ui.icon name="plus" class="h-4 w-4" />
                Add Partner
            </a>
        @endcan
    </div>

    {{-- card:has(table) auto-upgrades to glass-solid tier --}}
    <div class="card overflow-hidden" :class="compact ? 'density-compact' : ''">

        {{-- Filters --}}
        <div class="flex flex-col gap-3 p-4 lg:flex-row
                    border-b border-white/40 dark:border-white/[0.08]">
            <div class="relative flex-1">
                <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    placeholder="Search by code, name or email"
                    class="input w-full pl-9"
                />
            </div>

            <select wire:model.live="filterType" class="input lg:w-48">
                <option value="">All Types</option>
                <option value="supplier">Supplier</option>
                <option value="shipper">Shipper</option>
                <option value="consignee">Consignee</option>
                <option value="customer">Customer</option>
                <option value="shipping_line">Shipping Line</option>
                <option value="transporter">Transporter</option>
            </select>

            <select wire:model.live="filterStatus" class="input lg:w-40">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>

            <div class="flex items-center gap-1 tabs shrink-0">
                <button type="button" @click="compact = false" :class="!compact ? 'tab-active' : ''" class="tab text-xs">Comfortable</button>
                <button type="button" @click="compact = true"  :class="compact  ? 'tab-active' : ''" class="tab text-xs">Compact</button>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto" wire:loading.class="opacity-50">
            <table class="table min-w-[800px]">

                <thead>
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Type(s)</th>
                        <th class="px-4 py-3">TIN</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 w-14 sr-only">Actions</th>
                    </tr>
                </thead>

                <tbody wire:loading.remove class="divide-y divide-white/50 dark:divide-white/[0.06]">
                @forelse ($this->partners as $partner)
                    <tr wire:key="partner-{{ $partner->id }}"
                        class="transition hover:bg-white/30 dark:hover:bg-white/[0.04]">

                        {{-- Code --}}
                        <td class="px-4 py-3.5">
                            <a href="{{ route('admin.partners.show', $partner) }}" wire:navigate
                               class="font-mono text-xs font-semibold text-simba-700 dark:text-simba-400
                                      hover:text-simba-600 dark:hover:text-simba-300 transition-colors">
                                {{ $partner->code }}
                            </a>
                        </td>

                        {{-- Name --}}
                        <td class="px-4 py-3.5">
                            <div class="min-w-0">
                                <a href="{{ route('admin.partners.show', $partner) }}" wire:navigate
                                   class="truncate font-medium text-slate-900 dark:text-slate-100
                                          hover:text-simba-600 dark:hover:text-simba-400 transition-colors">
                                    {{ $partner->name }}
                                </a>
                                @if ($partner->email)
                                    <p class="truncate text-xs text-slate-500 dark:text-slate-400 mono">{{ $partner->email }}</p>
                                @endif
                            </div>
                        </td>

                        {{-- Types --}}
                        <td class="px-4 py-3.5">
                            <div class="flex flex-wrap gap-1">
                                @foreach ((array) $partner->type as $type)
                                    <span class="rounded bg-slate-100 dark:bg-white/[0.08] px-1.5 py-0.5
                                                 text-[10px] font-semibold uppercase tracking-wide
                                                 text-slate-600 dark:text-slate-300
                                                 ring-1 ring-slate-200 dark:ring-white/[0.1]">
                                        {{ \App\Enums\PartnerType::from($type)->label() }}
                                    </span>
                                @endforeach
                            </div>
                        </td>

                        {{-- TIN --}}
                        <td class="px-4 py-3.5 text-slate-500 dark:text-slate-400 mono text-xs">
                            {{ $partner->tin ?? '—' }}
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3.5">
                            @if ($partner->is_active)
                                <span class="badge badge-green">Active</span>
                            @else
                                <span class="badge badge-slate">Inactive</span>
                            @endif
                        </td>

                        {{-- Actions --}}
                        <td class="px-4 py-3.5">
                            <x-ui.row-actions :label="'Actions for ' . $partner->name">
                                <x-ui.row-actions.item icon="eye" label="View" :href="route('admin.partners.show', $partner)" wire:navigate />
                                @can('partners.update')
                                    <x-ui.row-actions.item icon="pencil" label="Edit" :href="route('admin.partners.edit', $partner)" wire:navigate />
                                    <x-ui.row-actions.divider />
                                    @if (!$partner->is_active)
                                        <x-ui.row-actions.item icon="check" label="Activate" tone="success" wire:click="activate({{ $partner->id }})" />
                                    @else
                                        <x-ui.row-actions.item icon="x" label="Deactivate" tone="warning" wire:confirm="Deactivate {{ $partner->name }}?" wire:click="deactivate({{ $partner->id }})" />
                                    @endif
                                @endcan
                            </x-ui.row-actions>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-16 text-center">
                            <x-ui.icon name="users" class="mx-auto h-8 w-8 text-slate-300 dark:text-slate-600" />
                            <p class="mt-3 font-medium text-slate-900 dark:text-white">No partners found</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Try changing your search or filters.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>

                <tbody wire:loading class="divide-y divide-white/50 dark:divide-white/[0.06]">
                    @for ($i = 0; $i < 5; $i++)
                        <tr class="skeleton-row">
                            <td class="px-4"><div class="skeleton-cell w-16"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-40"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-24"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-20"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-14"></div></td>
                            <td class="px-4 w-14"><div class="skeleton-cell w-8 ml-auto"></div></td>
                        </tr>
                    @endfor
                </tbody>

            </table>
        </div>

        {{-- Pagination --}}
        @if ($this->partners->hasPages())
            <div class="px-4 py-3 border-t border-white/40 dark:border-white/[0.08]">
                {{ $this->partners->links() }}
            </div>
        @endif

    </div>

</div>
