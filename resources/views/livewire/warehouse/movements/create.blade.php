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
            <button type="button" wire:click="addLine" class="btn btn-sm btn-secondary">
                <x-ui.icon name="plus" class="w-3.5 h-3.5" /> Add Line
            </button>
        </div>
        <div class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach($lines as $i => $line)
                <div wire:key="mov-line-{{ $i }}" class="p-5 space-y-4">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Line {{ $i + 1 }}</span>
                        @if(count($lines) > 1)
                            <button type="button" wire:click="removeLine({{ $i }})" class="icon-btn text-slate-400 hover:text-red-500">
                                <x-ui.icon name="trash" class="w-4 h-4" />
                            </button>
                        @endif
                    </div>

                    {{-- Item --}}
                    <div>
                        <label class="label">Item <span class="text-red-500">*</span></label>
                        <select wire:model="lines.{{ $i }}.item_id" class="input @error('lines.'.$i.'.item_id') border-red-400 @enderror">
                            <option value="">Select item…</option>
                            @foreach($this->items as $item)
                                <option value="{{ $item->id }}">{{ $item->sku }} — {{ $item->name }}</option>
                            @endforeach
                        </select>
                        @error('lines.'.$i.'.item_id') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- From / To --}}
                    <div class="grid grid-cols-1 md:grid-cols-[1fr_auto_1fr] gap-4 items-start">
                        <div>
                            <label class="label">From Bin <span class="text-red-500">*</span></label>
                            <select wire:model="lines.{{ $i }}.from_location_id" class="input @error('lines.'.$i.'.from_location_id') border-red-400 @enderror">
                                <option value="">Select bin…</option>
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
                            <select wire:model="lines.{{ $i }}.to_location_id" class="input @error('lines.'.$i.'.to_location_id') border-red-400 @enderror">
                                <option value="">Select bin…</option>
                                @foreach($this->binLocations as $loc)
                                    <option value="{{ $loc->id }}">{{ $loc->code }}</option>
                                @endforeach
                            </select>
                            @error('lines.'.$i.'.to_location_id') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Batch / Expiry / Qty / UoM --}}
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
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
                        <div>
                            <label class="label">Qty <span class="text-red-500">*</span></label>
                            <input wire:model="lines.{{ $i }}.qty" type="number" step="0.0001" min="0.0001"
                                   class="input mono @error('lines.'.$i.'.qty') border-red-400 @enderror"
                                   placeholder="0.0000">
                            @error('lines.'.$i.'.qty') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">UoM <span class="text-red-500">*</span></label>
                            <select wire:model="lines.{{ $i }}.uom_id" class="input @error('lines.'.$i.'.uom_id') border-red-400 @enderror">
                                <option value="">UoM…</option>
                                @foreach($this->uoms as $uom)
                                    <option value="{{ $uom->id }}">{{ $uom->code }}</option>
                                @endforeach
                            </select>
                            @error('lines.'.$i.'.uom_id') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Serials --}}
                    <div>
                        <label class="label">Serial Numbers <span class="text-xs text-slate-400">(required for serial-tracked items — one per line or comma-separated)</span></label>
                        <textarea wire:model="lines.{{ $i }}.serials" rows="2"
                                  class="input w-full mono text-xs resize-y @error('lines.'.$i.'.serials') border-red-400 @enderror"
                                  placeholder="SN-001, SN-002"></textarea>
                        @error('lines.'.$i.'.serials') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endforeach
        </div>
        <div class="card-footer justify-between">
            <button type="button" wire:click="addLine" class="btn btn-secondary">
                <x-ui.icon name="plus" class="w-4 h-4" /> Add Line
            </button>
            <button type="button" wire:click="save" wire:loading.attr="disabled" class="btn btn-primary">
                <span wire:loading.remove wire:target="save"><x-ui.icon name="check" class="w-4 h-4" /> Create Movement</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </div>
</div>
