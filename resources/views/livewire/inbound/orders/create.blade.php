<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">New Purchase Order</h2>
            <p class="page-sub">Create an inbound purchase order and queue it for approval.</p>
        </div>
        <a href="{{ route('inbound.orders.index') }}" wire:navigate class="btn btn-secondary">
            <x-ui.icon name="arrow-left" class="w-4 h-4" /> Back to Orders
        </a>
    </div>

    <form wire:submit="save" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ── Left Column ──────────────────────────────────────────────────── --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Order Header --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Order Header</h3>
                </div>
                <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-4">

                    {{-- Warehouse --}}
                    <div>
                        <label class="label">Warehouse <span class="required-mark">*</span></label>
                        <select wire:model.live="warehouseId" class="input">
                            <option value="">Select warehouse</option>
                            @foreach ($this->warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                        @error('warehouseId') <p class="hint hint-error">{{ $message }}</p> @enderror
                    </div>

                    {{-- Department --}}
                    <div>
                        <label class="label">Department</label>
                        <select wire:model="departmentId" class="input"
                                @disabled(auth()->user()->hasRole('department-user'))>
                            <option value="">— None —</option>
                            @foreach ($this->departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Virtual Warehouse --}}
                    <div>
                        <label class="label">Virtual Warehouse</label>
                        <select wire:model="virtualWarehouseId" class="input">
                            <option value="">None</option>
                            @foreach ($this->virtualWarehouses as $vw)
                                <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Package Type --}}
                    <div>
                        <label class="label">Package Type <span class="required-mark">*</span></label>
                        <select wire:model.live="packageType" x-model="packageType" class="input">
                            @foreach ($this->packageTypes as $pt)
                                <option value="{{ $pt->value }}">{{ $pt->label() }}</option>
                            @endforeach
                        </select>
                        @error('packageType') <p class="hint hint-error">{{ $message }}</p> @enderror
                    </div>

                    {{-- Supplier --}}
                    <div>
                        <label class="label">Supplier</label>
                        <select wire:model="supplierPartnerId" class="input">
                            <option value="">None</option>
                            @foreach ($this->partners as $partner)
                                <option value="{{ $partner->id }}">{{ $partner->name }}</option>
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

                    {{-- Customer PO Reference --}}
                    <div>
                        <label class="label">Customer PO Reference</label>
                        <input wire:model="customerPoRef" type="text" placeholder="e.g. PO-2026-00123" class="input">
                    </div>

                    {{-- Expected Date --}}
                    <div>
                        <label class="label">Expected Arrival Date</label>
                        <input wire:model="expectedDate" type="date" class="input tabular-nums">
                    </div>

                </div>
            </div>

            {{-- Special Treatment --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Flags</h3>
                </div>
                <div class="card-body space-y-3">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input wire:model.live="isSpecialTreatment" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-simba-600 focus:ring-simba-500">
                        <span class="text-sm">
                            <span class="font-semibold text-slate-800 dark:text-slate-100">Special Treatment Required</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">Marks this PO as requiring mandatory approver review regardless of chain configuration.</span>
                        </span>
                    </label>
                    @if ($isSpecialTreatment)
                        <div>
                            <label class="label">Reason <span class="required-mark">*</span></label>
                            <textarea wire:model="specialTreatmentReason" rows="3" class="input resize-none"
                                      placeholder="Explain why special treatment is required (min. 10 characters)…"></textarea>
                            @error('specialTreatmentReason') <p class="hint hint-error">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>
            </div>

            {{-- Order Lines --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Order Lines</h3>
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="$set('blkOpen', true)" class="btn btn-sm btn-ghost">
                            <x-ui.icon name="upload" class="w-4 h-4" /> Bulk Upload
                        </button>
                        <button type="button" wire:click="addLine" class="btn btn-secondary btn-sm">
                            <x-ui.icon name="plus" class="w-4 h-4" /> Add Line
                        </button>
                    </div>
                </div>
                <div class="card-body space-y-3">

                    @error('lines') <p class="hint hint-error mb-2">{{ $message }}</p> @enderror

                    {{-- Column headers --}}
                    <div class="hidden md:grid md:grid-cols-12 gap-2 px-2 section-label">
                        <span class="col-span-3">Item</span>
                        <span class="col-span-1">Part No.</span>
                        <span class="col-span-1">UOM</span>
                        <span class="col-span-1">Qty</span>
                        <span class="col-span-2">Currency / Unit Price</span>
                        <span class="col-span-1">Total Value</span>
                        <span class="col-span-2">MIC No.</span>
                        <span class="col-span-1"></span>
                    </div>

                    @foreach ($lines as $i => $line)
                        <div class="grid grid-cols-2 md:grid-cols-12 gap-2 items-start p-3 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200/70 dark:border-slate-700/50">

                            {{-- Item --}}
                            <div class="col-span-2 md:col-span-3">
                                <select wire:model.live="lines.{{ $i }}.item_id" class="input input-sm">
                                    <option value="">Select item…</option>
                                    @foreach ($this->items as $item)
                                        <option value="{{ $item->id }}">{{ $item->sku }} — {{ $item->name }}</option>
                                    @endforeach
                                </select>
                                @error('lines.' . $i . '.item_id') <p class="hint hint-error">{{ $message }}</p> @enderror
                            </div>

                            {{-- Part No (manual entry) --}}
                            <div class="md:col-span-1">
                                <input wire:model="lines.{{ $i }}.part_number"
                                       type="text"
                                       placeholder="Part no."
                                       class="input input-sm font-mono">
                            </div>

                            {{-- UOM (auto-selected from item, can override) --}}
                            <div class="md:col-span-1">
                                <select wire:model="lines.{{ $i }}.uom_id" class="input input-sm">
                                    <option value="">—</option>
                                    @foreach ($this->uoms as $uom)
                                        <option value="{{ $uom->id }}">{{ $uom->code }}</option>
                                    @endforeach
                                </select>
                                @error('lines.' . $i . '.uom_id') <p class="hint hint-error">{{ $message }}</p> @enderror
                            </div>

                            {{-- Qty --}}
                            <div class="md:col-span-1">
                                <input wire:model.live="lines.{{ $i }}.qty"
                                       type="number"
                                       min="0.0001"
                                       step="any"
                                       placeholder="0"
                                       class="input input-sm tabular-nums">
                                @error('lines.' . $i . '.qty') <p class="hint hint-error">{{ $message }}</p> @enderror
                            </div>

                            {{-- Currency + Unit Price (combined) --}}
                            <div class="md:col-span-2 flex gap-1">
                                <select wire:model="lines.{{ $i }}.currency"
                                        class="input input-sm font-mono w-20 shrink-0">
                                    <option value="">—</option>
                                    @foreach ($this->currencies as $c)
                                        <option value="{{ $c->value }}">{{ $c->value }}</option>
                                    @endforeach
                                </select>
                                <div class="flex-1">
                                    <input wire:model.live="lines.{{ $i }}.unit_price"
                                           type="number"
                                           min="0"
                                           step="0.0001"
                                           placeholder="0.0000"
                                           class="input input-sm tabular-nums font-mono w-full">
                                    @error('lines.' . $i . '.unit_price') <p class="hint hint-error">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            {{-- Total Value (auto-calculated) --}}
                            <div class="md:col-span-1">
                                <input wire:model="lines.{{ $i }}.line_total"
                                       type="text"
                                       readonly
                                       placeholder="—"
                                       class="input input-sm tabular-nums font-mono font-semibold bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 cursor-default">
                            </div>

                            {{-- MSC Number --}}
                            <div class="md:col-span-2">
                                <input wire:model="lines.{{ $i }}.mic_number"
                                       type="text"
                                       maxlength="100"
                                       placeholder="MIC no."
                                       class="input input-sm font-mono">
                            </div>

                            {{-- Remove --}}
                            <div class="md:col-span-1 flex justify-end pt-1">
                                <button type="button"
                                        wire:click="removeLine({{ $i }})"
                                        class="icon-btn hover:!text-red-600"
                                        title="Remove line">
                                    <x-ui.icon name="trash" class="w-4 h-4" />
                                </button>
                            </div>

                        </div>
                    @endforeach

                </div>
            </div>

        </div>

        {{-- ── Right Sidebar ────────────────────────────────────────────────── --}}
        <div class="space-y-4">
            <div class="card self-start lg:sticky lg:top-6">
                <div class="card-header">
                    <h3 class="card-title">Summary</h3>
                </div>
                <div class="card-body space-y-4 text-sm">

                    <div class="flex justify-between">
                        <span class="text-slate-500">Lines</span>
                        <span class="font-semibold tabular-nums">{{ count($lines) }}</span>
                    </div>

                    {{-- PO Document --}}
                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
                        <label class="label">PO Document
                            <span class="text-xs font-normal text-slate-400 ml-1">(PDF, Word, Excel, Image — max 20 MB)</span>
                        </label>
                        <div class="relative">
                            <label class="flex items-center gap-3 cursor-pointer border border-dashed border-slate-300 dark:border-slate-600 rounded-xl px-4 py-3 hover:border-simba-400 transition-colors">
                                <x-ui.icon name="upload" class="w-4 h-4 text-slate-400 shrink-0" wire:loading.remove wire:target="poFile" />
                                <x-ui.icon name="refresh" class="w-4 h-4 text-brand animate-spin shrink-0 hidden" wire:loading wire:target="poFile" />
                                <span class="text-xs text-slate-500 dark:text-slate-400 truncate" wire:loading.remove wire:target="poFile">
                                    @if ($poFile)
                                        <span class="text-green-600 dark:text-green-400 font-medium">
                                            {{ is_object($poFile) ? $poFile->getClientOriginalName() : 'File selected' }}
                                        </span>
                                    @else
                                        Click to attach document…
                                    @endif
                                </span>
                                <span class="text-xs text-brand hidden" wire:loading wire:target="poFile">Uploading…</span>
                                <input type="file"
                                       wire:model="poFile"
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                       class="sr-only">
                            </label>
                        </div>
                        @if ($poFile)
                            <button type="button" wire:click="$set('poFile', null)" class="mt-1 text-xs text-red-500 hover:underline">
                                Remove file
                            </button>
                        @endif
                        @error('poFile') <p class="hint hint-error mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
                        <label class="label">Notes</label>
                        <textarea wire:model="notes"
                                  rows="4"
                                  placeholder="Internal notes or instructions…"
                                  class="input resize-none"></textarea>
                    </div>

                </div>
                <div class="card-footer !justify-stretch flex-col gap-2">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="save"
                            wire:loading.attr="aria-busy"
                            class="btn btn-primary w-full disabled:cursor-wait disabled:opacity-80">
                        {{-- Both states share one grid cell, so the button never changes size --}}
                        <span class="grid place-items-center [&>*]:col-start-1 [&>*]:row-start-1">
            <span wire:loading.class="invisible" wire:target="save" class="inline-flex items-center gap-2">
                <x-ui.icon name="check" class="h-4 w-4" /> Save purchase order
            </span>
            <span wire:loading.flex wire:target="save" class="hidden items-center gap-2" aria-live="polite">
                <x-ui.icon name="refresh" class="h-4 w-4 animate-spin" /> Saving…
            </span>
        </span>
                    </button>

                    <a href="{{ route('inbound.orders.index') }}" wire:navigate
                       wire:loading.class="pointer-events-none opacity-50" wire:target="save"
                       class="btn btn-ghost w-full">
                        Cancel
                    </a>
                </div>
            </div>
        </div>

    </form>

    @include('components.bulk-line-upload')

</div>
