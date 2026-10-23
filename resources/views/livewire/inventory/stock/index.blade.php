<div class="space-y-6">

    {{-- ── PAGE HEADER ──────────────────────────────────────────────────────────── --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Stock on Hand</h2>
            <p class="page-sub">Live balances by SKU — click a row to see full location breakdown.</p>
        </div>
        <button type="button" disabled title="Available in Module 10"
                class="btn btn-secondary opacity-40 cursor-not-allowed">
            <x-ui.icon name="download" class="w-4 h-4" /> Export
        </button>
    </div>

    {{-- ── STATS CARDS ──────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

        <div class="card p-5 flex items-center gap-4">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800">
                <x-ui.icon name="package" class="w-5 h-5 text-slate-500 dark:text-slate-400" />
            </div>
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Total SKUs</p>
                <p class="mt-0.5 text-2xl font-bold tabular-nums text-slate-900 dark:text-white leading-none">
                    {{ number_format($this->stats['total_skus']) }}
                </p>
            </div>
        </div>

        <div class="card p-5 flex items-center gap-4">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-simba-50 dark:bg-simba-900/30">
                <x-ui.icon name="trending-up" class="w-5 h-5 text-simba-600 dark:text-simba-400" />
            </div>
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Stock Value</p>
                <p class="mt-0.5 text-2xl font-bold tabular-nums text-simba-700 dark:text-simba-400 leading-none">
                    {{ number_format($this->stats['total_value'], 2) }}
                </p>
                <p class="text-[11px] text-slate-400 mt-0.5">at last purchase price</p>
            </div>
        </div>

        <div class="card p-5 flex items-center gap-4">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl
                        {{ $this->stats['below_min'] > 0 ? 'bg-red-50 dark:bg-red-950/30' : 'bg-slate-100 dark:bg-slate-800' }}">
                <x-ui.icon name="alert-triangle" class="w-5 h-5 {{ $this->stats['below_min'] > 0 ? 'text-red-500' : 'text-slate-400' }}" />
            </div>
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Below Min Stock</p>
                <p class="mt-0.5 text-2xl font-bold tabular-nums leading-none
                          {{ $this->stats['below_min'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">
                    {{ number_format($this->stats['below_min']) }}
                </p>
                @if($this->stats['below_min'] > 0)
                    <p class="text-[11px] text-red-400 mt-0.5">items need replenishment</p>
                @endif
            </div>
        </div>

    </div>

    {{-- ── FILTERS + TABLE ──────────────────────────────────────────────────────── --}}
    <div class="card overflow-hidden">

        {{-- Filter Bar --}}
        <div class="p-4 border-b border-slate-100 dark:border-white/[0.06] space-y-3">

            {{-- Row 1: Search --}}
            <div class="relative">
                <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input wire:model.live.debounce.300ms="search"
                       type="search"
                       placeholder="Search by SKU, item name…"
                       class="input w-full pl-9" />
            </div>

            {{-- Row 2: Dropdowns --}}
            <div class="flex flex-wrap gap-2">
                <select wire:model.live="filterCategory" class="input text-sm py-1.5 h-9 flex-1 min-w-[130px]">
                    <option value="">All categories</option>
                    @foreach ($this->categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>

                <select wire:model.live="filterLocation" class="input text-sm py-1.5 h-9 flex-1 min-w-[130px]">
                    <option value="">All locations</option>
                    @foreach ($this->locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->code }}</option>
                    @endforeach
                </select>

                <select wire:model.live="filterVw" class="input text-sm py-1.5 h-9 flex-1 min-w-[150px]">
                    <option value="">All warehouses</option>
                    @foreach ($this->virtualWarehouses as $vw)
                        <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                    @endforeach
                </select>

                <select wire:model.live="filterStatus" class="input text-sm py-1.5 h-9 w-36">
                    <option value="">All statuses</option>
                    <option value="available">Available</option>
                    <option value="on_hold">On Hold</option>
                    <option value="obsolete">Obsolete</option>
                </select>

                <select wire:model.live="filterExpiring" class="input text-sm py-1.5 h-9 w-44">
                    <option value="">Any expiry</option>
                    <option value="7">Expiring in 7 days</option>
                    <option value="14">Expiring in 14 days</option>
                    <option value="30">Expiring in 30 days</option>
                </select>

                {{-- Active filter count + clear --}}
                @php
                    $activeFilters = collect([$search, $filterCategory, $filterLocation, $filterVw, $filterStatus, $filterExpiring])->filter()->count();
                @endphp
                @if($activeFilters)
                    <button wire:click="$set('search', ''); $set('filterCategory', ''); $set('filterLocation', ''); $set('filterVw', ''); $set('filterStatus', ''); $set('filterExpiring', '')"
                            class="h-9 inline-flex items-center gap-1.5 px-3 rounded-lg text-sm font-medium text-slate-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/20 dark:hover:text-red-400 border border-slate-200 dark:border-white/10 transition-colors">
                        <x-ui.icon name="x" class="w-3.5 h-3.5" />
                        Clear {{ $activeFilters }}
                    </button>
                @endif
            </div>

        </div>

        {{-- Table --}}
        <div wire:loading.class="opacity-50 pointer-events-none" class="transition-opacity">
            <x-ui.table>
                <thead>
                    <tr>
                        <th class="w-10">#</th>
                        <th>Item</th>
                        <th>Locations</th>
                        <th class="text-right">On Hand</th>
                        <th class="text-right">Allocated</th>
                        <th class="text-right">Available</th>
                        <th>Status</th>
                        <th class="w-10 sr-only">Actions</th>
                    </tr>
                </thead>

                {{-- Loading skeleton --}}
                <tbody wire:loading class="divide-y divide-slate-100 dark:divide-white/[0.05]">
                    @for ($i = 0; $i < 8; $i++)
                        <tr>
                            <td class="px-4 py-3"><div class="h-3 w-5 rounded bg-slate-100 dark:bg-slate-800 animate-pulse"></div></td>
                            <td class="px-4 py-3">
                                <div class="h-3 w-20 rounded bg-slate-100 dark:bg-slate-800 animate-pulse mb-1.5"></div>
                                <div class="h-2.5 w-32 rounded bg-slate-100 dark:bg-slate-800 animate-pulse"></div>
                            </td>
                            <td class="px-4 py-3"><div class="h-3 w-24 rounded bg-slate-100 dark:bg-slate-800 animate-pulse"></div></td>
                            <td class="px-4 py-3"><div class="h-3 w-14 rounded bg-slate-100 dark:bg-slate-800 animate-pulse ml-auto"></div></td>
                            <td class="px-4 py-3"><div class="h-3 w-14 rounded bg-slate-100 dark:bg-slate-800 animate-pulse ml-auto"></div></td>
                            <td class="px-4 py-3"><div class="h-3 w-14 rounded bg-slate-100 dark:bg-slate-800 animate-pulse ml-auto"></div></td>
                            <td class="px-4 py-3"><div class="h-5 w-16 rounded-full bg-slate-100 dark:bg-slate-800 animate-pulse"></div></td>
                            <td class="px-4 py-3"><div class="h-5 w-5 rounded bg-slate-100 dark:bg-slate-800 animate-pulse ml-auto"></div></td>
                        </tr>
                    @endfor
                </tbody>

                {{-- Data rows --}}
                <tbody wire:loading.remove class="divide-y divide-slate-100 dark:divide-white/[0.05]">
                @forelse ($this->balances as $balance)
                    @php
                        $available   = bcsub((string) $balance->qty_on_hand, (string) $balance->qty_allocated, 4);
                        $isLowStock  = $balance->item?->min_stock !== null && (float) $balance->qty_on_hand < (float) $balance->item->min_stock;
                        $isHazardous = (bool) $balance->item?->is_hazardous;
                        $rawStatus   = $balance->getRawOriginal('stock_status') ?? 'available';
                        $statusColor = match($rawStatus) {
                            'available' => 'badge-green',
                            'on_hold'   => 'badge-amber',
                            'obsolete'  => 'badge-red',
                            default     => 'badge-slate',
                        };
                        $statusLabel = match($rawStatus) {
                            'available' => 'Available',
                            'on_hold'   => 'On Hold',
                            'obsolete'  => 'Obsolete',
                            default     => ucfirst($rawStatus),
                        };
                    @endphp
                    <tr wire:key="balance-{{ $balance->id }}"
                        class="group cursor-pointer transition-colors
                               {{ $isLowStock ? 'bg-red-50/40 dark:bg-red-950/10 hover:bg-red-50/70 dark:hover:bg-red-950/20' : 'hover:bg-slate-50/80 dark:hover:bg-white/[0.025]' }}"
                        wire:click="$wire.dispatch('navigate', { to: '{{ route('inventory.stock.show', $balance->id) }}' })"
                        onclick="window.Livewire && window.location.href !== '{{ route('inventory.stock.show', $balance->id) }}' ? (window.location.href = '{{ route('inventory.stock.show', $balance->id) }}') : null">

                        {{-- Row number --}}
                        <td class="px-4 py-3.5 text-xs text-slate-400 tabular-nums w-10">
                            {{ $this->balances->firstItem() + $loop->index }}
                        </td>

                        {{-- Item --}}
                        <td class="px-4 py-3.5">
                            <div class="flex items-start gap-2.5">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-mono text-xs font-semibold text-simba-700 dark:text-simba-400">
                                            {{ $balance->item?->sku ?? '—' }}
                                        </span>
                                        @if($isLowStock)
                                            <span class="badge badge-red !text-[10px] !px-1.5 !py-0">Low</span>
                                        @endif
                                        @if($isHazardous)
                                            <span class="badge badge-amber !text-[10px] !px-1.5 !py-0">
                                                <x-ui.icon name="alert-triangle" class="w-2.5 h-2.5 inline -mt-px" /> Hazardous
                                            </span>
                                        @endif
                                    </div>
                                    <div class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-200 truncate max-w-xs">
                                        {{ $balance->item?->name ?? '—' }}
                                    </div>
                                    @if($balance->item?->category)
                                        <div class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">
                                            {{ $balance->item->category->name }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </td>

                        {{-- Locations --}}
                        <td class="px-4 py-3.5">
                            @php
                                $locCount = (int) ($balance->locations_count ?? 1);
                                $vwCount  = (int) ($balance->warehouses_count ?? 1);
                            @endphp
                            <span class="inline-flex items-center gap-1 text-xs font-medium text-slate-600 dark:text-slate-300">
                                <x-ui.icon name="map-pin" class="w-3 h-3 text-slate-400 shrink-0" />
                                {{ $locCount }} {{ Str::plural('bin', $locCount) }}
                            </span>
                            @if($vwCount > 1)
                                <div class="mt-0.5 text-xs text-slate-400">{{ $vwCount }} warehouses</div>
                            @endif
                        </td>

                        {{-- On Hand --}}
                        <td class="px-4 py-3.5 text-right">
                            <span class="tabular-nums font-semibold text-slate-800 dark:text-slate-200">
                                {{ number_format((float) $balance->qty_on_hand, 2) }}
                            </span>
                            @if($balance->item?->baseUom)
                                <span class="ml-1 text-xs text-slate-400">{{ $balance->item->baseUom->code }}</span>
                            @endif
                        </td>

                        {{-- Allocated --}}
                        <td class="px-4 py-3.5 text-right tabular-nums text-sm
                                   {{ (float) $balance->qty_allocated > 0 ? 'text-amber-600 dark:text-amber-400 font-medium' : 'text-slate-400' }}">
                            {{ number_format((float) $balance->qty_allocated, 2) }}
                        </td>

                        {{-- Available --}}
                        <td class="px-4 py-3.5 text-right">
                            @php $availFloat = (float) $available; @endphp
                            <span class="tabular-nums font-bold text-sm
                                        {{ $availFloat <= 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                {{ number_format($availFloat, 2) }}
                            </span>
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3.5">
                            <span class="badge {{ $statusColor }}">
                                <span class="dot"></span>{{ $statusLabel }}
                            </span>
                        </td>

                        {{-- Actions --}}
                        <td class="px-4 py-3.5 w-10" onclick="event.stopPropagation()">
                            <a href="{{ route('inventory.stock.show', $balance->id) }}"
                               wire:navigate
                               title="View details"
                               class="flex h-7 w-7 items-center justify-center rounded-lg
                                      text-slate-300 group-hover:text-slate-500
                                      dark:text-slate-600 dark:group-hover:text-slate-400
                                      hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                                <x-ui.icon name="arrow-right" class="h-4 w-4" />
                            </a>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-20 text-center">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                                <x-ui.icon name="package" class="h-7 w-7 text-slate-400" />
                            </div>
                            @if ($search || $filterCategory || $filterLocation || $filterVw || $filterStatus || $filterExpiring)
                                <p class="mt-4 font-semibold text-slate-800 dark:text-slate-200">No stock matches your filters</p>
                                <p class="mt-1 text-sm text-slate-500">
                                    <button wire:click="$set('search', ''); $set('filterCategory', ''); $set('filterLocation', ''); $set('filterVw', ''); $set('filterStatus', ''); $set('filterExpiring', '')"
                                            class="text-simba-600 dark:text-simba-400 hover:underline font-medium">
                                        Clear all filters
                                    </button>
                                </p>
                            @else
                                <p class="mt-4 font-semibold text-slate-800 dark:text-slate-200">No stock on hand</p>
                                <p class="mt-1 text-sm text-slate-500">Post an opening balance or receive goods to see stock here.</p>
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>

            </x-ui.table>
        </div>

        {{-- Pagination --}}
        @if ($this->balances->hasPages())
            <div class="px-4 py-3 border-t border-slate-100 dark:border-white/[0.06] flex items-center justify-between">
                <p class="text-xs text-slate-400">
                    Showing {{ $this->balances->firstItem() }}–{{ $this->balances->lastItem() }} of {{ number_format($this->balances->total()) }}
                </p>
                {{ $this->balances->links() }}
            </div>
        @endif

    </div>

    {{-- ── VIEW SERIALS MODAL ───────────────────────────────────────────────────── --}}
    @if ($viewingSerialsId && $this->viewingSerialsBalance)
        @php
            $vsBalance = $this->viewingSerialsBalance['balance'];
            $vsSerials = $this->viewingSerialsBalance['serials'];
            $countMatch = $vsSerials->count() === (int) round((float) $vsBalance->qty_on_hand);
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog" aria-modal="true"
             x-data x-on:keydown.escape.window="$wire.closeSerials()">

            <div class="absolute inset-0 bg-black/50 backdrop-blur-[2px]"
                 wire:click="closeSerials"></div>

            <div class="relative z-10 w-full max-w-lg rounded-2xl bg-white dark:bg-zinc-900
                        shadow-2xl ring-1 ring-black/[0.06] dark:ring-white/[0.08]
                        flex flex-col max-h-[80vh]">

                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-white/[0.08]">
                    <div>
                        <h3 class="font-semibold text-slate-900 dark:text-white">Serial Numbers</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ $vsBalance->item?->name }} &bull; {{ $vsBalance->location?->code }} &bull; {{ $vsBalance->virtualWarehouse?->name }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeSerials"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                        <x-ui.icon name="x" class="h-4 w-4" />
                    </button>
                </div>

                <div class="px-6 py-2.5 border-b border-slate-100 dark:border-white/[0.08] flex items-center gap-3">
                    <span class="text-sm text-slate-500 dark:text-slate-400 tabular-nums">
                        {{ $vsSerials->count() }} serial(s) on hand
                    </span>
                    <span class="badge {{ $countMatch ? 'badge-green' : 'badge-amber' }}">
                        <x-ui.icon name="{{ $countMatch ? 'check' : 'alert-triangle' }}" class="w-3 h-3 inline -mt-px mr-1" />
                        {{ $countMatch ? 'Count matches balance' : 'Count mismatch' }}
                    </span>
                </div>

                <div class="overflow-y-auto flex-1">
                    <x-ui.table class="text-sm">
                        <thead>
                            <tr>
                                <th class="w-10">#</th>
                                <th>Serial No</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/[0.06]">
                            @foreach ($vsSerials as $idx => $sn)
                                <tr>
                                    <td class="text-xs text-slate-400 tabular-nums w-10">{{ $idx + 1 }}</td>
                                    <td class="font-mono text-xs font-semibold text-slate-800 dark:text-slate-200">{{ $sn->serial_no }}</td>
                                    <td><span class="badge badge-{{ $sn->status->color() }}">{{ $sn->status->label() }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                </div>

                <div class="px-6 py-4 border-t border-slate-100 dark:border-white/[0.08] flex justify-end">
                    <button type="button" wire:click="closeSerials" class="btn btn-secondary">Close</button>
                </div>

            </div>
        </div>
    @endif

</div>
