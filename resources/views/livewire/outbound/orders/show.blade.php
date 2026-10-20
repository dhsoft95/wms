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
            {{-- Draft: submit + edit --}}
            @if ($this->do->status === \App\Enums\Outbound\DeliveryOrderStatus::Draft)
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

            {{-- Submitted: pending approval --}}
            @if ($this->do->status === \App\Enums\Outbound\DeliveryOrderStatus::Submitted)
                @can('approveDepartment', $this->do)
                    <button wire:click="approve" wire:loading.attr="disabled" class="btn btn-primary">
                        <x-ui.icon name="check" class="w-4 h-4" /> Approve
                    </button>
                @endcan
            @endif

            {{-- Approved: accept for warehouse processing --}}
            @if ($this->do->status === \App\Enums\Outbound\DeliveryOrderStatus::Approved)
                @can('allocate', $this->do)
                    <button wire:click="accept" wire:loading.attr="disabled" class="btn btn-primary">
                        <x-ui.icon name="check-circle" class="w-4 h-4" /> Accept Order
                    </button>
                @endcan
            @endif

            {{-- Acceptance: allocate stock --}}
            @if ($this->do->status === \App\Enums\Outbound\DeliveryOrderStatus::Acceptance)
                @can('allocate', $this->do)
                    <button wire:click="allocate" wire:loading.attr="disabled" class="btn btn-primary">
                        <x-ui.icon name="check" class="w-4 h-4" /> Allocate Stock
                    </button>
                @endcan
            @endif

            {{-- Allocated / PartiallyAllocated / Picking: generate or view pick list --}}
            @if (in_array($this->do->status, [
                    \App\Enums\Outbound\DeliveryOrderStatus::Picking,
                    \App\Enums\Outbound\DeliveryOrderStatus::Allocated,
                    \App\Enums\Outbound\DeliveryOrderStatus::PartiallyAllocated,
                ]))
                @can('allocate', $this->do)
                    @if ($this->activePickList === null)
                        <button wire:click="$set('showAssignModal', true)" class="btn btn-primary">
                            <x-ui.icon name="clipboard-list" class="w-4 h-4" /> Generate Pick List
                        </button>
                    @else
                        <button wire:click="$set('activeTab', 'pick_lists')" class="btn btn-secondary">
                            <x-ui.icon name="clipboard-list" class="w-4 h-4" />
                            View Pick List
                            <span class="badge badge-slate ml-1 py-0">{{ $this->activePickList->status->label() }}</span>
                        </button>
                    @endif
                @endcan
            @endif

            {{-- Cancel button for all non-terminal states --}}
            @if (! in_array($this->do->status, [
                    \App\Enums\Outbound\DeliveryOrderStatus::Dispatched,
                    \App\Enums\Outbound\DeliveryOrderStatus::Delivered,
                    \App\Enums\Outbound\DeliveryOrderStatus::Cancelled,
                ]))
                @can('cancel', $this->do)
                    <button wire:click="cancel"
                            wire:confirm="Cancel this order? All reservations will be released."
                            class="btn btn-secondary text-red-600 dark:text-red-400">
                        <x-ui.icon name="x-circle" class="w-4 h-4" /> Cancel
                    </button>
                @endcan
            @endif
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
        <x-ui.table>
            <thead>
                <tr>
                    <th>Pick List #</th>
                    <th>Assigned To</th>
                    <th>Status</th>
                    <th>Tasks Progress</th>
                    <th>Started At</th>
                    <th>Completed At</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->do->pickLists as $pickList)
                    @php
                        $totalTasks     = $pickList->tasks->count();
                        $doneTasks      = $pickList->tasks->whereIn('status.value', ['picked', 'short'])->count();
                        $canCancel      = in_array($pickList->status->value, ['open', 'in_progress'])
                                          && $doneTasks === 0;
                    @endphp
                    <tr>
                        <td class="font-mono text-sm font-semibold text-slate-700 dark:text-slate-300">
                            {{ $pickList->number }}
                        </td>
                        <td class="text-sm">{{ $pickList->assignedTo?->name ?? '—' }}</td>
                        <td>
                            <span @class([
                                'badge',
                                'badge-slate'   => $pickList->status === \App\Enums\Outbound\PickListStatus::Open,
                                'badge-violet'  => $pickList->status === \App\Enums\Outbound\PickListStatus::InProgress,
                                'badge-green'   => $pickList->status === \App\Enums\Outbound\PickListStatus::Completed,
                                'badge-red'     => $pickList->status === \App\Enums\Outbound\PickListStatus::Cancelled,
                            ])>
                                <span class="dot"></span>{{ $pickList->status->label() }}
                            </span>
                        </td>
                        <td>
                            @if ($totalTasks > 0)
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-slate-200 dark:bg-slate-700 rounded-full h-1.5 min-w-[60px]">
                                        <div class="bg-simba-500 h-1.5 rounded-full transition-all"
                                             style="width: {{ round($doneTasks / $totalTasks * 100) }}%"></div>
                                    </div>
                                    <span class="text-xs text-slate-500 tabular-nums whitespace-nowrap">
                                        {{ $doneTasks }}/{{ $totalTasks }}
                                    </span>
                                </div>
                            @else
                                <span class="text-slate-400 text-xs">—</span>
                            @endif
                        </td>
                        <td class="tabular-nums text-xs text-slate-500">
                            {{ $pickList->started_at?->format('d M H:i') ?? '—' }}
                        </td>
                        <td class="tabular-nums text-xs text-slate-500">
                            {{ $pickList->completed_at?->format('d M H:i') ?? '—' }}
                        </td>
                        <td>
                            @if ($canCancel)
                                @can('allocate', $this->do)
                                    <button
                                        wire:click="cancelPickList({{ $pickList->id }})"
                                        wire:confirm="Cancel this pick list? Tasks will be cancelled and you can regenerate."
                                        class="text-xs text-red-500 hover:text-red-700 dark:hover:text-red-400 font-medium">
                                        Cancel
                                    </button>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="!py-8 text-center text-slate-500">No pick lists generated yet.</td></tr>
                @endforelse
            </tbody>
        </x-ui.table>
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
</div>
