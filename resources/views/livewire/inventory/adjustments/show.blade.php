<div class="space-y-6">
    <div class="page-header">
        <div>
            <a href="{{ route('inventory.adjustments.index') }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-simba-600 mb-2">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Back to Adjustments
            </a>
            <h2 class="page-title font-mono">{{ $adjustment->number }}</h2>
            <div class="flex items-center gap-3 mt-1">
                <span class="badge {{ $adjustment->status->badge() }}"><span class="dot"></span>{{ $adjustment->status->label() }}</span>
                @if($adjustment->threshold_exceeded)
                    <span class="badge badge-amber">Approval Required</span>
                @endif
            </div>
        </div>
        <div class="flex gap-2 flex-wrap">
            @if($adjustment->status === \App\Enums\AdjustmentStatus::Draft)
                @can('submit', $adjustment)
                    <button wire:click="submit" wire:loading.attr="disabled" class="btn btn-primary">
                        <span wire:loading.remove wire:target="submit"><x-ui.icon name="check" class="w-4 h-4" /> Submit</span>
                        <span wire:loading wire:target="submit">Submitting…</span>
                    </button>
                @endcan
            @elseif($adjustment->status === \App\Enums\AdjustmentStatus::Submitted)
                @can('approve', $adjustment)
                    <button wire:click="approve" wire:loading.attr="disabled" class="btn btn-success">
                        <span wire:loading.remove wire:target="approve"><x-ui.icon name="check" class="w-4 h-4" /> Approve & Post</span>
                        <span wire:loading wire:target="approve">Posting…</span>
                    </button>
                    <button wire:click="openRejectModal" class="btn btn-secondary text-red-600 dark:text-red-400">
                        <x-ui.icon name="x" class="w-4 h-4" /> Reject
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
                        <th>Reason</th>
                        <th class="text-right">Qty</th>
                        <th>UoM</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($adjustment->lines as $line)
                        <tr wire:key="adj-line-{{ $line->id }}">
                            <td class="tabular-nums text-slate-400 text-xs">{{ $loop->iteration }}</td>
                            <td>
                                <div class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $line->item?->sku }}</div>
                                <div class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $line->item?->name }}</div>
                            </td>
                            <td class="mono text-sm">{{ $line->location?->code }}</td>
                            <td class="mono text-sm">{{ $line->batch?->batch_no ?? '—' }}</td>
                            <td class="text-sm">{{ $line->reasonCode?->name }}</td>
                            <td class="text-right tabular-nums font-semibold {{ (float)$line->qty < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                {{ (float)$line->qty > 0 ? '+' : '' }}{{ number_format((float)$line->qty, 4) }}
                            </td>
                            <td class="mono text-sm text-slate-500">{{ $line->uom?->code }}</td>
                        </tr>
                    @empty
                        <x-ui.empty-state title="No lines." :cols="7" />
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
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Warehouse</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $adjustment->virtualWarehouse?->name ?? '—' }}</p>
                    </div>
                    <div class="py-2 first:pt-0 last:pb-0">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Created By</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $adjustment->createdBy?->name ?? '—' }}</p>
                    </div>
                    <div class="py-2 first:pt-0 last:pb-0">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Created</p>
                        <p class="mt-0.5 text-sm tabular-nums text-slate-800 dark:text-slate-200">{{ $adjustment->created_at->format('d M Y H:i') }}</p>
                    </div>
                    @if($adjustment->approvedBy)
                        <div class="py-2 first:pt-0 last:pb-0">
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Approved By</p>
                            <p class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $adjustment->approvedBy->name }}</p>
                        </div>
                    @endif
                    @if($adjustment->posted_at)
                        <div class="py-2 first:pt-0 last:pb-0">
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Posted</p>
                            <p class="mt-0.5 text-sm tabular-nums text-slate-800 dark:text-slate-200">{{ $adjustment->posted_at->format('d M Y H:i') }}</p>
                        </div>
                    @endif
                    @if($adjustment->notes)
                        <div class="py-2 first:pt-0 last:pb-0">
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Notes</p>
                            <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-400">{{ $adjustment->notes }}</p>
                        </div>
                    @endif
                    @if($adjustment->rejection_reason)
                        <div class="py-2 first:pt-0 last:pb-0">
                            <p class="text-xs font-medium text-red-500 uppercase tracking-wide">Rejection Reason</p>
                            <p class="mt-0.5 text-sm text-red-600 dark:text-red-400">{{ $adjustment->rejection_reason }}</p>
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
                    <h3 class="text-[15px] font-semibold text-slate-900 dark:text-white">Reject Adjustment</h3>
                    <button type="button" wire:click="$set('showRejectModal', false)" class="icon-btn text-slate-400 hover:text-slate-700">
                        <x-ui.icon name="x" class="h-4 w-4" />
                    </button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="label">Reason <span class="required-mark">*</span></label>
                        <textarea wire:model="rejectionReason" rows="3" class="input w-full resize-none @error('rejectionReason') border-red-400 @enderror"
                                  placeholder="Explain why this adjustment is being rejected…"></textarea>
                        @error('rejectionReason') <p class="hint hint-error">{{ $message }}</p> @enderror
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
