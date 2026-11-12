<div class="space-y-6">
    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Orders</h2>
            <p class="page-sub">Purchase orders, delivery requests and department transfers in one place.</p>
        </div>
        @if ($this->canCreateAny)
            <button wire:click="$set('showTypeModal', true)" class="btn btn-primary">
                <x-ui.icon name="plus" class="w-4 h-4" /> New Order
            </button>
        @endif
    </div>

    <div class="card">
        {{-- Type Tabs --}}
        <div class="card-header flex-col sm:flex-row gap-3">
            <div class="tabs overflow-x-auto">
                <button wire:click="$set('tab', 'all')"
                        @class(['tab', 'active' => $tab === 'all'])>
                    All
                </button>
                <button wire:click="$set('tab', 'po')"
                        @class(['tab', 'active' => $tab === 'po'])>
                    Purchase Orders
                </button>
                <button wire:click="$set('tab', 'mrn')"
                        @class(['tab', 'active' => $tab === 'mrn'])>
                    MRN
                </button>
                <button wire:click="$set('tab', 'transfer')"
                        @class(['tab', 'active' => $tab === 'transfer'])>
                    Transfers
                </button>
            </div>

            {{-- Search --}}
            <div class="relative ml-auto">
                <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                <input wire:model.live.debounce.300ms="search"
                       type="text"
                       placeholder="Search order number…"
                       class="input !pl-9 sm:w-64" />
            </div>
        </div>

        {{-- Filter Bar --}}
        <div x-data="{ open: {{ ($statusFilter || $dateFrom || $dateTo) ? 'true' : 'false' }} }"
             class="border-b border-white/40 dark:border-white/[0.06]">

            <button
                type="button"
                x-on:click="open = !open"
                class="flex w-full items-center gap-2 px-4 py-2.5 text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 transition-colors"
            >
                <x-ui.icon name="filter" class="w-4 h-4" />
                <span>Filters</span>
                @if ($statusFilter || $dateFrom || $dateTo)
                    <span class="badge badge-simba ml-1">Active</span>
                @endif
                <x-ui.icon name="arrow-right" class="w-3.5 h-3.5 ml-auto transition-transform duration-200" x-bind:class="open ? 'rotate-90' : ''" />
            </button>

            <div x-show="open" x-collapse class="px-4 pb-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-3">
                    {{-- Status --}}
                    <div>
                        <label class="label">Status</label>
                        <input wire:model.live="statusFilter"
                               type="text"
                               placeholder="e.g. draft, submitted…"
                               class="input" />
                    </div>

                    {{-- Date From --}}
                    <div>
                        <label class="label">Created From</label>
                        <input wire:model.live="dateFrom" type="date" class="input tabular-nums" />
                    </div>

                    {{-- Date To --}}
                    <div>
                        <label class="label">Created To</label>
                        <input wire:model.live="dateTo" type="date" class="input tabular-nums" />
                    </div>
                </div>

                @if ($statusFilter || $dateFrom || $dateTo)
                    <div class="mt-3 flex justify-end">
                        <button
                            wire:click="$set('statusFilter', ''); $set('dateFrom', ''); $set('dateTo', '')"
                            type="button"
                            class="btn btn-secondary btn-sm"
                        >
                            <x-ui.icon name="x" class="w-3.5 h-3.5" /> Clear Filters
                        </button>
                    </div>
                @endif
            </div>
        </div>

        {{-- Bulk action bar --}}
        @if (auth()->user()->hasRole('system-admin') && count($selectedItems) > 0)
            <div class="flex items-center gap-3 px-4 py-2.5 bg-red-50 dark:bg-red-900/20 border-b border-red-200 dark:border-red-800/50">
                <span class="text-sm font-medium text-red-700 dark:text-red-400">
                    {{ count($selectedItems) }} {{ count($selectedItems) === 1 ? 'record' : 'records' }} selected
                </span>
                <button type="button"
                        wire:click="$set('showBulkDeleteModal', true)"
                        class="btn btn-sm bg-red-600 hover:bg-red-700 text-white font-semibold ml-auto">
                    <x-ui.icon name="trash" class="w-3.5 h-3.5" /> Delete Selected
                </button>
                <button type="button" wire:click="deselectAll" class="btn btn-sm btn-secondary">
                    Clear
                </button>
            </div>
        @endif

        {{-- Table --}}
        <x-ui.table>
            <thead>
                <tr>
                    @if (auth()->user()->hasRole('system-admin'))
                        <th class="w-10 text-center">
                            <input type="checkbox"
                                   x-data
                                   {{ $this->allOnPageSelected ? 'checked' : '' }}
                                   :indeterminate="{{ (!$this->allOnPageSelected && count($selectedItems) > 0) ? 'true' : 'false' }}"
                                   x-on:change="{{ $this->allOnPageSelected ? '$wire.deselectAll()' : '$wire.selectAll()' }}"
                                   class="rounded border-slate-300 text-simba-600 focus:ring-simba-500 dark:border-slate-600 dark:bg-slate-800" />
                        </th>
                    @endif
                    <th>#</th>
                    <th>Type</th>
                    <th>Number</th>
                    <th>Department / VW</th>
                    <th>Progress</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->orders as $order)
                    <tr wire:key="order-{{ $order->rec_type }}-{{ $order->id }}">

                        @if (auth()->user()->hasRole('system-admin'))
                            <td class="w-10 text-center">
                                <input type="checkbox"
                                       {{ $this->isSelected($order->rec_type, $order->id) ? 'checked' : '' }}
                                       wire:click="toggleItem('{{ $order->rec_type }}', {{ $order->id }}, '{{ $order->number }}')"
                                       class="rounded border-slate-300 text-simba-600 focus:ring-simba-500 dark:border-slate-600 dark:bg-slate-800" />
                            </td>
                        @endif

                        {{-- Row number --}}
                        <td class="tabular-nums text-slate-400 text-sm">
                            {{ ($this->orders->currentPage() - 1) * $this->orders->perPage() + $loop->iteration }}
                        </td>

                        {{-- Type badge --}}
                        <td>
                            @if ($order->rec_type === 'po')
                                <span class="badge badge-blue">Purchase Order</span>
                            @elseif ($order->rec_type === 'mrn')
                                <span class="badge badge-indigo">MRN</span>
                            @elseif ($order->rec_type === 'mov')
                                <span class="badge badge-slate">In-WH Transfer</span>
                            @elseif ($order->rec_type === 'trf')
                                <span class="badge badge-simba">Dept. Transfer</span>
                            @elseif ($order->rec_type === 'wtr')
                                <span class="badge badge-purple">WH Transfer</span>
                            @else
                                <span class="badge badge-slate">Transfer</span>
                            @endif
                        </td>

                        {{-- Number (linked) --}}
                        <td>
                            @if ($order->rec_type === 'po')
                                <a href="{{ route('inbound.orders.show', $order->id) }}"
                                   wire:navigate
                                   class="mono font-semibold text-simba-600 dark:text-simba-400 hover:underline">
                                    {{ $order->number }}
                                </a>
                            @elseif ($order->rec_type === 'mrn')
                                <a href="{{ route('outbound.orders.show', $order->id) }}"
                                   wire:navigate
                                   class="mono font-semibold text-simba-600 dark:text-simba-400 hover:underline">
                                    {{ $order->number }}
                                </a>
                            @elseif ($order->rec_type === 'mov')
                                <a href="{{ route('warehouse.movements.show', $order->id) }}"
                                   wire:navigate
                                   class="mono font-semibold text-simba-600 dark:text-simba-400 hover:underline">
                                    {{ $order->number }}
                                </a>
                            @elseif ($order->rec_type === 'trf')
                                <a href="{{ route('warehouse.transfers.show', $order->id) }}"
                                   wire:navigate
                                   class="mono font-semibold text-simba-600 dark:text-simba-400 hover:underline">
                                    {{ $order->number }}
                                </a>
                            @elseif ($order->rec_type === 'wtr')
                                <a href="{{ route('warehouse.wtr.show', $order->id) }}"
                                   wire:navigate
                                   class="mono font-semibold text-simba-600 dark:text-simba-400 hover:underline">
                                    {{ $order->number }}
                                </a>
                            @else
                                <span class="mono font-semibold text-slate-600">{{ $order->number }}</span>
                            @endif
                        </td>

                        {{-- Department / VW --}}
                        <td class="text-sm text-slate-600 dark:text-slate-400">
                            {{ $order->department_name ?? '—' }}
                        </td>

                        {{-- Progress --}}
                        <td>
                            @php
                                $pct      = (int) ($order->progress_pct ?? 0);
                                $barColor = $pct >= 100 ? 'bg-emerald-500' : ($pct > 0 ? 'bg-simba-500' : 'bg-slate-200 dark:bg-slate-700');
                            @endphp
                            @if ($order->rec_type === 'po')
                                <div class="flex items-center gap-2 min-w-[90px]">
                                    <div class="progress flex-1">
                                        <div class="{{ $barColor }}" style="width:{{ $pct }}%"></div>
                                    </div>
                                    <span class="text-xs tabular-nums text-slate-500 dark:text-slate-400 shrink-0">{{ $pct }}%</span>
                                </div>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>

                        {{-- Date --}}
                        <td class="tabular-nums text-sm text-slate-600 dark:text-slate-400">
                            {{ $order->doc_date ? \Carbon\Carbon::parse($order->doc_date)->format('d M Y') : '—' }}
                        </td>

                        {{-- Status --}}
                        <td>
                            <span class="badge badge-neutral">
                                <span class="dot"></span>{{ ucfirst(str_replace('_', ' ', $order->status)) }}
                            </span>
                        </td>

                        {{-- Actions --}}
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                @if ($order->rec_type === 'po')
                                    <a href="{{ route('inbound.orders.show', $order->id) }}"
                                       wire:navigate class="btn btn-secondary btn-sm">
                                        <x-ui.icon name="eye" class="w-3.5 h-3.5" />
                                    </a>
                                @elseif ($order->rec_type === 'mrn')
                                    <a href="{{ route('outbound.orders.show', $order->id) }}"
                                       wire:navigate class="btn btn-secondary btn-sm">
                                        <x-ui.icon name="eye" class="w-3.5 h-3.5" />
                                    </a>
                                @elseif ($order->rec_type === 'mov')
                                    <a href="{{ route('warehouse.movements.show', $order->id) }}"
                                       wire:navigate class="btn btn-secondary btn-sm">
                                        <x-ui.icon name="eye" class="w-3.5 h-3.5" />
                                    </a>
                                @elseif ($order->rec_type === 'trf')
                                    <a href="{{ route('warehouse.transfers.show', $order->id) }}"
                                       wire:navigate class="btn btn-secondary btn-sm">
                                        <x-ui.icon name="eye" class="w-3.5 h-3.5" />
                                    </a>
                                @elseif ($order->rec_type === 'wtr')
                                    <a href="{{ route('warehouse.wtr.show', $order->id) }}"
                                       wire:navigate class="btn btn-secondary btn-sm">
                                        <x-ui.icon name="eye" class="w-3.5 h-3.5" />
                                    </a>
                                @endif

                                @if (auth()->user()->hasRole('system-admin'))
                                    <button type="button"
                                            wire:click="openDeleteModal('{{ $order->rec_type }}', {{ $order->id }}, '{{ $order->number }}')"
                                            class="btn btn-sm border border-red-200 dark:border-red-800/50 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                                        <x-ui.icon name="trash" class="w-3.5 h-3.5" />
                                    </button>
                                @endif
                            </div>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->user()->hasRole('system-admin') ? 9 : 8 }}" class="!py-12 text-center text-slate-500">
                            @if ($search || $statusFilter || $dateFrom || $dateTo)
                                No orders match your current filters.
                            @else
                                No orders found.
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

    {{-- ── Bulk Delete Confirmation Modal ────────────────────────────────────── --}}
    @if ($showBulkDeleteModal)
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 sm:p-6"
             role="dialog"
             aria-modal="true"
             x-data
             x-on:keydown.escape.window="$wire.showBulkDeleteModal = false">

            <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"
                 wire:click="$set('showBulkDeleteModal', false)"></div>

            <div class="relative z-10 w-full max-w-md overflow-hidden rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl ring-1 ring-black/5 dark:ring-white/10">

                <button type="button"
                        wire:click="$set('showBulkDeleteModal', false)"
                        class="absolute right-3 top-3 rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-white/5 dark:hover:text-slate-200"
                        aria-label="Close">
                    <x-ui.icon name="x" class="h-4 w-4" />
                </button>

                <div class="px-6 pt-7 pb-5 text-center sm:text-left sm:flex sm:gap-4">
                    <div class="mx-auto sm:mx-0 flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-50 ring-8 ring-red-50/50 dark:bg-red-500/10 dark:ring-red-500/5">
                        <x-ui.icon name="trash" class="h-5 w-5 text-red-600 dark:text-red-400" />
                    </div>

                    <div class="mt-4 sm:mt-0.5 flex-1">
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">
                            Delete {{ count($selectedItems) }} {{ count($selectedItems) === 1 ? 'Record' : 'Records' }}
                        </h3>

                        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                            Permanently delete <strong class="font-semibold text-slate-900 dark:text-white">{{ count($selectedItems) }} {{ count($selectedItems) === 1 ? 'record' : 'records' }}</strong>
                            and all related data (lines, approvals, allocations)?
                        </p>
                    </div>
                </div>

                <div class="mx-6 mb-5 flex gap-2.5 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 dark:border-red-500/20 dark:bg-red-500/10">
                    <x-ui.icon name="trash" class="mt-0.5 h-4 w-4 shrink-0 text-red-600 dark:text-red-400" />
                    <p class="text-xs leading-relaxed text-red-700 dark:text-red-300">
                        Stock reservations will be released where applicable. <span class="font-semibold">This cannot be undone.</span>
                    </p>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50/80 px-6 py-4 sm:flex-row sm:justify-end sm:gap-3 dark:border-white/10 dark:bg-white/[0.02]">
                    <button type="button"
                            wire:click="$set('showBulkDeleteModal', false)"
                            class="btn btn-secondary w-full sm:w-auto justify-center">
                        Cancel
                    </button>
                    <button type="button"
                            wire:click="bulkDelete"
                            wire:loading.attr="disabled"
                            wire:target="bulkDelete"
                            class="btn w-full sm:w-auto justify-center bg-red-600 font-semibold text-white shadow-sm transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-70">
                <span wire:loading.remove wire:target="bulkDelete" class="inline-flex items-center gap-2">
                    <x-ui.icon name="trash" class="w-4 h-4" /> Delete Permanently
                </span>
                        <span wire:loading wire:target="bulkDelete">Deleting…</span>
                    </button>
                </div>

            </div>
        </div>
    @endif

    {{-- ── Delete Confirmation Modal ─────────────────────────────────────────── --}}
    @if ($deleteType && $deleteId)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog"
             aria-modal="true"
             x-data
             x-on:keydown.escape.window="$wire.deleteType = null; $wire.deleteId = null">

            <div class="absolute inset-0 bg-black/50 backdrop-blur-[2px]"
                 wire:click="$set('deleteType', null)"></div>

            <div class="relative z-10 w-full max-w-md rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl ring-1 ring-black/[0.06] dark:ring-white/[0.08]">

                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-white/[0.08]">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center shrink-0">
                            <x-ui.icon name="trash" class="w-4 h-4 text-red-600 dark:text-red-400" />
                        </div>
                        <h3 class="text-[15px] font-semibold text-slate-900 dark:text-white">Delete Record</h3>
                    </div>
                    <button type="button"
                            wire:click="$set('deleteType', null)"
                            class="icon-btn -mr-2 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200"
                            aria-label="Close">
                        <x-ui.icon name="x" class="h-4 w-4" />
                    </button>
                </div>

                <div class="px-6 py-5 space-y-3">
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        Permanently delete <strong class="font-mono text-slate-900 dark:text-white">{{ $deleteNumber }}</strong> and all related records?
                    </p>
                    <p class="text-sm text-red-600 dark:text-red-400">
                        @if ($deleteType === 'mrn')
                            Stock reservations will be released and pick movements reversed.
                        @else
                            All child records (lines, approvals, etc.) will also be removed.
                        @endif
                        This cannot be undone.
                    </p>
                </div>

                <div class="flex justify-end gap-3 px-6 py-4 bg-slate-50/80 dark:bg-white/[0.02] border-t border-slate-100 dark:border-white/[0.08] rounded-b-2xl">
                    <button type="button"
                            wire:click="$set('deleteType', null)"
                            class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="button"
                            wire:click="deleteRecord"
                            wire:loading.attr="disabled"
                            wire:target="deleteRecord"
                            class="btn bg-red-600 hover:bg-red-700 text-white font-semibold">
                        <span wire:loading.remove wire:target="deleteRecord">
                            <x-ui.icon name="trash" class="w-4 h-4" /> Delete Permanently
                        </span>
                        <span wire:loading wire:target="deleteRecord">Deleting…</span>
                    </button>
                </div>

            </div>
        </div>
    @endif

    {{-- New Order Type Selection Modal --}}
    @if ($showTypeModal)
        <x-ui.modal title="New Order" wireClose="$set('showTypeModal', false); $set('showTransferStep', false)" maxWidth="lg">

            @if(! $showTransferStep)

                <p class="text-sm text-slate-500 dark:text-slate-400 -mt-1">
                    Select the type of order you want to create.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-2">

                    @can('po.create')
                        <a href="{{ route('inbound.orders.create') }}"
                           wire:navigate
                           class="block p-4 rounded-lg border border-slate-200 dark:border-white/[0.08] hover:border-simba-400 dark:hover:border-simba-500 hover:bg-simba-50 dark:hover:bg-simba-500/10 transition group">
                            <div class="flex items-center gap-3 mb-2">
                                <span class="w-8 h-8 rounded-lg bg-simba-100 dark:bg-simba-500/20 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="cart" class="w-4 h-4 text-simba-600 dark:text-simba-400" />
                                </span>
                                <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">GRN</span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                Inbound goods from a supplier or return.
                            </p>
                        </a>
                    @endcan

                    @can('orders.create')
                        <a href="{{ route('outbound.orders.create') }}"
                           wire:navigate
                           class="block p-4 rounded-lg border border-slate-200 dark:border-white/[0.08] hover:border-simba-400 dark:hover:border-simba-500 hover:bg-simba-50 dark:hover:bg-simba-500/10 transition group">
                            <div class="flex items-center gap-3 mb-2">
                                <span class="w-8 h-8 rounded-lg bg-simba-100 dark:bg-simba-500/20 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="truck" class="w-4 h-4 text-simba-600 dark:text-simba-400" />
                                </span>
                                <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">MRN</span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                Material request for outbound delivery.
                            </p>
                        </a>
                    @endcan

                    @can('transfers.create')
                        <button type="button"
                                wire:click="$set('showTransferStep', true)"
                                class="block w-full text-left p-4 rounded-lg border border-slate-200 dark:border-white/[0.08] hover:border-simba-400 dark:hover:border-simba-500 hover:bg-simba-50 dark:hover:bg-simba-500/10 transition group">
                            <div class="flex items-center gap-3 mb-2">
                                <span class="w-8 h-8 rounded-lg bg-simba-100 dark:bg-simba-500/20 flex items-center justify-center shrink-0">
                                    <x-ui.icon name="arrows" class="w-4 h-4 text-simba-600 dark:text-simba-400" />
                                </span>
                                <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">STN</span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                                Move stock between locations or departments.
                            </p>
                        </button>
                    @endcan

                </div>

            @else

                {{-- Transfer type sub-step --}}
                <div class="flex items-center gap-2 mb-4">
                    <button type="button" wire:click="$set('showTransferStep', false)"
                            class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">
                        <x-ui.icon name="arrow-left" class="w-4 h-4" />
                    </button>
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">Select transfer type</p>
                </div>

                <div class="grid grid-cols-1 gap-3">

                    <a href="{{ route('warehouse.movements.create') }}"
                       wire:navigate
                       class="block p-4 rounded-lg border border-slate-200 dark:border-white/[0.08] hover:border-simba-400 dark:hover:border-simba-500 hover:bg-simba-50 dark:hover:bg-simba-500/10 transition">
                        <div class="flex items-center gap-3 mb-1">
                            <span class="w-8 h-8 rounded-lg bg-simba-100 dark:bg-simba-500/20 flex items-center justify-center shrink-0">
                                <x-ui.icon name="refresh" class="w-4 h-4 text-simba-600 dark:text-simba-400" />
                            </span>
                            <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">In-Warehouse Transfer (MOV)</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed ml-11">
                            Same warehouse &amp; department. Move stock between bins.
                        </p>
                    </a>

                    <a href="{{ route('warehouse.transfers.create') }}"
                       wire:navigate
                       class="block p-4 rounded-lg border border-slate-200 dark:border-white/[0.08] hover:border-simba-400 dark:hover:border-simba-500 hover:bg-simba-50 dark:hover:bg-simba-500/10 transition">
                        <div class="flex items-center gap-3 mb-1">
                            <span class="w-8 h-8 rounded-lg bg-simba-100 dark:bg-simba-500/20 flex items-center justify-center shrink-0">
                                <x-ui.icon name="arrows" class="w-4 h-4 text-simba-600 dark:text-simba-400" />
                            </span>
                            <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">Departmental Transfer (STN)</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed ml-11">
                            Change stock ownership between virtual warehouses / departments.
                        </p>
                    </a>

                    <a href="{{ route('warehouse.wtr.create') }}"
                       wire:navigate
                       class="block p-4 rounded-lg border border-slate-200 dark:border-white/[0.08] hover:border-simba-400 dark:hover:border-simba-500 hover:bg-simba-50 dark:hover:bg-simba-500/10 transition">
                        <div class="flex items-center gap-3 mb-1">
                            <span class="w-8 h-8 rounded-lg bg-simba-100 dark:bg-simba-500/20 flex items-center justify-center shrink-0">
                                <x-ui.icon name="truck" class="w-4 h-4 text-simba-600 dark:text-simba-400" />
                            </span>
                            <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">Warehouse Transfer (WTR)</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed ml-11">
                            Physical move from one warehouse to another. Generates DN + RCN.
                        </p>
                    </a>

                </div>

            @endif

            <x-slot:footer>
                <button type="button"
                        wire:click="$set('showTypeModal', false); $set('showTransferStep', false)"
                        class="btn btn-secondary">
                    Cancel
                </button>
            </x-slot:footer>

        </x-ui.modal>
    @endif
</div>
