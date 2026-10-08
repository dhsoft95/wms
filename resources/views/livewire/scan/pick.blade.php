<div class="space-y-4">
    <div class="flex items-center gap-2">
        <a href="{{ route('scan.home') }}" wire:navigate class="p-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
            <x-ui.icon name="chevron-down" class="w-5 h-5 rotate-90 text-slate-600 dark:text-slate-300" />
        </a>
        <h1 class="text-lg font-bold text-slate-800 dark:text-slate-100">Pick</h1>
    </div>

    @if (! $pickList)
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm border border-slate-200 dark:border-slate-800 space-y-3">
            <label class="label">Order / pick list reference</label>
            <div class="flex gap-2">
                <input wire:model="orderRef" wire:keydown.enter="loadOrder" type="text" placeholder="e.g. ORD-2026-101" class="input flex-1 font-mono" />
                <button type="button" wire:click="loadOrder" class="btn btn-primary px-5">Load</button>
            </div>
        </div>
    @else
        <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="bg-simba-600 px-5 py-3">
                <p class="text-xs text-simba-200 uppercase tracking-wider">Pick list</p>
                <p class="font-bold text-white mono">{{ $orderRef }}</p>
            </div>
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($pickList as $i => $line)
                    <li class="flex items-center gap-3 px-5 py-4 {{ $line['picked'] ? 'opacity-50' : '' }}">
                        <div class="flex-1">
                            <p class="font-semibold text-sm mono">{{ $line['sku'] }}</p>
                            <p class="text-xs text-slate-500">{{ $line['description'] }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">Bin: <span class="font-semibold mono">{{ $line['bin'] }}</span> · Qty: <span class="font-semibold tabular-nums">{{ $line['qty'] }}</span></p>
                        </div>
                        @if (! $line['picked'])
                            <button type="button" wire:click="markPicked({{ $i }})" class="w-12 h-12 rounded-xl bg-simba-600 text-white flex items-center justify-center flex-shrink-0 active:scale-95 transition-transform">
                                <x-ui.icon name="check" class="w-6 h-6" />
                            </button>
                        @else
                            <div class="w-12 h-12 rounded-xl bg-green-100 dark:bg-green-900/30 flex items-center justify-center flex-shrink-0">
                                <x-ui.icon name="check" class="w-6 h-6 text-green-600" />
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
