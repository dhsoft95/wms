<div class="space-y-6">
    <div class="page-header">
        <div>
            <a href="{{ route('warehouse.movements.index') }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-simba-600 mb-2">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Back to Movements
            </a>
            <h2 class="page-title">New Internal Movement</h2>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="text-card-title">Header</h3></div>
        <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="label">Virtual Warehouse <span class="text-red-500">*</span></label>
                <select wire:model="virtualWarehouseId" class="input @error('virtualWarehouseId') border-red-400 @enderror">
                    <option value="">Select warehouse…</option>
                    @foreach($this->virtualWarehouses as $vw)
                        <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                    @endforeach
                </select>
                @error('virtualWarehouseId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-2">
                <label class="label">Notes</label>
                <textarea wire:model="notes" rows="2" class="input w-full resize-none" placeholder="Optional movement notes…"></textarea>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="text-card-title">Lines</h3>
            <div class="flex items-center gap-2">
                <button type="button" wire:click="$set('blkOpen', true)" class="btn btn-sm btn-ghost">
                    <x-ui.icon name="upload" class="w-4 h-4" /> Bulk Upload
                </button>
                <button type="button" wire:click="addLine" class="btn btn-sm btn-secondary">
                    <x-ui.icon name="plus" class="w-3.5 h-3.5" /> Add Line
                </button>
            </div>
        </div>

        <div class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach($lines as $i => $line)
                @php
                    $stockAtBin  = $this->stockByLine[$i] ?? [];
                    $hasFromBin  = ! empty($line['from_location_id']);
                    $hasItem     = ! empty($line['item_id']);
                    $available   = $this->availableByLine[$i] ?? null;
                @endphp

                <div wire:key="mov-line-{{ $i }}" class="p-5 space-y-4">

                    {{-- Line header --}}
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Line {{ $i + 1 }}</span>
                        @if(count($lines) > 1)
                            <button type="button" wire:click="removeLine({{ $i }})" class="icon-btn text-slate-400 hover:text-red-500">
                                <x-ui.icon name="trash" class="w-4 h-4" />
                            </button>
                        @endif
                    </div>

                    {{-- Step 1: From Bin --}}
                    <div class="grid grid-cols-1 md:grid-cols-[1fr_auto_1fr] gap-4 items-start">
                        <div>
                            <label class="label">From Bin <span class="text-red-500">*</span></label>
                            <select wire:model.live="lines.{{ $i }}.from_location_id"
                                    class="input @error('lines.'.$i.'.from_location_id') border-red-400 @enderror">
                                <option value="">Select source bin…</option>
                                @foreach($this->binLocations as $loc)
                                    <option value="{{ $loc->id }}">{{ $loc->code }}</option>
                                @endforeach
                            </select>
                            @error('lines.'.$i.'.from_location_id') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="hidden md:flex items-center justify-center h-[38px] w-10 text-slate-400 mt-5">
                            <x-ui.icon name="arrow-right" class="w-5 h-5" />
                        </div>

                        <div>
                            <label class="label">To Bin <span class="text-red-500">*</span></label>
                            <select wire:model="lines.{{ $i }}.to_location_id"
                                    class="input @error('lines.'.$i.'.to_location_id') border-red-400 @enderror">
                                <option value="">Select destination bin…</option>
                                @foreach($this->binLocations as $loc)
                                    <option value="{{ $loc->id }}">{{ $loc->code }}</option>
                                @endforeach
                            </select>
                            @error('lines.'.$i.'.to_location_id') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Stock summary at selected bin --}}
                    @if($hasFromBin)
                        @if(count($stockAtBin) > 0)
                            <div class="rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                                <div class="px-4 py-2 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700 flex items-center gap-2">
                                    <x-ui.icon name="archive" class="w-3.5 h-3.5 text-slate-400" />
                                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                                        Stock at this bin — {{ count($stockAtBin) }} item(s)
                                    </span>
                                </div>
                                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @foreach($stockAtBin as $stock)
                                        <div class="flex items-center justify-between px-4 py-2.5 text-sm">
                                            <div>
                                                <span class="font-mono font-semibold text-simba-600 dark:text-simba-400">
                                                    {{ $stock['item']?->sku }}
                                                </span>
                                                <span class="text-slate-500 ml-2">{{ $stock['item']?->name }}</span>
                                            </div>
                                            <div class="flex items-center gap-3 text-right shrink-0">
                                                <span class="text-xs text-slate-400">{{ $stock['vw_name'] }}</span>
                                                <span class="tabular-nums font-semibold text-emerald-600 dark:text-emerald-400">
                                                    {{ number_format((float) $stock['qty'], 4) }}
                                                </span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="rounded-xl border border-amber-200 dark:border-amber-800/50 bg-amber-50/60 dark:bg-amber-950/20 px-4 py-3 flex items-center gap-2 text-sm text-amber-700 dark:text-amber-400">
                                <x-ui.icon name="exclamation" class="w-4 h-4 shrink-0" />
                                No available stock found in this bin.
                            </div>
                        @endif
                    @endif

                    {{-- Step 2: Item (filtered to bin stock) --}}
                    <div>
                        <label class="label">Item <span class="text-red-500">*</span></label>
                        <select wire:model.live="lines.{{ $i }}.item_id"
                                class="input @error('lines.'.$i.'.item_id') border-red-400 @enderror"
                                @disabled(! $hasFromBin)>
                            <option value="">
                                {{ $hasFromBin ? 'Select item from bin…' : 'Select a From Bin first' }}
                            </option>
                            @if($hasFromBin)
                                @foreach($stockAtBin as $stock)
                                    <option value="{{ $stock['item']?->id }}">
                                        {{ $stock['item']?->sku }} — {{ $stock['item']?->name }}
                                        ({{ number_format((float) $stock['qty'], 4) }} available)
                                    </option>
                                @endforeach
                            @endif
                        </select>
                        @error('lines.'.$i.'.item_id') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Step 3: Qty / UoM / Batch / Expiry --}}
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label class="label">Qty <span class="text-red-500">*</span></label>
                            <input wire:model="lines.{{ $i }}.qty" type="number" step="0.0001" min="0.0001"
                                   class="input mono @error('lines.'.$i.'.qty') border-red-400 @enderror"
                                   placeholder="0.0000"
                                   @disabled(! $hasItem)>
                            @if($hasItem && $available !== null)
                                <p class="hint text-slate-500 mt-1">
                                    Available:
                                    <span class="font-semibold {{ (float)$available > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500' }}">
                                        {{ number_format((float) $available, 4) }}
                                    </span>
                                </p>
                            @endif
                            @error('lines.'.$i.'.qty') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">UoM <span class="text-red-500">*</span></label>
                            <select wire:model="lines.{{ $i }}.uom_id"
                                    class="input @error('lines.'.$i.'.uom_id') border-red-400 @enderror"
                                    @disabled(! $hasItem)>
                                <option value="">UoM…</option>
                                @foreach($this->uoms as $uom)
                                    <option value="{{ $uom->id }}">{{ $uom->code }}</option>
                                @endforeach
                            </select>
                            @error('lines.'.$i.'.uom_id') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Batch No</label>
                            <input wire:model="lines.{{ $i }}.batch_no" type="text"
                                   class="input mono @error('lines.'.$i.'.batch_no') border-red-400 @enderror"
                                   placeholder="e.g. LOT-001">
                            @error('lines.'.$i.'.batch_no') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Expiry Date</label>
                            <input wire:model="lines.{{ $i }}.expiry_date" type="date"
                                   class="input @error('lines.'.$i.'.expiry_date') border-red-400 @enderror">
                            @error('lines.'.$i.'.expiry_date') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Serials (only shown for serial-tracked items) --}}
                    @php
                        $selectedItem = $hasItem ? collect($stockAtBin)->firstWhere(fn($s) => (string)($s['item']?->id) === (string)$line['item_id']) : null;
                        $trackSerial  = $selectedItem ? $selectedItem['item']?->track_serial : false;
                    @endphp
                    @if($trackSerial)
                        <div>
                            <label class="label">
                                Serial Numbers
                                <span class="text-xs text-red-500 ml-1">* required</span>
                            </label>
                            <textarea wire:model="lines.{{ $i }}.serials" rows="3"
                                      class="input w-full mono text-xs resize-y @error('lines.'.$i.'.serials') border-red-400 @enderror"
                                      placeholder="One per line or comma-separated: SN-001, SN-002"></textarea>
                            @error('lines.'.$i.'.serials') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif

                </div>
            @endforeach
        </div>

        <div class="card-footer justify-between">
            <button type="button" wire:click="addLine" class="btn btn-secondary">
                <x-ui.icon name="plus" class="w-4 h-4" /> Add Line
            </button>
            <button type="button" wire:click="save" wire:loading.attr="disabled" class="btn btn-primary">
                <span wire:loading.remove wire:target="save">
                    <x-ui.icon name="check" class="w-4 h-4" /> Create Movement
                </span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </div>

    @include('components.bulk-line-upload')
</div>
