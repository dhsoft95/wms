<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">Containers & Yard</h2>
            <p class="page-sub">Track containers from vessel arrival to yard release.</p>
        </div>
        <a href="{{ route('inbound.containers.create') }}" wire:navigate class="btn btn-primary">
            <x-ui.icon name="plus" class="w-4 h-4" /> New Container
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="tabs">
                @foreach ([['all','All'],['expected','Expected'],['arrived','Arrived'],['unloading','Unloading'],['released','Released']] as [$val,$lbl])
                    <button
                        wire:click="$set('statusFilter','{{ $val }}')"
                        @class(['tab','active' => $statusFilter === $val])
                    >{{ $lbl }}</button>
                @endforeach
            </div>
            <div class="relative">
                <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                <input wire:model.live="search" type="text" placeholder="Search container, BL, vessel" class="input !pl-9 sm:w-64">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr><th>Container</th><th>Seal No.</th><th>BL Reference</th><th>Vessel / Voyage</th><th>ETA</th><th>Yard Slot</th><th>Status</th><th class="text-right">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                <p class="mono font-semibold">{{ $row['container'] }}</p>
                                <p class="text-xs text-slate-500">{{ $row['type'] }}</p>
                            </td>
                            <td class="mono">{{ $row['seal'] }}</td>
                            <td class="mono">{{ $row['bl'] }}</td>
                            <td>{{ $row['vessel'] }}</td>
                            <td class="tabular-nums">{{ $row['eta'] }}</td>
                            <td>
                                @if ($row['slot'])
                                    <span class="font-mono text-xs px-2 py-0.5 rounded-md bg-zinc-900/5 dark:bg-white/10 text-slate-700 dark:text-slate-200">{{ $row['slot'] }}</span>
                                @else
                                    <span class="text-slate-400 italic text-sm">Not assigned</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $row['badge'] }}"><span class="dot"></span>{{ $row['label'] }}</span></td>
                            <td class="text-right">
                                <x-ui.row-actions :label="'Actions for container ' . $row['container']">
                                    @if (in_array($row['status'], ['unloading', 'arrived']))
                                        <x-ui.row-actions.item icon="inbox" label="Receive" :href="route('inbound.grn.create')" />
                                    @endif
                                    @if ($row['status'] === 'released')
                                        <x-ui.row-actions.item icon="doc" label="Documents" :href="route('reports.print')" />
                                    @endif
                                    @if (!in_array($row['status'], ['unloading', 'arrived', 'released']))
                                        <x-ui.row-actions.item icon="home" label="Assign Slot" tone="info" wire:click="$dispatch('toast', { message: 'Slot assignment coming in Module 3', type: 'info' })" />
                                    @endif
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="!py-12 text-center text-slate-500">No containers found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
