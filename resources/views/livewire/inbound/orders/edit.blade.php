<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <a href="{{ route('inbound.orders.show', $purchaseOrder) }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-simba-600 mb-2">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Back to {{ $purchaseOrder->number }}
            </a>
            <h2 class="page-title">Edit Purchase Order</h2>
            <p class="page-sub font-mono text-sm text-slate-500">{{ $purchaseOrder->number }}</p>
        </div>
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
                        <select wire:model="warehouseId" class="input">
                            <option value="">Select warehouse</option>
                            @foreach ($this->warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                        @error('warehouseId') <p class="hint hint-error">{{ $message }}</p> @enderror
                    </div>

                    {{-- Virtual Warehouse --}}
                    <div>
                        <label class="label">Virtual Warehouse </label>
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
                    <button type="button" wire:click="addLine" class="btn btn-secondary btn-sm">
                        <x-ui.icon name="plus" class="w-4 h-4" /> Add Line
                    </button>
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
                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800 space-y-2">
                        <label class="label">PO Document
                            <span class="text-xs font-normal text-slate-400 ml-1">(PDF, Word, Excel, Image — max 20 MB)</span>
                        </label>

                        @if ($purchaseOrder->po_file_path && ! $removeDoc && ! $poFile)
                            {{-- Current document --}}
                            <div class="flex items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm">
                                <x-ui.icon name="document" class="w-4 h-4 text-slate-400 shrink-0" />
                                <span class="flex-1 truncate text-slate-700 dark:text-slate-300 font-mono text-xs">
                                    {{ basename($purchaseOrder->po_file_path) }}
                                </span>
                                <a href="{{ route('inbound.orders.document.download', $purchaseOrder) }}"
                                   target="_blank"
                                   class="text-xs text-brand hover:underline shrink-0">View</a>
                            </div>
                            <div class="flex gap-2">
                                <label class="flex-1 flex items-center gap-2 cursor-pointer border border-dashed border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 hover:border-simba-400 transition-colors text-xs text-slate-500">
                                    <x-ui.icon name="refresh" class="w-3.5 h-3.5 shrink-0" />
                                    Replace document
                                    <input type="file" wire:model="poFile" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" class="sr-only">
                                </label>
                                <button type="button" wire:click="$set('removeDoc', true)"
                                        class="btn btn-ghost text-xs text-red-500 hover:text-red-600 px-2.5">
                                    <x-ui.icon name="trash" class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        @elseif ($removeDoc)
                            <div class="flex items-center gap-2 rounded-lg border border-red-200 dark:border-red-900/50 bg-red-50/60 dark:bg-red-950/20 px-3 py-2 text-xs text-red-600 dark:text-red-400">
                                <x-ui.icon name="exclamation" class="w-3.5 h-3.5 shrink-0" />
                                Document will be removed on save.
                                <button type="button" wire:click="$set('removeDoc', false)" class="ml-auto underline">Undo</button>
                            </div>
                        @else
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
                                <input type="file" wire:model="poFile" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" class="sr-only">
                            </label>
                            @if ($poFile)
                                <button type="button" wire:click="$set('poFile', null)" class="text-xs text-red-500 hover:underline">Remove file</button>
                            @endif
                        @endif
                        @error('poFile') <p class="hint hint-error">{{ $message }}</p> @enderror
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
                            class="btn btn-primary w-full">
                        <span wire:loading.remove wire:target="save">
                            <x-ui.icon name="check" class="w-4 h-4" /> Save Changes
                        </span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                    <a href="{{ route('inbound.orders.show', $purchaseOrder) }}" wire:navigate class="btn btn-secondary w-full">
                        Cancel
                    </a>
                </div>
            </div>
        </div>

    </form>

</div>
