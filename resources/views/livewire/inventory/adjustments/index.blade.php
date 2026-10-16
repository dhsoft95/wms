<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">Stock Adjustments</h2>
            <p class="page-sub">Correct system balances after counts, damages or found stock.</p>
        </div>
        @can('create', \App\Models\StockAdjustment::class)
            <a href="{{ route('inventory.adjustments.create') }}" wire:navigate class="btn btn-primary">
                <x-ui.icon name="plus" class="w-4 h-4" /> New Adjustment
            </a>
        @endcan
    </div>

    <div class="card">
        <div class="card-body">
            <div class="flex gap-3 flex-wrap">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search number…" class="input w-56">
                <select wire:model.live="status" class="input w-44">
                    <option value="">All statuses</option>
                    @foreach(\App\Enums\AdjustmentStatus::cases() as $s)
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
                    <th>Lines</th>
                    <th>Created By</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($adjustments as $adj)
                    <tr wire:key="adj-{{ $adj->id }}">
                        <td>
                            <a href="{{ route('inventory.adjustments.show', $adj) }}" wire:navigate
                               class="mono font-semibold text-simba-600 dark:text-simba-400 hover:underline">
                                {{ $adj->number }}
                            </a>
                        </td>
                        <td class="text-sm">{{ $adj->virtualWarehouse?->name ?? '—' }}</td>
                        <td class="tabular-nums text-slate-500">{{ $adj->lines_count }}</td>
                        <td class="text-sm">{{ $adj->createdBy?->name ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $adj->status->badge() }}">
                                <span class="dot"></span>{{ $adj->status->label() }}
                            </span>
                        </td>
                        <td class="text-meta tabular-nums">{{ $adj->created_at->format('d M Y') }}</td>
                        <td class="text-right">
                            <a href="{{ route('inventory.adjustments.show', $adj) }}" wire:navigate class="btn btn-sm btn-ghost">
                                <x-ui.icon name="eye" class="w-3.5 h-3.5" />
                            </a>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-state title="No adjustments found." :cols="7" />
                @endforelse
            </tbody>
        </x-ui.table>
        <div class="card-footer">{{ $adjustments->links() }}</div>
    </div>
</div>
