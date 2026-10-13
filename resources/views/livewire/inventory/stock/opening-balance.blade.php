<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <a href="{{ route('inventory.movements.index') }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-simba-600 mb-2">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Back to Movements
            </a>
            <h2 class="page-title">Opening Balance</h2>
            <p class="page-sub">Post initial stock quantities to seed the ledger. This action is permanent.</p>
        </div>
    </div>

    {{-- ── FORM MODE ─────────────────────────────────────────────────────────── --}}
    @if (! $previewing)

        <div class="space-y-6">

            {{-- Notes --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <x-ui.icon name="pencil" class="w-4 h-4" /> Notes
                    </h3>
                </div>
                <div class="card-body">
                    <textarea
                        wire:model="notes"
                        rows="2"
                        placeholder="Optional notes for this opening balance posting…"
                        class="input w-full resize-none"
                    ></textarea>
                </div>
            </div>

            {{-- Lines Table --}}
            <div class="card overflow-hidden">
                <div class="card-header">
                    <h3 class="card-title">
                        <x-ui.icon name="package" class="w-4 h-4" /> Stock Lines
                        <span class="ml-2 text-sm font-normal text-slate-400 dark:text-slate-500">{{ count($lines) }} line(s)</span>
                    </h3>
                </div>

                <x-ui.table class="min-w-[1200px]">
                        <thead>
                            <tr>
                                <th class="px-4 py-3">#</th>
                                <th class="px-4 py-3">Item <span class="text-red-500">*</span></th>
                                <th class="px-4 py-3">Batch No</th>
                                <th class="px-4 py-3">Expiry Date</th>
                                <th class="px-4 py-3">Location <span class="text-red-500">*</span></th>
                                <th class="px-4 py-3">Virtual Warehouse <span class="text-red-500">*</span></th>
                                <th class="px-4 py-3">Status <span class="text-red-500">*</span></th>
                                <th class="px-4 py-3">Qty <span class="text-red-500">*</span></th>
                                <th class="px-4 py-3">UoM <span class="text-red-500">*</span></th>
                                <th class="px-4 py-3 w-12 sr-only">Remove</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($lines as $i => $line)
                            @php
                                $lineItem = $line['item_id'] ? $this->items->firstWhere('id', (int) $line['item_id']) : null;
                                $isSerial = $lineItem?->track_serial ?? false;
                            @endphp
                            <tr wire:key="line-{{ $i }}"
                                class="transition hover:bg-white/30 dark:hover:bg-white/[0.04] border-b {{ $isSerial ? '' : 'border-white/50 dark:border-white/[0.06]' }}">

                                {{-- Row number --}}
                                <td class="px-4 py-3 text-xs text-slate-400 dark:text-slate-500 tabular-nums w-10">
                                    {{ $i + 1 }}
                                </td>

                                {{-- Item --}}
                                <td class="px-4 py-3 min-w-[200px]">
                                    <select wire:model.live="lines.{{ $i }}.item_id"
                                            class="input w-full @error('lines.' . $i . '.item_id') border-red-400 @enderror">
                                        <option value="">Select item…</option>
                                        @foreach ($this->items as $item)
                                            <option value="{{ $item->id }}">{{ $item->sku }} — {{ $item->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('lines.' . $i . '.item_id')
                                        <p class="hint !text-red-600 dark:!text-red-400 mt-1">{{ $message }}</p>
                                    @enderror
                                </td>

                                {{-- Batch No --}}
                                <td class="px-4 py-3 min-w-[120px]">
                                    <input wire:model="lines.{{ $i }}.batch_no"
                                           type="text"
                                           placeholder="e.g. BAT-001"
                                           class="input w-full font-mono" />
                                </td>

                                {{-- Expiry Date --}}
                                <td class="px-4 py-3 min-w-[140px]">
                                    <input wire:model="lines.{{ $i }}.expiry_date"
                                           type="date"
                                           class="input w-full tabular-nums" />
                                </td>

                                {{-- Location --}}
                                <td class="px-4 py-3 min-w-[160px]">
                                    <select wire:model="lines.{{ $i }}.location_id"
                                            class="input w-full @error('lines.' . $i . '.location_id') border-red-400 @enderror">
                                        <option value="">Select location…</option>
                                        @foreach ($this->locations as $loc)
                                            <option value="{{ $loc->id }}">{{ $loc->code }}</option>
                                        @endforeach
                                    </select>
                                    @error('lines.' . $i . '.location_id')
                                        <p class="hint !text-red-600 dark:!text-red-400 mt-1">{{ $message }}</p>
                                    @enderror
                                </td>

                                {{-- Virtual Warehouse --}}
                                <td class="px-4 py-3 min-w-[160px]">
                                    <select wire:model="lines.{{ $i }}.vw_id"
                                            class="input w-full @error('lines.' . $i . '.vw_id') border-red-400 @enderror">
                                        <option value="">Select warehouse…</option>
                                        @foreach ($this->virtualWarehouses as $vw)
                                            <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('lines.' . $i . '.vw_id')
                                        <p class="hint !text-red-600 dark:!text-red-400 mt-1">{{ $message }}</p>
                                    @enderror
                                </td>

                                {{-- Status --}}
                                <td class="px-4 py-3 min-w-[120px]">
                                    <select wire:model="lines.{{ $i }}.stock_status"
                                            class="input w-full @error('lines.' . $i . '.stock_status') border-red-400 @enderror">
                                        <option value="available">Available</option>
                                        <option value="on_hold">On Hold</option>
                                    </select>
                                </td>

                                {{-- Qty --}}
                                <td class="px-4 py-3 min-w-[100px]">
                                    <input wire:model.live="lines.{{ $i }}.qty"
                                           type="number"
                                           min="0.0001"
                                           step="0.0001"
                                           placeholder="0.00"
                                           class="input w-full tabular-nums @error('lines.' . $i . '.qty') border-red-400 @enderror" />
                                    @error('lines.' . $i . '.qty')
                                        <p class="hint !text-red-600 dark:!text-red-400 mt-1">{{ $message }}</p>
                                    @enderror
                                </td>

                                {{-- UoM --}}
                                <td class="px-4 py-3 min-w-[100px]">
                                    <select wire:model="lines.{{ $i }}.uom_id"
                                            class="input w-full @error('lines.' . $i . '.uom_id') border-red-400 @enderror">
                                        <option value="">UoM…</option>
                                        @foreach ($this->uoms as $uom)
                                            <option value="{{ $uom->id }}">{{ $uom->code }}</option>
                                        @endforeach
                                    </select>
                                    @error('lines.' . $i . '.uom_id')
                                        <p class="hint !text-red-600 dark:!text-red-400 mt-1">{{ $message }}</p>
                                    @enderror
                                </td>

                                {{-- Remove --}}
                                <td class="px-4 py-3 w-12">
                                    <button type="button"
                                            wire:click="removeLine({{ $i }})"
                                            class="flex h-7 w-7 items-center justify-center rounded text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                                        <x-ui.icon name="x" class="h-4 w-4" />
                                    </button>
                                </td>

                            </tr>

                            {{-- Serials expansion row (only for serial-tracked items) --}}
                            @if ($isSerial)
                                @php
                                    $targetQty  = (int) max(0, round((float) ($line['qty'] ?? 0)));
                                    $enteredSns = array_values(array_filter(
                                        array_map('trim', explode("\n", str_replace("\r", '', $line['serials'] ?? '')))
                                    ));
                                    $snCount    = count($enteredSns);
                                    $snUnique   = count(array_unique(array_map('strtoupper', $enteredSns)));
                                    $hasDupes   = $snCount !== $snUnique;
                                    $matched    = ! $hasDupes && $targetQty > 0 && $snCount === $targetQty;
                                    $counterCls = $matched
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : ($snCount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400');
                                @endphp
                                <tr wire:key="serials-{{ $i }}" class="border-b border-white/50 dark:border-white/[0.06] bg-slate-50/60 dark:bg-white/[0.02]">
                                    <td class="px-4 py-3 text-xs text-slate-400 w-10"></td>
                                    <td colspan="8" class="px-4 py-3">
                                        <div class="flex items-start gap-4">
                                            <div class="flex-1">
                                                <label class="label text-xs mb-1">
                                                    Serial Numbers <span class="text-slate-400">(one per line, paste or scan)</span>
                                                </label>
                                                <textarea
                                                    wire:model="lines.{{ $i }}.serials"
                                                    rows="4"
                                                    placeholder="RTR-0001&#10;RTR-0002&#10;RTR-0003"
                                                    class="input w-full font-mono text-xs resize-y @if ($hasDupes) border-red-400 @endif"
                                                ></textarea>
                                                @if ($hasDupes)
                                                    <p class="hint !text-red-600 dark:!text-red-400 mt-1">Duplicate serial number detected.</p>
                                                @endif
                                            </div>
                                            <div class="shrink-0 pt-6 text-right min-w-[120px]">
                                                <p class="text-xs {{ $counterCls }} font-semibold tabular-nums">
                                                    {{ $snCount }} of {{ $targetQty }} serials entered
                                                </p>
                                                @if ($targetQty > 0 && ! $matched && ! $hasDupes)
                                                    <p class="text-xs text-amber-500 dark:text-amber-400 mt-0.5">
                                                        Need {{ $targetQty - $snCount > 0 ? '+' . ($targetQty - $snCount) : abs($targetQty - $snCount) . ' extra' }}
                                                    </p>
                                                @endif
                                                @if ($matched)
                                                    <p class="text-xs text-emerald-500 dark:text-emerald-400 mt-0.5">✓ Count matches</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 w-12"></td>
                                </tr>
                            @endif
                        @endforeach
                        </tbody>
                </x-ui.table>

                {{-- Add Line --}}
                <div class="border-t border-white/40 dark:border-white/[0.08] px-4 py-3">
                    <button type="button" wire:click="addLine"
                            class="btn btn-secondary btn-sm">
                        <x-ui.icon name="plus" class="w-3.5 h-3.5" /> Add Line
                    </button>
                </div>
            </div>

            {{-- Form Actions --}}
            <div class="flex items-center justify-between">
                <button type="button"
                        wire:click="$set('lines', []); $call('addLine')"
                        wire:confirm="Clear all lines?"
                        class="btn btn-secondary">
                    Clear
                </button>
                <button type="button"
                        wire:click="preview"
                        wire:loading.attr="disabled"
                        class="btn btn-primary">
                    <span wire:loading.remove wire:target="preview">
                        <x-ui.icon name="eye" class="w-4 h-4" /> Preview &amp; Confirm
                    </span>
                    <span wire:loading wire:target="preview">Validating…</span>
                </button>
            </div>

        </div>

    {{-- ── PREVIEW MODE ──────────────────────────────────────────────────────── --}}
    @else

        <div class="space-y-6">

            {{-- Warning Banner --}}
            <div class="rounded-xl border border-amber-200 dark:border-amber-700/40
                        bg-amber-50 dark:bg-amber-900/20 px-5 py-4 flex items-start gap-3">
                <x-ui.icon name="shield" class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                <div>
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">This posting is irreversible</p>
                    <p class="mt-0.5 text-sm text-amber-700 dark:text-amber-400">
                        Once posted, all lines will be applied to the stock ledger as an Opening Balance transaction.
                        Reversals are only possible via a separate Reversal transaction (System Admin only).
                    </p>
                </div>
            </div>

            {{-- Notes preview --}}
            @if ($notes)
                <div class="card p-4">
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Notes</p>
                    <p class="text-sm text-slate-700 dark:text-slate-300">{{ $notes }}</p>
                </div>
            @endif

            {{-- Preview Table --}}
            <div class="card overflow-hidden">
                <div class="card-header">
                    <h3 class="card-title">
                        <x-ui.icon name="package" class="w-4 h-4" /> Review Lines
                        <span class="ml-2 text-sm font-normal text-slate-400 dark:text-slate-500">{{ count($lines) }} line(s)</span>
                    </h3>
                </div>

                <x-ui.table class="min-w-[1000px]">
                        <thead>
                            <tr>
                                <th class="px-4 py-3">#</th>
                                <th class="px-4 py-3">Item</th>
                                <th class="px-4 py-3">Batch No</th>
                                <th class="px-4 py-3">Expiry</th>
                                <th class="px-4 py-3">Location</th>
                                <th class="px-4 py-3">Warehouse</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Qty</th>
                                <th class="px-4 py-3">UoM</th>
                                <th class="px-4 py-3">Serials</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/50 dark:divide-white/[0.06]">
                        @foreach ($lines as $i => $line)
                            @php
                                $previewItem     = $this->items->find($line['item_id']);
                                $previewLocation = $this->locations->find($line['location_id']);
                                $previewVw       = $this->virtualWarehouses->find($line['vw_id']);
                                $previewUom      = $this->uoms->find($line['uom_id']);
                                $previewSerials  = array_values(array_filter(
                                    array_map('trim', explode("\n", str_replace("\r", '', $line['serials'] ?? '')))
                                ));
                            @endphp
                            <tr wire:key="preview-{{ $i }}"
                                class="transition hover:bg-white/30 dark:hover:bg-white/[0.04]">
                                <td class="px-4 py-3.5 text-xs text-slate-400 dark:text-slate-500 tabular-nums w-10">
                                    {{ $i + 1 }}
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="font-medium text-slate-900 dark:text-slate-100">{{ $previewItem?->name ?? '—' }}</div>
                                    <div class="mono text-xs text-slate-400 dark:text-slate-500">{{ $previewItem?->sku }}</div>
                                </td>
                                <td class="px-4 py-3.5 mono text-xs text-slate-700 dark:text-slate-300">
                                    {{ $line['batch_no'] ?: '—' }}
                                </td>
                                <td class="px-4 py-3.5 text-sm tabular-nums text-slate-600 dark:text-slate-400">
                                    {{ $line['expiry_date'] ?: '—' }}
                                </td>
                                <td class="px-4 py-3.5 mono text-xs text-slate-700 dark:text-slate-300">
                                    {{ $previewLocation?->code ?? '—' }}
                                </td>
                                <td class="px-4 py-3.5 text-sm text-slate-600 dark:text-slate-400">
                                    {{ $previewVw?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3.5">
                                    @if ($line['stock_status'] === 'on_hold')
                                        <span class="badge badge-amber">On Hold</span>
                                    @else
                                        <span class="badge badge-green">Available</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-right tabular-nums font-semibold text-slate-900 dark:text-slate-100">
                                    {{ number_format((float) $line['qty'], 4) }}
                                </td>
                                <td class="px-4 py-3.5">
                                    @if ($previewUom)
                                        <span class="badge badge-slate font-mono">{{ $previewUom->code }}</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-sm text-slate-600 dark:text-slate-400">
                                    @if ($previewItem?->track_serial)
                                        <span class="{{ count($previewSerials) === (int) round((float) $line['qty']) ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }} font-medium tabular-nums">
                                            {{ count($previewSerials) }} serial(s)
                                        </span>
                                    @else
                                        <span class="text-slate-300 dark:text-slate-600">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="border-t border-white/50 dark:border-white/[0.07]
                                       bg-white/[0.45] dark:bg-white/[0.04]">
                            <tr>
                                <td colspan="7" class="px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-300">
                                    Total Lines
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums font-bold text-slate-900 dark:text-white">
                                    {{ count($lines) }}
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                </x-ui.table>
            </div>

            {{-- Preview Actions --}}
            <div class="flex items-center justify-between">
                <button type="button"
                        wire:click="cancelPreview"
                        class="btn btn-secondary">
                    <x-ui.icon name="pencil" class="w-4 h-4" /> Edit Lines
                </button>
                <button type="button"
                        wire:click="post"
                        wire:loading.attr="disabled"
                        class="btn btn-primary">
                    <span wire:loading.remove wire:target="post">
                        <x-ui.icon name="check" class="w-4 h-4" /> Post Opening Balance
                    </span>
                    <span wire:loading wire:target="post">Posting…</span>
                </button>
            </div>

        </div>

    @endif

</div>
