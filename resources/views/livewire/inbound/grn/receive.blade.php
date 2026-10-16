<div class="space-y-6">
    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <a href="{{ route('inbound.grn.show', $grn) }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-simba-600 mb-2">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Back to GRN
            </a>
            <h2 class="page-title font-mono">Receive: {{ $grn->number }}</h2>
            <div class="flex items-center gap-2 mt-1">
                <span class="badge {{ $grn->status->color() }}">
                    <span class="dot"></span>{{ $grn->status->label() }}
                </span>
                <span class="text-sm text-slate-500">{{ $grn->warehouse?->name }}</span>
            </div>
        </div>
        <button wire:click="finishReceiving"
                wire:confirm="Finish receiving and post stock? This cannot be undone."
                class="btn btn-primary">
            <x-ui.icon name="check" class="w-4 h-4" /> Finish Receiving
        </button>
    </div>

    {{-- Scan Input --}}
    <div class="card">
        <div class="card-body">
            <div class="flex gap-3 items-center">
                <x-ui.icon name="qr-code" class="w-6 h-6 text-slate-400 shrink-0" />
                <input
                    wire:model="scanInput"
                    wire:keydown.enter="processScan"
                    type="text"
                    placeholder="Scan barcode or type SKU then press Enter…"
                    autofocus
                    class="input flex-1 text-lg font-mono"
                    autocomplete="off"
                />
                <button wire:click="processScan" class="btn btn-secondary">Scan</button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Lines Panel --}}
        <div class="lg:col-span-2 card overflow-hidden">
            <div class="card-header">
                <h3 class="card-title">
                    <x-ui.icon name="list" class="w-4 h-4" />
                    GRN Lines
                </h3>
            </div>
            <x-ui.table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Expected</th>
                        <th>Received</th>
                        <th>Progress</th>
                        <th class="text-right">Select</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->lines as $line)
                        @php
                            $received = $line->received_good_qty + $line->received_damaged_qty;
                            $expected = $line->expected_qty ?? 0;
                            $pct      = $expected > 0 ? min(100, round($received / $expected * 100)) : ($received > 0 ? 100 : 0);
                            $isActive = $activeLine === $line->id;
                        @endphp
                        <tr wire:key="line-{{ $line->id }}" @class(['ring-2 ring-simba-500' => $isActive])>
                            <td>
                                <div class="font-mono text-xs text-simba-600 dark:text-simba-400">{{ $line->item?->sku }}</div>
                                <div class="text-sm">{{ $line->item?->name }}</div>
                                @if ($line->is_unexpected)
                                    <span class="badge badge-amber text-[10px]">Unexpected</span>
                                @endif
                            </td>
                            <td class="tabular-nums">{{ $expected > 0 ? number_format((float)$expected, 2) : '—' }}</td>
                            <td class="tabular-nums">{{ number_format((float)$received, 2) }}</td>
                            <td>
                                <div class="progress w-20">
                                    <div class="{{ $pct >= 100 ? 'bg-emerald-600' : 'bg-simba-500' }}" style="width:{{ $pct }}%"></div>
                                </div>
                                <span class="text-xs tabular-nums text-slate-400">{{ $pct }}%</span>
                            </td>
                            <td class="text-right">
                                <button wire:click="selectLine({{ $line->id }})"
                                        @class(['btn btn-sm', 'btn-primary' => $isActive, 'btn-secondary' => !$isActive])>
                                    {{ $isActive ? 'Active' : 'Select' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="!py-8 text-center text-slate-500">
                                No lines. Scan a barcode to add an unexpected line.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.table>
        </div>

        {{-- Receipt Entry Form --}}
        <div class="space-y-4">
            @if ($this->activeGrnLine)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <x-ui.icon name="plus" class="w-4 h-4" />
                        Record Receipt
                    </h3>
                </div>
                <div class="card-body space-y-4">
                    <div class="rounded-lg bg-slate-50 dark:bg-slate-800/50 px-3 py-2">
                        <p class="text-xs text-slate-500">Recording for:</p>
                        <p class="font-mono text-sm font-semibold text-simba-600 dark:text-simba-400">
                            {{ $this->activeGrnLine->item?->sku }}
                        </p>
                        <p class="text-sm">{{ $this->activeGrnLine->item?->name }}</p>
                    </div>

                    <div>
                        <label class="label">Qty <span class="text-red-500">*</span></label>
                        <input wire:model="receiptQty" type="number" step="0.0001" min="0.0001"
                               class="input @error('receiptQty') border-red-400 @enderror"
                               placeholder="0.0000" />
                        @error('receiptQty') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="label">Unit of Measure <span class="text-red-500">*</span></label>
                        <select wire:model="receiptUomId"
                                class="input @error('receiptUomId') border-red-400 @enderror">
                            <option value="">Select UoM…</option>
                            @foreach ($this->uomOptions as $uom)
                                <option value="{{ $uom->id }}">{{ $uom->code }}</option>
                            @endforeach
                        </select>
                        @error('receiptUomId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Serial Numbers --}}
                    @if ($this->activeGrnLine->item?->track_serial)
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="label mb-0">
                                Serial Numbers <span class="text-red-500">*</span>
                                <span class="text-slate-400 font-normal text-xs ml-1">(one per line, must match qty)</span>
                            </label>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer text-xs">
                            <input type="checkbox" wire:model.live="receiptSerialsMissing" class="rounded accent-amber-500">
                            <span class="text-amber-600 dark:text-amber-400 font-medium">Serials not available — enter later</span>
                        </label>
                        @if (! $receiptSerialsMissing)
                        <textarea wire:model="receiptSerials" rows="5"
                                  class="input font-mono text-xs @error('receiptSerials') border-red-400 @enderror"
                                  placeholder="SN-000001&#10;SN-000002&#10;SN-000003"></textarea>
                        @error('receiptSerials') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        @else
                        <p class="hint text-amber-600">Serials will be flagged as missing. You can enter them later from the GRN or Stock on Hand.</p>
                        @endif
                    </div>
                    @endif

                    {{-- Batch / Expiry --}}
                    @if ($this->activeGrnLine->item?->track_batch)
                    <div>
                        <label class="label">Batch No. <span class="text-red-500">*</span></label>
                        <input wire:model="receiptBatchNo" type="text"
                               class="input font-mono" placeholder="BATCH-001" />
                        @error('receiptBatchNo') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                    @endif

                    @if ($this->activeGrnLine->item?->track_expiry)
                    <div>
                        <label class="label">Expiry Date <span class="text-red-500">*</span></label>
                        <input wire:model="receiptExpiry" type="date" class="input" />
                        @error('receiptExpiry') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                    @endif

                    <div>
                        <label class="label">Condition <span class="text-red-500">*</span></label>
                        <div class="flex gap-4">
                            @foreach ($this->conditions as $cond)
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" wire:model="receiptCondition" value="{{ $cond->value }}"
                                           class="text-simba-600 focus:ring-simba-600" />
                                    <span class="badge {{ $cond->color() }}">{{ $cond->label() }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="label">LPN (optional)</label>
                        <div class="flex gap-2">
                            <select wire:model="receiptLpnId" class="input flex-1">
                                <option value="">— No LPN —</option>
                                @foreach ($this->grnLpns as $lpn)
                                    <option value="{{ $lpn->id }}">{{ $lpn->code }}</option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="generateLpn"
                                    class="btn btn-secondary btn-sm shrink-0">+ New LPN</button>
                        </div>
                    </div>

                    <button wire:click="addReceipt" class="btn btn-primary w-full">
                        <x-ui.icon name="plus" class="w-4 h-4" /> Add Receipt
                    </button>
                </div>
            </div>

            {{-- Receipts for this line --}}
            @if ($this->activeGrnLine->receipts->count() > 0)
            <div class="card overflow-hidden">
                <div class="card-header">
                    <h3 class="card-title text-sm">Receipts for this line</h3>
                </div>
                <div class="divide-y divide-white/40 dark:divide-white/[0.06]">
                    @foreach ($this->activeGrnLine->receipts as $r)
                        <div class="px-4 py-3 flex items-center justify-between gap-3">
                            <div>
                                <span class="tabular-nums font-semibold">{{ number_format((float)$r->qty, 4) }}</span>
                                <span class="text-xs text-slate-500">{{ $r->uom?->code }}</span>
                                @if ($r->batch_no)
                                    <span class="ml-2 mono text-xs badge badge-slate">{{ $r->batch_no }}</span>
                                @endif
                                <span class="ml-2 badge {{ $r->condition->color() }} text-[10px]">{{ $r->condition->label() }}</span>
                            </div>
                            <button wire:click="deleteReceipt({{ $r->id }})"
                                    wire:confirm="Delete this receipt?"
                                    class="icon-btn hover:text-red-600 dark:hover:text-red-400">
                                <x-ui.icon name="trash" class="w-4 h-4" />
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
            @else
            <div class="card">
                <div class="card-body text-center py-10">
                    <x-ui.icon name="qr-code" class="w-10 h-10 text-slate-300 dark:text-slate-600 mx-auto mb-3" />
                    <p class="text-slate-500">Scan a barcode or select a line to start recording receipts.</p>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
