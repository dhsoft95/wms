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

    {{-- Scan bar --}}
    <div class="card">
        <div class="card-body py-3">
            <x-scan-input
                wire:model="scanInput"
                :method="'processScan'"
                placeholder="Scan LPN or item barcode to open its putaway task…"
            />
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

    @include('livewire.partials.putaway-execute-modal')

</div>
