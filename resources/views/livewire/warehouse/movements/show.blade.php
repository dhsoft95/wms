<div class="space-y-6">
    <div class="page-header">
        <div>
            <a href="{{ route('warehouse.movements.index') }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-simba-600 mb-2">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Back to Movements
            </a>
            <h2 class="page-title font-mono">{{ $movement->number }}</h2>
            <div class="flex items-center gap-3 mt-1">
                <span class="badge {{ $movement->status->badge() }}"><span class="dot"></span>{{ $movement->status->label() }}</span>
            </div>
        </div>
        <div class="flex gap-2 flex-wrap">
            @if(in_array($movement->status, [\App\Enums\MovementStatus::Draft, \App\Enums\MovementStatus::Submitted]))
                @can('execute', $movement)
                    <button wire:click="execute" wire:loading.attr="disabled" wire:confirm="Execute this movement? Stock will be moved and this cannot be undone." class="btn btn-success">
                        <span wire:loading.remove wire:target="execute"><x-ui.icon name="check" class="w-4 h-4" /> Execute Movement</span>
                        <span wire:loading wire:target="execute">Executing…</span>
                    </button>
                @endcan
                @can('cancel', $movement)
                    <button wire:click="cancel" wire:loading.attr="disabled" wire:confirm="Cancel this movement?" class="btn btn-secondary text-red-600 dark:text-red-400">
                        <x-ui.icon name="x" class="w-4 h-4" /> Cancel
                    </button>
                @endcan
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Lines --}}
        <div class="card lg:col-span-2">
            <div class="card-header"><h3 class="card-title">Lines</h3></div>
            <x-ui.table>
                <thead>
                    <tr>
                        <th class="w-8">#</th>
                        <th>Item</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Batch</th>
                        <th class="text-right">Qty</th>
                        <th>UoM</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movement->lines as $line)
                        <tr wire:key="mov-line-{{ $line->id }}">
                            <td class="tabular-nums text-slate-400 text-xs">{{ $loop->iteration }}</td>
                            <td>
                                <div class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $line->item?->sku }}</div>
                                <div class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $line->item?->name }}</div>
                            </td>
                            <td class="mono text-sm">{{ $line->fromLocation?->code }}</td>
                            <td class="mono text-sm">{{ $line->toLocation?->code }}</td>
                            <td class="mono text-sm">
                                {{ $line->batch_no ?? '—' }}
                                @if($line->expiry_date)
                                    <span class="text-xs text-slate-400">({{ $line->expiry_date->format('d M Y') }})</span>
                                @endif
                            </td>
                            <td class="text-right tabular-nums font-semibold">{{ number_format((float)$line->qty, 4) }}</td>
                            <td class="mono text-sm text-slate-500">{{ $line->uom?->code }}</td>
                        </tr>
                        @if($line->serials->isNotEmpty())
                            <tr wire:key="mov-line-serials-{{ $line->id }}">
                                <td></td>
                                <td colspan="6" class="pb-3">
                                    <div class="flex flex-wrap gap-1 mt-0.5">
                                        @foreach($line->serials as $serial)
                                            <span class="badge badge-slate mono text-xs">{{ $serial->serial_number }}</span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <x-ui.empty-state title="No lines." :cols="7" />
                    @endforelse
                </tbody>
            </x-ui.table>
        </div>

        {{-- Details --}}
        <div class="space-y-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Details</h3></div>
                <div class="card-body divide-y divide-slate-100 dark:divide-slate-800">
                    <div class="py-2 first:pt-0 last:pb-0">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Virtual Warehouse</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $movement->virtualWarehouse?->name ?? '—' }}</p>
                    </div>
                    <div class="py-2 first:pt-0 last:pb-0">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Created By</p>
                        <p class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $movement->createdBy?->name ?? '—' }}</p>
                    </div>
                    <div class="py-2 first:pt-0 last:pb-0">
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Created</p>
                        <p class="mt-0.5 text-sm tabular-nums text-slate-800 dark:text-slate-200">{{ $movement->created_at->format('d M Y H:i') }}</p>
                    </div>
                    @if($movement->posted_at)
                        <div class="py-2 first:pt-0 last:pb-0">
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Posted</p>
                            <p class="mt-0.5 text-sm tabular-nums text-slate-800 dark:text-slate-200">{{ $movement->posted_at->format('d M Y H:i') }}</p>
                        </div>
                    @endif
                    @if($movement->notes)
                        <div class="py-2 first:pt-0 last:pb-0">
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Notes</p>
                            <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-400">{{ $movement->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
