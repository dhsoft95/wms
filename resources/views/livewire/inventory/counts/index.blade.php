<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">Cycle Counts</h2>
            <p class="page-sub">Blind count bins and items, then post variances as adjustments.</p>
        </div>
        @can('create', \App\Models\CycleCount::class)
            <a href="{{ route('inventory.counts.create') }}" wire:navigate class="btn btn-primary">
                <x-ui.icon name="plus" class="w-4 h-4" /> New Count
            </a>
        @endcan
    </div>

    <div class="card">
        <div class="card-body">
            <div class="flex gap-3 flex-wrap">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search number…" class="input w-56">
                <select wire:model.live="status" class="input w-44">
                    <option value="">All statuses</option>
                    @foreach(\App\Enums\CycleCountStatus::cases() as $s)
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
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($counts as $count)
                    <tr wire:key="cnt-{{ $count->id }}">
                        <td>
                            <a href="{{ route('inventory.counts.show', $count) }}" wire:navigate
                               class="mono font-semibold text-simba-600 dark:text-simba-400 hover:underline">
                                {{ $count->number }}
                            </a>
                        </td>
                        <td class="text-sm">{{ $count->virtualWarehouse?->name ?? '—' }}</td>
                        <td class="tabular-nums text-slate-500">{{ $count->lines_count }}</td>
                        <td class="text-sm">{{ $count->createdBy?->name ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $count->status->badge() }}">
                                <span class="dot"></span>{{ $count->status->label() }}
                            </span>
                        </td>
                        <td class="text-meta tabular-nums">{{ $count->created_at->format('d M Y') }}</td>
                        <td class="text-right">
                            <a href="{{ route('inventory.counts.show', $count) }}" wire:navigate class="btn btn-sm btn-ghost">
                                <x-ui.icon name="eye" class="w-3.5 h-3.5" />
                            </a>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-state title="No cycle counts found." :cols="7" />
                @endforelse
            </tbody>
        </x-ui.table>
        <div class="card-footer">{{ $counts->links() }}</div>
    </div>
</div>
