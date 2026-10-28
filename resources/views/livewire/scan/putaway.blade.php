tail <div>
    {{-- Step header --}}
    @php
        $steps = ['item'=>1,'bin'=>2,'confirm'=>3];
        $stepLabels = ['item'=>'Scan Item','bin'=>'Scan Bin','confirm'=>'Confirm'];
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

    {{-- Item Step --}}
    @if ($step === 'item')
        <div class="space-y-3">
            <p class="text-lg font-bold text-slate-800 dark:text-slate-100">Scan item barcode or select task:</p>
            <x-scan-input wire:model="barcode" :method="'processScan'" placeholder="Scan item barcode or SKU…" autofocus class="text-lg h-14" />

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
            @if ($this->currentTask)
                <div class="bg-simba-50 dark:bg-simba-900/20 border border-simba-200 rounded-xl p-3 text-sm">
                    <p class="text-slate-500">Item</p>
                    <p class="font-bold text-simba-700 dark:text-simba-400 font-mono">{{ $this->currentTask->item?->sku }}</p>
                    <p class="text-xs text-slate-400">{{ $this->currentTask->item?->name }}</p>
                </div>
            @endif
            <p class="text-lg font-bold text-slate-800 dark:text-slate-100">Scan destination bin:</p>
            <x-scan-input wire:model="barcode" :method="'processScan'" placeholder="BIN-XXXXX" autofocus class="text-lg h-14 font-mono" />
        </div>
    @endif

    {{-- Confirm Step --}}
    @if ($step === 'confirm')
        <div class="space-y-4">
            <p class="text-lg font-bold">Confirm putaway:</p>

            {{-- Destination bin --}}
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
            </div>

            <button wire:click="confirmPutaway" wire:loading.attr="disabled"
                    class="w-full btn btn-primary h-16 text-lg font-bold">
                <span wire:loading.remove>Confirm Putaway</span>
                <span wire:loading>Saving&hellip;</span>
            </button>
        </div>
    @endif
</div>
