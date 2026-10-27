<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <a href="{{ route('inbound.orders.index') }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-simba-600 mb-2">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Back to Purchase Orders
            </a>
            <h2 class="page-title font-mono">{{ $purchaseOrder->number }}</h2>
            <div class="flex flex-wrap items-center gap-3 mt-1">
                <span class="badge {{ $purchaseOrder->status?->color() ?? 'badge-slate' }}">
                    <span class="dot"></span>{{ $purchaseOrder->status?->label() }}
                </span>
                @if ($purchaseOrder->department)
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ $purchaseOrder->department->name }}</span>
                @endif
                <span class="text-xs text-slate-500 dark:text-slate-400">
                    Created {{ $purchaseOrder->created_at->format('d M Y H:i') }}
                </span>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="flex flex-wrap items-center gap-2">
            {{-- Attached PO document — always visible when uploaded --}}
            @if ($purchaseOrder->po_file_path)
                <x-ui.pdf-viewer :url="route('inbound.orders.document.download', $purchaseOrder)"
                                 label="Attached PO Document"
                                 btnClass="btn btn-secondary gap-1.5">
                    <x-ui.icon name="paper-clip" class="w-4 h-4" /> Attached PO
                </x-ui.pdf-viewer>
            @endif

            {{-- Draft actions --}}
            @if ($purchaseOrder->status === \App\Enums\Inbound\PurchaseOrderStatus::Draft)
                @can('update', $purchaseOrder)
                    <a href="{{ route('inbound.orders.edit', $purchaseOrder) }}" wire:navigate class="btn btn-secondary">
                        <x-ui.icon name="pencil" class="w-4 h-4" /> Edit
                    </a>
                @endcan
                @can('submit', $purchaseOrder)
                    <button wire:click="submit" wire:loading.attr="disabled" class="btn btn-primary">
                        <x-ui.icon name="paper-airplane" class="w-4 h-4" /> Submit for Approval
                    </button>
                @endcan
            @endif

            {{-- Returned for correction — HOD can edit directly or forward to requester --}}
            @if ($purchaseOrder->status === \App\Enums\Inbound\PurchaseOrderStatus::ReturnedForCorrection)
                @can('update', $purchaseOrder)
                    <a href="{{ route('inbound.orders.edit', $purchaseOrder) }}" wire:navigate class="btn btn-secondary">
                        <x-ui.icon name="pencil" class="w-4 h-4" /> Edit
                    </a>
                @endcan
                @can('submit', $purchaseOrder)
                    <button wire:click="submit" wire:loading.attr="disabled" class="btn btn-primary">
                        <x-ui.icon name="paper-airplane" class="w-4 h-4" /> Resubmit
                    </button>
                @endcan
                @if ($this->canForwardReturn)
                    <button wire:click="openForwardModal" class="btn btn-secondary">
                        <x-ui.icon name="arrow-uturn-left" class="w-4 h-4" /> Send Back to Requester
                    </button>
                @endif
            @endif

            {{-- Awaiting requester correction — requester's turn to fix and resubmit --}}
            @if ($purchaseOrder->status === \App\Enums\Inbound\PurchaseOrderStatus::AwaitingRequesterCorrection)
                @can('update', $purchaseOrder)
                    <a href="{{ route('inbound.orders.edit', $purchaseOrder) }}" wire:navigate class="btn btn-secondary">
                        <x-ui.icon name="pencil" class="w-4 h-4" /> Edit
                    </a>
                @endcan
                @can('submit', $purchaseOrder)
                    <button wire:click="submit" wire:loading.attr="disabled" class="btn btn-primary">
                        <x-ui.icon name="paper-airplane" class="w-4 h-4" /> Resubmit
                    </button>
                @endcan
            @endif

            {{-- Pending approval: approve/return/reject (both dept-head and WM levels) --}}
            @if ($purchaseOrder->status->isAwaitingApproval())
                @if ($this->canActOnApprovals)
                    <button wire:click="openReturnModal" class="btn btn-secondary">
                        <x-ui.icon name="arrow-uturn-left" class="w-4 h-4" /> Return
                    </button>
                    <button wire:click="openRejectModal" class="btn btn-secondary text-red-600 dark:text-red-400">
                        <x-ui.icon name="x-circle" class="w-4 h-4" /> Reject
                    </button>
                    <button wire:click="approve" wire:loading.attr="disabled" class="btn btn-primary">
                        <x-ui.icon name="check" class="w-4 h-4" /> Approve
                    </button>
                @endif
            @endif

            {{-- Approved — warehouse manager accepts --}}
            @if ($purchaseOrder->status === \App\Enums\Inbound\PurchaseOrderStatus::ApprovedPendingAcceptance)
                @can('accept', $purchaseOrder)
                    <button wire:click="openWmRejectModal" class="btn btn-secondary text-amber-600 dark:text-amber-400">
                        <x-ui.icon name="arrow-uturn-left" class="w-4 h-4" /> Send Back
                    </button>
                    <button wire:click="accept" wire:loading.attr="disabled" class="btn btn-primary">
                        <x-ui.icon name="check" class="w-4 h-4" /> Accept Order
                    </button>
                @endcan
            @endif

            {{-- Accepted — assign supervisor --}}
            @if ($purchaseOrder->status === \App\Enums\Inbound\PurchaseOrderStatus::AcceptedPendingAssignment)
                @can('assign', $purchaseOrder)
                    <button wire:click="openAssignSupervisor" class="btn btn-primary">
                        <x-ui.icon name="user-plus" class="w-4 h-4" /> Assign Supervisor
                    </button>
                @endcan
            @endif

            {{-- Assigned — assign clerk + start receiving --}}
            @if ($purchaseOrder->status === \App\Enums\Inbound\PurchaseOrderStatus::AssignedPendingReceiving)
                @can('assign', $purchaseOrder)
                    <button wire:click="openAssignClerk" class="btn btn-secondary">
                        <x-ui.icon name="user-plus" class="w-4 h-4" />
                        {{ $purchaseOrder->assigned_clerk_id ? 'Reassign Clerk' : 'Assign Clerk' }}
                    </button>
                @endcan
                @can('receive', $purchaseOrder)
                    <button wire:click="openReceiveModal" class="btn btn-primary">
                        <x-ui.icon name="clipboard" class="w-4 h-4" /> Start Receiving
                    </button>
                @endcan
            @endif

            {{-- Receiving in progress — only action is via GRN tally page --}}
            @if (in_array($purchaseOrder->status, [
                    \App\Enums\Inbound\PurchaseOrderStatus::ReceivingInProgress,
                    \App\Enums\Inbound\PurchaseOrderStatus::PartiallyReceived,
                ], true))
                @can('receive', $purchaseOrder)
                    <button wire:click="openReceiveModal" class="btn btn-secondary">
                        <x-ui.icon name="plus" class="w-4 h-4" /> New Delivery
                    </button>
                @endcan
                @can('closeShort', $purchaseOrder)
                    <button wire:click="openCloseShortModal" class="btn btn-secondary text-amber-600 dark:text-amber-400">
                        <x-ui.icon name="x-mark" class="w-4 h-4" /> Close Partial Delivery
                    </button>
                @endcan
            @endif

            {{-- Putaway: show tasks with Execute button when order is in putaway status --}}
            @if (in_array($purchaseOrder->status, [
                \App\Enums\Inbound\PurchaseOrderStatus::PendingPutaway,
                \App\Enums\Inbound\PurchaseOrderStatus::PutawayInProgress,
            ], true) && $this->putawayTasks->isNotEmpty())
                @can('execute', $this->putawayTasks->first())
                    <button wire:click="openExecute({{ $this->putawayTasks->firstWhere(fn($t) => in_array($t->status->value, ['open','assigned','in_progress']))?->id ?? 0 }})"
                            class="btn btn-primary">
                        <x-ui.icon name="archive" class="w-4 h-4" /> Execute Putaway
                    </button>
                @endcan
            @endif

            {{-- Completed — WM closes the order (or close via GRN show page) --}}
            @if (in_array($purchaseOrder->status, [
                    \App\Enums\Inbound\PurchaseOrderStatus::CompletedPendingVerification,
                    \App\Enums\Inbound\PurchaseOrderStatus::PendingSupervisorValidation,
                    \App\Enums\Inbound\PurchaseOrderStatus::IssueRaised,
                    \App\Enums\Inbound\PurchaseOrderStatus::IssueResolved,
                ], true))
                @if (auth()->user()->hasAnyRole(['warehouse-manager', 'system-admin']))
                    <button wire:click="closeOrder"
                            wire:confirm="Close this purchase order?"
                            class="btn btn-primary">
                        <x-ui.icon name="lock-closed" class="w-4 h-4" /> Close Order
                    </button>
                @endif
                @can('resolveIssue', $purchaseOrder)
                    @if ($purchaseOrder->status === \App\Enums\Inbound\PurchaseOrderStatus::IssueRaised)
                        @foreach ($purchaseOrder->issues->where('status.value', 'open') as $openIssue)
                            <button wire:click="openResolveModal({{ $openIssue->id }})" class="btn btn-secondary">
                                <x-ui.icon name="check" class="w-4 h-4" /> Resolve Issue
                            </button>
                        @endforeach
                    @endif
                @endcan
            @endif

            {{-- Cancel (if status is cancellable) --}}
            @if ($purchaseOrder->status?->isCancellable())
                @can('cancel', $purchaseOrder)
                    <button wire:click="cancel"
                            wire:confirm="Cancel this purchase order? This cannot be undone."
                            class="btn btn-secondary text-red-600 dark:text-red-400">
                        <x-ui.icon name="x-circle" class="w-4 h-4" /> Cancel
                    </button>
                @endcan
            @endif
        </div>
    </div>

    {{-- Status Timeline --}}
    @php
        $currentIdx   = collect($this->timelineSteps)->search(fn($s) => $s['status'] === $this->timelineStatus());
        $allDone = $purchaseOrder->status === \App\Enums\Inbound\PurchaseOrderStatus::VerifiedClosed;
        $terminalOverride = in_array($purchaseOrder->status, [
            \App\Enums\Inbound\PurchaseOrderStatus::Rejected,
            \App\Enums\Inbound\PurchaseOrderStatus::Cancelled,
            \App\Enums\Inbound\PurchaseOrderStatus::ReturnedForCorrection,
            \App\Enums\Inbound\PurchaseOrderStatus::AwaitingRequesterCorrection,
            \App\Enums\Inbound\PurchaseOrderStatus::PendingSupervisorValidation,
        ], true);
    @endphp
    <div class="card">
        <div class="card-body overflow-x-auto">
            <div class="flex items-center gap-0 min-w-max">
                @foreach ($this->timelineSteps as $i => $step)
                    @php
                        $done    = $allDone || (!$terminalOverride && $currentIdx !== false && $i < $currentIdx);
                        $current = !$allDone && !$terminalOverride && $currentIdx === $i;
                    @endphp
                    <div class="flex items-center">
                        <div class="flex flex-col items-center gap-1">
                            <div @class([
                                'w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold ring-2',
                                'bg-emerald-500 text-white ring-emerald-500'                              => $done,
                                'bg-simba-600 text-white ring-simba-600'                                  => $current,
                                'bg-white dark:bg-zinc-900 text-slate-400 ring-slate-200 dark:ring-slate-700' => !$done && !$current,
                            ])>
                                @if ($done)
                                    <x-ui.icon name="check" class="w-4 h-4" />
                                @else
                                    {{ $i + 1 }}
                                @endif
                            </div>
                            <span @class([
                                'text-[10px] font-medium whitespace-nowrap',
                                'text-emerald-600 dark:text-emerald-400' => $done,
                                'text-simba-600 dark:text-simba-400'     => $current,
                                'text-slate-400 dark:text-slate-500'     => !$done && !$current,
                            ])>{{ $step['label'] }}</span>
                        </div>
                        @if (!$loop->last)
                            <div @class([
                                'h-0.5 w-8 mx-1 rounded self-start mt-4',
                                'bg-emerald-400' => $allDone || $done,
                                'bg-slate-200 dark:bg-slate-700' => !$allDone && !$done,
                            ])></div>
                        @endif
                    </div>
                @endforeach

                @if ($terminalOverride)
                    <div class="ml-4 badge {{ $purchaseOrder->status->color() }}">
                        <span class="dot"></span>{{ $purchaseOrder->status->label() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left: Details + Lines --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Details Card --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Purchase Order Details</h3>
                    @if ($purchaseOrder->po_file_path)
                        <x-ui.pdf-viewer :url="route('inbound.orders.document.download', $purchaseOrder)"
                                         label="Purchase Order Document"
                                         btnClass="btn btn-ghost btn-sm text-xs gap-1.5 ml-auto">
                            <x-ui.icon name="document" class="w-3.5 h-3.5" /> PO Document
                        </x-ui.pdf-viewer>
                    @endif
                </div>
                <div class="card-body grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-4 text-sm">
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Warehouse</p>
                        <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">{{ $purchaseOrder->warehouse?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Virtual WH</p>
                        <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">{{ $purchaseOrder->virtualWarehouse?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Department</p>
                        <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">{{ $purchaseOrder->department?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Supplier</p>
                        <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">{{ $purchaseOrder->supplierPartner?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Consignee</p>
                        <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">{{ $purchaseOrder->consigneePartner?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Customer PO Ref</p>
                        <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">{{ $purchaseOrder->customer_po_ref ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Currency</p>
                        <p class="mt-0.5 font-mono font-medium text-slate-800 dark:text-slate-200">{{ $purchaseOrder->currency ?? 'TZS' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Package Type</p>
                        <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">{{ $purchaseOrder->package_type?->label() ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Expected Date</p>
                        <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">{{ $purchaseOrder->expected_date?->format('d M Y') ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Requested By</p>
                        <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-200">{{ $purchaseOrder->requestedBy?->name ?? '—' }}</p>
                    </div>
                    @if ($purchaseOrder->notes)
                        <div class="col-span-2 md:col-span-3">
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">Notes</p>
                            <p class="mt-0.5 text-slate-700 dark:text-slate-300">{{ $purchaseOrder->notes }}</p>
                        </div>
                    @endif
                    @if ($purchaseOrder->acceptance_rejection_reason)
                        <div class="col-span-2 md:col-span-3">
                            <p class="text-xs font-medium text-amber-600 dark:text-amber-400 uppercase tracking-wide">Warehouse Feedback</p>
                            <p class="mt-0.5 text-slate-700 dark:text-slate-300">{{ $purchaseOrder->acceptance_rejection_reason }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Lines --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Order Lines ({{ $purchaseOrder->lines->count() }})</h3>
                    @php $total = $purchaseOrder->totalAmount(); @endphp
                    @if ($total > 0)
                        <span class="ml-auto text-sm font-semibold text-slate-700 dark:text-slate-200 font-mono">
                            Total: {{ $purchaseOrder->currency ?? 'TZS' }} {{ number_format((float) $total, 2) }}
                        </span>
                    @endif
                </div>
                <x-ui.table>
                    <thead>
                        <tr>
                            <th class="w-8">#</th>
                            <th>Item</th>
                            <th>Part No.</th>
                            <th>UOM</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-right">Line Total</th>
                            <th>MIC No.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($purchaseOrder->lines as $line)
                            <tr>
                                <td class="tabular-nums text-slate-400 text-xs">{{ $line->line_no }}</td>
                                <td>
                                    <div class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $line->item?->sku }}</div>
                                    <div class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $line->item?->name }}</div>
                                </td>
                                <td class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $line->item?->part_number ?? '—' }}</td>
                                <td class="font-mono text-xs text-slate-600 dark:text-slate-400">{{ $line->uom?->code }}</td>
                                <td class="tabular-nums text-right font-medium">{{ number_format((float) $line->qty, 2) }}</td>
                                <td class="tabular-nums text-right text-slate-600 dark:text-slate-400">
                                    {{ $line->unit_price > 0 ? number_format((float) $line->unit_price, 2) : '—' }}
                                </td>
                                <td class="tabular-nums text-right font-medium">
                                    {{ $line->line_total > 0 ? number_format((float) $line->line_total, 2) : '—' }}
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
                            <x-ui.empty-state colspan="8" message="No lines on this purchase order." />
                        @endforelse
                    </tbody>
                </x-ui.table>
            </div>

            {{-- GRNs --}}
            @if ($purchaseOrder->grns->isNotEmpty())
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Deliveries / GRNs ({{ $purchaseOrder->grns->count() }})</h3>
                    </div>
                    <x-ui.table>
                        <thead>
                            <tr>
                                <th>GRN Number</th>
                                <th>Status</th>
                                <th>Dock</th>
                                <th class="text-right">Items Received</th>
                                <th>Date</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($purchaseOrder->grns as $grn)
                                @php
                                    $totalGood    = $grn->lines->sum(fn($l) => (float)($l->received_good_qty ?? 0));
                                    $totalDamaged = $grn->lines->sum(fn($l) => (float)($l->received_damaged_qty ?? 0));
                                    $totalItems   = $grn->lines->count();
                                @endphp
                                <tr wire:key="grn-{{ $grn->id }}">
                                    <td class="font-mono text-sm font-semibold text-simba-600 dark:text-simba-400">
                                        {{ $grn->number ?? '(Receiving in progress)' }}
                                    </td>
                                    <td>
                                        <span class="badge {{ $grn->status->color() }}">
                                            <span class="dot"></span>{{ $grn->status->label() }}
                                        </span>
                                    </td>
                                    <td class="font-mono text-xs text-slate-500">{{ $grn->receivingDock?->code ?? '—' }}</td>
                                    <td class="text-right">
                                        @if ($totalItems > 0)
                                            <div class="tabular-nums text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                                                {{ number_format($totalGood, 2) }}
                                            </div>
                                            @if ($totalDamaged > 0)
                                                <div class="tabular-nums text-xs text-red-500 dark:text-red-400">
                                                    +{{ number_format($totalDamaged, 2) }} dmg
                                                </div>
                                            @endif
                                            <div class="text-xs text-slate-400">{{ $totalItems }} line(s)</div>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="text-xs text-slate-500">{{ $grn->created_at->format('d M Y H:i') }}</td>
                                    <td class="text-right">
                                        @if ($grn->number === null && $grn->status === \App\Enums\GrnStatus::Draft)
                                            <a href="{{ route('inbound.grn.tally', $grn) }}" wire:navigate class="btn btn-primary btn-sm">
                                                <x-ui.icon name="pencil" class="w-3.5 h-3.5" /> Continue Receiving
                                            </a>
                                        @else
                                            <a href="{{ route('inbound.grn.show', $grn) }}" wire:navigate class="btn btn-secondary btn-sm">
                                                <x-ui.icon name="eye" class="w-3.5 h-3.5" /> View
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                </div>
            @endif

            {{-- Putaway Tasks --}}
            @if ($this->putawayTasks->isNotEmpty())
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Putaway Tasks ({{ $this->putawayTasks->count() }})</h3>
                    </div>
                    <x-ui.table>
                        <thead>
                            <tr>
                                <th>GRN</th>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>From</th>
                                <th>Destination</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->putawayTasks as $task)
                                <tr wire:key="pt-{{ $task->id }}">
                                    <td class="font-mono text-xs text-simba-600 dark:text-simba-400">{{ $task->grn?->number }}</td>
                                    <td>
                                        <div class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $task->item?->sku }}</div>
                                        <div class="text-sm">{{ $task->item?->name }}</div>
                                    </td>
                                    <td class="tabular-nums text-sm">{{ number_format((float) $task->qty, 2) }}</td>
                                    <td class="font-mono text-xs text-slate-500">{{ $task->fromLocation?->code ?? '—' }}</td>
                                    <td class="font-mono text-xs">
                                        @if ($task->actualLocation)
                                            <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ $task->actualLocation->code }}</span>
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
                                                <button wire:click="openExecute({{ $task->id }})" class="btn btn-primary btn-sm">
                                                    <x-ui.icon name="archive" class="w-3.5 h-3.5" /> Put Away
                                                </button>
                                            @endcan
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                </div>
            @endif

        </div>

        {{-- Right: Sidebar --}}
        <div class="space-y-6">

            {{-- Documents card — visible once approved --}}
            @php
                $approvedStatuses = [
                    \App\Enums\Inbound\PurchaseOrderStatus::ApprovedPendingAcceptance,
                    \App\Enums\Inbound\PurchaseOrderStatus::AcceptedPendingAssignment,
                    \App\Enums\Inbound\PurchaseOrderStatus::AssignedPendingReceiving,
                    \App\Enums\Inbound\PurchaseOrderStatus::ReceivingInProgress,
                    \App\Enums\Inbound\PurchaseOrderStatus::PartiallyReceived,
                    \App\Enums\Inbound\PurchaseOrderStatus::PendingPutaway,
                    \App\Enums\Inbound\PurchaseOrderStatus::PutawayInProgress,
                    \App\Enums\Inbound\PurchaseOrderStatus::PendingSupervisorValidation,
                    \App\Enums\Inbound\PurchaseOrderStatus::CompletedPendingVerification,
                    \App\Enums\Inbound\PurchaseOrderStatus::IssueRaised,
                    \App\Enums\Inbound\PurchaseOrderStatus::IssueResolved,
                    \App\Enums\Inbound\PurchaseOrderStatus::VerifiedClosed,
                ];
            @endphp
            @if (in_array($purchaseOrder->status, $approvedStatuses, true))
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Documents</h3>
                    </div>
                    <div class="card-body space-y-3">
                        {{-- Attached PO document --}}
                        <div class="flex items-center justify-between gap-3 p-3 rounded-lg bg-slate-50 dark:bg-white/[0.03] border border-slate-100 dark:border-white/[0.06]">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="shrink-0 w-8 h-8 rounded-md bg-simba-50 dark:bg-simba-900/30 flex items-center justify-center">
                                    <x-ui.icon name="document-text" class="w-4 h-4 text-simba-600 dark:text-simba-400" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-slate-700 dark:text-slate-200 truncate">Purchase Order</p>
                                    <p class="text-[10px] text-slate-400 font-mono">{{ $purchaseOrder->number }}</p>
                                </div>
                            </div>
                            @if ($purchaseOrder->po_file_path)
                                <x-ui.pdf-viewer :url="route('inbound.orders.document.download', $purchaseOrder)"
                                                 label="Purchase Order  {{ $purchaseOrder->number }}"
                                                 btnClass="btn btn-ghost btn-sm gap-1 text-xs shrink-0">
                                    <x-ui.icon name="eye" class="w-3.5 h-3.5" /> View
                                </x-ui.pdf-viewer>
                            @else
                                <span class="text-[10px] text-slate-400 italic shrink-0">No document attached</span>
                            @endif
                        </div>

                        {{-- Approval stamp --}}
                        @if ($purchaseOrder->approved_at)
                            <p class="text-[10px] text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                <x-ui.icon name="check-badge" class="w-3.5 h-3.5" />
                                Approved {{ $purchaseOrder->approved_at->format('d M Y H:i') }}
                            </p>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Audit Trail --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Audit Trail</h3>
                </div>

                {{-- Timeline --}}
                @php
                    // ── Approval descriptions that are fully captured by purchase_order_approvals.
                    // Any activity_log entry with one of these descriptions is a duplicate and
                    // must be suppressed. This covers both the current code and legacy descriptions
                    // that older versions of the workflow wrote.
                    $suppressedActivityDescriptions = [
                        'created', 'updated',
                        'Purchase order created',
                        // current + legacy approval writes
                        'Approved by dept head',
                        'Approved',
                        'Level 1 approved',
                        'Level 2 approved',
                        'Level 3 approved',
                        // current + legacy return/rejection writes
                        'Returned for correction',
                        'Purchase order rejected',
                        // forward-to-requester (was a duplicate source before the Forwarded action was added)
                        'Return forwarded to requester by dept head',
                        'Forwarded to requester',
                    ];

                    // ── Deduplicate purchase_order_approvals:
                    // Old multi-level code could write 2+ records for the same action click.
                    // Group by (actor, action, level, 5-second timestamp bucket) and keep the
                    // richest record — prefer the entry that has an approval_chain_id set, because
                    // it carries the stage name for the rich label.
                    $deduplicatedApprovals = $purchaseOrder->approvals
                        ->groupBy(fn ($ap) => implode('|', [
                            $ap->user_id,
                            $ap->action?->value ?? (string) $ap->action,
                            (int) $ap->level,
                            (int) floor($ap->created_at->timestamp / 5),
                        ]))
                        ->map(fn ($group) =>
                            $group->sortByDesc(fn ($ap) => $ap->approval_chain_id !== null ? 1 : 0)->first()
                        )
                        ->values();

                    $auditEvents = collect()
                        ->merge(
                            $deduplicatedApprovals->map(fn ($ap) => [
                                'type' => 'approval',
                                'time' => $ap->created_at,
                                'data' => $ap,
                            ])
                        )
                        ->merge(
                            $purchaseOrder->assignments->map(fn ($a) => [
                                'type' => 'assignment',
                                'time' => $a->created_at,
                                'data' => $a,
                            ])
                        )
                        ->merge(
                            $purchaseOrder->activities
                                ->filter(fn ($act) =>
                                    ! in_array($act->description, $suppressedActivityDescriptions, true)
                                    && ! preg_match('/^Level \d+ approved$/i', $act->description)
                                    && ! str_starts_with($act->description, 'Supervisor assigned')
                                    && ! str_starts_with($act->description, 'Clerk assigned')
                                )
                                ->map(fn ($act) => [
                                    'type' => 'activity',
                                    'time' => $act->created_at,
                                    'data' => $act,
                                ])
                        )
                        ->push([
                            'type' => 'created',
                            'time' => $purchaseOrder->created_at,
                            'data' => $purchaseOrder,
                        ])
                        ->sortByDesc('time')
                        ->values();
                @endphp

                @if ($auditEvents->isEmpty())
                    <p class="px-4 py-4 text-sm text-slate-400 dark:text-slate-500">No activity yet.</p>
                @else
                    <div class="px-4 py-4 sm:px-5">
                        <ol>
                            @foreach ($auditEvents as $event)
                                @php
                                    $d    = $event['data'];
                                    $time = $event['time'];

                                    $label = ''; $actor = ''; $meta = ''; $note = null;
                                    $dotColor = 'bg-slate-400'; $badgeText = ''; $badgeClass = 'badge-neutral';

                                    // badge-blue and badge-cyan alias to the brand colour in this app;
                                    // use inline utilities instead to get actual blue / cyan.
                                    $blueB = 'bg-blue-500/15 text-blue-700 ring-1 ring-inset ring-blue-600/30 dark:text-blue-400';
                                    $cyanB = 'bg-cyan-500/15 text-cyan-700 ring-1 ring-inset ring-cyan-600/30 dark:text-cyan-400';

                                    if ($event['type'] === 'approval') {
                                        $actionValue = $d->action?->value ?? $d->action;
                                        [$dotColor, $badgeClass] = match ($actionValue) {
                                            'approved'  => ['bg-emerald-500', 'badge-emerald'],
                                            'rejected'  => ['bg-red-500',     'badge-red'],
                                            'returned'  => ['bg-orange-500',  'badge-orange'],
                                            'forwarded' => ['bg-amber-500',   'badge-amber'],
                                            default     => ['bg-slate-400',   'badge-neutral'],
                                        };
                                        $actionLabel = $d->action?->label() ?? ucfirst($actionValue);
                                        $badgeText   = $actionLabel;
                                        // Rich label: append stage descriptor for chain-linked records.
                                        $stageName = $d->approvalChain ? 'Department Approval' : null;
                                        $label     = $stageName ? "{$actionLabel} — {$stageName}" : $actionLabel;
                                        $actor     = $d->user?->name ?? '—';
                                        $meta      = 'Level ' . $d->level . ' · ' . $time->format('d M Y H:i');
                                        $note      = $d->comment ?: null;

                                    } elseif ($event['type'] === 'assignment') {
                                        $roleLabel = match ($d->type) {
                                            'supervisor'               => 'Supervisor',
                                            'receiving_clerk', 'clerk' => 'Receiving Clerk',
                                            default                    => ucfirst(str_replace('_', ' ', $d->type ?? '')),
                                        };
                                        $dotColor  = 'bg-violet-500';
                                        $badgeText = 'Assigned';
                                        $badgeClass = 'badge-neutral';
                                        $label     = $roleLabel . ' Assigned';
                                        $actor     = $d->previousUser
                                            ? ($d->previousUser->name . ' → ' . ($d->assignedUser?->name ?? '—'))
                                            : ($d->assignedUser?->name ?? '—');
                                        $meta      = ($d->assignedByUser ? 'by ' . $d->assignedByUser->name . ' · ' : '') . $time->format('d M Y H:i');
                                        $note      = $d->notes ?: null;

                                    } elseif ($event['type'] === 'activity') {
                                        $desc = $d->description;
                                        [$dotColor, $badgeText, $badgeClass] = match (true) {
                                            $desc === 'Accepted by warehouse manager'      => ['bg-teal-500',    'Accepted',     'badge-success'],
                                            $desc === 'Submitted for approval'             => ['bg-blue-500',    'Submitted',    $blueB],
                                            str_starts_with($desc, 'Cancelled')            => ['bg-red-600',     'Cancelled',    'badge-danger'],
                                            str_starts_with($desc, 'Closed short')         => ['bg-amber-500',   'Closed Short', 'badge-amber'],
                                            $desc === 'Order closed by warehouse manager'  => ['bg-emerald-600', 'Closed',       'badge-emerald'],
                                            $desc === 'Verified and closed by approver'    => ['bg-green-600',   'Verified',     'badge-success'],
                                            $desc === 'Receiving started'                  => ['bg-cyan-500',    'Receiving',    $cyanB],
                                            str_starts_with($desc, 'Putaway')              => ['bg-indigo-500',  'Putaway',      'badge-neutral'],
                                            str_starts_with($desc, 'Sent back')            => ['bg-orange-500',  'Sent Back',    'badge-orange'],
                                            $desc === 'Supervisor validation complete'     => ['bg-green-500',   'Validated',    'badge-success'],
                                            $desc === 'All issues resolved'                => ['bg-emerald-500', 'Resolved',     'badge-emerald'],
                                            $desc === 'Issue raised by approver'           => ['bg-rose-500',    'Issue',        'badge-danger'],
                                            default                                         => ['bg-slate-400',   'Activity',     'badge-neutral'],
                                        };
                                        $label = $desc;
                                        $actor = $d->causer?->name ?? '—';
                                        $meta  = $time->format('d M Y H:i');
                                        $note  = $d->properties['comment'] ?? $d->properties['reason'] ?? null;

                                    } elseif ($event['type'] === 'created') {
                                        $dotColor  = 'bg-slate-400';
                                        $badgeText = 'Created';
                                        $badgeClass = 'badge-neutral';
                                        $label     = 'Order Created';
                                        $actor     = $d->requestedBy?->name ?? '—';
                                        $meta      = $time->format('d M Y H:i');
                                    }
                                @endphp

                                <li class="flex gap-3">
                                    {{-- Gutter: fixed-width column for dot + connector --}}
                                    <div class="flex w-7 shrink-0 flex-col items-center">
                                        <div class="relative z-10 mt-[3px] h-3.5 w-3.5 shrink-0 rounded-full {{ $dotColor }} ring-2 ring-white dark:ring-slate-900"></div>
                                        @if (! $loop->last)
                                            <div class="mt-1.5 w-0.5 flex-1 bg-slate-200 dark:bg-slate-700"></div>
                                        @endif
                                    </div>

                                    {{-- Content --}}
                                    <div class="min-w-0 flex-1 {{ $loop->last ? 'pb-1' : 'pb-5' }}">
                                        <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
                                            <div class="min-w-0 flex-1">
                                                <p class="break-words text-sm font-medium text-slate-800 dark:text-slate-200">{{ $label }}</p>
                                                <p class="break-words text-xs text-slate-500 dark:text-slate-400">{{ $actor }} · {{ $meta }}</p>
                                                @if ($note)
                                                    <div class="mt-1.5 border-l-2 border-slate-300 pl-2.5 dark:border-slate-600">
                                                        <p class="break-words text-xs italic text-slate-500 dark:text-slate-400">{{ trim($note) }}</p>
                                                    </div>
                                                @endif
                                            </div>
                                            <span class="inline-flex shrink-0 items-center whitespace-nowrap rounded-full px-2 py-0.5 text-[12px] font-medium leading-[16px] {{ $badgeClass }} mt-0.5">{{ $badgeText }}</span>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif
            </div>

            {{-- Issues --}}
            @if ($purchaseOrder->issues->isNotEmpty())
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Issues</h3>
                    </div>
                    <div class="divide-y divide-slate-100 dark:divide-white/[0.06]">
                        @foreach ($purchaseOrder->issues as $issue)
                            <div class="px-5 py-3 text-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-medium text-slate-700 dark:text-slate-300">{{ $issue->raisedBy?->name }}</span>
                                    <span class="badge {{ $issue->status?->value === 'open' ? 'badge-red' : 'badge-green' }}">
                                        <span class="dot"></span>{{ ucfirst($issue->status?->value ?? '—') }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">{{ $issue->description }}</p>
                                <p class="text-xs text-slate-400 mt-0.5">{{ $issue->created_at->format('d M Y H:i') }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>

    {{-- ─── Modals ────────────────────────────────────────────────────────────── --}}

    {{-- Send Back --}}
    @if ($showReturnModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm p-4"
             x-data wire:click.self="closeReturnModal">
            <div class="card w-full max-w-md p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-slate-900 dark:text-white">Send Back</h3>
                    <button wire:click="closeReturnModal" class="icon-btn"><x-ui.icon name="x" class="w-5 h-5" /></button>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    This will send the order back to the previous approver with your remark.
                    If there is no previous level, it will be returned to the requester.
                </p>
                <div>
                    <label class="label">Remark <span class="text-red-500">*</span></label>
                    <textarea wire:model="returnComment" rows="3" class="input resize-none"
                              placeholder="Explain why you are sending this back…"></textarea>
                    @error('returnComment') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button wire:click="closeReturnModal" class="btn btn-ghost">Cancel</button>
                    <button wire:click="returnToPreviousLevel" wire:loading.attr="disabled" class="btn btn-secondary">
                        <span wire:loading.remove wire:target="returnToPreviousLevel">Send Back</span>
                        <span wire:loading wire:target="returnToPreviousLevel">Sending…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Reject (approval) --}}
    @if ($showRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm p-4"
             x-data wire:click.self="closeRejectModal">
            <div class="card w-full max-w-md p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-ui.icon name="x-circle" class="w-5 h-5 text-red-500" />
                        <h3 class="font-bold text-slate-900 dark:text-white">Reject Purchase Order</h3>
                    </div>
                    <button wire:click="closeRejectModal" class="icon-btn"><x-ui.icon name="x" class="w-5 h-5" /></button>
                </div>
                <div>
                    <label class="label">Rejection Reason <span class="text-red-500">*</span></label>
                    <textarea wire:model="rejectReason" rows="3" class="input resize-none"
                              placeholder="Explain why this PO is being rejected…"></textarea>
                    @error('rejectReason') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button wire:click="closeRejectModal" class="btn btn-ghost">Cancel</button>
                    <button wire:click="rejectApproval" wire:loading.attr="disabled" class="btn btn-primary !bg-red-600 hover:!bg-red-700">
                        <span wire:loading.remove wire:target="rejectApproval">Reject</span>
                        <span wire:loading wire:target="rejectApproval">Rejecting…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Warehouse reject / send back --}}
    @if ($showWmRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm p-4"
             x-data wire:click.self="closeWmRejectModal">
            <div class="card w-full max-w-md p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-slate-900 dark:text-white">Send Back to Department</h3>
                    <button wire:click="closeWmRejectModal" class="icon-btn"><x-ui.icon name="x" class="w-5 h-5" /></button>
                </div>
                <div>
                    <label class="label">Reason <span class="text-red-500">*</span></label>
                    <textarea wire:model="wmRejectReason" rows="3" class="input resize-none"
                              placeholder="Explain the issue…"></textarea>
                    @error('wmRejectReason') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button wire:click="closeWmRejectModal" class="btn btn-ghost">Cancel</button>
                    <button wire:click="rejectByWarehouse" wire:loading.attr="disabled" class="btn btn-secondary text-amber-600">
                        <span wire:loading.remove wire:target="rejectByWarehouse">Send Back</span>
                        <span wire:loading wire:target="rejectByWarehouse">Sending…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- HOD: forward return to requester --}}
    @if ($showForwardModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm p-4"
             x-data wire:click.self="closeForwardModal">
            <div class="card w-full max-w-md p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-slate-900 dark:text-white">Send Back to Requester</h3>
                    <button wire:click="closeForwardModal" class="icon-btn"><x-ui.icon name="x" class="w-5 h-5" /></button>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Notify the requester of what needs to be corrected before resubmitting.
                </p>
                <div>
                    <label class="label">Your Note <span class="text-red-500">*</span></label>
                    <textarea wire:model="forwardComment" rows="3" class="input resize-none"
                              placeholder="Explain what the requester needs to fix…"></textarea>
                    @error('forwardComment') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button wire:click="closeForwardModal" class="btn btn-ghost">Cancel</button>
                    <button wire:click="forwardToRequester" wire:loading.attr="disabled" class="btn btn-secondary">
                        <span wire:loading.remove wire:target="forwardToRequester">Send Back</span>
                        <span wire:loading wire:target="forwardToRequester">Sending…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Assign supervisor / clerk --}}
    @if ($showAssignModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm p-4"
             x-data wire:click.self="closeAssignModal">
            <div class="card w-full max-w-sm p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-slate-900 dark:text-white">
                        Assign {{ $assignType === 'supervisor' ? 'Supervisor' : 'Clerk' }}
                    </h3>
                    <button wire:click="closeAssignModal" class="icon-btn"><x-ui.icon name="x" class="w-5 h-5" /></button>
                </div>
                <div>
                    @php $userList = $assignType === 'supervisor' ? $this->supervisors : $this->clerks; @endphp
                    <label class="label">
                        Select {{ $assignType === 'supervisor' ? 'Supervisor' : 'Clerk' }}
                        <span class="text-red-500">*</span>
                        <span class="text-slate-400 font-normal ml-1">({{ $userList->count() }} available)</span>
                    </label>
                    @if ($userList->isEmpty())
                        <p class="text-sm text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 rounded-lg px-3 py-2">
                            No active {{ $assignType === 'supervisor' ? 'warehouse supervisors' : 'storekeepers' }} found.
                            Create a user with the <strong>{{ $assignType === 'supervisor' ? 'warehouse-supervisor' : 'storekeeper' }}</strong> role first.
                        </p>
                    @else
                        <select wire:model="assignUserId" class="input">
                            <option value="">Choose…</option>
                            @foreach ($userList as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} — {{ $u->email }}</option>
                            @endforeach
                        </select>
                    @endif
                    @error('assignUserId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button wire:click="closeAssignModal" class="btn btn-ghost">Cancel</button>
                    <button wire:click="saveAssignment" wire:loading.attr="disabled" class="btn btn-primary">
                        <span wire:loading.remove wire:target="saveAssignment">Assign</span>
                        <span wire:loading wire:target="saveAssignment">Assigning…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Start Receiving modal --}}
    @if ($showReceiveModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm p-4"
             x-data wire:click.self="closeReceiveModal">
            <div class="card w-full max-w-lg p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white">Start Receiving</h3>
                    </div>
                    <button wire:click="closeReceiveModal" class="icon-btn"><x-ui.icon name="x" class="w-5 h-5" /></button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="label">Receiving Dock <span class="text-red-500">*</span></label>
                        <select wire:model="receiveDockId" class="input">
                            <option value="">Select dock…</option>
                            @foreach ($this->receivingDocks as $dock)
                                <option value="{{ $dock->id }}">{{ $dock->code }}</option>
                            @endforeach
                        </select>
                        @error('receiveDockId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Container Details — only when PO package type = Container --}}
                    @if ($purchaseOrder->package_type?->value === 'container')
                        <div class="md:col-span-2 pt-1">
                            <div class="flex items-center gap-2 mb-3">
                                <div class="h-px flex-1 bg-slate-200 dark:bg-slate-700"></div>
                                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Container Details</span>
                                <div class="h-px flex-1 bg-slate-200 dark:bg-slate-700"></div>
                            </div>
                        </div>

                        <div>
                            <label class="label">Container No. <span class="text-red-500">*</span></label>
                            <input wire:model="receiveContainerNo" type="text"
                                   placeholder="e.g. TCKU1234567"
                                   class="input font-mono uppercase" />
                            @error('receiveContainerNo') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Size / Type <span class="text-red-500">*</span></label>
                            <select wire:model="receiveContainerSizeType" class="input">
                                <option value="">Select…</option>
                                @foreach (\App\Enums\ContainerSizeType::cases() as $st)
                                    <option value="{{ $st->value }}">{{ $st->label() }}</option>
                                @endforeach
                            </select>
                            @error('receiveContainerSizeType') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Seal No. <span class="text-red-500">*</span></label>
                            <input wire:model="receiveContainerSealNo" type="text"
                                   placeholder="Seal number"
                                   class="input font-mono" />
                            @error('receiveContainerSealNo') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Shipping Line</label>
                            <select wire:model="receiveContainerShippingLineId" class="input">
                                <option value="">Select shipping line…</option>
                                @foreach ($this->shippingLines as $sl)
                                    <option value="{{ $sl->id }}">{{ $sl->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Vehicle & Driver Details --}}
                    <div class="md:col-span-2 pt-1">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="h-px flex-1 bg-slate-200 dark:bg-slate-700"></div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Vehicle & Driver</span>
                            <div class="h-px flex-1 bg-slate-200 dark:bg-slate-700"></div>
                        </div>
                    </div>

                    <div>
                        <label class="label">Vehicle Type</label>
                        <select wire:model="receiveVehicleType" class="input">
                            <option value="">Not specified</option>
                            @foreach ($this->vehicleTypes as $vt)
                                <option value="{{ $vt }}">{{ $vt }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="label">Vehicle Plate</label>
                        <input wire:model="receiveVehiclePlate" type="text" placeholder="e.g. T123ABC" class="input font-mono" />
                    </div>

                    <div>
                        <label class="label">Driver Name</label>
                        <input wire:model="receiveDriverName" type="text" class="input" />
                    </div>

                    <div>
                        <label class="label">Driver Phone</label>
                        <input wire:model="receiveDriverPhone" type="text" class="input" />
                    </div>

                    <div>
                        <label class="label">Transporter</label>
                        <input wire:model="receiveTransporter" type="text" class="input" />
                    </div>

                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button wire:click="closeReceiveModal" class="btn btn-ghost">Cancel</button>
                    <button wire:click="startReceiving" wire:loading.attr="disabled" class="btn btn-primary">
                        <span wire:loading.remove wire:target="startReceiving">
                            <x-ui.icon name="clipboard" class="w-4 h-4" /> Create Receiving Tally
                        </span>
                        <span wire:loading wire:target="startReceiving">Creating…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Send back (supervisor) --}}
    @if ($showSendBackModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm p-4"
             x-data wire:click.self="closeSendBackModal">
            <div class="card w-full max-w-md p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-slate-900 dark:text-white">Send Back to Clerk</h3>
                    <button wire:click="closeSendBackModal" class="icon-btn"><x-ui.icon name="x" class="w-5 h-5" /></button>
                </div>
                <div>
                    <label class="label">Comment <span class="text-red-500">*</span></label>
                    <textarea wire:model="sendBackComment" rows="3" class="input resize-none"
                              placeholder="What needs to be corrected…"></textarea>
                    @error('sendBackComment') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button wire:click="closeSendBackModal" class="btn btn-ghost">Cancel</button>
                    <button wire:click="sendBackToClerk" wire:loading.attr="disabled" class="btn btn-secondary">
                        <span wire:loading.remove wire:target="sendBackToClerk">Send Back</span>
                        <span wire:loading wire:target="sendBackToClerk">Sending…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Raise Issue --}}
    @if ($showIssueModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm p-4"
             x-data wire:click.self="closeIssueModal">
            <div class="card w-full max-w-md p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-ui.icon name="exclamation-triangle" class="w-5 h-5 text-amber-500" />
                        <h3 class="font-bold text-slate-900 dark:text-white">Raise Issue</h3>
                    </div>
                    <button wire:click="closeIssueModal" class="icon-btn"><x-ui.icon name="x" class="w-5 h-5" /></button>
                </div>
                <div>
                    <label class="label">Description <span class="text-red-500">*</span></label>
                    <textarea wire:model="issueDescription" rows="3" class="input resize-none"
                              placeholder="Describe the issue with this delivery…"></textarea>
                    @error('issueDescription') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button wire:click="closeIssueModal" class="btn btn-ghost">Cancel</button>
                    <button wire:click="raiseIssue" wire:loading.attr="disabled" class="btn btn-secondary text-amber-600">
                        <span wire:loading.remove wire:target="raiseIssue">Raise Issue</span>
                        <span wire:loading wire:target="raiseIssue">Raising…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Resolve Issue --}}
    @if ($showResolveModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm p-4"
             x-data wire:click.self="closeResolveModal">
            <div class="card w-full max-w-md p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-slate-900 dark:text-white">Resolve Issue</h3>
                    <button wire:click="closeResolveModal" class="icon-btn"><x-ui.icon name="x" class="w-5 h-5" /></button>
                </div>
                <div>
                    <label class="label">Resolution Notes <span class="text-red-500">*</span></label>
                    <textarea wire:model="resolveNotes" rows="3" class="input resize-none"
                              placeholder="Explain how the issue was resolved…"></textarea>
                    @error('resolveNotes') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button wire:click="closeResolveModal" class="btn btn-ghost">Cancel</button>
                    <button wire:click="resolveIssue" wire:loading.attr="disabled" class="btn btn-primary">
                        <span wire:loading.remove wire:target="resolveIssue">Resolve</span>
                        <span wire:loading wire:target="resolveIssue">Resolving…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Close Short --}}
    @if ($showCloseShortModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm p-4"
             x-data wire:click.self="closeCloseShortModal">
            <div class="card w-full max-w-md p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-slate-900 dark:text-white">Close Partial Delivery</h3>
                    <button wire:click="closeCloseShortModal" class="icon-btn"><x-ui.icon name="x" class="w-5 h-5" /></button>
                </div>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    This will close the PO as a partial delivery — not all lines have been fully received. A reason is required.
                </p>
                <div>
                    <label class="label">Reason <span class="text-red-500">*</span></label>
                    <textarea wire:model="closeShortReason" rows="2" class="input resize-none"
                              placeholder="e.g. Supplier confirmed no further deliveries…"></textarea>
                    @error('closeShortReason') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button wire:click="closeCloseShortModal" class="btn btn-ghost">Cancel</button>
                    <button wire:click="closeShort" wire:loading.attr="disabled" class="btn btn-secondary text-amber-600">
                        <span wire:loading.remove wire:target="closeShort">Confirm Partial Delivery</span>
                        <span wire:loading wire:target="closeShort">Closing…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @include('livewire.partials.putaway-execute-modal')
</div>
