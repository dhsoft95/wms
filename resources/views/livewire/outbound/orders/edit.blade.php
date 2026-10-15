<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <a href="{{ route('outbound.orders.show', $deliveryOrder) }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 mb-1">
                <x-ui.icon name="arrow-left" class="w-4 h-4" /> Back to {{ $deliveryOrder->number }}
            </a>
            <h2 class="page-title">Edit Delivery Order</h2>
            <p class="page-sub">{{ $deliveryOrder->number }} &mdash; {{ $deliveryOrder->status->label() }}</p>
        </div>
    </div>

    {{-- Non-draft warning banner --}}
    @if (! $this->isDraft)
        <div class="rounded-lg border border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700 p-4 text-sm text-amber-800 dark:text-amber-300 flex items-start gap-3">
            <x-ui.icon name="alert-triangle" class="w-5 h-5 shrink-0 mt-0.5" />
            <div>
                <p class="font-semibold">This order is not in Draft status.</p>
                <p>Only draft delivery orders can be modified. The current status is
                    <span class="font-semibold">{{ $deliveryOrder->status->label() }}</span>.
                    Saving changes is disabled.
                </p>
            </div>
        </div>
    @endif

    <form wire:submit="save" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ── Left Column ─────────────────────────────────────────────────── --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Order Header --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Order Header</h3>
                </div>
                <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-4">

                    {{-- Virtual Warehouse --}}
                    <div>
                        <label class="label">Virtual Warehouse <span class="text-red-500">*</span></label>
                        <select wire:model="virtualWarehouseId" class="input" @disabled(! $this->isDraft)>
                            <option value="">Select virtual warehouse</option>
                            @foreach ($this->virtualWarehouses as $vw)
                                <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                            @endforeach
                        </select>
                        @error('virtualWarehouseId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Warehouse --}}
                    <div>
                        <label class="label">Source Warehouse <span class="text-red-500">*</span></label>
                        <select wire:model="warehouseId" class="input" @disabled(! $this->isDraft)>
                            <option value="">Select warehouse</option>
                            @foreach ($this->warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                        @error('warehouseId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Department (optional) --}}
                    <div>
                        <label class="label">Department</label>
                        <select wire:model="departmentId" class="input" @disabled(! $this->isDraft)>
                            <option value="">None</option>
                            @foreach ($this->departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Consignee --}}
                    <div>
                        <label class="label">Consignee</label>
                        <select wire:model="consigneePartnerId" class="input" @disabled(! $this->isDraft)>
                            <option value="">None</option>
                            @foreach ($this->partners as $partner)
                                <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Delivery Address --}}
                    <div class="md:col-span-2">
                        <label class="label">Delivery Address</label>
                        <input wire:model="deliveryAddress" type="text" placeholder="Street, City, Country" class="input" @disabled(! $this->isDraft)>
                    </div>

                    {{-- Required Date --}}
                    <div>
                        <label class="label">Required Date <span class="text-red-500">*</span></label>
                        <input wire:model="requiredDate" type="date" class="input tabular-nums" @disabled(! $this->isDraft)>
                        @error('requiredDate') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>

                </div>
            </div>

            {{-- Allocation --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Allocation Settings</h3>
                </div>
                <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-4">

                    <div>
                        <label class="label">Allocation Rule</label>
                        <select wire:model="allocationRule" class="input" @disabled(! $this->isDraft)>
                            @foreach ($this->allocationRules as $rule)
                                <option value="{{ $rule->value }}">{{ $rule->label() }}</option>
                            @endforeach
                        </select>
                        @error('allocationRule') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="label">Remarks</label>
                        <textarea wire:model="remarks" rows="3" placeholder="Internal notes or special instructions…" class="input" @disabled(! $this->isDraft)></textarea>
                    </div>

                </div>
            </div>

            {{-- Order Lines --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Order Lines</h3>
                    @if ($this->isDraft)
                        <button type="button" wire:click="addLine" class="btn btn-secondary btn-sm">
                            <x-ui.icon name="plus" class="w-4 h-4" /> Add Line
                        </button>
                    @endif
                </div>
                <div class="card-body space-y-3">

                    @error('lines') <p class="hint !text-red-600 mb-2">{{ $message }}</p> @enderror

                    {{-- Column headers --}}
                    <div class="hidden md:grid grid-cols-12 gap-2 px-2 section-label">
                        <span class="col-span-3">SKU Search</span>
                        <span class="col-span-3">Item Name</span>
                        <span class="col-span-1">UOM</span>
                        <span class="col-span-2">Qty</span>
                        <span class="col-span-2">Notes</span>
                        <span class="col-span-1"></span>
                    </div>

                    @foreach ($lines as $i => $line)
                        <div class="grid grid-cols-2 md:grid-cols-12 gap-2 items-start p-3 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200/70 dark:border-slate-700/50">

                            {{-- SKU Search --}}
                            <div class="md:col-span-3">
                                <div class="relative">
                                    <input
                                        wire:model="lines.{{ $i }}.item_sku"
                                        wire:keydown.enter.prevent="searchItem({{ $i }}, $el.value)"
                                        type="text"
                                        placeholder="SKU or name, Enter to search"
                                        class="input input-sm font-mono pr-8"
                                        @disabled(! $this->isDraft)
                                    />
                                    @if ($this->isDraft)
                                        <button
                                            type="button"
                                            wire:click="searchItem({{ $i }}, '{{ $line['item_sku'] }}')"
                                            class="absolute inset-y-0 right-2 flex items-center text-slate-400 hover:text-slate-600"
                                        >
                                            <x-ui.icon name="search" class="w-4 h-4" />
                                        </button>
                                    @endif
                                </div>
                                @error('lines.' . $i . '.item_id') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                            </div>

                            {{-- Item Name (auto-filled) --}}
                            <div class="md:col-span-3">
                                <input
                                    type="text"
                                    value="{{ $line['item_name'] }}"
                                    placeholder="Item name"
                                    readonly
                                    class="input input-sm bg-slate-100 dark:bg-slate-700 cursor-default"
                                />
                            </div>

                            {{-- UOM (auto-filled) --}}
                            <div class="md:col-span-1">
                                <input
                                    type="text"
                                    value="{{ $line['uom_code'] }}"
                                    placeholder="UOM"
                                    readonly
                                    class="input input-sm bg-slate-100 dark:bg-slate-700 cursor-default font-mono"
                                />
                            </div>

                            {{-- Qty --}}
                            <div class="md:col-span-2">
                                <input
                                    wire:model="lines.{{ $i }}.qty"
                                    type="number"
                                    min="0.0001"
                                    step="any"
                                    placeholder="Qty"
                                    class="input input-sm tabular-nums"
                                    @disabled(! $this->isDraft)
                                />
                                @if ($line['available_qty'] !== null)
                                    <p class="hint">Avail: {{ number_format($line['available_qty'], 2) }}</p>
                                @endif
                                @error('lines.' . $i . '.qty') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                            </div>

                            {{-- Notes --}}
                            <div class="md:col-span-2">
                                <input
                                    wire:model="lines.{{ $i }}.notes"
                                    type="text"
                                    placeholder="Notes"
                                    class="input input-sm"
                                    @disabled(! $this->isDraft)
                                />
                            </div>

                            {{-- Remove --}}
                            <div class="md:col-span-1 flex justify-end pt-1">
                                @if ($this->isDraft)
                                    <button
                                        type="button"
                                        wire:click="removeLine({{ $i }})"
                                        class="icon-btn hover:!text-red-600"
                                        title="Remove line"
                                    >
                                        <x-ui.icon name="trash" class="w-4 h-4" />
                                    </button>
                                @endif
                            </div>

                        </div>
                    @endforeach

                </div>
            </div>

        </div>

        {{-- ── Right Sidebar ─────────────────────────────────────────────────── --}}
        <div class="card self-start lg:sticky lg:top-6">
            <div class="card-header">
                <h3 class="card-title">Order Summary</h3>
            </div>
            <div class="card-body space-y-4 text-sm">

                <div class="flex justify-between">
                    <span class="text-slate-500">Order No.</span>
                    <span class="font-mono font-semibold">{{ $deliveryOrder->number }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Status</span>
                    <span class="badge {{ $deliveryOrder->status->badge() }}">{{ $deliveryOrder->status->label() }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Lines</span>
                    <span class="font-semibold tabular-nums">{{ count($lines) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Total Qty</span>
                    <span class="font-semibold tabular-nums">{{ number_format($this->totalQty, 2) }}</span>
                </div>

                <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
                    <label class="label">Priority</label>
                    <select wire:model="priority" class="input" @disabled(! $this->isDraft)>
                        @foreach ($this->priorities as $p)
                            <option value="{{ $p->value }}">{{ $p->label() }}</option>
                        @endforeach
                    </select>
                    @error('priority') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>

            </div>
            <div class="card-footer !justify-stretch flex-col gap-2">
                @if ($this->isDraft)
                    <button type="submit" class="btn btn-primary w-full">
                        <x-ui.icon name="check" class="w-4 h-4" /> Save Changes
                    </button>
                @endif
                <a href="{{ route('outbound.orders.show', $deliveryOrder) }}" wire:navigate class="btn btn-secondary w-full">
                    Cancel
                </a>
            </div>
        </div>

    </form>

</div>
