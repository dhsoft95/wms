<div class="space-y-6">
    <div class="page-header">
        <div>
            <a href="{{ route('warehouse.wtr.index') }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-simba-600 mb-2">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Back to Warehouse Transfers
            </a>
            <h2 class="page-title font-mono">{{ $wtr->number }}</h2>
            <div class="flex items-center gap-3 mt-1">
                <span class="badge {{ $wtr->status->badge() }}"><span class="dot"></span>{{ $wtr->status->label() }}</span>
            </div>
        </div>
        <div class="flex gap-2 flex-wrap">
            @if($wtr->status === \App\Enums\Transfers\WarehouseTransferStatus::Draft)
                @can('submit', $wtr)
                    <button wire:click="submit" wire:loading.attr="disabled" class="btn btn-primary">
                        <span wire:loading.remove wire:target="submit"><x-ui.icon name="check" class="w-4 h-4" /> Submit</span>
                        <span wire:loading wire:target="submit">Submitting…</span>
                    </button>
                @endcan
            @endif

            @if($wtr->status === \App\Enums\Transfers\WarehouseTransferStatus::Submitted)
                @can('accept', $wtr)
                    <button wire:click="accept" wire:loading.attr="disabled" class="btn btn-success">
                        <span wire:loading.remove wire:target="accept"><x-ui.icon name="check" class="w-4 h-4" /> Accept</span>
                        <span wire:loading wire:target="accept">Accepting…</span>
                    </button>
                    <button wire:click="openRejectModal" class="btn btn-secondary text-red-600 dark:text-red-400">
                        <x-ui.icon name="x" class="w-4 h-4" /> Reject
                    </button>
                @endcan
            @endif

            @if($wtr->status === \App\Enums\Transfers\WarehouseTransferStatus::Accepted)
                @can('dispatch', $wtr)
                    <button wire:click="confirmDispatch" wire:loading.attr="disabled"
                            wire:confirm="Dispatch this transfer? Stock will move to in-transit."
                            class="btn btn-primary">
                        <span wire:loading.remove wire:target="confirmDispatch"><x-ui.icon name="truck" class="w-4 h-4" /> Dispatch</span>
                        <span wire:loading wire:target="confirmDispatch">Dispatching…</span>
                    </button>
                @endcan
                @can('reject', $wtr)
                    <button wire:click="openRejectModal" class="btn btn-secondary text-red-600 dark:text-red-400">
                        <x-ui.icon name="x" class="w-4 h-4" /> Reject
                    </button>
                @endcan
            @endif

            @if($wtr->status === \App\Enums\Transfers\WarehouseTransferStatus::Dispatched)
                @can('receive', $wtr)
                    <button wire:click="receive" wire:loading.attr="disabled"
                            wire:confirm="Confirm receipt at destination warehouse?"
                            class="btn btn-success">
                        <span wire:loading.remove wire:target="receive"><x-ui.icon name="check" class="w-4 h-4" /> Receive</span>
                        <span wire:loading wire:target="receive">Receiving…</span>
                    </button>
                @endcan
            @endif

            @if(! $wtr->status->isTerminal() && $wtr->status !== \App\Enums\Transfers\WarehouseTransferStatus::Dispatched)
                @can('cancel', $wtr)
                    <button wire:click="cancel" wire:loading.attr="disabled"
                            wire:confirm="Cancel this transfer?"
                            class="btn btn-secondary text-red-600 dark:text-red-400">
                        <x-ui.icon name="x" class="w-4 h-4" /> Cancel
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
                        <th>Batch</th>
                        <th class="text-right">Qty</th>
                        <th>UoM</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($wtr->lines as $line)
                        <tr wire:key="wtr-line-{{ $line->id }}">
                            <td class="tabular-nums text-slate-400 text-xs">{{ $loop->iteration }}</td>
                            <td>
                                <div class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $line->item?->sku }}</div>
                                <div class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $line->item?->name }}</div>
                            </td>
                            <td class="mono text-sm">{{ $line->batch_no ?? '—' }}</td>
                            <td class="text-right tabular-nums font-semibold">{{ number_format((float)$line->qty, 4) }}</td>
                            <td class="mono text-sm text-slate-500">{{ $line->uom?->code }}</td>
                        </tr>
                    @empty
                        <x-ui.empty-state title="No lines." :cols="5" />
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
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">From Warehouse</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $wtr->fromWarehouse?->name ?? '—' }}</p>
                    </div>
                    <div class="py-2 first:pt-0 last:pb-0">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">To Warehouse</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $wtr->toWarehouse?->name ?? '—' }}</p>
                    </div>
                    @if($wtr->fromVirtualWarehouse)
                        <div class="py-2 first:pt-0 last:pb-0">
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">From Department</p>
                            <p class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $wtr->fromVirtualWarehouse->name }}</p>
                        </div>
                    @endif
                    @if($wtr->toVirtualWarehouse)
                        <div class="py-2 first:pt-0 last:pb-0">
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">To Department</p>
                            <p class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $wtr->toVirtualWarehouse->name }}</p>
                        </div>
                    @endif
                    <div class="py-2 first:pt-0 last:pb-0">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Created By</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $wtr->createdBy?->name ?? '—' }}</p>
                    </div>
                    <div class="py-2 first:pt-0 last:pb-0">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Created</p>
                        <p class="mt-0.5 text-sm tabular-nums text-slate-800 dark:text-slate-200">{{ $wtr->created_at->format('d M Y H:i') }}</p>
                    </div>
                    @if($wtr->acceptedBy)
                        <div class="py-2 first:pt-0 last:pb-0">
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Accepted By</p>
                            <p class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $wtr->acceptedBy->name }}</p>
                        </div>
                    @endif
                    @if($wtr->dispatched_at)
                        <div class="py-2 first:pt-0 last:pb-0">
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Dispatched</p>
                            <p class="mt-0.5 text-sm tabular-nums text-slate-800 dark:text-slate-200">{{ $wtr->dispatched_at->format('d M Y H:i') }}</p>
                        </div>
                    @endif
                    @if($wtr->dn_number)
                        <div class="py-2 first:pt-0 last:pb-0">
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">DN / RCN</p>
                            <p class="mt-0.5 text-sm mono text-slate-800 dark:text-slate-200">{{ $wtr->dn_number }} / {{ $wtr->rcn_number }}</p>
                        </div>
                    @endif
                    @if($wtr->received_at)
                        <div class="py-2 first:pt-0 last:pb-0">
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Received</p>
                            <p class="mt-0.5 text-sm tabular-nums text-slate-800 dark:text-slate-200">{{ $wtr->received_at->format('d M Y H:i') }}</p>
                        </div>
                    @endif
                    @if($wtr->purchaseOrder)
                        <div class="py-2 first:pt-0 last:pb-0">
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Linked PO</p>
                            <a href="{{ route('inbound.orders.show', $wtr->purchaseOrder) }}" wire:navigate
                               class="mt-0.5 text-sm mono text-simba-600 dark:text-simba-400 hover:underline">
                                {{ $wtr->purchaseOrder->number }}
                            </a>
                        </div>
                    @endif
                    @if($wtr->notes)
                        <div class="py-2 first:pt-0 last:pb-0">
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Notes</p>
                            <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-400">{{ $wtr->notes }}</p>
                        </div>
                    @endif
                    @if($wtr->rejection_reason)
                        <div class="py-2 first:pt-0 last:pb-0">
                            <p class="text-xs font-medium text-red-500 uppercase tracking-wide">Rejection Reason</p>
                            <p class="mt-0.5 text-sm text-red-600 dark:text-red-400">{{ $wtr->rejection_reason }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

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
</div>
