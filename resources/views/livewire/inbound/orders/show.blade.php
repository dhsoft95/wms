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

            {{-- Returned for correction --}}
            @if ($purchaseOrder->status === \App\Enums\Inbound\PurchaseOrderStatus::ReturnedForCorrection)
                @php $isHod = auth()->id() === $purchaseOrder->department?->head_user_id; @endphp
                @if ($isHod || auth()->user()->hasRole('system-admin'))
                    <button wire:click="openForwardModal" class="btn btn-secondary">
                        <x-ui.icon name="arrow-uturn-left" class="w-4 h-4" /> Send Back to Requester
                    </button>
                @endif
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

            {{-- Pending supervisor validation --}}
            @if ($purchaseOrder->status === \App\Enums\Inbound\PurchaseOrderStatus::PendingSupervisorValidation)
                @can('validate', $purchaseOrder)
                    <button wire:click="openSendBackModal" class="btn btn-secondary">
                        <x-ui.icon name="arrow-uturn-left" class="w-4 h-4" /> Send Back
                    </button>
                    <button wire:click="validateReceiving" wire:loading.attr="disabled" class="btn btn-primary">
                        <x-ui.icon name="check-badge" class="w-4 h-4" /> Validate
                    </button>
                @endcan
                @if (auth()->user()->hasAnyRole(['warehouse-manager', 'system-admin']))
                    <button wire:click="closeOrder"
                            wire:confirm="Close this purchase order now? This skips validation and marks it as closed."
                            class="btn btn-secondary">
                        <x-ui.icon name="lock-closed" class="w-4 h-4" /> Close Order
                    </button>
                @endif
            @endif

            {{-- Completed / issue raised / issue resolved — approver verifies --}}
            @if (in_array($purchaseOrder->status, [
                    \App\Enums\Inbound\PurchaseOrderStatus::CompletedPendingVerification,
                    \App\Enums\Inbound\PurchaseOrderStatus::IssueRaised,
                    \App\Enums\Inbound\PurchaseOrderStatus::IssueResolved,
                ], true))
                @can('verify', $purchaseOrder)
                    @if ($purchaseOrder->status !== \App\Enums\Inbound\PurchaseOrderStatus::IssueRaised)
                        <button wire:click="openIssueModal" class="btn btn-secondary text-amber-600 dark:text-amber-400">
                            <x-ui.icon name="exclamation-triangle" class="w-4 h-4" /> Raise Issue
                        </button>
                        <button wire:click="verifyAndClose" wire:loading.attr="disabled" class="btn btn-primary">
                            <x-ui.icon name="check-badge" class="w-4 h-4" /> Verify & Close
                        </button>
                    @endif
                @endcan
                @can('resolveIssue', $purchaseOrder)
                    @if ($purchaseOrder->status === \App\Enums\Inbound\PurchaseOrderStatus::IssueRaised)
                        @foreach ($purchaseOrder->issues->where('status.value', 'open') as $openIssue)
                            <button wire:click="openResolveModal({{ $openIssue->id }})" class="btn btn-primary">
                                <x-ui.icon name="check" class="w-4 h-4" /> Resolve Issue
                            </button>
                        @endforeach
                    @endif
                @endcan
                @if (auth()->user()->hasAnyRole(['warehouse-manager', 'system-admin']))
                    <button wire:click="closeOrder"
                            wire:confirm="Close this purchase order now?"
                            class="btn btn-secondary">
                        <x-ui.icon name="lock-closed" class="w-4 h-4" /> Close Order
                    </button>
                @endif
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
                            </tr>
                        @empty
                            <x-ui.empty-state colspan="7" message="No lines on this purchase order." />
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
                                                 label="Purchase Order — {{ $purchaseOrder->number }}"
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

            {{-- Assignment card --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Assignment</h3>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-white/[0.06]">
                    @foreach ([
                        ['person' => $purchaseOrder->assignedSupervisor, 'role' => 'Supervisor'],
                        ['person' => $purchaseOrder->assignedClerk,       'role' => 'Receiving Clerk'],
                    ] as $row)
                        <div class="px-5 py-3 flex items-center gap-3">
                            @if ($row['person'])
                                <div class="shrink-0 w-8 h-8 rounded-full bg-simba-100 dark:bg-simba-900/40 flex items-center justify-center text-xs font-bold text-simba-700 dark:text-simba-300 uppercase">
                                    {{ mb_substr($row['person']->name, 0, 1) }}
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $row['person']->name }}</p>
                                    <p class="text-xs text-slate-400 dark:text-slate-500">{{ $row['role'] }}</p>
                                </div>
                            @else
                                <div class="shrink-0 w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                                    <x-ui.icon name="user" class="w-4 h-4 text-slate-400" />
                                </div>
                                <div>
                                    <p class="text-sm text-slate-400 dark:text-slate-500">Unassigned</p>
                                    <p class="text-xs text-slate-400 dark:text-slate-500">{{ $row['role'] }}</p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Approval history --}}
            @if ($purchaseOrder->approvals->isNotEmpty())
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Approval History</h3>
                    </div>
                    <div class="divide-y divide-slate-100 dark:divide-white/[0.06]">
                        @foreach ($purchaseOrder->approvals as $approval)
                            <div class="px-5 py-3 text-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ $approval->user?->name ?? '—' }}</span>
                                    @php
                                        $actionColor = match($approval->action?->value ?? $approval->action) {
                                            'approved' => 'badge-green',
                                            'rejected' => 'badge-red',
                                            'returned' => 'badge-amber',
                                            default    => 'badge-slate',
                                        };
                                    @endphp
                                    <span class="badge {{ $actionColor }} capitalize">{{ $approval->action?->value ?? $approval->action }}</span>
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Level {{ $approval->level }} · {{ $approval->created_at->format('d M Y H:i') }}
                                </div>
                                @if ($approval->comment)
                                    <p class="text-xs text-slate-600 dark:text-slate-400 mt-1 italic">{{ $approval->comment }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

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
                            <label class="label">Container No.</label>
                            <input wire:model="receiveContainerNo" type="text"
                                   placeholder="e.g. TCKU1234567"
                                   class="input font-mono uppercase" />
                            @error('receiveContainerNo') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Size / Type</label>
                            <select wire:model="receiveContainerSizeType" class="input">
                                <option value="">Select…</option>
                                @foreach (\App\Enums\ContainerSizeType::cases() as $st)
                                    <option value="{{ $st->value }}">{{ $st->label() }}</option>
                                @endforeach
                            </select>
                            @error('receiveContainerSizeType') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Seal No.</label>
                            <input wire:model="receiveContainerSealNo" type="text"
                                   placeholder="Seal number"
                                   class="input font-mono" />
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

</div>
