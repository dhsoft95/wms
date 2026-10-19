<div class="space-y-6">
    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Goods Receipt Notes</h2>
            <p class="page-sub">Receiving records for every container and supplier delivery.</p>
        </div>
    </div>

    <div class="card">
        {{-- Status Tabs --}}
        <div class="card-header flex-col sm:flex-row gap-3">
            <div class="tabs overflow-x-auto">
                <button wire:click="$set('statusFilter','all')" @class(['tab','active' => $statusFilter === 'all'])>
                    All <span class="badge badge-neutral ml-1">{{ $this->statusCounts['all'] ?? 0 }}</span>
                </button>
                @foreach ($this->statuses as $status)
                    <button wire:click="$set('statusFilter','{{ $status->value }}')"
                            @class(['tab','active' => $statusFilter === $status->value])>
                        {{ $status->label() }}
                        @if (($this->statusCounts[$status->value] ?? 0) > 0)
                            <span class="badge {{ $status->color() }} ml-1">{{ $this->statusCounts[$status->value] }}</span>
                        @endif
                    </button>
                @endforeach
            </div>

            <div class="flex items-center gap-2 ml-auto">
                {{-- Source filter --}}
                <select wire:model.live="sourceFilter" class="input input-sm">
                    <option value="">All Sources</option>
                    @foreach ($this->sources as $src)
                        <option value="{{ $src->value }}">{{ $src->label() }}</option>
                    @endforeach
                </select>

                {{-- Search --}}
                <div class="relative">
                    <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                    <input wire:model.live.debounce.300ms="search" type="text"
                           placeholder="Search GRN, container, supplier…"
                           class="input !pl-9 sm:w-64" />
                </div>
            </div>
        </div>

        <x-ui.table>
            <thead>
                <tr>
                    <th>GRN Number</th>
                    <th>PO Number</th>
                    <th>Source</th>
                    <th>Container / Supplier</th>
                    <th>Warehouse</th>
                    <th>Progress</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->grns as $grn)
                    <tr>
                        <td class="mono font-semibold">{{ $grn->number }}</td>
                        <td class="mono text-sm">
                            @if ($grn->purchaseOrder)
                                <a href="{{ route('inbound.orders.show', $grn->purchaseOrder) }}" wire:navigate
                                   class="text-simba-600 dark:text-simba-400 hover:underline">
                                    {{ $grn->purchaseOrder->number }}
                                </a>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $grn->source->color() }}">
                                <span class="dot"></span>{{ $grn->source->label() }}
                            </span>
                        </td>
                        <td>
                            @if ($grn->container)
                                <span class="mono text-sm">{{ $grn->container->number }}</span>
                            @elseif ($grn->supplierPartner)
                                {{ $grn->supplierPartner->name }}
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td>{{ $grn->warehouse?->name }}</td>
                        <td>
                            @php
                                $pct = $grn->progressPercent();
                                $barColor = $pct >= 100 ? 'bg-emerald-600' : 'bg-simba-500';
                            @endphp
                            <div class="flex items-center gap-2">
                                <div class="progress w-24">
                                    <div class="{{ $barColor }}" style="width:{{ $pct }}%"></div>
                                </div>
                                <span class="text-xs tabular-nums text-slate-500">{{ $pct }}%</span>
                            </div>
                        </td>
                        <td>
                            <span class="badge {{ $grn->status->color() }}">
                                <span class="dot"></span>{{ $grn->status->label() }}
                            </span>
                        </td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('inbound.grn.show', $grn) }}" wire:navigate
                                   class="btn btn-secondary btn-sm">View</a>
                                @if ($grn->purchase_order_id && $grn->status->value === 'draft')
                                    <a href="{{ route('inbound.grn.tally', $grn) }}" wire:navigate
                                       class="btn btn-primary btn-sm">Continue Tally</a>
                                @elseif (in_array($grn->status->value, ['draft', 'receiving']) && ! $grn->purchase_order_id)
                                    <a href="{{ route('inbound.grn.receive', $grn) }}" wire:navigate
                                       class="btn btn-primary btn-sm">Receive</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="!py-12 text-center text-slate-500">
                            No GRNs found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        <div class="px-4 py-3 border-t border-white/40 dark:border-white/[0.06]">
            {{ $this->grns->links() }}
        </div>
    </div>
</div>
