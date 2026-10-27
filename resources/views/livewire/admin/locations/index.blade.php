<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">
                Locations
                @if ($viewMode === 'list')
                    <span class="ml-2 align-middle text-sm font-medium text-slate-400 dark:text-slate-500">{{ $this->locations->total() }}</span>
                @endif
            </h2>
            <p class="page-sub">Manage warehouse zones, aisles, racks, shelf levels, and bins.</p>
        </div>
        <div class="flex items-center gap-2">
            @can('locations.create')
                <a href="{{ route('admin.locations.bulk-generate') }}" wire:navigate class="btn btn-secondary">
                    <x-ui.icon name="arrows" class="w-4 h-4" /> Bulk Generate
                </a>
                <a href="{{ route('admin.locations.create') }}" wire:navigate class="btn btn-primary">
                    <x-ui.icon name="plus" class="w-4 h-4" /> Add Location
                </a>
            @endcan
        </div>
    </div>

    {{-- View Mode Toggle + Filters --}}
    <div class="card overflow-hidden">

        <div class="flex flex-col gap-3 p-4 lg:flex-row border-b border-white/40 dark:border-white/[0.08]">

            {{-- View Mode --}}
            <div class="flex rounded-lg overflow-hidden ring-1 ring-slate-200 dark:ring-white/[0.1] shrink-0">
                <button wire:click="$set('viewMode', 'list')"
                        type="button"
                        class="px-3 py-1.5 text-xs font-semibold transition
                               {{ $viewMode === 'list'
                                   ? 'bg-simba-600 text-white'
                                   : 'bg-white/60 dark:bg-white/[0.04] text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-white/[0.08]' }}">
                    <x-ui.icon name="arrows" class="inline w-3.5 h-3.5 mr-1" />List
                </button>
                <button wire:click="$set('viewMode', 'tree')"
                        type="button"
                        class="px-3 py-1.5 text-xs font-semibold transition border-l border-slate-200 dark:border-white/[0.1]
                               {{ $viewMode === 'tree'
                                   ? 'bg-simba-600 text-white'
                                   : 'bg-white/60 dark:bg-white/[0.04] text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-white/[0.08]' }}">
                    <x-ui.icon name="home" class="inline w-3.5 h-3.5 mr-1" />Tree
                </button>
            </div>

            {{-- Search (list mode only) --}}
            @if ($viewMode === 'list')
                <div class="relative flex-1">
                    <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="search"
                        placeholder="Search by code, segment or barcode…"
                        class="input w-full pl-9"
                    />
                </div>
            @endif

            {{-- Warehouse filter --}}
            <div class="lg:w-44">
                <select wire:model.live="filterWarehouse" class="input w-full">
                    <option value="">All Warehouses</option>
                    @foreach ($this->warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->code }} — {{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>

            @if ($viewMode === 'list')
                {{-- Type filter --}}
                <div class="lg:w-44">
                    <select wire:model.live="filterType" class="input w-full">
                        <option value="">All Types</option>
                        @foreach (\App\Enums\LocationType::cases() as $t)
                            <option value="{{ $t->value }}">{{ $t->label() }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Status filter --}}
                <div class="lg:w-40">
                    <select wire:model.live="filterStatus" class="input w-full">
                        <option value="">All Statuses</option>
                        @foreach (\App\Enums\LocationStatus::cases() as $s)
                            <option value="{{ $s->value }}">{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

        </div>

        {{-- ── LIST VIEW ──────────────────────────────────────────────────────── --}}
        @if ($viewMode === 'list')

            <div wire:loading.class="opacity-50">
                <x-ui.table class="min-w-[760px]">

                    <thead class="text-[11px] font-semibold uppercase tracking-wider
                                  text-slate-500 dark:text-slate-400
                                  bg-white/[0.45] dark:bg-white/[0.04]
                                  border-b border-white/50 dark:border-white/[0.07]">
                    <tr>
                        <th class="px-5 py-3">Code</th>
                        <th class="px-5 py-3">Level</th>
                        <th class="px-5 py-3">Type</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Warehouse</th>
                        <th class="px-5 py-3">Parent</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                    </thead>

                    <tbody class="divide-y divide-white/50 dark:divide-white/[0.06]">
                    @forelse ($this->locations as $location)
                        <tr wire:key="loc-{{ $location->id }}"
                            class="transition hover:bg-white/30 dark:hover:bg-white/[0.04]">

                            {{-- Code --}}
                            <td class="px-5 py-3.5">
                                <span class="font-mono text-xs font-semibold text-slate-800 dark:text-slate-100">{{ $location->code }}</span>
                                @if ($location->is_pickable)
                                    <span class="ml-1.5 inline-flex items-center gap-0.5 rounded bg-emerald-50 dark:bg-emerald-900/20 px-1 py-0.5 text-[10px] font-semibold text-emerald-700 dark:text-emerald-400 ring-1 ring-emerald-200 dark:ring-emerald-700/30">
                                        Pickable
                                    </span>
                                @endif
                            </td>

                            {{-- Level --}}
                            <td class="px-5 py-3.5 text-slate-600 dark:text-slate-400 capitalize">
                                {{ $location->level->label() }}
                            </td>

                            {{-- Type --}}
                            <td class="px-5 py-3.5 text-slate-600 dark:text-slate-400">
                                {{ $location->type->label() }}
                            </td>

                            {{-- Status --}}
                            <td class="px-5 py-3.5">
                                @if ($location->status === \App\Enums\LocationStatus::Active)
                                    <span class="badge badge-green">Active</span>
                                @elseif ($location->status === \App\Enums\LocationStatus::Blocked)
                                    <span class="badge badge-red">Blocked</span>
                                @elseif ($location->status === \App\Enums\LocationStatus::Full)
                                    <span class="badge badge-amber">Full</span>
                                @endif
                            </td>

                            {{-- Warehouse --}}
                            <td class="px-5 py-3.5">
                                <span class="badge badge-slate mono">{{ $location->warehouse->code }}</span>
                            </td>

                            {{-- Parent --}}
                            <td class="px-5 py-3.5 text-slate-500 dark:text-slate-400 font-mono text-xs">
                                {{ $location->parent?->code ?? '—' }}
                            </td>

                            {{-- Actions --}}
                            <td class="px-5 py-3.5">
                                <x-ui.row-actions :label="'Actions for ' . $location->code">
                                    @can('stock.view')
                                        <x-ui.row-actions.item
                                            icon="list"
                                            label="View Stock"
                                            :href="route('inventory.stock.index', ['filterLocation' => $location->id])"
                                            wire:navigate
                                        />
                                    @endcan
                                    @can('locations.update')
                                        <x-ui.row-actions.item icon="pencil" label="Edit" :href="route('admin.locations.edit', $location)" wire:navigate />
                                        <x-ui.row-actions.divider />
                                        @if ($location->status === \App\Enums\LocationStatus::Blocked)
                                            <x-ui.row-actions.item icon="check" label="Unblock" tone="success" wire:click="unblockLocation({{ $location->id }})" />
                                        @elseif ($location->status === \App\Enums\LocationStatus::Active)
                                            <x-ui.row-actions.item icon="lock" label="Block" tone="warning" wire:click="openBlockModal({{ $location->id }})" />
                                        @endif
                                    @endcan
                                </x-ui.row-actions>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-16 text-center">
                                <x-ui.icon name="home" class="mx-auto h-8 w-8 text-slate-300 dark:text-slate-600" />
                                <p class="mt-3 font-medium text-slate-900 dark:text-white">No locations found</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    {{ $search || $filterWarehouse || $filterType || $filterStatus
                                        ? 'Try adjusting your filters.'
                                        : 'Add your first location or use bulk generate.' }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>

                </x-ui.table>
            </div>

            {{-- Pagination --}}
            @if ($this->locations->hasPages())
                <div class="px-4 py-3 border-t border-white/40 dark:border-white/[0.08]">
                    {{ $this->locations->links() }}
                </div>
            @endif

        @else
            {{-- ── TREE VIEW ──────────────────────────────────────────────────── --}}

            <div class="p-4 space-y-2" wire:loading.class="opacity-50">

                @forelse ($this->treeLocations as $root)
                    @include('livewire.admin.locations._tree-node', ['node' => $root, 'depth' => 0])
                @empty
                    <div class="py-12 text-center">
                        <x-ui.icon name="home" class="mx-auto h-8 w-8 text-slate-300 dark:text-slate-600" />
                        <p class="mt-3 font-medium text-slate-900 dark:text-white">No root locations found</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ $filterWarehouse ? 'No root locations for the selected warehouse.' : 'Select a warehouse or add locations first.' }}
                        </p>
                    </div>
                @endforelse

            </div>

        @endif

    </div>

    {{-- ── BLOCK MODAL ──────────────────────────────────────────────────────── --}}
    @if ($blockingLocationId)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog" aria-modal="true">

            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"
                 wire:click="closeBlockModal"></div>

            {{-- Panel --}}
            <div class="relative z-10 w-full max-w-md rounded-xl bg-white dark:bg-zinc-900
                        shadow-2xl ring-1 ring-black/[0.08] dark:ring-white/[0.08]">

                <div class="p-6">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">Block Location</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        This location will be marked as <strong class="text-red-600 dark:text-red-400">Blocked</strong> and
                        unavailable for warehouse operations.
                    </p>
                </div>

                <div class="px-6 pb-6 space-y-4">
                    <div>
                        <label class="label" for="block-reason">
                            Reason <span class="required-mark">*</span>
                        </label>
                        <textarea
                            wire:model="blockReason"
                            id="block-reason"
                            rows="3"
                            placeholder="Describe why this location is being blocked…"
                            class="input w-full resize-none @error('blockReason') border-red-400 @enderror"
                        ></textarea>
                        @error('blockReason')
                            <p class="hint hint-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="button" wire:click="closeBlockModal" class="btn btn-secondary">
                            Cancel
                        </button>
                        <button type="button"
                                wire:click="blockLocation"
                                wire:loading.attr="disabled"
                                class="btn bg-red-600 hover:bg-red-700 text-white font-semibold">
                            <span wire:loading.remove wire:target="blockLocation">Block Location</span>
                            <span wire:loading wire:target="blockLocation">Blocking…</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    @endif

</div>
