<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">New Delivery Order</h2>
            <p class="page-sub">Create a delivery order and queue it for approval and allocation.</p>
        </div>
        <a href="{{ route('outbound.orders.index') }}" wire:navigate class="btn btn-secondary">
            <x-ui.icon name="arrow-left" class="w-4 h-4" /> Back to Orders
        </a>
    </div>

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
                        <select wire:model="virtualWarehouseId" class="input">
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
                        <select wire:model="warehouseId" class="input">
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
                        <select wire:model="departmentId" class="input">
                            <option value="">None</option>
                            @foreach ($this->departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Consignee --}}
                    <div>
                        <label class="label">Consignee</label>
                        <select wire:model="consigneePartnerId" class="input">
                            <option value="">None</option>
                            @foreach ($this->partners as $partner)
                                <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Delivery Address --}}
                    <div class="md:col-span-2">
                        <label class="label">Delivery Address</label>
                        <input wire:model="deliveryAddress" type="text" placeholder="Street, City, Country" class="input">
                    </div>

                    {{-- Required Date --}}
                    <div>
                        <label class="label">Required Date <span class="text-red-500">*</span></label>
                        <input wire:model="requiredDate" type="date" class="input tabular-nums">
                        @error('requiredDate') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>

                </div>
            </div>

            {{-- Shipment Settings --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Shipment Settings</h3>
                </div>
                <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-4">

                    <div>
                        <label class="label">Package Type</label>
                        <select wire:model="packageType" class="input">
                            <option value="">— Select package type —</option>
                            @foreach (\App\Enums\Outbound\PackageType::cases() as $pt)
                                <option value="{{ $pt->value }}">{{ $pt->label() }}</option>
                            @endforeach
                        </select>
                        @error('packageType') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="label">Remarks</label>
                        <textarea wire:model="remarks" rows="3" placeholder="Internal notes or special instructions…" class="input"></textarea>
                    </div>

                </div>
            </div>

            {{-- Sales Order --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Sales Order</h3>
                    <span class="text-xs text-slate-400">Required before submitting for approval</span>
                </div>
                <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-4">

                    <div>
                        <label class="label">Sales Order Number</label>
                        <input wire:model="mrnNumber" type="text" placeholder="e.g. SO-2026-00123" class="input">
                        @error('mrnNumber') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="label">Sales Order Document <span class="text-xs text-slate-400">(PDF / image, max 10 MB)</span></label>
                        <input wire:model="mrnFile" type="file" accept=".pdf,.jpg,.jpeg,.png" class="input text-sm file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-slate-100 dark:file:bg-slate-700">
                        @error('mrnFile') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>

                </div>
            </div>

            {{-- Order Lines --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Order Lines</h3>
                    <button type="button" wire:click="addLine" class="btn btn-secondary btn-sm">
                        <x-ui.icon name="plus" class="w-4 h-4" /> Add Line
                    </button>
                </div>
                <div class="card-body space-y-3">

                    @error('lines') <p class="hint !text-red-600 mb-2">{{ $message }}</p> @enderror

                    {{-- Column headers --}}
                    <div class="hidden md:grid grid-cols-12 gap-2 px-2 section-label">
                        <span class="col-span-3">Item Search</span>
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
                                <div class="relative" x-data="{ open: false }" @click.away="open = false" @keydown.escape="open = false">
                                    <div class="relative">
                                        <x-ui.icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" />
                                        <input
                                            wire:model.live.debounce.300ms="lines.{{ $i }}.item_sku"
                                            @focus="open = true"
                                            @keydown.enter.prevent="open = false"
                                            type="text"
                                            placeholder="Search SKU or name…"
                                            class="input input-sm font-mono pl-7"
                                            autocomplete="off"
                                        />
                                    </div>
                                    @if (!empty($line['search_results']))
                                        <ul
                                            x-show="open"
                                            x-transition:enter="transition ease-out duration-100"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            class="absolute z-50 mt-1 w-full min-w-[20rem] bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-lg shadow-lg max-h-56 overflow-y-auto"
                                        >
                                            @foreach ($line['search_results'] as $result)
                                                <li>
                                                    <button
                                                        type="button"
                                                        wire:click="selectItem({{ $i }}, {{ $result['id'] }})"
                                                        @click="open = false"
                                                        class="w-full text-left px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-700 flex items-center gap-2"
                                                    >
                                                        <span class="font-mono text-xs text-slate-500 w-24 shrink-0">{{ $result['sku'] }}</span>
                                                        <span class="truncate flex-1">{{ $result['name'] }}</span>
                                                        <span class="text-xs text-slate-400 shrink-0">{{ $result['uom_code'] }}</span>
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
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
                                />
                            </div>

                            {{-- Remove --}}
                            <div class="md:col-span-1 flex justify-end pt-1">
                                <button
                                    type="button"
                                    wire:click="removeLine({{ $i }})"
                                    class="icon-btn hover:!text-red-600"
                                    title="Remove line"
                                >
                                    <x-ui.icon name="trash" class="w-4 h-4" />
                                </button>
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
                    <span class="text-slate-500">Lines</span>
                    <span class="font-semibold tabular-nums">{{ count($lines) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Total Qty</span>
                    <span class="font-semibold tabular-nums">{{ number_format($this->totalQty, 2) }}</span>
                </div>

                <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
                    <label class="label">Priority</label>
                    <select wire:model="priority" class="input">
                        @foreach ($this->priorities as $p)
                            <option value="{{ $p->value }}">{{ $p->label() }}</option>
                        @endforeach
                    </select>
                    @error('priority') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300 pt-1 cursor-pointer">
                    <input wire:model="autoSubmit" type="checkbox" class="rounded border-slate-300 text-simba-600 focus:ring-simba-600">
                    Auto-submit for approval
                </label>

            </div>
            <div class="card-footer !justify-stretch flex-col gap-2">
                <button type="submit" class="btn btn-primary w-full">
                    <x-ui.icon name="check" class="w-4 h-4" />
                    {{ $autoSubmit ? 'Save & Submit' : 'Save as Draft' }}
                </button>
                <a href="{{ route('outbound.orders.index') }}" wire:navigate class="btn btn-secondary w-full">
                    Cancel
                </a>
            </div>
        </div>

    </form>

</div>
