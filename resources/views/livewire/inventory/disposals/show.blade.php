<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title font-mono">{{ $this->disposalRequest->number }}</h2>
            <p class="page-sub">
                {{ $this->disposalRequest->method?->label() }} &mdash;
                {{ $this->disposalRequest->virtualWarehouse?->name }}
                &mdash; <span class="badge {{ $this->disposalRequest->status->badge() }}">{{ $this->disposalRequest->status->label() }}</span>
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('inventory.disposals.print', $this->disposalRequest) }}" target="_blank" class="btn btn-ghost">
                <x-ui.icon name="printer" class="w-4 h-4" /> Print Note
            </a>
            <a href="{{ route('inventory.disposals.index') }}" wire:navigate class="btn btn-ghost">Back</a>
        </div>
    </div>

    {{-- Details card --}}
    <div class="card">
        <div class="card-body grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-meta">Department</p>
                <p>{{ $this->disposalRequest->department?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-meta">Created By</p>
                <p>{{ $this->disposalRequest->createdBy?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-meta">Total Value</p>
                <p class="tabular-nums font-semibold">
                    @if($this->disposalRequest->total_value !== null)
                        {{ number_format((float) $this->disposalRequest->total_value, 2) }}
                    @else
                        —
                    @endif
                </p>
            </div>
            <div>
                <p class="text-meta">Submitted</p>
                <p>{{ $this->disposalRequest->submitted_at?->format('d M Y H:i') ?? '—' }}</p>
            </div>
        </div>
        @if($this->disposalRequest->reason)
        <div class="card-body border-t pt-3">
            <p class="text-meta text-xs mb-1">Reason</p>
            <p class="text-sm">{{ $this->disposalRequest->reason }}</p>
        </div>
        @endif
    </div>

    {{-- Action buttons --}}
    <div class="flex gap-2 flex-wrap">
        @can('approve', $this->disposalRequest)
            @if($this->disposalRequest->status === \App\Enums\Inventory\DisposalStatus::Submitted)
                <button wire:click="openApproveModal" type="button" class="btn btn-primary">Approve</button>
                <button wire:click="openRejectModal" type="button" class="btn btn-error">Reject</button>
            @endif
        @endcan

        @can('execute', $this->disposalRequest)
            @if($this->disposalRequest->status === \App\Enums\Inventory\DisposalStatus::Approved)
                <button wire:click="openMoveModal" type="button" class="btn btn-primary">Move to Disposal Area</button>
                <button wire:click="openRejectModal" type="button" class="btn btn-ghost text-red-500">Reject</button>
            @endif
            @if($this->disposalRequest->status === \App\Enums\Inventory\DisposalStatus::Moved)
                <button wire:click="openCompleteModal" type="button" class="btn btn-primary">Complete Disposal</button>
                <button wire:click="openRejectModal" type="button" class="btn btn-ghost text-red-500">Reject</button>
            @endif
        @endcan
    </div>

    {{-- Lines --}}
    <div class="card">
        <div class="card-header"><h3 class="card-title">Lines</h3></div>
        <x-ui.table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Batch</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th class="text-right">Qty</th>
                    <th>Unit</th>
                    <th class="text-right">Value</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->disposalRequest->lines as $line)
                    <tr wire:key="line-{{ $line->id }}">
                        <td class="font-medium text-sm">{{ $line->item?->name ?? '—' }} <span class="text-meta text-xs">{{ $line->item?->sku }}</span></td>
                        <td class="text-sm mono">{{ $line->batch?->batch_no ?? '—' }}</td>
                        <td class="text-sm mono">{{ $line->location?->code ?? '—' }}</td>
                        <td>
                            @if($line->stock_status)
                                <span class="badge badge-neutral">{{ $line->stock_status->label() }}</span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="text-right tabular-nums">{{ number_format((float) $line->qty, 4) }}</td>
                        <td class="text-sm">{{ $line->item?->baseUom?->code ?? '—' }}</td>
                        <td class="text-right tabular-nums text-sm">
                            @if($line->value !== null)
                                {{ number_format((float) $line->value, 2) }}
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-state title="No lines." :cols="7" />
                @endforelse
            </tbody>
            @if($this->disposalRequest->total_value !== null)
            <tfoot>
                <tr class="font-semibold">
                    <td colspan="6" class="text-right pr-4">Total Value:</td>
                    <td class="text-right tabular-nums">{{ number_format((float) $this->disposalRequest->total_value, 2) }}</td>
                </tr>
            </tfoot>
            @endif
        </x-ui.table>
    </div>

    {{-- Completion details (if completed) --}}
    @if($this->disposalRequest->status === \App\Enums\Inventory\DisposalStatus::Completed)
    <div class="card">
        <div class="card-header"><h3 class="card-title">Completion Details</h3></div>
        <div class="card-body grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
            @if($this->disposalRequest->method === \App\Enums\Inventory\DisposalMethod::Auction)
                <div><p class="text-meta">Buyer</p><p>{{ $this->disposalRequest->buyer_name ?? '—' }}</p></div>
                <div><p class="text-meta">Sale Price</p><p class="tabular-nums">{{ $this->disposalRequest->sale_price ? number_format((float)$this->disposalRequest->sale_price, 2) : '—' }}</p></div>
                <div><p class="text-meta">Sale Date</p><p>{{ $this->disposalRequest->sale_date?->format('d M Y') ?? '—' }}</p></div>
            @else
                <div><p class="text-meta">Destroy Method</p><p>{{ $this->disposalRequest->destroy_method?->label() ?? '—' }}</p></div>
                <div><p class="text-meta">Destroy Date</p><p>{{ $this->disposalRequest->destroy_date?->format('d M Y') ?? '—' }}</p></div>
                <div><p class="text-meta">Witness</p><p>{{ $this->disposalRequest->witness_name ?? '—' }}</p></div>
            @endif
        </div>
    </div>
    @endif

    {{-- Rejection info --}}
    @if($this->disposalRequest->status === \App\Enums\Inventory\DisposalStatus::Rejected && $this->disposalRequest->rejection_reason)
    <div class="card border-red-200">
        <div class="card-body">
            <p class="text-error text-sm font-semibold">Rejection Reason</p>
            <p class="text-sm mt-1">{{ $this->disposalRequest->rejection_reason }}</p>
            <p class="text-meta text-xs mt-1">Rejected by {{ $this->disposalRequest->rejectedBy?->name }} on {{ $this->disposalRequest->rejected_at?->format('d M Y H:i') }}</p>
        </div>
    </div>
    @endif

    {{-- Approval history --}}
    @if($this->disposalRequest->approvals->count())
    <div class="card">
        <div class="card-header"><h3 class="card-title">Approval History</h3></div>
        <x-ui.table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Approver</th>
                    <th>Level</th>
                    <th>Action</th>
                    <th>Comment</th>
                </tr>
            </thead>
            <tbody>
                @foreach($this->disposalRequest->approvals as $approval)
                <tr wire:key="approval-{{ $approval->id }}">
                    <td class="text-meta text-xs tabular-nums">{{ $approval->created_at->format('d M Y H:i') }}</td>
                    <td class="text-sm">{{ $approval->user?->name ?? '—' }}</td>
                    <td class="tabular-nums text-xs">{{ $approval->level }}</td>
                    <td>
                        <span class="badge {{ $approval->action === 'approved' ? 'badge-success' : 'badge-error' }}">
                            {{ ucfirst($approval->action) }}
                        </span>
                    </td>
                    <td class="text-sm text-slate-500">{{ $approval->comment ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </x-ui.table>
    </div>
    @endif

    {{-- MODALS --}}

    {{-- Approve modal --}}
    @if($showApproveModal)
    <div class="modal-overlay" x-data x-on:keydown.escape.window="$wire.closeApproveModal()">
        <div class="modal">
            <h3 class="modal-title">Approve Disposal Request</h3>
            <p class="text-sm text-slate-500 mt-1">Are you sure you want to approve {{ $this->disposalRequest->number }}?</p>
            <div class="mt-4">
                <label class="label">Comment (optional)</label>
                <textarea wire:model="approveComment" class="input w-full" rows="2"></textarea>
            </div>
            <div class="modal-footer">
                <button wire:click="closeApproveModal" type="button" class="btn btn-ghost">Cancel</button>
                <button wire:click="approve" type="button" class="btn btn-primary">Approve</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Reject modal --}}
    @if($showRejectModal)
    <div class="modal-overlay" x-data x-on:keydown.escape.window="$wire.closeRejectModal()">
        <div class="modal">
            <h3 class="modal-title text-red-600">Reject Disposal Request</h3>
            <div class="mt-4">
                <label class="label">Reason for rejection <span class="required-mark">*</span></label>
                <textarea wire:model="rejectReason" class="input w-full" rows="3" placeholder="Enter rejection reason…"></textarea>
                @error('rejectReason') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="modal-footer">
                <button wire:click="closeRejectModal" type="button" class="btn btn-ghost">Cancel</button>
                <button wire:click="reject" type="button" class="btn btn-error">Reject</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Move modal --}}
    @if($showMoveModal)
    <div class="modal-overlay" x-data x-on:keydown.escape.window="$wire.closeMoveModal()">
        <div class="modal">
            <h3 class="modal-title">Move to Disposal Area</h3>
            <p class="text-sm text-slate-500 mt-1">Select a disposal bin to move the stock into.</p>
            <div class="mt-4">
                <label class="label">Disposal Location <span class="required-mark">*</span></label>
                <select wire:model="disposalLocationId" class="input w-full">
                    <option value="0">-- Select disposal bin --</option>
                    @foreach($this->disposalLocations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->code }}</option>
                    @endforeach
                </select>
                @error('disposalLocationId') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="modal-footer">
                <button wire:click="closeMoveModal" type="button" class="btn btn-ghost">Cancel</button>
                <button wire:click="moveToDisposalArea" type="button" class="btn btn-primary">Move Stock</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Complete modal --}}
    @if($showCompleteModal)
    <div class="modal-overlay" x-data x-on:keydown.escape.window="$wire.closeCompleteModal()">
        <div class="modal max-w-lg">
            <h3 class="modal-title">Complete Disposal</h3>
            <div class="mt-4 space-y-3">
                @if($this->disposalRequest->method === \App\Enums\Inventory\DisposalMethod::Auction)
                    <div>
                        <label class="label">Buyer Name <span class="required-mark">*</span></label>
                        <input wire:model="completionData.buyer_name" type="text" class="input w-full">
                        @error('completionData.buyer_name') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Sale Price <span class="required-mark">*</span></label>
                        <input wire:model="completionData.sale_price" type="number" min="0" step="any" class="input w-full">
                        @error('completionData.sale_price') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Sale Date <span class="required-mark">*</span></label>
                        <input wire:model="completionData.sale_date" type="date" class="input w-full">
                        @error('completionData.sale_date') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div>
                        <label class="label">Destroy Method <span class="required-mark">*</span></label>
                        <select wire:model="completionData.destroy_method" class="input w-full">
                            <option value="">-- Select --</option>
                            @foreach(\App\Enums\Inventory\DestroyMethod::cases() as $dm)
                                <option value="{{ $dm->value }}">{{ $dm->label() }}</option>
                            @endforeach
                        </select>
                        @error('completionData.destroy_method') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Destroy Date <span class="required-mark">*</span></label>
                        <input wire:model="completionData.destroy_date" type="date" class="input w-full">
                        @error('completionData.destroy_date') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Witness Name <span class="required-mark">*</span></label>
                        <input wire:model="completionData.witness_name" type="text" class="input w-full">
                        @error('completionData.witness_name') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button wire:click="closeCompleteModal" type="button" class="btn btn-ghost">Cancel</button>
                <button wire:click="complete" type="button" class="btn btn-primary">Complete Disposal</button>
            </div>
        </div>
    </div>
    @endif
</div>
