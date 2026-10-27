<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">Quarantine</h2>
            <p class="page-sub">Stock held in quarantine locations or on-hold status.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search SKU…" class="input w-56">
        </div>
    </div>

    <div class="card">
        <x-ui.table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Location</th>
                    <th>Virtual Warehouse</th>
                    <th>Batch</th>
                    <th class="text-right">On Hand</th>
                    <th>Reason</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($balances as $balance)
                    <tr wire:key="qtn-{{ $balance->id }}">
                        <td>
                            <div class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $balance->item?->sku }}</div>
                            <div class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $balance->item?->name }}</div>
                        </td>
                        <td class="mono text-sm">{{ $balance->location?->code }}</td>
                        <td class="text-sm">{{ $balance->virtualWarehouse?->name ?? '—' }}</td>
                        <td class="mono text-sm">{{ $balance->batch?->batch_no ?? '—' }}</td>
                        <td class="text-right tabular-nums font-semibold">{{ number_format((float)$balance->qty_on_hand, 4) }}</td>
                        <td>
                            @if($balance->stock_status === \App\Enums\Inventory\StockStatus::OnHold)
                                <span class="badge badge-amber"><span class="dot"></span>On Hold</span>
                            @else
                                <span class="badge badge-red"><span class="dot"></span>Quarantine</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @can('quarantine.manage')
                                <button wire:click="openRelease({{ $balance->id }})" class="btn btn-sm btn-secondary">
                                    Release
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-state title="No quarantined stock." :cols="7" />
                @endforelse
            </tbody>
        </x-ui.table>
        <div class="card-footer">{{ $balances->links() }}</div>
    </div>

    {{-- Release Modal --}}
    @if($showReleaseModal)
        @php $releaseBalance = $balances->firstWhere('id', $releaseBalanceId); @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             x-data x-on:keydown.escape.window="$wire.showReleaseModal = false">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-[2px]"
                 wire:click="$set('showReleaseModal', false)"></div>
            <div class="relative z-10 w-full max-w-lg rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl ring-1 ring-black/[0.06] dark:ring-white/[0.08]">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-white/[0.08]">
                    <h3 class="text-[15px] font-semibold text-slate-900 dark:text-white">Release from Quarantine</h3>
                    <button type="button" wire:click="$set('showReleaseModal', false)" class="icon-btn text-slate-400 hover:text-slate-700">
                        <x-ui.icon name="x" class="h-4 w-4" />
                    </button>
                </div>
                @if($releaseBalance)
                    <div class="px-6 py-3 bg-slate-50 dark:bg-white/[0.03] border-b border-slate-100 dark:border-white/[0.08]">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-mono text-xs text-slate-400">{{ $releaseBalance->item?->sku }}</p>
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $releaseBalance->item?->name }}</p>
                                <p class="text-xs text-slate-500 mt-0.5">{{ $releaseBalance->location?->code }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-xs text-slate-400">On Hand</p>
                                <p class="tabular-nums font-bold text-slate-800 dark:text-slate-200">{{ number_format((float)$releaseBalance->qty_on_hand, 4) }}</p>
                            </div>
                        </div>
                    </div>
                @endif
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <label class="label">Destination Bin <span class="required-mark">*</span></label>
                        <select wire:model="releaseBinId" class="input @error('releaseBinId') border-red-400 @enderror">
                            <option value="">Select bin…</option>
                            @foreach($binLocations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->code }}</option>
                            @endforeach
                        </select>
                        @error('releaseBinId') <p class="hint hint-error">{{ $message }}</p> @enderror
                    </div>

                    @if($isSerialTracked)
                        {{-- Serial number selection --}}
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="label mb-0">Serial Numbers <span class="required-mark">*</span></label>
                                <span class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ count($selectedSerials) }} of {{ count($availableSerials) }} selected
                                </span>
                            </div>
                            @error('selectedSerials') <p class="hint hint-error mb-1">{{ $message }}</p> @enderror

                            @if(count($availableSerials) > 0)
                                <div class="flex items-center gap-2 mb-2">
                                    <button type="button"
                                        wire:click="$set('selectedSerials', @js($availableSerials))"
                                        class="text-xs text-simba-600 dark:text-simba-400 hover:underline">
                                        Select all
                                    </button>
                                    <span class="text-slate-300 dark:text-slate-600">·</span>
                                    <button type="button"
                                        wire:click="$set('selectedSerials', [])"
                                        class="text-xs text-slate-500 hover:underline">
                                        Clear
                                    </button>
                                </div>
                                <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800" style="max-height: 220px;">
                                    @foreach($availableSerials as $sn)
                                        <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 dark:hover:bg-white/[0.04] cursor-pointer">
                                            <input type="checkbox"
                                                   wire:model.live="selectedSerials"
                                                   value="{{ $sn }}"
                                                   class="rounded border-slate-300 dark:border-slate-600 text-simba-600 focus:ring-simba-500">
                                            <span class="font-mono text-xs text-slate-800 dark:text-slate-200">{{ $sn }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-slate-400 italic">No serial numbers found in quarantine for this balance.</p>
                            @endif
                        </div>

                        {{-- Auto-filled qty indicator --}}
                        @if(count($selectedSerials) > 0)
                            <div class="flex items-center justify-between px-3 py-2 rounded-lg bg-slate-50 dark:bg-white/[0.04] text-xs">
                                <span class="text-slate-500 dark:text-slate-400">Qty to release</span>
                                <span class="font-bold tabular-nums text-slate-800 dark:text-slate-200">{{ count($selectedSerials) }}</span>
                            </div>
                        @endif
                    @else
                        <div>
                            <label class="label">Quantity to Release <span class="required-mark">*</span></label>
                            <input wire:model="releaseQty" type="number" step="0.0001" min="0.0001"
                                   class="input mono @error('releaseQty') border-red-400 @enderror"
                                   placeholder="0.0000">
                            @error('releaseQty') <p class="hint hint-error">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>
                <div class="flex justify-end gap-3 px-6 py-4 bg-slate-50/80 dark:bg-white/[0.02] border-t border-slate-100 dark:border-white/[0.08] rounded-b-2xl">
                    <button wire:click="$set('showReleaseModal', false)" class="btn btn-secondary">Cancel</button>
                    <button wire:click="release" wire:loading.attr="disabled" class="btn btn-primary">
                        <span wire:loading.remove wire:target="release">Release Stock</span>
                        <span wire:loading wire:target="release">Releasing…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
