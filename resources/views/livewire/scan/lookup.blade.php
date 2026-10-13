<div class="space-y-4">
    {{-- Sticky scan input --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm">
        <label class="text-xs font-semibold uppercase tracking-wider text-slate-400 block mb-2">Scan any barcode</label>
        <div class="flex gap-2">
            <input type="text" wire:model.defer="barcode"
                   x-data="{lastScan:'',lastAt:0}"
                   x-on:keydown.enter="const now=Date.now();if($el.value===lastScan&&now-lastAt<600){$el.value='';return;}lastScan=$el.value;lastAt=now;$wire.lookup()"
                   placeholder="LPN / Item / Bin / Serial&hellip;"
                   class="input flex-1 text-lg h-13" autocomplete="off" autofocus />
            <button wire:click="lookup" class="btn btn-primary px-5 h-13 flex-shrink-0">
                <x-ui.icon name="search" class="w-5 h-5" />
            </button>
        </div>
    </div>

    {{-- Error state --}}
    @if ($errorMsg)
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-300 rounded-xl p-4 text-red-700 dark:text-red-400 text-sm font-semibold">
            {{ $errorMsg }}
        </div>
    @endif

    {{-- Result: Item + stock --}}
    @if ($resultType === 'item' && isset($data['item']))
        @php $item = $data['item']; $stock = $data['stock']; @endphp
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden shadow-sm">
            <div class="p-4 border-b border-slate-100 dark:border-slate-700">
                <p class="text-xs text-slate-400">Item</p>
                <p class="font-bold text-xl font-mono">{{ $item->sku }}</p>
                <p class="text-slate-500 text-sm">{{ $item->name }}</p>
            </div>
            @if ($stock->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-700/50 text-xs text-slate-400 uppercase tracking-wider">
                                <th class="px-3 py-2 text-left">Location</th>
                                <th class="px-3 py-2 text-left">VW</th>
                                <th class="px-3 py-2 text-right">On Hand</th>
                                <th class="px-3 py-2 text-right">Allocated</th>
                                <th class="px-3 py-2 text-right">On Hold</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach ($stock as $row)
                                <tr>
                                    <td class="px-3 py-2 font-mono font-semibold">{{ $row->location }}</td>
                                    <td class="px-3 py-2 text-slate-500 text-xs">{{ $row->vw }}</td>
                                    <td class="px-3 py-2 text-right font-semibold">{{ number_format($row->qty_on_hand, 2) }}</td>
                                    <td class="px-3 py-2 text-right text-amber-600">{{ number_format($row->qty_allocated, 2) }}</td>
                                    <td class="px-3 py-2 text-right text-red-500">{{ number_format($row->qty_on_hold, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="px-4 py-6 text-sm text-slate-400 text-center">No stock on hand.</p>
            @endif
        </div>
    @endif

    {{-- Result: Location contents --}}
    @if ($resultType === 'location' && isset($data['location']))
        @php $loc = $data['location']; $contents = $data['contents']; @endphp
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden shadow-sm">
            <div class="p-4 border-b border-slate-100 dark:border-slate-700">
                <p class="text-xs text-slate-400">Location</p>
                <p class="font-black text-2xl font-mono">{{ $loc->code }}</p>
                @if ($loc->name)
                    <p class="text-sm text-slate-500">{{ $loc->name }}</p>
                @endif
                <span class="inline-block mt-1 text-xs px-2 py-0.5 rounded-full font-semibold
                    {{ $loc->status === 'active' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-600' }}">
                    {{ ucfirst($loc->status) }}
                </span>
            </div>
            @if ($contents->isNotEmpty())
                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach ($contents as $row)
                        <div class="px-4 py-3 flex justify-between items-start">
                            <div>
                                <p class="font-semibold text-sm font-mono">{{ $row->sku }}</p>
                                <p class="text-xs text-slate-400">{{ $row->name }}</p>
                                @if ($row->lpn_code)
                                    <p class="text-xs text-slate-400 mt-0.5">LPN: <span class="font-mono">{{ $row->lpn_code }}</span></p>
                                @endif
                            </div>
                            <div class="text-right">
                                <p class="font-bold">{{ number_format($row->qty_on_hand, 2) }}</p>
                                <p class="text-xs text-slate-400">{{ $row->stock_status }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="px-4 py-6 text-sm text-slate-400 text-center">This location is empty.</p>
            @endif
        </div>
    @endif

    {{-- Result: LPN --}}
    @if ($resultType === 'lpn' && isset($data['lpn']))
        @php $lpn = $data['lpn']; @endphp
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm divide-y divide-slate-100 dark:divide-slate-700">
            <div class="px-4 py-4">
                <p class="text-xs text-slate-400">LPN</p>
                <p class="font-black text-2xl font-mono">{{ $lpn->code }}</p>
            </div>
            @foreach ([
                ['Warehouse', $lpn->warehouse?->name ?? '—'],
                ['Current Location', $lpn->currentLocation?->code ?? 'Not placed'],
                ['Status', $lpn->status ?? '—'],
            ] as [$label, $value])
                <div class="flex justify-between px-4 py-3">
                    <span class="text-sm text-slate-400">{{ $label }}</span>
                    <span class="text-sm font-semibold">{{ $value }}</span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Result: Serial --}}
    @if ($resultType === 'serial' && isset($data['serial']))
        @php $serial = $data['serial']; @endphp
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm divide-y divide-slate-100 dark:divide-slate-700">
            <div class="px-4 py-4">
                <p class="text-xs text-slate-400">Serial Number</p>
                <p class="font-black text-xl font-mono">{{ $serial->serial_number }}</p>
            </div>
            @foreach ([
                ['Item', $serial->item?->sku . ' — ' . $serial->item?->name],
                ['Status', $serial->status ?? '—'],
                ['Location', $serial->location_id ? $serial->location?->code : 'Unknown'],
            ] as [$label, $value])
                <div class="flex justify-between px-4 py-3">
                    <span class="text-sm text-slate-400">{{ $label }}</span>
                    <span class="text-sm font-semibold">{{ $value }}</span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Result: Container --}}
    @if ($resultType === 'container' && isset($data['container']))
        @php $container = $data['container']; @endphp
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm divide-y divide-slate-100 dark:divide-slate-700">
            <div class="px-4 py-4">
                <p class="text-xs text-slate-400">Container</p>
                <p class="font-black text-2xl font-mono">{{ $container->number }}</p>
            </div>
            @foreach ([
                ['Warehouse', $container->warehouse?->name ?? '—'],
                ['Yard Slot', $container->yardSlot?->code ?? 'Not slotted'],
                ['Status', $container->status ?? '—'],
            ] as [$label, $value])
                <div class="flex justify-between px-4 py-3">
                    <span class="text-sm text-slate-400">{{ $label }}</span>
                    <span class="text-sm font-semibold">{{ $value }}</span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Result: GRN --}}
    @if ($resultType === 'grn' && isset($data['grn']))
        @php $grn = $data['grn']; @endphp
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm divide-y divide-slate-100 dark:divide-slate-700">
            <div class="px-4 py-4">
                <p class="text-xs text-slate-400">GRN</p>
                <p class="font-black text-2xl font-mono">{{ $grn->number }}</p>
            </div>
            @foreach ([
                ['Warehouse', $grn->warehouse?->name ?? '—'],
                ['Supplier', $grn->supplierPartner?->name ?? '—'],
                ['Status', $grn->status?->label() ?? ucfirst($grn->status?->value ?? '—')],
            ] as [$label, $value])
                <div class="flex justify-between px-4 py-3">
                    <span class="text-sm text-slate-400">{{ $label }}</span>
                    <span class="text-sm font-semibold">{{ $value }}</span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- GS1 data pill --}}
    @if ($gs1Data)
        <div class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">GS1 Data</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($gs1Data as $key => $val)
                    <span class="text-xs bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg px-2 py-1 font-mono">
                        {{ $key }}: {{ $val }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif
</div>
