<div class="space-y-6">
    <div class="page-header">
        <div>
            <a href="{{ route('warehouse.transfers.index') }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-simba-600 mb-2">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Back to Transfers
            </a>
            <h2 class="page-title font-mono">{{ $transfer->number }}</h2>
            <div class="flex items-center gap-3 mt-1">
                <span class="badge {{ $transfer->status->badge() }}"><span class="dot"></span>{{ $transfer->status->label() }}</span>
            </div>
        </div>
        <div class="flex gap-2 flex-wrap">
            @if($transfer->status === \App\Enums\TransferStatus::Draft || $transfer->status === \App\Enums\TransferStatus::AwaitingRequesterCorrection)
                @can('submit', $transfer)
                    <button wire:click="submit" wire:loading.attr="disabled" class="btn btn-primary">
                        <span wire:loading.remove wire:target="submit"><x-ui.icon name="check" class="w-4 h-4" /> Submit for Approval</span>
                        <span wire:loading wire:target="submit">Submitting…</span>
                    </button>
                @endcan
            @elseif($transfer->status === \App\Enums\TransferStatus::Submitted)
                @can('approveDepartment', $transfer)
                    <button wire:click="approveDepartment" wire:loading.attr="disabled" class="btn btn-success">
                        <span wire:loading.remove wire:target="approveDepartment"><x-ui.icon name="check" class="w-4 h-4" /> Approve</span>
                        <span wire:loading wire:target="approveDepartment">Approving…</span>
                    </button>
                @endcan
                @can('returnOrder', $transfer)
                    <button wire:click="openReturnModal" class="btn btn-secondary text-amber-600 dark:text-amber-400">
                        <x-ui.icon name="arrow-left" class="w-4 h-4" /> Send Back
                    </button>
                @endcan
                @can('accept', $transfer)
                    <button wire:click="accept" wire:loading.attr="disabled" class="btn btn-success">
                        <span wire:loading.remove wire:target="accept"><x-ui.icon name="check" class="w-4 h-4" /> Accept</span>
                        <span wire:loading wire:target="accept">Accepting…</span>
                    </button>
                    <button wire:click="openRejectModal" class="btn btn-secondary text-red-600 dark:text-red-400">
                        <x-ui.icon name="x" class="w-4 h-4" /> Reject
                    </button>
                @endcan
            @elseif($transfer->status === \App\Enums\TransferStatus::ReturnedForCorrection)
                @can('forwardReturn', $transfer)
                    <button wire:click="forwardReturn" wire:loading.attr="disabled" class="btn btn-secondary">
                        <span wire:loading.remove wire:target="forwardReturn"><x-ui.icon name="check" class="w-4 h-4" /> Forward to Requester</span>
                        <span wire:loading wire:target="forwardReturn">Forwarding…</span>
                    </button>
                @endcan
            @elseif($transfer->status === \App\Enums\TransferStatus::Accepted && $transfer->is_physical)
                @can('execute', $transfer)
                    <button wire:click="execute" wire:loading.attr="disabled"
                            wire:confirm="Execute this transfer? Stock will move and this cannot be undone."
                            class="btn btn-success">
                        <span wire:loading.remove wire:target="execute"><x-ui.icon name="check" class="w-4 h-4" /> Execute Transfer</span>
                        <span wire:loading wire:target="execute">Executing…</span>
                    </button>
                @endcan
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Lines --}}
        <div class="card lg:col-span-2">
            <div class="card-header"><h3 class="text-card-title">Lines</h3></div>
            <x-ui.table>
                <thead>
                    <tr>
                        <th class="w-8">#</th>
                        <th>Item</th>
                        <th>Location</th>
                        <th>Batch</th>
                        <th class="text-right">Qty</th>
                        <th>UoM</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transfer->lines as $line)
                        <tr wire:key="trf-line-{{ $line->id }}">
                            <td class="tabular-nums text-slate-400 text-xs">{{ $loop->iteration }}</td>
                            <td>
                                <div class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $line->item?->sku }}</div>
                                <div class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $line->item?->name }}</div>
                            </td>
                            <td class="mono text-sm">{{ $line->location?->code }}</td>
                            <td class="mono text-sm">{{ $line->batch?->batch_no ?? '—' }}</td>
                            <td class="text-right tabular-nums font-semibold">{{ number_format((float)$line->qty, 4) }}</td>
                            <td class="mono text-sm text-slate-500">{{ $line->uom?->code }}</td>
                        </tr>
                    @empty
                        <x-ui.empty-state title="No lines." :cols="6" />
                    @endforelse
                </tbody>
            </x-ui.table>
        </div>

        {{-- Details --}}
        <div class="space-y-4">
            <div class="card">
                <div class="card-header"><h3 class="text-card-title">Details</h3></div>
                <div class="card-body divide-y divide-slate-100 dark:divide-slate-800">
                    <div class="py-2 first:pt-0 last:pb-0">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">From</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $transfer->fromVirtualWarehouse?->name ?? '—' }}</p>
                    </div>
                    <div class="py-2 first:pt-0 last:pb-0">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">To</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $transfer->toVirtualWarehouse?->name ?? '—' }}</p>
                    </div>
                    <div class="py-2 first:pt-0 last:pb-0">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Created By</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $transfer->createdBy?->name ?? '—' }}</p>
                    </div>
                    <div class="py-2 first:pt-0 last:pb-0">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Created</p>
                        <p class="mt-0.5 text-sm tabular-nums text-slate-800 dark:text-slate-200">{{ $transfer->created_at->format('d M Y H:i') }}</p>
                    </div>
                    @if($transfer->acceptedBy)
                        <div class="py-2 first:pt-0 last:pb-0">
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Accepted By</p>
                            <p class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $transfer->acceptedBy->name }}</p>
                        </div>
                    @endif
                    @if($transfer->posted_at)
                        <div class="py-2 first:pt-0 last:pb-0">
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Posted</p>
                            <p class="mt-0.5 text-sm tabular-nums text-slate-800 dark:text-slate-200">{{ $transfer->posted_at->format('d M Y H:i') }}</p>
                        </div>
                    @endif
                    @if($transfer->notes)
                        <div class="py-2 first:pt-0 last:pb-0">
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Notes</p>
                            <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-400">{{ $transfer->notes }}</p>
                        </div>
                    @endif
                    @if($transfer->rejection_reason)
                        <div class="py-2 first:pt-0 last:pb-0">
                            <p class="text-xs font-medium text-red-500 uppercase tracking-wide">Rejection Reason</p>
                            <p class="mt-0.5 text-sm text-red-600 dark:text-red-400">{{ $transfer->rejection_reason }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Approvals --}}
    @if($transfer->approvals->isNotEmpty())
    <div class="card">
        <div class="card-header"><h3 class="text-card-title">Approvals</h3></div>
        <div class="card-body space-y-3">
            @foreach($transfer->approvals as $approval)
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4 space-y-2">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200 capitalize">
                                {{ $approval->approver_role }} Approval
                                @if($approval->level)
                                    <span class="text-xs font-normal text-slate-500 ml-1">(Level {{ $approval->level }})</span>
                                @endif
                            </p>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $approval->created_at->format('d M Y H:i') }}</p>
                        </div>
                        @if($approval->decision === 'approved')
                            <span class="badge badge-green shrink-0"><span class="dot"></span>Approved</span>
                        @elseif($approval->decision === 'rejected')
                            <span class="badge badge-red shrink-0"><span class="dot"></span>Rejected</span>
                        @elseif($approval->decision === 'returned')
                            <span class="badge badge-orange shrink-0"><span class="dot"></span>Returned</span>
                        @else
                            <span class="badge badge-amber shrink-0"><span class="dot"></span>Pending</span>
                        @endif
                    </div>
                    @if($approval->decidedBy || $approval->notes)
                        <div class="text-sm text-slate-600 dark:text-slate-400">
                            @if($approval->decidedBy)
                                <span class="font-medium">{{ $approval->decidedBy->name }}</span>
                                @if($approval->decided_at)
                                    <span class="text-xs text-slate-400 ml-1">· {{ $approval->decided_at->format('d M Y H:i') }}</span>
                                @endif
                            @endif
                            @if($approval->notes)
                                <p class="mt-1 text-slate-500 dark:text-slate-400 italic">{{ $approval->notes }}</p>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Reject Modal --}}
    @if($showRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             x-data x-on:keydown.escape.window="$wire.showRejectModal = false">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-[2px]"
                 wire:click="$set('showRejectModal', false)"></div>
            <div class="relative z-10 w-full max-w-md rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl ring-1 ring-black/[0.06] dark:ring-white/[0.08]">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-white/[0.08]">
                    <h3 class="text-[15px] font-semibold text-slate-900 dark:text-white">Reject Transfer</h3>
                    <button type="button" wire:click="$set('showRejectModal', false)" class="icon-btn text-slate-400 hover:text-slate-700">
                        <x-ui.icon name="x" class="h-4 w-4" />
                    </button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="label">Reason <span class="text-red-500">*</span></label>
                        <textarea wire:model="rejectionReason" rows="3" class="input w-full resize-none @error('rejectionReason') border-red-400 @enderror"
                                  placeholder="Explain why this transfer is being rejected…"></textarea>
                        @error('rejectionReason') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex justify-end gap-3 px-6 py-4 bg-slate-50/80 dark:bg-white/[0.02] border-t border-slate-100 dark:border-white/[0.08] rounded-b-2xl">
                    <button wire:click="$set('showRejectModal', false)" class="btn btn-secondary">Cancel</button>
                    <button wire:click="reject" wire:loading.attr="disabled" class="btn bg-red-600 hover:bg-red-700 text-white font-semibold">
                        <span wire:loading.remove wire:target="reject">Reject</span>
                        <span wire:loading wire:target="reject">Rejecting…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Return for Correction Modal --}}
    @if($showReturnModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             x-data x-on:keydown.escape.window="$wire.showReturnModal = false">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-[2px]"
                 wire:click="$set('showReturnModal', false)"></div>
            <div class="relative z-10 w-full max-w-md rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl ring-1 ring-black/[0.06] dark:ring-white/[0.08]">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-white/[0.08]">
                    <h3 class="text-[15px] font-semibold text-slate-900 dark:text-white">Send Back for Correction</h3>
                    <button type="button" wire:click="$set('showReturnModal', false)" class="icon-btn text-slate-400 hover:text-slate-700">
                        <x-ui.icon name="x" class="h-4 w-4" />
                    </button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="label">Reason <span class="text-red-500">*</span></label>
                        <textarea wire:model="returnNotes" rows="3" class="input w-full resize-none @error('returnNotes') border-red-400 @enderror"
                                  placeholder="Explain what needs to be corrected…"></textarea>
                        @error('returnNotes') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex justify-end gap-3 px-6 py-4 bg-slate-50/80 dark:bg-white/[0.02] border-t border-slate-100 dark:border-white/[0.08] rounded-b-2xl">
                    <button wire:click="$set('showReturnModal', false)" class="btn btn-secondary">Cancel</button>
                    <button wire:click="sendBack" wire:loading.attr="disabled" class="btn bg-amber-500 hover:bg-amber-600 text-white font-semibold">
                        <span wire:loading.remove wire:target="sendBack">Send Back</span>
                        <span wire:loading wire:target="sendBack">Sending…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
