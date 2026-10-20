<div class="space-y-6" wire:poll.3000ms="refresh">
    <div class="page-header">
        <div>
            <h2 class="page-title">Reports Library</h2>
            <p class="page-sub">Generate and download PDF reports for any date range.</p>
        </div>
    </div>

    {{-- Date range filter --}}
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

    {{-- Report cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach ($catalogue as $report)
            @php
                $req = $this->requests->get($report['type']);
            @endphp
            <div class="card">
                <div class="card-body">
                    <div class="flex items-start justify-between gap-3">
                        <div class="kpi-icon glass-brand text-white">
                            <x-ui.icon name="{{ $report['icon'] }}" class="w-5 h-5" />
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="badge badge-slate text-xs">{{ $report['tag'] }}</span>
                            @if ($req)
                                @if ($req->isPending() || $req->isProcessing())
                                    <span class="badge badge-yellow text-xs">
                                        <svg class="w-3 h-3 animate-spin mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                                        {{ ucfirst($req->status) }}
                                    </span>
                                @elseif ($req->isReady())
                                    <span class="badge badge-green text-xs">Ready</span>
                                @elseif ($req->isFailed())
                                    <span class="badge badge-red text-xs">Failed</span>
                                @endif
                            @endif
                        </div>
                    </div>
                    <div class="mt-4 space-y-1">
                        <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $report['name'] }}</p>
                        <p class="text-sm text-slate-500">{{ $report['desc'] }}</p>
                        @if ($req?->isFailed() && $req->error)
                            <p class="text-xs text-red-500 mt-1">{{ Str::limit($req->error, 80) }}</p>
                        @endif
                        @if ($req?->isReady())
                            <p class="text-xs text-slate-400 mt-1">
                                Generated {{ $req->updated_at->diffForHumans() }}
                                @if ($req->date_from && $req->date_to)
                                · {{ \Carbon\Carbon::parse($req->date_from)->format('d M') }}–{{ \Carbon\Carbon::parse($req->date_to)->format('d M Y') }}
                                @endif
                            </p>
                        @endif
                    </div>
                </div>
                <div class="card-footer">
                    <button
                        type="button"
                        wire:click="queueReport('{{ $report['type'] }}')"
                        wire:loading.attr="disabled"
                        wire:target="queueReport('{{ $report['type'] }}')"
                        class="btn btn-primary btn-sm flex-1"
                    >
                        <x-ui.icon name="chart-bar" class="w-3.5 h-3.5" />
                        <span wire:loading.remove wire:target="queueReport('{{ $report['type'] }}')">Generate PDF</span>
                        <span wire:loading wire:target="queueReport('{{ $report['type'] }}')">Queuing…</span>
                    </button>

                    @if ($req?->isReady())
                        <a
                            href="{{ route('reports.download', $req) }}"
                            target="_blank"
                            class="btn btn-secondary btn-sm"
                            title="Download PDF"
                        >
                            <x-ui.icon name="download" class="w-3.5 h-3.5" /> PDF
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Generated reports history --}}
    @if ($this->history->isNotEmpty())
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Generated Reports</h3>
            <p class="card-sub">Your last 30 generated reports  click Download to retrieve the PDF.</p>
        </div>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Report</th>
                        <th>Period</th>
                        <th>Requested</th>
                        <th>Status</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->history as $row)
                        @php
                            $label = collect($catalogue)->firstWhere('type', $row->type)['name'] ?? ucwords(str_replace('-', ' ', $row->type));
                        @endphp
                        <tr>
                            <td class="font-medium">{{ $label }}</td>
                            <td class="tabular-nums text-slate-500">
                                @if ($row->date_from && $row->date_to)
                                    {{ \Carbon\Carbon::parse($row->date_from)->format('d M Y') }} – {{ \Carbon\Carbon::parse($row->date_to)->format('d M Y') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-slate-500">{{ $row->created_at->diffForHumans() }}</td>
                            <td>
                                @if ($row->isPending() || $row->isProcessing())
                                    <span class="badge badge-yellow text-xs">
                                        <svg class="w-3 h-3 animate-spin inline mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                                        {{ ucfirst($row->status) }}
                                    </span>
                                @elseif ($row->isReady())
                                    <span class="badge badge-green text-xs">Ready</span>
                                @elseif ($row->isFailed())
                                    <span class="badge badge-red text-xs" title="{{ $row->error }}">Failed</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @if ($row->isReady())
                                    <a href="{{ route('reports.download', $row) }}" target="_blank" class="btn btn-secondary btn-xs">
                                        <x-ui.icon name="download" class="w-3.5 h-3.5" /> Download
                                    </a>
                                @else
                                    <span class="text-slate-400 text-sm">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
