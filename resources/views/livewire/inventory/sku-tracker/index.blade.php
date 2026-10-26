<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">SKU Tracker</h2>
            <p class="page-sub">Full visibility into any item  stock, movements, open orders and more.</p>
        </div>
    </div>

    {{-- ══ SEARCH HERO (no item selected) ══════════════════════════════════════ --}}
    @if($this->selectedItem === null)

        {{-- Hero search card --}}
        <div class="card overflow-hidden">
            <div class="relative bg-gradient-to-br from-brand-50 via-white to-slate-50 dark:from-brand-950/40 dark:via-slate-800/60 dark:to-slate-800/30 px-6 py-10 sm:px-10 sm:py-14 text-center">
                {{-- Decorative circles --}}
                <div class="pointer-events-none absolute -right-10 -top-10 h-48 w-48 rounded-full bg-brand-100/50 dark:bg-brand-900/20 blur-3xl"></div>
                <div class="pointer-events-none absolute -left-10 -bottom-10 h-48 w-48 rounded-full bg-slate-100/80 dark:bg-slate-700/20 blur-3xl"></div>

                <div class="relative">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-white dark:bg-slate-800 shadow-md ring-1 ring-slate-200/60 dark:ring-white/10">
                        <x-ui.icon name="search" class="h-6 w-6 text-brand-500" />
                    </div>
                    <h3 class="mb-1 text-base font-semibold text-slate-800 dark:text-white">Find any SKU instantly</h3>
                    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">Search by code, barcode, part number or item name</p>

                    <div class="relative mx-auto max-w-xl">
                        <x-ui.icon name="search" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            wire:model.live.debounce.300ms="search"
                            type="search"
                            placeholder="e.g. SKU-001, barcode, part number or name…"
                            class="input w-full pl-11 py-3 text-sm shadow-sm"
                            autofocus
                        />
                    </div>
                </div>
            </div>
        </div>

        {{-- Results / idle state --}}
        @if(strlen(trim($search)) >= 2)
            @if($this->results->isEmpty())
                <div class="card p-12 text-center">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-700/50">
                        <x-ui.icon name="search" class="h-6 w-6 text-slate-400" />
                    </div>
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-300">No items matched <span class="font-semibold">"{{ $search }}"</span></p>
                    <p class="mt-1 text-xs text-slate-400">Try a SKU, barcode, part number or part of the name.</p>
                </div>
            @else
                <div class="card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-white/40 dark:border-white/[0.08] px-5 py-3">
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Results</span>
                        <span class="rounded-full bg-brand-100 dark:bg-brand-900/40 px-2.5 py-0.5 text-xs font-semibold text-brand-700 dark:text-brand-300">
                            {{ $this->results->count() }}
                        </span>
                    </div>
                    <ul class="divide-y divide-slate-100 dark:divide-white/[0.05]">
                        @foreach($this->results as $item)
                            <li>
                                <button
                                    wire:click="selectItem({{ $item->id }})"
                                    class="group flex w-full items-center gap-4 px-5 py-4 text-left transition-all hover:bg-brand-50/60 dark:hover:bg-brand-950/20"
                                >
                                    {{-- Avatar --}}
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white dark:bg-slate-700 shadow-sm ring-1 ring-slate-200/70 dark:ring-white/10">
                                        <x-ui.icon name="archive" class="h-5 w-5 text-brand-500 dark:text-brand-400" />
                                    </div>

                                    {{-- Identity --}}
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2.5">
                                            <span class="font-mono text-sm font-bold text-slate-900 dark:text-white">{{ $item->sku }}</span>
                                            @if(! $item->is_active)
                                                <span class="badge badge-neutral text-xs">Inactive</span>
                                            @endif
                                        </div>
                                        <div class="mt-0.5 truncate text-sm text-slate-500 dark:text-slate-400">{{ $item->name }}</div>
                                        @if($item->part_number)
                                            <div class="mt-0.5 text-xs text-slate-400">PN: {{ $item->part_number }}</div>
                                        @endif
                                    </div>

                                    {{-- Meta --}}
                                    <div class="hidden shrink-0 flex-col items-end gap-1 sm:flex">
                                        @if($item->category)
                                            <span class="rounded-md bg-slate-100 dark:bg-slate-700/60 px-2 py-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $item->category->name }}</span>
                                        @endif
                                        @if($item->baseUom)
                                            <span class="rounded-md bg-brand-100 dark:bg-brand-900/40 px-2 py-0.5 text-xs font-medium text-brand-700 dark:text-brand-300">{{ $item->baseUom->code }}</span>
                                        @endif
                                    </div>

                                    <x-ui.icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-300 transition-transform group-hover:translate-x-0.5 dark:text-slate-600" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif

    {{-- ══ ITEM DETAIL ══════════════════════════════════════════════════════════ --}}
    @else
        @php $item = $this->selectedItem; @endphp

        <div x-data="{
            tab: 'stock',
            counts: {
                stock:     {{ $this->stockByLocation->count() }},
                movements: {{ $this->recentMovements->count() }},
                inbound:   {{ $this->openInbound->count() }},
                outbound:  {{ $this->openOutbound->count() }},
                @if($item->track_batch) batches: {{ $this->batches->count() }}, @endif
                @if($item->track_serial) serials: {{ $this->serials->count() }}, @endif
            }
        }" class="space-y-5">

            {{-- ── Item Header Card ─────────────────────────────────────────── --}}
            <div class="card relative overflow-hidden">
                {{-- Accent bar --}}
                <div class="absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-brand-400 to-brand-600 rounded-l-[inherit]"></div>
                {{-- Background watermark --}}
                <div class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 opacity-[0.04] dark:opacity-[0.06]">
                    <x-ui.icon name="archive" class="h-32 w-32 text-brand-600" />
                </div>

                <div class="pl-6 pr-5 py-5">
                    {{-- Back link --}}
                    <button wire:click="clearSelection"
                        class="mb-3 inline-flex items-center gap-1.5 text-xs font-semibold text-slate-400 hover:text-brand-600 dark:hover:text-brand-400 transition-colors">
                        <x-ui.icon name="arrow-left" class="h-3.5 w-3.5" />
                        Back to search
                    </button>

                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                        {{-- Item icon --}}
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 shadow-md shadow-brand-200 dark:shadow-brand-900/30">
                            <x-ui.icon name="archive" class="h-7 w-7 text-white" />
                        </div>

                        {{-- Identity --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-mono text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $item->sku }}</h3>
                                @if(! $item->is_active)
                                    <span class="badge badge-neutral">Inactive</span>
                                @endif
                            </div>
                            <p class="mt-0.5 text-base font-medium text-slate-600 dark:text-slate-300">{{ $item->name }}</p>
                            @if($item->part_number)
                                <p class="mt-0.5 text-xs text-slate-400">Part No: <span class="font-mono">{{ $item->part_number }}</span></p>
                            @endif
                            @if($item->description)
                                <p class="mt-1.5 text-xs text-slate-400 dark:text-slate-500 line-clamp-2">{{ $item->description }}</p>
                            @endif
                        </div>

                        {{-- Right-side chips --}}
                        <div class="flex shrink-0 flex-wrap gap-1.5">
                            @if($item->category)
                                <span class="inline-flex items-center gap-1 rounded-lg bg-slate-100 dark:bg-slate-700/60 px-2.5 py-1 text-xs font-medium text-slate-600 dark:text-slate-300">
                                    <x-ui.icon name="tag" class="h-3 w-3" />{{ $item->category->name }}
                                </span>
                            @endif
                            @if($item->baseUom)
                                <span class="inline-flex items-center gap-1 rounded-lg bg-brand-100 dark:bg-brand-900/40 px-2.5 py-1 text-xs font-semibold text-brand-700 dark:text-brand-300">
                                    {{ $item->baseUom->code }}
                                </span>
                            @endif
                            @if($item->track_batch)
                                <span class="inline-flex items-center gap-1 rounded-lg bg-amber-100 dark:bg-amber-900/30 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:text-amber-300">
                                    <x-ui.icon name="list" class="h-3 w-3" />Batch
                                </span>
                            @endif
                            @if($item->track_expiry)
                                <span class="inline-flex items-center gap-1 rounded-lg bg-amber-100 dark:bg-amber-900/30 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:text-amber-300">
                                    <x-ui.icon name="clock" class="h-3 w-3" />Expiry
                                </span>
                            @endif
                            @if($item->track_serial)
                                <span class="inline-flex items-center gap-1 rounded-lg bg-brand-100 dark:bg-brand-900/40 px-2.5 py-1 text-xs font-semibold text-brand-700 dark:text-brand-300">
                                    <x-ui.icon name="qr-code" class="h-3 w-3" />Serial
                                </span>
                            @endif
                            @if($item->is_hazardous)
                                <span class="inline-flex items-center gap-1 rounded-lg bg-red-100 dark:bg-red-900/30 px-2.5 py-1 text-xs font-semibold text-red-700 dark:text-red-300">
                                    <x-ui.icon name="alert" class="h-3 w-3" />Hazardous
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Stock KPI Cards ──────────────────────────────────────────── --}}
            @php
                $summary = $this->stockSummary;
                $uom     = $item->baseUom?->code ?? '';
            @endphp
            <div class="grid grid-cols-3 gap-4">
                <x-ui.kpi-card
                    label="On Hand"
                    :value="number_format($summary['on_hand'], 2)"
                    :hint="$uom"
                    tone="brand"
                    icon="archive"
                />
                <x-ui.kpi-card
                    label="Allocated"
                    :value="number_format($summary['allocated'], 2)"
                    :hint="$uom"
                    :tone="$summary['allocated'] > 0 ? 'warning' : 'neutral'"
                    icon="lock"
                />
                <x-ui.kpi-card
                    label="Available"
                    :value="number_format($summary['available'], 2)"
                    :hint="$uom"
                    :tone="$summary['available'] > 0 ? 'success' : 'danger'"
                    icon="badge-check"
                />
            </div>

            {{-- ── Tabbed Detail Card ───────────────────────────────────────── --}}
            <div class="card overflow-hidden">

                {{-- Tab bar --}}
                <div class="flex overflow-x-auto border-b border-white/40 dark:border-white/[0.08] bg-slate-50/50 dark:bg-white/[0.02] px-4 gap-1 pt-2">
                    @php
                        $tabs = [
                            ['key' => 'stock',     'label' => 'Locations',     'icon' => 'map-pin'],
                            ['key' => 'movements', 'label' => 'Movements',      'icon' => 'arrows'],
                            ['key' => 'inbound',   'label' => 'Open Inbound',   'icon' => 'download'],
                            ['key' => 'outbound',  'label' => 'Open Outbound',  'icon' => 'upload'],
                        ];
                        if ($item->track_batch)  $tabs[] = ['key' => 'batches', 'label' => 'Batches', 'icon' => 'list'];
                        if ($item->track_serial) $tabs[] = ['key' => 'serials', 'label' => 'Serials', 'icon' => 'qr-code'];
                    @endphp

                    @foreach($tabs as $t)
                        <button
                            @click="tab = '{{ $t['key'] }}'"
                            :class="tab === '{{ $t['key'] }}'
                                ? 'border-brand-500 text-brand-600 dark:text-brand-400 bg-white dark:bg-slate-800/60'
                                : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-white/60 dark:hover:bg-white/[0.04]'"
                            class="flex items-center gap-1.5 whitespace-nowrap rounded-t-lg border-b-2 px-3.5 pb-2.5 pt-2 text-sm font-medium transition-all"
                        >
                            <x-ui.icon name="{{ $t['icon'] }}" class="h-3.5 w-3.5" />
                            {{ $t['label'] }}
                            <span
                                :class="tab === '{{ $t['key'] }}'
                                    ? 'bg-brand-100 text-brand-700 dark:bg-brand-900/50 dark:text-brand-300'
                                    : 'bg-slate-100 text-slate-500 dark:bg-slate-700/50 dark:text-slate-400'"
                                class="ml-0.5 rounded-full px-1.5 py-0.5 text-xs font-semibold tabular-nums"
                                x-text="counts['{{ $t['key'] }}'] ?? 0"
                            ></span>
                        </button>
                    @endforeach
                </div>

                {{-- ── Tab: Stock Locations ──────────────────────────────────── --}}
                <div x-show="tab === 'stock'">
                    @if($this->stockByLocation->isEmpty())
                        <div class="py-14 text-center">
                            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-700/50">
                                <x-ui.icon name="archive" class="h-6 w-6 text-slate-400" />
                            </div>
                            <p class="text-sm font-medium text-slate-500">No stock on hand</p>
                            <p class="mt-1 text-xs text-slate-400">This item has no stock in any location.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-slate-100 dark:border-white/[0.06] bg-slate-50/80 dark:bg-white/[0.02]">
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Location</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Warehouse</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Batch</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">On Hand</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Allocated</th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Available</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50 dark:divide-white/[0.04]">
                                    @foreach($this->stockByLocation as $row)
                                        @php
                                            $onHand    = (float) $row->qty_on_hand;
                                            $allocated = (float) $row->qty_allocated;
                                            $available = $onHand - $allocated;
                                            $pct       = $onHand > 0 ? round(($allocated / $onHand) * 100) : 0;
                                        @endphp
                                        <tr class="hover:bg-slate-50/80 dark:hover:bg-white/[0.02] transition-colors">
                                            <td class="px-5 py-3.5">
                                                <span class="inline-flex items-center gap-1.5 font-mono text-sm font-semibold text-slate-800 dark:text-white">
                                                    <x-ui.icon name="map-pin" class="h-3.5 w-3.5 text-brand-400 shrink-0" />
                                                    {{ $row->location?->code ?? '—' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3.5 text-sm text-slate-500 dark:text-slate-400">{{ $row->virtualWarehouse?->name ?? '—' }}</td>
                                            <td class="px-4 py-3.5">
                                                @if($row->batch)
                                                    <span class="font-mono text-xs bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 rounded px-1.5 py-0.5">{{ $row->batch->batch_no }}</span>
                                                @else
                                                    <span class="text-slate-300 dark:text-slate-600">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3.5">
                                                @if($row->stock_status)
                                                    <span class="badge badge-neutral text-xs">{{ $row->stock_status->value }}</span>
                                                @else
                                                    <span class="text-slate-300 dark:text-slate-600">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3.5 text-right font-mono text-sm font-medium text-slate-700 dark:text-slate-200">
                                                {{ number_format($onHand, 2) }}
                                            </td>
                                            <td class="px-4 py-3.5 text-right">
                                                @if($allocated > 0)
                                                    <div class="flex flex-col items-end gap-1">
                                                        <span class="font-mono text-sm font-medium text-amber-600 dark:text-amber-400">{{ number_format($allocated, 2) }}</span>
                                                        <div class="h-1 w-16 rounded-full bg-slate-100 dark:bg-slate-700">
                                                            <div class="h-1 rounded-full bg-amber-400" style="width: {{ $pct }}%"></div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <span class="font-mono text-sm text-slate-300 dark:text-slate-600">0.00</span>
                                                @endif
                                            </td>
                                            <td class="px-5 py-3.5 text-right">
                                                <span class="font-mono text-sm font-bold {{ $available > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400' }}">
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

                {{-- ── Tab: Movements ────────────────────────────────────────── --}}
                <div x-show="tab === 'movements'" x-cloak>
                    @if($this->recentMovements->isEmpty())
                        <div class="py-14 text-center">
                            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-700/50">
                                <x-ui.icon name="arrows" class="h-6 w-6 text-slate-400" />
                            </div>
                            <p class="text-sm font-medium text-slate-500">No movements recorded</p>
                        </div>
                    @else
                        @php
                            $typeColors = [
                                'receipt'           => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
                                'dispatch'          => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
                                'adjustment'        => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
                                'count_adjustment'  => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
                                'relocation'        => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
                                'pick'              => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                                'putaway'           => 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300',
                                'reversal'          => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
                                'opening_balance'   => 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300',
                            ];
                        @endphp
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-slate-100 dark:border-white/[0.06] bg-slate-50/80 dark:bg-white/[0.02]">
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Reference</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Location</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Batch</th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Qty</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50 dark:divide-white/[0.04]">
                                    @foreach($this->recentMovements as $mv)
                                        @php
                                            $qty      = (float) $mv->qty;
                                            $typeVal  = $mv->transaction?->type?->value ?? '';
                                            $colorCls = $typeColors[$typeVal] ?? 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
                                        @endphp
                                        <tr class="hover:bg-slate-50/80 dark:hover:bg-white/[0.02] transition-colors">
                                            <td class="px-5 py-3.5 whitespace-nowrap text-xs text-slate-500">
                                                {{ $mv->created_at ? \Illuminate\Support\Carbon::parse($mv->created_at)->format('d M Y') : '—' }}
                                                <div class="text-slate-400">{{ $mv->created_at ? \Illuminate\Support\Carbon::parse($mv->created_at)->format('H:i') : '' }}</div>
                                            </td>
                                            <td class="px-4 py-3.5">
                                                @if($mv->transaction?->type)
                                                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold {{ $colorCls }}">
                                                        {{ $mv->transaction->type->label() }}
                                                    </span>
                                                @else
                                                    <span class="text-slate-300">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3.5 font-mono text-xs text-slate-600 dark:text-slate-300">
                                                {{ $mv->transaction?->reference_no ?? $mv->transaction?->number ?? '—' }}
                                            </td>
                                            <td class="px-4 py-3.5 text-sm text-slate-600 dark:text-slate-300">
                                                {{ $mv->location?->code ?? '—' }}
                                            </td>
                                            <td class="px-4 py-3.5">
                                                @if($mv->batch)
                                                    <span class="font-mono text-xs text-slate-500">{{ $mv->batch->batch_no }}</span>
                                                @else
                                                    <span class="text-slate-300 dark:text-slate-600">—</span>
                                                @endif
                                            </td>
                                            <td class="px-5 py-3.5 text-right">
                                                <span class="font-mono text-sm font-bold {{ $qty >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400' }}">
                                                    {{ $qty >= 0 ? '+' : '' }}{{ number_format($qty, 2) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($this->recentMovements->count() >= 50)
                            <div class="border-t border-slate-100 dark:border-white/[0.06] px-5 py-3 text-center text-xs text-slate-400">
                                Showing the last 50 movements
                            </div>
                        @endif
                    @endif
                </div>

                {{-- ── Tab: Open Inbound ─────────────────────────────────────── --}}
                <div x-show="tab === 'inbound'" x-cloak>
                    @if($this->openInbound->isEmpty())
                        <div class="py-14 text-center">
                            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-900/20">
                                <x-ui.icon name="download" class="h-6 w-6 text-emerald-400" />
                            </div>
                            <p class="text-sm font-medium text-slate-500">No open inbound receipts</p>
                            <p class="mt-1 text-xs text-slate-400">All inbound GRNs are fully received or closed.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-slate-100 dark:border-white/[0.06] bg-slate-50/80 dark:bg-white/[0.02]">
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">GRN No</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Expected</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Received</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Progress</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50 dark:divide-white/[0.04]">
                                    @foreach($this->openInbound as $line)
                                        @php
                                            $expected    = (float) $line->expected_qty;
                                            $received    = (float) $line->received_good_qty;
                                            $pct         = $expected > 0 ? min(100, round(($received / $expected) * 100)) : 0;
                                            $outstanding = $expected - $received;
                                        @endphp
                                        <tr class="hover:bg-slate-50/80 dark:hover:bg-white/[0.02] transition-colors">
                                            <td class="px-5 py-3.5">
                                                <span class="font-mono text-sm font-semibold text-slate-800 dark:text-white">{{ $line->grn?->number ?? '—' }}</span>
                                            </td>
                                            <td class="px-4 py-3.5">
                                                @if($line->grn?->status)
                                                    <span class="badge {{ $line->grn->status->color() }}"><span class="dot"></span>{{ $line->grn->status->label() }}</span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="px-4 py-3.5 text-right font-mono text-sm text-slate-600 dark:text-slate-300">{{ number_format($expected, 2) }}</td>
                                            <td class="px-4 py-3.5 text-right font-mono text-sm text-slate-600 dark:text-slate-300">{{ number_format($received, 2) }}</td>
                                            <td class="px-5 py-3.5">
                                                <div class="flex items-center gap-2.5 min-w-[140px]">
                                                    <div class="flex-1 h-1.5 rounded-full bg-slate-100 dark:bg-slate-700">
                                                        <div class="h-1.5 rounded-full {{ $pct >= 100 ? 'bg-emerald-500' : 'bg-brand-500' }}" style="width: {{ $pct }}%"></div>
                                                    </div>
                                                    <span class="shrink-0 text-xs font-semibold text-slate-500">{{ $pct }}%</span>
                                                    @if($outstanding > 0)
                                                        <span class="shrink-0 text-xs text-amber-600 dark:text-amber-400 font-mono">−{{ number_format($outstanding, 2) }}</span>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- ── Tab: Open Outbound ───────────────────────────────────── --}}
                <div x-show="tab === 'outbound'" x-cloak>
                    @if($this->openOutbound->isEmpty())
                        <div class="py-14 text-center">
                            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-900/20">
                                <x-ui.icon name="upload" class="h-6 w-6 text-brand-400" />
                            </div>
                            <p class="text-sm font-medium text-slate-500">No open outbound orders</p>
                            <p class="mt-1 text-xs text-slate-400">All delivery orders are fully dispatched or cancelled.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-slate-100 dark:border-white/[0.06] bg-slate-50/80 dark:bg-white/[0.02]">
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">DO No</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Ordered</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Dispatched</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Progress</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50 dark:divide-white/[0.04]">
                                    @foreach($this->openOutbound as $line)
                                        @php
                                            $ordered     = (float) $line->requested_qty_base;
                                            $dispatched  = (float) $line->dispatched_qty;
                                            $pct         = $ordered > 0 ? min(100, round(($dispatched / $ordered) * 100)) : 0;
                                            $outstanding = $ordered - $dispatched;
                                        @endphp
                                        <tr class="hover:bg-slate-50/80 dark:hover:bg-white/[0.02] transition-colors">
                                            <td class="px-5 py-3.5">
                                                <span class="font-mono text-sm font-semibold text-slate-800 dark:text-white">{{ $line->deliveryOrder?->number ?? '—' }}</span>
                                            </td>
                                            <td class="px-4 py-3.5">
                                                @if($line->status)
                                                    <span class="badge badge-neutral"><span class="dot"></span>{{ $line->status->label() }}</span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="px-4 py-3.5 text-right font-mono text-sm text-slate-600 dark:text-slate-300">{{ number_format($ordered, 2) }}</td>
                                            <td class="px-4 py-3.5 text-right font-mono text-sm text-slate-600 dark:text-slate-300">{{ number_format($dispatched, 2) }}</td>
                                            <td class="px-5 py-3.5">
                                                <div class="flex items-center gap-2.5 min-w-[140px]">
                                                    <div class="flex-1 h-1.5 rounded-full bg-slate-100 dark:bg-slate-700">
                                                        <div class="h-1.5 rounded-full {{ $pct >= 100 ? 'bg-emerald-500' : 'bg-brand-500' }}" style="width: {{ $pct }}%"></div>
                                                    </div>
                                                    <span class="shrink-0 text-xs font-semibold text-slate-500">{{ $pct }}%</span>
                                                    @if($outstanding > 0)
                                                        <span class="shrink-0 text-xs text-amber-600 dark:text-amber-400 font-mono">−{{ number_format($outstanding, 2) }}</span>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- ── Tab: Batches ──────────────────────────────────────────── --}}
                @if($item->track_batch)
                    <div x-show="tab === 'batches'" x-cloak>
                        @if($this->batches->isEmpty())
                            <div class="py-14 text-center">
                                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-900/20">
                                    <x-ui.icon name="list" class="h-6 w-6 text-amber-400" />
                                </div>
                                <p class="text-sm font-medium text-slate-500">No batches recorded</p>
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-100 dark:border-white/[0.06] bg-slate-50/80 dark:bg-white/[0.02]">
                                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Batch No</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Manufactured</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Expiry</th>
                                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Stock on Hand</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50 dark:divide-white/[0.04]">
                                        @foreach($this->batches as $batch)
                                            @php
                                                $expired  = $batch->expiry_date && $batch->expiry_date->isPast();
                                                $expiring = !$expired && $batch->expiry_date && $batch->expiry_date->diffInDays(now()) <= 30;
                                                $daysLeft = $batch->expiry_date ? now()->diffInDays($batch->expiry_date, false) : null;
                                            @endphp
                                            <tr class="hover:bg-slate-50/80 dark:hover:bg-white/[0.02] transition-colors">
                                                <td class="px-5 py-3.5">
                                                    <span class="font-mono text-sm font-semibold text-slate-800 dark:text-white">{{ $batch->batch_no }}</span>
                                                </td>
                                                <td class="px-4 py-3.5 text-sm text-slate-500">
                                                    {{ $batch->manufactured_date?->format('d M Y') ?? '—' }}
                                                </td>
                                                <td class="px-4 py-3.5">
                                                    @if($batch->expiry_date)
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-sm {{ $expired ? 'text-red-600 dark:text-red-400 font-semibold' : ($expiring ? 'text-amber-600 dark:text-amber-400 font-medium' : 'text-slate-600 dark:text-slate-300') }}">
                                                                {{ $batch->expiry_date->format('d M Y') }}
                                                            </span>
                                                            @if($expired)
                                                                <span class="badge badge-danger text-xs">Expired</span>
                                                            @elseif($expiring)
                                                                <span class="badge badge-warning text-xs">{{ $daysLeft }}d left</span>
                                                            @endif
                                                        </div>
                                                    @else
                                                        <span class="text-slate-300 dark:text-slate-600">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-5 py-3.5 text-right">
                                                    <span class="font-mono text-sm {{ $batch->qty_on_hand > 0 ? 'font-bold text-slate-800 dark:text-white' : 'text-slate-300 dark:text-slate-600' }}">
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

                {{-- ── Tab: Serials ──────────────────────────────────────────── --}}
                @if($item->track_serial)
                    <div x-show="tab === 'serials'" x-cloak>
                        @if($this->serials->isEmpty())
                            <div class="py-14 text-center">
                                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-900/20">
                                    <x-ui.icon name="qr-code" class="h-6 w-6 text-brand-400" />
                                </div>
                                <p class="text-sm font-medium text-slate-500">No serial numbers recorded</p>
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-100 dark:border-white/[0.06] bg-slate-50/80 dark:bg-white/[0.02]">
                                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Serial No</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Location</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Batch</th>
                                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Last Updated</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50 dark:divide-white/[0.04]">
                                        @foreach($this->serials as $serial)
                                            <tr class="hover:bg-slate-50/80 dark:hover:bg-white/[0.02] transition-colors">
                                                <td class="px-5 py-3.5">
                                                    <span class="font-mono text-sm font-semibold text-slate-800 dark:text-white">{{ $serial->serial_no }}</span>
                                                </td>
                                                <td class="px-4 py-3.5">
                                                    @if($serial->status)
                                                        <span class="badge badge-{{ $serial->status->color() }}">
                                                            <span class="dot"></span>{{ $serial->status->label() }}
                                                        </span>
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3.5">
                                                    @if($serial->location)
                                                        <span class="inline-flex items-center gap-1 font-mono text-xs text-slate-600 dark:text-slate-300">
                                                            <x-ui.icon name="map-pin" class="h-3 w-3 text-slate-400" />{{ $serial->location->code }}
                                                        </span>
                                                    @else
                                                        <span class="text-slate-300 dark:text-slate-600">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3.5">
                                                    @if($serial->batch)
                                                        <span class="font-mono text-xs bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 rounded px-1.5 py-0.5">{{ $serial->batch->batch_no }}</span>
                                                    @else
                                                        <span class="text-slate-300 dark:text-slate-600">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-5 py-3.5 text-xs text-slate-400">
                                                    {{ $serial->updated_at ? \Illuminate\Support\Carbon::parse($serial->updated_at)->format('d M Y, H:i') : '—' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if($this->serials->count() >= 200)
                                <div class="border-t border-slate-100 dark:border-white/[0.06] px-5 py-3 text-center text-xs text-slate-400">
                                    Showing first 200 serial numbers
                                </div>
                            @endif
                        @endif
                    </div>
                @endif

            </div>{{-- end tabbed card --}}
        </div>{{-- end x-data --}}

    @endif

</div>
