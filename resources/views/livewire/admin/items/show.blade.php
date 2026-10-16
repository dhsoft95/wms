<div class="space-y-6">

    {{-- Page Header --}}
    <div class="flex flex-col gap-5 pb-6 sm:flex-row sm:items-center sm:justify-between
                border-b border-white/40 dark:border-white/[0.08]">

        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="truncate text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                    {{ $item->name }}
                </h2>
                <span class="badge badge-slate font-mono text-xs">{{ $item->sku }}</span>
                @if ($item->is_active)
                    <span class="badge badge-green"><span class="dot"></span> Active</span>
                @else
                    <span class="badge badge-red">Inactive</span>
                @endif
            </div>
            @if ($item->description)
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $item->description }}</p>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
            <a href="{{ route('admin.items.index') }}" wire:navigate class="btn btn-secondary btn-sm">
                <x-ui.icon name="arrows" class="h-4 w-4" /> Back
            </a>
            @can('items.edit')
                <a href="{{ route('admin.items.edit', $item) }}" wire:navigate class="btn btn-secondary">
                    <x-ui.icon name="cog" class="h-4 w-4" /> Edit Item
                </a>
            @endcan
        </div>
    </div>

    {{-- Tab Bar --}}
    <div class="flex gap-1 overflow-x-auto rounded-xl
                bg-white/40 dark:bg-white/[0.06]
                border border-white/50 dark:border-white/[0.08]
                p-1">
        @foreach ([
            'summary'  => 'Summary',
            'uoms'     => 'Units & Packing',
            'barcodes' => 'Barcodes',
            'tracking' => 'Tracking & Flags',
            'stock'    => 'Stock',
            'activity' => 'Activity',
        ] as $tab => $label)
            <button type="button"
                    wire:click="$set('activeTab', '{{ $tab }}')"
                    class="shrink-0 rounded-lg px-4 py-2 text-sm font-medium transition
                           {{ $activeTab === $tab
                               ? 'bg-white dark:bg-white/[0.12] text-slate-900 dark:text-white shadow-sm'
                               : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ── SUMMARY TAB ──────────────────────────────────────────────────────── --}}
    @if ($activeTab === 'summary')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- Core Details --}}
        <section class="card overflow-hidden">
            <div class="card-header py-5">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl
                                bg-simba-50 dark:bg-simba-500/15
                                text-simba-600 dark:text-simba-400">
                        <x-ui.icon name="package" class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Core Details</h3>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">SKU, naming, and classification</p>
                    </div>
                </div>
            </div>

            <dl class="divide-y divide-white/50 dark:divide-white/[0.06] px-5">

                <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                    <dt class="section-label">SKU</dt>
                    <dd class="font-mono text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $item->sku }}</dd>
                </div>

                <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                    <dt class="section-label">Name</dt>
                    <dd class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $item->name }}</dd>
                </div>

                <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-start">
                    <dt class="section-label">Description</dt>
                    <dd class="text-sm text-slate-700 dark:text-slate-300">
                        {{ $item->description ?? '—' }}
                    </dd>
                </div>

                <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                    <dt class="section-label">Category</dt>
                    <dd class="text-sm text-slate-700 dark:text-slate-300">
                        {{ $item->category?->name ?? '—' }}
                    </dd>
                </div>

                <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                    <dt class="section-label">Base UoM</dt>
                    <dd>
                        @if ($item->baseUom)
                            <span class="badge badge-slate font-mono">{{ $item->baseUom->code }}</span>
                            <span class="ml-2 text-sm text-slate-500 dark:text-slate-400">{{ $item->baseUom->name }}</span>
                        @else
                            <span class="text-sm text-slate-400">—</span>
                        @endif
                    </dd>
                </div>

                <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                    <dt class="section-label">Default Warehouse</dt>
                    <dd class="text-sm text-slate-700 dark:text-slate-300">
                        {{ $item->defaultVirtualWarehouse?->name ?? '—' }}
                    </dd>
                </div>

            </dl>
        </section>

        {{-- Dimensions & Stock --}}
        <div class="space-y-6">

            <section class="card overflow-hidden">
                <div class="card-header py-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl
                                    bg-simba-50 dark:bg-simba-500/15
                                    text-simba-600 dark:text-simba-400">
                            <x-ui.icon name="arrows" class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">Dimensions &amp; Weight</h3>
                        </div>
                    </div>
                </div>

                <dl class="divide-y divide-white/50 dark:divide-white/[0.06] px-5">

                    <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                        <dt class="section-label">L × W × H</dt>
                        <dd class="font-mono text-sm text-slate-700 dark:text-slate-300">
                            @if ($item->length || $item->width || $item->height)
                                {{ $item->length ?? '—' }} × {{ $item->width ?? '—' }} × {{ $item->height ?? '—' }}
                            @else
                                —
                            @endif
                        </dd>
                    </div>

                    <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                        <dt class="section-label">Weight</dt>
                        <dd class="font-mono text-sm text-slate-700 dark:text-slate-300">
                            {{ $item->weight ?? '—' }}
                        </dd>
                    </div>

                </dl>
            </section>

            <section class="card overflow-hidden">
                <div class="card-header py-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl
                                    bg-simba-50 dark:bg-simba-500/15
                                    text-simba-600 dark:text-simba-400">
                            <x-ui.icon name="refresh" class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">Stock Thresholds</h3>
                        </div>
                    </div>
                </div>

                <dl class="divide-y divide-white/50 dark:divide-white/[0.06] px-5">

                    <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                        <dt class="section-label">Min Stock</dt>
                        <dd class="font-mono text-sm text-slate-700 dark:text-slate-300">
                            {{ $item->min_stock ?? '—' }}
                            @if ($item->min_stock && $item->baseUom)
                                <span class="ml-1 text-xs text-slate-400">{{ $item->baseUom->code }}</span>
                            @endif
                        </dd>
                    </div>

                    <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                        <dt class="section-label">Max Stock</dt>
                        <dd class="font-mono text-sm text-slate-700 dark:text-slate-300">
                            {{ $item->max_stock ?? '—' }}
                            @if ($item->max_stock && $item->baseUom)
                                <span class="ml-1 text-xs text-slate-400">{{ $item->baseUom->code }}</span>
                            @endif
                        </dd>
                    </div>

                </dl>
            </section>

        </div>
    </div>
    @endif

    {{-- ── UNITS & PACKING TAB ──────────────────────────────────────────────── --}}
    @if ($activeTab === 'uoms')
    <div class="card overflow-hidden">
        <div class="card-header">
            <h3 class="card-title">
                <x-ui.icon name="refresh" class="w-4 h-4" />
                Units of Measure
            </h3>
        </div>

        <x-ui.table class="min-w-[600px]">
                <thead class="text-[11px] font-semibold uppercase tracking-wider
                              text-slate-500 dark:text-slate-400
                              bg-white/[0.45] dark:bg-white/[0.04]
                              border-b border-white/50 dark:border-white/[0.07]">
                    <tr>
                        <th class="px-5 py-3">Code</th>
                        <th class="px-5 py-3">Name</th>
                        <th class="px-5 py-3">Factor to Base</th>
                        <th class="px-5 py-3 text-center">Default Purchase</th>
                        <th class="px-5 py-3 text-center">Default Issue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/50 dark:divide-white/[0.06]">
                @forelse ($item->uoms as $uomRow)
                    <tr wire:key="uom-{{ $uomRow->id }}"
                        class="transition hover:bg-white/30 dark:hover:bg-white/[0.04]">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-semibold text-slate-800 dark:text-slate-200">
                                    {{ $uomRow->uom?->code ?? '—' }}
                                </span>
                                @if ((string) $uomRow->uom_id === (string) $item->base_uom_id)
                                    <span class="badge badge-slate text-[10px]">Base</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-5 py-3.5 text-slate-600 dark:text-slate-300">
                            {{ $uomRow->uom?->name ?? '—' }}
                        </td>
                        <td class="px-5 py-3.5 font-mono text-slate-700 dark:text-slate-300">
                            {{ $uomRow->factor_to_base }}
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            @if ($uomRow->is_default_purchase)
                                <x-ui.icon name="check" class="mx-auto h-4 w-4 text-emerald-500" />
                            @else
                                <span class="text-slate-300 dark:text-slate-600">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            @if ($uomRow->is_default_issue)
                                <x-ui.icon name="check" class="mx-auto h-4 w-4 text-emerald-500" />
                            @else
                                <span class="text-slate-300 dark:text-slate-600">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-sm text-slate-400 dark:text-slate-500">
                            No UoM records defined yet.
                        </td>
                    </tr>
                @endforelse
                </tbody>
        </x-ui.table>
    </div>
    @endif

    {{-- ── BARCODES TAB ─────────────────────────────────────────────────────── --}}
    @if ($activeTab === 'barcodes')
    <div class="card p-5">
        <div class="card-header mb-4">
            <h3 class="card-title">
                <x-ui.icon name="search" class="w-4 h-4" />
                Barcodes
            </h3>
        </div>

        @if ($item->barcodes->isEmpty())
            <p class="py-10 text-center text-sm text-slate-400 dark:text-slate-500">No barcodes defined yet.</p>
        @else
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($item->barcodes as $bc)
                @php
                    $bcType = $bc->type instanceof \App\Enums\ItemBarcodeType ? $bc->type->value : (string) $bc->type;
                @endphp
                <div wire:key="bc-{{ $bc->id }}"
                     class="flex flex-col items-center rounded-xl border border-white/40 dark:border-white/10
                            bg-white/30 dark:bg-white/[0.04] p-4 gap-3">

                    {{-- badges row --}}
                    <div class="flex flex-wrap gap-1.5 w-full">
                        @if ($bc->is_primary)
                            <span class="badge badge-green">Primary</span>
                        @endif
                        @if ($bcType === 'supplier')
                            <span class="badge badge-amber">Supplier</span>
                        @else
                            <span class="badge badge-slate">Internal</span>
                        @endif
                        @if ($bc->uom)
                            <span class="badge badge-slate font-mono">{{ $bc->uom->code }}</span>
                        @endif
                    </div>

                    {{-- barcode image --}}
                    <img src="{{ route('admin.items.barcodes.png', [$item, $bc]) }}"
                         alt="{{ $bc->barcode }}"
                         class="max-w-full h-16 object-contain bg-white rounded px-1">

                    {{-- barcode value --}}
                    <span class="font-mono text-xs text-slate-700 dark:text-slate-300 break-all text-center leading-tight">
                        {{ $bc->barcode }}
                    </span>

                    {{-- action buttons --}}
                    <div class="flex gap-2 mt-auto pt-1 w-full">
                        <a href="{{ route('admin.items.barcodes.label', [$item, $bc]) }}"
                           target="_blank"
                           class="btn btn-secondary btn-sm flex-1 text-center text-xs">
                            Print label
                        </a>
                        <a href="{{ route('admin.items.barcodes.png', [$item, $bc]) }}"
                           download="{{ $bc->barcode }}.png"
                           class="btn btn-secondary btn-sm flex-1 text-center text-xs">
                            Download PNG
                        </a>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>
    @endif

    {{-- ── TRACKING & FLAGS TAB ─────────────────────────────────────────────── --}}
    @if ($activeTab === 'tracking')
    <div class="card overflow-hidden">
        <div class="card-header">
            <h3 class="card-title">
                <x-ui.icon name="shield" class="w-4 h-4" />
                Tracking &amp; Flags
            </h3>
        </div>

        <dl class="divide-y divide-white/50 dark:divide-white/[0.06] px-5">

            @php
                $flags = [
                    'track_batch'  => ['label' => 'Track Batch',         'hint' => 'Lot/batch management required at receipt'],
                    'track_expiry' => ['label' => 'Track Expiry',        'hint' => 'FEFO enabled; batch tracking forced on'],
                    'track_serial' => ['label' => 'Track Serial Numbers','hint' => 'Each unit tracked individually'],
                    'is_active'    => ['label' => 'Active',              'hint' => 'Item is available for transactions'],
                ];
            @endphp

            @foreach ($flags as $field => $meta)
                <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[220px_minmax(0,1fr)] sm:items-center">
                    <dt class="section-label">{{ $meta['label'] }}</dt>
                    <dd class="flex items-center gap-3">
                        @if ($item->$field)
                            <div class="flex h-7 w-7 items-center justify-center rounded-full
                                        bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                <x-ui.icon name="check" class="h-4 w-4" />
                            </div>
                            <span class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Enabled</span>
                        @else
                            <div class="flex h-7 w-7 items-center justify-center rounded-full
                                        bg-slate-100 dark:bg-white/[0.06] text-slate-400 dark:text-slate-500">
                                <x-ui.icon name="x" class="h-4 w-4" />
                            </div>
                            <span class="text-sm text-slate-500 dark:text-slate-400">Disabled</span>
                        @endif
                        <span class="text-xs text-slate-400 dark:text-slate-500">{{ $meta['hint'] }}</span>
                    </dd>
                </div>
            @endforeach

        </dl>
    </div>
    @endif

    {{-- ── STOCK TAB ────────────────────────────────────────────────────────── --}}
    @if ($activeTab === 'stock')
    <div class="space-y-6">

        {{-- Balances --}}
        <div class="card overflow-hidden">
            <div class="card-header">
                <h3 class="card-title">
                    <x-ui.icon name="package" class="w-4 h-4" /> Current Balances
                </h3>
                <a href="{{ route('inventory.stock.index', ['filterItem' => $item->id]) }}" wire:navigate
                   class="btn btn-secondary btn-sm">
                    <x-ui.icon name="arrows" class="w-3.5 h-3.5" /> Full Stock View
                </a>
            </div>

            <x-ui.table class="min-w-[800px]">
                    <thead class="text-[11px] font-semibold uppercase tracking-wider
                                  text-slate-500 dark:text-slate-400
                                  bg-white/[0.45] dark:bg-white/[0.04]
                                  border-b border-white/50 dark:border-white/[0.07]">
                        <tr>
                            <th class="px-5 py-3">Location</th>
                            <th class="px-5 py-3">Warehouse</th>
                            <th class="px-5 py-3">Batch</th>
                            <th class="px-5 py-3">Expiry</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">On Hand</th>
                            <th class="px-5 py-3 text-right">Allocated</th>
                            <th class="px-5 py-3 text-right">Available</th>
                            <th class="px-5 py-3">UoM</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/50 dark:divide-white/[0.06]">
                    @forelse ($this->stockBalances as $balance)
                        @php $available = $balance->qty_on_hand - $balance->qty_allocated; @endphp
                        <tr class="transition hover:bg-white/30 dark:hover:bg-white/[0.04]">
                            <td class="px-5 py-3.5 font-mono text-xs font-semibold text-slate-800 dark:text-slate-200">
                                {{ $balance->location?->code ?? '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-slate-600 dark:text-slate-400">
                                {{ $balance->virtualWarehouse?->name ?? '—' }}
                            </td>
                            <td class="px-5 py-3.5 font-mono text-xs text-slate-600 dark:text-slate-400">
                                {{ $balance->batch?->batch_no ?? '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-sm tabular-nums
                                       @php
                                           $days = $balance->batch?->expiry_date ? now()->diffInDays($balance->batch->expiry_date, false) : null;
                                       @endphp
                                       {{ $days !== null && $days <= 30 ? 'text-red-600 dark:text-red-400' : ($days !== null && $days <= 60 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-600 dark:text-slate-400') }}">
                                {{ $balance->batch?->expiry_date?->format('d M Y') ?? '—' }}
                            </td>
                            <td class="px-5 py-3.5">
                                @if (($balance->stock_status?->value ?? $balance->stock_status) === 'on_hold')
                                    <span class="badge badge-amber">On Hold</span>
                                @else
                                    <span class="badge badge-green"><span class="dot"></span> Available</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right tabular-nums font-semibold text-slate-900 dark:text-slate-100">
                                {{ number_format($balance->qty_on_hand, 2) }}
                            </td>
                            <td class="px-5 py-3.5 text-right tabular-nums text-amber-600 dark:text-amber-400">
                                {{ number_format($balance->qty_allocated, 2) }}
                            </td>
                            <td class="px-5 py-3.5 text-right tabular-nums font-bold
                                       {{ $available <= 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                {{ number_format($available, 2) }}
                            </td>
                            <td class="px-5 py-3.5">
                                @if ($item->baseUom)
                                    <span class="badge badge-slate font-mono">{{ $item->baseUom->code }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-5 py-12 text-center text-sm text-slate-400 dark:text-slate-500">
                                No stock on hand for this item.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
            </x-ui.table>
        </div>

        {{-- Last 20 Movements --}}
        <div class="card overflow-hidden">
            <div class="card-header">
                <h3 class="card-title">
                    <x-ui.icon name="refresh" class="w-4 h-4" /> Last 20 Movements
                </h3>
            </div>

            <x-ui.table class="min-w-[700px]">
                    <thead class="text-[11px] font-semibold uppercase tracking-wider
                                  text-slate-500 dark:text-slate-400
                                  bg-white/[0.45] dark:bg-white/[0.04]
                                  border-b border-white/50 dark:border-white/[0.07]">
                        <tr>
                            <th class="px-5 py-3">Number</th>
                            <th class="px-5 py-3">Type</th>
                            <th class="px-5 py-3">Date</th>
                            <th class="px-5 py-3">Location</th>
                            <th class="px-5 py-3">Warehouse</th>
                            <th class="px-5 py-3 text-right">Qty</th>
                            <th class="px-5 py-3">UoM</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/50 dark:divide-white/[0.06]">
                    @forelse ($this->recentMovements as $movement)
                        <tr class="transition hover:bg-white/30 dark:hover:bg-white/[0.04]">
                            <td class="px-5 py-3.5 font-mono text-xs font-semibold text-slate-800 dark:text-slate-100">
                                {{ $movement->transaction?->number ?? '—' }}
                            </td>
                            <td class="px-5 py-3.5">
                                @if ($movement->transaction)
                                    <span class="badge badge-slate">{{ $movement->transaction->type->label() }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-sm tabular-nums text-slate-600 dark:text-slate-400">
                                {{ $movement->transaction?->posted_at?->format('d M Y H:i') ?? '—' }}
                            </td>
                            <td class="px-5 py-3.5 font-mono text-xs text-slate-600 dark:text-slate-400">
                                {{ $movement->location?->code ?? '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-slate-600 dark:text-slate-400">
                                {{ $movement->virtualWarehouse?->name ?? '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-right tabular-nums font-semibold
                                       {{ $movement->entered_qty > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ $movement->entered_qty > 0 ? '+' : '' }}{{ number_format($movement->entered_qty, 4) }}
                            </td>
                            <td class="px-5 py-3.5">
                                @if ($movement->enteredUom)
                                    <span class="badge badge-slate font-mono">{{ $movement->enteredUom->code }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-sm text-slate-400 dark:text-slate-500">
                                No movement history for this item yet.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
            </x-ui.table>
        </div>

    </div>
    @endif

    {{-- ── ACTIVITY TAB ─────────────────────────────────────────────────────── --}}
    @if ($activeTab === 'activity')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <x-ui.icon name="refresh" class="w-4 h-4" />
                Activity
            </h3>
        </div>
        <div class="card-body py-16 text-center">
            <x-ui.icon name="refresh" class="mx-auto h-8 w-8 text-slate-300 dark:text-slate-600" />
            <p class="mt-3 font-medium text-slate-900 dark:text-white">Activity log coming soon</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Change history will appear here in a future release.</p>
        </div>
    </div>
    @endif

</div>
