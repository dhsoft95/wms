<div class="space-y-4">
    <div class="flex items-center gap-2">
        <a href="{{ route('scan.home') }}" wire:navigate class="p-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
            <x-ui.icon name="chevron-down" class="w-5 h-5 rotate-90 text-slate-600 dark:text-slate-300" />
        </a>
        <h1 class="text-lg font-bold text-slate-800 dark:text-slate-100">Receive Goods</h1>
    </div>

    <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm border border-slate-200 dark:border-slate-800 space-y-3">
        <label class="label">Scan or enter barcode</label>
        <div class="flex gap-2">
            <input
                wire:model="barcode"
                wire:keydown.enter="scan"
                type="text"
                placeholder="Scan barcode…"
                autofocus
                class="input flex-1 text-lg tabular-nums"
            />
            <button type="button" wire:click="scan" class="btn btn-primary px-5">Scan</button>
        </div>
    </div>

    @if ($scannedItem)
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm border border-slate-200 dark:border-slate-800 space-y-4">
            <div>
                <p class="section-label">Scanned item</p>
                <p class="font-bold text-lg mt-1 mono">{{ $scannedItem['sku'] }}</p>
                <p class="text-slate-500">{{ $scannedItem['description'] }}</p>
                <p class="text-sm mt-2">Expected qty: <span class="font-semibold tabular-nums">{{ number_format($scannedItem['expected']) }}</span></p>
            </div>
            <button type="button" wire:click="confirmReceive" class="scan-btn scan-btn-primary">
                <x-ui.icon name="check" class="w-6 h-6" /> Confirm Receive
            </button>
        </div>
    @endif
</div>
