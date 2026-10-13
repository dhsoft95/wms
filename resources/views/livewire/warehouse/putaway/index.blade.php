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
            @if (count($selectedTaskIds) > 0)
                <button wire:click="openBulkAssign" class="btn btn-primary btn-sm">
                    <x-ui.icon name="users" class="w-4 h-4" />
                    Assign ({{ count($selectedTaskIds) }})
                </button>
            @endif
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
                    <th class="w-8">
                        <span class="sr-only">Select</span>
                    </th>
                    <th>GRN</th>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>From</th>
                    <th>Suggested</th>
                    <th>Assigned To</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->tasks as $task)
                    <tr wire:key="task-{{ $task->id }}">
                        <td>
                            <input type="checkbox" wire:model.live="selectedTaskIds"
                                   value="{{ $task->id }}"
                                   class="rounded text-simba-600 focus:ring-simba-600" />
                        </td>
                        <td class="mono text-sm">{{ $task->grn?->number }}</td>
                        <td>
                            <div class="mono text-xs text-simba-600 dark:text-simba-400">{{ $task->item?->sku }}</div>
                            <div class="text-sm">{{ $task->item?->name }}</div>
                        </td>
                        <td class="tabular-nums">{{ number_format((float)$task->qty, 2) }}</td>
                        <td class="mono text-xs">{{ $task->fromLocation?->code }}</td>
                        <td class="mono text-xs">{{ $task->suggestedLocation?->code ?? '—' }}</td>
                        <td class="text-sm">{{ $task->assignedTo?->name ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $task->status->color() }}">
                                <span class="dot"></span>{{ $task->status->label() }}
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
                            @elseif ($task->status->value === 'completed')
                                <span class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                                    {{ $task->actualLocation?->code }}
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="!py-12 text-center text-slate-500">No putaway tasks found.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        <div class="px-4 py-3 border-t border-white/40 dark:border-white/[0.06]">
            {{ $this->tasks->links() }}
        </div>
    </div>

    {{-- Bulk Assign Modal --}}
    @if ($showBulkAssignModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-5">
            <h3 class="text-lg font-semibold">Assign {{ count($selectedTaskIds) }} Task(s)</h3>

            <div>
                <label class="label">Assign To <span class="text-red-500">*</span></label>
                <select wire:model="assignToUserId" class="input @error('assignToUserId') border-red-400 @enderror">
                    <option value="">Select user…</option>
                    @foreach ($this->users as $usr)
                        <option value="{{ $usr->id }}">{{ $usr->name }}</option>
                    @endforeach
                </select>
                @error('assignToUserId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end gap-3">
                <button wire:click="closeBulkAssign" class="btn btn-secondary">Cancel</button>
                <button wire:click="bulkAssign" class="btn btn-primary">
                    <x-ui.icon name="check" class="w-4 h-4" /> Assign
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Execute Modal --}}
    @if ($showExecuteModal && $this->executingTask)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-lg p-6 space-y-5">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold">Execute Putaway</h3>
                <button wire:click="closeExecute" class="icon-btn">
                    <x-ui.icon name="x" class="w-5 h-5" />
                </button>
            </div>

            {{-- Task Info --}}
            <div class="rounded-xl bg-slate-50 dark:bg-slate-800/50 p-4 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-slate-500">Item</span>
                    <span class="font-mono font-semibold">{{ $this->executingTask->item?->sku }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-500">Qty</span>
                    <span class="tabular-nums font-semibold">{{ number_format((float)$this->executingTask->qty, 2) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-500">From</span>
                    <span class="mono">{{ $this->executingTask->fromLocation?->code }}</span>
                </div>
                @if ($this->executingTask->suggestedLocation)
                <div class="flex justify-between text-sm">
                    <span class="text-slate-500">Suggested</span>
                    <span class="mono font-semibold text-simba-600 dark:text-simba-400">
                        {{ $this->executingTask->suggestedLocation?->code }}
                    </span>
                </div>
                @endif
            </div>

            {{-- Scan Destination --}}
            <div>
                <label class="label">Scan or Enter Destination Bin</label>
                <div class="flex gap-2">
                    <input wire:model="scanDestination"
                           wire:keydown.enter="resolveDestination"
                           type="text"
                           placeholder="Scan bin barcode or type code…"
                           class="input flex-1 font-mono"
                           autofocus />
                    <button wire:click="resolveDestination" class="btn btn-secondary">Find</button>
                </div>
                @error('destinationId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>

            @if ($destinationId !== '' && $this->suggestedLocation)
                <div class="rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/50 px-4 py-3">
                    <p class="text-sm text-emerald-800 dark:text-emerald-300 font-medium">
                        Destination confirmed: {{ $this->suggestedLocation->code }}
                    </p>
                </div>
            @endif

            <div class="flex justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-800">
                <button wire:click="closeExecute" class="btn btn-secondary">Cancel</button>
                <button wire:click="executeTask" class="btn btn-primary"
                        @disabled($destinationId === '')>
                    <x-ui.icon name="check" class="w-4 h-4" /> Confirm Putaway
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
