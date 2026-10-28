<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div class="flex items-center gap-3.5">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 shadow-lg shadow-brand-500/25 ring-1 ring-inset ring-white/20">
                <x-ui.icon name="qr-code" class="h-5 w-5 text-white" />
            </div>
            <div>
                <p class="section-label text-brand-600 dark:text-brand-400">Inventory</p>
                <h2 class="page-title">SKU Tracker</h2>
            </div>
        </div>
        <p class="page-sub max-w-md sm:text-right">Full visibility into any item: stock, movements, open orders and more.</p>
    </div>

    {{-- ══════════════════════════════════════════════════════ SEARCH STATE ══ --}}
    @if ($this->selectedItem === null)

        {{-- Hero search card --}}
        <div class="card relative overflow-hidden">
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-brand-50/60 via-transparent to-transparent dark:from-brand-950/30"></div>
            <div class="pointer-events-none absolute -top-24 left-1/2 h-64 w-[36rem] -translate-x-1/2 rounded-full bg-brand-400/15 blur-3xl dark:bg-brand-500/[0.07]"></div>

            <div class="relative px-6 py-14 text-center sm:px-10 sm:py-20">
                <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-white/80 shadow-xl shadow-brand-500/10 ring-1 ring-white/70 dark:bg-white/[0.06] dark:ring-white/10">
                    <x-ui.icon name="search" class="h-7 w-7 text-brand-500" />
                </div>
                <h3 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-3xl">Find any SKU instantly</h3>
                <p class="mx-auto mt-2 max-w-md text-sm text-slate-500 dark:text-slate-400">
                    Look up stock levels, locations, movements and open orders in one place.
                </p>

                <div class="relative mx-auto mt-8 max-w-2xl">
                    <div class="relative" x-data="{ focused: false }">
                        <span class="pointer-events-none absolute left-4 top-1/2 z-10 -translate-y-1/2 transition-colors"
                              x-bind:class="focused ? 'text-brand-500' : 'text-slate-400'">
                            <x-ui.icon name="search" class="h-5 w-5" />
                        </span>
                        <input
                            wire:model.live.debounce.300ms="search"
                            type="search"
                            placeholder="SKU, barcode, part number or item name"
                            class="input py-3.5 pl-12 pr-10 text-base bg-white/90 dark:bg-zinc-900/80 [&::-webkit-search-cancel-button]:hidden"
                            autofocus
                            x-on:focus="focused = true"
                            x-on:blur="focused = false"
                        />
                        <div class="absolute right-4 top-1/2 z-10 -translate-y-1/2">
                            <svg wire:loading wire:target="search" class="h-5 w-5 animate-spin text-brand-500" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                                <path class="opacity-90" d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                            </svg>
                            @if (strlen($search) > 0)
                                <button
                                    type="button"
                                    wire:loading.remove
                                    wire:target="search"
                                    wire:click="$set('search', '')"
                                    class="flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 transition hover:bg-black/[0.06] hover:text-slate-600 dark:hover:bg-white/10 dark:hover:text-slate-200"
                                    aria-label="Clear search"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                        <span>Search by</span>
                        @foreach (['SKU code', 'Barcode', 'Part number', 'Item name'] as $hint)
                            <span class="rounded-full border border-white/60 bg-white/50 px-2.5 py-1 font-medium backdrop-blur dark:border-white/10 dark:bg-white/[0.04]">{{ $hint }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Search results --}}
        @if (strlen(trim($search)) >= 2)
            @if ($this->results->isEmpty())
                <div class="card px-6 py-14 text-center">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-black/[0.04] dark:bg-white/[0.06] ring-8 ring-black/[0.02] dark:ring-white/[0.03]">
                        <x-ui.icon name="search" class="h-6 w-6 text-slate-400" />
                    </div>
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">No items matched "{{ $search }}"</p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Try a SKU, barcode, part number or part of the name.</p>
                </div>
            @else
                <div class="card overflow-hidden">
                    <div class="card-header">
                        <div>
                            <h4 class="card-title">Search results</h4>
                            <p class="hint">Matching "{{ $search }}"</p>
                        </div>
                        <span class="badge badge-brand">
                            {{ $this->results->count() }} {{ \Illuminate\Support\Str::plural('item', $this->results->count()) }}
                        </span>
                    </div>

                    <ul class="divide-y divide-black/[0.04] dark:divide-white/[0.05]">
                        @foreach ($this->results as $item)
                            <li>
                                <button
                                    wire:click="selectItem({{ $item->id }})"
                                    wire:loading.attr="disabled"
                                    class="group relative flex w-full items-center gap-4 px-5 py-3.5 text-left transition hover:bg-brand-50/50 disabled:opacity-60 dark:hover:bg-white/[0.03]"
                                >
                                    <span class="absolute inset-y-2 left-0 w-[3px] scale-y-0 rounded-r-full bg-brand-500 transition-transform group-hover:scale-y-100"></span>

                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-black/[0.04] ring-1 ring-black/[0.06] transition group-hover:bg-brand-500 group-hover:ring-brand-500 dark:bg-white/[0.05] dark:ring-white/[0.08]">
                                        <x-ui.icon name="archive" class="h-4.5 w-4.5 text-slate-500 transition group-hover:text-white dark:text-slate-400" />
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="mono text-slate-900 dark:text-white">{{ $item->sku }}</span>
                                            @if (! $item->is_active)
                                                <span class="badge badge-neutral text-[10px]">Inactive</span>
                                            @endif
                                        </div>
                                        <div class="mt-0.5 truncate text-sm text-slate-600 dark:text-slate-300">{{ $item->name }}</div>
                                        @if ($item->part_number)
                                            <div class="mt-0.5 font-mono text-xs text-slate-400">PN {{ $item->part_number }}</div>
                                        @endif
                                    </div>

                                    <div class="hidden shrink-0 items-center gap-1.5 sm:flex">
                                        @if ($item->category)
                                            <span class="badge badge-neutral">{{ $item->category->name }}</span>
                                        @endif
                                        @if ($item->baseUom)
                                            <span class="badge badge-brand">{{ $item->baseUom->code }}</span>
                                        @endif
                                    </div>

                                    <x-ui.icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-brand-500 dark:text-white/20" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif

    {{-- ═════════════════════════════════════════════════════ ITEM DETAIL ══ --}}
    @else
        @php
            $item     = $this->selectedItem;
            $summary  = $this->stockSummary;
            $uom      = $item->baseUom?->code ?? '';
            $availPct = $summary['on_hand'] > 0 ? max(0, min(100, round(($summary['available'] / $summary['on_hand']) * 100))) : 0;
            $allocPct = $summary['on_hand'] > 0 ? max(0, min(100, round(($summary['allocated'] / $summary['on_hand']) * 100))) : 0;
        @endphp

        <div
            x-data="{
                tab: 'stock',
                counts: {
                    stock:     {{ $this->stockByLocation->count() }},
                    movements: {{ $this->recentMovements->count() }},
                    inbound:   {{ $this->openInbound->count() }},
                    outbound:  {{ $this->openOutbound->count() }},
                    @if ($item->track_batch)  batches: {{ $this->batches->count() }}, @endif
                    @if ($item->track_serial) serials: {{ $this->serials->count() }}, @endif
                }
            }"
            class="space-y-5"
        >

            {{-- Item Header --}}
            <div class="card overflow-hidden">
                {{-- Brand banner --}}
                <div class="relative h-20 overflow-hidden bg-gradient-to-r from-brand-600 via-brand-500 to-brand-700">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,rgb(255_255_255/0.15)_1px,transparent_0)] bg-[size:18px_18px]"></div>
                    <div class="absolute -right-8 -top-12 h-48 w-48 rounded-full bg-white/10 blur-2xl"></div>
                    <button
                        wire:click="clearSelection"
                        class="absolute left-5 top-3.5 inline-flex items-center gap-1.5 rounded-lg bg-white/15 px-3 py-1.5 text-xs font-semibold text-white ring-1 ring-inset ring-white/25 backdrop-blur transition hover:bg-white/25"
                    >
                        <x-ui.icon name="arrow-left" class="h-3.5 w-3.5" />
                        Back to search
                    </button>
                </div>

                <div class="card-body">
                    <div class="-mt-8 flex flex-col gap-5 lg:flex-row lg:items-end">
                        <div class="flex h-[4.5rem] w-[4.5rem] shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 shadow-xl shadow-brand-500/25 ring-4 ring-white dark:ring-black/40">
                            <x-ui.icon name="archive" class="h-8 w-8 text-white" />
                        </div>

                        <div class="min-w-0 flex-1 lg:pb-1">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <h3 class="mono text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $item->sku }}</h3>
                                @if ($item->is_active)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Active
                                    </span>
                                @else
                                    <span class="badge badge-neutral">
                                        <span class="dot"></span>Inactive
                                    </span>
                                @endif
                            </div>
                            <p class="mt-1 text-base font-medium text-slate-700 dark:text-slate-200">{{ $item->name }}</p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-5 lg:grid-cols-[1fr_auto]">
                        <div class="space-y-3">
                            @if ($item->part_number || $item->description)
                                <div class="space-y-1.5">
                                    @if ($item->part_number)
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            Part No
                                            <span class="ml-1 rounded-md bg-black/[0.05] px-1.5 py-0.5 font-mono font-semibold text-slate-700 dark:bg-white/[0.08] dark:text-slate-200">{{ $item->part_number }}</span>
                                        </p>
                                    @endif
                                    @if ($item->description)
                                        <p class="line-clamp-2 max-w-2xl text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $item->description }}</p>
                                    @endif
                                </div>
                            @endif

                            <div class="flex flex-wrap gap-1.5">
                                @if ($item->category)
                                    <span class="badge badge-neutral">
                                        <x-ui.icon name="tag" class="h-3 w-3" />{{ $item->category->name }}
                                    </span>
                                @endif
                                @if ($item->baseUom)
                                    <span class="badge badge-brand">UoM {{ $item->baseUom->code }}</span>
                                @endif
                                @if ($item->track_batch)
                                    <span class="badge badge-warning">
                                        <x-ui.icon name="list" class="h-3 w-3" />Batch
                                    </span>
                                @endif
                                @if ($item->track_expiry)
                                    <span class="badge badge-orange">
                                        <x-ui.icon name="clock" class="h-3 w-3" />Expiry
                                    </span>
                                @endif
                                @if ($item->track_serial)
                                    <span class="badge badge-violet">
                                        <x-ui.icon name="qr-code" class="h-3 w-3" />Serial
                                    </span>
                                @endif
                                @if ($item->is_hazardous)
                                    <span class="badge badge-danger">
                                        <x-ui.icon name="alert" class="h-3 w-3" />Hazardous
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Stock health meter --}}
                        <div class="w-full rounded-xl border border-black/[0.06] bg-black/[0.02] p-4 dark:border-white/[0.08] dark:bg-white/[0.03] lg:w-64">
                            <div class="flex items-center justify-between">
                                <span class="section-label">Stock health</span>
                                <span class="text-sm font-bold tabular-nums {{ $availPct >= 50 ? 'text-emerald-600 dark:text-emerald-400' : ($availPct > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-red-500 dark:text-red-400') }}">
                                    {{ $availPct }}% free
                                </span>
                            </div>
                            <div class="mt-3 flex h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-white/20">
                                <div class="h-full bg-emerald-500 transition-all" style="width: {{ $availPct }}%"></div>
                                <div class="h-full bg-amber-400 transition-all" style="width: {{ $allocPct }}%"></div>
                            </div>
                            <div class="mt-2.5 flex items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
                                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Available</span>
                                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-amber-400"></span>Allocated</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- KPI Cards --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-ui.kpi-card label="On Hand"   :value="number_format($summary['on_hand'], 2)"   :hint="$uom" tone="brand"                                                         icon="archive" />
                <x-ui.kpi-card label="Allocated" :value="number_format($summary['allocated'], 2)" :hint="$uom" :tone="$summary['allocated'] > 0 ? 'warning' : 'neutral'"            icon="lock" />
                <x-ui.kpi-card label="Available" :value="number_format($summary['available'], 2)" :hint="$uom" :tone="$summary['available'] > 0 ? 'success' : 'danger'"             icon="badge-check" />
            </div>

            {{-- Tabbed detail --}}
            <div class="card overflow-hidden">

                @php
                    $tabs = [
                        ['key' => 'stock',     'label' => 'Locations',     'icon' => 'map-pin'],
                        ['key' => 'movements', 'label' => 'Movements',     'icon' => 'arrows'],
                        ['key' => 'inbound',   'label' => 'Open Inbound',  'icon' => 'download'],
                        ['key' => 'outbound',  'label' => 'Open Outbound', 'icon' => 'upload'],
                    ];
                    if ($item->track_batch)  { $tabs[] = ['key' => 'batches', 'label' => 'Batches', 'icon' => 'list']; }
                    if ($item->track_serial) { $tabs[] = ['key' => 'serials', 'label' => 'Serials', 'icon' => 'qr-code']; }
                @endphp

                {{-- Tab bar --}}
                <div class="overflow-x-auto border-b border-black/[0.05] px-4 py-3 dark:border-white/[0.06]">
                    <nav class="tabs">
                        @foreach ($tabs as $t)
                            <button
                                type="button"
                                @click="tab = '{{ $t['key'] }}'"
                                :class="tab === '{{ $t['key'] }}' ? 'active' : ''"
                                class="tab flex items-center gap-2 whitespace-nowrap"
                            >
                                <x-ui.icon name="{{ $t['icon'] }}" class="h-4 w-4" />
                                {{ $t['label'] }}
                                <span
                                    :class="tab === '{{ $t['key'] }}'
                                        ? 'bg-brand-500 text-white'
                                        : 'bg-black/[0.06] text-slate-500 dark:bg-white/10 dark:text-slate-400'"
                                    class="min-w-[1.5rem] rounded-full px-1.5 py-0.5 text-center text-[11px] font-bold tabular-nums transition-colors"
                                    x-text="counts['{{ $t['key'] }}'] ?? 0"
                                ></span>
                            </button>
                        @endforeach
                    </nav>
                </div>

                {{-- ── Tab: Locations ── --}}
                <div x-show="tab === 'stock'" x-transition.opacity.duration.150ms>
                    @if ($this->stockByLocation->isEmpty())
                        <div class="px-6 py-16 text-center">
                            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-black/[0.04] dark:bg-white/[0.05]">
                                <x-ui.icon name="archive" class="h-6 w-6 text-slate-400" />
                            </div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">No stock on hand</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">This item has no stock in any location.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th class="pl-6">Location</th>
                                        <th>Warehouse</th>
                                        <th>Batch</th>
                                        <th>Status</th>
                                        <th class="text-right">On Hand</th>
                                        <th class="text-right">Allocated</th>
                                        <th class="pr-6 text-right">Available</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($this->stockByLocation as $row)
                                        @php
                                            $onHand    = (float) $row->qty_on_hand;
                                            $allocated = (float) $row->qty_allocated;
                                            $available = $onHand - $allocated;
                                            $pct       = $onHand > 0 ? min(100, round(($allocated / $onHand) * 100)) : 0;
                                        @endphp
                                        <tr>
                                            <td class="pl-6">
                                                <span class="inline-flex items-center gap-2 font-mono text-sm font-semibold text-slate-900 dark:text-white">
                                                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-50 ring-1 ring-inset ring-brand-100 dark:bg-brand-500/10 dark:ring-brand-500/20">
                                                        <x-ui.icon name="map-pin" class="h-3.5 w-3.5 text-brand-500" />
                                                    </span>
                                                    {{ $row->location?->code ?? '—' }}
                                                </span>
                                            </td>
                                            <td class="text-zinc-600 dark:text-zinc-300">{{ $row->virtualWarehouse?->name ?? '—' }}</td>
                                            <td>
                                                @if ($row->batch)
                                                    <span class="rounded-md bg-black/[0.05] px-2 py-0.5 font-mono text-xs text-slate-700 dark:bg-white/[0.08] dark:text-slate-300">{{ $row->batch->batch_no }}</span>
                                                @else
                                                    <span class="text-slate-300 dark:text-white/20">&mdash;</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($row->stock_status)
                                                    <span class="badge badge-neutral capitalize">{{ str_replace('_', ' ', $row->stock_status->value) }}</span>
                                                @else
                                                    <span class="text-slate-300 dark:text-white/20">&mdash;</span>
                                                @endif
                                            </td>
                                            <td class="text-right font-mono font-semibold tabular-nums text-slate-900 dark:text-white">
                                                {{ number_format($onHand, 2) }}
                                            </td>
                                            <td class="text-right tabular-nums">
                                                @if ($allocated > 0)
                                                    <div class="flex flex-col items-end gap-1">
                                                        <span class="font-mono font-medium text-amber-600 dark:text-amber-400">{{ number_format($allocated, 2) }}</span>
                                                        <div class="h-1 w-14 overflow-hidden rounded-full bg-gray-200 dark:bg-white/20">
                                                            <div class="h-full rounded-full bg-amber-400" style="width: {{ $pct }}%"></div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <span class="font-mono text-slate-300 dark:text-white/20">0.00</span>
                                                @endif
                                            </td>
                                            <td class="pr-6 text-right tabular-nums">
                                                <span class="inline-flex rounded-md px-2 py-0.5 font-mono text-sm font-bold {{ $available > 0 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400' }}">
                                                    {{ number_format($available, 2) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- ── Tab: Movements ── --}}
                <div x-show="tab === 'movements'" x-transition.opacity.duration.150ms x-cloak>
                    @if ($this->recentMovements->isEmpty())
                        <div class="px-6 py-16 text-center">
                            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-black/[0.04] dark:bg-white/[0.05]">
                                <x-ui.icon name="arrows" class="h-6 w-6 text-slate-400" />
                            </div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">No movements recorded</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Stock transactions will appear here.</p>
                        </div>
                    @else
                        @php
                            $typeColors = [
                                'receipt'          => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20',
                                'dispatch'         => 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/20',
                                'adjustment'       => 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/20',
                                'count_adjustment' => 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/20',
                                'relocation'       => 'bg-violet-50 text-violet-700 ring-violet-200 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-500/20',
                                'pick'             => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20',
                                'putaway'          => 'bg-teal-50 text-teal-700 ring-teal-200 dark:bg-teal-500/10 dark:text-teal-300 dark:ring-teal-500/20',
                                'reversal'         => 'bg-orange-50 text-orange-700 ring-orange-200 dark:bg-orange-500/10 dark:text-orange-300 dark:ring-orange-500/20',
                                'opening_balance'  => 'bg-black/[0.04] text-slate-600 ring-black/[0.06] dark:bg-white/[0.06] dark:text-slate-300 dark:ring-white/[0.08]',
                            ];
                            $defaultColor = 'bg-black/[0.04] text-slate-600 ring-black/[0.06] dark:bg-white/[0.06] dark:text-slate-300 dark:ring-white/[0.08]';
                        @endphp
                        <div class="overflow-x-auto">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th class="pl-6">Date</th>
                                        <th>Type</th>
                                        <th>Reference</th>
                                        <th>Location</th>
                                        <th>Batch</th>
                                        <th class="pr-6 text-right">Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($this->recentMovements as $mv)
                                        @php
                                            $qty      = (float) $mv->qty;
                                            $typeVal  = $mv->transaction?->type?->value ?? '';
                                            $colorCls = $typeColors[$typeVal] ?? $defaultColor;
                                            $date     = $mv->created_at ? \Illuminate\Support\Carbon::parse($mv->created_at) : null;
                                        @endphp
                                        <tr>
                                            <td class="pl-6 whitespace-nowrap">
                                                @if ($date)
                                                    <div class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $date->format('d M Y') }}</div>
                                                    <div class="font-mono text-xs text-slate-400">{{ $date->format('H:i') }}</div>
                                                @else
                                                    <span class="text-slate-300 dark:text-white/20">&mdash;</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($mv->transaction?->type)
                                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $colorCls }}">
                                                        {{ $mv->transaction->type->label() }}
                                                    </span>
                                                @else
                                                    <span class="text-slate-300 dark:text-white/20">&mdash;</span>
                                                @endif
                                            </td>
                                            <td class="font-mono text-xs text-slate-700 dark:text-slate-300">
                                                {{ $mv->transaction?->reference_no ?? $mv->transaction?->number ?? '—' }}
                                            </td>
                                            <td class="font-mono text-zinc-700 dark:text-zinc-300">
                                                {{ $mv->location?->code ?? '—' }}
                                            </td>
                                            <td>
                                                @if ($mv->batch)
                                                    <span class="rounded-md bg-black/[0.05] px-2 py-0.5 font-mono text-xs text-slate-700 dark:bg-white/[0.08] dark:text-slate-300">{{ $mv->batch->batch_no }}</span>
                                                @else
                                                    <span class="text-slate-300 dark:text-white/20">&mdash;</span>
                                                @endif
                                            </td>
                                            <td class="pr-6 text-right tabular-nums">
                                                <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 font-mono text-sm font-bold {{ $qty >= 0 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400' }}">
                                                    {{ $qty >= 0 ? '+' : '' }}{{ number_format($qty, 2) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($this->recentMovements->count() >= 50)
                            <p class="border-t border-black/[0.05] bg-black/[0.02] px-6 py-3 text-center text-xs font-medium text-slate-500 dark:border-white/[0.06] dark:bg-white/[0.02] dark:text-slate-400">
                                Showing the last 50 movements
                            </p>
                        @endif
                    @endif
                </div>

                {{-- ── Tab: Open Inbound ── --}}
                <div x-show="tab === 'inbound'" x-transition.opacity.duration.150ms x-cloak>
                    @if ($this->openInbound->isEmpty())
                        <div class="px-6 py-16 text-center">
                            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 dark:bg-emerald-500/10">
                                <x-ui.icon name="download" class="h-6 w-6 text-emerald-500" />
                            </div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">No open inbound receipts</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">All inbound GRNs are fully received or closed.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th class="pl-6">GRN No</th>
                                        <th>Status</th>
                                        <th class="text-right">Expected</th>
                                        <th class="text-right">Received</th>
                                        <th class="pr-6">Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($this->openInbound as $line)
                                        @php
                                            $expected    = (float) $line->expected_qty;
                                            $received    = (float) $line->received_good_qty;
                                            $pct         = $expected > 0 ? min(100, round(($received / $expected) * 100)) : 0;
                                            $outstanding = $expected - $received;
                                        @endphp
                                        <tr>
                                            <td class="pl-6 font-mono font-semibold text-slate-900 dark:text-white">{{ $line->grn?->number ?? '—' }}</td>
                                            <td>
                                                @if ($line->grn?->status)
                                                    <span class="badge {{ $line->grn->status->color() }}"><span class="dot"></span>{{ $line->grn->status->label() }}</span>
                                                @else
                                                    <span class="text-slate-300 dark:text-white/20">&mdash;</span>
                                                @endif
                                            </td>
                                            <td class="text-right font-mono tabular-nums text-zinc-700 dark:text-zinc-300">{{ number_format($expected, 2) }}</td>
                                            <td class="text-right font-mono tabular-nums text-zinc-700 dark:text-zinc-300">{{ number_format($received, 2) }}</td>
                                            <td class="pr-6">
                                                <div class="min-w-[160px]">
                                                    <div class="mb-1.5 flex items-center justify-between text-xs">
                                                        <span class="font-semibold tabular-nums text-slate-700 dark:text-slate-300">{{ $pct }}%</span>
                                                        @if ($outstanding > 0)
                                                            <span class="font-mono tabular-nums text-amber-600 dark:text-amber-400">{{ number_format($outstanding, 2) }} pending</span>
                                                        @endif
                                                    </div>
                                                    <div class="h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-white/20">
                                                        <div class="h-full rounded-full bg-gradient-to-r {{ $pct >= 100 ? 'from-emerald-400 to-emerald-600' : 'from-brand-400 to-brand-600' }}" style="width: {{ $pct }}%"></div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- ── Tab: Open Outbound ── --}}
                <div x-show="tab === 'outbound'" x-transition.opacity.duration.150ms x-cloak>
                    @if ($this->openOutbound->isEmpty())
                        <div class="px-6 py-16 text-center">
                            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 dark:bg-brand-500/10">
                                <x-ui.icon name="upload" class="h-6 w-6 text-brand-500" />
                            </div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">No open outbound orders</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">All delivery orders are fully dispatched or cancelled.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th class="pl-6">DO No</th>
                                        <th>Status</th>
                                        <th class="text-right">Ordered</th>
                                        <th class="text-right">Dispatched</th>
                                        <th class="pr-6">Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($this->openOutbound as $line)
                                        @php
                                            $ordered     = (float) $line->requested_qty_base;
                                            $dispatched  = (float) $line->dispatched_qty;
                                            $pct         = $ordered > 0 ? min(100, round(($dispatched / $ordered) * 100)) : 0;
                                            $outstanding = $ordered - $dispatched;
                                        @endphp
                                        <tr>
                                            <td class="pl-6 font-mono font-semibold text-slate-900 dark:text-white">{{ $line->deliveryOrder?->number ?? '—' }}</td>
                                            <td>
                                                @if ($line->status)
                                                    <span class="badge badge-neutral"><span class="dot"></span>{{ $line->status->label() }}</span>
                                                @else
                                                    <span class="text-slate-300 dark:text-white/20">&mdash;</span>
                                                @endif
                                            </td>
                                            <td class="text-right font-mono tabular-nums text-zinc-700 dark:text-zinc-300">{{ number_format($ordered, 2) }}</td>
                                            <td class="text-right font-mono tabular-nums text-zinc-700 dark:text-zinc-300">{{ number_format($dispatched, 2) }}</td>
                                            <td class="pr-6">
                                                <div class="min-w-[160px]">
                                                    <div class="mb-1.5 flex items-center justify-between text-xs">
                                                        <span class="font-semibold tabular-nums text-slate-700 dark:text-slate-300">{{ $pct }}%</span>
                                                        @if ($outstanding > 0)
                                                            <span class="font-mono tabular-nums text-amber-600 dark:text-amber-400">{{ number_format($outstanding, 2) }} pending</span>
                                                        @endif
                                                    </div>
                                                    <div class="h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-white/20">
                                                        <div class="h-full rounded-full bg-gradient-to-r {{ $pct >= 100 ? 'from-emerald-400 to-emerald-600' : 'from-brand-400 to-brand-600' }}" style="width: {{ $pct }}%"></div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- ── Tab: Batches ── --}}
                @if ($item->track_batch)
                    <div x-show="tab === 'batches'" x-transition.opacity.duration.150ms x-cloak>
                        @if ($this->batches->isEmpty())
                            <div class="px-6 py-16 text-center">
                                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-50 dark:bg-amber-500/10">
                                    <x-ui.icon name="list" class="h-6 w-6 text-amber-500" />
                                </div>
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">No batches recorded</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Batches are created on receipt.</p>
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th class="pl-6">Batch No</th>
                                            <th>Manufactured</th>
                                            <th>Expiry</th>
                                            <th class="pr-6 text-right">Stock on Hand</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($this->batches as $batch)
                                            @php
                                                $daysLeft = $batch->expiry_date ? (int) now()->startOfDay()->diffInDays($batch->expiry_date->copy()->startOfDay(), false) : null;
                                                $expired  = $daysLeft !== null && $daysLeft < 0;
                                                $expiring = $daysLeft !== null && ! $expired && $daysLeft <= 30;
                                            @endphp
                                            <tr class="{{ $expired ? 'bg-red-50/30 dark:bg-red-500/[0.04]' : '' }}">
                                                <td class="pl-6 font-mono font-semibold text-slate-900 dark:text-white">{{ $batch->batch_no }}</td>
                                                <td class="tabular-nums text-zinc-600 dark:text-zinc-300">
                                                    {{ $batch->manufactured_date ? $batch->manufactured_date->format('d M Y') : '—' }}
                                                </td>
                                                <td>
                                                    @if ($batch->expiry_date)
                                                        <div class="flex items-center gap-2">
                                                            <span class="tabular-nums {{ $expired ? 'font-semibold text-red-600 dark:text-red-400' : ($expiring ? 'font-medium text-amber-600 dark:text-amber-400' : 'text-zinc-700 dark:text-zinc-300') }}">
                                                                {{ $batch->expiry_date->format('d M Y') }}
                                                            </span>
                                                            @if ($expired)
                                                                <span class="badge badge-danger">Expired</span>
                                                            @elseif ($expiring)
                                                                <span class="badge badge-warning">{{ $daysLeft }}d left</span>
                                                            @endif
                                                        </div>
                                                    @else
                                                        <span class="text-slate-300 dark:text-white/20">&mdash;</span>
                                                    @endif
                                                </td>
                                                <td class="pr-6 text-right tabular-nums">
                                                    <span class="font-mono {{ $batch->qty_on_hand > 0 ? 'font-bold text-slate-900 dark:text-white' : 'text-slate-300 dark:text-white/20' }}">
                                                        {{ number_format($batch->qty_on_hand, 2) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- ── Tab: Serials ── --}}
                @if ($item->track_serial)
                    <div x-show="tab === 'serials'" x-transition.opacity.duration.150ms x-cloak>
                        @if ($this->serials->isEmpty())
                            <div class="px-6 py-16 text-center">
                                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-violet-50 dark:bg-violet-500/10">
                                    <x-ui.icon name="qr-code" class="h-6 w-6 text-violet-500" />
                                </div>
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">No serial numbers recorded</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Serials are captured during receipt.</p>
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th class="pl-6">Serial No</th>
                                            <th>Status</th>
                                            <th>Location</th>
                                            <th>Batch</th>
                                            <th class="pr-6">Last Updated</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($this->serials as $serial)
                                            <tr>
                                                <td class="pl-6 font-mono font-semibold text-slate-900 dark:text-white">{{ $serial->serial_no }}</td>
                                                <td>
                                                    @if ($serial->status)
                                                        <span class="badge badge-{{ $serial->status->color() }}"><span class="dot"></span>{{ $serial->status->label() }}</span>
                                                    @else
                                                        <span class="text-slate-300 dark:text-white/20">&mdash;</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($serial->location)
                                                        <span class="inline-flex items-center gap-1.5 font-mono text-xs text-zinc-700 dark:text-zinc-300">
                                                            <x-ui.icon name="map-pin" class="h-3.5 w-3.5 text-brand-500" />{{ $serial->location->code }}
                                                        </span>
                                                    @else
                                                        <span class="text-slate-300 dark:text-white/20">&mdash;</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($serial->batch)
                                                        <span class="rounded-md bg-black/[0.05] px-2 py-0.5 font-mono text-xs text-slate-700 dark:bg-white/[0.08] dark:text-slate-300">{{ $serial->batch->batch_no }}</span>
                                                    @else
                                                        <span class="text-slate-300 dark:text-white/20">&mdash;</span>
                                                    @endif
                                                </td>
                                                <td class="pr-6 whitespace-nowrap font-mono text-xs tabular-nums text-slate-500 dark:text-slate-400">
                                                    {{ $serial->updated_at ? \Illuminate\Support\Carbon::parse($serial->updated_at)->format('d M Y, H:i') : '—' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if ($this->serials->count() >= 200)
                                <p class="border-t border-black/[0.05] bg-black/[0.02] px-6 py-3 text-center text-xs font-medium text-slate-500 dark:border-white/[0.06] dark:bg-white/[0.02] dark:text-slate-400">
                                    Showing first 200 serial numbers
                                </p>
                            @endif
                        @endif
                    </div>
                @endif

            </div>{{-- end tabbed card --}}
        </div>{{-- end x-data --}}

    @endif

</div>
