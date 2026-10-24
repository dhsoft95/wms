<div class="space-y-6">
    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <a href="{{ route('outbound.orders.index') }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-simba-600 mb-2">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Back to Orders
            </a>
            <h2 class="page-title font-mono">{{ $this->do->number }}</h2>
            <div class="flex items-center gap-3 mt-1">
                <span class="badge {{ $this->do->status->badge() }}">
                    <span class="dot"></span>{{ $this->do->status->label() }}
                </span>
                <span class="badge {{ $this->do->priority->badge() }}">{{ $this->do->priority->label() }}</span>
                @if ($this->do->isOverdue())
                    <span class="badge badge-red"><span class="dot"></span>Overdue</span>
                @endif
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center gap-2 flex-wrap">
            @php $S = \App\Enums\Outbound\DeliveryOrderStatus::class; @endphp

            {{-- Draft: submit + edit --}}
            @if ($this->do->status === $S::Draft)
                @can('submit', $this->do)
                    <button wire:click="submit" wire:loading.attr="disabled" class="btn btn-primary">
                        <x-ui.icon name="paper-airplane" class="w-4 h-4" /> Submit for Approval
                    </button>
                @endcan
                @can('update', $this->do)
                    <a href="{{ route('outbound.orders.edit', $this->do) }}" wire:navigate class="btn btn-secondary">
                        <x-ui.icon name="pencil" class="w-4 h-4" /> Edit
                    </a>
                @endcan
            @endif

            {{-- AwaitingRequesterCorrection: requester edits and resubmits --}}
            @if ($this->do->status === $S::AwaitingRequesterCorrection)
                @can('submit', $this->do)
                    <button wire:click="submit" wire:loading.attr="disabled" class="btn btn-primary">
                        <x-ui.icon name="paper-airplane" class="w-4 h-4" /> Resubmit for Approval
                    </button>
                @endcan
                @can('update', $this->do)
                    <a href="{{ route('outbound.orders.edit', $this->do) }}" wire:navigate class="btn btn-secondary">
                        <x-ui.icon name="pencil" class="w-4 h-4" /> Edit
                    </a>
                @endcan
            @endif

            {{-- ReturnedForCorrection: HOD forwards to requester --}}
            @if ($this->do->status === $S::ReturnedForCorrection)
                @can('forwardReturn', $this->do)
                    <button wire:click="forwardToRequester" wire:loading.attr="disabled" class="btn btn-secondary">
                        <x-ui.icon name="arrow-right" class="w-4 h-4" /> Send Back to Requester
                    </button>
                @endcan
            @endif

            {{-- Submitted: HOD approves / returns / rejects --}}
            @if ($this->do->status === $S::Submitted)
                @can('approveDepartment', $this->do)
                    <button wire:click="approve" wire:loading.attr="disabled" class="btn btn-primary">
                        <x-ui.icon name="check" class="w-4 h-4" /> Approve
                    </button>
                    <button wire:click="$set('showReturnModal', true)" class="btn btn-secondary">
                        <x-ui.icon name="arrow-uturn-left" class="w-4 h-4" /> Return
                    </button>
                    <button wire:click="$set('showRejectModal', true)" class="btn btn-secondary text-red-600 dark:text-red-400">
                        <x-ui.icon name="x-circle" class="w-4 h-4" /> Reject
                    </button>
                @endcan
            @endif

            {{-- Approved: accept for warehouse processing --}}
            @if ($this->do->status === $S::Approved)
                @can('allocate', $this->do)
                    <button wire:click="accept" wire:loading.attr="disabled" class="btn btn-primary">
                        <x-ui.icon name="check-circle" class="w-4 h-4" /> Accept Order
                    </button>
                @endcan
            @endif

            {{-- Acceptance: allocate stock --}}
            @if ($this->do->status === $S::Acceptance)
                @can('allocate', $this->do)
                    <button wire:click="allocate" wire:loading.attr="disabled" class="btn btn-primary">
                        <x-ui.icon name="check" class="w-4 h-4" /> Allocate Stock
                    </button>
                @endcan
            @endif

            {{-- Allocated / PartiallyAllocated / Picking: generate or start picking --}}
            @if (in_array($this->do->status, [$S::Picking, $S::Allocated, $S::PartiallyAllocated]))
                @can('allocate', $this->do)
                    @if ($this->activePickList === null)
                        <button wire:click="$set('showAssignModal', true)" class="btn btn-primary">
                            <x-ui.icon name="clipboard-list" class="w-4 h-4" /> Generate Pick List
                        </button>
                    @else
                        <button wire:click="$set('activeTab', 'pick_lists')" class="btn btn-primary">
                            <x-ui.icon name="play" class="w-4 h-4" /> Start Pick
                            <span class="badge badge-white ml-1 py-0 !text-simba-700">{{ $this->activePickList->status->label() }}</span>
                        </button>
                    @endif
                @endcan
            @endif

            {{-- Picked: dispatch + optionally resume picking --}}
            @if ($this->do->status === $S::Picked)
                @can('allocate', $this->do)
                    <button wire:click="openDispatchModal" class="btn btn-primary">
                        <x-ui.icon name="truck" class="w-4 h-4" /> Dispatch
                    </button>
                @endcan
                @php
                    $hasShortLines = $this->do->lines->some(
                        fn ($l) => bccomp((string) $l->picked_qty, (string) $l->requested_qty_base, 4) < 0
                    );
                @endphp
                @if ($hasShortLines)
                    @can('allocate', $this->do)
                        <button
                            wire:click="resumePicking"
                            wire:loading.attr="disabled"
                            wire:confirm="Resume picking for the remaining quantities? This will re-allocate available stock and allow a new pick list to be generated."
                            class="btn btn-secondary">
                            <x-ui.icon name="arrow-path" class="w-4 h-4" /> Resume Picking
                        </button>
                    @endcan
                @endif
            @endif

            {{-- Cancel button for cancellable states --}}
            @if ($this->do->status->isCancellable())
                @can('cancel', $this->do)
                    <button wire:click="cancel"
                            wire:confirm="Cancel this order? All reservations will be released."
                            class="btn btn-secondary text-red-600 dark:text-red-400">
                        <x-ui.icon name="x-circle" class="w-4 h-4" /> Cancel
                    </button>
                @endcan
            @endif

            {{-- Admin delete --}}
            @can('delete', $this->do)
                <button wire:click="$set('showDeleteModal', true)"
                        class="btn btn-secondary text-red-600 dark:text-red-400">
                    <x-ui.icon name="trash" class="w-4 h-4" /> Delete
                </button>
            @endcan
        </div>
    </div>

    {{-- Status Stepper --}}
    <div class="card">
        <div class="card-body py-4">
            <div class="flex items-center justify-between overflow-x-auto gap-1">
                @foreach ($this->stepperStatuses as $step)
                    @php
                        $currentIdx = $this->stepperCurrentIndex();
                        $stepIdx    = array_search($step, $this->stepperStatuses);
                        $isCancelled = $this->do->status === \App\Enums\Outbound\DeliveryOrderStatus::Cancelled;
                        $done       = ! $isCancelled && $stepIdx < $currentIdx;
                        $current    = ! $isCancelled && $stepIdx === $currentIdx;
                    @endphp
                    <div class="flex flex-col items-center gap-1 shrink-0">
                        <div @class([
                            'w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border-2 transition',
                            'border-emerald-500 bg-emerald-500 text-white'                       => $done,
                            'border-simba-500 bg-simba-500 text-white'                           => $current,
                            'border-red-400 bg-red-50 dark:bg-red-900/20 text-red-400'           => $isCancelled && $stepIdx === 0,
                            'border-slate-300 dark:border-slate-600 text-slate-400'              => ! $done && ! $current && ! ($isCancelled && $stepIdx === 0),
                        ])>
                            @if ($isCancelled && $stepIdx === 0)
                                <x-ui.icon name="x" class="w-4 h-4" />
                            @elseif ($done)
                                <x-ui.icon name="check" class="w-4 h-4" />
                            @else
                                {{ $stepIdx + 1 }}
                            @endif
                        </div>
                        <span @class([
                            'text-[10px] text-center leading-tight',
                            'text-emerald-600 dark:text-emerald-400 font-semibold' => $done,
                            'text-simba-600 dark:text-simba-400 font-semibold'     => $current,
                            'text-red-400 font-semibold'                            => $isCancelled && $stepIdx === 0,
                            'text-slate-400'                                        => ! $done && ! $current && ! ($isCancelled && $stepIdx === 0),
                        ])>{{ $step->label() }}</span>
                    </div>
                    @if (! $loop->last)
                        <div @class([
                            'flex-1 h-0.5 min-w-[12px] transition',
                            'bg-emerald-500'                        => $stepIdx < $currentIdx && ! $isCancelled,
                            'bg-slate-200 dark:bg-slate-700'        => $stepIdx >= $currentIdx || $isCancelled,
                        ])></div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    {{-- Header Info Card --}}
    <div class="card">
        <div class="card-body grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-4 text-sm">
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Virtual Warehouse</p>
                <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">
                    {{ $this->do->virtualWarehouse?->name ?? '—' }}
                </p>
            </div>
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Warehouse</p>
                <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">
                    {{ $this->do->warehouse?->name ?? '—' }}
                </p>
            </div>
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Department</p>
                <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">
                    {{ $this->do->department?->name ?? '—' }}
                </p>
            </div>
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Consignee</p>
                <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">
                    {{ $this->do->consigneePartner?->name ?? '—' }}
                </p>
            </div>
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Required Date</p>
                <p class="mt-0.5 font-medium {{ $this->do->isOverdue() ? 'text-red-600 dark:text-red-400' : 'text-slate-800 dark:text-slate-200' }}">
                    {{ $this->do->required_date?->format('d M Y') ?? '—' }}
                </p>
            </div>
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Requested By</p>
                <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">
                    {{ $this->do->requestedBy?->name ?? '—' }}
                </p>
            </div>
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Created At</p>
                <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">
                    {{ $this->do->created_at->format('d M Y H:i') }}
                </p>
            </div>
            @if ($this->do->remarks)
                <div class="col-span-2 md:col-span-4">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Remarks</p>
                    <p class="mt-0.5 text-slate-700 dark:text-slate-300">{{ $this->do->remarks }}</p>
                </div>
            @endif
            @if ($this->do->backorderOf)
                <div class="col-span-2 md:col-span-4">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Backorder Of</p>
                    <a href="{{ route('outbound.orders.show', $this->do->backorderOf) }}" wire:navigate
                       class="mt-0.5 font-mono text-sm text-simba-600 hover:underline">
                        {{ $this->do->backorderOf->number }}
                    </a>
                </div>
            @endif
        </div>
    </div>

    {{-- Tabs --}}
    <div class="card">
        <div class="card-header">
            <div class="tabs overflow-x-auto">
                @foreach ([
                    'lines'       => 'Lines (' . $this->do->lines->count() . ')',
                    'allocations' => 'Allocations',
                    'pick_lists'  => 'Pick Lists (' . $this->do->pickLists->count() . ')',
                    'approvals'   => 'Approvals (' . $this->do->approvals->count() . ')',
                    'dispatch'    => 'Dispatch (' . $this->do->dispatches->count() . ')',
                    'pod'         => 'POD',
                ] as $tab => $label)
                    <button wire:click="$set('activeTab', '{{ $tab }}')"
                            @class(['tab', 'active' => $activeTab === $tab])>{{ $label }}</button>
                @endforeach
            </div>
        </div>

        {{-- Lines Tab --}}
        @if ($activeTab === 'lines')
        <x-ui.table>
            <thead>
                <tr>
                    <th class="w-8">#</th>
                    <th>Item</th>
                    <th>UOM</th>
                    <th class="text-right">Requested</th>
                    <th class="text-right">Allocated</th>
                    <th class="text-right">Picked</th>
                    <th class="text-right">Dispatched</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->do->lines as $line)
                    <tr>
                        <td class="tabular-nums text-slate-400 text-xs">{{ $line->line_no }}</td>
                        <td>
                            <div class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $line->item?->sku }}</div>
                            <div class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $line->item?->name }}</div>
                        </td>
                        <td class="mono text-xs text-slate-600 dark:text-slate-400">{{ $line->uom?->code }}</td>
                        <td class="tabular-nums text-right">{{ number_format((float) $line->requested_qty, 2) }}</td>
                        <td class="tabular-nums text-right {{ (float) $line->allocated_qty > 0 ? 'text-simba-700 dark:text-simba-400' : '' }}">
                            {{ number_format((float) $line->allocated_qty, 2) }}
                        </td>
                        <td class="tabular-nums text-right {{ (float) $line->picked_qty > 0 ? 'text-amber-700 dark:text-amber-400' : '' }}">
                            {{ number_format((float) $line->picked_qty, 2) }}
                        </td>
                        <td class="tabular-nums text-right {{ (float) $line->dispatched_qty > 0 ? 'text-emerald-700 dark:text-emerald-400' : '' }}">
                            {{ number_format((float) $line->dispatched_qty, 2) }}
                        </td>
                        <td>
                            <span class="badge badge-slate">{{ $line->status->label() }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="!py-8 text-center text-slate-500">No lines on this order.</td></tr>
                @endforelse
            </tbody>
        </x-ui.table>
        @endif

        {{-- Allocations Tab --}}
        @if ($activeTab === 'allocations')
        <x-ui.table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Batch</th>
                    <th>Location</th>
                    <th class="text-right">Qty</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $allAllocations = $this->do->lines->flatMap(fn ($l) => $l->allocations);
                @endphp
                @forelse ($allAllocations as $alloc)
                    <tr>
                        <td>
                            <div class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $alloc->item?->sku }}</div>
                            <div class="text-sm text-slate-700 dark:text-slate-300">{{ $alloc->item?->name }}</div>
                        </td>
                        <td class="mono text-xs text-slate-600 dark:text-slate-400">
                            {{ $alloc->batch?->batch_no ?? '—' }}
                        </td>
                        <td class="mono text-xs text-slate-600 dark:text-slate-400">
                            {{ $alloc->location?->code ?? '—' }}
                        </td>
                        <td class="tabular-nums text-right">{{ number_format((float) $alloc->qty, 2) }}</td>
                        <td>
                            <span class="badge badge-slate">{{ $alloc->status->value }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="!py-8 text-center text-slate-500">No allocations yet.</td></tr>
                @endforelse
            </tbody>
        </x-ui.table>
        @endif

        {{-- Pick Lists Tab --}}
        @if ($activeTab === 'pick_lists')
            @forelse ($this->do->pickLists as $pickList)
                @php
                    $totalTasks  = $pickList->tasks->count();
                    $doneTasks   = $pickList->tasks->filter(fn ($t) => in_array($t->status->value, ['picked', 'short']))->count();
                    $canCancel   = in_array($pickList->status->value, ['open', 'in_progress']) && $doneTasks === 0;
                    $isActive    = in_array($pickList->status->value, ['open', 'in_progress']);
                @endphp

                {{-- Pick List Header --}}
                <div @class([
                    'px-5 py-3 flex flex-wrap items-center gap-3 border-b border-slate-100 dark:border-slate-800',
                    'bg-slate-50 dark:bg-slate-800/30' => ! $isActive,
                ])>
                    <span class="font-mono text-sm font-bold text-slate-800 dark:text-slate-200">{{ $pickList->number }}</span>
                    <span @class([
                        'badge',
                        'badge-slate'   => $pickList->status === \App\Enums\Outbound\PickListStatus::Open,
                        'badge-violet'  => $pickList->status === \App\Enums\Outbound\PickListStatus::InProgress,
                        'badge-green'   => $pickList->status === \App\Enums\Outbound\PickListStatus::Completed,
                        'badge-red'     => $pickList->status === \App\Enums\Outbound\PickListStatus::Cancelled,
                    ])><span class="dot"></span>{{ $pickList->status->label() }}</span>

                    @if ($pickList->assignedTo)
                        <span class="text-xs text-slate-500">
                            <x-ui.icon name="user" class="w-3 h-3 inline" /> {{ $pickList->assignedTo->name }}
                        </span>
                    @endif

                    @if ($totalTasks > 0)
                        <div class="flex items-center gap-2">
                            <div class="w-24 bg-slate-200 dark:bg-slate-700 rounded-full h-1.5">
                                <div class="bg-simba-500 h-1.5 rounded-full transition-all"
                                     style="width: {{ round($doneTasks / $totalTasks * 100) }}%"></div>
                            </div>
                            <span class="text-xs text-slate-500 tabular-nums">{{ $doneTasks }}/{{ $totalTasks }}</span>
                        </div>
                    @endif

                    @if ($pickList->started_at)
                        <span class="text-xs text-slate-400">Started {{ $pickList->started_at->format('d M H:i') }}</span>
                    @endif
                    @if ($pickList->completed_at)
                        <span class="text-xs text-emerald-600 dark:text-emerald-400">Done {{ $pickList->completed_at->format('d M H:i') }}</span>
                    @endif

                    <div class="ml-auto flex items-center gap-2">
                        {{-- Print PDF --}}
                        <a href="{{ route('outbound.pick-lists.pdf', $pickList) }}"
                           target="_blank"
                           class="btn btn-secondary btn-sm">
                            <x-ui.icon name="printer" class="w-3.5 h-3.5" /> Print PDF
                        </a>

                        {{-- Cancel --}}
                        @if ($canCancel)
                            @can('allocate', $this->do)
                                <button
                                    wire:click="cancelPickList({{ $pickList->id }})"
                                    wire:confirm="Cancel this pick list? Tasks will be cancelled and you can regenerate."
                                    class="btn btn-secondary btn-sm !text-red-600 dark:!text-red-400">
                                    <x-ui.icon name="x" class="w-3.5 h-3.5" /> Cancel
                                </button>
                            @endcan
                        @endif
                    </div>
                </div>

                {{-- Task rows for active pick lists --}}
                @if ($isActive && $pickList->tasks->isNotEmpty())
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">

                        {{-- Column headers --}}
                        <div class="hidden md:grid md:grid-cols-12 gap-2 px-5 py-2 text-[10px] font-semibold uppercase tracking-wide text-slate-400 bg-slate-50/80 dark:bg-slate-800/20">
                            <span class="col-span-1 text-center">#</span>
                            <span class="col-span-2">Location</span>
                            <span class="col-span-4">Item</span>
                            <span class="col-span-2">Batch</span>
                            <span class="col-span-1 text-right">Qty</span>
                            <span class="col-span-2 text-center">Status / Action</span>
                        </div>

                        @foreach ($pickList->tasks->sortBy('sequence') as $task)
                            @php
                                $isPicked    = $task->status->value === 'picked';
                                $isShort     = $task->status->value === 'short';
                                $isTaskOpen  = $task->status->value === 'open';
                            @endphp
                            <div @class([
                                'grid grid-cols-6 md:grid-cols-12 gap-2 items-center px-5 py-3 text-sm',
                                'bg-emerald-50/40 dark:bg-emerald-900/10' => $isPicked,
                                'bg-amber-50/40 dark:bg-amber-900/10'     => $isShort,
                            ])>
                                {{-- Seq --}}
                                <div class="col-span-1 text-center">
                                    @if ($isPicked)
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-500 text-white">
                                            <x-ui.icon name="check" class="w-3.5 h-3.5" />
                                        </span>
                                    @elseif ($isShort)
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-400 text-white text-xs font-bold">
                                            !
                                        </span>
                                    @else
                                        <span class="text-xs font-semibold text-slate-400">{{ $task->sequence }}</span>
                                    @endif
                                </div>

                                {{-- Location --}}
                                <div class="col-span-2">
                                    <span class="font-mono text-sm font-bold text-slate-800 dark:text-slate-200">
                                        {{ $task->location?->code ?? '—' }}
                                    </span>
                                </div>

                                {{-- Item --}}
                                <div class="col-span-6 md:col-span-4">
                                    <div class="font-mono text-xs text-slate-400">{{ $task->item?->sku ?? '' }}</div>
                                    <div class="text-slate-700 dark:text-slate-300 text-sm leading-tight">{{ $task->item?->name ?? '—' }}</div>
                                </div>

                                {{-- Batch --}}
                                <div class="col-span-2 text-xs text-slate-500 italic">
                                    {{ $task->batch?->batch_no ?? '—' }}
                                </div>

                                {{-- Qty --}}
                                <div class="col-span-1 text-right tabular-nums">
                                    @if ($isPicked)
                                        <span class="text-emerald-600 font-semibold">{{ number_format((float) $task->qty_picked, 2) }}</span>
                                        <span class="text-xs text-slate-400 block">of {{ number_format((float) $task->qty_to_pick, 2) }}</span>
                                    @else
                                        <span class="font-semibold">{{ number_format((float) $task->qty_to_pick, 2) }}</span>
                                        <span class="text-xs text-slate-400 block">{{ $task->item?->baseUom?->code }}</span>
                                    @endif
                                </div>

                                {{-- Action --}}
                                <div class="col-span-2 flex items-center justify-center gap-2">
                                    @if ($isPicked)
                                        <span class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">Picked</span>
                                        @can('allocate', $this->do)
                                            <button
                                                wire:click="unconfirmTask({{ $task->id }})"
                                                wire:confirm="Reverse this pick? Stock will be returned to the bin."
                                                class="text-xs text-slate-400 hover:text-red-500 underline">
                                                Undo
                                            </button>
                                        @endcan
                                    @elseif ($isShort)
                                        <span class="badge badge-amber">Short</span>
                                    @elseif ($isTaskOpen)
                                        @can('allocate', $this->do)
                                            <button
                                                wire:click="openPickTask({{ $task->id }})"
                                                class="btn btn-primary btn-sm">
                                                <x-ui.icon name="check" class="w-3.5 h-3.5" /> Pick
                                            </button>
                                        @endcan
                                    @endif
                                </div>

                            </div>
                        @endforeach

                    </div>
                @elseif (! $isActive && $pickList->tasks->isNotEmpty())
                    {{-- Completed/cancelled: condensed task summary --}}
                    <div class="px-5 py-3">
                        <x-ui.table>
                            <thead>
                                <tr>
                                    <th class="w-8">#</th>
                                    <th>Location</th>
                                    <th>Item</th>
                                    <th class="text-right">Qty to Pick</th>
                                    <th class="text-right">Qty Picked</th>
                                    <th>Picked By</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pickList->tasks->sortBy('sequence') as $task)
                                    <tr>
                                        <td class="tabular-nums text-slate-400 text-xs">{{ $task->sequence }}</td>
                                        <td class="font-mono text-sm">{{ $task->location?->code ?? '—' }}</td>
                                        <td>
                                            <div class="font-mono text-xs text-slate-400">{{ $task->item?->sku }}</div>
                                            <div class="text-sm">{{ $task->item?->name }}</div>
                                        </td>
                                        <td class="tabular-nums text-right">{{ number_format((float) $task->qty_to_pick, 2) }}</td>
                                        <td class="tabular-nums text-right {{ (float) $task->qty_picked > 0 ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : '' }}">
                                            {{ number_format((float) $task->qty_picked, 2) }}
                                        </td>
                                        <td class="text-xs text-slate-500">{{ $task->pickedBy?->name ?? '—' }}</td>
                                        <td>
                                            <span @class([
                                                'badge',
                                                'badge-green'  => $task->status->value === 'picked',
                                                'badge-amber'  => $task->status->value === 'short',
                                                'badge-slate'  => $task->status->value === 'open',
                                                'badge-red'    => $task->status->value === 'cancelled',
                                            ])>{{ ucfirst($task->status->value) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-ui.table>
                    </div>
                @endif

            @empty
                <div class="py-12 text-center text-slate-500">
                    <x-ui.icon name="clipboard-list" class="w-8 h-8 mx-auto mb-2 text-slate-300 dark:text-slate-600" />
                    <p class="text-sm font-medium">No pick lists generated yet.</p>
                    <p class="text-xs text-slate-400 mt-1">Complete allocation first, then generate a pick list.</p>
                </div>
            @endforelse
        @endif

        {{-- Approvals Tab --}}
        @if ($activeTab === 'approvals')
        <div class="card-body space-y-4">
            @forelse ($this->do->approvals as $approval)
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4 space-y-2">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200 capitalize">
                                {{ $approval->approver_role }} Approval
                                @if ($approval->level)
                                    <span class="text-xs font-normal text-slate-500 ml-1">(Level {{ $approval->level }})</span>
                                @endif
                            </p>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $approval->created_at->format('d M Y H:i') }}</p>
                        </div>
                        @if ($approval->decision === 'approved')
                            <span class="badge badge-green shrink-0"><span class="dot"></span>Approved</span>
                        @elseif ($approval->decision === 'rejected')
                            <span class="badge badge-red shrink-0"><span class="dot"></span>Rejected</span>
                        @else
                            <span class="badge badge-amber shrink-0"><span class="dot"></span>Pending</span>
                        @endif
                    </div>
                    @if ($approval->decidedBy || $approval->notes)
                        <div class="text-sm text-slate-600 dark:text-slate-400">
                            @if ($approval->decidedBy)
                                <span class="font-medium">{{ $approval->decidedBy->name }}</span>
                                @if ($approval->decided_at)
                                    <span class="text-xs text-slate-400 ml-1">
                                        · {{ $approval->decided_at->format('d M Y H:i') }}
                                    </span>
                                @endif
                            @endif
                            @if ($approval->notes)
                                <p class="mt-1 text-slate-500 dark:text-slate-400 italic">{{ $approval->notes }}</p>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-center text-slate-500 py-8">No approvals recorded.</p>
            @endforelse
        </div>
        @endif

        {{-- Dispatch Tab --}}
        @if ($activeTab === 'dispatch')
        <div class="card-body space-y-4">
            @forelse ($this->do->dispatches as $dispatch)
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <p class="font-mono text-sm font-semibold text-slate-700 dark:text-slate-300">
                            {{ $dispatch->number }}
                        </p>
                        @if ($dispatch->gatePass)
                            <span class="badge badge-emerald shrink-0">
                                <span class="dot"></span>Gate Pass: {{ $dispatch->gatePass->number }}
                            </span>
                        @endif
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-3 text-sm">
                        <div>
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Vehicle</p>
                            <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">{{ $dispatch->vehicle_no ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Driver</p>
                            <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">{{ $dispatch->driver_name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Driver Phone</p>
                            <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">{{ $dispatch->driver_phone ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Seal No.</p>
                            <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">{{ $dispatch->seal_no ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Dispatched By</p>
                            <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">{{ $dispatch->dispatchedBy?->name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Dispatched At</p>
                            <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">
                                {{ $dispatch->dispatched_at?->format('d M Y H:i') ?? '—' }}
                            </p>
                        </div>
                        @if ($dispatch->gatePass)
                            <div>
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Gate Pass Status</p>
                                <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">
                                    {{ $dispatch->gatePass->status->label() }}
                                </p>
                            </div>
                            @if ($dispatch->gatePass->exited_at)
                                <div>
                                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Exited At</p>
                                    <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">
                                        {{ $dispatch->gatePass->exited_at->format('d M Y H:i') }}
                                    </p>
                                </div>
                            @endif
                        @endif
                        @if ($dispatch->notes)
                            <div class="col-span-2 md:col-span-4">
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Notes</p>
                                <p class="mt-0.5 text-slate-700 dark:text-slate-300">{{ $dispatch->notes }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-center text-slate-500 py-8">No dispatches recorded yet.</p>
            @endforelse
        </div>
        @endif

        {{-- POD Tab --}}
        @if ($activeTab === 'pod')
        <div class="card-body">
            @if ($this->do->proofOfDelivery)
                @php $pod = $this->do->proofOfDelivery; @endphp
                <div class="space-y-4">
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-4 text-sm">
                        <div>
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Received By</p>
                            <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">{{ $pod->received_by_name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Received At</p>
                            <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">
                                {{ $pod->received_at?->format('d M Y H:i') ?? '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Uploaded By</p>
                            <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">{{ $pod->uploadedBy?->name ?? '—' }}</p>
                        </div>
                        @if ($pod->notes)
                            <div class="col-span-2 md:col-span-3">
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Notes</p>
                                <p class="mt-0.5 text-slate-700 dark:text-slate-300">{{ $pod->notes }}</p>
                            </div>
                        @endif
                    </div>
                    @if ($pod->signature_path)
                        <div>
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-2">Signature</p>
                            <img src="{{ asset('storage/' . $pod->signature_path) }}" alt="Signature"
                                 class="border border-slate-200 dark:border-slate-700 rounded-lg max-h-32 bg-white p-2">
                        </div>
                    @endif
                </div>
            @elseif ($this->do->status === \App\Enums\Outbound\DeliveryOrderStatus::Dispatched)
                <div class="flex flex-col items-center justify-center py-12 gap-3 text-slate-500">
                    <x-ui.icon name="clock" class="w-8 h-8 text-slate-300 dark:text-slate-600" />
                    <p class="text-sm font-medium">Awaiting Proof of Delivery</p>
                    <p class="text-xs text-slate-400">POD will appear here once the delivery is confirmed.</p>
                </div>
            @else
                <p class="text-center text-slate-500 py-8">No proof of delivery recorded.</p>
            @endif
        </div>
        @endif
    </div>

    {{-- Return for Correction Modal --}}
    @if ($showReturnModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm p-4"
             x-data wire:click.self="$set('showReturnModal', false)">
            <div class="card w-full max-w-md p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-slate-900 dark:text-white">Return for Correction</h3>
                    <button wire:click="$set('showReturnModal', false)" class="icon-btn">
                        <x-ui.icon name="x" class="w-5 h-5" />
                    </button>
                </div>
                <div>
                    <label class="label">Comment <span class="text-red-500">*</span></label>
                    <textarea wire:model="returnNotes" rows="3" class="input resize-none"
                              placeholder="What needs to be corrected…"></textarea>
                    @error('returnNotes') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button wire:click="$set('showReturnModal', false)" class="btn btn-ghost">Cancel</button>
                    <button wire:click="returnForCorrection" wire:loading.attr="disabled" class="btn btn-secondary">Return</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Reject Modal --}}
    @if ($showRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm p-4"
             x-data wire:click.self="$set('showRejectModal', false)">
            <div class="card w-full max-w-md p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-ui.icon name="x-circle" class="w-5 h-5 text-red-500" />
                        <h3 class="font-bold text-slate-900 dark:text-white">Reject Order</h3>
                    </div>
                    <button wire:click="$set('showRejectModal', false)" class="icon-btn">
                        <x-ui.icon name="x" class="w-5 h-5" />
                    </button>
                </div>
                <div>
                    <label class="label">Reason <span class="text-red-500">*</span></label>
                    <textarea wire:model="rejectNotes" rows="3" class="input resize-none"
                              placeholder="Explain why this order is being rejected…"></textarea>
                    @error('rejectNotes') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button wire:click="$set('showRejectModal', false)" class="btn btn-ghost">Cancel</button>
                    <button wire:click="rejectOrder" wire:loading.attr="disabled"
                            class="btn btn-primary !bg-red-600 hover:!bg-red-700">Reject</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Pick Task Modal --}}
    @if ($showPickTaskModal)
        @php
            $activeTask = $activePickTaskId
                ? $this->do->pickLists->flatMap(fn ($pl) => $pl->tasks)->firstWhere('id', $activePickTaskId)
                : null;
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
             x-data wire:click.self="$set('showPickTaskModal', false)">
            <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-xl w-full max-w-lg space-y-4 overflow-hidden">

                {{-- Header --}}
                <div class="px-6 pt-5 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200">Confirm Pick</h3>
                    <button wire:click="$set('showPickTaskModal', false)" class="icon-btn">
                        <x-ui.icon name="x" class="w-5 h-5" />
                    </button>
                </div>

                {{-- Task details --}}
                @if ($activeTask)
                    <div class="mx-6 rounded-xl bg-slate-50 dark:bg-slate-800/50 p-4 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Location</p>
                            <p class="mt-0.5 font-mono font-bold text-slate-800 dark:text-slate-200 text-base">
                                {{ $activeTask->location?->code ?? '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Item</p>
                            <p class="mt-0.5 font-mono text-xs text-slate-400">{{ $activeTask->item?->sku }}</p>
                            <p class="text-slate-800 dark:text-slate-200 font-medium leading-tight">{{ $activeTask->item?->name }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Batch</p>
                            <p class="mt-0.5 text-slate-700 dark:text-slate-300">{{ $activeTask->batch?->batch_no ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Qty to Pick</p>
                            <p class="mt-0.5 font-semibold tabular-nums text-slate-800 dark:text-slate-200">
                                {{ number_format((float) $activeTask->qty_to_pick, 2) }}
                                {{ $activeTask->item?->baseUom?->code }}
                            </p>
                        </div>
                    </div>
                @endif

                {{-- Qty input --}}
                <div class="px-6">
                    <label class="label">Qty Picked <span class="text-red-500">*</span></label>
                    <input
                        wire:model="pickedQty"
                        type="number"
                        min="0.0001"
                        step="any"
                        class="input tabular-nums"
                        autofocus
                    >
                    @error('pickedQty') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Serial selection (only for serial-tracked items) --}}
                @if (! empty($availableSerials))
                    <div class="px-6">
                        <label class="label">Serial Numbers</label>
                        <p class="text-xs text-slate-500 mb-2">Check off serials from the allocated location (highlighted) — must match qty picked.</p>
                        <div class="max-h-40 overflow-y-auto border border-slate-200 dark:border-slate-700 rounded-lg divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($availableSerials as $serial)
                                <label @class([
                                    'flex items-center gap-3 px-3 py-2 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50',
                                    'bg-simba-50 dark:bg-simba-900/10' => $serial['at_alloc_location'],
                                ])>
                                    <input
                                        type="checkbox"
                                        wire:model="selectedSerials"
                                        value="{{ $serial['serial_no'] }}"
                                        class="rounded border-slate-300 text-simba-600 focus:ring-simba-600"
                                    >
                                    <span class="font-mono text-sm text-slate-800 dark:text-slate-200">{{ $serial['serial_no'] }}</span>
                                    <span class="text-xs text-slate-400 ml-auto">{{ $serial['location_code'] }}</span>
                                    @if ($serial['at_alloc_location'])
                                        <span class="text-xs text-simba-600 font-medium">← allocated</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                        @error('selectedSerials') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                {{-- Footer --}}
                <div class="px-6 pb-5 flex justify-end gap-3 border-t border-slate-100 dark:border-slate-800 pt-4">
                    <button wire:click="$set('showPickTaskModal', false)" class="btn btn-ghost">Cancel</button>
                    <button wire:click="confirmPick" wire:loading.attr="disabled" class="btn btn-primary">
                        <x-ui.icon name="check" class="w-4 h-4" /> Confirm Pick
                    </button>
                </div>

            </div>
        </div>
    @endif

    {{-- Dispatch Modal --}}
    @if ($showDispatchModal)
        @php
            $isContainer = $this->do->package_type?->value === \App\Enums\Outbound\PackageType::Container->value;
            $shortDispatchLines = $this->do->lines->filter(
                fn ($l) => bccomp((string) $l->picked_qty, (string) $l->requested_qty_base, 4) < 0
            );
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
             x-data wire:click.self="$set('showDispatchModal', false)">
            <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-xl w-full max-w-2xl space-y-4 overflow-hidden max-h-[90vh] overflow-y-auto">

                {{-- Header --}}
                <div class="px-6 pt-5 flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                    <div class="flex items-center gap-2">
                        <x-ui.icon name="truck" class="w-5 h-5 text-simba-600" />
                        <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200">Confirm Dispatch</h3>
                        <span class="font-mono text-sm text-slate-500">{{ $this->do->number }}</span>
                    </div>
                    <button wire:click="$set('showDispatchModal', false)" class="icon-btn">
                        <x-ui.icon name="x" class="w-5 h-5" />
                    </button>
                </div>

                <div class="px-6 space-y-4">

                    {{-- Short lines warning + backorder option --}}
                    @if ($shortDispatchLines->isNotEmpty())
                        <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-4 space-y-3">
                            <div class="flex items-center gap-2">
                                <x-ui.icon name="exclamation-triangle" class="w-4 h-4 text-amber-600 shrink-0" />
                                <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Short-pick detected</p>
                            </div>
                            <p class="text-xs text-amber-700 dark:text-amber-400">
                                These lines were not fully picked. Only picked quantities will be dispatched.
                            </p>
                            <table class="w-full text-xs border-collapse">
                                <thead>
                                    <tr class="text-amber-700 dark:text-amber-400">
                                        <th class="text-left pb-1 font-semibold">Item</th>
                                        <th class="text-right pb-1 font-semibold">Ordered</th>
                                        <th class="text-right pb-1 font-semibold">Picked</th>
                                        <th class="text-right pb-1 font-semibold">Remaining</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-amber-200 dark:divide-amber-800">
                                    @foreach ($shortDispatchLines as $line)
                                        <tr>
                                            <td class="py-1 text-slate-700 dark:text-slate-300">
                                                <span class="font-mono text-slate-400 mr-1">{{ $line->item?->sku }}</span>
                                                {{ $line->item?->name }}
                                            </td>
                                            <td class="py-1 text-right tabular-nums text-slate-600 dark:text-slate-400">
                                                {{ number_format((float) $line->requested_qty_base, 2) }}
                                            </td>
                                            <td class="py-1 text-right tabular-nums text-emerald-700 dark:text-emerald-400 font-medium">
                                                {{ number_format((float) $line->picked_qty, 2) }}
                                            </td>
                                            <td class="py-1 text-right tabular-nums text-amber-700 dark:text-amber-400 font-semibold">
                                                {{ number_format((float) bcsub((string) $line->requested_qty_base, (string) $line->picked_qty, 4), 2) }}
                                                {{ $line->uom?->code }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" wire:model="createBackorder"
                                       class="mt-0.5 rounded border-amber-400 text-simba-600 focus:ring-simba-600">
                                <div>
                                    <span class="text-sm font-medium text-amber-800 dark:text-amber-300">Create a backorder</span>
                                    <p class="text-xs text-amber-600 dark:text-amber-500 mt-0.5">A new order will be raised for the remaining quantities.</p>
                                </div>
                            </label>
                        </div>
                    @endif

                    {{-- Vehicle & driver fields --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                        <div>
                            <label class="label">Vehicle Registration No <span class="text-rose-500">*</span></label>
                            <input wire:model="vehicleRegNo" type="text" placeholder="T 452 ABC"
                                   class="input w-full mono uppercase @error('vehicleRegNo') border-red-400 @enderror" />
                            @error('vehicleRegNo') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="label">Seal No @if($isContainer)<span class="text-rose-500">*</span>@endif</label>
                            <input wire:model="sealNo" type="text" placeholder="SL-55021"
                                   class="input w-full mono @error('sealNo') border-red-400 @enderror" />
                            @error('sealNo') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>

                        @if ($isContainer)
                            <div>
                                <label class="label">Container No <span class="text-rose-500">*</span></label>
                                <input wire:model="containerNo" type="text" placeholder="TCKU 123456-7"
                                       class="input w-full mono uppercase @error('containerNo') border-red-400 @enderror" />
                                @error('containerNo') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">Container Size <span class="text-rose-500">*</span></label>
                                <select wire:model="containerSize" class="input w-full @error('containerSize') border-red-400 @enderror">
                                    <option value="">— Select size —</option>
                                    @foreach ($this->containerSizes as $size)
                                        <option value="{{ $size->value }}">{{ $size->label() }}</option>
                                    @endforeach
                                </select>
                                @error('containerSize') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <div>
                            <label class="label">Driver Name <span class="text-rose-500">*</span></label>
                            <input wire:model="driverName" type="text" placeholder="Juma Kassim"
                                   class="input w-full @error('driverName') border-red-400 @enderror" />
                            @error('driverName') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="label">Driver Phone</label>
                            <input wire:model="driverPhone" type="tel" placeholder="+255 712 000 000"
                                   class="input w-full tabular-nums @error('driverPhone') border-red-400 @enderror" />
                            @error('driverPhone') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="label">Driver ID No</label>
                            <input wire:model="driverIdNo" type="text" placeholder="DL-4410923"
                                   class="input w-full mono @error('driverIdNo') border-red-400 @enderror" />
                            @error('driverIdNo') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="label">Transporter</label>
                            <select wire:model="transporterPartnerId" class="input w-full">
                                <option value="">— None / Own transport —</option>
                                @foreach ($this->transporters as $transporter)
                                    <option value="{{ $transporter->id }}">{{ $transporter->name }}</option>
                                @endforeach
                            </select>
                        </div>

                    </div>

                    <div>
                        <label class="label">Notes</label>
                        <textarea wire:model="dispatchNotes" rows="2" placeholder="Optional dispatch notes…"
                                  class="input w-full resize-none"></textarea>
                    </div>

                </div>

                {{-- Footer --}}
                <div class="px-6 pb-5 flex justify-end gap-3 border-t border-slate-100 dark:border-slate-800 pt-4">
                    <button wire:click="$set('showDispatchModal', false)" class="btn btn-ghost">Cancel</button>
                    <button wire:click="dispatchOrder" wire:loading.attr="disabled" class="btn btn-primary">
                        <span wire:loading.remove wire:target="dispatchOrder">
                            <x-ui.icon name="truck" class="w-4 h-4" /> Confirm Dispatch
                        </span>
                        <span wire:loading wire:target="dispatchOrder">Dispatching…</span>
                    </button>
                </div>

            </div>
        </div>
    @endif

    {{-- Assign Picker Modal --}}
    @if ($showAssignModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-xl w-full max-w-md mx-4 p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200">Generate Pick List</h3>
                    <button wire:click="$set('showAssignModal', false)"
                            class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                        <x-ui.icon name="x" class="w-5 h-5" />
                    </button>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Optionally assign a picker. Leave blank to generate an unassigned pick list.
                </p>
                <div>
                    <label class="label">Assign To (optional)</label>
                    <select wire:model="assignUserId" class="input">
                        <option value="">— Unassigned —</option>
                        @foreach ($this->pickerUsers as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex justify-end gap-3 pt-1">
                    <button wire:click="$set('showAssignModal', false)" class="btn btn-secondary">Cancel</button>
                    <button wire:click="generatePickList" wire:loading.attr="disabled" class="btn btn-primary">
                        <x-ui.icon name="clipboard-list" class="w-4 h-4" /> Generate Pick List
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Delete Confirmation Modal --}}
    @if ($showDeleteModal)
        <div class="modal-backdrop"
             x-data wire:click.self="$set('showDeleteModal', false)">
            <div class="modal-box max-w-md">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                            <x-ui.icon name="trash" class="w-5 h-5 text-red-600 dark:text-red-400" />
                        </div>
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Delete Order</h3>
                    </div>
                    <button wire:click="$set('showDeleteModal', false)" class="icon-btn">
                        <x-ui.icon name="x-mark" class="w-5 h-5" />
                    </button>
                </div>
                <p class="text-sm text-slate-600 dark:text-slate-400 mb-2">
                    Are you sure you want to permanently delete <strong class="font-mono">{{ $this->do->number }}</strong>?
                </p>
                <p class="text-sm text-red-600 dark:text-red-400 mb-5">
                    This will delete all lines, pick lists, allocations, and dispatch records. Stock movements will be reversed where applicable. This cannot be undone.
                </p>
                <div class="flex justify-end gap-3">
                    <button wire:click="$set('showDeleteModal', false)" class="btn btn-secondary">Cancel</button>
                    <button wire:click="deleteOrder" wire:loading.attr="disabled" class="btn bg-red-600 hover:bg-red-700 text-white">
                        <x-ui.icon name="trash" class="w-4 h-4" /> Delete Permanently
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
