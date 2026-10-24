@php
    $fmt = fn ($qty) => rtrim(rtrim(number_format((float) $qty, 4, '.', ','), '0'), '.');

    $totalExpected = (float) $this->lines->sum(fn ($l) => (float) ($l->expected_qty ?? 0));
    $totalReceived = (float) $this->lines->sum(fn ($l) => (float) ($l->received_good_qty ?? 0) + (float) ($l->received_damaged_qty ?? 0));
    $totalDamaged  = (float) $this->lines->sum(fn ($l) => (float) ($l->received_damaged_qty ?? 0));
    $overallPct    = $totalExpected > 0 ? min(100, (int) round($totalReceived / $totalExpected * 100)) : ($totalReceived > 0 ? 100 : 0);
    $linesDone     = $this->lines->filter(function ($l) {
        $exp = (float) ($l->expected_qty ?? 0);
        $rec = (float) ($l->received_good_qty ?? 0) + (float) ($l->received_damaged_qty ?? 0);
        return $exp > 0 ? $rec >= $exp : $rec > 0;
    })->count();

    $spinner = '<svg class="w-4 h-4 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>';
@endphp

<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div class="min-w-0">
            <a href="{{ route('inbound.orders.show', $grn->purchaseOrder) }}" wire:navigate
               class="inline-flex items-center gap-1.5 mb-2 text-xs font-semibold text-slate-500 transition-colors hover:text-simba-600 dark:text-slate-400 dark:hover:text-simba-400">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5 shrink-0" />
                <span>Back to {{ $grn->purchaseOrder?->number ?? 'Purchase Order' }}</span>
            </a>
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="page-title">Goods Receipt</h2>
                <span class="badge badge-slate">
                    <span class="dot"></span>Draft · unconfirmed
                </span>
            </div>
            <div class="flex flex-wrap items-center gap-2 mt-2">
                @if($grn->purchaseOrder)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs rounded-md bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        <span class="text-slate-400">PO</span>
                        <span class="font-mono font-semibold">{{ $grn->purchaseOrder->number }}</span>
                    </span>
                @endif
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs rounded-md bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    <span class="text-slate-400">Dock</span>
                    <span class="font-mono font-semibold">{{ $grn->receivingDock?->code ?? '—' }}</span>
                </span>
                @if($grn->vehicle_plate)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs rounded-md bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        <x-ui.icon name="truck" class="w-3.5 h-3.5 shrink-0 text-slate-400" />
                        <span class="font-mono font-semibold">{{ $grn->vehicle_plate }}</span>
                    </span>
                @endif
            </div>
        </div>

        <button type="button"
                wire:click="completeReceiving"
                wire:confirm="Complete receiving? Stock will be posted and a GRN number assigned. This cannot be undone."
                wire:loading.attr="disabled"
                wire:target="completeReceiving"
                @disabled(! $this->hasReceipts)
                title="{{ $this->hasReceipts ? '' : 'Record at least one receipt first' }}"
                class="btn btn-primary inline-flex items-center justify-center gap-1.5 h-10 px-4 disabled:opacity-50 disabled:cursor-not-allowed">
            <span wire:loading.remove wire:target="completeReceiving" class="inline-flex items-center gap-1.5">
                <x-ui.icon name="check" class="w-4 h-4 shrink-0" />
                <span>Complete Receiving</span>
            </span>
            <span wire:loading.inline-flex wire:target="completeReceiving" class="items-center gap-1.5">
                {!! $spinner !!}<span>Posting stock…</span>
            </span>
        </button>
    </div>

    {{-- Progress summary --}}
    <div class="card card-body">
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <div>
                <p class="text-xs font-medium tracking-wide uppercase text-slate-400">Lines Complete</p>
                <p class="mt-1 text-lg font-semibold tabular-nums text-slate-800 dark:text-slate-200">
                    {{ $linesDone }} <span class="text-sm font-normal text-slate-400">/ {{ $this->lines->count() }}</span>
                </p>
            </div>
            <div>
                <p class="text-xs font-medium tracking-wide uppercase text-slate-400">Expected</p>
                <p class="mt-1 text-lg font-semibold tabular-nums text-slate-800 dark:text-slate-200">{{ $fmt($totalExpected) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium tracking-wide uppercase text-slate-400">Received</p>
                <p class="mt-1 text-lg font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $fmt($totalReceived) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium tracking-wide uppercase text-slate-400">Damaged</p>
                <p class="mt-1 text-lg font-semibold tabular-nums {{ $totalDamaged > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400' }}">
                    {{ $fmt($totalDamaged) }}
                </p>
            </div>
        </div>

        <div class="mt-4">
            <div class="flex items-center justify-between mb-1.5 text-xs">
                <span class="text-slate-500">Overall progress</span>
                <span class="font-semibold tabular-nums text-slate-700 dark:text-slate-300">{{ $overallPct }}%</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                <div class="h-full rounded-full transition-all duration-500 {{ $overallPct >= 100 ? 'bg-emerald-500' : 'bg-simba-500' }}"
                     style="width: {{ $overallPct }}%"></div>
            </div>
        </div>

        @unless($this->hasReceipts)
            <div class="flex items-center gap-2 px-3 py-2 mt-4 text-xs rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400">
                <x-ui.icon name="exclamation" class="w-4 h-4 shrink-0" />
                <span>Select a line and record at least one receipt before completing.</span>
            </div>
        @endunless
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">

        {{-- Lines panel --}}
        <div class="card min-w-0 self-start lg:col-span-2">
            <div class="card-header">
                <h3 class="card-title">Order Lines</h3>
                <span class="text-xs text-slate-400">Select to receive</span>
            </div>

            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($this->lines as $line)
                    @php
                        $received = (float) ($line->received_good_qty ?? 0) + (float) ($line->received_damaged_qty ?? 0);
                        $damaged  = (float) ($line->received_damaged_qty ?? 0);
                        $expected = (float) ($line->expected_qty ?? 0);
                        $pct      = $expected > 0 ? min(100, (int) round($received / $expected * 100)) : ($received > 0 ? 100 : 0);
                        $isActive = $activeLine === $line->id;
                        $isDone   = $pct >= 100;
                        $isOver   = $expected > 0 && $received > $expected;
                    @endphp

                    <button type="button"
                            wire:key="line-{{ $line->id }}"
                            wire:click="selectLine({{ $line->id }})"
                        @class([
                            'relative w-full px-4 py-3 text-left transition-colors focus:outline-none',
                            'bg-simba-50/60 dark:bg-simba-500/10' => $isActive,
                            'hover:bg-slate-50 dark:hover:bg-white/[0.03]' => ! $isActive,
                        ])>
                        @if($isActive)
                            <span class="absolute inset-y-0 left-0 w-1 rounded-r bg-simba-500"></span>
                        @endif

                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium truncate text-slate-800 dark:text-slate-200">{{ $line->item?->name ?? '—' }}</p>
                                <p class="font-mono text-xs truncate text-slate-500 dark:text-slate-400">
                                    {{ $line->item?->sku }}
                                    @if($line->item?->part_number)
                                        <span class="text-slate-400">· {{ $line->item->part_number }}</span>
                                    @endif
                                </p>
                            </div>

                            <div class="text-right shrink-0">
                                <p class="text-sm tabular-nums">
                                    <span @class([
                                        'font-semibold',
                                        'text-emerald-600 dark:text-emerald-400' => $isDone && ! $isOver,
                                        'text-red-600 dark:text-red-400'         => $isOver,
                                        'text-simba-600 dark:text-simba-400'     => $pct > 0 && ! $isDone,
                                        'text-slate-400'                         => $pct === 0,
                                    ])>{{ $fmt($received) }}</span>
                                    <span class="text-slate-400">/ {{ $expected > 0 ? $fmt($expected) : '—' }}</span>
                                </p>
                                <p class="font-mono text-xs text-slate-400">{{ $line->uom?->code }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 mt-2">
                            <div class="flex-1 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div @class([
                                        'h-full rounded-full transition-all duration-500',
                                        'bg-emerald-500' => $isDone && ! $isOver,
                                        'bg-red-500'     => $isOver,
                                        'bg-simba-500'   => ! $isDone,
                                     ])
                                     style="width: {{ $pct }}%"></div>
                            </div>
                            <span class="w-9 text-xs text-right tabular-nums text-slate-400">{{ $pct }}%</span>
                        </div>

                        @if($damaged > 0 || $line->item?->track_serial || $line->item?->track_batch)
                            <div class="flex flex-wrap gap-1 mt-2">
                                @if($damaged > 0)
                                    <span class="badge badge-amber text-xs">{{ $fmt($damaged) }} damaged</span>
                                @endif
                                @if($line->item?->track_serial)
                                    <span class="badge badge-slate text-xs">Serialised</span>
                                @endif
                                @if($line->item?->track_batch)
                                    <span class="badge badge-slate text-xs">Batch</span>
                                @endif
                            </div>
                        @endif
                    </button>
                @empty
                    <div class="px-4 py-10 text-sm text-center text-slate-400">No lines on this GRN.</div>
                @endforelse
            </div>
        </div>

        {{-- Receipt entry panel --}}
        <div class="space-y-4 min-w-0 lg:col-span-3">

            @if($activeLine && $this->activeTallyLine)
                @php
                    $tallyLine   = $this->activeTallyLine;
                    $tExpected   = (float) ($tallyLine->expected_qty ?? 0);
                    $tReceived   = (float) ($tallyLine->received_good_qty ?? 0) + (float) ($tallyLine->received_damaged_qty ?? 0);
                    $tRemaining  = max(0, $tExpected - $tReceived);
                    $isDamaged   = $receiptCondition === 'damaged';

                    $neededSerials = null;
                    if ($tallyLine->item?->track_serial && $receiptQty !== '' && $receiptUomId !== '') {
                        try {
                            $neededSerials = (int) round((float) \App\Services\Foundation\UomConverter::toBase(
                                $tallyLine->item,
                                (int) $receiptUomId,
                                $receiptQty,
                            ));
                        } catch (\Throwable) {
                            $neededSerials = null;
                        }
                    }
                @endphp

                <div class="card" wire:key="entry-{{ $tallyLine->id }}">
                    <div class="card-header">
                        <div class="min-w-0">
                            <h3 class="card-title">Enter Receipt</h3>
                            <p class="mt-0.5 text-xs truncate text-slate-500 dark:text-slate-400">
                                <span class="font-mono">{{ $tallyLine->item?->sku }}</span> · {{ $tallyLine->item?->name }}
                            </p>
                        </div>
                        @if($tExpected > 0)
                            <div class="text-right shrink-0">
                                <p class="text-xs text-slate-400">Remaining</p>
                                <p class="text-sm font-semibold tabular-nums {{ $tRemaining > 0 ? 'text-slate-800 dark:text-slate-200' : 'text-emerald-600 dark:text-emerald-400' }}">
                                    {{ $fmt($tRemaining) }} <span class="font-mono text-xs font-normal text-slate-400">{{ $tallyLine->uom?->code }}</span>
                                </p>
                            </div>
                        @endif
                    </div>

                    <form wire:submit="addReceipt" class="card-body space-y-5">

                        {{-- Condition --}}
                        <div>
                            <label class="label">Condition <span class="text-red-500">*</span></label>
                            <div class="grid grid-cols-2 gap-3">
                                <label @class([
                                    'flex items-center gap-3 p-3 border rounded-xl cursor-pointer transition-colors',
                                    'border-emerald-400 bg-emerald-50/60 ring-1 ring-emerald-400 dark:border-emerald-500/60 dark:bg-emerald-500/10' => ! $isDamaged,
                                    'border-slate-200 hover:border-slate-300 dark:border-slate-700' => $isDamaged,
                                ])>
                                    <input wire:model.live="receiptCondition" type="radio" value="good" class="sr-only" />
                                    <span class="flex items-center justify-center w-8 h-8 rounded-lg shrink-0 {{ ! $isDamaged ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-400 dark:bg-slate-800' }}">
                                        <x-ui.icon name="check" class="w-4 h-4 shrink-0" />
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-slate-800 dark:text-slate-200">Good</span>
                                        <span class="block text-xs text-slate-500">Goes to available stock</span>
                                    </span>
                                </label>

                                <label @class([
                                    'flex items-center gap-3 p-3 border rounded-xl cursor-pointer transition-colors',
                                    'border-amber-400 bg-amber-50/60 ring-1 ring-amber-400 dark:border-amber-500/60 dark:bg-amber-500/10' => $isDamaged,
                                    'border-slate-200 hover:border-slate-300 dark:border-slate-700' => ! $isDamaged,
                                ])>
                                    <input wire:model.live="receiptCondition" type="radio" value="damaged" class="sr-only" />
                                    <span class="flex items-center justify-center w-8 h-8 rounded-lg shrink-0 {{ $isDamaged ? 'bg-amber-500 text-white' : 'bg-slate-100 text-slate-400 dark:bg-slate-800' }}">
                                        <x-ui.icon name="exclamation" class="w-4 h-4 shrink-0" />
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-slate-800 dark:text-slate-200">Damaged</span>
                                        <span class="block text-xs text-slate-500">Goes to quarantine</span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        {{-- Qty + UoM --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-[2fr_1fr]">
                            <div>
                                <div class="flex items-baseline justify-between">
                                    <label class="label">Quantity <span class="text-red-500">*</span></label>
                                    @if($tRemaining > 0)
                                        <button type="button"
                                                wire:click="$set('receiptQty', '{{ $tRemaining }}')"
                                                class="text-xs font-medium text-simba-600 hover:underline dark:text-simba-400">
                                            Fill remaining ({{ $fmt($tRemaining) }})
                                        </button>
                                    @endif
                                </div>
                                <input wire:model.live.debounce.400ms="receiptQty" type="number" step="0.0001" min="0.0001"
                                       class="input font-mono text-base tabular-nums @error('receiptQty') border-red-400 @enderror"
                                       placeholder="0" autofocus />
                                @error('receiptQty') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">UoM <span class="text-red-500">*</span></label>
                                <select wire:model.live="receiptUomId" class="input font-mono @error('receiptUomId') border-red-400 @enderror">
                                    <option value="">Select…</option>
                                    @foreach($this->uomOptions as $u)
                                        <option value="{{ $u->id }}">{{ $u->code }}</option>
                                    @endforeach
                                </select>
                                @error('receiptUomId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        {{-- Damage remark --}}
                        @if($isDamaged)
                            <div>
                                <label class="label">Damage Remark <span class="text-red-500">*</span></label>
                                <textarea wire:model="receiptDamageRemark" rows="2"
                                          class="input w-full resize-none @error('receiptDamageRemark') border-red-400 @enderror"
                                          placeholder="e.g. Crushed carton, water damage, torn packaging…"></textarea>
                                @error('receiptDamageRemark') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        {{-- Tracking fields --}}
                        @if($tallyLine->item?->track_batch || $this->itemRequiresExpiry || ($tallyLine->item?->track_expiry ?? false))
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                @if($tallyLine->item?->track_batch)
                                    <div>
                                        <label class="label">Batch No <span class="text-xs font-normal text-slate-400">(optional)</span></label>
                                        <input wire:model="receiptBatchNo" type="text"
                                               class="input font-mono @error('receiptBatchNo') border-red-400 @enderror"
                                               placeholder="Auto: B-{date}-{seq}" />
                                        @error('receiptBatchNo') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                @endif

                                @if($this->itemRequiresExpiry || ($tallyLine->item?->track_expiry ?? false))
                                    <div>
                                        <div class="flex items-baseline justify-between">
                                            <label class="label">
                                                Expiry Date
                                                @if($this->itemRequiresExpiry) <span class="text-red-500">*</span> @endif
                                            </label>
                                            @if($this->itemRequiresExpiry)
                                                <span class="badge badge-amber text-xs">FEFO</span>
                                            @endif
                                        </div>
                                        <input wire:model="receiptExpiry" type="date" min="{{ now()->toDateString() }}"
                                               class="input @error('receiptExpiry') border-red-400 @enderror" />
                                        @error('receiptExpiry') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Serials --}}
                        @if($tallyLine->item?->track_serial)
                            <div x-data="{
                                    count: 0,
                                    needed: @js($neededSerials),
                                    recount(v) { this.count = v.split(/[\n,]+/).map(s => s.trim()).filter(Boolean).length }
                                 }"
                                 x-init="recount($refs.serials.value)">
                                <div class="flex items-baseline justify-between">
                                    <label class="label">Serial Numbers <span class="text-red-500">*</span></label>
                                    <span class="text-xs tabular-nums"
                                          :class="needed === null ? 'text-slate-400' : (count === needed ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-amber-600 dark:text-amber-400 font-semibold')">
                                        <span x-text="count"></span><template x-if="needed !== null"><span> / <span x-text="needed"></span></span></template> entered
                                    </span>
                                </div>
                                <textarea x-ref="serials"
                                          x-on:input="recount($event.target.value)"
                                          wire:model="receiptSerials" rows="4"
                                          class="input w-full font-mono text-xs resize-y @error('receiptSerials') border-red-400 @enderror"
                                          placeholder="SN-001&#10;SN-002&#10;SN-003"></textarea>
                                <p class="hint mt-1 text-slate-400">One per line or comma-separated. Scanners can paste directly.</p>
                                @error('receiptSerials') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <div class="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
                            <button type="submit"
                                    wire:loading.attr="disabled"
                                    wire:target="addReceipt"
                                    class="btn btn-primary inline-flex items-center justify-center gap-1.5 h-10 px-4 disabled:opacity-60">
                                <span wire:loading.remove wire:target="addReceipt" class="inline-flex items-center gap-1.5">
                                    <x-ui.icon name="plus" class="w-4 h-4 shrink-0" />
                                    <span>Add {{ $isDamaged ? 'Damaged' : '' }} Receipt</span>
                                </span>
                                <span wire:loading.inline-flex wire:target="addReceipt" class="items-center gap-1.5">
                                    {!! $spinner !!}<span>Adding…</span>
                                </span>
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Receipts for this line --}}
                @if($tallyLine->receipts->isNotEmpty())
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Recorded Receipts</h3>
                            <span class="badge badge-slate text-xs tabular-nums">{{ $tallyLine->receipts->count() }}</span>
                        </div>

                        <div class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($tallyLine->receipts as $receipt)
                                @php
                                    $condition = $receipt->condition?->value ?? $receipt->condition;
                                    $isGood    = $condition === 'good';
                                @endphp
                                <div wire:key="receipt-{{ $receipt->id }}" class="flex items-start gap-3 px-4 py-3">
                                    <span class="flex items-center justify-center w-8 h-8 rounded-lg shrink-0 {{ $isGood ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400' }}">
                                        <x-ui.icon :name="$isGood ? 'check' : 'exclamation'" class="w-4 h-4 shrink-0" />
                                    </span>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span class="text-sm font-semibold tabular-nums text-slate-800 dark:text-slate-200">{{ $fmt($receipt->qty) }}</span>
                                            <span class="font-mono text-xs text-slate-500">{{ $receipt->uom?->code }}</span>
                                            <span class="badge {{ $isGood ? 'badge-green' : 'badge-amber' }} text-xs">{{ ucfirst($condition ?? '—') }}</span>
                                            @if($receipt->batch_no)
                                                <span class="font-mono text-xs text-slate-500">Batch {{ $receipt->batch_no }}</span>
                                            @endif
                                        </div>

                                        @if($receipt->damage_remark)
                                            <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">{{ $receipt->damage_remark }}</p>
                                        @endif

                                        @if(! empty($receipt->serial_nos))
                                            <div class="flex flex-wrap gap-1 mt-2">
                                                @foreach($receipt->serial_nos as $sn)
                                                    <span class="badge badge-slate font-mono text-xs">{{ $sn }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    <button type="button"
                                            wire:click="removeReceipt({{ $receipt->id }})"
                                            wire:confirm="Remove this receipt?"
                                            wire:loading.attr="disabled"
                                            wire:target="removeReceipt({{ $receipt->id }})"
                                            class="icon-btn shrink-0 text-slate-400 hover:text-red-600 dark:hover:text-red-400"
                                            title="Remove receipt">
                                        <x-ui.icon name="trash" class="w-4 h-4 shrink-0" />
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

            @else
                <div class="card">
                    <div class="flex flex-col items-center justify-center gap-3 px-6 py-20 text-center card-body">
                        <span class="flex items-center justify-center w-14 h-14 rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800">
                            <x-ui.icon name="archive" class="w-7 h-7 shrink-0" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">No line selected</p>
                            <p class="mt-1 text-sm text-slate-400">Pick an order line to start recording what arrived.</p>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>

</div>
