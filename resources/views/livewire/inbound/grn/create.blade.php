<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">New Goods Receipt (GRN)</h2>
            <p class="page-sub">Create a manual GRN for a supplier delivery or customer return.</p>
        </div>
        <a href="{{ route('inbound.grn.index') }}" wire:navigate class="btn btn-secondary">
            <x-ui.icon name="x" class="w-4 h-4" /> Cancel
        </a>
    </div>

    <form wire:submit="save" class="space-y-6">

        {{-- ── GRN Details ──────────────────────────────────────────────── --}}
        <div class="card max-w-3xl">
            <div class="card-header">
                <h3 class="card-title">
                    <x-ui.icon name="clipboard" class="w-4 h-4" />
                    GRN Details
                </h3>
            </div>
            <div class="card-body space-y-5">

                {{-- Source --}}
                <div>
                    <label class="label">Source <span class="text-red-500">*</span></label>
                    <div class="flex gap-4 flex-wrap">
                        @foreach ($this->sources as $src)
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" wire:model.live="source" value="{{ $src->value }}"
                                       class="text-simba-600 focus:ring-simba-600" />
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                    {{ $src->label() }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('source') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                </div>

                {{-- Warehouse --}}
                <div>
                    <label class="label" for="grn-warehouse">Warehouse <span class="text-red-500">*</span></label>
                    <select wire:model.live="warehouseId" id="grn-warehouse"
                            class="input @error('warehouseId') border-red-400 @enderror">
                        <option value="">Select warehouse…</option>
                        @foreach ($this->warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                        @endforeach
                    </select>
                    @error('warehouseId') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

                    {{-- Supplier (supplier source only) --}}
                    @if ($source === 'supplier')
                    <div>
                        <label class="label" for="grn-supplier">Supplier</label>
                        <select wire:model="supplierId" id="grn-supplier"
                                class="input @error('supplierId') border-red-400 @enderror">
                            <option value="">— No supplier —</option>
                            @foreach ($this->suppliers as $sup)
                                <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                            @endforeach
                        </select>
                        @error('supplierId') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                    </div>
                    @endif

                    {{-- Customer (return source only) --}}
                    @if ($source === 'return')
                    <div>
                        <label class="label" for="grn-customer">Customer</label>
                        <select wire:model="customerId" id="grn-customer"
                                class="input @error('customerId') border-red-400 @enderror">
                            <option value="">— No customer —</option>
                            @foreach ($this->customers as $cust)
                                <option value="{{ $cust->id }}">{{ $cust->name }}</option>
                            @endforeach
                        </select>
                        @error('customerId') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                    </div>
                    @endif

                    {{-- Receiving Dock --}}
                    <div>
                        <label class="label" for="grn-dock">Receiving Dock</label>
                        <select wire:model="dockId" id="grn-dock"
                                class="input @error('dockId') border-red-400 @enderror">
                            <option value="">— Use warehouse default —</option>
                            @foreach ($this->dockLocations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->code }}</option>
                            @endforeach
                        </select>
                        @error('dockId') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                    </div>

                    {{-- Delivery Note Reference --}}
                    <div>
                        <label class="label" for="grn-dn-ref">
                            Delivery Note Ref <span class="text-slate-400 font-normal">(optional)</span>
                        </label>
                        <input type="text" wire:model="deliveryNoteRef" id="grn-dn-ref"
                               maxlength="100" placeholder="e.g. DN-2026-00123"
                               class="input @error('deliveryNoteRef') border-red-400 @enderror" />
                        @error('deliveryNoteRef') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                    </div>

                    {{-- Expected Date --}}
                    <div>
                        <label class="label" for="grn-expected-date">
                            Expected Arrival Date <span class="text-slate-400 font-normal">(optional)</span>
                        </label>
                        <input type="date" wire:model="expectedDate" id="grn-expected-date"
                               class="input @error('expectedDate') border-red-400 @enderror" />
                        @error('expectedDate') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                    </div>

                </div>

                {{-- Notes --}}
                <div>
                    <label class="label" for="grn-notes">
                        Notes <span class="text-slate-400 font-normal">(optional)</span>
                    </label>
                    <textarea wire:model="notes" id="grn-notes" rows="3"
                              class="input resize-none @error('notes') border-red-400 @enderror"></textarea>
                    @error('notes') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                </div>

            </div>
        </div>

        {{-- ── Expected Lines ───────────────────────────────────────────── --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <x-ui.icon name="list" class="w-4 h-4" />
                    Expected Lines
                    <span class="text-slate-400 text-xs font-normal">(optional — pre-populate to track variance)</span>
                </h3>
                <button type="button" wire:click="addLine" class="btn btn-secondary btn-sm">
                    <x-ui.icon name="plus" class="w-4 h-4" /> Add Line
                </button>
            </div>

            @if (count($lines) > 0)
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="w-8">#</th>
                            <th>Item <span class="text-red-500">*</span></th>
                            <th class="w-28">Exp. Qty <span class="text-red-500">*</span></th>
                            <th class="w-32">UoM <span class="text-red-500">*</span></th>
                            <th class="w-44">Virtual Warehouse <span class="text-red-500">*</span></th>
                            <th class="w-32">Batch No.</th>
                            <th class="w-36">Expiry Date</th>
                            <th class="w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $i => $line)
                        <tr wire:key="line-{{ $i }}">
                            <td class="text-slate-400 text-sm">{{ $i + 1 }}</td>

                            {{-- Item --}}
                            <td>
                                <select wire:model="lines.{{ $i }}.item_id"
                                        class="input input-sm @error("lines.{$i}.item_id") border-red-400 @enderror">
                                    <option value="">Select item…</option>
                                    @foreach ($this->items as $item)
                                        <option value="{{ $item->id }}">{{ $item->sku }} — {{ $item->name }}</option>
                                    @endforeach
                                </select>
                                @error("lines.{$i}.item_id")
                                    <p class="hint !text-red-600 dark:!text-red-400 mt-0.5">{{ $message }}</p>
                                @enderror
                            </td>

                            {{-- Expected Qty --}}
                            <td>
                                <input type="number" wire:model="lines.{{ $i }}.expected_qty"
                                       min="0.0001" step="any"
                                       class="input input-sm @error("lines.{$i}.expected_qty") border-red-400 @enderror" />
                                @error("lines.{$i}.expected_qty")
                                    <p class="hint !text-red-600 dark:!text-red-400 mt-0.5">{{ $message }}</p>
                                @enderror
                            </td>

                            {{-- UoM --}}
                            <td>
                                <select wire:model="lines.{{ $i }}.uom_id"
                                        class="input input-sm @error("lines.{$i}.uom_id") border-red-400 @enderror">
                                    <option value="">UoM…</option>
                                    @foreach ($this->uoms as $uom)
                                        <option value="{{ $uom->id }}">{{ $uom->code }}</option>
                                    @endforeach
                                </select>
                                @error("lines.{$i}.uom_id")
                                    <p class="hint !text-red-600 dark:!text-red-400 mt-0.5">{{ $message }}</p>
                                @enderror
                            </td>

                            {{-- Virtual Warehouse --}}
                            <td>
                                <select wire:model="lines.{{ $i }}.virtual_warehouse_id"
                                        class="input input-sm @error("lines.{$i}.virtual_warehouse_id") border-red-400 @enderror">
                                    <option value="">VW…</option>
                                    @foreach ($this->virtualWarehouses as $vw)
                                        <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                                    @endforeach
                                </select>
                                @error("lines.{$i}.virtual_warehouse_id")
                                    <p class="hint !text-red-600 dark:!text-red-400 mt-0.5">{{ $message }}</p>
                                @enderror
                            </td>

                            {{-- Batch No --}}
                            <td>
                                <input type="text" wire:model="lines.{{ $i }}.batch_no"
                                       maxlength="100" placeholder="optional"
                                       class="input input-sm @error("lines.{$i}.batch_no") border-red-400 @enderror" />
                            </td>

                            {{-- Expiry Date --}}
                            <td>
                                <input type="date" wire:model="lines.{{ $i }}.expiry_date"
                                       class="input input-sm @error("lines.{$i}.expiry_date") border-red-400 @enderror" />
                            </td>

                            {{-- Remove --}}
                            <td>
                                <button type="button" wire:click="removeLine({{ $i }})"
                                        class="text-red-400 hover:text-red-600 transition-colors">
                                    <x-ui.icon name="trash-2" class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="card-body">
                <p class="text-sm text-slate-400 text-center py-6">
                    No expected lines added. Click <strong>Add Line</strong> to pre-populate expected items,
                    or leave empty to receive without a manifest.
                </p>
            </div>
            @endif
        </div>

        {{-- ── Actions ──────────────────────────────────────────────────── --}}
        <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
            <a href="{{ route('inbound.grn.index') }}" wire:navigate class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <x-ui.icon name="check" class="w-4 h-4" /> Create GRN
            </button>
        </div>

    </form>
</div>
