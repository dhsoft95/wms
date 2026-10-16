<div class="space-y-6">
    <div class="page-header">
        <div>
            <a href="{{ route('warehouse.transfers.index') }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-simba-600 mb-2">
                <x-ui.icon name="chevron-right" class="w-3.5 h-3.5 rotate-180" /> Back
            </a>
            <h2 class="page-title">New Transfer</h2>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="card max-w-2xl">
            <div class="card-header"><h3 class="text-card-title">Header</h3></div>
            <div class="card-body space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-[1fr_auto_1fr] gap-4 items-end">
                    <div>
                        <label class="label">From Virtual Warehouse *</label>
                        <select wire:model="fromVwId" class="input @error('fromVwId') border-red-400 @enderror">
                            <option value="">Select…</option>
                            @foreach($warehouses as $vw)
                                <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                            @endforeach
                        </select>
                        @error('fromVwId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="hidden md:flex items-center justify-center h-[38px] w-10 text-slate-400">
                        <x-ui.icon name="arrows" class="w-5 h-5" />
                    </div>
                    <div>
                        <label class="label">To Virtual Warehouse *</label>
                        <select wire:model="toVwId" class="input @error('toVwId') border-red-400 @enderror">
                            <option value="">Select…</option>
                            @foreach($warehouses as $vw)
                                <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                            @endforeach
                        </select>
                        @error('toVwId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="label">Notes</label>
                    <textarea wire:model="notes" rows="2" class="input"></textarea>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header flex items-center justify-between">
                <h3 class="text-card-title">Lines</h3>
                <button type="button" wire:click="addLine" class="btn btn-sm btn-secondary">
                    <x-ui.icon name="plus" class="w-4 h-4" /> Add Line
                </button>
            </div>
            <div class="card-body space-y-4">
                @foreach($lines as $i => $line)
                    <div wire:key="trf-line-{{ $i }}" class="p-4 rounded-xl border border-slate-200 dark:border-slate-700 space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="section-label">Line {{ $i + 1 }}</span>
                            @if(count($lines) > 1)
                                <button type="button" wire:click="removeLine({{ $i }})" class="icon-btn text-red-500">
                                    <x-ui.icon name="trash" class="w-4 h-4" />
                                </button>
                            @endif
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="label">Item *</label>
                                <select wire:model="lines.{{ $i }}.item_id" class="input @error('lines.'.$i.'.item_id') border-red-400 @enderror">
                                    <option value="">Select item…</option>
                                    @foreach($this->items as $item)
                                        <option value="{{ $item->id }}">{{ $item->sku }} — {{ $item->name }}</option>
                                    @endforeach
                                </select>
                                @error("lines.{$i}.item_id") <p class="hint !text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">Location (Source) *</label>
                                <select wire:model="lines.{{ $i }}.location_id" class="input @error('lines.'.$i.'.location_id') border-red-400 @enderror">
                                    <option value="">Select location…</option>
                                    @foreach($this->locations as $loc)
                                        <option value="{{ $loc->id }}">{{ $loc->code }}</option>
                                    @endforeach
                                </select>
                                @error("lines.{$i}.location_id") <p class="hint !text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">Batch No. <span class="text-slate-400 font-normal">(optional)</span></label>
                                <input wire:model="lines.{{ $i }}.batch_no" type="text" class="input mono @error('lines.'.$i.'.batch_no') border-red-400 @enderror" placeholder="e.g. BAT-001">
                                @error("lines.{$i}.batch_no") <p class="hint !text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">Qty *</label>
                                <input wire:model="lines.{{ $i }}.qty" type="number" step="0.0001" min="0.0001" class="input mono @error('lines.'.$i.'.qty') border-red-400 @enderror">
                                @error("lines.{$i}.qty") <p class="hint !text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label">Unit of Measure *</label>
                                <select wire:model="lines.{{ $i }}.uom_id" class="input @error('lines.'.$i.'.uom_id') border-red-400 @enderror">
                                    <option value="">Select UoM…</option>
                                    @foreach($this->uoms as $uom)
                                        <option value="{{ $uom->id }}">{{ $uom->code }}</option>
                                    @endforeach
                                </select>
                                @error("lines.{$i}.uom_id") <p class="hint !text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="card-footer">
                <a href="{{ route('warehouse.transfers.index') }}" wire:navigate class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Save Draft</button>
            </div>
        </div>
    </form>
</div>
