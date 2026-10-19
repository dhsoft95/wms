<div class="space-y-6" wire:poll.30s="$refresh">
    <div class="page-header">
        <div>
            <h2 class="page-title">Containers</h2>
            <p class="page-sub">Track inbound shipping containers from arrival through unloading and release.</p>
        </div>
    </div>

    <div class="card overflow-hidden">
        {{-- Status tabs --}}
        <div class="card-header flex-wrap gap-y-3">
            <div class="tabs overflow-x-auto flex-1">
                <button wire:click="$set('statusFilter','all')" @class(['tab','active' => $statusFilter==='all'])>
                    All <span class="badge badge-neutral ml-1 text-[10px]">{{ $this->statusCounts['all'] }}</span>
                </button>
                @foreach (\App\Enums\ContainerStatus::cases() as $s)
                    <button wire:click="$set('statusFilter','{{ $s->value }}')" @class(['tab','active' => $statusFilter===$s->value])>
                        {{ $s->label() }}
                        @if(($this->statusCounts[$s->value] ?? 0) > 0)
                            <span class="badge {{ $s->color() }} ml-1 text-[10px]">{{ $this->statusCounts[$s->value] }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
            <div class="flex gap-2 flex-wrap">
                <div class="relative">
                    <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Container, BL, vessel…" class="input !pl-9 w-52">
                </div>
                <select wire:model.live="filterDwell" class="input text-sm py-1.5 px-2 h-auto">
                    <option value="">All dwell</option>
                    <option value="warning">Warning dwell</option>
                    <option value="critical">Critical dwell</option>
                </select>
            </div>
        </div>

        <div wire:loading.class="opacity-50 pointer-events-none">
            <x-ui.table>
                <thead>
                    <tr>
                        <th>Internal #</th>
                        <th>Container</th>
                        <th>BL</th>
                        <th>Vessel / Voyage</th>
                        <th>ETA</th>
                        <th>Arrived</th>
                        <th>Yard Slot</th>
                        <th>Dwell</th>
                        <th>Status</th>
                        <th class="w-20"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->containers as $c)
                        @php $dwell = $c->dwellDays(); $dwellStatus = $c->dwellStatus(); @endphp
                        <tr wire:key="container-{{ $c->id }}">
                            <td class="font-mono text-xs text-slate-500">{{ $c->number }}</td>
                            <td>
                                <div class="font-mono font-semibold text-sm">{{ $c->container_no }}</div>
                                <div class="text-[11px] text-slate-400 mt-0.5">{{ $c->size_type?->label() }}</div>
                            </td>
                            <td class="font-mono text-xs text-slate-500">{{ $c->bl_no ?? '—' }}</td>
                            <td class="text-sm">
                                {{ $c->vessel ?? '—' }}
                                @if($c->voyage) <span class="text-slate-400 text-xs">V.{{ $c->voyage }}</span> @endif
                            </td>
                            <td class="text-sm tabular-nums">{{ $c->eta?->format('d M Y') ?? '—' }}</td>
                            <td class="text-sm tabular-nums">{{ $c->arrived_at?->format('d M Y') ?? '—' }}</td>
                            <td>
                                @if($c->yardLocation)
                                    <span class="font-mono text-xs px-2 py-0.5 rounded-md bg-zinc-900/5 dark:bg-white/10 text-slate-700 dark:text-slate-200">{{ $c->yardLocation->code }}</span>
                                @else
                                    <span class="text-slate-400 text-sm italic">—</span>
                                @endif
                            </td>
                            <td>
                                @if($c->arrived_at)
                                    <span @class([
                                        'badge',
                                        'badge-neutral'  => $dwellStatus === 'ok',
                                        'badge-amber'    => $dwellStatus === 'warning',
                                        'badge-red'      => $dwellStatus === 'critical',
                                    ])>{{ $dwell }}d</span>
                                @else
                                    <span class="text-slate-400 text-sm">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $c->status->color() }}">
                                    <span class="dot"></span>{{ $c->status->label() }}
                                </span>
                            </td>
                            <td>
                                <div class="flex items-center gap-1">
                                    <a href="{{ route('inbound.containers.show', $c) }}" wire:navigate class="icon-btn" title="View">
                                        <x-ui.icon name="eye" class="w-4 h-4" />
                                    </a>
                                    @if($c->isEditable())
                                        @can('update', $c)
                                            <a href="{{ route('inbound.containers.edit', $c) }}" wire:navigate class="icon-btn" title="Edit">
                                                <x-ui.icon name="pencil" class="w-4 h-4" />
                                            </a>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state icon="cube" title="No containers found" text="Register the first container or adjust your filters." :cols="10" />
                    @endforelse
                </tbody>
            </x-ui.table>
        </div>

        <div class="p-4 border-t border-white/40 dark:border-white/[0.08]">
            {{ $this->containers->links() }}
        </div>
    </div>
</div>
