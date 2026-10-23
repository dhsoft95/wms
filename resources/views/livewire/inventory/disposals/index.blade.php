<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">Disposal Requests</h2>
            <p class="page-sub">Manage obsolete, damaged or quarantined stock disposals.</p>
        </div>
        @can('create', \App\Models\DisposalRequest::class)
            <a href="{{ route('inventory.disposals.create') }}" wire:navigate class="btn btn-primary">
                <x-ui.icon name="plus" class="w-4 h-4" /> New Disposal
            </a>
        @endcan
    </div>

    <div class="card">
        <div class="card-body">
            <div class="flex gap-3 flex-wrap">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search number…" class="input w-56">
                <select wire:model.live="status" class="input w-44">
                    <option value="">All statuses</option>
                    @foreach(\App\Enums\Inventory\DisposalStatus::cases() as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <x-ui.table>
            <thead>
                <tr>
                    <th>Number</th>
                    <th>Virtual Warehouse</th>
                    <th>Method</th>
                    <th>Lines</th>
                    <th>Total Value</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($disposals as $disposal)
                    <tr wire:key="disp-{{ $disposal->id }}">
                        <td>
                            <a href="{{ route('inventory.disposals.show', $disposal) }}" wire:navigate
                               class="mono font-semibold text-simba-600 dark:text-simba-400 hover:underline">
                                {{ $disposal->number }}
                            </a>
                        </td>
                        <td class="text-sm">{{ $disposal->virtualWarehouse?->name ?? '—' }}</td>
                        <td class="text-sm">{{ $disposal->method?->label() ?? '—' }}</td>
                        <td class="tabular-nums text-slate-500">{{ $disposal->lines_count }}</td>
                        <td class="tabular-nums text-sm">
                            @if($disposal->total_value !== null)
                                {{ number_format((float) $disposal->total_value, 2) }}
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $disposal->status->badge() }}">
                                <span class="dot"></span>{{ $disposal->status->label() }}
                            </span>
                        </td>
                        <td class="text-meta tabular-nums">{{ $disposal->created_at->format('d M Y') }}</td>
                        <td class="text-right">
                            <a href="{{ route('inventory.disposals.show', $disposal) }}" wire:navigate class="btn btn-sm btn-ghost">
                                <x-ui.icon name="eye" class="w-3.5 h-3.5" />
                            </a>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-state title="No disposal requests found." :cols="8" />
                @endforelse
            </tbody>
        </x-ui.table>
        <div class="card-footer">{{ $disposals->links() }}</div>
    </div>
</div>
