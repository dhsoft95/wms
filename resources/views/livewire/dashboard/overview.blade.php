@php
// placeholder data
$kpis = [
    ['label' => 'Stock on Hand',     'value' => '48,250', 'trend' => '+4.2% vs last week',  'trendColor' => 'text-emerald-600', 'icon' => 'archive', 'iconBg' => 'bg-simba-50 text-simba-600 dark:bg-simba-500/10 dark:text-simba-400'],
    ['label' => 'Pending GRNs',      'value' => '6',      'trend' => '2 overdue more than 24h', 'trendColor' => 'text-amber-600', 'icon' => 'clipboard', 'iconBg' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400'],
    ['label' => 'Active Containers', 'value' => '18',     'trend' => '5 arriving this week', 'trendColor' => 'text-blue-600',    'icon' => 'cube',      'iconBg' => 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400'],
    ['label' => "Today's Dispatches", 'value' => '28',    'trend' => '96% on-time rate',     'trendColor' => 'text-emerald-600', 'icon' => 'truck',     'iconBg' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400'],
];

$pipeline = [
    ['container' => 'MSKU1234567', 'vessel' => 'COSCO Pride V.42',    'eta' => '2026-10-02', 'slot' => 'SLOT-A03', 'status' => 'Unloading', 'badge' => 'badge-blue'],
    ['container' => 'MRKU8829102', 'vessel' => 'MSC Diana V.12',      'eta' => '2026-10-03', 'slot' => 'SLOT-B12', 'status' => 'Arrived',   'badge' => 'badge-amber'],
    ['container' => 'CMAU5543219', 'vessel' => 'CMA CGM Africa V.08', 'eta' => '2026-10-05', 'slot' => null,       'status' => 'Expected',  'badge' => 'badge-slate'],
    ['container' => 'MSCU4410983', 'vessel' => 'MSC Diana V.12',      'eta' => '2026-10-06', 'slot' => null,       'status' => 'Expected',  'badge' => 'badge-slate'],
];

$utilization = [
    ['label' => 'DAR-General Store', 'pct' => 78, 'warn' => false],
    ['label' => 'DAR-Tech Vault',    'pct' => 54, 'warn' => false],
    ['label' => 'Cold Room',         'pct' => 91, 'warn' => true],
    ['label' => 'Yard Slots',        'pct' => 60, 'warn' => false, 'neutral' => true, 'label2' => '12 / 20'],
];

$activity = [
    ['icon' => 'truck',     'iconBg' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10', 'text' => '<strong>GP-2026-088</strong> released for Azam Ltd, vehicle T 452 ABC',                  'meta' => 'Juma Kassim · 12 minutes ago'],
    ['icon' => 'clipboard', 'iconBg' => 'bg-blue-50 text-blue-600 dark:bg-blue-500/10',          'text' => '<strong>GRN-2026-089</strong> receiving started for container MSKU1234567',               'meta' => 'Onesmo Mlelwa · 45 minutes ago'],
    ['icon' => 'alert',     'iconBg' => 'bg-red-50 text-red-600 dark:bg-red-500/10',             'text' => 'Shortage of <strong>12 bags</strong> logged against GRN-2026-089',                       'meta' => 'Onesmo Mlelwa · 1 hour ago'],
    ['icon' => 'arrows',    'iconBg' => 'bg-simba-50 text-simba-600 dark:bg-simba-500/10',       'text' => '150 units of <strong>SKU-AZ-001</strong> moved to DAR-A-03-02-B',                        'meta' => 'Neema Joseph · 3 hours ago'],
];

$approvals = [
    ['ref' => 'ADJ-2026-014', 'desc' => 'Cycle count variance, -50 units', 'badge' => 'badge-amber', 'label' => 'Adjustment'],
    ['ref' => 'TRF-2026-031', 'desc' => 'Tech Vault to Zanzibar, 200 units', 'badge' => 'badge-violet', 'label' => 'Transfer'],
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
        @foreach ($kpis as $kpi)
            <div class="card p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500">{{ $kpi['label'] }}</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums">{{ $kpi['value'] }}</p>
                    </div>
                    <div class="kpi-icon">
                        <x-ui.icon :name="$kpi['icon']" class="w-5 h-5" />
                    </div>
                </div>
                <p class="mt-3 text-xs text-slate-500">
                    <span class="font-semibold {{ $kpi['trendColor'] }}">{{ explode(' ', $kpi['trend'])[0] }}</span>
                    {{ implode(' ', array_slice(explode(' ', $kpi['trend']), 1)) }}
                </p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Inbound pipeline --}}
        <div class="card lg:col-span-2">
            <div class="card-header">
                <h3 class="card-title">Inbound Pipeline</h3>
                <a href="{{ route('inbound.containers.index') }}" wire:navigate class="text-xs font-semibold text-simba-600 hover:underline">View all containers</a>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Container</th><th>Vessel</th><th>ETA</th><th>Yard Slot</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pipeline as $row)
                            <tr>
                                <td class="mono font-semibold">{{ $row['container'] }}</td>
                                <td>{{ $row['vessel'] }}</td>
                                <td class="tabular-nums">{{ $row['eta'] }}</td>
                                <td>
                                    @if ($row['slot'])
                                        <span class="font-mono text-xs px-2 py-0.5 rounded-md bg-zinc-900/5 dark:bg-white/10 text-slate-700 dark:text-slate-200">{{ $row['slot'] }}</span>
                                    @else
                                        <span class="text-slate-400 italic text-sm">Not assigned</span>
                                    @endif
                                </td>
                                <td><span class="badge {{ $row['badge'] }}"><span class="dot"></span>{{ $row['status'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
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
