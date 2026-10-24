@php
    use App\Enums\TransferStatus as Status;

    $status = $transfer->status;

    $canSubmit   = in_array($status, [Status::Draft, Status::AwaitingRequesterCorrection]);
    $isSubmitted = $status === Status::Submitted;
    $isReturned  = $status === Status::ReturnedForCorrection;
    $needsFix    = in_array($status, [Status::ReturnedForCorrection, Status::AwaitingRequesterCorrection]);
    $canExecute  = $status === Status::Accepted && $transfer->is_physical;

    $pendingApproval = $isSubmitted
        ? $transfer->approvals->whereNotIn('decision', ['approved', 'rejected', 'returned'])->sortBy('level')->first()
        : null;
    $awaitingApproval   = $pendingApproval !== null;
    $awaitingAcceptance = $isSubmitted && ! $awaitingApproval;

    $statusLabel = $awaitingApproval ? 'Pending Approval' : $status->label();

    $lineCount  = $transfer->lines->count();
    $lastReturn = $transfer->approvals->where('decision', 'returned')->sortByDesc('decided_at')->first();

    $fmt = fn ($qty) => rtrim(rtrim(number_format((float) $qty, 4, '.', ','), '0'), '.');
    $dt  = fn ($date) => $date?->format('d M Y · H:i');

    $decisionStyles = [
        'approved' => ['text' => 'text-emerald-600 dark:text-emerald-400', 'label' => 'Approved', 'dot' => 'bg-emerald-500 text-white', 'icon' => 'check'],
        'rejected' => ['text' => 'text-red-600 dark:text-red-400',         'label' => 'Rejected', 'dot' => 'bg-red-500 text-white',     'icon' => 'x'],
        'returned' => ['text' => 'text-amber-600 dark:text-amber-400',     'label' => 'Returned', 'dot' => 'bg-amber-500 text-white',   'icon' => 'arrow-left'],
    ];
    $pendingStyle = ['text' => 'text-amber-600 dark:text-amber-400', 'label' => 'Pending', 'dot' => 'bg-white border-2 border-slate-300 dark:bg-slate-900 dark:border-slate-600', 'icon' => null];

    $btn      = 'btn inline-flex items-center justify-center gap-1.5 h-9 px-3.5 text-sm disabled:opacity-60';
    $btnGhost = 'inline-flex items-center justify-center gap-1.5 h-9 px-3 text-sm font-medium rounded-lg transition-colors';

    $spinner = '<svg class="w-4 h-4 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>';
@endphp

<div class="space-y-4">

    {{-- Header --}}
    <div>
        <a href="{{ route('warehouse.transfers.index') }}" wire:navigate
           class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 transition-colors hover:text-simba-600 dark:text-slate-400 dark:hover:text-simba-400">
            <x-ui.icon name="arrow-left" class="w-3.5 h-3.5 shrink-0" />
            <span>Back to Transfers</span>
        </a>

        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <h2 class="page-title font-mono truncate">{{ $transfer->number }}</h2>
                    <span class="badge {{ $status->badge() }}">
                        <span class="dot"></span>{{ $statusLabel }}
                    </span>
                    <span class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $transfer->is_physical ? 'Physical move' : 'Ownership only' }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ $transfer->fromVirtualWarehouse?->name ?? '—' }}</span>
                    <span class="mx-1 text-slate-400">&rarr;</span>
                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ $transfer->toVirtualWarehouse?->name ?? '—' }}</span>
                    <span class="mx-2 text-slate-300 dark:text-slate-600">·</span>
                    {{ $transfer->createdBy?->name ?? '—' }}, <span class="tabular-nums">{{ $dt($transfer->created_at) ?? '—' }}</span>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-1.5">
                @if($canSubmit)
                    @can('submit', $transfer)
                        <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit"
                                class="{{ $btn }} btn-primary">
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
                        <button type="button" wire:click="openReturnModal"
                                class="{{ $btnGhost }} text-amber-600 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-500/10">
                            <x-ui.icon name="arrow-left" class="w-4 h-4 shrink-0" />
                            <span>Send Back</span>
                        </button>
                    @endcan

                    @can('reject', $transfer)
                        <button type="button" wire:click="openRejectModal"
                                class="{{ $btnGhost }} text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">
                            <x-ui.icon name="x" class="w-4 h-4 shrink-0" />
                            <span>Reject</span>
                        </button>
                    @endcan

                    @if($awaitingApproval)
                        @can('approveDepartment', $transfer)
                            <button type="button" wire:click="approveDepartment" wire:loading.attr="disabled" wire:target="approveDepartment"
                                    class="{{ $btn }} btn-success">
                                <span wire:loading.remove wire:target="approveDepartment" class="inline-flex items-center gap-1.5">
                                    <x-ui.icon name="check" class="w-4 h-4 shrink-0" />
                                    <span>Approve</span>
                                </span>
                                <span wire:loading.inline-flex wire:target="approveDepartment" class="items-center gap-1.5">
                                    {!! $spinner !!}<span>Approving…</span>
                                </span>
                            </button>
                        @endcan
                    @elseif($awaitingAcceptance)
                        @can('accept', $transfer)
                            <button type="button" wire:click="accept" wire:loading.attr="disabled" wire:target="accept"
                                    class="{{ $btn }} btn-success">
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

                @elseif($isReturned)
                    @can('forwardReturn', $transfer)
                        <button type="button" wire:click="forwardReturn" wire:loading.attr="disabled" wire:target="forwardReturn"
                                class="{{ $btn }} btn-primary">
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
                        <button type="button" wire:click="execute" wire:loading.attr="disabled" wire:target="execute"
                                wire:confirm="Execute this transfer? Stock will move and this cannot be undone."
                                class="{{ $btn }} btn-primary">
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
    </div>

    {{-- Status banners --}}
    @if($transfer->rejection_reason)
        <div class="flex items-start gap-2.5 px-4 py-2.5 rounded-lg bg-red-50 dark:bg-red-950/20">
            <x-ui.icon name="exclamation" class="w-4 h-4 mt-0.5 shrink-0 text-red-500" />
            <p class="text-sm text-red-700 dark:text-red-400">
                <span class="font-semibold">Rejected.</span>
                <span class="whitespace-pre-line">{{ $transfer->rejection_reason }}</span>
            </p>
        </div>
    @elseif($needsFix && $lastReturn)
        <div class="flex items-start gap-2.5 px-4 py-2.5 rounded-lg bg-amber-50 dark:bg-amber-950/20">
            <x-ui.icon name="exclamation" class="w-4 h-4 mt-0.5 shrink-0 text-amber-500" />
            <p class="text-sm text-amber-700 dark:text-amber-400">
                <span class="font-semibold">Sent back for correction</span>@if($lastReturn->decidedBy) by {{ $lastReturn->decidedBy->name }}@endif.
                @if($lastReturn->notes)
                    <span class="whitespace-pre-line">{{ $lastReturn->notes }}</span>
                @endif
            </p>
        </div>
    @endif

    {{-- Body --}}
    <div class="card overflow-hidden">
        <div class="grid grid-cols-1 divide-y divide-slate-100 lg:grid-cols-3 lg:divide-x lg:divide-y-0 dark:divide-slate-800">

            {{-- Lines --}}
            <section class="min-w-0 lg:col-span-2">
                <div class="px-5 pt-4 pb-2">
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                        Lines <span class="font-normal tabular-nums text-slate-400">({{ $lineCount }})</span>
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <x-ui.table>
                        <thead>
                        <tr>
                            <th class="w-10">#</th>
                            <th>Item</th>
                            <th class="w-48">Bin</th>
                            <th class="w-32">Batch</th>
                            <th class="w-28 text-right">Qty</th>
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
                                <td class="whitespace-nowrap font-mono text-xs text-slate-700 dark:text-slate-300">
                                    {{ $line->location?->code ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap font-mono text-xs text-slate-600 dark:text-slate-300">
                                    {{ $line->batch?->batch_no ?? '—' }}
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
            </section>

            {{-- Sidebar --}}
            <aside class="divide-y divide-slate-100 dark:divide-slate-800">

                <section class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Approvals</h3>

                    @if($transfer->approvals->isEmpty())
                        <p class="mt-2 text-sm text-slate-400">Approvals appear here once the transfer is submitted.</p>
                    @else
                        <ol class="mt-3">
                            @foreach($transfer->approvals as $approval)
                                @php $style = $decisionStyles[$approval->decision] ?? $pendingStyle; @endphp
                                <li wire:key="approval-{{ $approval->id }}" class="relative flex gap-3 {{ $loop->last ? '' : 'pb-4' }}">
                                    @unless($loop->last)
                                        <span class="absolute left-[9px] top-5 bottom-0 w-px bg-slate-200 dark:bg-slate-700"></span>
                                    @endunless

                                    <span class="relative z-10 flex items-center justify-center w-5 h-5 mt-0.5 rounded-full shrink-0 {{ $style['dot'] }}">
                                        @if($style['icon'])
                                            <x-ui.icon :name="$style['icon']" class="w-3 h-3 shrink-0" />
                                        @endif
                                    </span>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-baseline justify-between gap-2">
                                            <p class="text-sm font-medium capitalize text-slate-800 dark:text-slate-200">
                                                {{ $approval->approver_role }}
                                                @if($approval->level)
                                                    <span class="text-xs font-normal normal-case text-slate-400">· Level {{ $approval->level }}</span>
                                                @endif
                                            </p>
                                            <span class="shrink-0 text-xs font-medium {{ $style['text'] }}">{{ $style['label'] }}</span>
                                        </div>
                                        <p class="text-xs text-slate-400">
                                            @if($approval->decidedBy){{ $approval->decidedBy->name }} · @endif
                                            <span class="tabular-nums">{{ $dt($approval->decided_at) ?? 'Requested ' . $dt($approval->created_at) }}</span>
                                        </p>
                                        @if($approval->notes)
                                            <p class="mt-1 text-sm whitespace-pre-line text-slate-600 dark:text-slate-400">{{ $approval->notes }}</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>

                @if($transfer->acceptedBy || $transfer->posted_at)
                    <section class="px-5 py-4">
                        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Execution</h3>
                        <dl class="mt-2 space-y-1.5 text-sm">
                            @if($transfer->acceptedBy)
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">Accepted by</dt>
                                    <dd class="truncate font-medium text-slate-800 dark:text-slate-200">{{ $transfer->acceptedBy->name }}</dd>
                                </div>
                            @endif
                            @if($transfer->posted_at)
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">Posted</dt>
                                    <dd class="tabular-nums text-slate-800 dark:text-slate-200">{{ $dt($transfer->posted_at) }}</dd>
                                </div>
                            @endif
                        </dl>
                    </section>
                @endif

                @if($transfer->notes)
                    <section class="px-5 py-4">
                        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Notes</h3>
                        <p class="mt-1.5 text-sm whitespace-pre-line text-slate-600 dark:text-slate-400">{{ $transfer->notes }}</p>
                    </section>
                @endif
            </aside>
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
                <form wire:submit="reject">
                    <div class="px-6 pt-5 pb-4">
                        <div class="flex items-start justify-between gap-3">
                            <h3 id="reject-title" class="text-[15px] font-semibold text-slate-900 dark:text-white">Reject {{ $transfer->number }}</h3>
                            <button type="button" wire:click="$set('showRejectModal', false)"
                                    class="icon-btn -mr-2 -mt-1 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">
                                <x-ui.icon name="x" class="w-4 h-4 shrink-0" />
                            </button>
                        </div>
                        <p class="mt-1 text-sm text-slate-500">Rejection is final. Use Send Back if the requester can fix it.</p>

                        <label class="label mt-4">Reason <span class="text-red-500">*</span></label>
                        <textarea wire:model="rejectionReason" rows="3" autofocus
                                  class="input w-full resize-none @error('rejectionReason') border-red-400 @enderror"
                                  placeholder="Explain why this transfer is being rejected…"></textarea>
                        @error('rejectionReason') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-2 px-6 pb-5">
                        <button type="button" wire:click="$set('showRejectModal', false)"
                                class="btn btn-secondary inline-flex items-center justify-center h-9 px-3.5 text-sm">
                            Keep Transfer
                        </button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="reject"
                                class="btn inline-flex items-center justify-center gap-1.5 h-9 px-3.5 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 disabled:opacity-60">
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
                <form wire:submit="sendBack">
                    <div class="px-6 pt-5 pb-4">
                        <div class="flex items-start justify-between gap-3">
                            <h3 id="return-title" class="text-[15px] font-semibold text-slate-900 dark:text-white">Send Back for Correction</h3>
                            <button type="button" wire:click="$set('showReturnModal', false)"
                                    class="icon-btn -mr-2 -mt-1 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">
                                <x-ui.icon name="x" class="w-4 h-4 shrink-0" />
                            </button>
                        </div>

                        <label class="label mt-4">What needs to be corrected? <span class="text-red-500">*</span></label>
                        <textarea wire:model="returnNotes" rows="3" autofocus
                                  class="input w-full resize-none @error('returnNotes') border-red-400 @enderror"
                                  placeholder="e.g. Wrong bin on line 2, quantity exceeds request…"></textarea>
                        @error('returnNotes') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-2 px-6 pb-5">
                        <button type="button" wire:click="$set('showReturnModal', false)"
                                class="btn btn-secondary inline-flex items-center justify-center h-9 px-3.5 text-sm">
                            Cancel
                        </button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="sendBack"
                                class="btn inline-flex items-center justify-center gap-1.5 h-9 px-3.5 text-sm font-semibold text-white bg-amber-500 hover:bg-amber-600 disabled:opacity-60">
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
