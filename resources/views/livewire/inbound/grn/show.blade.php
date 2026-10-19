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
                        <x-ui.icon name="pencil" class="w-4 h-4" /> Continue Tally
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

            @if (in_array($grn->status->value, ['received', 'putaway_in_progress']))
                <button wire:click="$set('activeTab', 'putaway')" class="btn btn-primary">
                    <x-ui.icon name="archive" class="w-4 h-4" /> Proceed to Putaway
                </button>
            @endif

            @if (in_array($grn->status->value, ['received', 'putaway_complete']))
                @can('close', $grn)
                    <button wire:click="close" wire:confirm="Close this GRN? No further changes will be possible."
                            class="btn btn-secondary">
                        <x-ui.icon name="check" class="w-4 h-4" /> Close
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
        </div>
    </div>

    {{-- Status Stepper --}}
    <div class="card">
        <div class="card-body py-4">
            <div class="flex items-center justify-between overflow-x-auto gap-1">
                @foreach ($this->stepperStatuses as $step)
                    @php
                        $currentIdx = array_search($grn->status, $this->stepperStatuses);
                        $stepIdx    = array_search($step, $this->stepperStatuses);
                        $done       = $stepIdx < $currentIdx;
                        $current    = $stepIdx === $currentIdx;
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
                            'bg-emerald-500' => $stepIdx < $currentIdx,
                            'bg-slate-200 dark:bg-slate-700' => $stepIdx >= $currentIdx,
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
                    'lpns'     => 'LPNs (' . $grn->lpns()->count() . ')',
                    'putaway'  => 'Putaway (' . $grn->putawayTasks()->count() . ')',
                    'variance' => 'Variance Approvals',
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
                    </tr>
                @empty
                    <tr><td colspan="8" class="!py-8 text-center text-slate-500">No lines yet.</td></tr>
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

        {{-- LPNs Tab --}}
        @if ($activeTab === 'lpns')
        <x-ui.table>
            <thead>
                <tr>
                    <th>LPN Code</th>
                    <th>Status</th>
                    <th>Current Location</th>
                    <th>Printed</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->lpns as $lpn)
                    <tr>
                        <td class="mono font-semibold">{{ $lpn->code }}</td>
                        <td><span class="badge badge-slate">{{ $lpn->status->value }}</span></td>
                        <td class="mono text-xs">{{ $lpn->currentLocation?->code ?? '—' }}</td>
                        <td class="tabular-nums text-xs">
                            @if ($lpn->printed_at)
                                {{ $lpn->printed_at->format('d M H:i') }}
                                <span class="text-slate-400">(×{{ $lpn->print_count }})</span>
                            @else
                                <span class="text-slate-400">Never</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @can('lpn.print')
                                <a href="{{ route('lpn.print', ['grn' => $grn->id, 'lpns' => $lpn->id]) }}"
                                   target="_blank" class="btn btn-secondary btn-sm">
                                    <x-ui.icon name="printer" class="w-3.5 h-3.5" /> Print
                                </a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="!py-8 text-center text-slate-500">No LPNs for this GRN.</td></tr>
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
                            @if ($task->actualLocation)
                                <span class="font-semibold text-emerald-600 dark:text-emerald-400">
                                    {{ $task->actualLocation->code }}
                                </span>
                            @elseif ($task->suggestedLocation)
                                <span class="text-slate-400">{{ $task->suggestedLocation->code }} (suggested)</span>
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
                            @if (in_array($task->status->value, ['open', 'assigned', 'in_progress']))
                                @can('execute', $task)
                                    <button wire:click="openExecute({{ $task->id }})"
                                            class="btn btn-primary btn-sm">
                                        <x-ui.icon name="archive" class="w-3.5 h-3.5" /> Put Away
                                    </button>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="!py-8 text-center text-slate-500">No putaway tasks yet.</td></tr>
                @endforelse
            </tbody>
        </x-ui.table>
        @endif

        {{-- Variance Approvals Tab --}}
        @if ($activeTab === 'variance')
        <div class="card-body space-y-4">
            @forelse ($this->varianceApprovals as $approval)
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4 space-y-2">
                    <div class="flex items-start justify-between">
                        <div>
                            <span class="text-sm font-medium">Requested by {{ $approval->requestedBy?->name }}</span>
                            <span class="text-xs text-slate-500 ml-2">{{ $approval->created_at->diffForHumans() }}</span>
                        </div>
                        @if ($approval->isPending())
                            <span class="badge badge-amber"><span class="dot"></span>Pending</span>
                        @elseif ($approval->isApproved())
                            <span class="badge badge-green"><span class="dot"></span>Approved</span>
                        @else
                            <span class="badge badge-red"><span class="dot"></span>Rejected</span>
                        @endif
                    </div>
                    @if ($approval->decidedBy)
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            Decision by <strong>{{ $approval->decidedBy?->name }}</strong>
                            @if ($approval->notes) — {{ $approval->notes }} @endif
                        </p>
                    @endif
                </div>
            @empty
                <p class="text-center text-slate-500 py-8">No variance approvals.</p>
            @endforelse
        </div>
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

    {{-- Putaway Execute Modal --}}
    @if ($showExecuteModal && $this->executingTask)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-lg p-6 space-y-5">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold">Execute Putaway</h3>
                <button wire:click="closeExecute" class="icon-btn">
                    <x-ui.icon name="x" class="w-5 h-5" />
                </button>
            </div>

            {{-- Task Summary --}}
            <div class="rounded-xl bg-slate-50 dark:bg-slate-800/50 p-4 space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-500">Item</span>
                    <span class="font-mono font-semibold">{{ $this->executingTask->item?->sku }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Description</span>
                    <span>{{ $this->executingTask->item?->name }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Qty</span>
                    <span class="tabular-nums font-semibold">{{ number_format((float)$this->executingTask->qty, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">From</span>
                    <span class="mono">{{ $this->executingTask->fromLocation?->code }}</span>
                </div>
                @if ($this->executingTask->suggestedLocation)
                <div class="flex justify-between">
                    <span class="text-slate-500">Suggested</span>
                    <span class="mono text-slate-400">{{ $this->executingTask->suggestedLocation?->code }}</span>
                </div>
                @endif
            </div>

            {{-- Destination Bin --}}
            <div x-data="{ open: false }">
                <label class="label">Destination Bin <span class="text-red-500">*</span></label>
                <div class="relative">
                    <input wire:model.live.debounce.250ms="scanDestination"
                           wire:keydown.enter="resolveDestination"
                           @focus="open = true"
                           @keydown.escape="open = false"
                           type="text"
                           placeholder="Scan or type bin code…"
                           class="input w-full font-mono pr-10"
                           autocomplete="off"
                           autofocus />

                    @if ($scanDestination !== '')
                        <button type="button"
                                wire:click="$set('scanDestination', ''); $set('destinationId', '')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                            <x-ui.icon name="x" class="w-4 h-4" />
                        </button>
                    @endif

                    @if (count($this->binSearchResults) > 0)
                        <div x-show="open"
                             @click.outside="open = false"
                             class="absolute z-20 w-full mt-1 overflow-hidden rounded-xl border border-white/50 dark:border-white/[0.08] shadow-lg glass-solid dark:glass-solid-dark">
                            @foreach ($this->binSearchResults as $loc)
                                <button type="button"
                                        wire:click="selectBin({{ $loc['id'] }}, '{{ $loc['code'] }}')"
                                        @click="open = false"
                                        class="w-full flex items-center justify-between px-4 py-2.5 text-sm hover:bg-brand-50/60 dark:hover:bg-brand-900/20 transition-colors {{ !$loop->first ? 'border-t border-white/40 dark:border-white/[0.06]' : '' }}">
                                    <span class="font-mono font-semibold">{{ $loc['code'] }}</span>
                                    @if ($loc['barcode'] && $loc['barcode'] !== $loc['code'])
                                        <span class="text-xs text-slate-400 font-mono truncate ml-3">{{ $loc['barcode'] }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
                @error('destinationId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-800">
                <button wire:click="closeExecute" class="btn btn-secondary">Cancel</button>
                <button wire:click="executeTask"
                        class="btn btn-primary"
                        @disabled($destinationId === '')>
                    <x-ui.icon name="check" class="w-4 h-4" /> Confirm Putaway
                </button>
            </div>
        </div>
    </div>
    @endif

</div>
