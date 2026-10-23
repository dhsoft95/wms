@php
    $b    = $stockBalance;
    $info = $this->stockInfo;

    $isLow       = $b->item?->min_stock !== null && $info['qtyOnHand'] < (float) $b->item->min_stock;
    $isOverstock = $b->item?->max_stock !== null && $info['qtyOnHand'] > (float) $b->item->max_stock;

    $daysToExpiry = null;
    $expiryClass  = 'text-slate-800 dark:text-slate-200';
    $expiryBadge  = '';
    if ($b->batch?->expiry_date) {
        $daysToExpiry = (int) now()->diffInDays($b->batch->expiry_date, false);
        if ($daysToExpiry <= 0)      { $expiryClass = 'text-red-600 dark:text-red-400 font-semibold'; $expiryBadge = 'badge-red'; }
        elseif ($daysToExpiry <= 30) { $expiryClass = 'text-red-600 dark:text-red-400';               $expiryBadge = 'badge-red'; }
        elseif ($daysToExpiry <= 60) { $expiryClass = 'text-amber-600 dark:text-amber-400';            $expiryBadge = 'badge-amber'; }
    }

    $colorMap = [
        'indigo'  => ['dot' => 'bg-indigo-500',  'ring' => 'ring-indigo-200 dark:ring-indigo-800'],
        'green'   => ['dot' => 'bg-emerald-500', 'ring' => 'ring-emerald-200 dark:ring-emerald-800'],
        'teal'    => ['dot' => 'bg-teal-500',    'ring' => 'ring-teal-200 dark:ring-teal-800'],
        'blue'    => ['dot' => 'bg-blue-500',    'ring' => 'ring-blue-200 dark:ring-blue-800'],
        'orange'  => ['dot' => 'bg-orange-500',  'ring' => 'ring-orange-200 dark:ring-orange-800'],
        'red'     => ['dot' => 'bg-red-500',     'ring' => 'ring-red-200 dark:ring-red-800'],
        'yellow'  => ['dot' => 'bg-yellow-500',  'ring' => 'ring-yellow-200 dark:ring-yellow-800'],
        'slate'   => ['dot' => 'bg-slate-400',   'ring' => 'ring-slate-200 dark:ring-slate-700'],
        'rose'    => ['dot' => 'bg-rose-500',    'ring' => 'ring-rose-200 dark:ring-rose-800'],
        'purple'  => ['dot' => 'bg-purple-500',  'ring' => 'ring-purple-200 dark:ring-purple-800'],
    ];
@endphp

<div class="space-y-6 pb-12">

    {{-- ── PAGE HEADER ──────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-slate-900/40 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm">
        <div class="space-y-2">
            <a href="{{ route('inventory.stock.index') }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-simba-600 dark:text-slate-400 dark:hover:text-simba-400 transition-colors">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Back to Stock on Hand
            </a>

            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-simba-50 dark:bg-simba-900/30 text-simba-600 dark:text-simba-400 ring-1 ring-simba-500/20">
                    <x-ui.icon name="cube" class="w-6 h-6" />
                </div>
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h2 class="text-xl font-mono font-bold tracking-tight text-slate-900 dark:text-white leading-none">{{ $b->item?->sku ?? '—' }}</h2>
                        @php
                            $statusVal = is_object($b->stock_status) ? $b->stock_status->value : $b->stock_status;
                        @endphp
                        @if ($statusVal === 'available')
                            <span class="badge badge-green"><span class="dot"></span> Available</span>
                        @elseif ($statusVal === 'obsolete')
                            <span class="badge badge-red"><span class="dot"></span> Obsolete</span>
                        @else
                            <span class="badge badge-amber"><span class="dot"></span> On Hold</span>
                        @endif
                    </div>
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400 mt-1">{{ $b->item?->name ?? 'Unnamed Item' }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2 pt-1 flex-wrap">
                @if ($b->item?->category)
                    <span class="badge badge-slate">{{ $b->item->category->name }}</span>
                @endif
                @if ($b->item?->baseUom)
                    <span class="badge badge-slate font-mono">{{ $b->item->baseUom->code }}</span>
                @endif
                @if ($isLow)
                    <span class="badge badge-red"><span class="dot"></span> Below Min Stock</span>
                @endif
                @if ($isOverstock)
                    <span class="badge badge-orange"><span class="dot"></span> Overstock</span>
                @endif
            </div>
        </div>

        <div class="flex flex-col items-end gap-3 shrink-0">
            <div class="text-right">
                <span class="text-[11px] text-slate-400 dark:text-slate-500 uppercase tracking-wider font-semibold">Last Updated</span>
                <p class="mt-0.5 text-xs font-semibold text-slate-700 dark:text-slate-300 tabular-nums">{{ $b->updated_at?->format('d M Y, H:i') ?? '—' }}</p>
            </div>
            @can('obsolete.mark')
                @php $sv = is_object($b->stock_status) ? $b->stock_status->value : $b->stock_status; @endphp
                @if ($sv === 'available' || $sv === 'on_hold')
                    <a href="{{ route('inventory.stock-status.create', ['balance_id' => $b->id, 'type' => 'mark_obsolete']) }}"
                       wire:navigate
                       class="btn btn-sm btn-secondary">
                        <x-ui.icon name="archive" class="w-4 h-4" /> Mark Obsolete
                    </a>
                @elseif ($sv === 'obsolete')
                    <a href="{{ route('inventory.stock-status.create', ['balance_id' => $b->id, 'type' => 'restore']) }}"
                       wire:navigate
                       class="btn btn-sm btn-secondary">
                        <x-ui.icon name="refresh" class="w-4 h-4" /> Restore to Available
                    </a>
                @endif
            @endcan
        </div>
    </div>

    {{-- ── QUANTITY KPI STRIP ───────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">

        {{-- Total On Hand (all locations) --}}
        <div class="card p-4 flex flex-col justify-between gap-2 bg-gradient-to-b from-white to-slate-50/50 dark:from-slate-900 dark:to-slate-900/50">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Total On Hand</span>
            <div>
                <p class="text-xl font-bold font-mono tracking-tight text-slate-900 dark:text-white">{{ number_format($info['qtyOnHand'], 2) }}</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Across all locations</p>
            </div>
        </div>

        {{-- Allocated --}}
        <div class="card p-4 flex flex-col justify-between gap-2 bg-gradient-to-b from-white to-slate-50/50 dark:from-slate-900 dark:to-slate-900/50">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Allocated</span>
            <div>
                <p class="text-xl font-bold font-mono tracking-tight text-amber-600 dark:text-amber-400">{{ number_format($info['qtyAllocated'], 2) }}</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Reserved for orders</p>
            </div>
        </div>

        {{-- Available --}}
        <div class="card p-4 flex flex-col justify-between gap-2 bg-gradient-to-b from-white to-slate-50/50 dark:from-slate-900 dark:to-slate-900/50">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Available</span>
            <div>
                <p class="text-xl font-bold font-mono tracking-tight {{ $info['available'] <= 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                    {{ number_format($info['available'], 2) }}
                </p>
                <p class="text-[11px] text-slate-400 mt-0.5">Free to allocate</p>
            </div>
        </div>

        {{-- Opening Balance --}}
        <div class="card p-4 flex flex-col justify-between gap-2 bg-gradient-to-b from-white to-slate-50/50 dark:from-slate-900 dark:to-slate-900/50">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Opening Balance</span>
            <div>
                <p class="text-xl font-bold font-mono tracking-tight text-indigo-600 dark:text-indigo-400">{{ number_format((float) $info['openingBalance'], 2) }}</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Initial stock posted</p>
            </div>
        </div>

        {{-- On Order --}}
        <div class="card p-4 flex flex-col justify-between gap-2 bg-gradient-to-b from-white to-slate-50/50 dark:from-slate-900 dark:to-slate-900/50">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">On Order</span>
            <div>
                <p class="text-xl font-bold font-mono tracking-tight text-blue-600 dark:text-blue-400">{{ number_format((float) $info['onOrderQty'], 2) }}</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Open PO qty</p>
            </div>
        </div>

        {{-- Stock Value --}}
        <div class="card p-4 flex flex-col justify-between gap-2 bg-gradient-to-b from-white to-slate-50/50 dark:from-slate-900 dark:to-slate-900/50">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Stock Value</span>
            <div>
                @if ($info['stockValue'] !== null)
                    <p class="text-xl font-bold font-mono tracking-tight text-simba-700 dark:text-simba-400">{{ number_format($info['stockValue'], 2) }}</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">At last purchase price</p>
                @else
                    <p class="text-xl font-semibold text-slate-400 dark:text-slate-500 tracking-tight">N/A</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">No purchase price found</p>
                @endif
            </div>
        </div>

    </div>

    {{-- ── DETAILS: Item Info + Activity Dates ────────────────────────────── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

        {{-- Item Info --}}
        <div class="card flex flex-col">
            <div class="card-header gap-2.5 border-b border-slate-100 dark:border-slate-800/80">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-simba-50 dark:bg-simba-900/30 text-simba-600 dark:text-simba-400 shrink-0">
                    <x-ui.icon name="cube" class="w-3.5 h-3.5" />
                </span>
                <h3 class="card-title font-semibold">Item Info</h3>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800/60 flex-1">
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <span class="text-xs text-slate-500 dark:text-slate-400">SKU / Code</span>
                    <span class="font-mono font-semibold text-xs text-simba-700 dark:text-simba-400 text-right">{{ $b->item?->sku ?? '—' }}</span>
                </div>
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Category</span>
                    <span class="text-xs font-medium text-slate-800 dark:text-slate-200 text-right truncate max-w-[160px]">{{ $b->item?->category?->name ?? '—' }}</span>
                </div>
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Unit of Measure</span>
                    @if ($b->item?->baseUom)
                        <span class="badge badge-slate font-mono text-[11px]">{{ $b->item->baseUom->code }}</span>
                    @else
                        <span class="text-xs text-slate-400">—</span>
                    @endif
                </div>
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Min Stock</span>
                    <span class="text-xs font-semibold tabular-nums {{ $isLow ? 'text-red-600 dark:text-red-400' : 'text-slate-800 dark:text-slate-200' }}">
                        @if ($b->item?->min_stock !== null)
                            {{ number_format((float) $b->item->min_stock, 2) }}
                            @if ($isLow) <span class="badge badge-red ml-1 text-[9px] py-0">LOW</span> @endif
                        @else —
                        @endif
                    </span>
                </div>
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Max Stock</span>
                    <span class="text-xs font-semibold tabular-nums {{ $isOverstock ? 'text-orange-600 dark:text-orange-400' : 'text-slate-800 dark:text-slate-200' }}">
                        @if ($b->item?->max_stock !== null)
                            {{ number_format((float) $b->item->max_stock, 2) }}
                            @if ($isOverstock) <span class="badge badge-orange ml-1 text-[9px] py-0">OVER</span> @endif
                        @else —
                        @endif
                    </span>
                </div>
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Serial Tracked</span>
                    @if ($b->item?->track_serial)
                        <span class="badge badge-slate text-[11px]">Yes — {{ $info['serialCount'] }}</span>
                    @else
                        <span class="text-xs text-slate-400">No</span>
                    @endif
                </div>
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Opening Balance</span>
                    <span class="text-xs font-semibold tabular-nums text-indigo-600 dark:text-indigo-400">
                        {{ number_format((float) $info['openingBalance'], 2) }}
                    </span>
                </div>
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Unit Price <span class="font-normal opacity-60">(last PO)</span></span>
                    @if ($info['lastUnitPrice'] !== null)
                        <span class="text-xs font-semibold tabular-nums text-slate-800 dark:text-slate-200">
                            {{ number_format((float) $info['lastUnitPrice'], 2) }}
                        </span>
                    @else
                        <span class="text-xs text-slate-400">—</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Activity Dates --}}
        <div class="card flex flex-col">
            <div class="card-header gap-2.5 border-b border-slate-100 dark:border-slate-800/80">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 shrink-0">
                    <x-ui.icon name="clock" class="w-3.5 h-3.5" />
                </span>
                <h3 class="card-title font-semibold">Activity Dates</h3>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800/60 flex-1">
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Last Received</span>
                    <div class="text-right">
                        <span class="text-xs font-medium text-slate-800 dark:text-slate-200">
                            {{ $b->received_at?->format('d M Y') ?? '—' }}
                        </span>
                        @if ($b->received_at)
                            <span class="block text-[10px] text-slate-400">{{ $b->received_at->format('H:i') }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Last Movement</span>
                    <div class="text-right">
                        @if ($info['lastMovement'])
                            @php $lm = \Carbon\Carbon::parse($info['lastMovement']); @endphp
                            <span class="text-xs font-medium text-slate-800 dark:text-slate-200">{{ $lm->format('d M Y') }}</span>
                            <span class="block text-[10px] text-slate-400">{{ $lm->format('H:i') }}</span>
                        @else
                            <span class="text-xs text-slate-400">—</span>
                        @endif
                    </div>
                </div>
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Last Count</span>
                    <div class="text-right">
                        @if ($info['lastCount'])
                            @php $lc = \Carbon\Carbon::parse($info['lastCount']); @endphp
                            <span class="text-xs font-medium text-slate-800 dark:text-slate-200">{{ $lc->format('d M Y') }}</span>
                            <span class="block text-[10px] text-slate-400">{{ $lc->format('H:i') }}</span>
                        @else
                            <span class="text-xs text-slate-400">—</span>
                        @endif
                    </div>
                </div>
                <div class="flex items-center justify-between gap-3 px-5 py-3">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Record Updated</span>
                    <div class="text-right">
                        <span class="text-xs font-medium text-slate-800 dark:text-slate-200">{{ $b->updated_at?->format('d M Y') ?? '—' }}</span>
                        @if ($b->updated_at)
                            <span class="block text-[10px] text-slate-400">{{ $b->updated_at->format('H:i') }}</span>
                        @endif
                    </div>
                </div>
{{--                <div class="flex items-center justify-between gap-3 px-5 py-3 bg-slate-50/70 dark:bg-slate-800/30">--}}
{{--                    <span class="text-xs text-slate-500 dark:text-slate-400">Stock Age</span>--}}
{{--                    <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">--}}
{{--                        @if ($b->received_at)--}}
{{--                            {{ $b->received_at->diffForHumans(null, true, true, 1) }}--}}
{{--                        @else —--}}
{{--                        @endif--}}
{{--                    </span>--}}
{{--                </div>--}}
            </div>
        </div>

    </div>

    {{-- ── LOCATIONS & STOCK BREAKDOWN (full width) ───────────────────────── --}}
    <div class="card overflow-hidden">
        <div class="card-header gap-2.5 border-b border-slate-100 dark:border-slate-800/80">
            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 shrink-0">
                <x-ui.icon name="map-pin" class="w-3.5 h-3.5" />
            </span>
            <h3 class="card-title font-semibold">Locations & Stock Breakdown</h3>
            <span class="badge badge-slate">{{ $this->allItemBalances->count() }} location(s)</span>
        </div>
        <div class="overflow-x-auto">
            <x-ui.table>
                <thead>
                    <tr>
                        <th>Location</th>
                        <th>Warehouse</th>
                        <th>Virtual WH</th>
                        <th>Batch</th>
                        <th>Expiry</th>
                        <th>Status</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">On Hand</th>
                        <th class="text-right">Allocated</th>
                        <th class="text-right">Available</th>
                        <th class="text-right">Stock Value</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->allItemBalances as $lb)
                        @php
                            $lbAvailable = (float) $lb->qty_on_hand - (float) $lb->qty_allocated;
                            $lbExpiry = $lb->batch?->expiry_date;
                            $lbDays = $lbExpiry ? (int) now()->diffInDays($lbExpiry, false) : null;
                            $lbExpiryClass = match(true) {
                                $lbDays !== null && $lbDays <= 0  => 'text-red-600 dark:text-red-400 font-semibold',
                                $lbDays !== null && $lbDays <= 30 => 'text-red-600 dark:text-red-400',
                                $lbDays !== null && $lbDays <= 60 => 'text-amber-600 dark:text-amber-400',
                                default => 'text-slate-600 dark:text-slate-300',
                            };
                            $isCurrentRow = $lb->id === $b->id;
                        @endphp
                        <tr wire:key="lb-{{ $lb->id }}"
                            class="{{ $isCurrentRow ? 'bg-simba-50/50 dark:bg-simba-900/20 ring-1 ring-inset ring-simba-200/60 dark:ring-simba-800/40' : '' }}">
                            <td class="font-mono font-semibold text-xs text-slate-800 dark:text-slate-200">
                                {{ $lb->location?->code ?? '—' }}
                                @if ($isCurrentRow)
                                    <span class="ml-1 badge badge-simba text-[9px] py-0">current</span>
                                @endif
                            </td>
                            <td class="text-xs text-slate-600 dark:text-slate-300">{{ $lb->location?->warehouse?->name ?? '—' }}</td>
                            <td class="text-xs text-slate-600 dark:text-slate-300 truncate max-w-[140px]">{{ $lb->virtualWarehouse?->name ?? '—' }}</td>
                            <td class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $lb->batch?->batch_no ?? '—' }}</td>
                            <td class="text-xs {{ $lbExpiryClass }}">
                                @if ($lbExpiry)
                                    {{ $lbExpiry->format('d M Y') }}
                                    @if ($lbDays !== null && $lbDays <= 60)
                                        <div class="text-[10px] opacity-80">{{ $lbDays <= 0 ? 'Expired' : $lbDays . 'd left' }}</div>
                                    @endif
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td>
                                @php $lbStatus = $lb->stock_status?->value ?? $lb->stock_status; @endphp
                                @if ($lbStatus === 'available')
                                    <span class="badge badge-green text-[10px]">Available</span>
                                @elseif ($lbStatus === 'obsolete')
                                    <span class="badge badge-red text-[10px]">Obsolete</span>
                                @else
                                    <span class="badge badge-amber text-[10px]">On Hold</span>
                                @endif
                            </td>
                            <td class="text-right tabular-nums text-xs text-slate-600 dark:text-slate-300">
                                {{ $info['lastUnitPrice'] !== null ? number_format((float) $info['lastUnitPrice'], 2) : '—' }}
                            </td>
                            <td class="text-right tabular-nums text-xs font-semibold text-slate-900 dark:text-slate-100">{{ number_format((float) $lb->qty_on_hand, 2) }}</td>
                            <td class="text-right tabular-nums text-xs text-amber-600 dark:text-amber-400">{{ number_format((float) $lb->qty_allocated, 2) }}</td>
                            <td class="text-right tabular-nums text-xs font-bold {{ $lbAvailable <= 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                {{ number_format($lbAvailable, 2) }}
                            </td>
                            <td class="text-right tabular-nums text-xs font-semibold text-simba-700 dark:text-simba-400">
                                @if ($info['lastUnitPrice'] !== null)
                                    {{ number_format((float) $lb->qty_on_hand * (float) $info['lastUnitPrice'], 2) }}
                                @else
                                    <span class="text-slate-400 font-normal">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="py-10 text-center text-xs text-slate-400">No location records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.table>
        </div>
    </div>

    {{-- ── INBOUND + OUTBOUND HISTORY ─────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- Inbound History --}}
        <div class="card overflow-hidden">
            <div class="card-header border-b border-slate-100 dark:border-slate-800/80">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 shrink-0">
                        <x-ui.icon name="arrow-down-circle" class="w-3.5 h-3.5" />
                    </span>
                    <h3 class="card-title font-semibold">Inbound History</h3>
                </div>
                <span class="badge badge-slate">{{ $this->inboundHistory->total() }} record(s)</span>
            </div>
            <div class="overflow-x-auto">
                <x-ui.table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>GRN / Ref</th>
                            <th>Type</th>
                            <th>Supplier</th>
                            <th>Warehouse</th>
                            <th class="text-right">Qty</th>
                            <th>Received By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->inboundHistory as $movement)
                            @php
                                $pos = (float) $movement->qty >= 0;
                                $grn = $movement->transaction?->relationLoaded('grn') ? $movement->transaction->getRelation('grn') : null;
                            @endphp
                            <tr>
                                <td class="tabular-nums text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                    {{ $movement->transaction?->posted_at?->format('d M Y') ?? '—' }}
                                    <div class="text-[10px] text-slate-400">{{ $movement->transaction?->posted_at?->format('H:i') }}</div>
                                </td>
                                <td>
                                    <div class="font-mono text-xs font-semibold text-simba-700 dark:text-simba-400">
                                        {{ $movement->transaction?->reference_no ?? $movement->transaction?->number ?? '—' }}
                                    </div>
                                    @if ($movement->transaction?->number)
                                        <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                            {{ $movement->transaction->number }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if ($movement->transaction?->type)
                                        <span class="badge badge-green text-[11px]">{{ $movement->transaction->type->label() }}</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($grn?->supplierPartner)
                                        <div class="text-xs font-medium text-slate-800 dark:text-slate-200">{{ $grn->supplierPartner->name }}</div>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($grn?->warehouse)
                                        <div class="text-xs font-medium text-slate-800 dark:text-slate-200">{{ $grn->warehouse->name }}</div>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="text-right tabular-nums font-bold text-xs {{ $pos ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $pos ? '+' : '' }}{{ number_format((float) $movement->qty, 2) }}
                                </td>
                                <td class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $movement->transaction?->postedBy?->name ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center">
                                    <x-ui.icon name="arrow-down-circle" class="mx-auto w-8 h-8 text-slate-300 dark:text-slate-600 mb-2" />
                                    <p class="text-xs font-medium text-slate-500">No inbound records found.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.table>
            </div>
            @if ($this->inboundHistory->hasPages())
                <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-800">
                    {{ $this->inboundHistory->links() }}
                </div>
            @endif
        </div>

        {{-- Outbound History --}}
        <div class="card overflow-hidden">
            <div class="card-header border-b border-slate-100 dark:border-slate-800/80">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 shrink-0">
                        <x-ui.icon name="arrow-up-circle" class="w-3.5 h-3.5" />
                    </span>
                    <h3 class="card-title font-semibold">Outbound History</h3>
                </div>
                <span class="badge badge-slate">{{ $this->outboundHistory->total() }} record(s)</span>
            </div>
            <div class="overflow-x-auto">
                <x-ui.table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>DO / Ref</th>
                            <th>Type</th>
                            <th>Consignee</th>
                            <th>Warehouse</th>
                            <th class="text-right">Dispatched Qty</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->outboundHistory as $movement)
                            @php
                                $dispatch = $movement->transaction?->relationLoaded('dispatch') ? $movement->transaction->getRelation('dispatch') : null;
                                $pickTask = $movement->transaction?->relationLoaded('pickTask') ? $movement->transaction->getRelation('pickTask') : null;
                                $do       = $dispatch?->deliveryOrder ?? $pickTask?->pickList?->deliveryOrder;
                            @endphp
                            <tr>
                                <td class="tabular-nums text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                    {{ $movement->transaction?->posted_at?->format('d M Y') ?? '—' }}
                                    <div class="text-[10px] text-slate-400">{{ $movement->transaction?->posted_at?->format('H:i') }}</div>
                                </td>
                                <td>
                                    <div class="font-mono text-xs font-semibold text-simba-700 dark:text-simba-400">
                                        {{ $movement->transaction?->reference_no ?? $movement->transaction?->number ?? '—' }}
                                    </div>
                                    @if ($do?->number)
                                        <div class="text-[10px] text-slate-400 font-mono mt-0.5">{{ $do->number }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($movement->transaction?->type)
                                        <span class="badge badge-red text-[11px]">{{ $movement->transaction->type->label() }}</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($do?->consigneePartner)
                                        <div class="text-xs font-medium text-slate-800 dark:text-slate-200">{{ $do->consigneePartner->name }}</div>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($do?->warehouse)
                                        <div class="text-xs font-medium text-slate-800 dark:text-slate-200">{{ $do->warehouse->name }}</div>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="text-right tabular-nums font-bold text-xs text-red-600 dark:text-red-400">
                                    {{ number_format(abs((float) $movement->qty), 2) }}
                                </td>
                                <td class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $movement->transaction?->postedBy?->name ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center">
                                    <x-ui.icon name="arrow-up-circle" class="mx-auto w-8 h-8 text-slate-300 dark:text-slate-600 mb-2" />
                                    <p class="text-xs font-medium text-slate-500">No outbound records found.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.table>
            </div>
            @if ($this->outboundHistory->hasPages())
                <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-800">
                    {{ $this->outboundHistory->links() }}
                </div>
            @endif
        </div>

    </div>

    {{-- ── MOVEMENT HISTORY (full width) ───────────────────────────────────── --}}
{{--    <div class="card overflow-hidden">--}}
{{--        <div class="card-header border-b border-slate-100 dark:border-slate-800/80">--}}
{{--            <div class="flex items-center gap-2.5">--}}
{{--                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 shrink-0">--}}
{{--                    <x-ui.icon name="arrow-path" class="w-3.5 h-3.5" />--}}
{{--                </span>--}}
{{--                <h3 class="card-title font-semibold">All Movements</h3>--}}
{{--            </div>--}}
{{--            <span class="badge badge-slate">{{ $this->movements->total() }} record(s)</span>--}}
{{--        </div>--}}
{{--        <div class="overflow-x-auto">--}}
{{--            <x-ui.table>--}}
{{--                <thead>--}}
{{--                    <tr>--}}
{{--                        <th>Date</th>--}}
{{--                        <th>Transaction</th>--}}
{{--                        <th>Type</th>--}}
{{--                        <th>Reference</th>--}}
{{--                        <th class="text-right">Qty</th>--}}
{{--                        <th>By</th>--}}
{{--                    </tr>--}}
{{--                </thead>--}}
{{--                <tbody>--}}
{{--                    @forelse ($this->movements as $movement)--}}
{{--                        @php $pos = (float) $movement->qty >= 0; @endphp--}}
{{--                        <tr>--}}
{{--                            <td class="tabular-nums text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">--}}
{{--                                {{ $movement->transaction?->posted_at?->format('d M Y, H:i') ?? '—' }}--}}
{{--                            </td>--}}
{{--                            <td class="font-mono text-xs font-semibold text-simba-700 dark:text-simba-400">--}}
{{--                                {{ $movement->transaction?->number ?? '—' }}--}}
{{--                            </td>--}}
{{--                            <td>--}}
{{--                                @if ($movement->transaction?->type)--}}
{{--                                    <span class="badge badge-slate text-[11px]">{{ $movement->transaction->type->label() }}</span>--}}
{{--                                @else--}}
{{--                                    <span class="text-slate-400">—</span>--}}
{{--                                @endif--}}
{{--                            </td>--}}
{{--                            <td class="font-mono text-xs text-slate-500 dark:text-slate-400">--}}
{{--                                {{ $movement->transaction?->reference_no ?? '—' }}--}}
{{--                            </td>--}}
{{--                            <td class="text-right tabular-nums font-bold text-xs {{ $pos ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">--}}
{{--                                {{ $pos ? '+' : '' }}{{ number_format((float) $movement->qty, 2) }}--}}
{{--                            </td>--}}
{{--                            <td class="text-xs text-slate-500 dark:text-slate-400">--}}
{{--                                {{ $movement->transaction?->postedBy?->name ?? '—' }}--}}
{{--                            </td>--}}
{{--                        </tr>--}}
{{--                    @empty--}}
{{--                        <tr>--}}
{{--                            <td colspan="6" class="py-12 text-center">--}}
{{--                                <x-ui.icon name="arrow-path" class="mx-auto w-8 h-8 text-slate-300 dark:text-slate-600 mb-2" />--}}
{{--                                <p class="text-xs font-medium text-slate-500">No movements found for this stock record.</p>--}}
{{--                            </td>--}}
{{--                        </tr>--}}
{{--                    @endforelse--}}
{{--                </tbody>--}}
{{--            </x-ui.table>--}}
{{--        </div>--}}
{{--        @if ($this->movements->hasPages())--}}
{{--            <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-800">--}}
{{--                {{ $this->movements->links() }}--}}
{{--            </div>--}}
{{--        @endif--}}
{{--    </div>--}}

    {{-- Serial Numbers --}}
    @if ($b->item?->track_serial && $this->serials->isNotEmpty())
        @php
            $serialCount  = $this->serials->count();
            $serialSearch = '';
        @endphp
                <div class="card overflow-hidden"
                     x-data="{
                        open: {{ $serialCount <= 20 ? 'true' : 'false' }},
                        search: '',
                     }">

                    {{-- Header --}}
                    <div class="card-header border-b border-slate-100 dark:border-slate-800/80 cursor-pointer select-none"
                         @click="open = !open">
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 shrink-0">
                                <x-ui.icon name="list" class="w-3.5 h-3.5" />
                            </span>
                            <h3 class="card-title font-semibold">Serial Numbers</h3>
                            <span class="badge badge-slate">{{ $serialCount }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            @php
                                $statusGroups = $this->serials->groupBy(fn($s) => $s->status->label());
                            @endphp
                            @foreach ($statusGroups as $label => $group)
                                <span class="badge badge-{{ $group->first()->status->color() }} text-[11px]">
                                    {{ $group->count() }} {{ $label }}
                                </span>
                            @endforeach
                            <span class="ml-1 text-slate-400 dark:text-slate-500 transition-transform duration-200"
                                  :class="open ? 'rotate-180' : 'rotate-0'">
                                <x-ui.icon name="chevron-down" class="w-4 h-4" />
                            </span>
                        </div>
                    </div>

                    {{-- Collapsible body --}}
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-1">

                        {{-- Search bar (shown when > 10 serials) --}}
                        @if ($serialCount > 10)
                            <div class="px-4 py-2.5 border-b border-slate-100 dark:border-slate-800/60">
                                <div class="relative">
                                    <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" />
                                    <input type="search"
                                           x-model="search"
                                           placeholder="Search serial number…"
                                           class="input w-full pl-8 text-xs py-1.5 h-8" />
                                </div>
                            </div>
                        @endif

                        {{-- Table with max-height scroll --}}
                        <div class="overflow-y-auto" style="max-height: 360px;">
                            <x-ui.table>
                                <thead class="sticky top-0 bg-white/95 dark:bg-zinc-900/95 backdrop-blur-sm z-10">
                                    <tr>
                                        <th class="w-10">#</th>
                                        <th>Serial No.</th>
                                        <th>Location</th>
                                        <th>Stock Age</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($this->serials as $idx => $sn)
                                        <tr x-show="search === '' || '{{ strtolower($sn->serial_no) }}'.includes(search.toLowerCase())"
                                            wire:key="sn-{{ $sn->id }}">
                                            <td class="tabular-nums text-xs text-slate-400">{{ $idx + 1 }}</td>
                                            <td class="font-mono text-xs font-semibold text-slate-800 dark:text-slate-200">
                                                <template x-if="search !== ''">
                                                    <span x-html="'{{ $sn->serial_no }}'.replace(
                                                        new RegExp(search.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi'),
                                                        m => `<mark class=\'bg-yellow-200 dark:bg-yellow-800/60 rounded\'>${m}</mark>`
                                                    )"></span>
                                                </template>
                                                <template x-if="search === ''">
                                                    <span>{{ $sn->serial_no }}</span>
                                                </template>
                                            </td>
                                            <td>
                                                <span class="font-mono text-xs text-slate-600 dark:text-slate-300">{{ $sn->location?->code ?? '—' }}</span>
                                            </td>
                                            <td class="text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                                @if ($sn->first_received_at)
                                                    <span title="{{ $sn->first_received_at->format('d M Y, H:i') }}">
                                                        {{ $sn->first_received_at->diffForHumans(null, true, false, 1) }}
                                                    </span>
                                                @else
                                                    <span class="text-slate-300 dark:text-slate-600">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ $sn->status->color() }} text-[11px]">{{ $sn->status->label() }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.table>
                        </div>

                        {{-- Footer count when searching --}}
                        <div class="px-4 py-2 border-t border-slate-100 dark:border-slate-800/60 flex items-center justify-between"
                             x-show="search !== ''">
                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                Showing serials matching "<span class="font-mono font-semibold" x-text="search"></span>"
                            </span>
                            <button type="button" @click.stop="search = ''"
                                    class="text-xs text-simba-600 dark:text-simba-400 hover:underline">
                                Clear search
                            </button>
                        </div>

                    </div>
                </div>
    @endif

</div>
