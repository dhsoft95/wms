
@php
    use App\Enums\TransferStatus as Status;

    $status = $transfer->status;

    $canSubmit = in_array($status, [
        Status::Draft,
        Status::AwaitingRequesterCorrection,
    ]);

    $isSubmitted = $status === Status::Submitted;
    $isReturned = $status === Status::ReturnedForCorrection;

    $needsFix = in_array($status, [
        Status::ReturnedForCorrection,
        Status::AwaitingRequesterCorrection,
    ]);

    $canExecute = $status === Status::Accepted && $transfer->is_physical;

    $lineCount = $transfer->lines->count();
    $lastReturn = $transfer->approvals
        ->where('decision', 'returned')
        ->sortByDesc('decided_at')
        ->first();

    $fmt = fn ($qty) => rtrim(
        rtrim(number_format((float) $qty, 4, '.', ','), '0'),
        '.'
    );

    $dt = fn ($date) => $date?->format('d M Y · H:i');

    $decisionStyles = [
        'approved' => [
            'badge' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20',
            'label' => 'Approved',
            'dot' => 'bg-emerald-500 text-white',
            'icon' => 'check',
        ],
        'rejected' => [
            'badge' => 'bg-red-50 text-red-700 ring-1 ring-red-200 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/20',
            'label' => 'Rejected',
            'dot' => 'bg-red-500 text-white',
            'icon' => 'x',
        ],
        'returned' => [
            'badge' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/20',
            'label' => 'Returned',
            'dot' => 'bg-amber-500 text-white',
            'icon' => 'arrow-left',
        ],
    ];

    $pendingStyle = [
        'badge' => 'bg-slate-100 text-slate-600 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
        'label' => 'Pending',
        'dot' => 'border-2 border-slate-300 bg-white dark:border-slate-600 dark:bg-slate-900',
        'icon' => null,
    ];

    $spinner = '<svg class="w-4 h-4 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>';
@endphp

<div class="space-y-4 text-slate-800 dark:text-slate-100">

    {{-- =========================================================
         PAGE HEADER
    ========================================================== --}}
    <div class="space-y-3">

        <a href="{{ route('warehouse.transfers.index') }}"
           wire:navigate
           class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 transition hover:text-rose-800 dark:text-slate-400 dark:hover:text-rose-400">
            <x-ui.icon name="arrow-left" class="h-4 w-4" />
            <span>Back to Transfers</span>
        </a>

        <div class="flex flex-col justify-between gap-3 xl:flex-row xl:items-center">

            {{-- Transfer identity --}}
            <div class="flex min-w-0 items-center gap-3">

                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-900 dark:bg-rose-950/60 dark:text-rose-300">
                    <x-ui.icon name="arrow-left-right" class="h-5 w-5" />
                </div>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="truncate font-mono text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">
                            {{ $transfer->number }}
                        </h1>

                        <span class="badge {{ $status->badge() }}">
                            <span class="dot"></span>
                            {{ $status->label() }}
                        </span>

                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            {{ $transfer->is_physical ? 'Physical move' : 'Ownership only' }}
                        </span>
                    </div>

                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ $lineCount }} {{ $lineCount === 1 ? 'line item' : 'line items' }}
                        <span class="mx-1.5 text-slate-300">·</span>
                        Created {{ $dt($transfer->created_at) ?? '—' }}
                    </p>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex flex-wrap items-center gap-2 xl:justify-end">

                @if($canSubmit)
                    @can('submit', $transfer)
                        <button type="button"
                                wire:click="submit"
                                wire:loading.attr="disabled"
                                wire:target="submit"
                                class="btn btn-primary inline-flex h-9 items-center justify-center gap-1.5 px-3 disabled:opacity-60">
                            <span wire:loading.remove wire:target="submit"
                                  class="inline-flex items-center gap-1.5">
                                <x-ui.icon name="check" class="h-4 w-4" />
                                {{ $needsFix ? 'Resubmit' : 'Submit for Approval' }}
                            </span>
                            <span wire:loading.inline-flex wire:target="submit"
                                  class="items-center gap-1.5">
                                {!! $spinner !!} Submitting…
                            </span>
                        </button>
                    @endcan

                @elseif($isSubmitted)

                    @can('returnOrder', $transfer)
                        <button type="button"
                                wire:click="openReturnModal"
                                class="btn btn-secondary inline-flex h-9 items-center justify-center gap-1.5 px-3 text-amber-700 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-500/10">
                            <x-ui.icon name="arrow-left" class="h-4 w-4" />
                            Send Back
                        </button>
                    @endcan

                    @can('accept', $transfer)
                        <button type="button"
                                wire:click="openRejectModal"
                                class="btn btn-secondary inline-flex h-9 items-center justify-center gap-1.5 px-3 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">
                            <x-ui.icon name="x" class="h-4 w-4" />
                            Reject
                        </button>
                    @endcan

                    @can('approveDepartment', $transfer)
                        <button type="button"
                                wire:click="approveDepartment"
                                wire:loading.attr="disabled"
                                wire:target="approveDepartment"
                                class="btn btn-success inline-flex h-9 items-center justify-center gap-1.5 px-3 disabled:opacity-60">
                            <span wire:loading.remove wire:target="approveDepartment"
                                  class="inline-flex items-center gap-1.5">
                                <x-ui.icon name="check" class="h-4 w-4" />
                                Approve
                            </span>
                            <span wire:loading.inline-flex wire:target="approveDepartment"
                                  class="items-center gap-1.5">
                                {!! $spinner !!} Approving…
                            </span>
                        </button>
                    @endcan

                    @can('accept', $transfer)
                        <button type="button"
                                wire:click="accept"
                                wire:loading.attr="disabled"
                                wire:target="accept"
                                class="btn btn-success inline-flex h-9 items-center justify-center gap-1.5 px-3 disabled:opacity-60">
                            <span wire:loading.remove wire:target="accept"
                                  class="inline-flex items-center gap-1.5">
                                <x-ui.icon name="check" class="h-4 w-4" />
                                Accept
                            </span>
                            <span wire:loading.inline-flex wire:target="accept"
                                  class="items-center gap-1.5">
                                {!! $spinner !!} Accepting…
                            </span>
                        </button>
                    @endcan

                @elseif($isReturned)

                    @can('forwardReturn', $transfer)
                        <button type="button"
                                wire:click="forwardReturn"
                                wire:loading.attr="disabled"
                                wire:target="forwardReturn"
                                class="btn btn-primary inline-flex h-9 items-center justify-center gap-1.5 px-3 disabled:opacity-60">
                            <span wire:loading.remove wire:target="forwardReturn"
                                  class="inline-flex items-center gap-1.5">
                                <x-ui.icon name="arrow-right" class="h-4 w-4" />
                                Forward to Requester
                            </span>
                            <span wire:loading.inline-flex wire:target="forwardReturn"
                                  class="items-center gap-1.5">
                                {!! $spinner !!} Forwarding…
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
                                class="btn btn-primary inline-flex h-9 items-center justify-center gap-1.5 px-3 disabled:opacity-60">
                            <span wire:loading.remove wire:target="execute"
                                  class="inline-flex items-center gap-1.5">
                                <x-ui.icon name="check" class="h-4 w-4" />
                                Execute Transfer
                            </span>
                            <span wire:loading.inline-flex wire:target="execute"
                                  class="items-center gap-1.5">
                                {!! $spinner !!} Executing…
                            </span>
                        </button>
                    @endcan

                @endif
            </div>
        </div>
    </div>

    {{-- =========================================================
         STATUS / FEEDBACK BANNERS
    ========================================================== --}}
    @if($transfer->rejection_reason)
        <div class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 dark:border-red-900/50 dark:bg-red-950/20">
            <x-ui.icon name="exclamation" class="mt-0.5 h-4 w-4 shrink-0 text-red-600 dark:text-red-400" />
            <div class="min-w-0">
                <p class="text-sm font-semibold text-red-800 dark:text-red-400">
                    Transfer rejected
                </p>
                <p class="mt-0.5 whitespace-pre-line text-sm text-red-700 dark:text-red-400/90">
                    {{ $transfer->rejection_reason }}
                </p>
            </div>
        </div>

    @elseif($needsFix && $lastReturn)
        <div class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 dark:border-amber-900/50 dark:bg-amber-950/20">
            <x-ui.icon name="exclamation" class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
            <div class="min-w-0">
                <p class="text-sm font-semibold text-amber-800 dark:text-amber-400">
                    Sent back for correction
                    @if($lastReturn->decidedBy)
                        <span class="font-normal">by {{ $lastReturn->decidedBy->name }}</span>
                    @endif
                </p>
                @if($lastReturn->notes)
                    <p class="mt-0.5 whitespace-pre-line text-sm text-amber-800/90 dark:text-amber-400/90">
                        {{ $lastReturn->notes }}
                    </p>
                @endif
            </div>
        </div>
    @endif

    {{-- =========================================================
         TRANSFER ROUTE + CREATOR DETAILS
         One compact surface instead of multiple cards.
    ========================================================== --}}
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">

        <div class="grid grid-cols-1 divide-y divide-slate-100 dark:divide-slate-800 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_210px] md:divide-x md:divide-y-0">

            {{-- From --}}
            <div class="flex min-w-0 items-center gap-3 px-4 py-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    <x-ui.icon name="warehouse" class="h-5 w-5" />
                </div>

                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">
                        From
                    </p>
                    <p class="mt-0.5 truncate text-sm font-semibold text-slate-800 dark:text-slate-200">
                        {{ $transfer->fromVirtualWarehouse?->name ?? '—' }}
                    </p>
                    <p class="text-xs text-slate-500">Source warehouse</p>
                </div>
            </div>

            {{-- To --}}
            <div class="flex min-w-0 items-center gap-3 px-4 py-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300">
                    <x-ui.icon name="warehouse" class="h-5 w-5" />
                </div>

                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">
                        To
                    </p>
                    <p class="mt-0.5 truncate text-sm font-semibold text-slate-800 dark:text-slate-200">
                        {{ $transfer->toVirtualWarehouse?->name ?? '—' }}
                    </p>
                    <p class="text-xs text-slate-500">Destination warehouse</p>
                </div>
            </div>

            {{-- Created information --}}
            <div class="grid grid-cols-2 gap-3 px-4 py-3 md:grid-cols-1 md:content-center md:gap-2">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">
                        Created By
                    </p>
                    <p class="mt-0.5 truncate text-sm font-medium text-slate-800 dark:text-slate-200">
                        {{ $transfer->createdBy?->name ?? '—' }}
                    </p>
                </div>

                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">
                        Created At
                    </p>
                    <p class="mt-0.5 text-xs tabular-nums text-slate-600 dark:text-slate-400">
                        {{ $dt($transfer->created_at) ?? '—' }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- =========================================================
         MAIN CONTENT
    ========================================================== --}}
    <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-[minmax(0,1fr)_300px]">

        {{-- TRANSFER LINES --}}
        <section class="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">

            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300">
                        <x-ui.icon name="list" class="h-4 w-4" />
                    </span>

                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                            Transfer Lines
                        </h2>
                        <p class="text-xs text-slate-500">
                            Items included in this transfer
                        </p>
                    </div>
                </div>

                <span class="inline-flex h-7 min-w-7 items-center justify-center rounded-lg bg-slate-100 px-2 text-xs font-bold tabular-nums text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                    {{ $lineCount }}
                </span>
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
                            <td class="text-xs tabular-nums text-slate-400">
                                {{ $loop->iteration }}
                            </td>

                            <td class="min-w-[10rem]">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                                    {{ $line->item?->name ?? '—' }}
                                </p>
                                <p class="mt-0.5 font-mono text-xs text-slate-500 dark:text-slate-400">
                                    {{ $line->item?->sku ?? '—' }}
                                </p>
                            </td>

                            <td class="whitespace-nowrap">
                                @if($line->location)
                                    <span class="inline-flex rounded-md bg-slate-100 px-2 py-1 font-mono text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                            {{ $line->location->code }}
                                        </span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>

                            <td class="whitespace-nowrap">
                                @if($line->batch?->batch_no)
                                    <span class="font-mono text-xs text-slate-600 dark:text-slate-300">
                                            {{ $line->batch->batch_no }}
                                        </span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>

                            <td class="whitespace-nowrap text-right">
                                    <span class="text-sm font-bold tabular-nums text-slate-900 dark:text-white">
                                        {{ $fmt($line->qty) }}
                                    </span>
                                <span class="ml-1 text-xs font-medium text-slate-500">
                                        {{ $line->uom?->code ?? '' }}
                                    </span>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state title="No lines recorded." :cols="5" />
                    @endforelse
                    </tbody>
                </x-ui.table>
            </div>

            {{-- Notes integrated into the lines section --}}
            @if($transfer->notes)
                <div class="border-t border-slate-200 bg-slate-50/60 px-4 py-3 dark:border-slate-800 dark:bg-slate-800/20">
                    <p class="mb-1 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                        Transfer Notes
                    </p>
                    <p class="whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">
                        {{ $transfer->notes }}
                    </p>
                </div>
            @endif
        </section>

        {{-- RIGHT SIDEBAR --}}
        <aside class="min-w-0 space-y-4">

            {{-- APPROVALS --}}
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">

                <div class="flex items-center justify-between gap-2 border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300">
                            <x-ui.icon name="check" class="h-4 w-4" />
                        </span>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                            Approvals
                        </h2>
                    </div>

                    <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold tabular-nums text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        {{ $transfer->approvals->count() }}
                    </span>
                </div>

                <div class="px-4 py-3">
                    @if($transfer->approvals->isEmpty())
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            No approvals yet. They will appear here once the transfer is submitted.
                        </p>
                    @else
                        <ol class="space-y-4">
                            @foreach($transfer->approvals as $approval)
                                @php
                                    $style = $decisionStyles[$approval->decision] ?? $pendingStyle;
                                @endphp

                                <li wire:key="approval-{{ $approval->id }}"
                                    class="relative flex gap-3">

                                    @unless($loop->last)
                                        <span class="absolute bottom-[-16px] left-[9px] top-6 w-px bg-slate-200 dark:bg-slate-700"></span>
                                    @endunless

                                    <span class="relative z-10 flex h-5 w-5 shrink-0 items-center justify-center rounded-full {{ $style['dot'] }}">
                                        @if($style['icon'])
                                            <x-ui.icon :name="$style['icon']" class="h-3 w-3" />
                                        @endif
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                                                {{ $approval->approver_role }}
                                                @if($approval->level)
                                                    <span class="text-xs font-normal text-slate-400">
                                                        · Level {{ $approval->level }}
                                                    </span>
                                                @endif
                                            </p>

                                            <span class="inline-flex shrink-0 items-center rounded-md px-2 py-1 text-[10px] font-semibold {{ $style['badge'] }}">
                                                {{ $style['label'] }}
                                            </span>
                                        </div>

                                        @if($approval->decidedBy)
                                            <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">
                                                {{ $approval->decidedBy->name }}
                                            </p>
                                        @endif

                                        <p class="mt-1 text-[11px] tabular-nums text-slate-400">
                                            @if($approval->decided_at)
                                                {{ $dt($approval->decided_at) }}
                                            @else
                                                Requested {{ $dt($approval->created_at) ?? '—' }}
                                            @endif
                                        </p>

                                        @if($approval->notes)
                                            <p class="mt-2 rounded-lg bg-slate-50 px-2.5 py-2 text-xs whitespace-pre-line text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                                                {{ $approval->notes }}
                                            </p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </section>

            {{-- EXECUTION DETAILS --}}
            @if($transfer->acceptedBy || $transfer->posted_at)
                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">

                    <div class="flex items-center gap-2 border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                            <x-ui.icon name="check" class="h-4 w-4" />
                        </span>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                            Execution
                        </h2>
                    </div>

                    <div class="space-y-3 px-4 py-3">
                        @if($transfer->acceptedBy)
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                    Accepted By
                                </p>
                                <p class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-200">
                                    {{ $transfer->acceptedBy->name }}
                                </p>
                            </div>
                        @endif

                        @if($transfer->posted_at)
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                    Posted At
                                </p>
                                <p class="mt-0.5 text-sm tabular-nums text-slate-700 dark:text-slate-300">
                                    {{ $dt($transfer->posted_at) }}
                                </p>
                            </div>
                        @endif
                    </div>
                </section>
            @endif

        </aside>
    </div>

    {{-- =========================================================
         REJECT MODAL
    ========================================================== --}}
    @if($showRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog"
             aria-modal="true"
             aria-labelledby="reject-title"
             x-data
             x-on:keydown.escape.window="$wire.set('showRejectModal', false)">

            <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm"
                 wire:click="$set('showRejectModal', false)"></div>

            <div class="relative z-10 w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 dark:bg-slate-900 dark:ring-white/10">

                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400">
                            <x-ui.icon name="x" class="h-4 w-4" />
                        </span>

                        <div>
                            <h3 id="reject-title" class="text-sm font-bold text-slate-900 dark:text-white">
                                Reject Transfer
                            </h3>
                            <p class="mt-0.5 font-mono text-xs text-slate-500">
                                {{ $transfer->number }}
                            </p>
                        </div>
                    </div>

                    <button type="button"
                            wire:click="$set('showRejectModal', false)"
                            aria-label="Close modal"
                            class="icon-btn text-slate-400 hover:text-slate-700 dark:hover:text-white">
                        <x-ui.icon name="x" class="h-4 w-4" />
                    </button>
                </div>

                <form wire:submit="reject">
                    <div class="space-y-2 px-5 py-4">
                        <label class="label">
                            Rejection Reason <span class="text-red-500">*</span>
                        </label>

                        <textarea wire:model="rejectionReason"
                                  rows="4"
                                  autofocus
                                  class="input w-full resize-none @error('rejectionReason') border-red-400 @enderror"
                                  placeholder="Explain why this transfer is being rejected..."></textarea>

                        @error('rejectionReason')
                        <p class="hint !text-red-600">{{ $message }}</p>
                        @enderror

                        <p class="text-xs text-slate-500">
                            Rejection is final. Use Send Back if the requester can correct the transfer.
                        </p>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3 dark:border-slate-800 dark:bg-slate-800/30">
                        <button type="button"
                                wire:click="$set('showRejectModal', false)"
                                class="btn btn-secondary inline-flex h-9 items-center justify-center px-4">
                            Cancel
                        </button>

                        <button type="submit"
                                wire:loading.attr="disabled"
                                wire:target="reject"
                                class="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-red-600 px-4 text-sm font-semibold text-white transition hover:bg-red-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="reject">
                                Reject Transfer
                            </span>
                            <span wire:loading.inline-flex wire:target="reject"
                                  class="items-center gap-2">
                                {!! $spinner !!} Rejecting…
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- =========================================================
         SEND BACK MODAL
    ========================================================== --}}
    @if($showReturnModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog"
             aria-modal="true"
             aria-labelledby="return-title"
             x-data
             x-on:keydown.escape.window="$wire.set('showReturnModal', false)">

            <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm"
                 wire:click="$set('showReturnModal', false)"></div>

            <div class="relative z-10 w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 dark:bg-slate-900 dark:ring-white/10">

                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                            <x-ui.icon name="arrow-left" class="h-4 w-4" />
                        </span>

                        <div>
                            <h3 id="return-title" class="text-sm font-bold text-slate-900 dark:text-white">
                                Send Back for Correction
                            </h3>
                            <p class="mt-0.5 font-mono text-xs text-slate-500">
                                {{ $transfer->number }}
                            </p>
                        </div>
                    </div>

                    <button type="button"
                            wire:click="$set('showReturnModal', false)"
                            aria-label="Close modal"
                            class="icon-btn text-slate-400 hover:text-slate-700 dark:hover:text-white">
                        <x-ui.icon name="x" class="h-4 w-4" />
                    </button>
                </div>

                <form wire:submit="sendBack">
                    <div class="space-y-2 px-5 py-4">
                        <label class="label">
                            What needs to be corrected?
                            <span class="text-red-500">*</span>
                        </label>

                        <textarea wire:model="returnNotes"
                                  rows="4"
                                  autofocus
                                  class="input w-full resize-none @error('returnNotes') border-red-400 @enderror"
                                  placeholder="Explain what needs to be corrected..."></textarea>

                        @error('returnNotes')
                        <p class="hint !text-red-600">{{ $message }}</p>
                        @enderror

                        <p class="text-xs text-slate-500">
                            The requester will need to correct the transfer before resubmitting it.
                        </p>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3 dark:border-slate-800 dark:bg-slate-800/30">
                        <button type="button"
                                wire:click="$set('showReturnModal', false)"
                                class="btn btn-secondary inline-flex h-9 items-center justify-center px-4">
                            Cancel
                        </button>

                        <button type="submit"
                                wire:loading.attr="disabled"
                                wire:target="sendBack"
                                class="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-amber-500 px-4 text-sm font-semibold text-white transition hover:bg-amber-600 disabled:opacity-60">
                            <span wire:loading.remove wire:target="sendBack">
                                Send Back
                            </span>
                            <span wire:loading.inline-flex wire:target="sendBack"
                                  class="items-center gap-2">
                                {!! $spinner !!} Sending…
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
