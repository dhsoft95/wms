<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">Goods Receipt Notes</h2>
            <p class="page-sub">Receiving records for every container and supplier delivery.</p>
        </div>
        <a href="{{ route('inbound.grn.create') }}" wire:navigate class="btn btn-primary">
            <x-ui.icon name="plus" class="w-4 h-4" /> New GRN
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="tabs">
                @foreach ([['all','All'],['progress','In Progress'],['discrepancy','Discrepancy'],['completed','Completed']] as [$val,$lbl])
                    <button wire:click="$set('statusFilter','{{ $val }}')" @class(['tab','active' => $statusFilter === $val])>{{ $lbl }}</button>
                @endforeach
            </div>
            <div class="relative">
                <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                <input wire:model.live="search" type="text" placeholder="Search GRN, container, supplier" class="input !pl-9 sm:w-64">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr><th>GRN Number</th><th>Container</th><th>Supplier</th><th>Received</th><th>Warehouse</th><th>Progress</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="mono font-semibold">{{ $row['grn'] }}</td>
                            <td class="mono">{{ $row['container'] }}</td>
                            <td>{{ $row['supplier'] }}</td>
                            <td class="tabular-nums">{{ $row['received'] }}</td>
                            <td>{{ $row['warehouse'] }}</td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <div class="progress w-24">
                                        <div class="{{ $row['barColor'] ?? 'bg-emerald-600' }}" style="width:{{ $row['progress'] }}%"></div>
                                    </div>
                                    <span class="text-xs tabular-nums text-slate-500">{{ $row['count'] }}</span>
                                </div>
                            </td>
                            <td><span class="badge {{ $row['badge'] }}"><span class="dot"></span>{{ $row['label'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="!py-12 text-center text-slate-500">No GRNs found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
