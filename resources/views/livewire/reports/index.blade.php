<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">Reports Library</h2>
            <p class="page-sub">Generate and export operational reports for any date range.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Date Range</h3>
        </div>
        <div class="card-body">
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="label">From</label>
                    <input wire:model="dateFrom" type="date" class="input tabular-nums w-44">
                </div>
                <div>
                    <label class="label">To</label>
                    <input wire:model="dateTo" type="date" class="input tabular-nums w-44">
                </div>
                <div class="flex gap-2 pb-0.5">
                    <button type="button" wire:click="$set('dateFrom', '{{ now()->startOfMonth()->format('Y-m-d') }}')" class="btn btn-secondary btn-sm">This month</button>
                    <button type="button" wire:click="$set('dateFrom', '{{ now()->subDays(7)->format('Y-m-d') }}')" class="btn btn-secondary btn-sm">Last 7 days</button>
                    <button type="button" wire:click="$set('dateFrom', '{{ now()->subDays(30)->format('Y-m-d') }}')" class="btn btn-secondary btn-sm">Last 30 days</button>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach ($reports as $report)
            <div class="card">
                <div class="card-body">
                    <div class="flex items-start justify-between gap-3">
                        <div class="kpi-icon bg-simba-50 dark:bg-simba-950/40 text-simba-600">
                            <x-ui.icon name="{{ $report['icon'] }}" class="w-5 h-5" />
                        </div>
                        <span class="badge badge-slate text-xs">{{ $report['tag'] }}</span>
                    </div>
                    <div class="mt-4 space-y-1">
                        <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $report['name'] }}</p>
                        <p class="text-sm text-slate-500">{{ $report['desc'] }}</p>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="button" wire:click="runReport('{{ $report['name'] }}')" class="btn btn-primary btn-sm flex-1">
                        <x-ui.icon name="chart-bar" class="w-3.5 h-3.5" /> Run Report
                    </button>
                    <button type="button" wire:click="exportCsv('{{ $report['name'] }}')" class="btn btn-secondary btn-sm">
                        <x-ui.icon name="download" class="w-3.5 h-3.5" /> CSV
                    </button>
                    <a href="{{ route('reports.print') }}" wire:navigate class="btn btn-secondary btn-sm">
                        <x-ui.icon name="printer" class="w-3.5 h-3.5" />
                    </a>
                </div>
            </div>
        @endforeach
    </div>
</div>
