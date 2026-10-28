<div class="space-y-6" wire:poll.30s="$refresh">
    <div class="page-header">
        <div>
            <h2 class="page-title">Containers</h2>
            <p class="page-sub">Track inbound shipping containers from arrival through unloading and release.</p>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="card-header">
            <div class="relative">
                <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Container, BL, vessel…" class="input !pl-9 w-52">
            </div>
        </div>

        <div wire:loading.class="opacity-50 pointer-events-none">
            <x-ui.table>
                <thead>
                    <tr>
                        <th>Internal #</th>
                        <th>Container</th>
                        <th>Yard Slot</th>
                        <th>Dock</th>
                        <th>Vehicle</th>
                        <th>Driver</th>
                        <th>Status</th>
                        <th class="w-20"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->containers as $c)
                        @php $grn = $c->grns->first(); @endphp
                        <tr wire:key="container-{{ $c->id }}">
                            <td class="font-mono text-xs text-slate-500">{{ $c->number }}</td>
                            <td>
                                <div class="font-mono font-semibold text-sm">{{ $c->container_no }}</div>
                                <div class="text-[11px] text-slate-400 mt-0.5">{{ $c->size_type?->label() }}</div>
                            </td>
                            <td>
                                @if($c->yardLocation)
                                    <span class="font-mono text-xs px-2 py-0.5 rounded-md bg-zinc-900/5 dark:bg-white/10 text-slate-700 dark:text-slate-200">{{ $c->yardLocation->code }}</span>
                                @else
                                    <span class="text-slate-400 text-sm italic">—</span>
                                @endif
                            </td>
                            <td class="text-xs font-mono text-slate-600 dark:text-slate-300">
                                {{ $grn?->receivingDock?->code ?? '—' }}
                            </td>
                            <td class="text-xs text-slate-600 dark:text-slate-300">
                                @if($grn?->vehicle_type || $grn?->vehicle_plate)
                                    <div>{{ $grn->vehicle_type ?? '' }}</div>
                                    <div class="font-mono text-[11px] text-slate-400">{{ $grn->vehicle_plate ?? '' }}</div>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="text-xs text-slate-600 dark:text-slate-300">
                                @if($grn?->driver_name || $grn?->driver_phone)
                                    <div>{{ $grn->driver_name ?? '' }}</div>
                                    <div class="text-[11px] text-slate-400">{{ $grn->driver_phone ?? '' }}</div>
                                @else
                                    <span class="text-slate-400">—</span>
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
                        <x-ui.empty-state icon="cube" title="No containers found" text="Register the first container or adjust your filters." :cols="8" />
                    @endforelse
                </tbody>
            </x-ui.table>
        </div>

        <div class="p-4 border-t border-white/40 dark:border-white/[0.08]">
            {{ $this->containers->links() }}
        </div>
    </div>
</div>
