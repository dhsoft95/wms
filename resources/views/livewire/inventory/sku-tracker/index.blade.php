<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">SKU Tracker</h2>
            <p class="page-sub">Search any SKU, barcode or item name to see full stock details, movements and open orders.</p>
        </div>
    </div>

    {{-- Search --}}
    <div class="card p-4">
        <div class="relative">
            <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
                wire:model.live.debounce.300ms="search"
                type="search"
                placeholder="Search by SKU, barcode, part number or item name…"
                class="input w-full pl-9"
                autofocus
            />
        </div>
    </div>

    {{-- ── Search Results (no item selected) ─────────────────────────────── --}}
    @if($this->selectedItem === null)

        @if(strlen(trim($search)) >= 2)
            @if($this->results->isEmpty())
                <div class="card p-10 text-center">
                    <x-ui.icon name="search" class="mx-auto mb-3 h-10 w-10 text-slate-300 dark:text-slate-600" />
                    <p class="text-sm font-medium text-slate-500">No items found for "{{ $search }}"</p>
                    <p class="mt-1 text-xs text-slate-400">Try a different SKU, barcode or name.</p>
                </div>
            @else
                <div class="card overflow-hidden">
                    <div class="border-b border-white/40 dark:border-white/[0.08] px-4 py-3">
                        <span class="text-sm font-medium text-slate-600 dark:text-slate-300">
                            {{ $this->results->count() }} result{{ $this->results->count() !== 1 ? 's' : '' }}
                        </span>
                    </div>
                    <ul class="divide-y divide-slate-100 dark:divide-white/[0.06]">
                        @foreach($this->results as $item)
                            <li>
                                <button
                                    wire:click="selectItem({{ $item->id }})"
                                    class="flex w-full items-center gap-4 px-4 py-3 text-left transition-colors hover:bg-slate-50 dark:hover:bg-white/[0.04]"
                                >
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 dark:bg-brand-900/30">
                                        <x-ui.icon name="archive" class="h-5 w-5 text-brand-600 dark:text-brand-400" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-sm font-semibold text-slate-900 dark:text-white">{{ $item->sku }}</span>
                                            @if($item->part_number)
                                                <span class="text-xs text-slate-400">· {{ $item->part_number }}</span>
                                            @endif
                                        </div>
                                        <div class="mt-0.5 text-sm text-slate-500 dark:text-slate-400 truncate">{{ $item->name }}</div>
                                    </div>
                                    <div class="flex shrink-0 flex-col items-end gap-1 text-xs text-slate-400">
                                        <span>{{ $item->category?->name ?? '—' }}</span>
                                        <span>{{ $item->baseUom?->code ?? '' }}</span>
                                    </div>
                                    <x-ui.icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-300 dark:text-slate-600" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @else
            <div class="card p-10 text-center">
                <x-ui.icon name="archive" class="mx-auto mb-3 h-10 w-10 text-slate-300 dark:text-slate-600" />
                <p class="text-sm text-slate-400">Type at least 2 characters to search.</p>
            </div>
        @endif

    {{-- ── Item Detail Panel ───────────────────────────────────────────────── --}}
    @else
        @php $item = $this->selectedItem; @endphp

        <div x-data="{ tab: 'stock' }" class="space-y-5">

            {{-- Item Header --}}
            <div class="card p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start">

                    {{-- Back --}}
                    <button wire:click="clearSelection" class="flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-800 dark:hover:text-white transition-colors self-start shrink-0">
                        <x-ui.icon name="arrow-left" class="h-4 w-4" />
                        Back
                    </button>

                    {{-- Item identity --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <span class="font-mono text-xl font-bold text-slate-900 dark:text-white">{{ $item->sku }}</span>
                            @if($item->part_number)
                                <span class="text-sm text-slate-400">· {{ $item->part_number }}</span>
                            @endif
                            @if(! $item->is_active)
                                <span class="badge badge-neutral">Inactive</span>
                            @endif
                        </div>
                        <div class="text-sm text-slate-600 dark:text-slate-300">{{ $item->name }}</div>
                        @if($item->description)
                            <div class="mt-1 text-xs text-slate-400 line-clamp-2">{{ $item->description }}</div>
                        @endif
                    </div>

                    {{-- Meta chips --}}
                    <div class="flex flex-wrap gap-2 shrink-0">
                        @if($item->category)
                            <span class="badge badge-neutral">{{ $item->category->name }}</span>
                        @endif
                        @if($item->baseUom)
                            <span class="badge badge-brand">{{ $item->baseUom->code }}</span>
                        @endif
                        @if($item->track_batch)
                            <span class="badge badge-warning">Batch</span>
                        @endif
                        @if($item->track_expiry)
                            <span class="badge badge-warning">Expiry</span>
                        @endif
                        @if($item->track_serial)
                            <span class="badge badge-brand">Serial</span>
                        @endif
                        @if($item->is_hazardous)
                            <span class="badge badge-danger">Hazardous</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Stock Summary KPIs --}}
            @php
                $summary = $this->stockSummary;
                $uom     = $item->baseUom?->code ?? '';
            @endphp
            <div class="grid grid-cols-3 gap-4">
                <x-ui.kpi-card
                    label="On Hand"
                    :value="number_format($summary['on_hand'], 2) . ' ' . $uom"
                    tone="brand"
                    icon="archive"
                />
                <x-ui.kpi-card
                    label="Allocated"
                    :value="number_format($summary['allocated'], 2) . ' ' . $uom"
                    :tone="$summary['allocated'] > 0 ? 'warning' : 'neutral'"
                    icon="lock"
                />
                <x-ui.kpi-card
                    label="Available"
                    :value="number_format($summary['available'], 2) . ' ' . $uom"
                    :tone="$summary['available'] > 0 ? 'success' : 'danger'"
                    icon="badge-check"
                />
            </div>

            {{-- Tabs --}}
            <div class="card overflow-hidden">

                {{-- Tab bar --}}
                <div class="flex overflow-x-auto border-b border-white/40 dark:border-white/[0.08] px-4 gap-0.5 pt-2">
                    @php
                        $tabs = [
                            ['key' => 'stock',      'label' => 'Stock Locations',  'icon' => 'map-pin'],
                            ['key' => 'movements',  'label' => 'Movements',         'icon' => 'arrows'],
                            ['key' => 'inbound',    'label' => 'Open Inbound',      'icon' => 'download'],
                            ['key' => 'outbound',   'label' => 'Open Outbound',     'icon' => 'upload'],
                        ];
                        if ($item->track_batch)  $tabs[] = ['key' => 'batches',  'label' => 'Batches',  'icon' => 'list'];
                        if ($item->track_serial) $tabs[] = ['key' => 'serials',  'label' => 'Serials',  'icon' => 'qr-code'];
                    @endphp

                    @foreach($tabs as $t)
                        <button
                            @click="tab = '{{ $t['key'] }}'"
                            :class="tab === '{{ $t['key'] }}' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-200'"
                            class="flex items-center gap-1.5 whitespace-nowrap border-b-2 px-3 pb-2.5 text-sm font-medium transition-colors"
                        >
                            <x-ui.icon name="{{ $t['icon'] }}" class="h-3.5 w-3.5" />
                            {{ $t['label'] }}
                        </button>
                    @endforeach
                </div>

                {{-- ── Tab: Stock Locations ─────────────────────────────── --}}
                <div x-show="tab === 'stock'">
                    @if($this->stockByLocation->isEmpty())
                        <div class="p-10 text-center">
                            <x-ui.icon name="archive" class="mx-auto mb-2 h-8 w-8 text-slate-300 dark:text-slate-600" />
                            <p class="text-sm text-slate-400">No stock found for this item.</p>
                        </div>
                    @else
                        <x-ui.table>
                            <thead>
                                <tr>
                                    <th>Location</th>
                                    <th>Virtual Warehouse</th>
                                    <th>Batch</th>
                                    <th>Status</th>
                                    <th class="text-right">On Hand</th>
                                    <th class="text-right">Allocated</th>
                                    <th class="text-right">Available</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->stockByLocation as $row)
                                    <tr>
                                        <td class="font-medium">{{ $row->location?->code ?? '—' }}</td>
                                        <td class="text-slate-500">{{ $row->virtualWarehouse?->name ?? '—' }}</td>
                                        <td class="font-mono text-xs">{{ $row->batch?->batch_no ?? '—' }}</td>
                                        <td>
                                            @if($row->stock_status)
                                                <span class="badge badge-neutral text-xs">{{ $row->stock_status->value }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-right font-mono text-sm">{{ number_format((float) $row->qty_on_hand, 4) }}</td>
                                        <td class="text-right font-mono text-sm text-amber-600 dark:text-amber-400">{{ number_format((float) $row->qty_allocated, 4) }}</td>
                                        <td class="text-right font-mono text-sm font-semibold {{ (float) $row->qtyAvailable() > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500' }}">
                                            {{ number_format((float) $row->qtyAvailable(), 4) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-ui.table>
                    @endif
                </div>

                {{-- ── Tab: Movements ───────────────────────────────────── --}}
                <div x-show="tab === 'movements'" x-cloak>
                    @if($this->recentMovements->isEmpty())
                        <div class="p-10 text-center">
                            <x-ui.icon name="arrows" class="mx-auto mb-2 h-8 w-8 text-slate-300 dark:text-slate-600" />
                            <p class="text-sm text-slate-400">No movements recorded for this item.</p>
                        </div>
                    @else
                        <x-ui.table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Reference</th>
                                    <th>Location</th>
                                    <th>Batch</th>
                                    <th class="text-right">Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->recentMovements as $mv)
                                    <tr>
                                        <td class="whitespace-nowrap text-xs text-slate-500">
                                            {{ $mv->created_at?->format('d M Y H:i') ?? '—' }}
                                        </td>
                                        <td>
                                            @if($mv->transaction?->type)
                                                <span class="badge badge-neutral text-xs">{{ $mv->transaction->type->label() }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="font-mono text-xs">{{ $mv->transaction?->reference_no ?? $mv->transaction?->number ?? '—' }}</td>
                                        <td class="text-sm">{{ $mv->location?->code ?? '—' }}</td>
                                        <td class="font-mono text-xs text-slate-500">{{ $mv->batch?->batch_no ?? '—' }}</td>
                                        <td class="text-right font-mono text-sm font-semibold {{ (float) $mv->qty >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500' }}">
                                            {{ (float) $mv->qty >= 0 ? '+' : '' }}{{ number_format((float) $mv->qty, 4) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-ui.table>
                        @if($this->recentMovements->count() >= 50)
                            <div class="px-4 py-3 text-center text-xs text-slate-400 border-t border-slate-100 dark:border-white/[0.06]">
                                Showing last 50 movements
                            </div>
                        @endif
                    @endif
                </div>

                {{-- ── Tab: Open Inbound ────────────────────────────────── --}}
                <div x-show="tab === 'inbound'" x-cloak>
                    @if($this->openInbound->isEmpty())
                        <div class="p-10 text-center">
                            <x-ui.icon name="download" class="mx-auto mb-2 h-8 w-8 text-slate-300 dark:text-slate-600" />
                            <p class="text-sm text-slate-400">No open inbound receipts for this item.</p>
                        </div>
                    @else
                        <x-ui.table>
                            <thead>
                                <tr>
                                    <th>GRN No</th>
                                    <th>Status</th>
                                    <th class="text-right">Expected</th>
                                    <th class="text-right">Received</th>
                                    <th class="text-right">Outstanding</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->openInbound as $line)
                                    @php $outstanding = (float) $line->expected_qty - (float) $line->received_good_qty; @endphp
                                    <tr>
                                        <td class="font-mono text-sm font-medium">{{ $line->grn?->number ?? '—' }}</td>
                                        <td>
                                            @if($line->grn?->status)
                                                <span class="badge {{ $line->grn->status->color() }}">{{ $line->grn->status->label() }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-right font-mono text-sm">{{ number_format((float) $line->expected_qty, 4) }}</td>
                                        <td class="text-right font-mono text-sm">{{ number_format((float) $line->received_good_qty, 4) }}</td>
                                        <td class="text-right font-mono text-sm font-semibold text-amber-600 dark:text-amber-400">
                                            {{ number_format($outstanding, 4) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-ui.table>
                    @endif
                </div>

                {{-- ── Tab: Open Outbound ───────────────────────────────── --}}
                <div x-show="tab === 'outbound'" x-cloak>
                    @if($this->openOutbound->isEmpty())
                        <div class="p-10 text-center">
                            <x-ui.icon name="upload" class="mx-auto mb-2 h-8 w-8 text-slate-300 dark:text-slate-600" />
                            <p class="text-sm text-slate-400">No open outbound orders for this item.</p>
                        </div>
                    @else
                        <x-ui.table>
                            <thead>
                                <tr>
                                    <th>DO No</th>
                                    <th>Status</th>
                                    <th class="text-right">Ordered</th>
                                    <th class="text-right">Dispatched</th>
                                    <th class="text-right">Outstanding</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->openOutbound as $line)
                                    @php $outstanding = (float) $line->requested_qty_base - (float) $line->dispatched_qty; @endphp
                                    <tr>
                                        <td class="font-mono text-sm font-medium">{{ $line->deliveryOrder?->number ?? '—' }}</td>
                                        <td>
                                            @if($line->status)
                                                <span class="badge badge-neutral text-xs">{{ $line->status->label() }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-right font-mono text-sm">{{ number_format((float) $line->requested_qty_base, 4) }}</td>
                                        <td class="text-right font-mono text-sm">{{ number_format((float) $line->dispatched_qty, 4) }}</td>
                                        <td class="text-right font-mono text-sm font-semibold text-amber-600 dark:text-amber-400">
                                            {{ number_format($outstanding, 4) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-ui.table>
                    @endif
                </div>

                {{-- ── Tab: Batches ─────────────────────────────────────── --}}
                @if($item->track_batch)
                    <div x-show="tab === 'batches'" x-cloak>
                        @if($this->batches->isEmpty())
                            <div class="p-10 text-center">
                                <x-ui.icon name="list" class="mx-auto mb-2 h-8 w-8 text-slate-300 dark:text-slate-600" />
                                <p class="text-sm text-slate-400">No batches recorded for this item.</p>
                            </div>
                        @else
                            <x-ui.table>
                                <thead>
                                    <tr>
                                        <th>Batch No</th>
                                        <th>Manufactured</th>
                                        <th>Expiry</th>
                                        <th class="text-right">Stock on Hand</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->batches as $batch)
                                        @php $expiring = $batch->expiry_date && $batch->expiry_date->isPast(); @endphp
                                        <tr>
                                            <td class="font-mono text-sm font-medium">{{ $batch->batch_no }}</td>
                                            <td class="text-sm text-slate-500">{{ $batch->manufactured_date?->format('d M Y') ?? '—' }}</td>
                                            <td>
                                                @if($batch->expiry_date)
                                                    <span class="{{ $expiring ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-600 dark:text-slate-300' }} text-sm">
                                                        {{ $batch->expiry_date->format('d M Y') }}
                                                        @if($expiring) <span class="badge badge-danger text-xs ml-1">Expired</span> @endif
                                                    </span>
                                                @else
                                                    <span class="text-slate-400">—</span>
                                                @endif
                                            </td>
                                            <td class="text-right font-mono text-sm {{ $batch->qty_on_hand > 0 ? 'font-semibold' : 'text-slate-400' }}">
                                                {{ number_format($batch->qty_on_hand, 4) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.table>
                        @endif
                    </div>
                @endif

                {{-- ── Tab: Serials ─────────────────────────────────────── --}}
                @if($item->track_serial)
                    <div x-show="tab === 'serials'" x-cloak>
                        @if($this->serials->isEmpty())
                            <div class="p-10 text-center">
                                <x-ui.icon name="qr-code" class="mx-auto mb-2 h-8 w-8 text-slate-300 dark:text-slate-600" />
                                <p class="text-sm text-slate-400">No serial numbers recorded for this item.</p>
                            </div>
                        @else
                            <x-ui.table>
                                <thead>
                                    <tr>
                                        <th>Serial No</th>
                                        <th>Status</th>
                                        <th>Location</th>
                                        <th>Batch</th>
                                        <th>Last Updated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->serials as $serial)
                                        <tr>
                                            <td class="font-mono text-sm font-medium text-slate-900 dark:text-white">{{ $serial->serial_no }}</td>
                                            <td>
                                                @if($serial->status)
                                                    <span class="badge badge-{{ $serial->status->color() }}">{{ $serial->status->label() }}</span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="text-sm">{{ $serial->location?->code ?? '—' }}</td>
                                            <td class="font-mono text-xs text-slate-500">{{ $serial->batch?->batch_no ?? '—' }}</td>
                                            <td class="text-xs text-slate-400">{{ $serial->updated_at?->format('d M Y H:i') ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.table>
                            @if($this->serials->count() >= 200)
                                <div class="px-4 py-3 text-center text-xs text-slate-400 border-t border-slate-100 dark:border-white/[0.06]">
                                    Showing first 200 serial numbers
                                </div>
                            @endif
                        @endif
                    </div>
                @endif

            </div>{{-- end card --}}
        </div>{{-- end x-data --}}

    @endif

</div>
