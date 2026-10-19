<div class="space-y-6">
    <div class="page-header">
        <div>
            <a href="{{ route('warehouse.wtr.index') }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-simba-600 mb-2">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Back
            </a>
            <h2 class="page-title">New Warehouse Transfer</h2>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">

        {{-- Route --}}
        <div class="card">
            <div class="card-header"><h3 class="text-card-title">Route</h3></div>
            <div class="card-body grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="label">From Warehouse <span class="text-red-500">*</span></label>
                    <select wire:model.live="fromWarehouseId" class="input @error('fromWarehouseId') border-red-400 @enderror">
                        <option value="0">— Select —</option>
                        @foreach($this->warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                        @endforeach
                    </select>
                    @error('fromWarehouseId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label">To Warehouse <span class="text-red-500">*</span></label>
                    <select wire:model.live="toWarehouseId" class="input @error('toWarehouseId') border-red-400 @enderror">
                        <option value="0">— Select —</option>
                        @foreach($this->warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                        @endforeach
                    </select>
                    @error('toWarehouseId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label">From Department (VW) <span class="text-red-500">*</span></label>
                    <select wire:model="fromVwId" class="input @error('fromVwId') border-red-400 @enderror"
                            @disabled(!$fromWarehouseId)>
                        <option value="0">— Select warehouse first —</option>
                        @foreach($this->fromVirtualWarehouses as $vw)
                            <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                        @endforeach
                    </select>
                    @error('fromVwId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label">To Department (VW)</label>
                    <select wire:model="toVwId" class="input" @disabled(!$toWarehouseId)>
                        <option value="0">— Same department (optional) —</option>
                        @foreach($this->toVirtualWarehouses as $vw)
                            <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                        @endforeach
                    </select>
                    <p class="hint">Leave blank to keep same department ownership.</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="label">Notes</label>
                    <textarea wire:model="notes" rows="2" class="input resize-none" placeholder="Optional notes…"></textarea>
                </div>
            </div>
        </div>

        {{-- Lines --}}
        <div class="card">
            <div class="card-header flex items-center justify-between">
                <h3 class="text-card-title">Lines</h3>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="$set('blkOpen', true)" class="btn btn-sm btn-ghost">
                        <x-ui.icon name="upload" class="w-4 h-4" /> Bulk Upload
                    </button>
                    <button type="button" wire:click="addLine" class="btn btn-secondary btn-sm">
                        <x-ui.icon name="plus" class="w-3.5 h-3.5" /> Add Line
                    </button>
                </div>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($lines as $i => $line)
                    <div wire:key="wtr-line-{{ $i }}" class="card-body grid grid-cols-1 sm:grid-cols-5 gap-3 items-start">
                        <div class="sm:col-span-2">
                            <label class="label">Item <span class="text-red-500">*</span></label>
                            <select wire:model="lines.{{ $i }}.item_id" class="input @error("lines.{$i}.item_id") border-red-400 @enderror">
                                <option value="">— Select —</option>
                                @foreach($this->items as $item)
                                    <option value="{{ $item->id }}">{{ $item->sku }} — {{ $item->name }}</option>
                                @endforeach
                            </select>
                            @error("lines.{$i}.item_id") <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="label">Qty <span class="text-red-500">*</span></label>
                            <input wire:model="lines.{{ $i }}.qty" type="number" step="0.0001" min="0.0001"
                                   class="input @error("lines.{$i}.qty") border-red-400 @enderror"
                                   placeholder="0.0000" />
                            @error("lines.{$i}.qty") <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="label">UoM <span class="text-red-500">*</span></label>
                            <select wire:model="lines.{{ $i }}.uom_id" class="input @error("lines.{$i}.uom_id") border-red-400 @enderror">
                                <option value="">— Select —</option>
                                @foreach($this->uoms as $uom)
                                    <option value="{{ $uom->id }}">{{ $uom->code }}</option>
                                @endforeach
                            </select>
                            @error("lines.{$i}.uom_id") <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                <label class="label">Batch No</label>
                                <input wire:model="lines.{{ $i }}.batch_no" type="text"
                                       class="input font-mono" placeholder="Optional" />
                            </div>
                            @if(count($lines) > 1)
                                <button type="button" wire:click="removeLine({{ $i }})"
                                        class="btn btn-ghost text-red-500 hover:text-red-700 mb-0.5">
                                    <x-ui.icon name="trash" class="w-4 h-4" />
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex justify-end gap-3">
            <a href="{{ route('warehouse.wtr.index') }}" wire:navigate class="btn btn-secondary">Cancel</a>
            <button type="submit" wire:loading.attr="disabled" class="btn btn-primary">
                <span wire:loading.remove wire:target="save">Create Transfer</span>
                <span wire:loading wire:target="save">Creating…</span>
            </button>
        </div>

    </form>

    @include('components.bulk-line-upload')
</div>
