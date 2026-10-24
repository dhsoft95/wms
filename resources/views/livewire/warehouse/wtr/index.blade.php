<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">Warehouse Transfers</h2>
            <p class="page-sub">Move stock between warehouses (WTR).</p>
        </div>
        @can('create', \App\Models\WarehouseTransfer::class)
            <a href="{{ route('warehouse.wtr.create') }}" wire:navigate class="btn btn-primary">
                <x-ui.icon name="plus" class="w-4 h-4" /> New WTR
            </a>
        @endcan
    </div>

    <div class="card">
        <div class="card-body">
            <div class="flex gap-3 flex-wrap">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search number…" class="input w-56">
                <select wire:model.live="status" class="input w-44">
                    <option value="">All statuses</option>
                    @foreach($statuses as $s)
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
                    <th>From</th>
                    <th>To</th>
                    <th>Created By</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->transfers as $wtr)
                    <tr wire:key="wtr-{{ $wtr->id }}">
                        <td>
                            <a href="{{ route('warehouse.wtr.show', $wtr) }}" wire:navigate
                               class="mono font-semibold text-simba-600 dark:text-simba-400 hover:underline">
                                {{ $wtr->number }}
                            </a>
                        </td>
                        <td class="text-sm">{{ $wtr->fromWarehouse?->name ?? '—' }}</td>
                        <td class="text-sm">{{ $wtr->toWarehouse?->name ?? '—' }}</td>
                        <td class="text-sm">{{ $wtr->createdBy?->name ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $wtr->status->badge() }}">
                                <span class="dot"></span>{{ $wtr->status->label() }}
                            </span>
                        </td>
                        <td class="text-meta tabular-nums">{{ $wtr->created_at->format('d M Y') }}</td>
                        <td class="text-right">
                            <a href="{{ route('warehouse.wtr.show', $wtr) }}" wire:navigate class="btn btn-sm btn-ghost">
                                <x-ui.icon name="eye" class="w-3.5 h-3.5" />
                            </a>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-state title="No warehouse transfers found." :cols="7" />
                @endforelse
            </tbody>
        </x-ui.table>
        <div class="card-footer">{{ $this->transfers->links() }}</div>
    </div>
</div>
