<div class="space-y-6">

    {{-- ── PAGE HEADER ──────────────────────────────────────────────────────────── --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Reports Library</h2>
            <p class="page-sub">Build, generate and export warehouse reports.</p>
        </div>
    </div>

    {{-- ── REPORT BUILDER ───────────────────────────────────────────────────────── --}}
    <div class="card overflow-hidden">

        {{-- Step 1 — Select report ─────────────────────────────────────────────── --}}
        <div class="px-6 pt-6 pb-5 border-b border-slate-100 dark:border-white/[0.06]">
            <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400 dark:text-slate-500 mb-3">
                1 &nbsp;·&nbsp; Select Report
            </p>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
                <div class="lg:col-span-2">
                    <select wire:model.live="reportKey"
                            class="input w-full text-sm font-medium h-10">
                        <option value="">— choose a report —</option>
                        @foreach ($this->registry->grouped() as $group => $defs)
                            <optgroup label="{{ $group }}">
                                @foreach ($defs as $def)
                                    <option value="{{ $def->key() }}">{{ $def->label() }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                @if ($this->currentDefinition !== null)
                    <div class="flex items-center gap-2 h-10">
                        <span class="badge badge-slate text-xs">{{ $this->currentDefinition->group() }}</span>
                        <span class="text-xs text-slate-400">
                            {{ $this->currentDefinition->periodType() === 'as_of' ? 'Point-in-time' : 'Date range' }}
                        </span>
                        @if ($this->currentDefinition->pdfEnabled())
                            <span class="badge badge-green text-xs">PDF</span>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- Step 2 — Filters (only shown once a report is chosen) ─────────────── --}}
        @if ($this->currentDefinition !== null)
            <div class="px-6 py-5 border-b border-slate-100 dark:border-white/[0.06]">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400 dark:text-slate-500 mb-4">
                    2 &nbsp;·&nbsp; Filters
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-x-6 gap-y-4">

                    {{-- Period ──────────────────────────────────────────────────── --}}
                    @if ($this->currentDefinition->periodType() === 'as_of')
                        <div class="md:col-span-2 xl:col-span-1">
                            <label class="label">As of Date</label>
                            <input wire:model="asOfDate" type="date"
                                   class="input w-full tabular-nums">
                        </div>
                    @else
                        <div>
                            <label class="label">From</label>
                            <input wire:model="dateFrom" type="date"
                                   class="input w-full tabular-nums">
                        </div>
                        <div>
                            <label class="label">To</label>
                            <input wire:model="dateTo" type="date"
                                   class="input w-full tabular-nums">
                        </div>
                    @endif

                    {{-- Warehouse ──────────────────────────────────────────────── --}}
                    <div>
                        <label class="label">Warehouse</label>
                        <select wire:model.live="warehouseId" class="input w-full text-sm">
                            <option value="">All warehouses</option>
                            @foreach ($this->virtualWarehouses as $vw)
                                <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Department ─────────────────────────────────────────────── --}}
                    <div>
                        <label class="label">Department</label>
                        <select wire:model.live="departmentId" class="input w-full text-sm">
                            <option value="">All departments</option>
                            @foreach ($this->departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>

                </div>

                {{-- Quick date shortcuts (range only) ───────────────────────────── --}}
                @if ($this->currentDefinition->periodType() !== 'as_of')
                    <div class="flex flex-wrap gap-2 mt-3">
                        <span class="text-xs text-slate-400 self-center mr-1">Quick:</span>
                        @foreach ([
                            'This month'  => [now()->startOfMonth()->format('Y-m-d'), now()->format('Y-m-d')],
                            'Last month'  => [now()->subMonth()->startOfMonth()->format('Y-m-d'), now()->subMonth()->endOfMonth()->format('Y-m-d')],
                            'Last 7 days' => [now()->subDays(6)->format('Y-m-d'), now()->format('Y-m-d')],
                            'Last 30 days'=> [now()->subDays(29)->format('Y-m-d'), now()->format('Y-m-d')],
                            'This year'   => [now()->startOfYear()->format('Y-m-d'), now()->format('Y-m-d')],
                        ] as $label => [$from, $to])
                            <button type="button"
                                    wire:click="$set('dateFrom', '{{ $from }}'); $set('dateTo', '{{ $to }}')"
                                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium
                                           border border-slate-200 dark:border-white/10
                                           text-slate-600 dark:text-slate-300
                                           hover:bg-slate-50 dark:hover:bg-white/[0.06]
                                           transition-colors cursor-pointer">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                @endif

                {{-- Activity-specific extras ──────────────────────────────────── --}}
                @if ($this->currentDefinition->key() === 'received-dispatched')
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4 pt-4 border-t border-slate-100 dark:border-white/[0.06]">
                        <div>
                            <label class="label">Doc Type</label>
                            <select wire:model.live="docType" class="input w-full text-sm">
                                <option value="all">All types</option>
                                <option value="po">PO — Inbound</option>
                                <option value="mrn">MRN — Outbound</option>
                                <option value="stn">STN — Transfer</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Direction</label>
                            <select wire:model.live="direction" class="input w-full text-sm">
                                <option value="both">Both directions</option>
                                <option value="in">Inbound only</option>
                                <option value="out">Outbound only</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Group By</label>
                            <select wire:model.live="grouping" class="input w-full text-sm">
                                <option value="day">Day</option>
                                <option value="week">Week</option>
                                <option value="month">Month</option>
                            </select>
                        </div>
                    </div>
                @endif

            </div>
        @endif

        {{-- Generate bar ──────────────────────────────────────────────────────── --}}
        <div class="px-6 py-4 bg-slate-50 dark:bg-white/[0.02] flex items-center justify-between gap-4">
            <p class="text-xs text-slate-400 hidden sm:block">
                @if ($this->currentDefinition === null)
                    Select a report to get started.
                @elseif ($this->currentDefinition->periodType() === 'as_of')
                    Snapshot as of {{ $asOfDate ?: 'today' }}.
                @else
                    {{ $dateFrom }} → {{ $dateTo }}
                @endif
            </p>
            <button wire:click="generate"
                    wire:loading.attr="disabled"
                    class="btn btn-primary ml-auto"
                    @if($this->currentDefinition === null) disabled @endif>
                <x-ui.icon name="chart-bar" class="w-4 h-4" />
                <span wire:loading.remove wire:target="generate">Run Report</span>
                <span wire:loading wire:target="generate">Running…</span>
            </button>
        </div>

    </div>

    {{-- ── AI SUMMARY ───────────────────────────────────────────────────────────── --}}
    @if ($showSummary && $aiSummaryText !== '')
        <div class="card border-l-4 border-simba-500">
            <div class="card-body">
                <div class="flex items-center gap-2 mb-2">
                    <x-ui.icon name="info" class="w-4 h-4 text-simba-500" />
                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">AI Summary</span>
                    <button wire:click="$set('showSummary', false)" class="ml-auto text-slate-400 hover:text-slate-600">
                        <x-ui.icon name="x" class="w-4 h-4" />
                    </button>
                </div>
                <div class="prose prose-sm dark:prose-invert max-w-none text-slate-700 dark:text-slate-300">
                    {!! nl2br(e($aiSummaryText)) !!}
                </div>
            </div>
        </div>
    @endif

    {{-- ── RESULTS TABLE ────────────────────────────────────────────────────────── --}}
    @if ($hasResults && $this->currentDefinition !== null)
        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h3 class="card-title">{{ $this->currentDefinition->label() }}</h3>
                    @if ($this->results !== null)
                        <p class="text-xs text-slate-400 mt-0.5">{{ number_format($this->results->total()) }} rows</p>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    @if (config('ai.enabled', false))
                        <button wire:click="loadAiSummary" wire:loading.attr="disabled" class="btn btn-secondary btn-sm">
                            <span wire:loading.remove wire:target="loadAiSummary">AI Summary</span>
                            <span wire:loading wire:target="loadAiSummary">Generating…</span>
                        </button>
                    @endif
                    <button wire:click="exportExcel" wire:loading.attr="disabled" class="btn btn-secondary btn-sm">
                        <x-ui.icon name="download" class="w-4 h-4" />
                        <span wire:loading.remove wire:target="exportExcel">Excel</span>
                        <span wire:loading wire:target="exportExcel">Queuing…</span>
                    </button>
                    @if ($this->currentDefinition->pdfEnabled())
                        <button wire:click="exportPdf" wire:loading.attr="disabled" class="btn btn-secondary btn-sm">
                            <x-ui.icon name="document-text" class="w-4 h-4" />
                            <span wire:loading.remove wire:target="exportPdf">PDF</span>
                            <span wire:loading wire:target="exportPdf">Queuing…</span>
                        </button>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto">
                @if ($this->results !== null && $this->results->count() > 0)
                    <x-ui.table>
                        <thead>
                            <tr>
                                @foreach ($this->currentDefinition->columns() as $col)
                                    <th class="text-{{ $col['align'] ?? 'left' }}">{{ $col['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->results as $row)
                                <tr>
                                    @foreach ($this->currentDefinition->columns() as $col)
                                        @php
                                            $val = data_get($row, $col['key']);
                                            $fmt = $col['format'] ?? 'text';
                                        @endphp
                                        <td class="text-{{ $col['align'] ?? 'left' }} tabular-nums">
                                            @if ($fmt === 'number')
                                                {{ is_numeric($val) ? number_format((float) $val, 2) : ($val ?? '—') }}
                                            @elseif ($fmt === 'date')
                                                {{ $val instanceof \Carbon\Carbon ? $val->format('d M Y') : ($val ? \Carbon\Carbon::parse($val)->format('d M Y') : '—') }}
                                            @elseif ($fmt === 'enum')
                                                {{ $val instanceof \BackedEnum ? (method_exists($val, 'label') ? $val->label() : $val->value) : ($val ?? '—') }}
                                            @else
                                                {{ $val ?? '—' }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>

                    <div class="px-4 py-3 border-t border-slate-100 dark:border-white/[0.06]">
                        {{ $this->results->links() }}
                    </div>
                @else
                    <x-ui.empty-state icon="chart-bar" title="No results"
                        description="No data matched the selected filters." />
                @endif
            </div>
        </div>
    @endif

    {{-- ── DOCUMENT REPORTS ────────────────────────────────────────────────────── --}}
    <div class="card overflow-hidden">
        <div class="card-header">
            <div>
                <h3 class="card-title">Document Reports</h3>
                <p class="text-xs text-slate-400 mt-0.5">Per-record PDF documents — generated directly from the relevant record page.</p>
            </div>
        </div>
        <div class="divide-y divide-slate-100 dark:divide-white/[0.05]">

            {{-- Container Receipt Report --}}
            <div class="flex items-center justify-between gap-4 px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-simba-50 dark:bg-simba-500/10">
                        <x-ui.icon name="document-text" class="w-4 h-4 text-simba-600 dark:text-simba-400" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Container Receipt Report</p>
                        <p class="text-xs text-slate-400 mt-0.5">Full dossier: container details, voyage, partners, GRN summary, received lines with variance analysis.</p>
                    </div>
                </div>
                <a href="{{ route('inbound.containers.index') }}"
                   class="btn btn-secondary btn-sm shrink-0">
                    <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                    Go to Containers
                </a>
            </div>

        </div>
    </div>

    {{-- ── QUEUED EXPORTS ───────────────────────────────────────────────────────── --}}
    @if ($this->recentRequests->isNotEmpty())
        <div class="card overflow-hidden"
             @if (!empty($trackedPendingIds)) wire:poll.5s="pollExports" @endif>
            <div class="card-header">
                <h3 class="card-title">Export Queue</h3>
                <span class="text-xs text-slate-400">Recent exports for your account</span>
            </div>
            <div class="overflow-x-auto">
                <x-ui.table>
                    <thead>
                        <tr>
                            <th>Report</th>
                            <th>Format</th>
                            <th>Status</th>
                            <th>Queued</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->recentRequests as $req)
                            <tr>
                                <td class="font-medium">{{ $req->type }}</td>
                                <td><span class="badge badge-slate text-xs uppercase">{{ $req->format ?? 'excel' }}</span></td>
                                <td>
                                    @if ($req->isPending() || $req->isProcessing())
                                        <span class="badge badge-amber">
                                            <svg class="w-3 h-3 animate-spin mr-1 inline" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                            </svg>
                                            {{ ucfirst($req->status) }}
                                        </span>
                                    @elseif ($req->isReady())
                                        <span class="badge badge-green">Ready</span>
                                    @elseif ($req->isFailed())
                                        @if ($req->error && auth()->user()?->hasRole('system-admin'))
                                            <span class="badge badge-red cursor-help"
                                                  title="{{ $req->error }}">Failed ⓘ</span>
                                        @else
                                            <span class="badge badge-red">Report failed</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="text-xs text-slate-500">{{ $req->created_at->diffForHumans() }}</td>
                                <td class="text-right">
                                    @if ($req->isReady() && $req->file_path)
                                        <div class="inline-flex items-center gap-1.5">
                                            @if (($req->format ?? 'excel') === 'pdf')
                                                <a href="{{ route('reports.view', $req) }}"
                                                   target="_blank"
                                                   class="btn btn-secondary btn-xs">
                                                    <x-ui.icon name="eye" class="w-3 h-3" />
                                                    View
                                                </a>
                                            @endif
                                            <a href="{{ route('reports.download', $req) }}"
                                               class="btn btn-secondary btn-xs">
                                                <x-ui.icon name="download" class="w-3 h-3" />
                                                Download
                                            </a>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </div>
        </div>
    @endif

</div>

<script>
function playReportReadySound() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        // C5 → E5 → G5 ascending chime
        [[523.25, 0], [659.25, 0.18], [783.99, 0.36]].forEach(([freq, delay]) => {
            const osc  = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'sine';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.28, ctx.currentTime + delay);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + delay + 0.55);
            osc.start(ctx.currentTime + delay);
            osc.stop(ctx.currentTime + delay + 0.55);
        });
    } catch {}
}
</script>
