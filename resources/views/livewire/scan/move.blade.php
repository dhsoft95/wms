<div>
    {{-- Step header --}}
    @php
        $hasPickVw = $step === 'pick_vw' || ($step !== 'source_bin' && $step !== 'lpn_or_item' && count($stockOptions) > 0);
        if ($hasPickVw) {
            $steps = ['source_bin'=>1,'lpn_or_item'=>2,'pick_vw'=>3,'dest_bin'=>4,'confirm'=>5];
        } else {
            $steps = ['source_bin'=>1,'lpn_or_item'=>2,'dest_bin'=>3,'confirm'=>4];
        }
        $stepLabels = ['source_bin'=>'Source Bin','lpn_or_item'=>'Scan LPN/Item','pick_vw'=>'Select Warehouse','dest_bin'=>'Dest Bin','confirm'=>'Confirm'];
        $total   = count($steps);
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

    {{-- Source Bin Step --}}
    @if ($step === 'source_bin')
        <div class="space-y-3">
            <p class="text-lg font-bold text-slate-800 dark:text-slate-100">Scan source bin location:</p>
            <x-scan-input wire:model="scanInput" :method="'processScan'" placeholder="BIN-XXXXX" class="text-lg h-14 font-mono" />
            <p class="text-xs text-slate-400">Scan the bin you are picking from.</p>
        </div>
    @endif

    {{-- LPN or Item Step --}}
    @if ($step === 'lpn_or_item')
        <div class="space-y-3">
            <div class="bg-simba-50 dark:bg-simba-900/20 border border-simba-200 rounded-xl p-3 text-sm">
                <p class="text-slate-500">Source Bin</p>
                <p class="font-bold text-simba-700 dark:text-simba-400 font-mono">{{ $this->sourceBin?->code }}</p>
            </div>
            <p class="text-lg font-bold text-slate-800 dark:text-slate-100">Scan LPN or item:</p>
            <x-scan-input wire:model="scanInput" :method="'processScan'" placeholder="LPN or SKU/barcode" class="text-lg h-14" />
            <div class="grid grid-cols-2 gap-2 mt-1">
                <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded-xl text-center border border-slate-200 dark:border-slate-700">
                    <p class="text-xs text-slate-400 mb-0.5">Full Pallet</p>
                    <p class="text-sm font-semibold">Scan LPN</p>
                </div>
                <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded-xl text-center border border-slate-200 dark:border-slate-700">
                    <p class="text-xs text-slate-400 mb-0.5">Partial Move</p>
                    <p class="text-sm font-semibold">Scan Item</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Pick Virtual Warehouse Step --}}
    @if ($step === 'pick_vw')
        <div class="space-y-3">
            <div class="bg-simba-50 dark:bg-simba-900/20 border border-simba-200 rounded-xl p-3 text-sm">
                <p class="text-slate-500">Item</p>
                <p class="font-bold text-simba-700 dark:text-simba-400 font-mono">{{ $this->currentItem?->sku }}</p>
            </div>
            <p class="text-lg font-bold">Select virtual warehouse:</p>
            <div class="space-y-2">
                @foreach ($stockOptions as $option)
                    <button wire:click="pickVw({{ $option['id'] }})"
                            class="w-full text-left p-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl hover:border-simba-400 transition-colors">
                        <p class="font-semibold">{{ $option['name'] }}</p>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Dest Bin Step --}}
    @if ($step === 'dest_bin')
        <div class="space-y-3">
            @if ($this->currentLpn)
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4">
                    <p class="text-xs text-slate-400">Moving Pallet</p>
                    <p class="font-bold font-mono text-lg">{{ $this->currentLpn->code }}</p>
                </div>
            @elseif ($this->currentItem)
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 space-y-3">
                    <div>
                        <p class="text-xs text-slate-400">Moving Item</p>
                        <p class="font-bold text-lg">{{ $this->currentItem->sku }}</p>
                        <p class="text-sm text-slate-500">{{ $this->currentItem->name }}</p>
                    </div>
                    <div>
                        <label class="text-xs text-slate-400 block mb-1">Quantity to move</label>
                        <input type="number" wire:model="qty"
                               min="0.001" step="any" placeholder="0"
                               class="input text-xl h-12 text-center font-bold w-full" autocomplete="off" />
                    </div>
                </div>
            @endif
            <p class="text-lg font-bold text-slate-800 dark:text-slate-100">Scan destination bin:</p>
            <x-scan-input wire:model="scanInput" :method="'processScan'" placeholder="BIN-XXXXX" class="text-lg h-14 font-mono" />
        </div>
    @endif

    {{-- Confirm Step --}}
    @if ($step === 'confirm')
        <div class="space-y-4">
            <p class="text-lg font-bold">Confirm move:</p>
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl divide-y divide-slate-100 dark:divide-slate-700">
                <div class="flex justify-between px-4 py-3">
                    <span class="text-sm text-slate-400">From</span>
                    <span class="text-sm font-semibold font-mono">{{ $this->sourceBin?->code }}</span>
                </div>
                <div class="flex justify-between px-4 py-3">
                    <span class="text-sm text-slate-400">To</span>
                    <span class="text-sm font-semibold font-mono">{{ $this->destBin?->code }}</span>
                </div>
                @if ($this->currentLpn)
                    <div class="flex justify-between px-4 py-3">
                        <span class="text-sm text-slate-400">Pallet (LPN)</span>
                        <span class="text-sm font-semibold font-mono">{{ $this->currentLpn->code }}</span>
                    </div>
                @endif
                @if ($this->currentItem)
                    <div class="flex justify-between px-4 py-3">
                        <span class="text-sm text-slate-400">Item</span>
                        <span class="text-sm font-semibold">{{ $this->currentItem->sku }}</span>
                    </div>
                    <div class="flex justify-between px-4 py-3">
                        <span class="text-sm text-slate-400">Qty</span>
                        <span class="text-sm font-semibold">{{ $qty }} {{ $this->currentItem->baseUom?->code }}</span>
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-3">
                <div class="flex-1 text-center p-4 bg-slate-100 dark:bg-slate-800 rounded-xl">
                    <p class="text-xs text-slate-400 mb-1">From</p>
                    <p class="font-black font-mono text-xl">{{ $this->sourceBin?->code }}</p>
                </div>
                <x-ui.icon name="arrow-right" class="w-6 h-6 text-slate-400 flex-shrink-0" />
                <div class="flex-1 text-center p-4 bg-green-50 dark:bg-green-900/20 border border-green-300 rounded-xl">
                    <p class="text-xs text-slate-400 mb-1">To</p>
                    <p class="font-black font-mono text-xl text-green-700 dark:text-green-400">{{ $this->destBin?->code }}</p>
                </div>
            </div>

            <button wire:click="confirmMove" wire:loading.attr="disabled"
                    class="w-full btn btn-primary h-16 text-lg font-bold">
                <span wire:loading.remove>Confirm Move</span>
                <span wire:loading>Moving&hellip;</span>
            </button>
        </div>
    @endif
</div>
