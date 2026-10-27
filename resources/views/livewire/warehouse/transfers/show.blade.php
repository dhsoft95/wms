@php
    use App\Enums\TransferStatus as Status;

    $status = $transfer->status;

    $canSubmit   = in_array($status, [Status::Draft, Status::AwaitingRequesterCorrection]);
    $isSubmitted = $status === Status::Submitted;
    $isReturned  = $status === Status::ReturnedForCorrection;
    $needsFix    = in_array($status, [Status::ReturnedForCorrection, Status::AwaitingRequesterCorrection]);
    $canExecute  = $status === Status::Accepted && $transfer->is_physical;

    $lineCount  = $transfer->lines->count();
    $lastReturn = $transfer->approvals->where('decision', 'returned')->sortByDesc('decided_at')->first();

    $fmt = fn ($qty) => rtrim(rtrim(number_format((float) $qty, 4, '.', ','), '0'), '.');
    $dt  = fn ($date) => $date?->format('d M Y · H:i');

    $decisionStyles = [
        'approved' => ['badge' => 'badge-green',  'label' => 'Approved', 'dot' => 'bg-emerald-500 text-white', 'icon' => 'check'],
        'rejected' => ['badge' => 'badge-red',    'label' => 'Rejected', 'dot' => 'bg-red-500 text-white',     'icon' => 'x'],
        'returned' => ['badge' => 'badge-orange', 'label' => 'Returned', 'dot' => 'bg-amber-500 text-white',   'icon' => 'arrow-left'],
    ];
    $pendingStyle = ['badge' => 'badge-amber', 'label' => 'Pending', 'dot' => 'bg-white border-2 border-slate-200 dark:bg-slate-900 dark:border-slate-700', 'icon' => null];

    $spinner = '<svg class="w-4 h-4 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>';
@endphp

<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div class="min-w-0">
            <a href="{{ route('warehouse.transfers.index') }}" wire:navigate
               class="inline-flex items-center gap-1.5 mb-2 text-xs font-semibold text-slate-500 transition-colors hover:text-simba-600 dark:text-slate-400 dark:hover:text-simba-400">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5 shrink-0" />
                <span>Back to Transfers</span>
            </a>
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="page-title font-mono truncate">{{ $transfer->number }}</h2>
                <span class="badge {{ $status->badge() }}">
                    <span class="dot"></span>{{ $status->label() }}
                </span>
                <span class="badge badge-slate text-xs">
                    {{ $transfer->is_physical ? 'Physical move' : 'Ownership only' }}
                </span>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if($canSubmit)
                @can('submit', $transfer)
                    <button type="button"
                            wire:click="submit"
                            wire:loading.attr="disabled"
                            wire:target="submit"
                            class="btn btn-primary inline-flex items-center justify-center gap-1.5 h-10 px-4 disabled:opacity-60">
                        <span wire:loading.remove wire:target="submit" class="inline-flex items-center gap-1.5">
                            <x-ui.icon name="check" class="w-4 h-4 shrink-0" />
                            <span>{{ $needsFix ? 'Resubmit for Approval' : 'Submit for Approval' }}</span>
                        </span>
                        <span wire:loading.inline-flex wire:target="submit" class="items-center gap-1.5">
                            {!! $spinner !!}<span>Submitting…</span>
                        </span>
                    </button>
                @endcan

            @elseif($isSubmitted)
                @can('returnOrder', $transfer)
                    <button type="button"
                            wire:click="openReturnModal"
                            class="btn btn-secondary inline-flex items-center justify-center gap-1.5 h-10 px-4 text-amber-600 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-500/10">
                        <x-ui.icon name="arrow-left" class="w-4 h-4 shrink-0" />
                        <span>Send Back</span>
                    </button>
                @endcan

                @can('accept', $transfer)
                    <button type="button"
                            wire:click="openRejectModal"
                            class="btn btn-secondary inline-flex items-center justify-center gap-1.5 h-10 px-4 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">
                        <x-ui.icon name="x" class="w-4 h-4 shrink-0" />
                        <span>Reject</span>
                    </button>
                @endcan

                @can('approveDepartment', $transfer)
                    <button type="button"
                            wire:click="approveDepartment"
                            wire:loading.attr="disabled"
                            wire:target="approveDepartment"
                            class="btn btn-success inline-flex items-center justify-center gap-1.5 h-10 px-4 disabled:opacity-60">
                        <span wire:loading.remove wire:target="approveDepartment" class="inline-flex items-center gap-1.5">
                            <x-ui.icon name="check" class="w-4 h-4 shrink-0" />
                            <span>Approve</span>
                        </span>
                        <span wire:loading.inline-flex wire:target="approveDepartment" class="items-center gap-1.5">
                            {!! $spinner !!}<span>Approving…</span>
                        </span>
                    </button>
                @endcan

                @can('accept', $transfer)
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

            @elseif($isReturned)
                @can('forwardReturn', $transfer)
                    <button type="button"
                            wire:click="forwardReturn"
                            wire:loading.attr="disabled"
                            wire:target="forwardReturn"
                            class="btn btn-primary inline-flex items-center justify-center gap-1.5 h-10 px-4 disabled:opacity-60">
                        <span wire:loading.remove wire:target="forwardReturn" class="inline-flex items-center gap-1.5">
                            <x-ui.icon name="arrow-right" class="w-4 h-4 shrink-0" />
                            <span>Forward to Requester</span>
                        </span>
                        <span wire:loading.inline-flex wire:target="forwardReturn" class="items-center gap-1.5">
                            {!! $spinner !!}<span>Forwarding…</span>
                        </span>
                    </button>
                @endcan

            @elseif($canExecute)
                @can('execute', $transfer)
                    <button type="button"
                            wire:click="execute"
                            wire:loading.attr="disabled"
                            wire:target="execute"
                            wire:confirm="Execute this transfer? Stock will move and this cannot be undone."
                            class="btn btn-primary inline-flex items-center justify-center gap-1.5 h-10 px-4 disabled:opacity-60">
                        <span wire:loading.remove wire:target="execute" class="inline-flex items-center gap-1.5">
                            <x-ui.icon name="check" class="w-4 h-4 shrink-0" />
                            <span>Execute Transfer</span>
                        </span>
                        <span wire:loading.inline-flex wire:target="execute" class="items-center gap-1.5">
                            {!! $spinner !!}<span>Executing…</span>
                        </span>
                    </button>
                @endcan
            @endif
        </div>
    </div>

    {{-- Status banners --}}
    @if($transfer->rejection_reason)
        <div class="flex items-start gap-3 px-4 py-3 border rounded-xl border-red-200 bg-red-50/70 dark:border-red-900/50 dark:bg-red-950/20">
            <x-ui.icon name="exclamation" class="w-5 h-5 mt-0.5 shrink-0 text-red-500" />
            <div class="min-w-0">
                <p class="text-sm font-semibold text-red-700 dark:text-red-400">Transfer rejected</p>
                <p class="mt-0.5 text-sm whitespace-pre-line text-red-600 dark:text-red-400/90">{{ $transfer->rejection_reason }}</p>
            </div>
        </div>
    @elseif($needsFix && $lastReturn)
        <div class="flex items-start gap-3 px-4 py-3 border rounded-xl border-amber-200 bg-amber-50/70 dark:border-amber-900/50 dark:bg-amber-950/20">
            <x-ui.icon name="exclamation" class="w-5 h-5 mt-0.5 shrink-0 text-amber-500" />
            <div class="min-w-0">
                <p class="text-sm font-semibold text-amber-700 dark:text-amber-400">
                    Sent back for correction
                    @if($lastReturn->decidedBy)
                        <span class="font-normal">by {{ $lastReturn->decidedBy->name }}</span>
                    @endif
                </p>
                @if($lastReturn->notes)
                    <p class="mt-0.5 text-sm whitespace-pre-line text-amber-700/90 dark:text-amber-400/90">{{ $lastReturn->notes }}</p>
                @endif
            </div>
        </div>
    @endif

    {{-- Transfer route --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Transfer Route</h3>
            <span class="text-xs text-slate-400">
                Created by
                <span class="font-medium text-slate-600 dark:text-slate-300">{{ $transfer->createdBy?->name ?? '—' }}</span>
                · <span class="tabular-nums">{{ $dt($transfer->created_at) ?? '—' }}</span>
            </span>
        </div>

        <div class="card-body">
            <div class="grid grid-cols-1 items-stretch gap-3 md:grid-cols-[1fr_auto_1fr]">

                {{-- From --}}
                <div class="flex items-center gap-3 p-4 border rounded-xl border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/50">
                    <span class="flex items-center justify-center w-10 h-10 rounded-lg shrink-0 bg-white text-slate-500 ring-1 ring-slate-200 dark:bg-slate-900 dark:text-slate-400 dark:ring-slate-700">
                        <x-ui.icon name="archive" class="w-5 h-5 shrink-0" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold tracking-wide uppercase text-slate-400">From</p>
                        <p class="mt-0.5 text-base font-semibold truncate text-slate-800 dark:text-slate-100"
                           title="{{ $transfer->fromVirtualWarehouse?->name }}">
                            {{ $transfer->fromVirtualWarehouse?->name ?? 'Not set' }}
                        </p>
                    </div>
                </div>

                {{-- Arrow --}}
                <div class="flex items-center justify-center">
                    <span class="flex items-center justify-center w-9 h-9 rounded-full rotate-90 md:rotate-0 bg-simba-50 text-simba-600 dark:bg-simba-500/10 dark:text-simba-400">
                        <x-ui.icon name="arrow-right" class="w-4 h-4 shrink-0" />
                    </span>
                </div>

                {{-- To --}}
                <div class="flex items-center gap-3 p-4 border rounded-xl border-simba-200 bg-simba-50/50 dark:border-simba-500/30 dark:bg-simba-500/5">
                    <span class="flex items-center justify-center w-10 h-10 rounded-lg shrink-0 bg-white text-simba-600 ring-1 ring-simba-200 dark:bg-slate-900 dark:text-simba-400 dark:ring-simba-500/30">
                        <x-ui.icon name="archive" class="w-5 h-5 shrink-0" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold tracking-wide uppercase text-simba-600/70 dark:text-simba-400/70">To</p>
                        <p class="mt-0.5 text-base font-semibold truncate text-slate-800 dark:text-slate-100"
                           title="{{ $transfer->toVirtualWarehouse?->name }}">
                            {{ $transfer->toVirtualWarehouse?->name ?? 'Not set' }}
                        </p>
                    </div>
                </div>

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
                        <th>Bin</th>
                        <th>Batch</th>
                        <th class="text-right">Qty</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($transfer->lines as $line)
                        <tr wire:key="trf-line-{{ $line->id }}">
                            <td class="text-xs tabular-nums text-slate-400">{{ $loop->iteration }}</td>
                            <td class="min-w-[12rem]">
                                <div class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $line->item?->name ?? '—' }}</div>
                                <div class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $line->item?->sku }}</div>
                            </td>
                            <td class="whitespace-nowrap">
                                @if($line->location)
                                    <span class="badge badge-slate font-mono text-xs">{{ $line->location->code }}</span>
                                @else
                                    <span class="text-slate-300 dark:text-slate-600">—</span>
                                @endif
                            </td>
                            <td class="text-sm whitespace-nowrap">
                                @if($line->batch?->batch_no)
                                    <span class="font-mono text-slate-600 dark:text-slate-300">{{ $line->batch->batch_no }}</span>
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

            {{-- Approvals timeline --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Approvals</h3>
                </div>
                <div class="card-body">
                    @if($transfer->approvals->isEmpty())
                        <p class="text-sm text-slate-400">No approvals yet. They will appear here once the transfer is submitted.</p>
                    @else
                        <ol>
                            @foreach($transfer->approvals as $approval)
                                @php $style = $decisionStyles[$approval->decision] ?? $pendingStyle; @endphp
                                <li wire:key="approval-{{ $approval->id }}" class="relative flex gap-3 {{ $loop->last ? '' : 'pb-5' }}">
                                    @unless($loop->last)
                                        <span class="absolute left-[9px] top-5 bottom-0 w-px bg-slate-200 dark:bg-slate-700"></span>
                                    @endunless

                                    <span class="relative z-10 flex items-center justify-center w-5 h-5 mt-0.5 rounded-full shrink-0 {{ $style['dot'] }}">
                                        @if($style['icon'])
                                            <x-ui.icon :name="$style['icon']" class="w-3 h-3 shrink-0" />
                                        @endif
                                    </span>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-start justify-between gap-2">
                                            <p class="text-sm font-semibold capitalize text-slate-800 dark:text-slate-200">
                                                {{ $approval->approver_role }}
                                                @if($approval->level)
                                                    <span class="text-xs font-normal normal-case text-slate-400">· Level {{ $approval->level }}</span>
                                                @endif
                                            </p>
                                            <span class="badge {{ $style['badge'] }} shrink-0 text-xs">{{ $style['label'] }}</span>
                                        </div>

                                        @if($approval->decidedBy)
                                            <p class="text-xs truncate text-slate-500">{{ $approval->decidedBy->name }}</p>
                                        @endif
                                        <p class="text-xs tabular-nums text-slate-400">
                                            {{ $dt($approval->decided_at) ?? 'Requested ' . $dt($approval->created_at) }}
                                        </p>

                                        @if($approval->notes)
                                            <p class="px-3 py-2 mt-2 text-sm whitespace-pre-line rounded-lg bg-slate-50 text-slate-600 dark:bg-slate-800/50 dark:text-slate-400">{{ $approval->notes }}</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </div>

            {{-- Execution --}}
            @if($transfer->acceptedBy || $transfer->posted_at)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Execution</h3>
                    </div>
                    <div class="card-body divide-y divide-slate-100 dark:divide-slate-800">
                        @if($transfer->acceptedBy)
                            <div class="flex items-center justify-between gap-3 py-2 first:pt-0 last:pb-0">
                                <span class="text-xs font-medium tracking-wide uppercase text-slate-400">Accepted By</span>
                                <span class="text-sm font-medium truncate text-slate-800 dark:text-slate-200">{{ $transfer->acceptedBy->name }}</span>
                            </div>
                        @endif
                        @if($transfer->posted_at)
                            <div class="flex items-center justify-between gap-3 py-2 first:pt-0 last:pb-0">
                                <span class="text-xs font-medium tracking-wide uppercase text-slate-400">Posted</span>
                                <span class="text-sm tabular-nums text-slate-800 dark:text-slate-200">{{ $dt($transfer->posted_at) }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Notes --}}
            @if($transfer->notes)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Notes</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-sm whitespace-pre-line text-slate-600 dark:text-slate-400">{{ $transfer->notes }}</p>
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
                            <x-ui.icon name="x" class="w-4 h-4 shrink-0" />
                        </span>
                        <h3 id="reject-title" class="text-[15px] font-semibold text-slate-900 dark:text-white">Reject {{ $transfer->number }}</h3>
                    </div>
                    <button type="button" wire:click="$set('showRejectModal', false)"
                            class="icon-btn text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">
                        <x-ui.icon name="x" class="w-4 h-4 shrink-0" />
                    </button>
                </div>

                <form wire:submit="reject">
                    <div class="px-6 py-5">
                        <label class="label">Reason <span class="required-mark">*</span></label>
                        <textarea wire:model="rejectionReason" rows="3" autofocus
                                  class="input w-full resize-none @error('rejectionReason') border-red-400 @enderror"
                                  placeholder="Explain why this transfer is being rejected…"></textarea>
                        @error('rejectionReason') <p class="hint hint-error">{{ $message }}</p> @enderror
                        <p class="hint mt-1 text-slate-400">Rejection is final. Use Send Back if the requester can fix it.</p>
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

    {{-- Send Back Modal --}}
    @if($showReturnModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog" aria-modal="true" aria-labelledby="return-title"
             x-data
             x-on:keydown.escape.window="$wire.set('showReturnModal', false)">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-[2px]"
                 wire:click="$set('showReturnModal', false)"></div>

            <div class="relative z-10 w-full max-w-md rounded-2xl bg-white shadow-2xl ring-1 ring-black/[0.06] dark:bg-zinc-900 dark:ring-white/[0.08]">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-white/[0.08]">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-8 h-8 rounded-full bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                            <x-ui.icon name="arrow-left" class="w-4 h-4 shrink-0" />
                        </span>
                        <h3 id="return-title" class="text-[15px] font-semibold text-slate-900 dark:text-white">Send Back for Correction</h3>
                    </div>
                    <button type="button" wire:click="$set('showReturnModal', false)"
                            class="icon-btn text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">
                        <x-ui.icon name="x" class="w-4 h-4 shrink-0" />
                    </button>
                </div>

                <form wire:submit="sendBack">
                    <div class="px-6 py-5">
                        <label class="label">What needs to be corrected? <span class="required-mark">*</span></label>
                        <textarea wire:model="returnNotes" rows="3" autofocus
                                  class="input w-full resize-none @error('returnNotes') border-red-400 @enderror"
                                  placeholder="e.g. Wrong bin on line 2, quantity exceeds request…"></textarea>
                        @error('returnNotes') <p class="hint hint-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-2 px-6 py-4 border-t rounded-b-2xl bg-slate-50/80 border-slate-100 dark:bg-white/[0.02] dark:border-white/[0.08]">
                        <button type="button" wire:click="$set('showReturnModal', false)"
                                class="btn btn-secondary inline-flex items-center justify-center h-10 px-4">
                            Cancel
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                wire:target="sendBack"
                                class="btn inline-flex items-center justify-center gap-1.5 h-10 px-4 font-semibold text-white bg-amber-500 hover:bg-amber-600 disabled:opacity-60">
                            <span wire:loading.remove wire:target="sendBack">Send Back</span>
                            <span wire:loading.inline-flex wire:target="sendBack" class="items-center gap-1.5">
                                {!! $spinner !!}<span>Sending…</span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
