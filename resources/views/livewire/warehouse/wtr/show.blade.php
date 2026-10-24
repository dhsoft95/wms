@php
    use App\Enums\Transfers\WarehouseTransferStatus as Status;

    $status       = $wtr->status;
    $isDraft      = $status === Status::Draft;
    $isSubmitted  = $status === Status::Submitted;
    $isAccepted   = $status === Status::Accepted;
    $isDispatched = $status === Status::Dispatched;
    $isRejected   = $status === Status::Rejected;
    $isCancelled  = $status === Status::Cancelled;
    $canBeCancelled = ! $status->isTerminal() && ! $isDispatched;

    $lineCount = $wtr->lines->count();
    $fmt = fn ($qty) => rtrim(rtrim(number_format((float) $qty, 4, '.', ','), '0'), '.');
    $dt  = fn ($date) => $date?->format('d M Y · H:i');

    $spinner = '<svg class="w-4 h-4 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>';

    $steps = [
        ['label' => 'Created',    'done' => true,                                                                                  'by' => $wtr->createdBy?->name,  'at' => $dt($wtr->created_at)],
        ['label' => 'Submitted',  'done' => $status !== Status::Draft,                                                             'by' => null,                    'at' => null],
        ['label' => 'Accepted',   'done' => in_array($status, [Status::Accepted, Status::Dispatched, Status::Completed], true),    'by' => $wtr->acceptedBy?->name, 'at' => $wtr->accepted_at ? $dt($wtr->accepted_at) : null],
        ['label' => 'Dispatched', 'done' => (bool) $wtr->dispatched_at,                                                           'by' => null,                    'at' => $dt($wtr->dispatched_at)],
        ['label' => 'Received',   'done' => (bool) $wtr->received_at,                                                             'by' => null,                    'at' => $dt($wtr->received_at)],
    ];

    if ($isRejected || $isCancelled) {
        $steps = array_values(array_filter($steps, fn ($s) => $s['done']));
        $steps[] = ['label' => $isRejected ? 'Rejected' : 'Cancelled', 'done' => true, 'by' => null, 'at' => null, 'danger' => true];
    }
@endphp

<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div class="min-w-0">
            <a href="{{ route('wtr.index') }}" wire:navigate
               class="inline-flex items-center gap-1.5 mb-2 text-xs font-semibold text-slate-500 transition-colors hover:text-simba-600 dark:text-slate-400 dark:hover:text-simba-400">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5 shrink-0" />
                <span>Back to Warehouse Transfers</span>
            </a>
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="page-title font-mono truncate">{{ $wtr->number }}</h2>
                <span class="badge {{ $status->badge() }}">
                    <span class="dot"></span>{{ $status->label() }}
                </span>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if($canBeCancelled)
                @can('cancel', $wtr)
                    <button type="button"
                            wire:click="cancel"
                            wire:loading.attr="disabled"
                            wire:target="cancel"
                            wire:confirm="Cancel this transfer? This cannot be undone."
                            class="btn btn-ghost inline-flex items-center justify-center gap-1.5 h-10 px-4 text-slate-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-400 disabled:opacity-60">
                        <span wire:loading.remove wire:target="cancel" class="inline-flex items-center gap-1.5">
                            <x-ui.icon name="x" class="w-4 h-4 shrink-0" />
                            <span>Cancel</span>
                        </span>
                        <span wire:loading.inline-flex wire:target="cancel" class="items-center gap-1.5">
                            {!! $spinner !!}<span>Cancelling…</span>
                        </span>
                    </button>
                @endcan
            @endif

            @if($isSubmitted || $isAccepted)
                @can('reject', $wtr)
                    <button type="button"
                            wire:click="openRejectModal"
                            class="btn btn-secondary inline-flex items-center justify-center gap-1.5 h-10 px-4 text-red-600 hover:text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">
                        <x-ui.icon name="x" class="w-4 h-4 shrink-0" />
                        <span>Reject</span>
                    </button>
                @endcan
            @endif

            @if($isDraft)
                @can('submit', $wtr)
                    <button type="button"
                            wire:click="submit"
                            wire:loading.attr="disabled"
                            wire:target="submit"
                            class="btn btn-primary inline-flex items-center justify-center gap-1.5 h-10 px-4 disabled:opacity-60">
                        <span wire:loading.remove wire:target="submit" class="inline-flex items-center gap-1.5">
                            <x-ui.icon name="check" class="w-4 h-4 shrink-0" />
                            <span>Submit</span>
                        </span>
                        <span wire:loading.inline-flex wire:target="submit" class="items-center gap-1.5">
                            {!! $spinner !!}<span>Submitting…</span>
                        </span>
                    </button>
                @endcan
            @endif

            @if($isSubmitted)
                @can('accept', $wtr)
                    <button type="button"
                            wire:click="accept"
                            wire:loading.attr="disabled"
                            wire:target="accept"
                            class="btn btn-success inline-flex items-center justify-center gap-1.5 h-10 px-4 disabled:opacity-60">
                        <span wire:loading.remove wire:target="accept" class="inline-flex items-center gap-1.5">
                            <x-ui.icon name="check" class="w-4 h-4 shrink-0" />
                            <span>Accept</span>
                        </span>
                        <span wire:loading.inline-flex wire:target="accept" class="items-center gap-1.5">
                            {!! $spinner !!}<span>Accepting…</span>
                        </span>
                    </button>
                @endcan
            @endif

            @if($isAccepted)
                @can('dispatch', $wtr)
                    <button type="button"
                            wire:click="confirmDispatch"
                            wire:loading.attr="disabled"
                            wire:target="confirmDispatch"
                            wire:confirm="Dispatch this transfer? Stock will move to in-transit."
                            class="btn btn-primary inline-flex items-center justify-center gap-1.5 h-10 px-4 disabled:opacity-60">
                        <span wire:loading.remove wire:target="confirmDispatch" class="inline-flex items-center gap-1.5">
                            <x-ui.icon name="truck" class="w-4 h-4 shrink-0" />
                            <span>Dispatch</span>
                        </span>
                        <span wire:loading.inline-flex wire:target="confirmDispatch" class="items-center gap-1.5">
                            {!! $spinner !!}<span>Dispatching…</span>
                        </span>
                    </button>
                @endcan
            @endif

            @if($isDispatched)
                @can('receive', $wtr)
                    <button type="button"
                            wire:click="receive"
                            wire:loading.attr="disabled"
                            wire:target="receive"
                            wire:confirm="Confirm receipt at the destination warehouse?"
                            class="btn btn-success inline-flex items-center justify-center gap-1.5 h-10 px-4 disabled:opacity-60">
                        <span wire:loading.remove wire:target="receive" class="inline-flex items-center gap-1.5">
                            <x-ui.icon name="check" class="w-4 h-4 shrink-0" />
                            <span>Receive</span>
                        </span>
                        <span wire:loading.inline-flex wire:target="receive" class="items-center gap-1.5">
                            {!! $spinner !!}<span>Receiving…</span>
                        </span>
                    </button>
                @endcan
            @endif
        </div>
    </div>

    {{-- Rejection banner --}}
    @if($wtr->rejection_reason)
        <div class="flex items-start gap-3 px-4 py-3 border rounded-xl border-red-200 bg-red-50/70 dark:border-red-900/50 dark:bg-red-950/20">
            <x-ui.icon name="exclamation" class="w-5 h-5 mt-0.5 shrink-0 text-red-500" />
            <div class="min-w-0">
                <p class="text-sm font-semibold text-red-700 dark:text-red-400">Transfer rejected</p>
                <p class="mt-0.5 text-sm whitespace-pre-line text-red-600 dark:text-red-400/90">{{ $wtr->rejection_reason }}</p>
            </div>
        </div>
    @endif

    {{-- Route strip --}}
    <div class="card card-body">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_auto_1fr] md:items-center">
            <div class="min-w-0">
                <p class="text-xs font-medium tracking-wide uppercase text-slate-400">From</p>
                <p class="mt-1 text-base font-semibold truncate text-slate-800 dark:text-slate-200">
                    {{ $wtr->fromWarehouse?->name ?? '—' }}
                </p>
                @if($wtr->fromVirtualWarehouse)
                    <p class="mt-0.5 text-xs truncate text-slate-500">{{ $wtr->fromVirtualWarehouse->name }}</p>
                @endif
            </div>

            <div class="flex items-center justify-center w-10 h-10 rounded-full rotate-90 md:rotate-0 bg-slate-100 text-slate-400 dark:bg-slate-800">
                <x-ui.icon name="arrow-right" class="w-4 h-4 shrink-0" />
            </div>

            <div class="min-w-0 md:text-right">
                <p class="text-xs font-medium tracking-wide uppercase text-slate-400">To</p>
                <p class="mt-1 text-base font-semibold truncate text-slate-800 dark:text-slate-200">
                    {{ $wtr->toWarehouse?->name ?? '—' }}
                </p>
                @if($wtr->toVirtualWarehouse)
                    <p class="mt-0.5 text-xs truncate text-slate-500">{{ $wtr->toVirtualWarehouse->name }}</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Main grid --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Lines --}}
        <div class="card min-w-0 lg:col-span-2 self-start">
            <div class="card-header">
                <h3 class="card-title">Lines</h3>
                <span class="badge badge-slate text-xs tabular-nums">{{ $lineCount }}</span>
            </div>
            <div class="overflow-x-auto">
                <x-ui.table>
                    <thead>
                    <tr>
                        <th class="w-10">#</th>
                        <th>Item</th>
                        <th>Source Bin</th>
                        <th>Batch</th>
                        <th class="text-right">Qty</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($wtr->lines as $line)
                        <tr wire:key="wtr-line-{{ $line->id }}">
                            <td class="text-xs tabular-nums text-slate-400">{{ $loop->iteration }}</td>
                            <td class="min-w-[12rem]">
                                <div class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $line->item?->name ?? '—' }}</div>
                                <div class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $line->item?->sku }}</div>
                            </td>
                            <td class="text-sm whitespace-nowrap">
                                @if($line->sourceLocation)
                                    <span class="font-mono text-slate-600 dark:text-slate-300">{{ $line->sourceLocation->code }}</span>
                                @else
                                    <span class="text-slate-300 dark:text-slate-600">—</span>
                                @endif
                            </td>
                            <td class="text-sm whitespace-nowrap">
                                @if($line->batch_no)
                                    <span class="font-mono text-slate-600 dark:text-slate-300">{{ $line->batch_no }}</span>
                                @else
                                    <span class="text-slate-300 dark:text-slate-600">—</span>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <span class="font-semibold tabular-nums text-slate-800 dark:text-slate-200">{{ $fmt($line->qty) }}</span>
                                <span class="ml-1 font-mono text-xs text-slate-500">{{ $line->uom?->code }}</span>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state title="No lines recorded." :cols="5" />
                    @endforelse
                    </tbody>
                </x-ui.table>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-4">

            {{-- Timeline --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Progress</h3>
                </div>
                <div class="card-body">
                    <ol class="relative">
                        @foreach($steps as $step)
                            @php $danger = $step['danger'] ?? false; @endphp
                            <li class="relative flex gap-3 {{ $loop->last ? '' : 'pb-5' }}">
                                @unless($loop->last)
                                    <span class="absolute left-[9px] top-5 bottom-0 w-px {{ $step['done'] ? 'bg-emerald-300 dark:bg-emerald-700' : 'bg-slate-200 dark:bg-slate-700' }}"></span>
                                @endunless

                                <span class="relative z-10 flex items-center justify-center w-5 h-5 mt-0.5 rounded-full shrink-0
                                    {{ $danger ? 'bg-red-500 text-white' : ($step['done'] ? 'bg-emerald-500 text-white' : 'bg-white border-2 border-slate-200 dark:bg-slate-900 dark:border-slate-700') }}">
                                    @if($danger)
                                        <x-ui.icon name="x" class="w-3 h-3 shrink-0" />
                                    @elseif($step['done'])
                                        <x-ui.icon name="check" class="w-3 h-3 shrink-0" />
                                    @endif
                                </span>

                                <div class="min-w-0">
                                    <p class="text-sm font-semibold {{ $danger ? 'text-red-600 dark:text-red-400' : ($step['done'] ? 'text-slate-800 dark:text-slate-200' : 'text-slate-400') }}">
                                        {{ $step['label'] }}
                                    </p>
                                    @if($step['by'])
                                        <p class="text-xs truncate text-slate-500">{{ $step['by'] }}</p>
                                    @endif
                                    @if($step['at'])
                                        <p class="text-xs tabular-nums text-slate-400">{{ $step['at'] }}</p>
                                    @endif
                                    @if(! $step['done'])
                                        <p class="text-xs text-slate-400">Pending</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>

            {{-- References --}}
            @if($wtr->dn_number || $wtr->rcn_number || $wtr->purchaseOrder)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">References</h3>
                    </div>
                    <div class="card-body divide-y divide-slate-100 dark:divide-slate-800">
                        @if($wtr->dn_number)
                            <div class="flex items-center justify-between gap-3 py-2 first:pt-0 last:pb-0">
                                <span class="text-xs font-medium tracking-wide uppercase text-slate-400">Delivery Note</span>
                                <span class="font-mono text-sm text-slate-800 dark:text-slate-200">{{ $wtr->dn_number }}</span>
                            </div>
                        @endif
                        @if($wtr->rcn_number)
                            <div class="flex items-center justify-between gap-3 py-2 first:pt-0 last:pb-0">
                                <span class="text-xs font-medium tracking-wide uppercase text-slate-400">Receipt Note</span>
                                <span class="font-mono text-sm text-slate-800 dark:text-slate-200">{{ $wtr->rcn_number }}</span>
                            </div>
                        @endif
                        @if($wtr->purchaseOrder)
                            <div class="flex items-center justify-between gap-3 py-2 first:pt-0 last:pb-0">
                                <span class="text-xs font-medium tracking-wide uppercase text-slate-400">Linked PO</span>
                                <a href="{{ route('inbound.orders.show', $wtr->purchaseOrder) }}" wire:navigate
                                   class="font-mono text-sm text-simba-600 hover:underline dark:text-simba-400">
                                    {{ $wtr->purchaseOrder->number }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Notes --}}
            @if($wtr->notes)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Notes</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-sm whitespace-pre-line text-slate-600 dark:text-slate-400">{{ $wtr->notes }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Reject Modal --}}
    @if($showRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog" aria-modal="true" aria-labelledby="reject-title"
             x-data
             x-on:keydown.escape.window="$wire.set('showRejectModal', false)">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-[2px]"
                 wire:click="$set('showRejectModal', false)"></div>

            <div class="relative z-10 w-full max-w-md rounded-2xl bg-white shadow-2xl ring-1 ring-black/[0.06] dark:bg-zinc-900 dark:ring-white/[0.08]">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-white/[0.08]">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-8 h-8 rounded-full bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400">
                            <x-ui.icon name="exclamation" class="w-4 h-4 shrink-0" />
                        </span>
                        <h3 id="reject-title" class="text-[15px] font-semibold text-slate-900 dark:text-white">Reject {{ $wtr->number }}</h3>
                    </div>
                    <button type="button" wire:click="$set('showRejectModal', false)"
                            class="icon-btn text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">
                        <x-ui.icon name="x" class="w-4 h-4 shrink-0" />
                    </button>
                </div>

                <form wire:submit="reject">
                    <div class="px-6 py-5">
                        <label class="label">Reason <span class="text-red-500">*</span></label>
                        <textarea wire:model="rejectionReason" rows="3" autofocus
                                  class="input w-full resize-none @error('rejectionReason') border-red-400 @enderror"
                                  placeholder="Explain why this transfer is being rejected…"></textarea>
                        @error('rejectionReason') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-2 px-6 py-4 border-t rounded-b-2xl bg-slate-50/80 border-slate-100 dark:bg-white/[0.02] dark:border-white/[0.08]">
                        <button type="button" wire:click="$set('showRejectModal', false)"
                                class="btn btn-secondary inline-flex items-center justify-center h-10 px-4">
                            Keep Transfer
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                wire:target="reject"
                                class="btn inline-flex items-center justify-center gap-1.5 h-10 px-4 font-semibold text-white bg-red-600 hover:bg-red-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="reject">Reject Transfer</span>
                            <span wire:loading.inline-flex wire:target="reject" class="items-center gap-1.5">
                                {!! $spinner !!}<span>Rejecting…</span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
