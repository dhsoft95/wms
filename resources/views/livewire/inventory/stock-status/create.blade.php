<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">Stock Status Request</h2>
            <p class="page-sub">Mark stock as obsolete or restore to available.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="label">Virtual Warehouse</label>
                    <select wire:model.live="virtualWarehouseId" class="input w-full">
                        <option value="0">-- Select --</option>
                        @foreach($this->warehouses as $vw)
                            <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                        @endforeach
                    </select>
                    @error('virtualWarehouseId') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">
                        Department
                        @if($virtualWarehouseId > 0)
                            <span class="text-xs font-normal text-slate-400 ml-1">(auto-filled from VW)</span>
                        @endif
                    </label>
                    <select wire:model.live="departmentId" class="input w-full">
                        <option value="0">-- Select --</option>
                        @foreach($this->departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                        @endforeach
                    </select>
                    @error('departmentId') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Request Type</label>
                    <select wire:model.live="type" class="input w-full">
                        @foreach($this->types as $t)
                            <option value="{{ $t->value }}">{{ $t->label() }}</option>
                        @endforeach
                    </select>
                    @error('type') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Reason</label>
                    <input wire:model="reason" type="text" class="input w-full" placeholder="Reason…">
                    @error('reason') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header flex items-center justify-between">
            <h3 class="card-title">Lines</h3>
            <button wire:click="addLine" type="button" class="btn btn-sm btn-ghost">
                <x-ui.icon name="plus" class="w-4 h-4" /> Add Line
            </button>
        </div>

        <div class="divide-y divide-slate-100 dark:divide-white/[0.06]">
            @foreach($lines as $i => $line)
                @php
                    $isSerial      = ! empty($line['item_id']) && ($this->serialTrackingMap[(int)$line['item_id']] ?? false);
                    $availSerials  = $this->availableSerialsPerLine[$i] ?? [];
                    $selectedCount = count(array_filter($line['serial_ids'] ?? []));
                @endphp
                <div wire:key="line-{{ $i }}" class="p-4 space-y-3">

                    {{-- Row 1: Item / Location / Batch / Qty / Delete --}}
                    <div class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_10rem_8rem_2rem] gap-3 items-start">
                        <div>
                            @if($i === 0)<label class="label">Item</label>@endif
                            <select wire:model.live="lines.{{ $i }}.item_id" class="input w-full">
                                <option value="">-- Item --</option>
                                @foreach($this->items as $item)
                                    <option value="{{ $item->id }}">{{ $item->sku }} — {{ $item->name }}</option>
                                @endforeach
                            </select>
                            @error("lines.{$i}.item_id") <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            @if($i === 0)<label class="label">Bin Location</label>@endif
                            <select wire:model.live="lines.{{ $i }}.location_id" class="input w-full"
                                    @if($virtualWarehouseId <= 0) disabled @endif>
                                <option value="">{{ $virtualWarehouseId <= 0 ? '— select VW first —' : '-- Bin --' }}</option>
                                @foreach($this->locations as $loc)
                                    <option value="{{ $loc->id }}">{{ $loc->code }}</option>
                                @endforeach
                            </select>
                            @error("lines.{$i}.location_id") <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            @if($i === 0)<label class="label">Batch ID</label>@endif
                            <input wire:model.live="lines.{{ $i }}.batch_id" type="number"
                                   class="input w-full" placeholder="Optional">
                            @error("lines.{$i}.batch_id") <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            @if($i === 0)<label class="label">Qty</label>@endif
                            @if($isSerial)
                                <input type="text" readonly
                                       value="{{ $selectedCount > 0 ? $selectedCount : '—' }}"
                                       class="input w-full bg-slate-50 dark:bg-slate-800 text-slate-400 cursor-not-allowed text-center"
                                       title="Qty is set by the number of selected serial numbers">
                            @else
                                <input wire:model.live="lines.{{ $i }}.qty" type="number"
                                       min="0.0001" step="any" class="input w-full" placeholder="0">
                                @error("lines.{$i}.qty") <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                            @endif
                        </div>

                        <div class="{{ $i === 0 ? 'mt-[1.625rem]' : '' }} flex items-center">
                            @if(count($lines) > 1)
                                <button wire:click="removeLine({{ $i }})" type="button"
                                        class="btn btn-ghost btn-sm text-red-500 p-1">
                                    <x-ui.icon name="trash" class="w-4 h-4" />
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- Row 2: Serial picker (only for serial-tracked items) --}}
                    @if($isSerial)
                        <div class="rounded-xl border border-slate-200 dark:border-white/[0.08] bg-slate-50 dark:bg-slate-800/50 p-3">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                                    Serial Numbers
                                </p>
                                @if($selectedCount > 0)
                                    <span class="badge badge-simba">{{ $selectedCount }} selected</span>
                                @endif
                            </div>

                            @if(empty($availSerials))
                                <p class="text-xs text-slate-400 italic">
                                    {{ empty($line['location_id']) ? 'Select a location to see available serial numbers.' : 'No in-stock serial numbers found at this location.' }}
                                </p>
                            @else
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-1.5 max-h-40 overflow-y-auto">
                                    @foreach($availSerials as $sn)
                                        @php $checked = in_array($sn['id'], $line['serial_ids'] ?? []); @endphp
                                        <label class="flex items-center gap-1.5 rounded-lg px-2 py-1.5 cursor-pointer
                                                       text-xs font-mono
                                                       {{ $checked
                                                           ? 'bg-simba-100 dark:bg-simba-900/30 text-simba-700 dark:text-simba-300 ring-1 ring-simba-400'
                                                           : 'bg-white dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600' }}">
                                            <input type="checkbox"
                                                   wire:click="toggleLineSerial({{ $i }}, {{ $sn['id'] }})"
                                                   @checked($checked)
                                                   class="rounded border-slate-300 dark:border-slate-600 text-simba-600 focus:ring-simba-500 shrink-0">
                                            {{ $sn['serial_no'] }}
                                        </label>
                                    @endforeach
                                </div>

                                @error("lines.{$i}.serial_ids")
                                    <p class="text-xs text-red-600 dark:text-red-400 mt-2">{{ $message }}</p>
                                @else
                                    @if($selectedCount === 0)
                                        <p class="text-xs text-amber-600 dark:text-amber-400 mt-2">
                                            <x-ui.icon name="alert-triangle" class="w-3 h-3 inline mr-1" />
                                            Select at least one serial number.
                                        </p>
                                    @endif
                                @enderror
                            @endif
                        </div>
                    @endif

                </div>
            @endforeach
        </div>
    </div>

    <div class="flex justify-end gap-3">
        <button wire:click="save" type="button" class="btn btn-primary" wire:loading.attr="disabled">
            <span wire:loading wire:target="save">Submitting…</span>
            <span wire:loading.remove wire:target="save">Submit for Approval</span>
        </button>
    </div>
</div>
