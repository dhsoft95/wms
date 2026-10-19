<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">Internal Movements</h2>
            <p class="page-sub">Bin-to-bin stock moves within the same virtual warehouse.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="flex gap-3 flex-wrap">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search number…" class="input w-56">
                <select wire:model.live="status" class="input w-44">
                    <option value="">All statuses</option>
                    @foreach(\App\Enums\MovementStatus::cases() as $s)
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
                @forelse($movements as $movement)
                    <tr wire:key="mov-{{ $movement->id }}">
                        <td>
                            <a href="{{ route('warehouse.movements.show', $movement) }}" wire:navigate
                               class="mono font-semibold text-simba-600 dark:text-simba-400 hover:underline">
                                {{ $movement->number }}
                            </a>
                        </td>
                        <td class="text-sm">{{ $movement->virtualWarehouse?->name ?? '—' }}</td>
                        <td class="tabular-nums text-slate-500">{{ $movement->lines_count }}</td>
                        <td class="text-sm">{{ $movement->createdBy?->name ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $movement->status->badge() }}">
                                <span class="dot"></span>{{ $movement->status->label() }}
                            </span>
                        </td>
                        <td class="text-meta tabular-nums">{{ $movement->created_at->format('d M Y') }}</td>
                        <td class="text-right">
                            <a href="{{ route('warehouse.movements.show', $movement) }}" wire:navigate
                               class="btn btn-sm btn-ghost">
                                <x-ui.icon name="eye" class="w-3.5 h-3.5" />
                            </a>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-state title="No movements found." :cols="7" />
                @endforelse
            </tbody>
        </x-ui.table>
        <div class="card-footer">{{ $movements->links() }}</div>
    </div>
</div>
