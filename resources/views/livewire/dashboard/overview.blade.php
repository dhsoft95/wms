@php
// Static placeholder KPIs — containers replaced with real data below
$kpis = [
    ['label' => 'Stock on Hand',     'value' => '48,250', 'trend' => '+4.2% vs last week',  'trendColor' => 'text-emerald-600', 'icon' => 'archive',   'iconBg' => 'bg-simba-50 text-simba-600 dark:bg-simba-500/10 dark:text-simba-400'],
    ['label' => 'Pending GRNs',      'value' => '6',      'trend' => '2 overdue more than 24h', 'trendColor' => 'text-amber-600', 'icon' => 'clipboard', 'iconBg' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400'],
    ["Today's Dispatches" => true,   'label' => "Today's Dispatches", 'value' => '28',       'trend' => '96% on-time rate',     'trendColor' => 'text-emerald-600', 'icon' => 'truck',     'iconBg' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400'],
];

$utilization = [
    ['label' => 'DAR-General Store', 'pct' => 78, 'warn' => false],
    ['label' => 'DAR-Tech Vault',    'pct' => 54, 'warn' => false],
    ['label' => 'Cold Room',         'pct' => 91, 'warn' => true],
    ['label' => 'Yard Slots',        'pct' => 60, 'warn' => false, 'neutral' => true, 'label2' => '12 / 20'],
];

$activity = [
    ['icon' => 'truck',     'iconBg' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10', 'text' => '<strong>GP-2026-088</strong> released for Azam Ltd, vehicle T 452 ABC',                  'meta' => 'Juma Kassim · 12 minutes ago'],
    ['icon' => 'clipboard', 'iconBg' => 'bg-simba-50 text-simba-600 dark:bg-simba-500/10',        'text' => '<strong>GRN-2026-089</strong> receiving started for container MSKU1234567',               'meta' => 'Onesmo Mlelwa · 45 minutes ago'],
    ['icon' => 'alert',     'iconBg' => 'bg-red-50 text-red-600 dark:bg-red-500/10',             'text' => 'Shortage of <strong>12 bags</strong> logged against GRN-2026-089',                       'meta' => 'Onesmo Mlelwa · 1 hour ago'],
    ['icon' => 'arrows',    'iconBg' => 'bg-simba-50 text-simba-600 dark:bg-simba-500/10',       'text' => '150 units of <strong>SKU-AZ-001</strong> moved to DAR-A-03-02-B',                        'meta' => 'Neema Joseph · 3 hours ago'],
];

$approvals = [
    ['ref' => 'ADJ-2026-014', 'desc' => 'Cycle count variance, -50 units', 'badge' => 'badge-amber', 'label' => 'Adjustment'],
    ['ref' => 'TRF-2026-031', 'desc' => 'Tech Vault to Zanzibar, 200 units', 'badge' => 'badge-neutral', 'label' => 'Transfer'],
    ['ref' => 'INS-2026-022', 'desc' => 'GRN shortage, 12 bags',            'badge' => 'badge-red',    'label' => 'Discrepancy'],
];
@endphp

<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">Operations Overview</h2>
            <p class="page-sub">Live snapshot of inbound, storage and outbound activity</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('outbound.orders.index') }}" wire:navigate class="btn btn-secondary">
                <x-ui.icon name="cart" class="w-4 h-4" /> New Order
            </a>
            <a href="{{ route('inbound.grn.create') }}" wire:navigate class="btn btn-primary">
                <x-ui.icon name="plus" class="w-4 h-4" /> New Receiving
            </a>
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6">
        {{-- Static KPI: Stock on Hand --}}
        <div class="card p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-500">Stock on Hand</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums">48,250</p>
                </div>
                <div class="kpi-icon bg-simba-50 text-simba-600 dark:bg-simba-500/10 dark:text-simba-400">
                    <x-ui.icon name="archive" class="w-5 h-5" />
                </div>
            </div>
            <p class="mt-3 text-xs text-slate-500">
                <span class="font-semibold text-emerald-600">+4.2%</span> vs last week
            </p>
        </div>
        {{-- Static KPI: Pending GRNs --}}
        <div class="card p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-500">Pending GRNs</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums">6</p>
                </div>
                <div class="kpi-icon bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                    <x-ui.icon name="clipboard" class="w-5 h-5" />
                </div>
            </div>
            <p class="mt-3 text-xs text-slate-500">
                <span class="font-semibold text-amber-600">2</span> overdue more than 24h
            </p>
        </div>
        {{-- Real KPI: Active Containers --}}
        <div class="card p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-500">Active Containers</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums">{{ $this->activeContainersCount }}</p>
                </div>
                <div class="kpi-icon bg-simba-50 text-simba-600 dark:bg-simba-500/10 dark:text-simba-400">
                    <x-ui.icon name="cube" class="w-5 h-5" />
                </div>
            </div>
            <p class="mt-3 text-xs text-slate-500">
                <span class="font-semibold text-simba-600">{{ $this->arrivingThisWeek }}</span> arriving this week
            </p>
        </div>
        {{-- Static KPI: Today's Dispatches --}}
        <div class="card p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-500">Today's Dispatches</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums">28</p>
                </div>
                <div class="kpi-icon bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                    <x-ui.icon name="truck" class="w-5 h-5" />
                </div>
            </div>
            <p class="mt-3 text-xs text-slate-500">
                <span class="font-semibold text-emerald-600">96%</span> on-time rate
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Inbound pipeline --}}
        <div class="card lg:col-span-2">
            <div class="card-header">
                <h3 class="card-title">Inbound Pipeline</h3>
                <a href="{{ route('inbound.containers.index') }}" wire:navigate class="text-xs font-semibold text-simba-600 hover:underline">View all containers</a>
            </div>
            <x-ui.table>
                    <thead>
                        <tr>
                            <th>Container</th><th>Vessel</th><th>ETA</th><th>Yard Slot</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->pipelineContainers as $row)
                            <tr>
                                <td>
                                    <a href="{{ route('inbound.containers.show', $row) }}" wire:navigate class="mono font-semibold text-simba-700 dark:text-simba-400 hover:text-simba-600 transition-colors">{{ $row->container_no }}</a>
                                </td>
                                <td class="text-sm">{{ $row->vessel ?? '—' }}</td>
                                <td class="tabular-nums text-sm">{{ $row->eta?->format('d M Y') ?? '—' }}</td>
                                <td>
                                    @if($row->yardLocation)
                                        <span class="font-mono text-xs px-2 py-0.5 rounded-md bg-zinc-900/5 dark:bg-white/10 text-slate-700 dark:text-slate-200">{{ $row->yardLocation->code }}</span>
                                    @else
                                        <span class="text-slate-400 italic text-sm">Not assigned</span>
                                    @endif
                                </td>
                                <td><span class="badge {{ $row->status->color() }}"><span class="dot"></span>{{ $row->status->label() }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-sm text-slate-400">No active containers in the pipeline.</td>
                            </tr>
                        @endforelse
                    </tbody>
            </x-ui.table>
        </div>

        {{-- Warehouse utilization --}}
        <div class="card">
            <div class="card-header"><h3 class="card-title">Warehouse Utilization</h3></div>
            <div class="card-body space-y-5">
                @foreach ($utilization as $u)
                    <div>
                        <div class="flex justify-between text-sm mb-2">
                            <span class="font-medium">{{ $u['label'] }}</span>
                            <span class="tabular-nums font-semibold {{ $u['warn'] ? 'text-amber-600' : '' }}">
                                {{ $u['label2'] ?? $u['pct'] . '%' }}
                            </span>
                        </div>
                        <x-ui.progress :value="$u['pct']" :neutral="$u['neutral'] ?? false" />
                        @if ($u['warn'])
                            <p class="hint">Approaching capacity, plan re-slotting.</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Outbound metrics --}}
    <div>
        <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">Outbound</h3>
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
            {{-- Pending Orders --}}
            <a href="{{ route('outbound.orders.index') }}" wire:navigate class="card p-5 hover:border-simba-300 dark:hover:border-simba-600 transition-colors group">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500">Pending Orders</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums group-hover:text-simba-600 transition-colors">{{ $this->pendingOrdersCount }}</p>
                    </div>
                    <div class="kpi-icon bg-simba-50 text-simba-600 dark:bg-simba-500/10 dark:text-simba-400">
                        <x-ui.icon name="clipboard-list" class="w-5 h-5" />
                    </div>
                </div>
                <p class="mt-3 text-xs text-slate-500">Draft, submitted &amp; approved</p>
            </a>

            {{-- Today's Dispatches --}}
            <div class="card p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500">Today's Dispatches</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums">{{ $this->todayDispatchCount }}</p>
                    </div>
                    <div class="kpi-icon bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                        <x-ui.icon name="truck" class="w-5 h-5" />
                    </div>
                </div>
                <p class="mt-3 text-xs text-slate-500">Dispatched today</p>
            </div>

            {{-- Open Pick Lists --}}
            <a href="{{ route('outbound.pick-lists.index') }}" wire:navigate class="card p-5 hover:border-simba-300 dark:hover:border-simba-600 transition-colors group">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500">Open Pick Lists</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums group-hover:text-simba-600 transition-colors">{{ $this->openPickListsCount }}</p>
                    </div>
                    <div class="kpi-icon bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                        <x-ui.icon name="clipboard" class="w-5 h-5" />
                    </div>
                </div>
                <p class="mt-3 text-xs text-slate-500">Open &amp; in-progress</p>
            </a>

            {{-- Overdue Orders --}}
            <a href="{{ route('outbound.orders.index') }}" wire:navigate class="card p-5 hover:border-red-300 dark:hover:border-red-700 transition-colors group">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500">Overdue Orders</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums {{ $this->overdueOrdersCount > 0 ? 'text-red-600' : '' }}">{{ $this->overdueOrdersCount }}</p>
                    </div>
                    <div class="kpi-icon {{ $this->overdueOrdersCount > 0 ? 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400' : 'bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-500' }}">
                        <x-ui.icon name="alert" class="w-5 h-5" />
                    </div>
                </div>
                <p class="mt-3 text-xs {{ $this->overdueOrdersCount > 0 ? 'text-red-500 font-semibold' : 'text-slate-500' }}">
                    {{ $this->overdueOrdersCount > 0 ? 'Past required date' : 'All on time' }}
                </p>
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Recent activity --}}
        <div class="card lg:col-span-2">
            <div class="card-header"><h3 class="card-title">Recent Activity</h3></div>
            <ul class="divide-y" style="--tw-divide-color: rgb(255 255 255 / 0.4)">
                @foreach ($activity as $item)
                    <li class="flex gap-4 px-5 py-4">
                        <div class="kpi-icon !w-8 !h-8 {{ $item['iconBg'] }}">
                            <x-ui.icon :name="$item['icon']" class="w-4 h-4" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm">{!! $item['text'] !!}</p>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $item['meta'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Awaiting approval --}}
        <div class="card flex flex-col">
            <div class="card-header">
                <h3 class="card-title">Awaiting Your Approval</h3>
                <span class="badge badge-simba">{{ count($approvals) }}</span>
            </div>
            <ul class="flex-1">
                @foreach ($approvals as $item)
                    <li class="list-row">
                        <div>
                            <p class="text-sm font-semibold mono">{{ $item['ref'] }}</p>
                            <p class="text-xs text-slate-500">{{ $item['desc'] }}</p>
                        </div>
                        <span class="badge {{ $item['badge'] }}">{{ $item['label'] }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="p-4" style="border-top: 1px solid rgb(255 255 255 / 0.4);">
                <a href="{{ route('approvals.index') }}" wire:navigate class="btn btn-secondary w-full">Open Approvals Inbox</a>
            </div>
        </div>
    </div>
</div>
