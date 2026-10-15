<div class="space-y-4">
    {{-- Header --}}
    <div class="flex items-center gap-2">
        <a href="{{ route('scan.home') }}" wire:navigate class="p-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
            <x-ui.icon name="chevron-down" class="w-5 h-5 rotate-90 text-slate-600 dark:text-slate-300" />
        </a>
        <h1 class="text-lg font-bold text-slate-800 dark:text-slate-100">Pick</h1>
    </div>

    {{-- Step: scan_list --}}
    @if ($step === 'scan_list')
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm border border-slate-200 dark:border-slate-800 space-y-3">
            <p class="text-lg font-bold text-slate-800 dark:text-slate-100">Scan Pick List / Order</p>
            <p class="text-sm text-slate-400">Enter or scan a pick list number or delivery order number.</p>
            <input wire:model="orderRef" wire:keydown.enter="loadOrder"
                   type="text" placeholder="e.g. PL-2026-00001 or DO-2026-00001"
                   class="input text-lg h-14 font-mono uppercase" autocomplete="off" autofocus />
            @if ($scanError)
                <p class="text-sm text-red-500">{{ $scanError }}</p>
            @endif
            <button wire:click="loadOrder" wire:loading.attr="disabled" class="w-full btn btn-primary h-14 text-base font-bold">
                <span wire:loading.remove wire:target="loadOrder">Load Pick List</span>
                <span wire:loading wire:target="loadOrder">Loading&hellip;</span>
            </button>
        </div>
    @endif

    {{-- Step: task_list --}}
    @if ($step === 'task_list' && $pickList)
        {{-- Pick list header --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
            <div class="bg-simba-600 px-5 py-3">
                <p class="text-xs text-simba-200 uppercase tracking-wider">Pick List</p>
                <p class="font-bold text-white font-mono">{{ $pickList->number }}</p>
                @if ($pickList->deliveryOrder)
                    <p class="text-xs text-simba-200 mt-0.5">
                        DO: <span class="font-semibold font-mono">{{ $pickList->deliveryOrder->number }}</span>
                        @if ($pickList->deliveryOrder->consigneePartner)
                            &middot; {{ $pickList->deliveryOrder->consigneePartner->name }}
                        @endif
                    </p>
                @endif
            </div>

            {{-- Task list --}}
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($pickList->tasks as $task)
                    @php
                        $isDone = $task->status !== \App\Enums\Outbound\PickTaskStatus::Open;
                    @endphp
                    <li class="flex items-center gap-3 px-5 py-4 {{ $isDone ? 'opacity-50' : '' }}">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold font-mono text-simba-700 dark:text-simba-400 uppercase tracking-wide">
                                {{ $task->allocation?->location?->code ?? '—' }}
                            </p>
                            <p class="font-semibold text-sm font-mono mt-0.5">{{ $task->allocation?->item?->sku ?? '—' }}</p>
                            <p class="text-xs text-slate-500 truncate">{{ $task->allocation?->item?->name ?? '' }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">
                                Qty: <span class="font-semibold tabular-nums text-slate-700 dark:text-slate-200">{{ number_format((float)$task->required_qty, 2) }}</span>
                                @if ($task->allocation?->batch)
                                    &middot; Batch: <span class="font-semibold font-mono">{{ $task->allocation->batch->batch_number }}</span>
                                @endif
                            </p>
                        </div>
                        @if (! $isDone)
                            <button type="button" wire:click="openTask({{ $task->id }})"
                                    class="flex-shrink-0 px-4 h-12 rounded-xl bg-simba-600 text-white font-bold text-sm active:scale-95 transition-transform whitespace-nowrap">
                                Pick
                            </button>
                        @else
                            <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                                <x-ui.icon name="check" class="w-6 h-6 text-green-600" />
                            </div>
                        @endif
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-slate-400">No tasks on this pick list.</li>
                @endforelse
            </ul>
        </div>

        {{-- Done button if all tasks completed --}}
        @if ($pickList->tasks->every(fn ($t) => $t->status !== \App\Enums\Outbound\PickTaskStatus::Open))
            <button wire:click="$set('step', 'done')" class="w-full btn btn-primary h-14 text-base font-bold">
                All Done &rarr;
            </button>
        @endif
    @endif

    {{-- Step: execute_task --}}
    @if ($step === 'execute_task' && $pickList && $activeTaskId)
        @php
            $activeTask = $pickList->tasks->firstWhere('id', $activeTaskId);
        @endphp
        @if ($activeTask)
            <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm border border-slate-200 dark:border-slate-800 space-y-4">
                <p class="text-lg font-bold text-slate-800 dark:text-slate-100">Confirm Pick</p>

                {{-- Task details --}}
                <div class="bg-slate-50 dark:bg-slate-800 rounded-xl divide-y divide-slate-100 dark:divide-slate-700">
                    <div class="flex justify-between px-4 py-3">
                        <span class="text-sm text-slate-400">Location</span>
                        <span class="text-sm font-bold font-mono">{{ $activeTask->allocation?->location?->code ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between px-4 py-3">
                        <span class="text-sm text-slate-400">Item</span>
                        <span class="text-sm font-bold font-mono">{{ $activeTask->allocation?->item?->sku ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between px-4 py-3">
                        <span class="text-sm text-slate-400">Item Name</span>
                        <span class="text-sm font-semibold text-right max-w-[60%]">{{ $activeTask->allocation?->item?->name ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between px-4 py-3">
                        <span class="text-sm text-slate-400">Required Qty</span>
                        <span class="text-sm font-bold tabular-nums text-simba-700 dark:text-simba-400">{{ number_format((float)$activeTask->required_qty, 2) }}</span>
                    </div>
                    @if ($activeTask->allocation?->batch)
                        <div class="flex justify-between px-4 py-3">
                            <span class="text-sm text-slate-400">Batch</span>
                            <span class="text-sm font-bold font-mono">{{ $activeTask->allocation->batch->batch_number }}</span>
                        </div>
                    @endif
                </div>

                {{-- Qty input --}}
                <div class="space-y-1">
                    <label class="label">Picked Quantity <span class="text-red-500">*</span></label>
                    <input wire:model="pickedQty" type="number" min="0.001" step="any"
                           placeholder="{{ number_format((float)$activeTask->required_qty, 2) }}"
                           class="input text-2xl h-16 text-center font-bold tabular-nums" autocomplete="off" autofocus />
                </div>

                {{-- Serial input --}}
                <div class="space-y-1">
                    <label class="label">Serial / Lot Number <span class="text-slate-400 font-normal">(optional)</span></label>
                    <input wire:model="pickedSerial" type="text"
                           placeholder="Scan serial barcode…"
                           class="input font-mono" autocomplete="off" />
                </div>

                @if ($scanError)
                    <p class="text-sm text-red-500">{{ $scanError }}</p>
                @endif

                <button wire:click="confirmPick" wire:loading.attr="disabled"
                        class="w-full btn btn-primary h-14 text-base font-bold">
                    <span wire:loading.remove wire:target="confirmPick">Confirm Pick</span>
                    <span wire:loading wire:target="confirmPick">Saving&hellip;</span>
                </button>

                <button wire:click="$set('step', 'task_list')"
                        class="w-full text-sm text-slate-400 hover:text-slate-600 transition-colors py-1">
                    &larr; Back to Tasks
                </button>
            </div>
        @endif
    @endif

    {{-- Step: done --}}
    @if ($step === 'done')
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm border border-emerald-300 dark:border-emerald-700 space-y-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                    <x-ui.icon name="check" class="w-7 h-7 text-emerald-600" />
                </div>
                <div>
                    <p class="font-bold text-lg text-slate-800 dark:text-slate-100">Pick Complete</p>
                    <p class="text-sm text-slate-400">All tasks have been picked</p>
                </div>
            </div>

            @if ($pickList)
                <div class="bg-slate-50 dark:bg-slate-800 rounded-xl divide-y divide-slate-100 dark:divide-slate-700">
                    <div class="flex justify-between px-4 py-3">
                        <span class="text-sm text-slate-400">Pick List</span>
                        <span class="text-sm font-bold font-mono">{{ $pickList->number }}</span>
                    </div>
                    @if ($pickList->deliveryOrder)
                        <div class="flex justify-between px-4 py-3">
                            <span class="text-sm text-slate-400">Delivery Order</span>
                            <span class="text-sm font-bold font-mono">{{ $pickList->deliveryOrder->number }}</span>
                        </div>
                    @endif
                </div>
            @endif

            <button wire:click="startOver" class="w-full btn btn-primary h-14 text-base font-bold">
                Scan Next
            </button>
        </div>
    @endif
</div>
