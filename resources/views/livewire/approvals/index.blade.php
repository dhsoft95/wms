<div class="space-y-6">
    {{-- Page header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Approvals Inbox</h2>
            <p class="page-sub">Documents awaiting your review and approval.</p>
        </div>
        @if ($this->totalPendingCount > 0)
            <span class="badge badge-simba !text-sm !px-3 !py-1">{{ $this->totalPendingCount }} pending</span>
        @endif
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 border-b border-slate-200 dark:border-white/[0.06]">
        <button wire:click="setTab('pending')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors
                       {{ $tab === 'pending'
                           ? 'border-simba-500 text-simba-600 dark:text-simba-400'
                           : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }}">
            Pending Approval
            @if ($this->totalPendingCount > 0 && $tab !== 'pending')
                <span class="ml-1.5 badge badge-amber !text-xs !px-1.5 !py-0.5">{{ $this->totalPendingCount }}</span>
            @endif
        </button>
        <button wire:click="setTab('all')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors
                       {{ $tab === 'all'
                           ? 'border-simba-500 text-simba-600 dark:text-simba-400'
                           : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }}">
            All
        </button>
    </div>

    {{-- Purchase Orders section --}}
    @if ($this->pendingPoApprovals->isNotEmpty())
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Purchase Orders</h3>
                <span class="badge badge-amber ml-2">{{ $this->pendingPoApprovals->count() }}</span>
            </div>
            <x-ui.table>
                <thead>
                    <tr>
                        <th>PO Number</th>
                        <th>Status</th>
                        <th>Department</th>
                        <th>Warehouse</th>
                        <th>Requested By</th>
                        <th>Submitted</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->pendingPoApprovals as $po)
                        @php
                            $lastAction = $po->approvals->first();
                            $isReturned = in_array($po->status, [
                                \App\Enums\Inbound\PurchaseOrderStatus::ReturnedForCorrection,
                                \App\Enums\Inbound\PurchaseOrderStatus::AwaitingRequesterCorrection,
                            ], true);
                        @endphp
                        <tr wire:key="po-{{ $po->id }}">
                            <td>
                                <a href="{{ route('inbound.orders.show', $po) }}" wire:navigate
                                   class="mono font-semibold text-simba-600 dark:text-simba-400 hover:underline">
                                    {{ $po->number }}
                                </a>
                            </td>
                            <td>
                                <span class="badge {{ $po->status->color() }}">{{ $po->status->label() }}</span>
                                @if ($isReturned && $lastAction?->comment)
                                    <p class="text-xs text-slate-500 mt-0.5 max-w-[180px] truncate" title="{{ $lastAction->comment }}">
                                        {{ $lastAction->comment }}
                                    </p>
                                @endif
                            </td>
                            <td class="text-sm text-slate-600 dark:text-slate-400">{{ $po->department?->name ?? '—' }}</td>
                            <td class="text-sm text-slate-600 dark:text-slate-400">{{ $po->warehouse?->name ?? '—' }}</td>
                            <td class="text-sm text-slate-600 dark:text-slate-400">{{ $po->requestedBy?->name ?? '—' }}</td>
                            <td class="text-xs text-slate-500">{{ $po->submitted_at?->format('d M Y') ?? '—' }}</td>
                            <td class="text-right">
                                <a href="{{ route('inbound.orders.show', $po) }}" wire:navigate class="btn btn-secondary btn-sm">
                                    <x-ui.icon name="eye" class="w-3.5 h-3.5" /> View
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </div>
    @endif

    {{-- Disposal Requests section --}}
    @if ($this->pendingDisposalApprovals->isNotEmpty())
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Disposal Requests</h3>
                <span class="badge badge-amber ml-2">{{ $this->pendingDisposalApprovals->count() }}</span>
            </div>
            <x-ui.table>
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Status</th>
                        <th>Department</th>
                        <th>VW</th>
                        <th>Method</th>
                        <th>Lines</th>
                        <th>Submitted</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->pendingDisposalApprovals as $req)
                    <tr wire:key="disp-{{ $req->id }}">
                        <td>
                            <a href="{{ route('inventory.disposals.show', $req) }}" wire:navigate
                               class="mono font-semibold text-simba-600 hover:underline">{{ $req->number }}</a>
                        </td>
                        <td>
                            <span class="badge {{ $req->status?->badge() ?? 'badge-slate' }}">
                                <span class="dot"></span>{{ $req->status?->label() ?? '—' }}
                            </span>
                        </td>
                        <td class="text-sm">{{ $req->department?->name ?? '—' }}</td>
                        <td class="text-sm">{{ $req->virtualWarehouse?->name ?? '—' }}</td>
                        <td class="text-sm">{{ $req->method?->label() ?? '—' }}</td>
                        <td class="tabular-nums text-slate-500">{{ $req->lines_count }}</td>
                        <td class="text-meta text-xs">{{ $req->submitted_at?->format('d M Y') ?? '—' }}</td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('inventory.disposals.show', $req) }}" wire:navigate class="btn btn-ghost btn-sm">View</a>
                                @if ($req->status === \App\Enums\Inventory\DisposalStatus::Submitted)
                                    <button wire:click="approveDisposal({{ $req->id }})" wire:loading.attr="disabled" class="btn btn-primary btn-sm">
                                        Approve
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </div>
    @endif

    {{-- Stock Status Requests section --}}
    @if ($this->pendingStockStatusApprovals->isNotEmpty())
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Stock Status Requests</h3>
                <span class="badge badge-amber ml-2">{{ $this->pendingStockStatusApprovals->count() }}</span>
            </div>
            <x-ui.table>
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Status</th>
                        <th>Type</th>
                        <th>Department</th>
                        <th>VW</th>
                        <th>Lines</th>
                        <th>Submitted</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->pendingStockStatusApprovals as $req)
                    <tr wire:key="ssr-{{ $req->id }}">
                        <td>
                            <a href="{{ route('inventory.stock-status.show', $req) }}" wire:navigate
                               class="mono font-semibold text-simba-600 hover:underline">{{ $req->number }}</a>
                        </td>
                        <td>
                            <span class="badge {{ $req->status?->badge() ?? 'badge-slate' }}">
                                <span class="dot"></span>{{ $req->status?->label() ?? '—' }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ $req->type === \App\Enums\Inventory\StockStatusRequestType::MarkObsolete ? 'badge-red' : 'badge-green' }}">
                                {{ $req->type?->label() }}
                            </span>
                        </td>
                        <td class="text-sm">{{ $req->department?->name ?? '—' }}</td>
                        <td class="text-sm">{{ $req->virtualWarehouse?->name ?? '—' }}</td>
                        <td class="tabular-nums text-slate-500">{{ $req->lines_count }}</td>
                        <td class="text-meta text-xs">{{ $req->submitted_at?->format('d M Y') ?? '—' }}</td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('inventory.stock-status.show', $req) }}" wire:navigate class="btn btn-ghost btn-sm">View</a>
                                @if ($req->status === \App\Enums\Inventory\StockStatusRequestStatus::Submitted)
                                    <button wire:click="approveSsr({{ $req->id }})" wire:loading.attr="disabled" class="btn btn-primary btn-sm">
                                        Approve
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </div>
    @endif

    {{-- Department Transfers (STN) section --}}
    @if ($this->pendingStnApprovals->isNotEmpty())
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Department Transfers</h3>
                <span class="badge badge-amber ml-2">{{ $this->pendingStnApprovals->count() }}</span>
            </div>
            <x-ui.table>
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Status</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Created By</th>
                        <th>Date</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->pendingStnApprovals as $stn)
                        @php
                            $lastApproval = $stn->approvals->first();
                            $isReturned   = in_array($stn->status, [
                                \App\Enums\TransferStatus::ReturnedForCorrection,
                                \App\Enums\TransferStatus::AwaitingRequesterCorrection,
                            ], true);
                        @endphp
                        <tr wire:key="stn-{{ $stn->id }}">
                            <td>
                                <a href="{{ route('warehouse.transfers.show', $stn) }}" wire:navigate
                                   class="mono font-semibold text-simba-600 dark:text-simba-400 hover:underline">
                                    {{ $stn->number }}
                                </a>
                            </td>
                            <td>
                                <span class="badge {{ $stn->status->badge() }}">
                                    <span class="dot"></span>{{ $stn->status->label() }}
                                </span>
                                @if ($isReturned && $lastApproval?->notes)
                                    <p class="text-xs text-slate-500 mt-0.5 max-w-[180px] truncate" title="{{ $lastApproval->notes }}">
                                        {{ $lastApproval->notes }}
                                    </p>
                                @endif
                            </td>
                            <td class="text-sm text-slate-600 dark:text-slate-400">{{ $stn->fromVirtualWarehouse?->name ?? '—' }}</td>
                            <td class="text-sm text-slate-600 dark:text-slate-400">{{ $stn->toVirtualWarehouse?->name ?? '—' }}</td>
                            <td class="text-sm text-slate-600 dark:text-slate-400">{{ $stn->createdBy?->name ?? '—' }}</td>
                            <td class="text-xs text-slate-500 tabular-nums">{{ $stn->created_at->format('d M Y') }}</td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('warehouse.transfers.show', $stn) }}" wire:navigate class="btn btn-ghost btn-sm">View</a>
                                    @if ($stn->status === \App\Enums\TransferStatus::Submitted)
                                        @can('approveDepartment', $stn)
                                            <button wire:click="approveStn({{ $stn->id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:confirm="Approve transfer {{ $stn->number }}?"
                                                    class="btn btn-primary btn-sm">
                                                Approve
                                            </button>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </div>
    @endif

    {{-- Delivery Orders section --}}
    @if ($this->pendingDoApprovals->isNotEmpty())
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Delivery Orders</h3>
                <span class="badge badge-amber ml-2">{{ $this->pendingDoApprovals->count() }}</span>
            </div>
            <x-ui.table>
                <thead>
                    <tr>
                        <th>DO Number</th>
                        <th>Status</th>
                        <th>Department</th>
                        <th>Consignee</th>
                        <th>Requested By</th>
                        <th>Submitted</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->pendingDoApprovals as $do)
                        @php
                            $lastApproval = $do->approvals->first();
                            $isReturned   = in_array($do->status, [
                                \App\Enums\Outbound\DeliveryOrderStatus::ReturnedForCorrection,
                                \App\Enums\Outbound\DeliveryOrderStatus::AwaitingRequesterCorrection,
                            ], true);
                        @endphp
                        <tr wire:key="do-{{ $do->id }}">
                            <td>
                                <a href="{{ route('outbound.orders.show', $do) }}" wire:navigate
                                   class="mono font-semibold text-simba-600 dark:text-simba-400 hover:underline">
                                    {{ $do->number }}
                                </a>
                            </td>
                            <td>
                                <span class="badge {{ $do->status->badge() }}">{{ $do->status->label() }}</span>
                                @if ($isReturned && $lastApproval?->notes)
                                    <p class="text-xs text-slate-500 mt-0.5 max-w-[180px] truncate" title="{{ $lastApproval->notes }}">
                                        {{ $lastApproval->notes }}
                                    </p>
                                @endif
                            </td>
                            <td class="text-sm text-slate-600 dark:text-slate-400">{{ $do->department?->name ?? '—' }}</td>
                            <td class="text-sm text-slate-600 dark:text-slate-400">{{ $do->consigneePartner?->name ?? '—' }}</td>
                            <td class="text-sm text-slate-600 dark:text-slate-400">{{ $do->requestedBy?->name ?? '—' }}</td>
                            <td class="text-xs text-slate-500">{{ $do->submitted_at?->format('d M Y') ?? '—' }}</td>
                            <td class="text-right">
                                <a href="{{ route('outbound.orders.show', $do) }}" wire:navigate class="btn btn-secondary btn-sm">
                                    <x-ui.icon name="eye" class="w-3.5 h-3.5" /> View
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </div>
    @endif

    {{-- Empty state --}}
    @if ($this->pendingPoApprovals->isEmpty() && $this->pendingDoApprovals->isEmpty() && $this->pendingDisposalApprovals->isEmpty() && $this->pendingStockStatusApprovals->isEmpty() && $this->pendingStnApprovals->isEmpty())
        <div class="card p-12 flex flex-col items-center justify-center text-center space-y-3">
            <div class="w-14 h-14 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                <x-ui.icon name="check" class="w-7 h-7 text-emerald-600" />
            </div>
            <p class="font-semibold text-slate-700 dark:text-slate-200">
                {{ $tab === 'pending' ? 'No pending approvals' : 'No records found' }}
            </p>
            <p class="text-sm text-slate-400">
                {{ $tab === 'pending' ? 'All documents have been reviewed. Check back later.' : 'There are no documents to display.' }}
            </p>
        </div>
    @endif

    {{-- PO Return modal --}}
    @if ($showPoReturnModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm p-4"
             x-data wire:click.self="closePoReturnModal">
            <div class="card w-full max-w-md p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-slate-900 dark:text-white">Return for Correction</h3>
                    <button wire:click="closePoReturnModal" class="icon-btn"><x-ui.icon name="x" class="w-5 h-5" /></button>
                </div>
                <div>
                    <label class="label">Comment <span class="text-red-500">*</span></label>
                    <textarea wire:model="poComment" rows="3" class="input resize-none"
                              placeholder="What needs to be corrected…"></textarea>
                    @error('poComment') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button wire:click="closePoReturnModal" class="btn btn-ghost">Cancel</button>
                    <button wire:click="returnPo" wire:loading.attr="disabled" class="btn btn-secondary">Return</button>
                </div>
            </div>
        </div>
    @endif

    {{-- PO Reject modal --}}
    @if ($showPoRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm p-4"
             x-data wire:click.self="closePoRejectModal">
            <div class="card w-full max-w-md p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-ui.icon name="x-circle" class="w-5 h-5 text-red-500" />
                        <h3 class="font-bold text-slate-900 dark:text-white">Reject Purchase Order</h3>
                    </div>
                    <button wire:click="closePoRejectModal" class="icon-btn"><x-ui.icon name="x" class="w-5 h-5" /></button>
                </div>
                <div>
                    <label class="label">Reason <span class="text-red-500">*</span></label>
                    <textarea wire:model="poRejectReason" rows="3" class="input resize-none"
                              placeholder="Explain why this PO is being rejected…"></textarea>
                    @error('poRejectReason') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button wire:click="closePoRejectModal" class="btn btn-ghost">Cancel</button>
                    <button wire:click="rejectPo" wire:loading.attr="disabled" class="btn btn-primary !bg-red-600 hover:!bg-red-700">Reject</button>
                </div>
            </div>
        </div>
    @endif

</div>
