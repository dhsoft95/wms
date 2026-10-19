<div class="space-y-6">
    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Purchase Orders</h2>
            <p class="page-sub">Manage inbound purchase orders from creation through receiving.</p>
        </div>
        @can('po.create')
            <a href="{{ route('inbound.orders.create') }}" wire:navigate class="btn btn-primary">
                <x-ui.icon name="plus" class="w-4 h-4" /> New Purchase Order
            </a>
        @endcan
    </div>

    <div class="card">
        {{-- Filters Bar --}}
        <div class="card-header flex-col sm:flex-row gap-3">
            {{-- Status Filter --}}
            <div class="flex flex-wrap gap-2">
                <button wire:click="$set('statusFilter', '')"
                        @class(['tab', 'active' => $statusFilter === ''])>
                    All
                </button>
                @foreach ($this->statusOptions as $status)
                    <button wire:click="$set('statusFilter', '{{ $status->value }}')"
                            @class(['tab', 'active' => $statusFilter === $status->value])>
                        {{ $status->label() }}
                    </button>
                @endforeach
            </div>

            {{-- Search --}}
            <div class="relative ml-auto">
                <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                <input wire:model.live.debounce.300ms="search"
                       type="text"
                       placeholder="Search PO number or ref…"
                       class="input !pl-9 sm:w-64" />
            </div>
        </div>

        {{-- Table --}}
        <x-ui.table>
            <thead>
                <tr>
                    <th>PO Number</th>
                    <th>Source / Type</th>
                    <th>Warehouse</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <th>Expected Date</th>
                    <th>Requested By</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->orders as $order)
                    <tr wire:key="po-{{ $order->id }}">
                        {{-- PO Number --}}
                        <td>
                            <a href="{{ route('inbound.orders.show', $order) }}"
                               wire:navigate
                               class="mono font-semibold text-simba-600 dark:text-simba-400 hover:underline">
                                {{ $order->number }}
                            </a>
                            @if ($order->customer_po_ref)
                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ $order->customer_po_ref }}</div>
                            @endif
                        </td>

                        {{-- Source / Type --}}
                        <td>
                            <div class="text-sm font-medium text-slate-800 dark:text-slate-100 capitalize">
                                {{ $order->source?->value ?? '—' }}
                            </div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $order->package_type?->label() ?? '—' }}
                            </div>
                        </td>

                        {{-- Warehouse --}}
                        <td class="text-sm text-slate-700 dark:text-slate-300">
                            {{ $order->warehouse?->name ?? '—' }}
                        </td>

                        {{-- Supplier --}}
                        <td class="text-sm text-slate-700 dark:text-slate-300">
                            {{ $order->supplierPartner?->name ?? '—' }}
                        </td>

                        {{-- Status --}}
                        <td>
                            <span class="badge {{ $order->status?->color() ?? 'badge-slate' }}">
                                <span class="dot"></span>{{ $order->status?->label() }}
                            </span>
                        </td>

                        {{-- Expected Date --}}
                        <td class="tabular-nums text-sm text-slate-600 dark:text-slate-400">
                            {{ $order->expected_date?->format('d M Y') ?? '—' }}
                        </td>

                        {{-- Requested By --}}
                        <td class="text-sm text-slate-600 dark:text-slate-400">
                            {{ $order->requestedBy?->name ?? '—' }}
                        </td>

                        {{-- Actions --}}
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('inbound.orders.show', $order) }}"
                                   wire:navigate
                                   class="btn btn-secondary btn-sm">
                                    <x-ui.icon name="eye" class="w-3.5 h-3.5" />
                                </a>

                                @if ($order->status === \App\Enums\Inbound\PurchaseOrderStatus::Draft)
                                    <a href="{{ route('inbound.orders.edit', $order) }}"
                                       wire:navigate
                                       class="btn btn-secondary btn-sm">
                                        <x-ui.icon name="pencil" class="w-3.5 h-3.5" />
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="!py-12 text-center text-slate-500">
                            @if ($search || $statusFilter)
                                No purchase orders match your current filters.
                            @else
                                No purchase orders found.
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
</div>
