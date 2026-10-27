<div class="space-y-6">
    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <a href="{{ route('inbound.grn.index') }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-simba-600 mb-2">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Back to GRNs
            </a>
            <h2 class="page-title font-mono">{{ $grn->number }}</h2>
            <div class="flex items-center gap-3 mt-1">
                <span class="badge {{ $grn->status->color() }}">
                    <span class="dot"></span>{{ $grn->status->label() }}
                </span>
                <span class="badge {{ $grn->source->color() }}">{{ $grn->source->label() }}</span>
                <span class="text-sm text-slate-500">{{ $grn->warehouse?->name }}</span>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center gap-2 flex-wrap">
            @if ($grn->status->value === 'draft')
                @if ($grn->purchase_order_id)
                    {{-- PO-driven GRN: go to tally page to enter quantities --}}
                    <a href="{{ route('inbound.grn.tally', $grn) }}" wire:navigate class="btn btn-primary">
                        <x-ui.icon name="pencil" class="w-4 h-4" /> Continue Receiving
                    </a>
                @else
                    @can('receive', $grn)
                        <button wire:click="startReceiving" class="btn btn-primary">
                            <x-ui.icon name="play" class="w-4 h-4" /> Start Receiving
                        </button>
                    @endcan
                @endif
            @endif

            @if (in_array($grn->status->value, ['draft', 'receiving']) && ! $grn->purchase_order_id)
                <a href="{{ route('inbound.grn.receive', $grn) }}" wire:navigate class="btn btn-primary">
                    <x-ui.icon name="qr-code" class="w-4 h-4" /> Receive Goods
                </a>
            @endif

            @if ($grn->status->value === 'receiving')
                @can('confirm', $grn)
                    <button wire:click="confirmGrn"
                            wire:confirm="Confirm this GRN? Stock will be posted to the receiving dock."
                            class="btn btn-primary">
                        <x-ui.icon name="check" class="w-4 h-4" /> Confirm GRN
                    </button>
                @endcan
            @endif


            @if ($grn->status->value === 'putaway_complete')
                @can('close', $grn)
                    <button wire:click="close" wire:confirm="Close this GRN? No further changes will be possible."
                            class="btn btn-primary">
                        <x-ui.icon name="check" class="w-4 h-4" /> Close GRN
                    </button>
                @endcan
            @endif

            @if ($grn->status->isActive() && $grn->status->value !== 'received')
                @can('cancel', $grn)
                    <button wire:click="cancel" wire:confirm="Cancel this GRN? This cannot be undone."
                            class="btn btn-secondary text-red-600 dark:text-red-400">
                        <x-ui.icon name="x" class="w-4 h-4" /> Cancel
                    </button>
                @endcan
            @endif

            @if ($grn->number)
                <x-ui.pdf-viewer :url="route('inbound.grn.document', $grn)" label="GRN Document"
                                 btnClass="btn btn-secondary gap-1.5">
                    <x-ui.icon name="document-arrow-down" class="w-4 h-4" /> Download GRN
                </x-ui.pdf-viewer>
            @endif
        </div>
    </div>

    {{-- Status Stepper --}}
    <div class="card">
        <div class="card-body py-4">
            <div class="flex items-center justify-between overflow-x-auto gap-1">
                @php $allDone = in_array($grn->status->value, ['closed', 'cancelled']); @endphp
                @foreach ($this->stepperStatuses as $step)
                    @php
                        $currentIdx = array_search($this->stepperStatus(), $this->stepperStatuses);
                        $stepIdx    = array_search($step, $this->stepperStatuses);
                        $done       = $allDone || $stepIdx < $currentIdx;
                        $current    = !$allDone && $stepIdx === $currentIdx;
                    @endphp
                    <div class="flex flex-col items-center gap-1 shrink-0">
                        <div @class([
                            'w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border-2 transition',
                            'border-emerald-500 bg-emerald-500 text-white' => $done,
                            'border-simba-500 bg-simba-500 text-white' => $current,
                            'border-slate-300 dark:border-slate-600 text-slate-400' => !$done && !$current,
                        ])>
                            @if ($done)
                                <x-ui.icon name="check" class="w-4 h-4" />
                            @else
                                {{ $stepIdx + 1 }}
                            @endif
                        </div>
                        <span @class([
                            'text-[10px] text-center leading-tight',
                            'text-emerald-600 dark:text-emerald-400 font-semibold' => $done,
                            'text-simba-600 dark:text-simba-400 font-semibold' => $current,
                            'text-slate-400' => !$done && !$current,
                        ])>{{ $step->label() }}</span>
                    </div>
                    @if (! $loop->last)
                        <div @class([
                            'flex-1 h-0.5 min-w-[12px] transition',
                            'bg-emerald-500' => $allDone || $stepIdx < $currentIdx,
                            'bg-slate-200 dark:bg-slate-700' => !$allDone && $stepIdx >= $currentIdx,
                        ])></div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    {{-- Variance Approval Panel --}}
    @if ($grn->status->value === 'pending_variance_approval')
    <div class="card border-amber-200 dark:border-amber-800/50">
        <div class="card-header bg-amber-50/60 dark:bg-amber-950/30">
            <h3 class="card-title text-amber-800 dark:text-amber-300">
                <x-ui.icon name="refresh" class="w-4 h-4" />
                Variance Approval Required
            </h3>
        </div>
        <div class="card-body space-y-4">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                This GRN has line variances that require manager approval before stock can be posted.
            </p>
            @can('approveVariance', $grn)
                <div>
                    <label class="label">Decision Notes</label>
                    <textarea wire:model="approvalNotes" rows="2"
                              class="input resize-none" placeholder="Optional notes…"></textarea>
                </div>
                <div class="flex gap-3">
                    <button wire:click="approveVariance" class="btn btn-primary">
                        <x-ui.icon name="check" class="w-4 h-4" /> Approve Variance & Confirm
                    </button>
                    <button wire:click="rejectVariance" class="btn btn-secondary text-red-600 dark:text-red-400">
                        <x-ui.icon name="x" class="w-4 h-4" /> Reject  Return to Receiving
                    </button>
                </div>
            @else
                <p class="text-sm text-amber-700 dark:text-amber-400 font-medium">
                    Awaiting manager approval. You do not have permission to approve.
                </p>
            @endcan
        </div>
    </div>
    @endif

    {{-- Tabs --}}
    <div class="card">
        <div class="card-header">
            <div class="tabs overflow-x-auto">
                @foreach ([
                    'lines'    => 'Lines (' . $grn->lines()->count() . ')',
                    'receipts' => 'Receipts (' . $grn->receipts()->count() . ')',
                    'putaway'  => 'Putaway (' . $grn->putawayTasks()->count() . ')',
                    'activity' => 'Activity',
                ] as $tab => $label)
                    <button wire:click="$set('activeTab','{{ $tab }}')"
                            @class(['tab', 'active' => $activeTab === $tab])>{{ $label }}</button>
                @endforeach
            </div>
        </div>

        {{-- Lines Tab --}}
        @if ($activeTab === 'lines')
        <x-ui.table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>VW</th>
                    <th>UoM</th>
                    <th>Expected</th>
                    <th>Good</th>
                    <th>Damaged</th>
                    <th>Variance</th>
                    <th>Type</th>
                    <th>MIC No.</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->lines as $line)
                    <tr>
                        <td>
                            <div class="font-mono text-xs">{{ $line->item?->sku }}</div>
                            <div class="text-sm text-slate-600 dark:text-slate-400">{{ $line->item?->name }}</div>
                        </td>
                        <td class="text-sm">{{ $line->virtualWarehouse?->name ?? '—' }}</td>
                        <td class="mono text-sm">{{ $line->uom?->code }}</td>
                        <td class="tabular-nums">{{ number_format((float)$line->expected_qty, 2) }}</td>
                        <td class="tabular-nums text-emerald-700 dark:text-emerald-400">
                            {{ number_format((float)$line->received_good_qty, 2) }}
                        </td>
                        <td class="tabular-nums text-red-700 dark:text-red-400">
                            {{ number_format((float)$line->received_damaged_qty, 2) }}
                        </td>
                        <td class="tabular-nums {{ (float)$line->variance_qty != 0 ? 'text-amber-600 dark:text-amber-400 font-semibold' : '' }}">
                            {{ ((float)$line->variance_qty > 0 ? '+' : '') . number_format((float)$line->variance_qty, 2) }}
                        </td>
                        <td>
                            <span class="badge {{ $line->variance_type->color() }}">
                                {{ $line->variance_type->label() }}
                            </span>
                        </td>
                        <td>
                            @if ($line->mic_number)
                                @php
                                    try {
                                        $bobj   = (new \Com\Tecnick\Barcode\Barcode())
                                            ->getBarcodeObj('C128', $line->mic_number, 160, 36);
                                        $micUri = 'data:image/png;base64,' . base64_encode($bobj->getPngData(true));
                                    } catch (\Throwable) {
                                        $micUri = null;
                                    }
                                @endphp
                                @if ($micUri)
                                    <img src="{{ $micUri }}" alt="{{ $line->mic_number }}"
                                         class="w-32 h-auto block mb-0.5">
                                @endif
                                <div class="font-mono text-xs text-slate-500">{{ $line->mic_number }}</div>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="!py-8 text-center text-slate-500">No lines yet.</td></tr>
                @endforelse
            </tbody>
        </x-ui.table>
        @endif

        {{-- Receipts Tab --}}
        @if ($activeTab === 'receipts')
        <x-ui.table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>UoM</th>
                    <th>Batch</th>
                    <th>Expiry</th>
                    <th>Condition</th>
                    <th>LPN</th>
                    <th>Received By</th>
                    <th>At</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->receipts as $receipt)
                    <tr>
                        <td class="font-mono text-xs">{{ $receipt->line?->item?->sku }}</td>
                        <td class="tabular-nums">{{ number_format((float)$receipt->qty, 4) }}</td>
                        <td class="mono text-xs">{{ $receipt->uom?->code }}</td>
                        <td class="mono text-xs">{{ $receipt->batch_no ?? '—' }}</td>
                        <td class="tabular-nums text-sm">{{ $receipt->expiry_date?->format('d M Y') ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $receipt->condition->color() }}">
                                {{ $receipt->condition->label() }}
                            </span>
                        </td>
                        <td class="mono text-xs">{{ $receipt->lpn?->code ?? '—' }}</td>
                        <td class="text-sm">{{ $receipt->receivedBy?->name }}</td>
                        <td class="tabular-nums text-xs text-slate-500">{{ $receipt->created_at->format('d M H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="!py-8 text-center text-slate-500">No receipts recorded yet.</td></tr>
                @endforelse
            </tbody>
        </x-ui.table>
        @endif

        {{-- Putaway Tasks Tab --}}
        @if ($activeTab === 'putaway')
        <x-ui.table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>From</th>
                    <th>Destination</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->putawayTasks as $task)
                    <tr wire:key="pt-{{ $task->id }}">
                        <td>
                            <div class="font-mono text-xs text-simba-600 dark:text-simba-400">{{ $task->item?->sku }}</div>
                            <div class="text-sm text-slate-500">{{ $task->item?->name }}</div>
                        </td>
                        <td class="tabular-nums">{{ number_format((float)$task->qty, 2) }}</td>
                        <td class="mono text-xs">{{ $task->fromLocation?->code ?? '—' }}</td>
                        <td class="mono text-xs">
                            @if ($task->splits->count() > 1)
                                <div class="space-y-0.5">
                                    @foreach ($task->splits as $split)
                                        <div class="text-xs">
                                            <span class="font-mono font-semibold text-emerald-600 dark:text-emerald-400">{{ $split->location?->code }}</span>
                                            <span class="text-slate-400 ml-1">× {{ number_format((float)$split->qty, 2) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @elseif ($task->actualLocation)
                                <span class="font-semibold text-emerald-600 dark:text-emerald-400">
                                    {{ $task->actualLocation->code }}
                                </span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $task->status->color() }}">
                                <span class="dot"></span>{{ $task->status->label() }}
                            </span>
                        </td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button wire:click="openDetails({{ $task->id }})"
                                        class="btn btn-secondary btn-sm">
                                    <x-ui.icon name="eye" class="w-3.5 h-3.5" /> Details
                                </button>
                                @if (in_array($task->status->value, ['open', 'assigned', 'in_progress']))
                                    @can('execute', $task)
                                        <button wire:click="openExecute({{ $task->id }})"
                                                class="btn btn-primary btn-sm">
                                            <x-ui.icon name="archive" class="w-3.5 h-3.5" /> Put Away
                                        </button>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="!py-8 text-center text-slate-500">No putaway tasks yet.</td></tr>
                @endforelse
            </tbody>
        </x-ui.table>
        @endif

        {{-- Activity Tab --}}
        @if ($activeTab === 'activity')
        <div class="card-body space-y-3">
            @forelse ($this->activityLog as $log)
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center shrink-0">
                        <x-ui.icon name="clock" class="w-4 h-4 text-slate-400" />
                    </div>
                    <div>
                        <p class="text-sm font-medium">{{ $log->description }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $log->causer?->name ?? 'System' }} · {{ $log->created_at->format('d M Y H:i') }}
                        </p>
                    </div>
                </div>
            @empty
                <p class="text-center text-slate-500 py-8">No activity recorded.</p>
            @endforelse
        </div>
        @endif

    </div>

    @include('livewire.partials.putaway-execute-modal')


    {{-- Putaway Task Details Modal --}}
    @if ($showDetailsModal && $this->detailsTask)
    @php $dt = $this->detailsTask; @endphp
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-lg flex flex-col max-h-[90vh]">

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-800 shrink-0">
                <div>
                    <h3 class="text-base font-semibold">Putaway Task Details</h3>
                    <p class="text-xs text-slate-500 font-mono mt-0.5">#{{ $dt->id }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="badge {{ $dt->status->color() }}">
                        <span class="dot"></span>{{ $dt->status->label() }}
                    </span>
                    <button wire:click="closeDetails" class="icon-btn">
                        <x-ui.icon name="x" class="w-4 h-4" />
                    </button>
                </div>
            </div>

            {{-- Body --}}
            <div class="overflow-y-auto px-5 py-4 space-y-5">

                {{-- Item --}}
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-2">Item</p>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center shrink-0">
                            <x-ui.icon name="cube" class="w-5 h-5 text-slate-400" />
                        </div>
                        <div>
                            <div class="font-semibold text-sm">{{ $dt->item?->name }}</div>
                            <div class="font-mono text-xs text-simba-600 dark:text-simba-400">{{ $dt->item?->sku }}</div>
                        </div>
                    </div>
                </div>

                {{-- Qty & LPN --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Quantity</p>
                        <p class="tabular-nums font-semibold">{{ rtrim(rtrim(number_format((float)$dt->qty, 4), '0'), '.') }}</p>
                    </div>
                    @if ($dt->lpn)
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">LPN</p>
                        <p class="font-mono text-xs">{{ $dt->lpn->code }}</p>
                    </div>
                    @endif
                </div>

                {{-- Locations --}}
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-2">Locations</p>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-500">From</span>
                            <span class="font-mono font-semibold">{{ $dt->fromLocation?->code ?? '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-500">Actual</span>
                            @if ($dt->actualLocation)
                                <span class="font-mono font-semibold text-emerald-600 dark:text-emerald-400">{{ $dt->actualLocation->code }}</span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Splits --}}
                @if ($dt->splits->count() > 0)
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-2">Split Destinations ({{ $dt->splits->count() }})</p>
                    <div class="divide-y divide-slate-100 dark:divide-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                        @foreach ($dt->splits as $split)
                        <div class="flex items-center justify-between px-3 py-2.5 text-sm">
                            <span class="font-mono font-semibold text-emerald-600 dark:text-emerald-400">{{ $split->location?->code }}</span>
                            <div class="flex items-center gap-3">
                                <span class="tabular-nums text-slate-600 dark:text-slate-300">{{ rtrim(rtrim(number_format((float)$split->qty, 4), '0'), '.') }}</span>
                                @if ($split->serial_nos && count($split->serial_nos))
                                    <span class="text-xs text-slate-400">{{ count($split->serial_nos) }} serial(s)</span>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Assignment & Completion --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Assigned To</p>
                        <p class="text-sm">{{ $dt->assignedTo?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Completed By</p>
                        <p class="text-sm">{{ $dt->completedBy?->name ?? '—' }}</p>
                    </div>
                    @if ($dt->completed_at)
                    <div class="col-span-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Completed At</p>
                        <p class="text-sm">{{ $dt->completed_at->format('d M Y H:i') }}</p>
                    </div>
                    @endif
                </div>

            </div>

            {{-- Footer --}}
            <div class="flex justify-end px-5 py-4 border-t border-slate-200 dark:border-slate-800 shrink-0">
                <button wire:click="closeDetails" class="btn btn-secondary">Close</button>
            </div>
        </div>
    </div>
    @endif

</div>
