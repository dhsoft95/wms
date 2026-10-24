<div class="space-y-6">
    <div class="page-header">
        <div>
            <a href="{{ route('wtr.index') }}" wire:navigate
               class="inline-flex items-center gap-1.5 mb-2 text-xs font-semibold text-slate-500 transition-colors hover:text-simba-600 dark:text-slate-400 dark:hover:text-simba-400">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5 shrink-0" />
                <span>Back to Warehouse Transfers</span>
            </a>
            <h2 class="page-title">New Warehouse Transfer</h2>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">

        {{-- Route --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Route</h3>
            </div>
            <div class="card-body grid grid-cols-1 sm:grid-cols-2 gap-4">

                {{-- From Warehouse --}}
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

                {{-- To Warehouse --}}
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

                {{-- From Department (VW) --}}
                <div>
                    <label class="label">From Department (VW) <span class="text-red-500">*</span></label>
                    <select wire:model.live="fromVwId" class="input @error('fromVwId') border-red-400 @enderror"
                            @disabled(! $fromWarehouseId)>
                        <option value="0">{{ $fromWarehouseId ? '— Select —' : '— Select warehouse first —' }}</option>
                        @foreach($this->fromVirtualWarehouses as $vw)
                            <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                        @endforeach
                    </select>
                    @error('fromVwId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- To Department (VW) --}}
                <div>
                    <label class="label">To Department (VW)</label>
                    <select wire:model="toVwId" class="input" @disabled(! $toWarehouseId)>
                        <option value="0">{{ $toWarehouseId ? '— Same department (optional) —' : '— Select warehouse first —' }}</option>
                        @foreach($this->toVirtualWarehouses as $vw)
                            <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                        @endforeach
                    </select>
                    <p class="hint">Leave blank to keep same department ownership.</p>
                </div>

                {{-- Notes --}}
                <div class="sm:col-span-2">
                    <label class="label">Notes</label>
                    <textarea wire:model="notes" rows="2" class="input resize-none" placeholder="Optional notes…"></textarea>
                </div>
            </div>
        </div>

        {{-- Lines --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Lines</h3>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="$set('blkOpen', true)" class="btn btn-sm btn-ghost">
                        <x-ui.icon name="upload" class="w-4 h-4 shrink-0" /> Bulk Upload
                    </button>
                    <button type="button" wire:click="addLine" class="btn btn-secondary btn-sm">
                        <x-ui.icon name="plus" class="w-3.5 h-3.5 shrink-0" /> Add Line
                    </button>
                </div>
            </div>

            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($lines as $i => $line)
                    @php
                        $stock     = $this->stockByLine[$i] ?? [];
                        $available = $this->availableByLine[$i] ?? null;
                        $hasStock  = count($stock) > 0;
                        $locId     = (int) ($line['source_location_id'] ?? 0);
                        $itemId    = (int) ($line['item_id'] ?? 0);
                    @endphp
                    <div wire:key="wtr-line-{{ $i }}" class="card-body space-y-3">

                        {{-- Row 1: Source Bin + Item --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            {{-- Source Bin --}}
                            <div>
                                <label class="label">Source Bin <span class="text-red-500">*</span></label>
                                <select wire:model.live="lines.{{ $i }}.source_location_id"
                                        class="input @error("lines.{$i}.source_location_id") border-red-400 @enderror"
                                        @disabled(! $fromWarehouseId || ! $fromVwId)>
                                    <option value="">
                                        {{ (! $fromWarehouseId || ! $fromVwId) ? '— Select warehouse & dept first —' : '— Select bin —' }}
                                    </option>
                                    @foreach($this->fromBins as $bin)
                                        <option value="{{ $bin->id }}">{{ $bin->code }}</option>
                                    @endforeach
                                </select>
                                @error("lines.{$i}.source_location_id") <p class="hint !text-red-600">{{ $message }}</p> @enderror
                            </div>

                            {{-- Item --}}
                            <div>
                                <label class="label flex items-center justify-between">
                                    <span>Item <span class="text-red-500">*</span></span>
                                    @if($available !== null)
                                        @php
                                            $avNum = (float) $available;
                                            $avFmt = rtrim(rtrim(number_format($avNum, 4, '.', ','), '0'), '.');
                                        @endphp
                                        <span class="text-xs font-normal tabular-nums {{ $avNum > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500' }}">
                                            Avail: {{ $avFmt }}
                                        </span>
                                    @endif
                                </label>

                                @if($hasStock && $locId)
                                    {{-- Clickable stock rows --}}
                                    <div class="flex flex-col gap-1 mb-2">
                                        @foreach($stock as $row)
                                            @php
                                                $rowItem  = $row['item'];
                                                $rowQty   = rtrim(rtrim(number_format((float) $row['qty'], 4, '.', ','), '0'), '.');
                                                $isActive = (string) ($line['item_id'] ?? '') === (string) $rowItem->id;
                                            @endphp
                                            <button type="button"
                                                    wire:click="$set('lines.{{ $i }}.item_id', {{ $rowItem->id }})"
                                                    class="w-full flex items-center justify-between px-3 py-2 rounded-lg border text-left text-sm transition-colors
                                                           {{ $isActive
                                                               ? 'border-simba-400 bg-simba-50 dark:bg-simba-900/20 dark:border-simba-600'
                                                               : 'border-slate-200 hover:border-simba-300 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800/50' }}">
                                                <span>
                                                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ $rowItem->name }}</span>
                                                    <span class="ml-1.5 font-mono text-xs text-slate-400">{{ $rowItem->sku }}</span>
                                                </span>
                                                <span class="tabular-nums text-xs {{ $isActive ? 'text-simba-600 font-semibold' : 'text-slate-500' }}">
                                                    {{ $rowQty }}
                                                </span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif

                                <select wire:model.live="lines.{{ $i }}.item_id"
                                        class="input @error("lines.{$i}.item_id") border-red-400 @enderror"
                                        @disabled(! $locId)>
                                    <option value="">{{ $locId ? '— Select item —' : '— Select bin first —' }}</option>
                                    @if($hasStock)
                                        @foreach($stock as $row)
                                            <option value="{{ $row['item']->id }}">{{ $row['item']->sku }} — {{ $row['item']->name }}</option>
                                        @endforeach
                                    @endif
                                </select>
                                @error("lines.{$i}.item_id") <p class="hint !text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        {{-- Row 2: Qty + UoM + Batch + Remove --}}
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 items-end">
                            <div>
                                <label class="label">Qty <span class="text-red-500">*</span></label>
                                <input wire:model="lines.{{ $i }}.qty" type="number" step="0.0001" min="0.0001"
                                       class="input @error("lines.{$i}.qty") border-red-400 @enderror"
                                       placeholder="0.0000" />
                                @error("lines.{$i}.qty") <p class="hint !text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="label">UoM <span class="text-red-500">*</span></label>
                                <select wire:model="lines.{{ $i }}.uom_id"
                                        class="input @error("lines.{$i}.uom_id") border-red-400 @enderror">
                                    <option value="">— Select —</option>
                                    @foreach($this->uoms as $uom)
                                        <option value="{{ $uom->id }}">{{ $uom->code }}</option>
                                    @endforeach
                                </select>
                                @error("lines.{$i}.uom_id") <p class="hint !text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="label">Batch No</label>
                                <input wire:model="lines.{{ $i }}.batch_no" type="text"
                                       class="input font-mono" placeholder="Optional" />
                            </div>

                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <label class="label">Expiry Date</label>
                                    <input wire:model="lines.{{ $i }}.expiry_date" type="date" class="input" />
                                </div>
                                @if(count($lines) > 1)
                                    <button type="button" wire:click="removeLine({{ $i }})"
                                            class="btn btn-ghost text-red-500 hover:text-red-700 mb-0.5 shrink-0">
                                        <x-ui.icon name="trash" class="w-4 h-4 shrink-0" />
                                    </button>
                                @endif
                            </div>
                        </div>

                    </div>
                @endforeach
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex justify-end gap-3">
            <a href="{{ route('wtr.index') }}" wire:navigate class="btn btn-secondary">Cancel</a>
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="btn btn-primary">
                <span wire:loading.remove wire:target="save">Create Transfer</span>
                <span wire:loading wire:target="save">Creating…</span>
            </button>
        </div>

    </form>

    @include('components.bulk-line-upload')
</div>
