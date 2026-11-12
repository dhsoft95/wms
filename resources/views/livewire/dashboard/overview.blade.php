<div class="space-y-4">

    {{-- ── Greeting card ───────────────────────────────────────────────────── --}}
    @php $userName = auth()->user()->name; @endphp
    <div
        x-data="{
            greeting: '',
            date: '',
            init() {
                const h = new Date().getHours()
                this.greeting = h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening'
                this.date = new Date().toLocaleDateString('en-GB', { weekday:'long', day:'numeric', month:'long', year:'numeric' })
            }
        }"
        x-init="init()"
        class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-simba-700 via-simba-600 to-simba-500 px-6 py-5 text-white shadow-lg shadow-simba-900/20"
    >
        {{-- Decorative circles --}}
        <div class="pointer-events-none absolute -right-8 -top-8 size-40 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-10 right-20 size-28 rounded-full bg-white/8"></div>

        <div class="relative flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-medium text-simba-100" x-text="date"></p>
                <h2 class="mt-0.5 text-2xl font-bold tracking-tight" x-text="greeting + ', {{ $userName }}!'"></h2>
                <p class="mt-1 text-sm text-simba-100">Here's what's happening in your warehouse today.</p>
            </div>

            <div class="flex items-center gap-4">
                @if (($this->pendingApprovals['total'] ?? 0) > 0)
                <a href="{{ route('approvals.index') }}" wire:navigate
                   class="flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 text-sm font-medium backdrop-blur-sm transition hover:bg-white/25">
                    <x-ui.icon name="bell" class="size-4 shrink-0" />
                    <span>{{ $this->pendingApprovals['total'] }} pending approval{{ $this->pendingApprovals['total'] !== 1 ? 's' : '' }}</span>
                </a>
                @else
                <div class="flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2.5 text-sm text-simba-100">
                    <x-ui.icon name="check-badge" class="size-4 shrink-0" />
                    <span>All approvals up to date</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Page header + filters ─────────────────────────────────────────── --}}
    <div class="page-header flex-wrap gap-y-2">
        <h2 class="page-title">Operations Overview</h2>

        <div class="flex flex-wrap items-center gap-2 ml-auto">
            {{-- Warehouse filter --}}
            @if ($this->warehouses->isNotEmpty())
            <select wire:model.live="warehouseId" class="input input-sm w-44">
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
        </div>
    </div>

    {{-- ── KPI cards grid ──────────────────────────────────────────────────── --}}

    <div wire:loading.class="kpi-cards--loading"
         class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-3 auto-rows-fr">
        @foreach ($this->kpis as $kpi)
            <x-ui.kpi-card
                :label="$kpi['label']"
                :value="$kpi['value']"
                :hint="$kpi['hint']"
                :icon="$kpi['icon']"
                :tone="$kpi['tone']"
                :href="$kpi['href']"
                :spark="$kpi['spark']"
                :trend="$kpi['trend']"
            />
        @endforeach
    </div>

    {{-- ── Charts ───────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- Inbound vs Outbound + Order Pipeline (tabbed) --}}
        @if ($this->canSeeChart('inbound_vs_outbound') || $this->canSeeChart('order_pipeline'))
        <div class="card" x-data="{ tab: 'inbound' }">
            <div class="card-header">
                <div class="flex gap-1 rounded-lg bg-slate-100 dark:bg-white/8 p-0.5">
                    @if ($this->canSeeChart('inbound_vs_outbound'))
                    <button type="button"
                            @click="tab = 'inbound'"
                            :class="tab === 'inbound' ? 'bg-white dark:bg-white/10 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
                            class="px-3 py-1 rounded-md text-xs font-medium transition-all">
                        Inbound vs Outbound
                    </button>
                    @endif
                    @if ($this->canSeeChart('order_pipeline'))
                    <button type="button"
                            @click="tab = 'pipeline'"
                            :class="tab === 'pipeline' ? 'bg-white dark:bg-white/10 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
                            class="px-3 py-1 rounded-md text-xs font-medium transition-all">
                        Order Pipeline
                    </button>
                    @endif
                </div>
                <span class="text-xs text-slate-400 ml-auto"
                      x-show="tab === 'inbound'">Last {{ $days }} days</span>
                <span class="text-xs text-slate-400 ml-auto"
                      x-show="tab === 'pipeline'" x-cloak>By status</span>
            </div>
            <div class="card-body">
                @if ($this->canSeeChart('inbound_vs_outbound'))
                <div x-show="tab === 'inbound'">
                    <x-ui.chart
                        type="line"
                        :series="$this->chartInboundVsOutbound['series'] ?? []"
                        :categories="$this->chartInboundVsOutbound['categories'] ?? []"
                        :height="200"
                        unit="units"
                        chart-id="chart-inbound-outbound"
                    />
                </div>
                @endif
                @if ($this->canSeeChart('order_pipeline'))
                <div x-show="tab === 'pipeline'" x-cloak>
                    <x-ui.chart
                        type="bar"
                        :series="$this->chartOrderPipeline['series'] ?? []"
                        :categories="$this->chartOrderPipeline['categories'] ?? []"
                        :height="200"
                        chart-id="chart-order-pipeline"
                    />
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Bin Utilisation --}}
        @if ($this->canSeeChart('bin_utilisation'))
        @php
            $binData     = $this->chartBinUtilisation;
            $binTotal    = $binData['total']     ?? 0;
            $binOccupied = $binData['occupied']  ?? 0;
            $binAvail    = $binData['available'] ?? 0;
            $binPct      = (float) ($binData['pct'] ?? 0);
            $binAvailPct = $binTotal > 0 ? round(($binAvail / $binTotal) * 100, 1) : 0.0;
            [$binStatusLabel, $binStatusBadge] = $binPct > 85
                ? ['Near capacity',  'badge-red']
                : ($binPct >= 20
                    ? ['Healthy',        'badge-green']
                    : ['Low utilisation','badge-slate']);
        @endphp
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Bin Utilisation</h3>
                @if ($binTotal > 0)
                    <span class="text-xs text-slate-400 ml-auto tabular-nums">
                        {{ number_format($binOccupied) }} of {{ number_format($binTotal) }} bins
                    </span>
                @endif
            </div>

            @if ($binTotal === 0)
                <div class="card-body">
                    <p class="text-sm text-slate-400 text-center py-4">No bins configured.</p>
                </div>
            @else
            <div class="card-body">
                <div
                    x-data="binUtilChart({{ Js::from([
                        'chartId'   => 'chart-bin-util',
                        'occupied'  => $binOccupied,
                        'available' => $binAvail,
                        'total'     => $binTotal,
                        'pct'       => $binPct,
                    ]) }})"
                    x-init="init()"
                    x-on:livewire:navigated.window="destroy()"
                    wire:ignore
                    class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center"
                >
                    <div class="flex justify-center md:justify-end">
                        <div class="relative" style="width:192px;height:192px;">
                            <canvas id="chart-bin-util"></canvas>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="size-2.5 shrink-0 rounded-full bg-simba-600"></span>
                                <span class="text-sm text-slate-600 dark:text-slate-400 flex-1">Occupied</span>
                                <span class="text-sm font-semibold tabular-nums text-slate-900 dark:text-white">{{ number_format($binOccupied) }}</span>
                                <span class="text-xs text-slate-400 tabular-nums w-11 text-right">{{ number_format($binPct, 1) }}%</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="size-2.5 shrink-0 rounded-full bg-green-600"></span>
                                <span class="text-sm text-slate-600 dark:text-slate-400 flex-1">Available</span>
                                <span class="text-sm font-semibold tabular-nums text-slate-900 dark:text-white">{{ number_format($binAvail) }}</span>
                                <span class="text-xs text-slate-400 tabular-nums w-11 text-right">{{ number_format($binAvailPct, 1) }}%</span>
                            </div>
                        </div>

                        <div class="border-t border-slate-100 dark:border-white/[0.08] pt-3 space-y-1.5">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-500 dark:text-slate-400">Total bins</span>
                                <span class="font-semibold text-slate-900 dark:text-white tabular-nums">{{ number_format($binTotal) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-500 dark:text-slate-400">Status</span>
                                <span class="badge {{ $binStatusBadge }}">{{ $binStatusLabel }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
        @endif

    </div>

    {{-- Top Moving Items — full width --}}
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
                :height="200"
                unit="units"
                chart-id="chart-top-items"
            />
        </div>
    </div>
    @endif


</div>
