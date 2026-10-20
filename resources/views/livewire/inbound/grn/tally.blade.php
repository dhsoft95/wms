<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <a href="{{ route('inbound.orders.show', $grn->purchaseOrder) }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-simba-600 mb-2">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Back to {{ $grn->purchaseOrder?->number }}
            </a>
            <h2 class="page-title">Goods Receipt Entry</h2>
            <div class="flex flex-wrap items-center gap-3 mt-1">
                <span class="badge badge-slate">
                    <span class="dot"></span>Draft (unconfirmed)
                </span>
                <span class="text-xs text-slate-500 dark:text-slate-400">
                    Dock: <span class="font-mono font-medium">{{ $grn->receivingDock?->code ?? '—' }}</span>
                </span>
                @if ($grn->vehicle_plate)
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                        Vehicle: <span class="font-mono font-medium">{{ $grn->vehicle_plate }}</span>
                    </span>
                @endif
            </div>
        </div>

        @if ($this->hasReceipts)
            <button wire:click="completeReceiving"
                    wire:confirm="Complete receiving? Stock will be posted and a GRN number assigned. This cannot be undone."
                    wire:loading.attr="disabled"
                    class="btn btn-primary">
                <span wire:loading.remove wire:target="completeReceiving">
                    <x-ui.icon name="check" class="w-4 h-4" /> Complete Receiving
                </span>
                <span wire:loading wire:target="completeReceiving">Posting stock…</span>
            </button>
        @else
            <div class="flex items-center gap-2 text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 rounded-lg px-3 py-2">
                <x-ui.icon name="exclamation-triangle" class="w-4 h-4 shrink-0" />
                Select a line on the left and record at least one receipt quantity before completing.
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Lines panel --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Order Lines</h3>
                <span class="ml-auto text-xs text-slate-500">Click a line to enter quantities</span>
            </div>
            <x-ui.table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-right">Expected</th>
                        <th class="text-right">Received</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->lines as $line)
                        @php
                            $received = ($line->received_good_qty ?? 0) + ($line->received_damaged_qty ?? 0);
                            $expected = (float) ($line->expected_qty ?? 0);
                            $pct      = $expected > 0 ? min(100, round($received / $expected * 100)) : ($received > 0 ? 100 : 0);
                            $isActive = $activeLine === $line->id;
                        @endphp
                        <tr wire:key="line-{{ $line->id }}"
                            wire:click="selectLine({{ $line->id }})"
                            @class([
                                'cursor-pointer hover:bg-slate-50 dark:hover:bg-white/[0.03]',
                                'ring-2 ring-inset ring-simba-500 bg-simba-50/30 dark:bg-simba-900/10' => $isActive,
                            ])>
                            <td>
                                <div class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $line->item?->sku }}</div>
                                <div class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $line->item?->name }}</div>
                                @if ($line->item?->part_number)
                                    <div class="font-mono text-xs text-slate-400">{{ $line->item->part_number }}</div>
                                @endif
                            </td>
                            <td class="tabular-nums text-right">
                                {{ $expected > 0 ? number_format($expected, 2) : '—' }}
                                <div class="text-xs text-slate-400">{{ $line->uom?->code }}</div>
                            </td>
                            <td class="tabular-nums text-right">
                                <span @class([
                                    'font-medium',
                                    'text-emerald-600 dark:text-emerald-400' => $pct >= 100,
                                    'text-simba-600 dark:text-simba-400'     => $pct > 0 && $pct < 100,
                                    'text-slate-400'                         => $pct === 0,
                                ])>{{ number_format((float) $received, 2) }}</span>
                                <div class="text-xs text-slate-400">{{ $pct }}%</div>
                            </td>
                            <td>
                                @if ($isActive)
                                    <x-ui.icon name="chevron-right" class="w-4 h-4 text-simba-600" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state colspan="4" message="No lines on this GRN." />
                    @endforelse
                </tbody>
            </x-ui.table>
        </div>

        {{-- Receipt entry panel --}}
        <div class="space-y-4">

            @if ($activeLine && $this->activeTallyLine)
                @php $tallyLine = $this->activeTallyLine; @endphp
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Enter Receipt</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                {{ $tallyLine->item?->sku }} — {{ $tallyLine->item?->name }}
                            </p>
                        </div>
                    </div>
                    <div class="card-body space-y-4">

                        {{-- Qty + UoM row --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="label">Quantity <span class="text-red-500">*</span></label>
                                <input wire:model.live="receiptQty" type="number" step="0.0001" min="0.0001"
                                       class="input font-mono" placeholder="0.0000" autofocus />
                                @error('receiptQty') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">UoM <span class="text-red-500">*</span></label>
                                <select wire:model="receiptUomId" class="input">
                                    <option value="">Select…</option>
                                    @foreach ($this->uomOptions as $u)
                                        <option value="{{ $u->id }}">{{ $u->code }}</option>

                                    @endforeach
                                </select>
                                @error('receiptUomId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        {{-- Condition --}}
                        <div class="space-y-3">
                            <div>
                                <label class="label">Condition <span class="text-red-500">*</span></label>
                                <div class="flex gap-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input wire:model.live="receiptCondition" type="radio" value="good" class="text-simba-600" />
                                        <span class="text-sm text-slate-700 dark:text-slate-200">Good</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input wire:model.live="receiptCondition" type="radio" value="damaged" class="text-amber-500" />
                                        <span class="text-sm text-slate-700 dark:text-slate-200">Damaged (→ quarantine)</span>
                                    </label>
                                </div>
                            </div>
                            @if ($receiptCondition === 'damaged')
                            <div>
                                <label class="label">
                                    Damage Remark <span class="text-red-500">*</span>
                                    <span class="text-xs text-slate-400 font-normal ml-1">Describe the damage</span>
                                </label>
                                <textarea wire:model="receiptDamageRemark" rows="2"
                                          class="input w-full resize-none @error('receiptDamageRemark') border-red-400 @enderror"
                                          placeholder="e.g. Crushed carton, water damage, torn packaging…"></textarea>
                                @error('receiptDamageRemark')
                                    <p class="hint !text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            @endif
                        </div>

                        @if ($tallyLine->item?->track_serial)
                            @php
                                $neededSerials = null;
                                if ($receiptQty !== '' && $receiptUomId !== '') {
                                    try {
                                        $neededSerials = (int) round((float) \App\Services\Foundation\UomConverter::toBase(
                                            $tallyLine->item,
                                            (int) $receiptUomId,
                                            $receiptQty,
                                        ));
                                    } catch (\Throwable) {}

                                }
                            @endphp
                            <div>
                                <label class="label">Serial Numbers <span class="text-red-500">*</span>
                                    @if ($neededSerials !== null)
                                        <span class="text-xs text-slate-400 font-normal">({{ $neededSerials }} required — one per line or comma-separated)</span>
                                    @else
                                        <span class="text-xs text-slate-400 font-normal">(one per line or comma-separated)</span>
                                    @endif
                                </label>
                                <textarea wire:model="receiptSerials" rows="4"
                                          class="input w-full font-mono text-xs resize-y @error('receiptSerials') border-red-400 @enderror"
                                          placeholder="SN-001&#10;SN-002&#10;SN-003"></textarea>
                                @error('receiptSerials') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        @if ($tallyLine->item?->track_batch)
                            <div>
                                <label class="label">Batch No <span class="text-xs text-slate-400">(auto-generated if blank)</span></label>
                                <input wire:model="receiptBatchNo" type="text" class="input font-mono"
                                       placeholder="Leave blank to auto-generate B-{date}-{seq}" />
                            </div>
                        @endif

                        @if ($this->itemRequiresExpiry || ($tallyLine->item?->track_expiry ?? false))
                            <div>
                                <label class="label">
                                    Expiry Date
                                    @if ($this->itemRequiresExpiry)
                                        <span class="text-red-500">*</span>
                                        <span class="text-xs text-amber-600 dark:text-amber-400 font-normal ml-1">Required  FEFO item</span>
                                    @endif
                                </label>
                                <input wire:model="receiptExpiry" type="date"
                                       class="input @error('receiptExpiry') border-red-400 @enderror" />
                                @error('receiptExpiry')
                                    <p class="hint !text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        <div class="flex justify-end pt-2 border-t border-slate-100 dark:border-slate-800">
                            <button wire:click="addReceipt" wire:loading.attr="disabled" class="btn btn-primary">
                                <span wire:loading.remove wire:target="addReceipt">
                                    <x-ui.icon name="plus" class="w-4 h-4" /> Add Receipt
                                </span>
                                <span wire:loading wire:target="addReceipt">Adding…</span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Receipts so far for this line --}}
                @if ($tallyLine->receipts->isNotEmpty())
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Receipts for This Line</h3>
                        </div>
                        <x-ui.table>
                            <thead>
                                <tr>
                                    <th class="text-right">Qty</th>
                                    <th>UoM</th>
                                    <th>Condition</th>
                                    <th>Batch / Serials</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tallyLine->receipts as $receipt)
                                    <tr wire:key="receipt-{{ $receipt->id }}">
                                        <td class="tabular-nums text-right font-mono">{{ number_format((float) $receipt->qty, 4) }}</td>
                                        <td class="font-mono text-xs">{{ $receipt->uom?->code }}</td>
                                        <td>
                                            <span @class([
                                                'badge',
                                                'badge-green' => $receipt->condition?->value === 'good' || $receipt->condition === 'good',
                                                'badge-amber' => $receipt->condition?->value !== 'good' && $receipt->condition !== 'good',
                                            ])>
                                                {{ ucfirst($receipt->condition?->value ?? $receipt->condition ?? '—') }}
                                            </span>
                                            @if ($receipt->damage_remark)
                                                <p class="text-xs text-amber-600 dark:text-amber-400 mt-0.5 max-w-[180px] truncate" title="{{ $receipt->damage_remark }}">
                                                    {{ $receipt->damage_remark }}
                                                </p>
                                            @endif
                                        </td>
                                        <td class="font-mono text-xs text-slate-500">
                                            {{ $receipt->batch_no ?? '—' }}
                                            @if (! empty($receipt->serial_nos))
                                                <div class="flex flex-wrap gap-1 mt-1">
                                                    @foreach ($receipt->serial_nos as $sn)
                                                        <span class="badge badge-slate text-xs font-mono">{{ $sn }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <button wire:click="removeReceipt({{ $receipt->id }})"
                                                    wire:confirm="Remove this receipt?"
                                                    class="icon-btn text-red-400 hover:text-red-600">
                                                <x-ui.icon name="trash" class="w-4 h-4" />
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-ui.table>
                    </div>
                @endif

            @else
                <div class="card">
                    <div class="card-body flex flex-col items-center justify-center gap-3 py-16 text-slate-400">
                        <x-ui.icon name="cursor-arrow-rays" class="w-10 h-10" />
                        <p class="text-sm">Select a line from the left to enter receipt quantities.</p>
                    </div>
                </div>
            @endif

        </div>
    </div>

</div>
