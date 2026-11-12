<div>
    {{-- Step header --}}
    @php
        $hasOverride = ($step === 'bin_override') || ($step === 'confirm' && $overrideReason !== '');
        $steps = ['lpn_or_item'=>1,'bin'=>2];
        if ($hasOverride) { $steps['bin_override'] = 3; $steps['confirm'] = 4; }
        else { $steps['confirm'] = 3; }
        $total   = count($steps);
        $stepLabels = ['lpn_or_item'=>'Scan LPN/Item','bin'=>'Scan Bin','bin_override'=>'Override Reason','confirm'=>'Confirm'];
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

    {{-- LPN or Item Step --}}
    @if ($step === 'lpn_or_item')
        <div class="space-y-3">
            <p class="text-lg font-bold text-slate-800 dark:text-slate-100">Scan LPN or item barcode:</p>
            <x-scan-input wire:model="scanInput" :method="'processScan'" placeholder="LPN or SKU barcode…" class="text-lg h-14" />

            @if ($this->myTasks->isNotEmpty())
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 mt-4">My Open Tasks</p>
                <div class="space-y-2">
                    @foreach ($this->myTasks as $task)
                        <button wire:click="selectTask({{ $task->id }})"
                                class="w-full text-left p-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl hover:border-simba-400 transition-colors">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-bold text-sm font-mono">{{ $task->item?->sku }}</p>
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $task->item?->name }}</p>
                                </div>
                            </div>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Bin Step --}}
    @if ($step === 'bin')
        <div class="space-y-3">
            {{-- LPN contents --}}
            @if ($this->currentLpn)
                <div class="bg-simba-50 dark:bg-simba-900/20 border border-simba-200 rounded-xl p-3">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-semibold text-slate-500">LPN</p>
                        <span class="badge badge-slate mono text-xs">{{ $this->currentLpn->code }}</span>
                    </div>
                    @if ($this->lpnContents->isNotEmpty())
                        <ul class="space-y-1">
                            @foreach ($this->lpnContents as $balance)
                                <li class="flex justify-between text-sm">
                                    <span class="font-mono font-medium">{{ $balance->item?->sku }}</span>
                                    <span class="text-slate-500 tabular-nums">{{ $balance->qty_on_hand }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-xs text-slate-400">No stock on this LPN.</p>
                    @endif
                </div>
            @elseif ($this->currentTask)
                <div class="bg-simba-50 dark:bg-simba-900/20 border border-simba-200 rounded-xl p-3 text-sm">
                    <p class="text-slate-500">Item</p>
                    <p class="font-bold text-simba-700 dark:text-simba-400 font-mono">{{ $this->currentTask->item?->sku }}</p>
                    <p class="text-xs text-slate-400">{{ $this->currentTask->item?->name }}</p>
                </div>
            @endif

            @if ($this->suggestedLocation)
                <div class="flex items-center gap-2 px-3 py-2 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-xl text-sm">
                    <x-ui.icon name="map-pin" class="w-4 h-4 text-blue-500 shrink-0" />
                    <span class="text-slate-500">Suggested:</span>
                    <span class="font-bold font-mono text-blue-700 dark:text-blue-400">{{ $this->suggestedLocation->code }}</span>
                </div>
            @endif

            <p class="text-lg font-bold text-slate-800 dark:text-slate-100">Scan destination bin:</p>
            <x-scan-input wire:model="scanInput" :method="'processScan'" placeholder="BIN-XXXXX" class="text-lg h-14 font-mono" />
        </div>
    @endif

    {{-- Bin Override Step --}}
    @if ($step === 'bin_override')
        <div class="space-y-4">
            <div class="flex items-start gap-3 px-4 py-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 rounded-xl">
                <x-ui.icon name="exclamation-triangle" class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" />
                <div class="text-sm">
                    <p class="font-semibold text-amber-700 dark:text-amber-400">Bin override</p>
                    <p class="text-slate-500 mt-0.5">
                        Suggested <span class="font-mono font-semibold">{{ $this->suggestedLocation?->code }}</span>,
                        scanned <span class="font-mono font-semibold">{{ $this->destination?->code }}</span>.
                    </p>
                </div>
            </div>

            <div class="space-y-2">
                <label for="override-reason" class="text-sm font-medium text-slate-700 dark:text-slate-300">Reason for override:</label>
                <textarea
                    id="override-reason"
                    wire:model="overrideReason"
                    rows="3"
                    placeholder="e.g. Suggested bin is full, item requires cold storage…"
                    class="input w-full resize-none"
                ></textarea>
            </div>

            <button wire:click="acceptOverride"
                    class="w-full btn btn-primary h-14 text-base font-bold">
                Continue &rarr;
            </button>
        </div>
    @endif

    {{-- Confirm Step --}}
    @if ($step === 'confirm')
        <div class="space-y-4">
            <p class="text-lg font-bold">Confirm putaway:</p>

            <div class="bg-green-50 dark:bg-green-900/20 border-2 border-green-400 rounded-2xl p-6 text-center">
                <p class="text-xs text-slate-500 mb-1">Destination Bin</p>
                <p class="text-4xl font-black font-mono text-green-700 dark:text-green-400 tracking-wide">
                    {{ $this->destination?->code ?? '—' }}
                </p>
                @if ($this->destination?->name)
                    <p class="text-sm text-slate-500 mt-1">{{ $this->destination->name }}</p>
                @endif
            </div>

            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl divide-y divide-slate-100 dark:divide-slate-700">
                @if ($this->currentLpn)
                    <div class="flex justify-between px-4 py-3">
                        <span class="text-sm text-slate-400">LPN</span>
                        <span class="text-sm font-semibold font-mono">{{ $this->currentLpn->code }}</span>
                    </div>
                @endif
                @if ($this->currentTask)
                    <div class="flex justify-between px-4 py-3">
                        <span class="text-sm text-slate-400">Item</span>
                        <span class="text-sm font-semibold font-mono">{{ $this->currentTask->item?->sku }}</span>
                    </div>
                    <div class="flex justify-between px-4 py-3">
                        <span class="text-sm text-slate-400">Task</span>
                        <span class="text-sm font-semibold">#{{ $this->currentTask->id }}</span>
                    </div>
                @endif
                @if ($overrideReason)
                    <div class="px-4 py-3">
                        <span class="text-sm text-slate-400">Override reason</span>
                        <p class="text-sm font-medium mt-0.5">{{ $overrideReason }}</p>
                    </div>
                @endif
            </div>

            <button wire:click="confirmPutaway" wire:loading.attr="disabled"
                    class="w-full btn btn-primary h-16 text-lg font-bold">
                <span wire:loading.remove>Confirm Putaway</span>
                <span wire:loading>Saving&hellip;</span>
            </button>
        </div>
    @endif
</div>
