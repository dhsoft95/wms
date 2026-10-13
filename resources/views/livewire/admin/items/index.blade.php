<div class="space-y-6" x-data="{ compact: $persist(false).as('table-density') }">

    {{-- Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">
                Items
                <span class="ml-2 align-middle text-sm font-medium text-slate-400 dark:text-slate-500">{{ $this->items->total() }}</span>
            </h2>
            <p class="page-sub">Manage product/SKU master data including UoMs, barcodes, and tracking settings.</p>
        </div>

        @can('items.create')
            <a href="{{ route('admin.items.create') }}" wire:navigate class="btn btn-primary">
                <x-ui.icon name="plus" class="h-4 w-4" />
                Add Item
            </a>
        @endcan
    </div>

    <div class="card overflow-hidden" :class="compact ? 'density-compact' : ''">

        {{-- Filters --}}
        <div class="flex flex-col gap-3 p-4 lg:flex-row
                    border-b border-white/40 dark:border-white/[0.08]">
            <div class="relative flex-1">
                <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    placeholder="Search by SKU, name, or barcode…"
                    class="input w-full pl-9"
                />
            </div>

            <select wire:model.live="filterCategory" class="input lg:w-44">
                <option value="">All categories</option>
                @foreach ($this->categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterVw" class="input lg:w-44">
                <option value="">All warehouses</option>
                @foreach ($this->virtualWarehouses as $vw)
                    <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterStatus" class="input lg:w-36">
                <option value="">All statuses</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>

            <div class="flex items-center gap-1 tabs shrink-0">
                <button type="button" @click="compact = false" :class="!compact ? 'tab-active' : ''" class="tab text-xs">Comfortable</button>
                <button type="button" @click="compact = true"  :class="compact  ? 'tab-active' : ''" class="tab text-xs">Compact</button>
            </div>
        </div>

        {{-- Table --}}
        <div wire:loading.class="opacity-50">
            <x-ui.table class="min-w-[800px]">

                <thead>
                    <tr>
                        <th class="px-4 py-3">SKU</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Base UoM</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 w-14 sr-only">Actions</th>
                    </tr>
                </thead>

                <tbody wire:loading.remove class="divide-y divide-white/50 dark:divide-white/[0.06]">
                @forelse ($this->items as $item)
                    <tr wire:key="item-{{ $item->id }}"
                        class="transition hover:bg-white/30 dark:hover:bg-white/[0.04]">

                        {{-- SKU --}}
                        <td class="px-4 py-3.5">
                            <a href="{{ route('admin.items.show', $item) }}" wire:navigate
                               class="font-mono text-xs font-semibold text-simba-700 dark:text-simba-400
                                      hover:underline">
                                {{ $item->sku }}
                            </a>
                        </td>

                        {{-- Name --}}
                        <td class="px-4 py-3.5">
                            <div class="font-medium text-slate-900 dark:text-slate-100">{{ $item->name }}</div>
                            @if ($item->defaultVirtualWarehouse)
                                <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">
                                    {{ $item->defaultVirtualWarehouse->name }}
                                </p>
                            @endif
                        </td>

                        {{-- Category --}}
                        <td class="px-4 py-3.5 text-slate-600 dark:text-slate-300">
                            {{ $item->category?->name ?? '—' }}
                        </td>

                        {{-- Base UoM --}}
                        <td class="px-4 py-3.5">
                            @if ($item->baseUom)
                                <span class="badge badge-slate font-mono">{{ $item->baseUom->code }}</span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3.5">
                            @if ($item->is_active)
                                <span class="badge badge-green">
                                    <span class="dot"></span> Active
                                </span>
                            @else
                                <span class="badge badge-red">Inactive</span>
                            @endif
                        </td>

                        {{-- Actions --}}
                        <td class="px-4 py-3.5 w-14">
                            <x-ui.row-actions :label="'Actions for ' . $item->name">

                                {{-- View (always) --}}
                                <x-ui.row-actions.item
                                    icon="eye"
                                    label="View"
                                    :href="route('admin.items.show', $item)"
                                    wire:navigate
                                />

                                @can('items.edit')
                                    {{-- Edit --}}
                                    <x-ui.row-actions.item
                                        icon="pencil"
                                        label="Edit"
                                        :href="route('admin.items.edit', $item)"
                                        wire:navigate
                                    />
                                @endcan

                                {{-- Print Label (always, disabled) --}}
                                <x-ui.row-actions.item
                                    icon="printer"
                                    label="Print Label"
                                    :disabled="true"
                                    hint="Available in Module 7"
                                />

                                <x-ui.row-actions.divider />

                                @can('items.edit')
                                    {{-- Activate (inactive items only) --}}
                                    @if (!$item->is_active)
                                        <x-ui.row-actions.item
                                            icon="check"
                                            label="Activate"
                                            tone="success"
                                            wire:click="activate({{ $item->id }})"
                                        />
                                    @endif

                                    {{-- Deactivate (active items only) --}}
                                    @if ($item->is_active)
                                        <x-ui.row-actions.item
                                            icon="x"
                                            label="Deactivate"
                                            tone="danger"
                                            wire:confirm="Deactivate {{ $item->name }}?"
                                            wire:click="deactivate({{ $item->id }})"
                                        />
                                    @endif
                                @endcan

                            </x-ui.row-actions>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-16 text-center">
                            <x-ui.icon name="package" class="mx-auto h-8 w-8 text-slate-300 dark:text-slate-600" />
                            @if ($search || $filterCategory || $filterVw || $filterStatus)
                                <p class="mt-3 font-medium text-slate-900 dark:text-white">No items match your filters</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    <button wire:click="$set('search', ''); $set('filterCategory', ''); $set('filterVw', ''); $set('filterStatus', '')"
                                            class="text-simba-600 dark:text-simba-400 hover:underline">
                                        Clear filters
                                    </button>
                                </p>
                            @else
                                <p class="mt-3 font-medium text-slate-900 dark:text-white">No items yet</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Get started by adding your first item.</p>
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
        @if ($this->items->hasPages())
            <div class="px-4 py-3 border-t border-white/40 dark:border-white/[0.08]">
                {{ $this->items->links() }}
            </div>
        @endif

    </div>

</div>
