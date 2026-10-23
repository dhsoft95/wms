<div class="space-y-6">
    <div class="page-header">
        <div>
            <a href="{{ route('inventory.counts.index') }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-simba-600 mb-2">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Back to Counts
            </a>
            <h2 class="page-title font-mono">{{ $count->number }}</h2>
            <div class="flex items-center gap-3 mt-1">
                <span class="badge {{ $count->status->badge() }}"><span class="dot"></span>{{ $count->status->label() }}</span>
                @if($blind)
                    <span class="badge badge-slate">Blind Count</span>
                @endif
            </div>
        </div>
        <div class="flex gap-2 flex-wrap">
            @if($count->status === \App\Enums\CycleCountStatus::Counting)
                @can('recordCount', $count)
                    <button wire:click="submitForReview" wire:loading.attr="disabled" class="btn btn-primary">
                        <span wire:loading.remove wire:target="submitForReview"><x-ui.icon name="check" class="w-4 h-4" /> Submit for Review</span>
                        <span wire:loading wire:target="submitForReview">Submitting…</span>
                    </button>
                @endcan
            @elseif($count->status === \App\Enums\CycleCountStatus::VarianceReview)
                @can('approve', $count)
                    <button wire:click="post" wire:loading.attr="disabled" class="btn btn-success">
                        <span wire:loading.remove wire:target="post"><x-ui.icon name="check" class="w-4 h-4" /> Approve & Post Variances</span>
                        <span wire:loading wire:target="post">Posting…</span>
                    </button>
                @endcan
            @endif

            @if(!$count->status->isTerminal())
                @can('approve', $count)
                    <button wire:click="cancel"
                            wire:confirm="Cancel this cycle count? The location lock will be released immediately."
                            wire:loading.attr="disabled"
                            class="btn btn-danger-outline">
                        <span wire:loading.remove wire:target="cancel"><x-ui.icon name="x" class="w-4 h-4" /> Cancel Count</span>
                        <span wire:loading wire:target="cancel">Cancelling…</span>
                    </button>
                @endcan
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Count Lines --}}
        <div class="card lg:col-span-2">
            <div class="card-header">
                <h3 class="text-card-title">Count Lines</h3>
                @if($blind)
                    <p class="text-xs text-slate-500 mt-0.5">System quantities are hidden until the count is submitted.</p>
                @endif
            </div>
            <x-ui.table>
                <thead>
                    <tr>
                        <th>Location</th>
                        <th>Item</th>
                        <th>Batch</th>
                        @unless($blind) <th class="text-right">System Qty</th> @endunless
                        <th class="text-right">Counted Qty</th>
                        @unless($blind) <th class="text-right">Variance</th> @endunless
                    </tr>
                </thead>
                <tbody>
                    @forelse($count->lines as $line)
                        @php
                            $variance = $line->variance;
                            $vColor = $variance === null
                                ? ''
                                : (bccomp((string)$variance, '0', 4) === 0
                                    ? 'text-green-600 dark:text-green-400'
                                    : 'text-red-600 dark:text-red-400');
                        @endphp
                        <tr wire:key="cnt-line-{{ $line->id }}">
                            <td class="mono text-sm">{{ $line->location?->code }}</td>
                            <td>
                                <div class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $line->item?->sku }}</div>
                                <div class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $line->item?->name }}</div>
                            </td>
                            <td class="mono text-sm">{{ $line->batch?->batch_no ?? '—' }}</td>
                            @unless($blind)
                                <td class="text-right tabular-nums text-sm">{{ number_format((float)$line->system_qty, 4) }}</td>
                            @endunless
                            <td class="text-right">
                                @if(in_array($count->status, [\App\Enums\CycleCountStatus::Open, \App\Enums\CycleCountStatus::Counting]))
                                    @can('recordCount', $count)
                                        <input wire:model="entries.{{ $line->id }}"
                                               type="number" step="0.0001" min="0"
                                               class="input input-sm w-28 mono text-right"
                                               wire:change="saveEntry({{ $line->id }})"
                                        >
                                    @else
                                        <span class="tabular-nums text-sm">{{ $line->counted_qty !== null ? number_format((float)$line->counted_qty, 4) : '—' }}</span>
                                    @endcan
                                @else
                                    <span class="tabular-nums text-sm">{{ $line->counted_qty !== null ? number_format((float)$line->counted_qty, 4) : '—' }}</span>
                                @endif
                            </td>
                            @unless($blind)
                                <td class="text-right tabular-nums text-sm font-semibold {{ $vColor }}">
                                    {{ $variance !== null ? (((float)$variance > 0 ? '+' : '') . number_format((float)$variance, 4)) : '—' }}
                                </td>
                            @endunless
                        </tr>
                    @empty
                        <x-ui.empty-state title="No lines." :cols="999" />
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
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Virtual Warehouse</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $count->virtualWarehouse?->name ?? '—' }}</p>
                    </div>
                    <div class="py-2 first:pt-0 last:pb-0">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Created By</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $count->createdBy?->name ?? '—' }}</p>
                    </div>
                    <div class="py-2 first:pt-0 last:pb-0">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Created</p>
                        <p class="mt-0.5 text-sm tabular-nums text-slate-800 dark:text-slate-200">{{ $count->created_at->format('d M Y H:i') }}</p>
                    </div>
                    @if($count->posted_at)
                        <div class="py-2 first:pt-0 last:pb-0">
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Posted</p>
                            <p class="mt-0.5 text-sm tabular-nums text-slate-800 dark:text-slate-200">{{ $count->posted_at->format('d M Y H:i') }}</p>
                        </div>
                    @endif
                    @if($count->notes)
                        <div class="py-2 first:pt-0 last:pb-0">
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Notes</p>
                            <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-400">{{ $count->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
