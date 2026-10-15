<div class="space-y-6">
    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Pick Lists</h2>
            <p class="page-sub">Manage and execute warehouse pick tasks.</p>
        </div>
    </div>

    <div class="card">
        {{-- Status Tabs + Search --}}
        <div class="card-header flex-col sm:flex-row gap-3">
            <div class="tabs overflow-x-auto">
                <button wire:click="$set('statusFilter','all')"
                        @class(['tab', 'active' => $statusFilter === 'all'])>
                    All
                    <span class="badge badge-neutral ml-1">{{ $this->statusCounts['all'] ?? 0 }}</span>
                </button>
                @foreach ($this->statuses as $status)
                    <button wire:click="$set('statusFilter','{{ $status->value }}')"
                            @class(['tab', 'active' => $statusFilter === $status->value])>
                        {{ $status->label() }}
                        @if (($this->statusCounts[$status->value] ?? 0) > 0)
                            <span class="badge {{ match($status->value) {
                                'open'        => 'badge-blue',
                                'in_progress' => 'badge-amber',
                                'completed'   => 'badge-emerald',
                                'cancelled'   => 'badge-red',
                                default       => 'badge-neutral',
                            } }} ml-1">{{ $this->statusCounts[$status->value] }}</span>
                        @endif
                    </button>
                @endforeach
            </div>

            <div class="relative ml-auto">
                <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                <input wire:model.live.debounce.300ms="search"
                       type="text"
                       placeholder="Search pick list or DO number…"
                       class="input !pl-9 sm:w-64" />
            </div>
        </div>

        {{-- Pick Lists Table --}}
        <x-ui.table>
            <thead>
                <tr>
                    <th>Pick List #</th>
                    <th>Delivery Order #</th>
                    <th>Consignee</th>
                    <th>Assigned To</th>
                    <th>Progress</th>
                    <th>Status</th>
                    <th>Started At</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->pickLists as $pl)
                    <tr wire:key="pl-{{ $pl->id }}">
                        {{-- Pick List # --}}
                        <td>
                            <a href="{{ route('outbound.orders.show', $pl->delivery_order_id) }}"
                               wire:navigate
                               class="mono font-semibold text-simba-600 dark:text-simba-400 hover:underline">
                                {{ $pl->number }}
                            </a>
                        </td>

                        {{-- Delivery Order # --}}
                        <td>
                            <span class="mono text-sm text-slate-700 dark:text-slate-300">
                                {{ $pl->deliveryOrder?->number ?? '—' }}
                            </span>
                        </td>

                        {{-- Consignee --}}
                        <td class="text-sm">
                            {{ $pl->deliveryOrder?->consigneePartner?->name ?? '—' }}
                        </td>

                        {{-- Assigned To --}}
                        <td class="text-sm">
                            @if ($pl->assignedTo)
                                {{ $pl->assignedTo->name }}
                            @else
                                <span class="text-slate-400 dark:text-slate-500">Unassigned</span>
                            @endif
                        </td>

                        {{-- Progress --}}
                        <td>
                            @php
                                $total     = $pl->tasks_count ?? 0;
                                $completed = $pl->completed_tasks_count ?? 0;
                                $pct       = $total > 0 ? (int) min(100, round(($completed / $total) * 100)) : 0;
                                $barColor  = $pct >= 100 ? 'bg-emerald-600' : 'bg-simba-500';
                            @endphp
                            <div class="flex items-center gap-2">
                                <div class="progress w-24">
                                    <div class="{{ $barColor }}" style="width:{{ $pct }}%"></div>
                                </div>
                                <span class="text-xs tabular-nums text-slate-500">
                                    {{ $completed }}/{{ $total }}
                                </span>
                            </div>
                        </td>

                        {{-- Status --}}
                        <td>
                            <span class="badge {{ match($pl->status->value) {
                                'open'        => 'badge-blue',
                                'in_progress' => 'badge-amber',
                                'completed'   => 'badge-emerald',
                                'cancelled'   => 'badge-red',
                                default       => 'badge-neutral',
                            } }}">
                                <span class="dot"></span>{{ $pl->status->label() }}
                            </span>
                        </td>

                        {{-- Started At --}}
                        <td class="tabular-nums text-sm text-slate-600 dark:text-slate-400">
                            {{ $pl->started_at?->format('d M Y H:i') ?? '&mdash;' }}
                        </td>

                        {{-- Actions --}}
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button wire:click="openAssignModal({{ $pl->id }})"
                                        class="btn btn-secondary btn-sm">
                                    <x-ui.icon name="user" class="w-3.5 h-3.5" /> Assign
                                </button>

                                @if (in_array($pl->status->value, ['open', 'in_progress'], true))
                                    <a href="{{ route('outbound.orders.show', $pl->delivery_order_id) }}"
                                       wire:navigate
                                       class="btn btn-primary btn-sm">
                                        <x-ui.icon name="clipboard" class="w-3.5 h-3.5" /> Pick Tasks
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="!py-12 text-center text-slate-500">
                            @if ($search || $statusFilter !== 'all')
                                No pick lists match your current filters.
                            @else
                                No pick lists found.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        <div class="px-4 py-3 border-t border-white/40 dark:border-white/[0.06]">
            {{ $this->pickLists->links() }}
        </div>
    </div>

    {{-- Open Tasks Section --}}
    @if (in_array($statusFilter, ['open', 'in_progress'], true))
        <div class="card">
            <div class="card-header">
                <h3 class="text-[15px] font-semibold text-slate-900 dark:text-white">Open Tasks</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Pending pick tasks from visible pick lists.</p>
            </div>

            <x-ui.table>
                <thead>
                    <tr>
                        <th>Pick List #</th>
                        <th>Location</th>
                        <th>Item SKU</th>
                        <th>Item Name</th>
                        <th class="text-right">Qty to Pick</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $openTasks = $this->pickLists->flatMap(
                            fn ($pl) => $pl->tasks
                                ->filter(fn ($t) => $t->status->value === 'open')
                                ->map(fn ($t) => ['pl' => $pl, 'task' => $t])
                        );
                    @endphp

                    @forelse ($openTasks as $row)
                        @php $task = $row['task']; $pl = $row['pl']; @endphp
                        <tr wire:key="task-{{ $task->id }}">
                            {{-- Pick List # --}}
                            <td class="mono text-sm font-semibold text-slate-700 dark:text-slate-300">
                                {{ $pl->number }}
                            </td>

                            {{-- Location --}}
                            <td class="mono text-sm">
                                {{ $task->location?->code ?? '—' }}
                            </td>

                            {{-- Item SKU --}}
                            <td class="mono text-sm">
                                {{ $task->item?->sku ?? $task->allocation?->item?->sku ?? '—' }}
                            </td>

                            {{-- Item Name --}}
                            <td class="text-sm text-slate-700 dark:text-slate-300">
                                {{ $task->item?->name ?? $task->allocation?->item?->name ?? '—' }}
                            </td>

                            {{-- Qty to Pick --}}
                            <td class="text-right tabular-nums text-sm font-semibold">
                                {{ number_format((float) $task->qty_to_pick, 2) }}
                            </td>

                            {{-- Action --}}
                            <td class="text-right">
                                <button wire:click="openPickTask({{ $task->id }})"
                                        class="btn btn-primary btn-sm">
                                    <x-ui.icon name="check" class="w-3.5 h-3.5" /> Pick
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="!py-10 text-center text-slate-500">
                                No open tasks for the current pick lists.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.table>
        </div>
    @endif

    {{-- Assign Modal --}}
    @if ($showAssignModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog"
             aria-modal="true"
             x-data
             x-on:keydown.escape.window="$wire.showAssignModal = false">

            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/50 backdrop-blur-[2px]"
                 wire:click="$set('showAssignModal', false)"></div>

            {{-- Panel --}}
            <div class="relative z-10 w-full max-w-md rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl ring-1 ring-black/[0.06] dark:ring-white/[0.08]">

                {{-- Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-white/[0.08]">
                    <h3 class="text-[15px] font-semibold text-slate-900 dark:text-white">Assign Picker</h3>
                    <button type="button"
                            wire:click="$set('showAssignModal', false)"
                            class="icon-btn -mr-2 -mt-0.5 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200"
                            aria-label="Close">
                        <x-ui.icon name="x" class="h-4 w-4" />
                    </button>
                </div>

                {{-- Body --}}
                <div class="px-6 py-5 space-y-4">
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        Select a picker to assign to this pick list.
                    </p>
                    <div>
                        <label class="label" for="assign-user">
                            Picker <span class="text-red-500">*</span>
                        </label>
                        <select wire:model="assignUserId"
                                id="assign-user"
                                class="input w-full @error('assignUserId') border-red-400 @enderror">
                            <option value="">Select picker…</option>
                            @foreach ($this->pickerUsers as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                        @error('assignUserId')
                            <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex justify-end gap-3 px-6 py-4 bg-slate-50/80 dark:bg-white/[0.02] border-t border-slate-100 dark:border-white/[0.08] rounded-b-2xl">
                    <button type="button"
                            wire:click="$set('showAssignModal', false)"
                            class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="button"
                            wire:click="confirmAssign"
                            wire:loading.attr="disabled"
                            class="btn btn-primary">
                        <span wire:loading.remove wire:target="confirmAssign">
                            <x-ui.icon name="check" class="w-4 h-4" /> Assign
                        </span>
                        <span wire:loading wire:target="confirmAssign">Assigning…</span>
                    </button>
                </div>

            </div>
        </div>
    @endif

    {{-- Pick Task Modal (desktop) --}}
    @if ($showPickModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog"
             aria-modal="true"
             x-data
             x-on:keydown.escape.window="$wire.showPickModal = false">

            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/50 backdrop-blur-[2px]"
                 wire:click="$set('showPickModal', false)"></div>

            {{-- Panel --}}
            <div class="relative z-10 w-full max-w-lg rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl ring-1 ring-black/[0.06] dark:ring-white/[0.08]">

                {{-- Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-white/[0.08]">
                    <h3 class="text-[15px] font-semibold text-slate-900 dark:text-white">Execute Pick Task</h3>
                    <button type="button"
                            wire:click="$set('showPickModal', false)"
                            class="icon-btn -mr-2 -mt-0.5 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200"
                            aria-label="Close">
                        <x-ui.icon name="x" class="h-4 w-4" />
                    </button>
                </div>

                {{-- Body --}}
                <div class="px-6 py-5 space-y-5">
                    @if ($this->activeTask)
                        @php $task = $this->activeTask; @endphp

                        {{-- Task Details --}}
                        <div class="rounded-xl bg-slate-50 dark:bg-white/[0.04] border border-slate-200 dark:border-white/[0.08] divide-y divide-slate-200 dark:divide-white/[0.08]">
                            <div class="flex items-center justify-between px-4 py-3 text-sm">
                                <span class="text-slate-500">Item</span>
                                <div class="text-right">
                                    <span class="mono font-semibold text-slate-800 dark:text-slate-100">
                                        {{ $task->item?->sku ?? $task->allocation?->item?->sku ?? '—' }}
                                    </span>
                                    <span class="block text-xs text-slate-500">
                                        {{ $task->item?->name ?? $task->allocation?->item?->name ?? '' }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between px-4 py-3 text-sm">
                                <span class="text-slate-500">Location</span>
                                <span class="mono font-semibold text-slate-800 dark:text-slate-100">
                                    {{ $task->location?->code ?? $task->allocation?->location?->code ?? '—' }}
                                </span>
                            </div>
                            @if ($task->allocation?->batch)
                                <div class="flex items-center justify-between px-4 py-3 text-sm">
                                    <span class="text-slate-500">Batch</span>
                                    <span class="mono text-slate-700 dark:text-slate-300">
                                        {{ $task->allocation->batch->number }}
                                    </span>
                                </div>
                            @endif
                            <div class="flex items-center justify-between px-4 py-3 text-sm">
                                <span class="text-slate-500">Qty Required</span>
                                <span class="tabular-nums font-semibold text-slate-800 dark:text-slate-100">
                                    {{ number_format((float) $task->qty_to_pick, 2) }}
                                </span>
                            </div>
                        </div>

                        {{-- Picked Qty --}}
                        <div>
                            <label class="label" for="picked-qty">
                                Qty Picked <span class="text-red-500">*</span>
                            </label>
                            <input wire:model="pickedQty"
                                   id="picked-qty"
                                   type="number"
                                   step="0.0001"
                                   min="0.0001"
                                   placeholder="{{ number_format((float) $task->qty_to_pick, 4) }}"
                                   class="input w-full tabular-nums @error('pickedQty') border-red-400 @enderror"
                                   autofocus />
                            @error('pickedQty')
                                <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Serial Number (optional) --}}
                        <div>
                            <label class="label" for="picked-serial">
                                Serial Number
                                <span class="text-slate-400 font-normal ml-1">(optional)</span>
                            </label>
                            <input wire:model="pickedSerial"
                                   id="picked-serial"
                                   type="text"
                                   placeholder="Scan or enter serial…"
                                   class="input w-full mono" />
                        </div>
                    @else
                        <p class="text-sm text-slate-500">Loading task…</p>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="flex justify-end gap-3 px-6 py-4 bg-slate-50/80 dark:bg-white/[0.02] border-t border-slate-100 dark:border-white/[0.08] rounded-b-2xl">
                    <button type="button"
                            wire:click="$set('showPickModal', false)"
                            class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="button"
                            wire:click="confirmPick"
                            wire:loading.attr="disabled"
                            class="btn btn-primary">
                        <span wire:loading.remove wire:target="confirmPick">
                            <x-ui.icon name="check" class="w-4 h-4" /> Confirm Pick
                        </span>
                        <span wire:loading wire:target="confirmPick">Saving…</span>
                    </button>
                </div>

            </div>
        </div>
    @endif
</div>
