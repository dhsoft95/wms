<div class="space-y-6">
    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Put-Away Tasks</h2>
            <p class="page-sub">Assign and execute putaway tasks to move goods from dock to storage.</p>
        </div>
        <div class="flex items-center gap-2">
            <label class="flex items-center gap-2 cursor-pointer text-sm text-slate-600 dark:text-slate-400">
                <input wire:model.live="myTasksOnly" type="checkbox"
                       class="rounded text-simba-600 focus:ring-simba-600" />
                My Tasks Only
            </label>
        </div>
    </div>

    <div class="card">
        {{-- Status Tabs --}}
        <div class="card-header flex-col sm:flex-row gap-3">
            <div class="tabs overflow-x-auto">
                <button wire:click="$set('statusFilter','all')"
                        @class(['tab','active' => $statusFilter === 'all'])>
                    All <span class="badge badge-neutral ml-1">{{ $this->statusCounts['all'] ?? 0 }}</span>
                </button>
                @foreach ($this->statuses as $status)
                    <button wire:click="$set('statusFilter','{{ $status->value }}')"
                            @class(['tab','active' => $statusFilter === $status->value])>
                        {{ $status->label() }}
                        @if (($this->statusCounts[$status->value] ?? 0) > 0)
                            <span class="badge {{ $status->color() }} ml-1">{{ $this->statusCounts[$status->value] }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
            <div class="relative ml-auto">
                <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                <input wire:model.live.debounce.300ms="search" type="text"
                       placeholder="Search item, GRN…"
                       class="input !pl-9 sm:w-56" />
            </div>
        </div>

        <x-ui.table>
            <thead>
                <tr>
                    <th>GRN</th>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>From</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->tasks as $task)
                    <tr wire:key="task-{{ $task->id }}">
                        <td class="mono text-sm">{{ $task->grn?->number }}</td>
                        <td>
                            <div class="mono text-xs text-simba-600 dark:text-simba-400">{{ $task->item?->sku }}</div>
                            <div class="text-sm">{{ $task->item?->name }}</div>
                        </td>
                        <td class="tabular-nums">{{ number_format((float)$task->qty, 2) }}</td>
                        <td class="mono text-xs">{{ $task->fromLocation?->code }}</td>
                        <td class="mono text-xs">
                            @if ($task->actualLocation)
                                <span class="font-semibold text-emerald-600 dark:text-emerald-400">
                                    {{ $task->actualLocation->code }}
                                </span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $statusLabel = match($task->status) {
                                    \App\Enums\PutawayTaskStatus::Open     => 'Putaway In Progress',
                                    \App\Enums\PutawayTaskStatus::Assigned => 'Putaway In Progress',
                                    default                                => $task->status->label(),
                                };
                            @endphp
                            <span class="badge {{ $task->status->color() }}">
                                <span class="dot"></span>{{ $statusLabel }}
                            </span>
                        </td>
                        <td class="text-right">
                            @if (in_array($task->status->value, ['open', 'assigned', 'in_progress']))
                                @can('execute', $task)
                                    <button wire:click="openExecute({{ $task->id }})"
                                            class="btn btn-primary btn-sm">
                                        Execute
                                    </button>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="!py-12 text-center text-slate-500">No putaway tasks found.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        <div class="px-4 py-3 border-t border-white/40 dark:border-white/[0.06]">
            {{ $this->tasks->links() }}
        </div>
    </div>

    {{-- Execute Modal --}}
    @if ($showExecuteModal && $this->executingTask)
    @php
        $task           = $this->executingTask;
        $taskQty        = (float) $task->qty;
        $trackSerial    = (bool) $task->item?->track_serial;
        $serialDriven   = $trackSerial && !empty($taskSerials);

        if ($serialDriven) {
            $alloc   = collect($splitRows)->sum(fn($r) => count((array)($r['serials'] ?? [])));
            $allocOk = $alloc === (int) $taskQty && $taskQty > 0;
        } else {
            $alloc   = array_sum(array_map(fn($r) => (float)($r['qty'] ?? 0), $splitRows));
            $allocOk = abs($alloc - $taskQty) < 0.0001 && $taskQty > 0;
        }
    @endphp
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-xl flex flex-col max-h-[90vh]">

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 pt-5 pb-4 border-b border-slate-200 dark:border-slate-800 shrink-0">
                <div>
                    <h3 class="text-base font-semibold">Execute Putaway</h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        GRN: <span class="font-mono">{{ $task->grn?->number }}</span> · Split across multiple bins if needed
                    </p>
                </div>
                <button wire:click="closeExecute" class="icon-btn">
                    <x-ui.icon name="x" class="w-4 h-4" />
                </button>
            </div>

            {{-- Task Summary --}}
            <div class="px-5 py-3 bg-slate-50 dark:bg-slate-800/40 border-b border-slate-200 dark:border-slate-700 shrink-0">
                <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                    <div>
                        <span class="text-slate-400 text-xs">Item</span>
                        <div class="font-mono font-semibold">{{ $task->item?->sku }}</div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <span class="text-slate-400 text-xs">Description</span>
                        <div class="truncate">{{ $task->item?->name }}</div>
                    </div>
                    <div>
                        <span class="text-slate-400 text-xs">Qty</span>
                        <div class="tabular-nums font-semibold">{{ number_format($taskQty, 2) }}</div>
                    </div>
                    <div>
                        <span class="text-slate-400 text-xs">From</span>
                        <div class="font-mono">{{ $task->fromLocation?->code }}</div>
                    </div>
                </div>
            </div>

            {{-- Draft restored banner --}}
            @if ($hasDraft)
            <div class="px-5 py-2.5 shrink-0 flex items-center justify-between bg-amber-50 dark:bg-amber-950/30 border-b border-amber-200 dark:border-amber-800/50">
                <div class="flex items-center gap-2 text-sm text-amber-800 dark:text-amber-300">
                    <x-ui.icon name="clock" class="w-4 h-4 shrink-0" />
                    <span>Draft restored from a previous session.</span>
                </div>
                <button wire:click="discardDraft"
                        class="text-xs text-amber-700 dark:text-amber-400 hover:text-amber-900 dark:hover:text-amber-200 font-medium underline">
                    Start fresh
                </button>
            </div>
            @endif

            {{-- Allocated counter --}}
            <div class="px-5 py-2.5 shrink-0 flex items-center justify-between text-sm border-b border-slate-200 dark:border-slate-700">
                <span class="text-slate-500">{{ $serialDriven ? 'Serials selected' : 'Allocated' }}</span>
                <span @class([
                    'tabular-nums font-semibold',
                    'text-emerald-600 dark:text-emerald-400' => $allocOk,
                    'text-amber-600 dark:text-amber-400'     => !$allocOk && $alloc > 0,
                    'text-slate-500'                          => $alloc == 0,
                ])>
                    @if ($serialDriven)
                        {{ $alloc }} / {{ (int) $taskQty }}
                    @else
                        {{ number_format($alloc, 2) }} / {{ number_format($taskQty, 2) }}
                    @endif
                </span>
            </div>

            {{-- Split Rows --}}
            <div class="overflow-y-auto flex-1 px-5 py-4 space-y-3">
                @foreach ($splitRows as $i => $row)
                    <div wire:key="split-row-{{ $i }}" class="rounded-xl border border-slate-200 dark:border-slate-700 p-3 space-y-3">

                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Bin {{ $i + 1 }}</span>
                            @if (count($splitRows) > 1)
                                <button wire:click="removeSplitRow({{ $i }})"
                                        class="text-xs text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 flex items-center gap-1">
                                    <x-ui.icon name="x" class="w-3 h-3" /> Remove
                                </button>
                            @endif
                        </div>

                        <div class="grid grid-cols-5 gap-2">
                            <div class="{{ $serialDriven ? 'col-span-5' : 'col-span-3' }}"
                                 wire:key="bin-sel-{{ $i }}"
                                 x-data="{
                                     open: false,
                                     results: [],
                                     async fetch(q) {
                                         this.results = await $wire.getBinSuggestions(q);
                                         this.open = this.results.length > 0;
                                     }
                                 }">
                                <label class="label text-xs">Destination Bin <span class="text-red-500">*</span></label>

                                @if (empty($row['locationId']))
                                    <div class="relative">
                                        <input type="text"
                                               placeholder="Search bin…"
                                               class="input w-full font-mono text-sm"
                                               autocomplete="off"
                                               @focus="fetch('')"
                                               @input.debounce.200ms="fetch($el.value)"
                                               @keydown.escape="open = false" />
                                        <div x-show="open"
                                             x-cloak
                                             @click.outside="open = false"
                                             class="absolute z-30 w-full mt-1 overflow-hidden rounded-xl border border-white/50 dark:border-white/[0.08] shadow-lg glass-solid dark:glass-solid-dark">
                                            <template x-for="loc in results" :key="loc.id">
                                                <button type="button"
                                                        @mousedown.prevent="$wire.selectSplitBin({{ $i }}, loc.id, loc.code); open = false"
                                                        class="w-full flex items-center justify-between px-3 py-2 text-sm hover:bg-brand-50/60 dark:hover:bg-brand-900/20 transition-colors border-t first:border-0 border-white/40 dark:border-white/[0.06]">
                                                    <span x-text="loc.code" class="font-mono font-semibold"></span>
                                                    <span x-show="loc.barcode && loc.barcode !== loc.code"
                                                          x-text="loc.barcode"
                                                          class="text-xs text-slate-400 font-mono truncate ml-2"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2 input bg-slate-50 dark:bg-slate-800/50">
                                        <span class="font-mono text-sm font-semibold flex-1">{{ $row['locationCode'] }}</span>
                                        <button wire:click="clearSplitBin({{ $i }})"
                                                class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                                            <x-ui.icon name="x" class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                @endif
                            </div>

                            @if (! $serialDriven)
                            <div class="col-span-2">
                                <label class="label text-xs">Qty <span class="text-red-500">*</span></label>
                                <input wire:model.live="splitRows.{{ $i }}.qty"
                                       type="number"
                                       min="0.0001"
                                       step="any"
                                       placeholder="0"
                                       class="input w-full tabular-nums text-sm" />
                            </div>
                            @endif
                        </div>

                        {{-- Serials --}}
                        @if ($trackSerial && !empty($taskSerials))
                            @php
                                $rowSelected   = (array)($row['serials'] ?? []);
                                $takenByOthers = collect($splitRows)
                                    ->forget($i)
                                    ->flatMap(fn($r) => (array)($r['serials'] ?? []))
                                    ->toArray();
                            @endphp
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="label text-xs mb-0">Serials</label>
                                    <span class="text-xs tabular-nums {{ count($rowSelected) > 0 ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-slate-400' }}">
                                        {{ count($rowSelected) }} selected
                                    </span>
                                </div>
                                <div class="max-h-36 overflow-y-auto rounded-lg border border-slate-200 dark:border-slate-700 divide-y divide-slate-100 dark:divide-white/[0.05]">
                                    @foreach ($taskSerials as $serial)
                                        @php
                                            $isChecked = in_array($serial, $rowSelected, true);
                                            $isTaken   = in_array($serial, $takenByOthers, true);
                                        @endphp
                                        <label @class([
                                            'flex items-center gap-2.5 px-3 py-1.5 cursor-pointer select-none',
                                            'bg-emerald-50 dark:bg-emerald-900/20'         => $isChecked,
                                            'opacity-40 cursor-not-allowed'                => $isTaken,
                                            'hover:bg-slate-50 dark:hover:bg-white/[0.03]' => !$isTaken && !$isChecked,
                                        ])>
                                            <input type="checkbox"
                                                   wire:click="toggleRowSerial({{ $i }}, '{{ $serial }}')"
                                                   @checked($isChecked)
                                                   @disabled($isTaken)
                                                   class="rounded border-slate-300 dark:border-slate-600 text-simba-600 focus:ring-simba-500 shrink-0" />
                                            <span class="font-mono text-xs {{ $isChecked ? 'text-emerald-700 dark:text-emerald-300 font-semibold' : 'text-slate-700 dark:text-slate-300' }}">
                                                {{ $serial }}
                                            </span>
                                            @if ($isTaken)
                                                <span class="ml-auto text-[10px] text-slate-400">assigned</span>
                                            @endif
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                    </div>
                @endforeach

                <button wire:click="addSplitRow"
                        class="w-full flex items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 dark:border-slate-700 py-2.5 text-sm text-slate-500 hover:border-simba-400 hover:text-simba-600 dark:hover:border-simba-600 dark:hover:text-simba-400 transition-colors">
                    <x-ui.icon name="plus" class="w-4 h-4" />
                    Add another bin
                </button>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-between px-5 py-4 border-t border-slate-200 dark:border-slate-800 shrink-0">
                <button wire:click="saveDraft"
                        class="btn btn-secondary text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-800/60 hover:bg-amber-50 dark:hover:bg-amber-950/30">
                    <x-ui.icon name="clock" class="w-4 h-4" /> Save as Draft
                </button>
                <div class="flex gap-3">
                    <button wire:click="closeExecute" class="btn btn-secondary">Cancel</button>
                    <button wire:click="executeSplitTask"
                            class="btn btn-primary"
                            @disabled(! $allocOk)>
                        <x-ui.icon name="check" class="w-4 h-4" /> Confirm Putaway
                    </button>
                </div>
            </div>

        </div>
    </div>
    @endif
</div>
