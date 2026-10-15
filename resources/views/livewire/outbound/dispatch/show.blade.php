<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <a href="{{ route('outbound.dispatch.index') }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-simba-600 mb-2">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Back to Dispatch
            </a>
            <h2 class="page-title font-mono">{{ $this->record->number }}</h2>
            <div class="flex items-center gap-3 mt-1">
                <span class="text-sm text-slate-500">
                    {{ $this->record->dispatched_at?->format('d M Y H:i') }}
                </span>
                @if ($this->record->gatePass)
                    <span class="badge {{ $this->record->gatePass->status->value === 'exited' ? 'badge-emerald' : 'badge-amber' }}">
                        <span class="dot"></span>
                        Gate {{ $this->record->gatePass->status->label() }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- Dispatch Details --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left: Header Info --}}
        <div class="lg:col-span-2 space-y-6">

            <div class="card">
                <div class="card-header"><h3 class="card-title">Shipment Details</h3></div>
                <div class="card-body grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="section-label">Delivery Order</p>
                        <a href="{{ route('outbound.orders.show', $this->record->delivery_order_id) }}" wire:navigate
                           class="font-mono font-semibold text-simba-600 hover:underline">
                            {{ $this->record->deliveryOrder?->number }}
                        </a>
                    </div>
                    <div>
                        <p class="section-label">Consignee</p>
                        <p>{{ $this->record->deliveryOrder?->consigneePartner?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="section-label">Virtual Warehouse</p>
                        <p>{{ $this->record->deliveryOrder?->virtualWarehouse?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="section-label">Vehicle No</p>
                        <p class="font-mono font-semibold">{{ $this->record->vehicle_no }}</p>
                    </div>
                    <div>
                        <p class="section-label">Driver</p>
                        <p>{{ $this->record->driver_name }}</p>
                    </div>
                    <div>
                        <p class="section-label">Driver Phone</p>
                        <p class="tabular-nums">{{ $this->record->driver_phone ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="section-label">Driver ID No</p>
                        <p class="font-mono">{{ $this->record->driver_id_no ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="section-label">Transporter</p>
                        <p>{{ $this->record->transporterPartner?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="section-label">Seal No</p>
                        <p class="font-mono">{{ $this->record->seal_no ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="section-label">Dispatched By</p>
                        <p>{{ $this->record->dispatchedBy?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="section-label">Dispatched At</p>
                        <p class="tabular-nums">{{ $this->record->dispatched_at?->format('d M Y H:i') ?? '—' }}</p>
                    </div>
                    @if ($this->record->notes)
                    <div class="col-span-2 sm:col-span-3">
                        <p class="section-label">Notes</p>
                        <p>{{ $this->record->notes }}</p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Items --}}
            <div class="card">
                <div class="card-header"><h3 class="card-title">Dispatched Items</h3></div>
                <x-ui.table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Batch</th>
                            <th class="text-right">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->record->items as $item)
                            <tr>
                                <td>
                                    <p class="font-mono text-xs text-slate-500">{{ $item->item?->sku }}</p>
                                    <p class="font-medium">{{ $item->item?->name }}</p>
                                </td>
                                <td class="font-mono text-sm">{{ $item->batch?->batch_no ?? '—' }}</td>
                                <td class="text-right tabular-nums font-semibold">{{ number_format((float) $item->qty, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-slate-400 py-6">No items recorded.</td></tr>
                        @endforelse
                    </tbody>
                </x-ui.table>
            </div>

        </div>

        {{-- Right: Gate Pass --}}
        <div class="space-y-4">
            @if ($this->record->gatePass)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Gate Pass</h3>
                    <span class="badge {{ $this->record->gatePass->status->value === 'exited' ? 'badge-emerald' : 'badge-amber' }}">
                        <span class="dot"></span>{{ $this->record->gatePass->status->label() }}
                    </span>
                </div>
                <div class="card-body space-y-3 text-sm">
                    <div>
                        <p class="section-label">Gate Pass No</p>
                        <p class="font-mono font-bold text-base">{{ $this->record->gatePass->number }}</p>
                    </div>
                    @if ($this->record->gatePass->exited_at)
                    <div>
                        <p class="section-label">Exited At</p>
                        <p class="tabular-nums">{{ $this->record->gatePass->exited_at->format('d M Y H:i') }}</p>
                    </div>
                    <div>
                        <p class="section-label">Verified By</p>
                        <p>{{ $this->record->gatePass->verifiedBy?->name ?? '—' }}</p>
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>

    </div>

</div>
