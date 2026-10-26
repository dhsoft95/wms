<div class="space-y-6">
    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Dispatch Orders</h2>
            <p class="page-sub">Manage outbound delivery requests from creation through dispatch.</p>
        </div>
        @can('create', \App\Models\DeliveryOrder::class)
            <a href="{{ route('outbound.orders.create') }}" wire:navigate class="btn btn-primary self-start">
                <x-ui.icon name="plus" class="w-4 h-4" /> New Order
            </a>
        @endcan
    </div>

    @php
        // Split statuses into primary (always visible) and secondary (in "More" dropdown)
        $primaryStatusValues = ['draft', 'submitted', 'returned_for_correction', 'approved', 'dispatched', 'delivered'];
        $primaryStatuses     = collect($this->statuses)->filter(fn ($s) => in_array($s->value, $primaryStatusValues));
        $moreStatuses        = collect($this->statuses)->reject(fn ($s) => in_array($s->value, $primaryStatusValues));
        $isMoreActive        = $moreStatuses->contains(fn ($s) => $s->value === $statusFilter);
        $activeMoreStatus    = $moreStatuses->firstWhere('value', $statusFilter);
        $activeFilterCount   = (int)(bool)$priorityFilter + (int)(bool)$dateFrom + (int)(bool)$dateTo + (int)$overdueOnly;
    @endphp

    <div class="card"
         x-data="{ filtersOpen: {{ $activeFilterCount > 0 ? 'true' : 'false' }} }">

        {{-- ── Status Tabs ─────────────────────────────────────────────────── --}}
        <div class="border-b border-white/40 dark:border-white/[0.06]">
            <div class="flex items-stretch">

                {{-- Scrollable primary tabs --}}
                <div class="relative flex-1 min-w-0 overflow-x-auto [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                    <div class="flex items-end gap-0 px-3 pt-1 min-w-max">

                        {{-- All --}}
                        <button wire:click="$set('statusFilter','all')"
                                @class(['tab whitespace-nowrap', 'active' => $statusFilter === 'all'])>
                            All
                            @if (($this->statusCounts['all'] ?? 0) > 0)
                                <span class="badge badge-neutral ml-1 text-[11px]">{{ $this->statusCounts['all'] }}</span>
                            @endif
                        </button>

                        {{-- Primary status tabs --}}
                        @foreach ($primaryStatuses as $status)
                            <button wire:click="$set('statusFilter','{{ $status->value }}')"
                                    @class(['tab whitespace-nowrap', 'active' => $statusFilter === $status->value])>
                                {{ $status->label() }}
                                @if (($this->statusCounts[$status->value] ?? 0) > 0)
                                    <span class="badge {{ $status->badge() }} ml-1 text-[11px]">{{ $this->statusCounts[$status->value] }}</span>
                                @endif
                            </button>
                        @endforeach

                    </div>
                    {{-- Fade hint for hidden tabs --}}
                    <div class="pointer-events-none absolute inset-y-0 right-0 w-8 bg-gradient-to-l from-white dark:from-slate-950"></div>
                </div>

                {{-- More button — outside the scroll container so its dropdown is never clipped --}}
                @if ($moreStatuses->isNotEmpty())
                    <div class="relative shrink-0 flex items-end pb-1 pl-1 pr-3 border-l border-slate-100 dark:border-white/[0.06]"
                         x-data="{ moreOpen: false }">
                        <button
                            type="button"
                            @click="moreOpen = !moreOpen"
                            @class([
                                'inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium rounded-lg transition whitespace-nowrap',
                                'bg-simba-50 text-simba-700 dark:bg-simba-900/20 dark:text-simba-400' => $isMoreActive,
                                'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' => ! $isMoreActive,
                            ])
                        >
                            @if ($isMoreActive && $activeMoreStatus)
                                {{ Str::limit($activeMoreStatus->label(), 22) }}
                                @if (($this->statusCounts[$activeMoreStatus->value] ?? 0) > 0)
                                    <span class="badge {{ $activeMoreStatus->badge() }} ml-1 text-[11px]">{{ $this->statusCounts[$activeMoreStatus->value] }}</span>
                                @endif
                            @else
                                More
                            @endif
                            <x-ui.icon name="chevron-down" class="w-3 h-3 shrink-0 opacity-60" />
                        </button>

                        <div x-show="moreOpen"
                             x-cloak
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             @click.outside="moreOpen = false"
                             class="absolute right-0 top-full mt-1.5 z-40 w-56 rounded-xl bg-white dark:bg-slate-900 shadow-xl ring-1 ring-black/[0.06] dark:ring-white/[0.08] py-1.5">
                            @foreach ($moreStatuses as $status)
                                <button
                                    wire:click="$set('statusFilter','{{ $status->value }}')"
                                    @click="moreOpen = false"
                                    type="button"
                                    @class([
                                        'w-full text-left px-4 py-2 text-sm flex items-center justify-between gap-2 transition',
                                        'bg-simba-50/80 dark:bg-simba-900/10 text-simba-700 dark:text-simba-400 font-semibold' => $statusFilter === $status->value,
                                        'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800' => $statusFilter !== $status->value,
                                    ])
                                >
                                    <span>{{ $status->label() }}</span>
                                    @if (($this->statusCounts[$status->value] ?? 0) > 0)
                                        <span class="badge {{ $status->badge() }} shrink-0 text-[11px]">{{ $this->statusCounts[$status->value] }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>
        </div>

        {{-- ── Toolbar: Search + Filters button ────────────────────────────── --}}
        <div class="flex items-center gap-3 px-4 py-3 border-b border-white/40 dark:border-white/[0.06]">
            {{-- Search --}}
            <div class="relative flex-1 min-w-0">
                <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                <input wire:model.live.debounce.300ms="search"
                       type="search"
                       placeholder="Search DO no., consignee, VW…"
                       class="input !pl-9 w-full" />
            </div>

            {{-- Filters toggle --}}
            <button
                type="button"
                x-on:click="filtersOpen = !filtersOpen"
                @class([
                    'btn btn-secondary btn-sm shrink-0 gap-1.5',
                    '!border-simba-400 !text-simba-700 dark:!text-simba-400' => $activeFilterCount > 0,
                ])
            >
                <x-ui.icon name="filter" class="w-3.5 h-3.5" />
                Filters
                @if ($activeFilterCount > 0)
                    <span class="badge badge-simba text-[10px] ml-0.5">{{ $activeFilterCount }}</span>
                @endif
            </button>
        </div>

        {{-- ── Collapsible Filter Panel ─────────────────────────────────────── --}}
        <div x-show="filtersOpen"
             x-collapse
             class="border-b border-white/40 dark:border-white/[0.06]">
            <div class="px-4 pt-4 pb-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">

                <div>
                    <label class="label">Priority</label>
                    <select wire:model.live="priorityFilter" class="input">
                        <option value="">All Priorities</option>
                        @foreach ($this->priorities as $priority)
                            <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label">Required Date From</label>
                    <input wire:model.live="dateFrom" type="date" class="input tabular-nums" />
                </div>

                <div>
                    <label class="label">Required Date To</label>
                    <input wire:model.live="dateTo" type="date" class="input tabular-nums" />
                </div>

                <div class="flex items-end pb-1">
                    <label class="flex items-center gap-2 cursor-pointer text-sm text-slate-600 dark:text-slate-400">
                        <input wire:model.live="overdueOnly" type="checkbox"
                               class="rounded text-simba-600 focus:ring-simba-600" />
                        Overdue only
                    </label>
                </div>
            </div>

            @if ($activeFilterCount > 0)
                <div class="px-4 pb-3 flex justify-end">
                    <button
                        wire:click="$set('priorityFilter', ''); $set('dateFrom', ''); $set('dateTo', ''); $set('overdueOnly', false)"
                        type="button"
                        class="btn btn-secondary btn-sm">
                        <x-ui.icon name="x" class="w-3.5 h-3.5" /> Clear Filters
                    </button>
                </div>
            @endif
        </div>

        {{-- ── Table ──────────────────────────────────────────────────────────── --}}
        <x-ui.table>
            <thead>
                <tr>
                    <th class="w-36">DO Number</th>
                    <th class="w-44">Department / VW</th>
                    <th>Consignee</th>
                    <th class="w-28">Required Date</th>
                    <th class="w-24">Priority</th>
                    <th class="w-28">Lines</th>
                    <th class="w-36">Status</th>
                    <th>Requested By</th>
                    <th class="w-px text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->orders as $order)
                    @php
                        $S = \App\Enums\Outbound\DeliveryOrderStatus::class;

                        // Show fulfilment progress only when in a picking/dispatch lifecycle stage
                        $fulfillmentStatuses = [
                            $S::Allocated, $S::PartiallyAllocated,
                            $S::Picking, $S::Picked,
                            $S::Dispatched, $S::Delivered,
                        ];
                        $showProgress = in_array($order->status, $fulfillmentStatuses, true);
                        $linesCount   = $order->lines_count ?? 0;

                        $terminalStatuses = [$S::Dispatched, $S::Delivered, $S::Cancelled, $S::Rejected];
                        $hasCancelAction  = ! in_array($order->status, $terminalStatuses, true);
                        $hasRejectAction  = $order->status === $S::Submitted;
                    @endphp
                    <tr wire:key="do-{{ $order->id }}">

                        {{-- DO Number --}}
                        <td class="align-middle">
                            <a href="{{ route('outbound.orders.show', $order) }}" wire:navigate
                               class="mono font-semibold text-simba-600 dark:text-simba-400 hover:underline">
                                {{ $order->number }}
                            </a>
                        </td>

                        {{-- Department / VW --}}
                        <td class="align-middle">
                            <div class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate max-w-[10rem]">
                                {{ $order->department?->name ?? '—' }}
                            </div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 truncate max-w-[10rem]">
                                {{ $order->virtualWarehouse?->name ?? '—' }}
                            </div>
                        </td>

                        {{-- Consignee --}}
                        <td class="align-middle text-sm text-slate-700 dark:text-slate-300">
                            {{ $order->consigneePartner?->name ?? '—' }}
                        </td>

                        {{-- Required Date --}}
                        <td class="align-middle tabular-nums text-sm whitespace-nowrap
                                   {{ $order->isOverdue() ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-700 dark:text-slate-300' }}">
                            {{ $order->required_date?->format('d M Y') ?? '—' }}
                            @if ($order->isOverdue())
                                <span class="block text-xs font-normal">Overdue</span>
                            @endif
                        </td>

                        {{-- Priority --}}
                        <td class="align-middle">
                            <span class="badge {{ $order->priority->badge() }}">
                                <span class="dot"></span>{{ $order->priority->label() }}
                            </span>
                        </td>

                        {{-- Lines / Fulfilment --}}
                        <td class="align-middle">
                            <div class="text-sm tabular-nums text-slate-700 dark:text-slate-300">
                                {{ $linesCount }} {{ Str::plural('line', $linesCount) }}
                            </div>
                            @if ($showProgress && $linesCount > 0)
                                @php
                                    $allocatedQty = $order->lines->sum('allocated_qty');
                                    $requestedQty = $order->lines->sum('requested_qty');
                                    $allocPct = $requestedQty > 0
                                        ? (int) min(100, round(($allocatedQty / $requestedQty) * 100))
                                        : 0;
                                @endphp
                                <div class="flex items-center gap-1.5 mt-1">
                                    <div class="progress w-16">
                                        <div class="{{ $allocPct >= 100 ? 'bg-emerald-600' : 'bg-simba-500' }}"
                                             style="width:{{ $allocPct }}%"></div>
                                    </div>
                                    <span class="text-xs tabular-nums text-slate-500">{{ $allocPct }}%</span>
                                </div>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td class="align-middle">
                            <span class="badge {{ $order->status->badge() }} whitespace-nowrap">
                                <span class="dot"></span>{{ $order->status->label() }}
                            </span>
                        </td>

                        {{-- Requested By --}}
                        <td class="align-middle text-sm text-slate-600 dark:text-slate-400">
                            {{ $order->requestedBy?->name ?? '—' }}
                        </td>

                        {{-- Actions --}}
                        <td class="align-middle">
                            <div class="flex items-center justify-end gap-1 flex-nowrap pr-1">

                                {{-- View --}}
                                <a href="{{ route('outbound.orders.show', $order) }}"
                                   wire:navigate
                                   class="btn btn-secondary btn-sm"
                                   title="View order {{ $order->number }}">
                                    <x-ui.icon name="eye" class="w-3.5 h-3.5" />
                                </a>

                                {{-- Primary action --}}
                                @if ($order->status === $S::Draft)
                                    @can('submit', $order)
                                        <button wire:click="submitOrder({{ $order->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="submitOrder({{ $order->id }})"
                                                class="btn btn-primary btn-sm whitespace-nowrap">
                                            <x-ui.icon name="paper-airplane" class="w-3.5 h-3.5" /> Submit
                                        </button>
                                    @endcan
                                @elseif ($order->status === $S::Approved)
                                    @can('allocate', $order)
                                        <button wire:click="allocateOrder({{ $order->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="allocateOrder({{ $order->id }})"
                                                class="btn btn-primary btn-sm whitespace-nowrap">
                                            <x-ui.icon name="squares-plus" class="w-3.5 h-3.5" /> Allocate
                                        </button>
                                    @endcan
                                @elseif (in_array($order->status, [$S::Allocated, $S::PartiallyAllocated], true))
                                    @can('allocate', $order)
                                        <a href="{{ route('outbound.pick-lists.index', ['deliveryOrderId' => $order->id]) }}"
                                           wire:navigate
                                           class="btn btn-primary btn-sm whitespace-nowrap">
                                            <x-ui.icon name="clipboard-list" class="w-3.5 h-3.5" /> Pick List
                                        </a>
                                    @endcan
                                @endif

                                {{-- Reject (icon button, amber) --}}
                                @if ($hasRejectAction)
                                    <button
                                        wire:click="openRejectModal({{ $order->id }})"
                                        type="button"
                                        title="Reject order"
                                        class="btn btn-secondary btn-sm !text-amber-600 dark:!text-amber-400 hover:!bg-amber-50 dark:hover:!bg-amber-900/20">
                                        <x-ui.icon name="x-circle" class="w-3.5 h-3.5" />
                                    </button>
                                @endif

                                {{-- Cancel (icon button, red) --}}
                                @if ($hasCancelAction)
                                    @can('cancel', $order)
                                        <button
                                            wire:click="cancelOrder({{ $order->id }})"
                                            wire:confirm="Cancel order {{ $order->number }}? This cannot be undone."
                                            type="button"
                                            title="Cancel order"
                                            class="btn btn-secondary btn-sm !text-red-600 dark:!text-red-400 hover:!bg-red-50 dark:hover:!bg-red-900/20">
                                            <x-ui.icon name="x-mark" class="w-3.5 h-3.5" />
                                        </button>
                                    @endcan
                                @endif

                                {{-- Delete (icon button, red) --}}
                                @can('delete', $order)
                                    <button
                                        wire:click="$set('deleteOrderId', {{ $order->id }})"
                                        type="button"
                                        title="Delete order"
                                        class="btn btn-secondary btn-sm !text-red-700 dark:!text-red-400 hover:!bg-red-50 dark:hover:!bg-red-900/20">
                                        <x-ui.icon name="trash" class="w-3.5 h-3.5" />
                                    </button>
                                @endcan

                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="!py-16 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <x-ui.icon name="document-text" class="w-8 h-8 text-slate-300 dark:text-slate-600" />
                                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                    @if ($search || $statusFilter !== 'all' || $priorityFilter || $dateFrom || $dateTo || $overdueOnly)
                                        No orders match your current filters.
                                    @elseif ($statusFilter !== 'all')
                                        No orders in <span class="font-semibold">{{ collect($this->statuses)->firstWhere('value', $statusFilter)?->label() ?? $statusFilter }}</span>.
                                    @else
                                        No delivery orders found.
                                    @endif
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        {{-- Pagination --}}
        <div class="px-4 py-3 border-t border-white/40 dark:border-white/[0.06]">
            {{ $this->orders->links() }}
        </div>

    </div>{{-- /card --}}

    {{-- ── Reject Modal ──────────────────────────────────────────────────────── --}}
    @if ($showRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog"
             aria-modal="true"
             x-data
             x-on:keydown.escape.window="$wire.showRejectModal = false">

            <div class="absolute inset-0 bg-black/50 backdrop-blur-[2px]"
                 wire:click="$set('showRejectModal', false)"></div>

            <div class="relative z-10 w-full max-w-md rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl ring-1 ring-black/[0.06] dark:ring-white/[0.08]">

                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-white/[0.08]">
                    <h3 class="text-[15px] font-semibold text-slate-900 dark:text-white">Reject Order</h3>
                    <button type="button"
                            wire:click="$set('showRejectModal', false)"
                            class="icon-btn -mr-2 -mt-0.5 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200"
                            aria-label="Close">
                        <x-ui.icon name="x" class="h-4 w-4" />
                    </button>
                </div>

                <div class="px-6 py-5 space-y-4">
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        Provide a reason for rejecting this order. The requester will be notified.
                    </p>
                    <div>
                        <label class="label" for="reject-notes">
                            Rejection reason <span class="text-red-500">*</span>
                        </label>
                        <textarea wire:model="rejectNotes"
                                  id="reject-notes"
                                  rows="4"
                                  placeholder="Explain why this order is being rejected…"
                                  class="input w-full resize-none @error('rejectNotes') border-red-400 @enderror"></textarea>
                        @error('rejectNotes')
                            <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-3 px-6 py-4 bg-slate-50/80 dark:bg-white/[0.02] border-t border-slate-100 dark:border-white/[0.08] rounded-b-2xl">
                    <button type="button"
                            wire:click="$set('showRejectModal', false)"
                            class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="button"
                            wire:click="confirmReject"
                            wire:loading.attr="disabled"
                            class="btn bg-red-600 hover:bg-red-700 text-white font-semibold">
                        <span wire:loading.remove wire:target="confirmReject">
                            <x-ui.icon name="x" class="w-4 h-4" /> Reject Order
                        </span>
                        <span wire:loading wire:target="confirmReject">Rejecting…</span>
                    </button>
                </div>

            </div>
        </div>
    @endif

    {{-- ── Delete Confirmation Modal ─────────────────────────────────────────── --}}
    @if ($deleteOrderId)
        <div class="modal-backdrop"
             x-data wire:click.self="$set('deleteOrderId', null)">
            <div class="modal-box max-w-md">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                            <x-ui.icon name="trash" class="w-5 h-5 text-red-600 dark:text-red-400" />
                        </div>
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Delete Order</h3>
                    </div>
                    <button wire:click="$set('deleteOrderId', null)" class="icon-btn">
                        <x-ui.icon name="x-mark" class="w-5 h-5" />
                    </button>
                </div>
                <p class="text-sm text-slate-600 dark:text-slate-400 mb-2">
                    Permanently delete this order and all related records?
                </p>
                <p class="text-sm text-red-600 dark:text-red-400 mb-5">
                    Stock reservations will be released and pick movements reversed. This cannot be undone.
                </p>
                <div class="flex justify-end gap-3">
                    <button wire:click="$set('deleteOrderId', null)" class="btn btn-secondary">Cancel</button>
                    <button wire:click="deleteOrder({{ $deleteOrderId }})"
                            wire:loading.attr="disabled"
                            class="btn bg-red-600 hover:bg-red-700 text-white">
                        <x-ui.icon name="trash" class="w-4 h-4" /> Delete Permanently
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
