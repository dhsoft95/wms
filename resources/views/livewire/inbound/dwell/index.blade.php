<div class="space-y-6">

    {{-- Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Dwell Time</h2>
            <p class="page-sub">Monitor how long containers have been in the yard. Sort by dwell days to prioritize releases.</p>
        </div>
        <a href="{{ route('inbound.containers.index') }}" wire:navigate class="btn btn-secondary">
            <x-ui.icon name="list" class="w-4 h-4" /> All Containers
        </a>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-4">
        <div class="card p-5">
            <p class="text-xs font-medium text-slate-500">In Yard</p>
            <p class="mt-2 text-3xl font-bold tabular-nums">{{ $this->stats['inYard'] }}</p>
            <p class="mt-2 text-xs text-slate-400">Active containers</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-medium text-slate-500">Avg Dwell</p>
            <p class="mt-2 text-3xl font-bold tabular-nums">{{ $this->stats['avgDwell'] }}<span class="text-lg font-medium ml-1">d</span></p>
            <p class="mt-2 text-xs text-slate-400">Days average</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-medium text-slate-500">Over Warning</p>
            <p class="mt-2 text-3xl font-bold tabular-nums {{ $this->stats['overWarning'] > 0 ? 'text-amber-600' : '' }}">{{ $this->stats['overWarning'] }}</p>
            <p class="mt-2 text-xs text-slate-400">Containers</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-medium text-slate-500">Critical Dwell</p>
            <p class="mt-2 text-3xl font-bold tabular-nums {{ $this->stats['overCritical'] > 0 ? 'text-red-600' : '' }}">{{ $this->stats['overCritical'] }}</p>
            <p class="mt-2 text-xs text-slate-400">Containers</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-medium text-slate-500">Total Demurrage</p>
            <p class="mt-2 text-3xl font-bold tabular-nums">{{ $this->stats['totalDemurrage'] }}<span class="text-lg font-medium ml-1">d</span></p>
            <p class="mt-2 text-xs text-slate-400">Across all containers</p>
        </div>
    </div>

    {{-- Dwell Table --}}
    <div class="card overflow-hidden">
        <div class="card-header">
            <h3 class="card-title">Containers by Dwell (Longest First)</h3>
        </div>
        <x-ui.table>
            <thead>
                <tr>
                    <th>Container</th>
                    <th>Status</th>
                    <th>Arrived</th>
                    <th>Yard Slot</th>
                    <th>Free Days</th>
                    <th>Dwell</th>
                    <th>Demurrage</th>
                    <th class="w-20"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->containers as $c)
                    @php $dwell = $c->dwellDays(); $dwellStatus = $c->dwellStatus(); $demurrage = $c->demurrageDays(); @endphp
                    <tr wire:key="dwell-{{ $c->id }}">
                        <td>
                            <div class="font-mono font-semibold text-sm">{{ $c->container_no }}</div>
                            <div class="text-xs text-slate-400">{{ $c->shippingLinePartner?->name ?? '—' }}</div>
                        </td>
                        <td>
                            <span class="badge {{ $c->status->color() }}">
                                <span class="dot"></span>{{ $c->status->label() }}
                            </span>
                        </td>
                        <td class="text-sm tabular-nums">{{ $c->arrived_at->format('d M Y') }}</td>
                        <td>
                            @if($c->yardLocation)
                                <span class="font-mono text-xs px-2 py-0.5 rounded-md bg-zinc-900/5 dark:bg-white/10 text-slate-700 dark:text-slate-200">{{ $c->yardLocation->code }}</span>
                            @else
                                <span class="text-slate-400 text-sm italic">—</span>
                            @endif
                        </td>
                        <td class="text-sm tabular-nums">{{ $c->free_days ?? '(default)' }}</td>
                        <td>
                            <span @class([
                                'badge',
                                'badge-neutral' => $dwellStatus === 'ok',
                                'badge-amber'   => $dwellStatus === 'warning',
                                'badge-red'     => $dwellStatus === 'critical',
                            ])>{{ $dwell }}d</span>
                        </td>
                        <td class="text-sm tabular-nums {{ $demurrage > 0 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-400' }}">
                            {{ $demurrage > 0 ? $demurrage . 'd' : '—' }}
                        </td>
                        <td>
                            <a href="{{ route('inbound.containers.show', $c) }}" wire:navigate class="icon-btn" title="View">
                                <x-ui.icon name="eye" class="w-4 h-4" />
                            </a>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-state icon="clock" title="No containers with dwell data" text="Containers will appear here after they have arrived in the yard." :cols="8" />
                @endforelse
            </tbody>
        </x-ui.table>
    </div>

</div>
