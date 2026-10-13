<div>
    {{-- Step header --}}
    @php
        $steps = ['lpn'=>1,'bin'=>2,'confirm'=>3];
        $stepLabels = ['lpn'=>'Scan LPN','bin'=>'Scan Bin','confirm'=>'Confirm'];
        $current = $steps[$step] ?? 1;
        $total = 3;
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

    {{-- LPN Step --}}
    @if ($step === 'lpn')
        <div class="space-y-3">
            <p class="text-lg font-bold text-slate-800 dark:text-slate-100">Scan LPN barcode or select task:</p>
            <input type="text" wire:model.defer="barcode"
                   x-data="{lastScan:'',lastAt:0}"
                   x-on:keydown.enter="const now=Date.now();if($el.value===lastScan&&now-lastAt<600){$el.value='';return;}lastScan=$el.value;lastAt=now;$wire.processScan()"
                   data-scan-input placeholder="LPN-XXXXX"
                   class="input text-lg h-14" autocomplete="off" autofocus />

            @if ($this->myTasks->isNotEmpty())
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 mt-4">My Open Tasks</p>
                <div class="space-y-2">
                    @foreach ($this->myTasks as $task)
                        <button wire:click="selectTask({{ $task->id }})"
                                class="w-full text-left p-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl hover:border-simba-400 transition-colors">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-bold text-sm">{{ $task->lpn?->code ?? 'No LPN' }}</p>
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $task->item?->sku }} &middot; {{ $task->item?->name }}</p>
                                </div>
                                @if ($task->suggestedLocation)
                                    <span class="text-xs bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 px-2 py-1 rounded-lg font-mono font-semibold">
                                        {{ $task->suggestedLocation->code }}
                                    </span>
                                @endif
                            </div>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Bin Step (manual bin scan — no suggestion) --}}
    @if ($step === 'bin')
        <div class="space-y-3">
            @if ($this->currentLpn)
                <div class="bg-simba-50 dark:bg-simba-900/20 border border-simba-200 rounded-xl p-3 text-sm">
                    <p class="text-slate-500">LPN</p>
                    <p class="font-bold text-simba-700 dark:text-simba-400 font-mono">{{ $this->currentLpn->code }}</p>
                </div>
            @endif
            <p class="text-lg font-bold text-slate-800 dark:text-slate-100">Scan destination bin:</p>
            <input type="text" wire:model.defer="barcode"
                   x-data="{lastScan:'',lastAt:0}"
                   x-on:keydown.enter="const now=Date.now();if($el.value===lastScan&&now-lastAt<600){$el.value='';return;}lastScan=$el.value;lastAt=now;$wire.processScan()"
                   data-scan-input placeholder="BIN-XXXXX"
                   class="input text-lg h-14 font-mono" autocomplete="off" autofocus />
        </div>
    @endif

    {{-- Confirm Step --}}
    @if ($step === 'confirm')
        <div class="space-y-4">
            <p class="text-lg font-bold">Confirm putaway:</p>

            {{-- Suggested / confirmed destination — large prominent display --}}
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
                <div class="flex justify-between px-4 py-3">
                    <span class="text-sm text-slate-400">LPN</span>
                    <span class="text-sm font-semibold font-mono">{{ $this->currentLpn?->code ?? '—' }}</span>
                </div>
                @if ($this->currentTask)
                    <div class="flex justify-between px-4 py-3">
                        <span class="text-sm text-slate-400">Task</span>
                        <span class="text-sm font-semibold">#{{ $this->currentTask->id }}</span>
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
