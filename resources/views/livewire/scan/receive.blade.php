<div>
    {{-- Step header --}}
    @php
        $isSerial = $this->currentItem?->track_serial ?? false;
        $steps = ['grn'=>1,'item'=>2,'qty'=>3,'condition'=>4,'lpn'=>($isSerial?6:5),'confirm'=>($isSerial?7:6)];
        if ($isSerial) $steps['serials'] = 5;
        $total   = $isSerial ? 7 : 6;
        $stepLabels = ['grn'=>'Scan GRN','item'=>'Scan Item','qty'=>'Enter Qty','condition'=>'Condition','serials'=>'Scan Serials','lpn'=>'Scan LPN','confirm'=>'Confirm'];
        $current = $steps[$step] ?? 1;
    @endphp
    <div class="flex items-center gap-3 mb-4">
        <button wire:click="back" class="p-2 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-lg transition-colors">
            <x-ui.icon name="arrow-left" class="w-5 h-5" />
        </button>
        <div class="flex-1">
            <p class="text-xs text-slate-400">Step {{ $current }} of {{ $total }}: {{ $stepLabels[$step] ?? '' }}</p>
            <div class="flex gap-1 mt-1">
                @for ($i = 1; $i <= $total; $i++)
                    <div class="h-1 flex-1 rounded-full {{ $i <= $current ? 'bg-simba-500' : 'bg-slate-200 dark:bg-slate-700' }}"></div>
                @endfor
            </div>
        </div>
        <button wire:click="cancel" class="text-xs text-slate-400 hover:text-red-500 transition-colors">Cancel</button>
    </div>

    {{-- GRN Step --}}
    @if ($step === 'grn')
        <div class="space-y-3">
            <p class="text-lg font-bold text-slate-800 dark:text-slate-100">Scan GRN barcode or select:</p>
            <x-scan-input wire:model="scanInput" :method="'processScan'" placeholder="GRN-XXXXX" class="text-lg h-14" />
            <div class="space-y-2 mt-2">
                @foreach ($this->openGrns as $grn)
                    <button wire:click="selectGrn({{ $grn->id }})"
                            class="w-full text-left p-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl hover:border-simba-400 transition-colors">
                        <p class="font-bold">{{ $grn->number }}</p>
                        <p class="text-sm text-slate-400">{{ $grn->warehouse?->name }} &middot; {{ $grn->supplierPartner?->name ?? $grn->customerPartner?->name ?? 'No partner' }}</p>
                    </button>
                @endforeach
                @if ($this->openGrns->isEmpty())
                    <p class="text-sm text-slate-400 text-center py-4">No GRNs in receiving status.</p>
                @endif
            </div>
        </div>
    @endif

    {{-- Item Step --}}
    @if ($step === 'item')
        <div class="space-y-3">
            <div class="bg-simba-50 dark:bg-simba-900/20 border border-simba-200 rounded-xl p-3 text-sm">
                <p class="text-slate-500">GRN</p>
                <p class="font-bold text-simba-700 dark:text-simba-400">{{ $this->currentGrn?->number }}</p>
            </div>
            <p class="text-lg font-bold text-slate-800 dark:text-slate-100">Scan item barcode:</p>
            <x-scan-input wire:model="scanInput" :method="'processScan'" placeholder="SKU or barcode" class="text-lg h-14" />
        </div>
    @endif

    {{-- Qty Step --}}
    @if ($step === 'qty')
        <div class="space-y-4">
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4">
                <p class="text-xs text-slate-400">Item</p>
                <p class="font-bold text-lg">{{ $this->currentItem?->sku }}</p>
                <p class="text-slate-500 text-sm">{{ $this->currentItem?->name }}</p>
            </div>
            <p class="text-lg font-bold">Enter quantity:</p>
            <input type="number" wire:model="qty" id="qty-input"
                   min="0.001" step="any" placeholder="0"
                   class="input text-2xl h-16 text-center font-bold" autocomplete="off" autofocus />
            <div class="grid grid-cols-3 gap-2">
                @foreach ([1, 5, 10, 25, 50, 100] as $q)
                    <button wire:click="$set('qty', '{{ $q }}')"
                            class="p-3 bg-slate-100 dark:bg-slate-800 rounded-xl font-bold text-lg hover:bg-simba-100 transition-colors">
                        {{ $q }}
                    </button>
                @endforeach
            </div>
            <button wire:click="setQty(qty)"
                    x-on:click.prevent="$wire.setQty(document.getElementById('qty-input').value)"
                    class="w-full btn btn-primary h-14 text-lg">
                Confirm Qty &rarr;
            </button>
        </div>
    @endif

    {{-- Condition Step --}}
    @if ($step === 'condition')
        <div class="space-y-4">
            <p class="text-lg font-bold">Condition of goods:</p>
            <button wire:click="setCondition('good')"
                    class="w-full p-6 bg-green-50 dark:bg-green-900/20 border-2 border-green-400 rounded-2xl text-green-700 dark:text-green-400 font-bold text-xl hover:bg-green-100 transition-colors">
                &#x2713; Good
            </button>
            <button wire:click="setCondition('damaged')"
                    class="w-full p-6 bg-amber-50 dark:bg-amber-900/20 border-2 border-amber-400 rounded-2xl text-amber-700 dark:text-amber-400 font-bold text-xl hover:bg-amber-100 transition-colors">
                &#x26A0; Damaged
            </button>
        </div>
    @endif

    {{-- Serials Step --}}
    @if ($step === 'serials')
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <p class="text-lg font-bold">Scan serial numbers:</p>
                <span class="text-sm font-semibold tabular-nums px-3 py-1 rounded-full
                    {{ count($serials) >= $this->requiredSerials
                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">
                    {{ count($serials) }} / {{ $this->requiredSerials }}
                </span>
            </div>

            <x-scan-input wire:model="scanInput" :method="'processScan'" placeholder="Scan serial number" class="text-lg h-14" />

            @if (count($serials) > 0)
                <ul class="space-y-1 max-h-56 overflow-y-auto">
                    @foreach ($serials as $i => $sn)
                        <li class="flex items-center gap-2 px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-mono">
                            <span class="text-xs text-slate-400 w-5 text-right">{{ $i + 1 }}.</span>
                            <span class="flex-1">{{ $sn }}</span>
                            <span class="text-emerald-500">&#x2713;</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    {{-- LPN Step --}}
    @if ($step === 'lpn')
        <div class="space-y-3">
            <p class="text-lg font-bold">Scan LPN or create new pallet:</p>
            <x-scan-input wire:model="scanInput" :method="'processScan'" placeholder="LPN-XXXXX" class="text-lg h-14" />
            <button wire:click="createNewLpn"
                    class="w-full btn btn-secondary h-14 text-base font-bold">
                + New Pallet (generate LPN)
            </button>
        </div>
    @endif

    {{-- Confirm Step --}}
    @if ($step === 'confirm')
        <div class="space-y-4">
            <p class="text-lg font-bold">Confirm receipt:</p>
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl divide-y divide-slate-100 dark:divide-slate-700">
                @foreach ([
                    ['GRN', $this->currentGrn?->number],
                    ['Item', ($this->currentItem?->sku ?? '') . ' — ' . ($this->currentItem?->name ?? '')],
                    ['Qty', $qty . ' ' . ($this->currentItem?->baseUom?->code ?? '')],
                    ['Condition', ucfirst($condition)],
                    ['LPN', ($this->currentLpn?->code ?? '') . ($newLpn ? ' (new)' : '')],
                    ['Batch', $batchNo ?: '—'],
                    ['Expiry', $expiryDate ?: '—'],
                ] as [$label, $value])
                    <div class="flex justify-between px-4 py-3">
                        <span class="text-sm text-slate-400">{{ $label }}</span>
                        <span class="text-sm font-semibold">{{ $value }}</span>
                    </div>
                @endforeach
                @if (count($serials) > 0)
                    <div class="px-4 py-3">
                        <span class="text-sm text-slate-400">Serials ({{ count($serials) }})</span>
                        <ul class="mt-1 space-y-0.5">
                            @foreach ($serials as $sn)
                                <li class="text-sm font-mono font-semibold">{{ $sn }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
            <button wire:click="saveReceipt" wire:loading.attr="disabled"
                    class="w-full btn btn-primary h-16 text-lg font-bold">
                <span wire:loading.remove>Save Receipt</span>
                <span wire:loading>Saving&hellip;</span>
            </button>
        </div>
    @endif
</div>
