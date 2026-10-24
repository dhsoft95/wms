<div class="space-y-6">
    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Dispatch Orders</h2>
            <p class="page-sub">Manage outbound delivery requests from creation through dispatch.</p>
        </div>
        @can('create', \App\Models\DeliveryOrder::class)
            <a href="{{ route('outbound.orders.create') }}" wire:navigate class="btn btn-primary">
                <x-ui.icon name="plus" class="w-4 h-4" /> New Order
            </a>
        @endcan
    </div>

    <div class="card">
        {{-- Status Tabs --}}
        <div class="card-header flex-col sm:flex-row gap-3">
            <div class="tabs overflow-x-auto">
                <button wire:click="$set('statusFilter','all')"
                        @class(['tab', 'active' => $statusFilter === 'all'])>
                    All
                    <span class="badge badge-neutral ml-1">{{ $this->statusCounts['all'] ?? 0 }}</span>
                </button>
                @foreach ($this->statuses as $status)
                    <button wire:click="$set('statusFilter','{{ $status->value }}')"
                            @class(['tab', 'active' => $statusFilter === $status->value])>
                        {{ $status->label() }}
                        @if (($this->statusCounts[$status->value] ?? 0) > 0)
                            <span class="badge {{ $status->badge() }} ml-1">{{ $this->statusCounts[$status->value] }}</span>
                        @endif
                    </button>
                @endforeach
            </div>

            <div class="relative ml-auto">
                <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                <input wire:model.live.debounce.300ms="search"
                       type="text"
                       placeholder="Search DO number, consignee, VW…"
                       class="input !pl-9 sm:w-64" />
            </div>
        </div>

        {{-- Collapsible Filters --}}
        <div x-data="{ open: {{ ($priorityFilter || $dateFrom || $dateTo || $overdueOnly) ? 'true' : 'false' }} }"
             class="border-b border-white/40 dark:border-white/[0.06]">

            <button
                type="button"
                x-on:click="open = !open"
                class="flex w-full items-center gap-2 px-4 py-2.5 text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors"
            >
                <x-ui.icon name="filter" class="w-4 h-4" />
                <span>Filters</span>
                @if ($priorityFilter || $dateFrom || $dateTo || $overdueOnly)
                    <span class="badge badge-simba ml-1">Active</span>
                @endif
                <x-ui.icon name="arrow-right" class="w-3.5 h-3.5 ml-auto transition-transform duration-200" x-bind:class="open ? 'rotate-90' : ''" />
            </button>

            <div x-show="open" x-collapse class="px-4 pb-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-3">
                    {{-- Priority --}}
                    <div>
                        <label class="label">Priority</label>
                        <select wire:model.live="priorityFilter" class="input">
                            <option value="">All Priorities</option>
                            @foreach ($this->priorities as $priority)
                                <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Date From --}}
                    <div>
                        <label class="label">Required Date From</label>
                        <input wire:model.live="dateFrom" type="date" class="input tabular-nums" />
                    </div>

                    {{-- Date To --}}
                    <div>
                        <label class="label">Required Date To</label>
                        <input wire:model.live="dateTo" type="date" class="input tabular-nums" />
                    </div>

                    {{-- Overdue Only --}}
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 cursor-pointer text-sm text-slate-600 dark:text-slate-400 pb-1">
                            <input wire:model.live="overdueOnly"
                                   type="checkbox"
                                   class="rounded text-simba-600 focus:ring-simba-600" />
                            Overdue only
                        </label>
                    </div>
                </div>

                @if ($priorityFilter || $dateFrom || $dateTo || $overdueOnly)
                    <div class="mt-3 flex justify-end">
                        <button
                            wire:click="$set('priorityFilter', ''); $set('dateFrom', ''); $set('dateTo', ''); $set('overdueOnly', false)"
                            type="button"
                            class="btn btn-secondary btn-sm"
                        >
                            <x-ui.icon name="x" class="w-3.5 h-3.5" /> Clear Filters
                        </button>
                    </div>
                @endif
            </div>
        </div>

        {{-- Table --}}
        <x-ui.table>
            <thead>
                <tr>
                    <th>DO Number</th>
                    <th>Department / VW</th>
                    <th>Consignee</th>
                    <th>Required Date</th>
                    <th>Priority</th>
                    <th>Lines</th>
                    <th>Status</th>
                    <th>Requested By</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->orders as $order)
                    <tr wire:key="do-{{ $order->id }}">
                        {{-- DO Number --}}
                        <td>
                            <a href="{{ route('outbound.orders.show', $order) }}"
                               wire:navigate
                               class="mono font-semibold text-simba-600 dark:text-simba-400 hover:underline">
                                {{ $order->number }}
                            </a>
                        </td>

                        {{-- Department / VW --}}
                        <td>
                            <div class="text-sm font-medium text-slate-800 dark:text-slate-100">
                                {{ $order->department?->name ?? '—' }}
                            </div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $order->virtualWarehouse?->name ?? '—' }}
                            </div>
                        </td>

                        {{-- Consignee --}}
                        <td class="text-sm">
                            {{ $order->consigneePartner?->name ?? '—' }}
                        </td>

                        {{-- Required Date --}}
                        <td class="tabular-nums text-sm {{ $order->isOverdue() ? 'text-red-600 dark:text-red-400 font-semibold' : '' }}">
                            {{ $order->required_date?->format('d M Y') ?? '—' }}
                            @if ($order->isOverdue())
                                <span class="block text-xs font-normal">Overdue</span>
                            @endif
                        </td>

                        {{-- Priority --}}
                        <td>
                            <span class="badge {{ $order->priority->badge() }}">
                                <span class="dot"></span>{{ $order->priority->label() }}
                            </span>
                        </td>

                        {{-- Lines / Allocation --}}
                        <td>
                            @php
                                $linesCount = $order->lines_count ?? 0;
                                $allocatedQty = $order->lines->sum('allocated_qty');
                                $requestedQty = $order->lines->sum('requested_qty');
                                $allocPct = $requestedQty > 0
                                    ? (int) min(100, round(($allocatedQty / $requestedQty) * 100))
                                    : 0;
                            @endphp
                            <div class="text-sm tabular-nums">{{ $linesCount }} {{ Str::plural('line', $linesCount) }}</div>
                            @if ($linesCount > 0)
                                <div class="flex items-center gap-1.5 mt-1">
                                    <div class="progress w-16">
                                        <div class="{{ $allocPct >= 100 ? 'bg-emerald-600' : 'bg-simba-500' }}" style="width:{{ $allocPct }}%"></div>
                                    </div>
                                    <span class="text-xs tabular-nums text-slate-500">{{ $allocPct }}%</span>
                                </div>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td>
                            <span class="badge {{ $order->status->badge() }}">
                                <span class="dot"></span>{{ $order->status->label() }}
                            </span>
                        </td>

                        {{-- Requested By --}}
                        <td class="text-sm text-slate-600 dark:text-slate-400">
                            {{ $order->requestedBy?->name ?? '—' }}
                        </td>

                        {{-- Actions --}}
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('outbound.orders.show', $order) }}"
                                   wire:navigate
                                   class="btn btn-secondary btn-sm">
                                    <x-ui.icon name="eye" class="w-3.5 h-3.5" />
                                </a>

                                @if ($order->status === \App\Enums\Outbound\DeliveryOrderStatus::Draft)
                                    <button wire:click="submitOrder({{ $order->id }})"
                                            class="btn btn-primary btn-sm">
                                        <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" /> Submit
                                    </button>
                                @endif

                                @if ($order->status === \App\Enums\Outbound\DeliveryOrderStatus::Approved)
                                    <button wire:click="allocateOrder({{ $order->id }})"
                                            class="btn btn-primary btn-sm">
                                        <x-ui.icon name="clipboard" class="w-3.5 h-3.5" /> Allocate
                                    </button>
                                @endif

                                @if (in_array($order->status, [
                                    \App\Enums\Outbound\DeliveryOrderStatus::Allocated,
                                    \App\Enums\Outbound\DeliveryOrderStatus::PartiallyAllocated,
                                ], true))
                                    <a href="{{ route('outbound.pick-lists.index', ['deliveryOrderId' => $order->id]) }}"
                                       wire:navigate
                                       class="btn btn-primary btn-sm">
                                        <x-ui.icon name="clipboard" class="w-3.5 h-3.5" /> Pick List
                                    </a>
                                @endif

                                @if ($order->status === \App\Enums\Outbound\DeliveryOrderStatus::Submitted)
                                    <button wire:click="openRejectModal({{ $order->id }})"
                                            class="btn btn-secondary btn-sm">
                                        <x-ui.icon name="x" class="w-3.5 h-3.5" /> Reject
                                    </button>
                                @endif

                                @if (! in_array($order->status, [
                                    \App\Enums\Outbound\DeliveryOrderStatus::Dispatched,
                                    \App\Enums\Outbound\DeliveryOrderStatus::Delivered,
                                    \App\Enums\Outbound\DeliveryOrderStatus::Cancelled,
                                    \App\Enums\Outbound\DeliveryOrderStatus::Rejected,
                                ], true))
                                    <button wire:click="cancelOrder({{ $order->id }})"
                                            wire:confirm="Cancel order {{ $order->number }}? This cannot be undone."
                                            class="btn btn-secondary btn-sm !text-red-600 dark:!text-red-400">
                                        <x-ui.icon name="trash" class="w-3.5 h-3.5" /> Cancel
                                    </button>
                                @endif
                                @can('delete', $order)
                                    <button wire:click="$set('deleteOrderId', {{ $order->id }})"
                                            class="btn btn-secondary btn-sm !text-red-700 dark:!text-red-400 font-semibold">
                                        <x-ui.icon name="trash" class="w-3.5 h-3.5" /> Delete
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="!py-12 text-center text-slate-500">
                            @if ($search || $statusFilter !== 'all' || $priorityFilter || $dateFrom || $dateTo || $overdueOnly)
                                No orders match your current filters.
                            @else
                                No delivery orders found.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        <div class="px-4 py-3 border-t border-white/40 dark:border-white/[0.06]">
            {{ $this->orders->links() }}
        </div>
    </div>

    {{-- Reject Modal --}}
    @if ($showRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog"
             aria-modal="true"
             x-data
             x-on:keydown.escape.window="$wire.showRejectModal = false">

            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/50 backdrop-blur-[2px]"
                 wire:click="$set('showRejectModal', false)"></div>

            {{-- Panel --}}
            <div class="relative z-10 w-full max-w-md rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl ring-1 ring-black/[0.06] dark:ring-white/[0.08]">

                {{-- Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-white/[0.08]">
                    <h3 class="text-[15px] font-semibold text-slate-900 dark:text-white">Reject Order</h3>
                    <button type="button"
                            wire:click="$set('showRejectModal', false)"
                            class="icon-btn -mr-2 -mt-0.5 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200"
                            aria-label="Close">
                        <x-ui.icon name="x" class="h-4 w-4" />
                    </button>
                </div>

                {{-- Body --}}
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

                {{-- Footer --}}
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

    {{-- Delete Confirmation Modal --}}
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
