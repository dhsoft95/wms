<div class="space-y-4">
    {{-- Header --}}
    <div class="flex items-center gap-2">
        <a href="{{ route('scan.home') }}" wire:navigate class="p-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
            <x-ui.icon name="chevron-down" class="w-5 h-5 rotate-90 text-slate-600 dark:text-slate-300" />
        </a>
        <h1 class="text-lg font-bold text-slate-800 dark:text-slate-100">Dispatch</h1>
    </div>

    {{-- Step: scan_do --}}
    @if ($step === 'scan_do')
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm border border-slate-200 dark:border-slate-800 space-y-3">
            <p class="text-lg font-bold text-slate-800 dark:text-slate-100">Scan Delivery Order</p>
            <p class="text-sm text-slate-400">Enter or scan the DO number to begin dispatch.</p>
            <input wire:model="doNumber" wire:keydown.enter="scanDo"
                   type="text" placeholder="DO-2026-00001"
                   class="input text-lg h-14 font-mono uppercase" autocomplete="off" autofocus />
            @if ($scanError)
                <p class="text-sm text-red-500">{{ $scanError }}</p>
            @endif
            <button wire:click="scanDo" class="w-full btn btn-primary h-14 text-base font-bold">
                <span wire:loading.remove wire:target="scanDo">Load Order</span>
                <span wire:loading wire:target="scanDo">Loading&hellip;</span>
            </button>
        </div>
    @endif

    {{-- Step: fill_details --}}
    @if ($step === 'fill_details')
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm border border-slate-200 dark:border-slate-800 space-y-4">
            <p class="text-lg font-bold text-slate-800 dark:text-slate-100">Vehicle &amp; Driver Details</p>

            <div class="space-y-3">
                <div>
                    <label class="label">Vehicle No <span class="required-mark">*</span></label>
                    <input wire:model="vehicleNo" type="text" placeholder="e.g. T 123 ABC"
                           class="input font-mono uppercase" autocomplete="off" autofocus />
                    @error('vehicleNo') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label">Driver Name <span class="required-mark">*</span></label>
                    <input wire:model="driverName" type="text" placeholder="Full name"
                           class="input" autocomplete="off" />
                    @error('driverName') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label">Driver Phone <span class="text-slate-400 font-normal">(optional)</span></label>
                    <input wire:model="driverPhone" type="tel" placeholder="+255 7XX XXX XXX"
                           class="input" autocomplete="off" />
                </div>

                <div>
                    <label class="label">Seal No <span class="text-slate-400 font-normal">(optional)</span></label>
                    <input wire:model="sealNo" type="text" placeholder="e.g. SL-0001"
                           class="input font-mono uppercase" autocomplete="off" />
                </div>
            </div>

            <button wire:click="confirmDispatch" wire:loading.attr="disabled"
                    class="w-full btn btn-primary h-14 text-base font-bold">
                <span wire:loading.remove wire:target="confirmDispatch">Confirm Dispatch</span>
                <span wire:loading wire:target="confirmDispatch">Processing&hellip;</span>
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
                    <p class="font-bold text-lg text-slate-800 dark:text-slate-100">Dispatch Confirmed</p>
                    <p class="text-sm text-slate-400">Vehicle cleared for exit</p>
                </div>
            </div>

            <div class="bg-slate-50 dark:bg-slate-800 rounded-xl divide-y divide-slate-100 dark:divide-slate-700">
                <div class="flex justify-between px-4 py-3">
                    <span class="text-sm text-slate-400">Dispatch No</span>
                    <span class="text-sm font-bold font-mono">{{ $dispatchNumber }}</span>
                </div>
                @if ($gatePassNumber)
                    <div class="flex justify-between px-4 py-3">
                        <span class="text-sm text-slate-400">Gate Pass</span>
                        <span class="text-sm font-bold font-mono text-amber-600">{{ $gatePassNumber }}</span>
                    </div>
                @endif
            </div>

            <button wire:click="startOver" class="w-full btn btn-primary h-14 text-base font-bold">
                Scan Next
            </button>
        </div>
    @endif
</div>
