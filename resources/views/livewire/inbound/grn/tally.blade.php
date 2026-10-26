<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <a href="{{ route('inbound.grn.show', $grn) }}" wire:navigate
               class="inline-flex items-center gap-1.5 mb-2 text-xs font-semibold text-slate-500 transition-colors hover:text-simba-600 dark:text-slate-400 dark:hover:text-simba-400">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5 shrink-0" /> Back to GRN
            </a>
            <h2 class="page-title font-mono">Entry {{ $grn->number }}</h2>
            <div class="flex items-center gap-2 mt-1">
                <span class="badge {{ $grn->status->color() }}">
                    <span class="dot"></span>{{ $grn->status->label() }}
                </span>
                @if($grn->purchaseOrder)
                    <span class="text-sm text-slate-500">PO: <span class="font-mono font-medium">{{ $grn->purchaseOrder->number }}</span></span>
                @endif
                <span class="text-sm text-slate-500">{{ $grn->warehouse?->name }}</span>
            </div>
        </div>

        @if($this->hasReceipts)
            <button wire:click="completeReceiving"
                    wire:confirm="Complete receiving and post stock? This cannot be undone."
                    wire:loading.attr="disabled"
                    wire:target="completeReceiving"
                    class="btn btn-primary inline-flex items-center gap-1.5">
                <span wire:loading.remove wire:target="completeReceiving" class="inline-flex items-center gap-1.5">
                    <x-ui.icon name="check" class="w-4 h-4 shrink-0" /> Complete Receiving
                </span>
                <span wire:loading.inline-flex wire:target="completeReceiving" class="items-center gap-1.5">
                    <svg class="w-4 h-4 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/>
                        <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                    </svg>
                    <span>Completing…</span>
                </span>
            </button>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Lines Panel --}}
        <div class="lg:col-span-2 card overflow-hidden self-start">
            <div class="card-header">
                <h3 class="card-title">PO Lines</h3>
                <span class="badge badge-slate tabular-nums">{{ $this->lines->count() }}</span>
            </div>
            <x-ui.table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-right">Expected</th>
                        <th class="text-right">Received</th>
                        <th class="w-24">Progress</th>
                        <th class="text-right w-24">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->lines as $line)
                        @php
                            $received = (float) ($line->received_good_qty ?? 0) + (float) ($line->received_damaged_qty ?? 0);
                            $expected = (float) ($line->expected_qty ?? 0);
                            $pct      = $expected > 0 ? min(100, (int) round($received / $expected * 100)) : ($received > 0 ? 100 : 0);
                            $isActive = $activeLine === $line->id;
                            $isDone   = $pct >= 100;
                        @endphp
                        <tr wire:key="line-{{ $line->id }}"
                            @class(['bg-simba-50/60 dark:bg-simba-900/10 ring-1 ring-inset ring-simba-300 dark:ring-simba-700' => $isActive])>
                            <td class="min-w-[12rem]">
                                <div class="font-mono text-xs text-simba-600 dark:text-simba-400">{{ $line->item?->sku }}</div>
                                <div class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $line->item?->name }}</div>
                            </td>
                            <td class="text-right tabular-nums text-sm">
                                {{ $expected > 0 ? rtrim(rtrim(number_format($expected, 4, '.', ','), '0'), '.') : '—' }}
                                <span class="text-xs text-slate-400 ml-0.5">{{ $line->uom?->code }}</span>
                            </td>
                            <td class="text-right tabular-nums text-sm">
                                <span class="{{ $isDone ? 'text-emerald-600 font-semibold' : 'text-slate-700 dark:text-slate-300' }}">
                                    {{ rtrim(rtrim(number_format($received, 4, '.', ','), '0'), '.') }}
                                </span>
                            </td>
                            <td>
                                <div class="flex items-center gap-1.5">
                                    <div class="flex-1 h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                                        <div class="h-full rounded-full transition-all {{ $isDone ? 'bg-emerald-500' : 'bg-simba-500' }}"
                                             style="width: {{ $pct }}%"></div>
                                    </div>
                                    <span class="text-[10px] tabular-nums text-slate-400 w-7 text-right">{{ $pct }}%</span>
                                </div>
                            </td>
                            <td class="text-right">
                                <button wire:click="selectLine({{ $line->id }})"
                                        class="btn btn-sm {{ $isActive ? 'btn-primary' : 'btn-secondary' }}">
                                    {{ $isActive ? 'Active' : 'Select' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state title="No lines found on this GRN." :cols="5" />
                    @endforelse
                </tbody>
            </x-ui.table>
        </div>

        {{-- Receipt Entry Form --}}
        <div class="space-y-4">

            @if($this->activeTallyLine)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Record Receipt</h3>
                    </div>
                    <div class="card-body space-y-4">

                        {{-- Item badge --}}
                        <div class="rounded-lg px-3 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700">
                            <p class="font-mono text-sm font-semibold text-simba-600 dark:text-simba-400">{{ $this->activeTallyLine->item?->sku }}</p>
                            <p class="text-sm text-slate-700 dark:text-slate-300">{{ $this->activeTallyLine->item?->name }}</p>
                        </div>

                        {{-- Qty --}}
                        <div>
                            <label class="label">Qty <span class="text-red-500">*</span></label>
                            <input wire:model="receiptQty" type="number" step="0.0001" min="0.0001"
                                   class="input @error('receiptQty') border-red-400 @enderror"
                                   placeholder="0.0000" autofocus />
                            @error('receiptQty') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- UoM --}}
                        @php $uomLocked = $receiptUomId !== '' && $this->uomOptions->count() === 1; @endphp
                        <div>
                            <label class="label">Unit of Measure <span class="text-red-500">*</span></label>
                            <select wire:model="receiptUomId"
                                    @disabled($uomLocked)
                                    class="input @error('receiptUomId') border-red-400 @enderror {{ $uomLocked ? 'bg-slate-100 dark:bg-slate-800 cursor-not-allowed' : '' }}">
                                <option value="">— Select —</option>
                                @foreach($this->uomOptions as $uom)
                                    <option value="{{ $uom->id }}">{{ $uom->code }}</option>
                                @endforeach
                            </select>
                            @error('receiptUomId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Batch No --}}
                        @if($this->activeTallyLine->item?->track_batch)
                            <div>
                                <label class="label">Batch No <span class="text-red-500">*</span></label>
                                <input wire:model="receiptBatchNo" type="text"
                                       class="input font-mono @error('receiptBatchNo') border-red-400 @enderror"
                                       placeholder="BATCH-001" />
                                @error('receiptBatchNo') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        {{-- Expiry --}}
                        @if($this->itemRequiresExpiry)
                            <div>
                                <label class="label">Expiry Date <span class="text-red-500">*</span></label>
                                <input wire:model="receiptExpiry" type="date"
                                       class="input @error('receiptExpiry') border-red-400 @enderror" />
                                @error('receiptExpiry') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        {{-- Serial Numbers --}}
                        @if($this->activeTallyLine->item?->track_serial)
                            <div>
                                <label class="label">Serial Numbers <span class="text-red-500">*</span>
                                    <span class="font-normal text-xs text-slate-400 ml-1">one per line, must match qty</span>
                                </label>
                                <textarea wire:model="receiptSerials" rows="4"
                                          class="input font-mono text-xs @error('receiptSerials') border-red-400 @enderror"
                                          placeholder="SN-000001&#10;SN-000002"></textarea>
                                @error('receiptSerials') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        {{-- Condition --}}
                        <div>
                            <label class="label">Condition <span class="text-red-500">*</span></label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" wire:model.live="receiptCondition" value="good"
                                           class="accent-emerald-500" />
                                    <span class="badge badge-green">Good</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" wire:model.live="receiptCondition" value="damaged"
                                           class="accent-red-500" />
                                    <span class="badge badge-red">Damaged</span>
                                </label>
                            </div>
                            @error('receiptCondition') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>

                        @if($receiptCondition === 'damaged')
                            <div>
                                <label class="label">Damage Remark <span class="text-red-500">*</span></label>
                                <textarea wire:model="receiptDamageRemark" rows="2"
                                          class="input resize-none @error('receiptDamageRemark') border-red-400 @enderror"
                                          placeholder="Describe the damage…"></textarea>
                                @error('receiptDamageRemark') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <button wire:click="addReceipt"
                                wire:loading.attr="disabled"
                                wire:target="addReceipt"
                                class="btn btn-primary w-full">
                            <span wire:loading.remove wire:target="addReceipt" class="inline-flex items-center gap-1.5">
                                <x-ui.icon name="plus" class="w-4 h-4 shrink-0" /> Add Receipt
                            </span>
                            <span wire:loading.inline-flex wire:target="addReceipt" class="items-center gap-1.5">
                                <svg class="w-4 h-4 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none">
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/>
                                    <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                                </svg>
                                <span>Saving…</span>
                            </span>
                        </button>
                    </div>
                </div>

                {{-- Receipts recorded for this line --}}
                @if($this->activeTallyLine->receipts->isNotEmpty())
                    <div class="card overflow-hidden">
                        <div class="card-header">
                            <h3 class="card-title">Recorded</h3>
                            <span class="badge badge-slate tabular-nums">{{ $this->activeTallyLine->receipts->count() }}</span>
                        </div>
                        <div class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($this->activeTallyLine->receipts as $r)
                                <div wire:key="receipt-{{ $r->id }}" class="flex items-center justify-between gap-3 px-4 py-3">
                                    <div class="min-w-0">
                                        <span class="tabular-nums font-semibold text-sm text-slate-800 dark:text-slate-200">
                                            {{ rtrim(rtrim(number_format((float)$r->qty, 4, '.', ','), '0'), '.') }}
                                        </span>
                                        <span class="text-xs font-mono text-slate-400 ml-1">{{ $r->uom?->code }}</span>
                                        @if($r->batch_no)
                                            <span class="ml-2 badge badge-slate font-mono text-[10px]">{{ $r->batch_no }}</span>
                                        @endif
                                        <span class="ml-2 badge {{ $r->condition->color() }} text-[10px]">{{ $r->condition->label() }}</span>
                                    </div>
                                    <button wire:click="removeReceipt({{ $r->id }})"
                                            wire:confirm="Remove this receipt entry?"
                                            class="icon-btn text-slate-400 hover:text-red-600 dark:hover:text-red-400 shrink-0">
                                        <x-ui.icon name="trash" class="w-4 h-4 shrink-0" />
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

            @else
                <div class="card">
                    <div class="card-body py-12 flex flex-col items-center text-center gap-3">
                        <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                            <x-ui.icon name="list" class="w-6 h-6 text-slate-400" />
                        </div>
                        <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Select a line to record receipt</p>
                        <p class="text-xs text-slate-400">Click "Select" on any PO line to begin entering quantities.</p>
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>
