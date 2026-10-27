@php
    $req    = $stockStatusRequest;
    $status = $req->status;
    $type   = $req->type;

    $isSubmitted = $status === \App\Enums\Inventory\StockStatusRequestStatus::Submitted;
    $isApproved  = $status === \App\Enums\Inventory\StockStatusRequestStatus::Approved;
    $isRejected  = $status === \App\Enums\Inventory\StockStatusRequestStatus::Rejected;

    $typeIcon  = $type?->value === 'mark_obsolete' ? 'archive' : 'refresh';
    $typeColor = $type?->value === 'mark_obsolete'
        ? 'bg-red-50 dark:bg-red-950/30 text-red-600 dark:text-red-400 ring-red-500/20'
        : 'bg-green-50 dark:bg-green-950/30 text-green-600 dark:text-green-400 ring-green-500/20';

    $steps = ['Draft', 'Submitted', 'Approved'];
    $stepStatus = match(true) {
        $isRejected  => 1,
        $isApproved  => 2,
        $isSubmitted => 1,
        default      => 0,
    };
@endphp

<div class="space-y-6">

    {{-- ── PAGE HEADER ─────────────────────────────────────────────────────── --}}
    <div class="page-header">
        <div>
            <a href="{{ route('inventory.stock.index') }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-simba-600 dark:text-slate-400 dark:hover:text-simba-400 mb-2 transition-colors">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Back to Stock on Hand
            </a>
            <div class="flex items-center gap-3 flex-wrap">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $typeColor }} ring-1">
                    <x-ui.icon name="{{ $typeIcon }}" class="w-5 h-5" />
                </div>
                <div>
                    <h2 class="page-title font-mono leading-none">{{ $req->number }}</h2>
                    <div class="flex items-center gap-2 mt-1 flex-wrap">
                        <span class="badge badge-slate">{{ $type?->label() }}</span>
                        <span class="badge {{ $status->badge() }}">
                            <span class="dot"></span>{{ $status->label() }}
                        </span>
                        @if($req->virtualWarehouse)
                            <span class="text-xs text-slate-400 dark:text-slate-500">
                                {{ $req->virtualWarehouse->name }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Action buttons --}}
        <div class="flex gap-2 flex-wrap items-center">
            @if($isSubmitted)
                @can('approve', $req)
                    <button wire:click="openApproveModal" type="button" class="btn btn-primary">
                        <x-ui.icon name="check" class="w-4 h-4" /> Approve
                    </button>
                    <button wire:click="openRejectModal" type="button"
                            class="btn btn-secondary text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30">
                        <x-ui.icon name="x" class="w-4 h-4" /> Reject
                    </button>
                @endcan
            @endif
        </div>
    </div>

    {{-- ── STATUS PROGRESS ─────────────────────────────────────────────────── --}}
    <div class="card">
        <div class="card-body py-4">
            <div class="flex items-center gap-0">
                @foreach($steps as $i => $step)
                    @php
                        $done    = $i < $stepStatus || ($i === $stepStatus && ! $isRejected && $i > 0);
                        $current = $i === $stepStatus && ! $isRejected;
                        $dot     = $done ? 'bg-green-500' : ($current ? 'bg-simba-600' : 'bg-slate-200 dark:bg-slate-700');
                        $label   = $done ? 'text-green-600 dark:text-green-400 font-semibold'
                                 : ($current ? 'text-simba-600 dark:text-simba-400 font-semibold'
                                 : 'text-slate-400 dark:text-slate-500');
                    @endphp

                    <div class="flex flex-col items-center gap-1.5 flex-1">
                        <div class="relative flex items-center w-full">
                            @if($i > 0)
                                <div class="flex-1 h-0.5 {{ $i <= $stepStatus && ! $isRejected ? 'bg-green-400 dark:bg-green-600' : 'bg-slate-200 dark:bg-slate-700' }}"></div>
                            @else
                                <div class="flex-1"></div>
                            @endif

                            <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 ring-2 ring-white dark:ring-slate-900 {{ $dot }}">
                                @if($done)
                                    <x-ui.icon name="check" class="w-3.5 h-3.5 text-white" />
                                @elseif($current && $isRejected)
                                    <x-ui.icon name="x" class="w-3.5 h-3.5 text-white" />
                                @else
                                    <span class="text-[10px] font-bold {{ $current ? 'text-white' : 'text-slate-400' }}">{{ $i + 1 }}</span>
                                @endif
                            </div>

                            @if($i < count($steps) - 1)
                                <div class="flex-1 h-0.5 {{ $i < $stepStatus && ! $isRejected ? 'bg-green-400 dark:bg-green-600' : 'bg-slate-200 dark:bg-slate-700' }}"></div>
                            @else
                                <div class="flex-1"></div>
                            @endif
                        </div>
                        <span class="text-[11px] uppercase tracking-wide {{ $label }}">{{ $step }}</span>
                    </div>
                @endforeach

                @if($isRejected)
                    <div class="flex flex-col items-center gap-1.5 flex-1">
                        <div class="relative flex items-center w-full">
                            <div class="flex-1 h-0.5 bg-red-300 dark:bg-red-800"></div>
                            <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 ring-2 ring-white dark:ring-slate-900 bg-red-500">
                                <x-ui.icon name="x" class="w-3.5 h-3.5 text-white" />
                            </div>
                            <div class="flex-1"></div>
                        </div>
                        <span class="text-[11px] uppercase tracking-wide text-red-500 font-semibold">Rejected</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ── REJECTION ALERT ─────────────────────────────────────────────────── --}}
    @if($isRejected && $req->rejection_reason)
        <div class="flex items-start gap-3 rounded-xl
                    bg-red-50/60 dark:bg-red-950/30
                    border border-red-200 dark:border-red-900/40 px-5 py-4">
            <x-ui.icon name="x" class="h-5 w-5 text-red-500 shrink-0 mt-0.5" />
            <div>
                <p class="text-sm font-semibold text-red-700 dark:text-red-400">Request Rejected</p>
                <p class="text-sm text-red-600 dark:text-red-300 mt-0.5">{{ $req->rejection_reason }}</p>
                @if($req->rejectedBy)
                    <p class="text-xs text-red-400 dark:text-red-500 mt-1">
                        by {{ $req->rejectedBy->name }}
                        @if($req->rejected_at) · {{ $req->rejected_at->format('d M Y H:i') }} @endif
                    </p>
                @endif
            </div>
        </div>
    @endif

    {{-- ── MAIN CONTENT ─────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Lines ─────────────────────────────────────────────────────────── --}}
        <div class="card lg:col-span-2">
            <div class="card-header">
                <h3 class="text-card-title">Lines</h3>
                <span class="badge badge-slate">{{ $req->lines->count() }} {{ Str::plural('item', $req->lines->count()) }}</span>
            </div>
            <x-ui.table>
                <thead>
                    <tr>
                        <th class="w-8">#</th>
                        <th>Item</th>
                        <th>Location</th>
                        <th>Batch</th>
                        <th class="text-right">Qty</th>
                        <th>Unit</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($req->lines as $line)
                        <tr wire:key="line-{{ $line->id }}">
                            <td class="tabular-nums text-slate-400 text-xs">{{ $loop->iteration }}</td>
                            <td>
                                <div class="mono text-xs text-slate-400">{{ $line->item?->sku }}</div>
                                <div class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $line->item?->name }}</div>
                            </td>
                            <td class="mono text-sm">{{ $line->location?->code ?? '—' }}</td>
                            <td class="mono text-sm text-slate-500">{{ $line->batch?->batch_no ?? '—' }}</td>
                            <td class="text-right tabular-nums font-semibold text-slate-800 dark:text-slate-200">
                                {{ number_format((float) $line->qty, 4) }}
                            </td>
                            <td class="mono text-sm text-slate-500">{{ $line->item?->baseUom?->code ?? '—' }}</td>
                        </tr>
                    @empty
                        <x-ui.empty-state title="No lines on this request." :cols="6" />
                    @endforelse
                </tbody>
            </x-ui.table>
        </div>

        {{-- Details sidebar ─────────────────────────────────────────────── --}}
        <div class="space-y-4">
            <div class="card">
                <div class="card-header"><h3 class="text-card-title">Details</h3></div>
                <div class="card-body divide-y divide-slate-100 dark:divide-white/[0.06]">
                    <div class="py-2.5 first:pt-0">
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Virtual Warehouse</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $req->virtualWarehouse?->name ?? '—' }}</p>
                    </div>
                    <div class="py-2.5">
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Department</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $req->department?->name ?? '—' }}</p>
                    </div>
                    <div class="py-2.5">
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Requested By</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $req->createdBy?->name ?? '—' }}</p>
                    </div>
                    <div class="py-2.5">
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Submitted</p>
                        <p class="mt-0.5 text-sm tabular-nums text-slate-800 dark:text-slate-200">
                            {{ $req->submitted_at?->format('d M Y, H:i') ?? '—' }}
                        </p>
                    </div>
                    @if($isApproved)
                        <div class="py-2.5">
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Approved By</p>
                            <p class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $req->approvedBy?->name ?? '—' }}</p>
                        </div>
                        <div class="py-2.5">
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Approved</p>
                            <p class="mt-0.5 text-sm tabular-nums text-slate-800 dark:text-slate-200">
                                {{ $req->approved_at?->format('d M Y, H:i') ?? '—' }}
                            </p>
                        </div>
                    @endif
                    <div class="py-2.5 last:pb-0">
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Reason</p>
                        <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-400">{{ $req->reason }}</p>
                    </div>
                </div>
            </div>

            {{-- Pending approval callout --}}
            @if($isSubmitted)
                <div class="flex items-start gap-3 rounded-xl
                            bg-amber-50/60 dark:bg-amber-950/30
                            border border-amber-200 dark:border-amber-900/40 px-4 py-3.5">
                    <x-ui.icon name="clock" class="h-4 w-4 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" />
                    <p class="text-sm text-amber-700 dark:text-amber-400">
                        Awaiting approval from a warehouse manager or department approver.
                    </p>
                </div>
            @endif
        </div>
    </div>

    {{-- ── APPROVAL HISTORY ─────────────────────────────────────────────────── --}}
    @if($req->approvals->count())
        <div class="card">
            <div class="card-header"><h3 class="text-card-title">Approval History</h3></div>
            <div class="card-body">
                <ul class="space-y-4">
                    @foreach($req->approvals as $approval)
                        @php
                            $isAppr  = $approval->action === 'approved';
                            $rowIcon = $isAppr ? 'check' : 'x';
                            $iconBg  = $isAppr
                                ? 'bg-green-100 dark:bg-green-900/40 text-green-600 dark:text-green-400'
                                : 'bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400';
                        @endphp
                        <li wire:key="appr-{{ $approval->id }}" class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ $iconBg }}">
                                <x-ui.icon name="{{ $rowIcon }}" class="h-3.5 w-3.5" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                                        {{ $approval->user?->name ?? '—' }}
                                    </span>
                                    <span class="badge {{ $isAppr ? 'badge-green' : 'badge-red' }}">
                                        {{ ucfirst($approval->action) }}
                                    </span>
                                    <span class="text-xs text-slate-400 tabular-nums">
                                        {{ $approval->created_at->format('d M Y, H:i') }}
                                    </span>
                                </div>
                                @if($approval->comment)
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $approval->comment }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- ── APPROVE MODAL ───────────────────────────────────────────────────── --}}
    @if($showApproveModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             x-data x-on:keydown.escape.window="$wire.closeApproveModal()">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-[2px]"
                 wire:click="closeApproveModal"></div>
            <div class="relative z-10 w-full max-w-md rounded-2xl bg-white dark:bg-zinc-900
                        shadow-2xl ring-1 ring-black/[0.06] dark:ring-white/[0.08]">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-white/[0.08]">
                    <div class="flex items-center gap-3">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/40 text-green-600 dark:text-green-400">
                            <x-ui.icon name="check" class="h-4 w-4" />
                        </div>
                        <h3 class="text-[15px] font-semibold text-slate-900 dark:text-white">Approve Request</h3>
                    </div>
                    <button type="button" wire:click="closeApproveModal" class="icon-btn text-slate-400 hover:text-slate-600">
                        <x-ui.icon name="x" class="h-4 w-4" />
                    </button>
                </div>

                <div class="px-6 py-5 space-y-4">
                    <div class="rounded-xl bg-green-50/60 dark:bg-green-950/20 border border-green-200 dark:border-green-900/40 px-4 py-3 text-sm text-green-700 dark:text-green-400">
                        This will change the stock status of
                        <strong>{{ $req->lines->count() }} {{ Str::plural('line', $req->lines->count()) }}</strong>
                        to <strong>{{ $type?->value === 'mark_obsolete' ? 'Obsolete' : 'Available' }}</strong>.
                        This action posts directly to the stock ledger.
                    </div>
                    <div>
                        <label class="label">Comment <span class="text-slate-400 font-normal">(optional)</span></label>
                        <textarea wire:model="approveComment" rows="2" class="input w-full resize-none"
                                  placeholder="Add a note for the audit trail…"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3 px-6 py-4 bg-slate-50/80 dark:bg-white/[0.02]
                            border-t border-slate-100 dark:border-white/[0.08] rounded-b-2xl">
                    <button wire:click="closeApproveModal" type="button" class="btn btn-secondary">Cancel</button>
                    <button wire:click="approve" wire:loading.attr="disabled" class="btn btn-primary">
                        <span wire:loading.remove wire:target="approve">
                            <x-ui.icon name="check" class="w-4 h-4" /> Approve
                        </span>
                        <span wire:loading wire:target="approve">Approving…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ── REJECT MODAL ────────────────────────────────────────────────────── --}}
    @if($showRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             x-data x-on:keydown.escape.window="$wire.closeRejectModal()">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-[2px]"
                 wire:click="closeRejectModal"></div>
            <div class="relative z-10 w-full max-w-md rounded-2xl bg-white dark:bg-zinc-900
                        shadow-2xl ring-1 ring-black/[0.06] dark:ring-white/[0.08]">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-white/[0.08]">
                    <div class="flex items-center gap-3">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400">
                            <x-ui.icon name="x" class="h-4 w-4" />
                        </div>
                        <h3 class="text-[15px] font-semibold text-slate-900 dark:text-white">Reject Request</h3>
                    </div>
                    <button type="button" wire:click="closeRejectModal" class="icon-btn text-slate-400 hover:text-slate-600">
                        <x-ui.icon name="x" class="h-4 w-4" />
                    </button>
                </div>

                <div class="px-6 py-5 space-y-4">
                    <div class="rounded-xl bg-red-50/60 dark:bg-red-950/20 border border-red-200 dark:border-red-900/40 px-4 py-3 text-sm text-red-700 dark:text-red-400">
                        The request will be closed and no stock status changes will be made.
                    </div>
                    <div>
                        <label class="label">Reason <span class="required-mark">*</span></label>
                        <textarea wire:model="rejectReason" rows="3" class="input w-full resize-none @error('rejectReason') border-red-400 @enderror"
                                  placeholder="Explain why this request is being rejected…"></textarea>
                        @error('rejectReason') <p class="hint hint-error mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-3 px-6 py-4 bg-slate-50/80 dark:bg-white/[0.02]
                            border-t border-slate-100 dark:border-white/[0.08] rounded-b-2xl">
                    <button wire:click="closeRejectModal" type="button" class="btn btn-secondary">Cancel</button>
                    <button wire:click="reject" wire:loading.attr="disabled"
                            class="btn bg-red-600 hover:bg-red-700 text-white font-semibold">
                        <span wire:loading.remove wire:target="reject">Reject</span>
                        <span wire:loading wire:target="reject">Rejecting…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
