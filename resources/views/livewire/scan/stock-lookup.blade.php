<div class="space-y-4">
    <div class="flex items-center gap-2">
        <a href="{{ route('scan.home') }}" wire:navigate class="p-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
            <x-ui.icon name="chevron-down" class="w-5 h-5 rotate-90 text-slate-600 dark:text-slate-300" />
        </a>
        <h1 class="text-lg font-bold text-slate-800 dark:text-slate-100">Stock Lookup</h1>
    </div>

    <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm border border-slate-200 dark:border-slate-800 space-y-3">
        <label class="label">Scan or enter barcode / SKU code</label>
        <div class="flex gap-2">
            <input wire:model="barcode" wire:keydown.enter="lookup" type="text" placeholder="Scan barcode…" autofocus class="input flex-1 text-lg tabular-nums" />
            <button type="button" wire:click="lookup" class="btn btn-primary px-5">Look up</button>
        </div>
    </div>

    @if ($result)
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm border border-slate-200 dark:border-slate-800 space-y-4">
            <div>
                <p class="section-label">Item details</p>
                <p class="font-bold text-xl mono mt-1">{{ $result['sku'] }}</p>
                <p class="text-slate-500 mt-0.5">{{ $result['description'] }}</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-slate-50 dark:bg-slate-800 p-4 text-center">
                    <p class="text-xs text-slate-500 mb-1">On Hand</p>
                    <p class="text-3xl font-bold tabular-nums text-slate-800 dark:text-slate-100">{{ number_format($result['on_hand']) }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 dark:bg-slate-800 p-4 text-center">
                    <p class="text-xs text-slate-500 mb-1">Bin</p>
                    <p class="text-2xl font-bold mono text-slate-800 dark:text-slate-100">{{ $result['bin'] }}</p>
                </div>
            </div>
            <div class="text-sm space-y-1 text-slate-500">
                <div class="flex justify-between"><span>Warehouse</span><span class="font-semibold text-slate-700 dark:text-slate-300">{{ $result['warehouse'] }}</span></div>
                <div class="flex justify-between"><span>Expiry date</span><span class="font-semibold tabular-nums text-slate-700 dark:text-slate-300">{{ $result['expiry'] }}</span></div>
            </div>
        </div>
    @endif
</div>
