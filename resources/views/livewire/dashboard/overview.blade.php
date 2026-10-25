<div class="space-y-6">

    {{-- ── Page header + filters ─────────────────────────────────────────── --}}
    <div class="page-header flex-wrap gap-y-3">
        <div>
            <h2 class="page-title">Operations Overview</h2>
            <p class="page-sub">Live snapshot of inbound, storage and outbound activity</p>
        </div>

        <div class="flex flex-wrap items-center gap-3 ml-auto">
            {{-- Warehouse filter --}}
            @if ($this->warehouses->isNotEmpty())
            <select wire:model.live="warehouseId" class="input input-sm w-48">
                <option value="">All warehouses</option>
                @foreach ($this->warehouses as $wh)
                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                @endforeach
            </select>
            @endif

            {{-- Day range toggle --}}
            <div class="flex rounded-lg border border-slate-200 dark:border-white/10 overflow-hidden text-xs font-medium">
                @foreach ([7 => '7d', 30 => '30d', 90 => '90d'] as $val => $label)
                <button type="button"
                        wire:click="$set('days', {{ $val }})"
                        class="px-3 py-1.5 transition-colors
                               {{ $days === $val
                                    ? 'bg-simba-600 text-white'
                                    : 'bg-white dark:bg-white/5 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/10' }}">
                    {{ $label }}
                </button>
                @endforeach
            </div>

            <a href="{{ route('outbound.orders.index') }}" wire:navigate class="btn btn-secondary btn-sm">
                <x-ui.icon name="cart" class="w-4 h-4" /> New Order
            </a>
            <a href="{{ route('inbound.grn.create') }}" wire:navigate class="btn btn-primary btn-sm">
                <x-ui.icon name="plus" class="w-4 h-4" /> New Receiving
            </a>
        </div>
    </div>

    {{-- ── KPI cards grid ──────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-4">

        @if ($this->canSeeKpi('stock_on_hand'))
        <x-ui.kpi-card
            label="Stock on Hand"
            :value="number_format($this->stockOnHand['qty'], 0)"
            :hint="number_format($this->stockOnHand['sku_count']) . ' SKUs'"
            icon="archive"
            tone="brand"
            :href="route('inventory.stock.index')"
            :spark="$this->sparkStockReceived"
        />
        @endif

        @if ($this->canSeeKpi('open_orders'))
        @php $openOrdersHint = $this->openOrders['po_count'] . ' PO · ' . $this->openOrders['grn_count'] . ' GRN · ' . $this->openOrders['transfer_count'] . ' transfer'; @endphp
        <x-ui.kpi-card
            label="Open Orders"
            :value="number_format($this->openOrders['total'])"
            :hint="$openOrdersHint"
            icon="clipboard-list"
            tone="brand"
        />
        @endif

        @if ($this->canSeeKpi('pending_approvals'))
        @php $approvalTone = $this->pendingApprovals['total'] > 0 ? 'warning' : 'neutral'; @endphp
        <x-ui.kpi-card
            label="Pending Approvals"
            :value="$this->pendingApprovals['total']"
            hint="For you to action"
            icon="bell"
            :tone="$approvalTone"
            :href="route('approvals.index')"
        />
        @endif

        @if ($this->canSeeKpi('to_receive'))
        @php $toReceiveHint = $this->toReceive['today'] . ' started today'; @endphp
        <x-ui.kpi-card
            label="To Receive"
            :value="$this->toReceive['total']"
            :hint="$toReceiveHint"
            icon="inbox"
            tone="brand"
            :href="route('inbound.grn.index')"
        />
        @endif

        @if ($this->canSeeKpi('awaiting_putaway'))
        <x-ui.kpi-card
            label="Awaiting Put-Away"
            :value="$this->awaitingPutaway['total']"
            hint="Open tasks"
            icon="arrows"
            tone="brand"
            :href="route('inbound.putaway.index')"
        />
        @endif

        @if ($this->canSeeKpi('to_pick'))
        <x-ui.kpi-card
            label="To Pick / Dispatch"
            :value="$this->toPick['total']"
            hint="Orders ready to pick"
            icon="truck"
            tone="success"
            :href="route('outbound.orders.index')"
            :spark="$this->sparkOrdersDispatched"
        />
        @endif

        @if ($this->canSeeKpi('in_transit'))
        @php $inTransitHint = $this->inTransit['containers'] . ' containers · ' . $this->inTransit['transfers'] . ' transfers'; @endphp
        <x-ui.kpi-card
            label="In Transit"
            :value="number_format($this->inTransit['total'])"
            :hint="$inTransitHint"
            icon="cube"
            tone="brand"
        />
        @endif

        @if ($this->canSeeKpi('low_stock'))
        @php $lowStockTone = $this->lowStock['count'] > 0 ? 'danger' : 'neutral'; @endphp
        <x-ui.kpi-card
            label="Low Stock"
            :value="$this->lowStock['count']"
            hint="SKUs below min stock"
            icon="alert"
            :tone="$lowStockTone"
            :href="route('inventory.stock.index')"
        />
        @endif

        @if ($this->canSeeKpi('expiring'))
        @php $expiringTone = $this->expiring['count'] > 0 ? 'warning' : 'neutral'; @endphp
        <x-ui.kpi-card
            label="Expiring Soon"
            :value="$this->expiring['count']"
            hint="SKUs expiring in ≤ 30 days"
            icon="clock"
            :tone="$expiringTone"
            :href="route('inventory.stock.index')"
        />
        @endif

        @if ($this->canSeeKpi('containers_in_yard'))
        @php $yardHint = $this->containersInYard['over_dwell'] > 0 ? $this->containersInYard['over_dwell'] . ' over dwell limit' : 'All within dwell limit'; @endphp
        <x-ui.kpi-card
            label="Containers in Yard"
            :value="$this->containersInYard['total']"
            :hint="$yardHint"
            icon="cube"
            tone="brand"
            :href="route('inbound.containers.index')"
        />
        @endif

    </div>

    {{-- ── Charts ───────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Inbound vs Outbound --}}
        @if ($this->canSeeChart('inbound_vs_outbound'))
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Inbound vs Outbound</h3>
                <span class="text-xs text-slate-400 ml-auto">Last {{ $days }} days</span>
            </div>
            <div class="card-body">
                <x-ui.chart
                    type="line"
                    :series="$this->chartInboundVsOutbound['series'] ?? []"
                    :categories="$this->chartInboundVsOutbound['categories'] ?? []"
                    :height="240"
                    unit="units"
                    chart-id="chart-inbound-outbound"
                />
            </div>
        </div>
        @endif

        {{-- Order Status Pipeline --}}
        @if ($this->canSeeChart('order_pipeline'))
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Order Pipeline</h3>
                <span class="text-xs text-slate-400 ml-auto">By status</span>
            </div>
            <div class="card-body">
                <x-ui.chart
                    type="bar"
                    :series="$this->chartOrderPipeline['series'] ?? []"
                    :categories="$this->chartOrderPipeline['categories'] ?? []"
                    :height="240"
                    chart-id="chart-order-pipeline"
                />
            </div>
        </div>
        @endif

        {{-- Top Moving Items --}}
        @if ($this->canSeeChart('top_moving_items'))
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Top Moving Items</h3>
                <span class="text-xs text-slate-400 ml-auto">Last {{ $days }} days · dispatched qty</span>
            </div>
            <div class="card-body">
                <x-ui.chart
                    type="bar"
                    :horizontal="true"
                    :series="$this->chartTopMovingItems['series'] ?? []"
                    :categories="$this->chartTopMovingItems['categories'] ?? []"
                    :height="240"
                    unit="units"
                    chart-id="chart-top-items"
                />
            </div>
        </div>
        @endif

        {{-- Bin Utilisation --}}
        @if ($this->canSeeChart('bin_utilisation'))
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Bin Utilisation</h3>
                @php $binPct = $this->chartBinUtilisation['series'][0] ?? 0; @endphp
                <span class="text-xs text-slate-400 ml-auto">{{ number_format($binPct, 1) }}% occupied</span>
            </div>
            <div class="card-body">
                <x-ui.chart
                    type="radialBar"
                    :series="$this->chartBinUtilisation['series'] ?? []"
                    :categories="$this->chartBinUtilisation['categories'] ?? []"
                    :height="240"
                    chart-id="chart-bin-util"
                />
            </div>
        </div>
        @endif

    </div>

    {{-- ── Action lists ────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        @if ($this->canSeeAction('my_approvals'))
        <div class="card flex flex-col">
            <div class="card-header">
                <h3 class="card-title">Pending Approvals</h3>
                @if ($this->myApprovals->isNotEmpty())
                    <span class="badge badge-amber ml-auto">{{ $this->myApprovals->count() }}</span>
                @endif
            </div>
            @if ($this->myApprovals->isEmpty())
                <div class="card-body">
                    <p class="text-sm text-slate-400 text-center py-4">No approvals awaiting your action.</p>
                </div>
            @else
            <x-ui.table>
                <thead>
                    <tr><th>Ref</th><th>Type</th><th>Details</th><th>Age</th>@if (! $this->isReadonly())<th></th>@endif</tr>
                </thead>
                <tbody>
                    @foreach ($this->myApprovals as $a)
                    <tr>
                        <td class="mono font-semibold">{{ $a['ref'] }}</td>
                        <td><span class="badge {{ $a['badge'] }}">{{ $a['type'] }}</span></td>
                        <td class="text-sm text-slate-500">{{ $a['desc'] }}</td>
                        <td class="text-sm text-slate-400 tabular-nums">{{ $a['age'] }}</td>
                        @if (! $this->isReadonly())
                        <td>
                            <a href="{{ route('approvals.index') }}" wire:navigate
                               class="btn btn-secondary btn-xs">Review</a>
                        </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            @endif
            <div class="card-footer mt-auto">
                <a href="{{ route('approvals.index') }}" wire:navigate class="btn btn-secondary btn-sm w-full">
                    Open Approvals Inbox
                </a>
            </div>
        </div>
        @endif

        @if ($this->canSeeAction('overdue_orders'))
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Overdue Orders</h3>
                @if ($this->overdueOrders->isNotEmpty())
                    <span class="badge badge-red ml-auto">{{ $this->overdueOrders->count() }}</span>
                @endif
            </div>
            @if ($this->overdueOrders->isEmpty())
                <div class="card-body">
                    <p class="text-sm text-slate-400 text-center py-4">All orders are on time.</p>
                </div>
            @else
            <x-ui.table>
                <thead>
                    <tr><th>Order</th><th>Consignee</th><th>Required</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @foreach ($this->overdueOrders as $do)
                    <tr>
                        <td>
                            <a href="{{ route('outbound.orders.show', $do) }}" wire:navigate
                               class="mono font-semibold text-simba-700 dark:text-simba-400 hover:text-simba-600">
                                {{ $do->number }}
                            </a>
                        </td>
                        <td class="text-sm">{{ $do->consigneePartner?->name ?? '—' }}</td>
                        <td class="text-sm tabular-nums text-red-600 font-medium">
                            {{ $do->required_date?->format('d M Y') ?? '—' }}
                        </td>
                        <td><span class="badge badge-red">{{ ucwords(str_replace('_', ' ', $do->status->value)) }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            @endif
        </div>
        @endif

        @if ($this->canSeeAction('recent_variances'))
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Recent Variances</h3>
            </div>
            @if ($this->recentVariances->isEmpty())
                <div class="card-body">
                    <p class="text-sm text-slate-400 text-center py-4">No recent variances.</p>
                </div>
            @else
            <x-ui.table>
                <thead>
                    <tr><th>Source</th><th>Ref</th><th>SKU</th><th>Item</th><th class="text-right">Variance</th></tr>
                </thead>
                <tbody>
                    @foreach ($this->recentVariances as $v)
                    <tr>
                        <td><span class="badge {{ $v['badge'] }} text-xs">{{ $v['source'] }}</span></td>
                        <td class="mono text-xs">{{ $v['ref'] }}</td>
                        <td class="mono text-xs">{{ $v['sku'] }}</td>
                        <td class="text-sm">{{ $v['item'] }}</td>
                        <td class="text-right mono text-sm font-semibold {{ $v['var'] < 0 ? 'text-red-600' : 'text-amber-600' }}">
                            {{ $v['var'] >= 0 ? '+' : '' }}{{ number_format($v['var'], 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            @endif
        </div>
        @endif

        @if ($this->canSeeAction('recent_activity'))
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Recent Activity</h3>
                <a href="{{ route('inventory.movements.index') }}" wire:navigate
                   class="text-xs font-semibold text-simba-600 hover:underline ml-auto">View all</a>
            </div>
            @if ($this->recentActivity->isEmpty())
                <div class="card-body">
                    <p class="text-sm text-slate-400 text-center py-4">No recent stock movements.</p>
                </div>
            @else
            <x-ui.table>
                <thead>
                    <tr><th>Ref</th><th>Type</th><th>Posted By</th><th>When</th></tr>
                </thead>
                <tbody>
                    @foreach ($this->recentActivity as $txn)
                    <tr>
                        <td class="mono text-xs font-semibold">{{ $txn->reference_no ?? '—' }}</td>
                        <td class="text-sm">{{ $txn->type?->label() ?? ucfirst(str_replace('_', ' ', $txn->type ?? '')) }}</td>
                        <td class="text-sm">{{ $txn->postedBy?->name ?? '—' }}</td>
                        <td class="text-xs text-slate-400 tabular-nums">{{ $txn->posted_at?->diffForHumans() }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            @endif
        </div>
        @endif

    </div>

</div>
