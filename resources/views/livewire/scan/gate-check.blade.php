<div class="space-y-4">
    {{-- Header --}}
    <div class="flex items-center gap-2">
        <a href="{{ route('scan.home') }}" wire:navigate class="p-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
            <x-ui.icon name="chevron-down" class="w-5 h-5 rotate-90 text-slate-600 dark:text-slate-300" />
        </a>
        <h1 class="text-lg font-bold text-slate-800 dark:text-slate-100">Gate Check</h1>
    </div>

    {{-- Step: scan --}}
    @if ($step === 'scan')
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm border border-slate-200 dark:border-slate-800 space-y-3">
            <p class="text-lg font-bold text-slate-800 dark:text-slate-100">Scan Gate Pass</p>
            <p class="text-sm text-slate-400">Enter or scan the gate pass number to verify exit.</p>
            <input wire:model="gpNumber" wire:keydown.enter="scan"
                   type="text" placeholder="GP-2026-00001"
                   class="input text-lg h-14 font-mono uppercase" autocomplete="off" autofocus />
            @if ($scanError)
                <p class="text-sm text-red-500">{{ $scanError }}</p>
            @endif
            <button wire:click="scan" class="w-full btn btn-primary h-14 text-base font-bold">
                <span wire:loading.remove wire:target="scan">Scan</span>
                <span wire:loading wire:target="scan">Searching&hellip;</span>
            </button>
        </div>
    @endif

    {{-- Step: confirm --}}
    @if ($step === 'confirm' && $gatePass)
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm border border-amber-300 dark:border-amber-700 space-y-4">
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wider">Gate Pass</p>
                <p class="font-bold text-xl font-mono text-slate-800 dark:text-slate-100">{{ $gatePass->number }}</p>
            </div>

            <div class="bg-slate-50 dark:bg-slate-800 rounded-xl divide-y divide-slate-100 dark:divide-slate-700">
                <div class="flex justify-between px-4 py-3">
                    <span class="text-sm text-slate-400">Delivery Order</span>
                    <span class="text-sm font-semibold font-mono">{{ $gatePass->dispatch->deliveryOrder->number }}</span>
                </div>
                <div class="flex justify-between px-4 py-3">
                    <span class="text-sm text-slate-400">Consignee</span>
                    <span class="text-sm font-semibold">{{ $gatePass->dispatch->deliveryOrder->consigneePartner?->name ?? '—' }}</span>
                </div>
                <div class="flex justify-between px-4 py-3">
                    <span class="text-sm text-slate-400">Vehicle No</span>
                    <span class="text-sm font-semibold font-mono">{{ $gatePass->dispatch->vehicle_no }}</span>
                </div>
                <div class="flex justify-between px-4 py-3">
                    <span class="text-sm text-slate-400">Driver</span>
                    <span class="text-sm font-semibold">{{ $gatePass->dispatch->driver_name }}</span>
                </div>
            </div>

            <button wire:click="confirmExit" wire:loading.attr="disabled"
                    class="w-full btn btn-primary h-16 text-lg font-bold">
                <span wire:loading.remove wire:target="confirmExit">
                    <x-ui.icon name="check" class="w-5 h-5 inline-block mr-1" /> Confirm Gate Exit
                </span>
                <span wire:loading wire:target="confirmExit">Processing&hellip;</span>
            </button>

            <button wire:click="startOver" class="w-full text-sm text-slate-400 hover:text-slate-600 transition-colors py-1">
                &larr; Back
            </button>
        </div>
    @endif

    {{-- Step: done --}}
    @if ($step === 'done')
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm border border-emerald-300 dark:border-emerald-700 space-y-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                    <x-ui.icon name="check" class="w-7 h-7 text-emerald-600" />
                </div>
                <div>
                    <p class="font-bold text-lg text-slate-800 dark:text-slate-100">Exit Confirmed</p>
                    <p class="text-sm text-slate-400">Vehicle has been cleared</p>
                </div>
            </div>

            @if ($gatePass)
                <div class="bg-slate-50 dark:bg-slate-800 rounded-xl px-4 py-3">
                    <p class="text-xs text-slate-400">Gate Pass</p>
                    <p class="font-bold font-mono text-lg">{{ $gatePass->number }}</p>
                </div>
            @endif

            <button wire:click="startOver" class="w-full btn btn-primary h-14 text-base font-bold">
                Scan Next
            </button>
        </div>
    @endif
</div>
