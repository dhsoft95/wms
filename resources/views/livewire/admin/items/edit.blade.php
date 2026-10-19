<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">
                Edit Item:
                <span class="font-mono text-simba-600 dark:text-simba-400">{{ $item->sku }}</span>
            </h2>
            <p class="page-sub">{{ $item->name }}</p>
        </div>
        <a href="{{ route('admin.items.show', $item) }}" wire:navigate class="btn btn-secondary">
            <x-ui.icon name="x" class="w-4 h-4" /> Cancel
        </a>
    </div>

    <form wire:submit="save" class="space-y-6">

        {{-- Tab Bar --}}
        <div class="flex gap-1 overflow-x-auto rounded-xl
                    bg-white/40 dark:bg-white/[0.06]
                    border border-white/50 dark:border-white/[0.08]
                    p-1">
            @foreach ([
                'general'  => 'General',
                'uoms'     => 'Units & Packing',
                'barcodes' => 'Barcodes',
                'tracking' => 'Tracking',
                'stock'    => 'Stock Levels',
                'rules'    => 'Rules & Approvals',
            ] as $tab => $label)
                <button type="button"
                        wire:click="$set('activeTab', '{{ $tab }}')"
                        class="shrink-0 rounded-lg px-4 py-2 text-sm font-medium transition
                               {{ $activeTab === $tab
                                   ? 'bg-white dark:bg-white/[0.12] text-slate-900 dark:text-white shadow-sm'
                                   : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- ── GENERAL TAB ──────────────────────────────────────────────────── --}}
        @if ($activeTab === 'general')
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

            {{-- Left: Core identity --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <x-ui.icon name="package" class="w-4 h-4" />
                        Identity
                    </h3>
                </div>
                <div class="card-body space-y-5">

                    <div>
                        <label class="label" for="sku">SKU <span class="text-red-500">*</span></label>
                        <input wire:model="sku" id="sku" type="text"
                               class="input font-mono @error('sku') border-red-400 @enderror" autocomplete="off" />
                        @error('sku') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="label" for="name">Name <span class="text-red-500">*</span></label>
                        <input wire:model="name" id="name" type="text"
                               class="input @error('name') border-red-400 @enderror" autocomplete="off" />
                        @error('name') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="label" for="description">
                            Description <span class="text-slate-400 font-normal">(optional)</span>
                        </label>
                        <textarea wire:model="description" id="description" rows="3"
                                  class="input @error('description') border-red-400 @enderror"></textarea>
                        @error('description') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="label" for="categoryId">Category</label>
                        <select wire:model="categoryId" id="categoryId"
                                class="input @error('categoryId') border-red-400 @enderror">
                            <option value="">— No category —</option>
                            @foreach ($this->categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        @error('categoryId') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input wire:model="isActive" type="checkbox"
                               class="rounded border-slate-300 dark:border-slate-600 text-simba-600 focus:ring-simba-600 dark:bg-zinc-800" />
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Active</span>
                    </label>

                </div>
            </div>

            {{-- Right: UoM, VW, Dimensions --}}
            <div class="space-y-6">

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <x-ui.icon name="refresh" class="w-4 h-4" />
                            Units & Warehouse
                        </h3>
                    </div>
                    <div class="card-body space-y-5">

                        <div>
                            <label class="label" for="baseUomId">Base Unit of Measure <span class="text-red-500">*</span></label>
                            <select wire:model="baseUomId" id="baseUomId"
                                    class="input @error('baseUomId') border-red-400 @enderror">
                                <option value="">Select UoM…</option>
                                @foreach ($this->uomOptions as $uom)
                                    <option value="{{ $uom->id }}">{{ $uom->code }} — {{ $uom->name }}</option>
                                @endforeach
                            </select>
                            @error('baseUomId') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="label" for="defaultVwId">Default Virtual Warehouse</label>
                            <select wire:model="defaultVwId" id="defaultVwId"
                                    class="input @error('defaultVwId') border-red-400 @enderror">
                                <option value="">— None —</option>
                                @foreach ($this->virtualWarehouses as $vw)
                                    <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                                @endforeach
                            </select>
                            @error('defaultVwId') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                        </div>

                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <x-ui.icon name="arrows" class="w-4 h-4" />
                            Dimensions &amp; Weight
                        </h3>
                    </div>
                    <div class="card-body space-y-5">

                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="label" for="length">Length</label>
                                <input wire:model="length" id="length" type="number" step="0.001" min="0"
                                       class="input @error('length') border-red-400 @enderror" />
                                @error('length') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label" for="width">Width</label>
                                <input wire:model="width" id="width" type="number" step="0.001" min="0"
                                       class="input @error('width') border-red-400 @enderror" />
                                @error('width') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label" for="height">Height</label>
                                <input wire:model="height" id="height" type="number" step="0.001" min="0"
                                       class="input @error('height') border-red-400 @enderror" />
                                @error('height') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="label" for="weight">Weight</label>
                            <input wire:model="weight" id="weight" type="number" step="0.001" min="0"
                                   class="input @error('weight') border-red-400 @enderror" />
                            @error('weight') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                        </div>

                    </div>
                </div>

            </div>
        </div>
        @endif

        {{-- ── UNITS & PACKING TAB ──────────────────────────────────────────── --}}
        @if ($activeTab === 'uoms')
        <div class="card overflow-hidden">
            <div class="card-header">
                <h3 class="card-title">
                    <x-ui.icon name="refresh" class="w-4 h-4" />
                    Units of Measure
                </h3>
            </div>

            <div class="px-5 py-4">

                {{-- Base UoM locked row --}}
                @php
                    $baseUomObj = $this->uomOptions->firstWhere('id', $baseUomId);
                @endphp
                <div class="mb-4 flex items-center gap-3 rounded-xl
                            bg-simba-50/60 dark:bg-simba-950/30
                            border border-simba-100 dark:border-simba-900/40
                            px-4 py-3">
                    <x-ui.icon name="shield" class="h-4 w-4 text-simba-500 shrink-0" />
                    <div class="text-sm">
                        <span class="font-semibold text-simba-800 dark:text-simba-300 font-mono">
                            {{ $baseUomObj?->code ?? $baseUomId }}
                        </span>
                        <span class="ml-2 text-simba-600 dark:text-simba-400">— Base UoM (factor: 1, locked)</span>
                    </div>
                </div>

                @if (count($uoms) > 0)
                <x-ui.table class="min-w-[600px]">
                        <thead class="text-[11px] font-semibold uppercase tracking-wider
                                      text-slate-500 dark:text-slate-400
                                      border-b border-white/50 dark:border-white/[0.07]">
                            <tr>
                                <th class="py-2 pr-4 text-left">Unit of Measure</th>
                                <th class="py-2 pr-4 text-left">Factor to Base</th>
                                <th class="py-2 pr-4 text-center">Default Purchase</th>
                                <th class="py-2 pr-4 text-center">Default Issue</th>
                                <th class="py-2 text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/40 dark:divide-white/[0.06]">
                        @foreach ($uoms as $i => $row)
                            <tr wire:key="uom-row-{{ $i }}">
                                <td class="py-2.5 pr-4">
                                    <select wire:model="uoms.{{ $i }}.uom_id"
                                            class="input @error('uoms.'.$i.'.uom_id') border-red-400 @enderror">
                                        <option value="">Select UoM…</option>
                                        @foreach ($this->uomOptions as $uom)
                                            <option value="{{ $uom->id }}">{{ $uom->code }} — {{ $uom->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('uoms.'.$i.'.uom_id')
                                        <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td class="py-2.5 pr-4">
                                    <input wire:model="uoms.{{ $i }}.factor_to_base"
                                           type="number" step="0.0001" min="0.0001"
                                           class="input font-mono @error('uoms.'.$i.'.factor_to_base') border-red-400 @enderror" />
                                    @error('uoms.'.$i.'.factor_to_base')
                                        <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td class="py-2.5 pr-4 text-center">
                                    <input wire:model="uoms.{{ $i }}.is_default_purchase" type="checkbox"
                                           class="rounded border-slate-300 dark:border-slate-600 text-simba-600 focus:ring-simba-600 dark:bg-zinc-800" />
                                </td>
                                <td class="py-2.5 pr-4 text-center">
                                    <input wire:model="uoms.{{ $i }}.is_default_issue" type="checkbox"
                                           class="rounded border-slate-300 dark:border-slate-600 text-simba-600 focus:ring-simba-600 dark:bg-zinc-800" />
                                </td>
                                <td class="py-2.5 text-right">
                                    <button type="button" wire:click="removeUomRow({{ $i }})"
                                            class="icon-btn hover:text-rose-600 dark:hover:text-rose-400">
                                        <x-ui.icon name="trash" class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                </x-ui.table>
                @endif

                <div class="mt-4">
                    <button type="button" wire:click="addUomRow" class="btn btn-secondary btn-sm">
                        <x-ui.icon name="plus" class="h-4 w-4" />
                        Add UoM
                    </button>
                </div>

            </div>
        </div>
        @endif

        {{-- ── BARCODES TAB ─────────────────────────────────────────────────── --}}
        @if ($activeTab === 'barcodes')
        <div class="card overflow-hidden">
            <div class="card-header">
                <h3 class="card-title">
                    <x-ui.icon name="search" class="w-4 h-4" />
                    Barcodes
                </h3>
            </div>

            <div class="px-5 py-4">

                @if (count($barcodes) > 0)
                <x-ui.table class="min-w-[600px]">
                        <thead class="text-[11px] font-semibold uppercase tracking-wider
                                      text-slate-500 dark:text-slate-400
                                      border-b border-white/50 dark:border-white/[0.07]">
                            <tr>
                                <th class="py-2 pr-4 text-left">Barcode</th>
                                <th class="py-2 pr-4 text-left">Unit</th>
                                <th class="py-2 pr-4 text-left">Type</th>
                                <th class="py-2 pr-4 text-center">Primary</th>
                                <th class="py-2 text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/40 dark:divide-white/[0.06]">
                        @foreach ($barcodes as $i => $row)
                            <tr wire:key="barcode-row-{{ $i }}">
                                <td class="py-2.5 pr-4">
                                    <input wire:model="barcodes.{{ $i }}.barcode"
                                           type="text"
                                           class="input font-mono @error('barcodes.'.$i.'.barcode') border-red-400 @enderror" />
                                    @error('barcodes.'.$i.'.barcode')
                                        <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td class="py-2.5 pr-4">
                                    <select wire:model="barcodes.{{ $i }}.uom_id"
                                            class="input @error('barcodes.'.$i.'.uom_id') border-red-400 @enderror">
                                        <option value="">Select UoM…</option>
                                        @foreach ($this->uomOptions as $uom)
                                            <option value="{{ $uom->id }}">{{ $uom->code }}</option>
                                        @endforeach
                                    </select>
                                    @error('barcodes.'.$i.'.uom_id')
                                        <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td class="py-2.5 pr-4">
                                    <select wire:model="barcodes.{{ $i }}.type" class="input">
                                        <option value="internal">Internal</option>
                                        <option value="supplier">Supplier</option>
                                    </select>
                                </td>
                                <td class="py-2.5 pr-4 text-center">
                                    <input wire:model="barcodes.{{ $i }}.is_primary" type="checkbox"
                                           class="rounded border-slate-300 dark:border-slate-600 text-simba-600 focus:ring-simba-600 dark:bg-zinc-800" />
                                </td>
                                <td class="py-2.5 text-right">
                                    <button type="button" wire:click="removeBarcodeRow({{ $i }})"
                                            class="icon-btn hover:text-rose-600 dark:hover:text-rose-400">
                                        <x-ui.icon name="trash" class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                </x-ui.table>
                @endif

                <div class="mt-4">
                    <button type="button" wire:click="addBarcodeRow" class="btn btn-secondary btn-sm">
                        <x-ui.icon name="plus" class="h-4 w-4" />
                        Add Barcode
                    </button>
                </div>

            </div>
        </div>
        @endif

        {{-- ── TRACKING TAB ─────────────────────────────────────────────────── --}}
        @if ($activeTab === 'tracking')
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <x-ui.icon name="shield" class="w-4 h-4" />
                    Tracking Flags
                </h3>
            </div>
            <div class="card-body space-y-5">

                @if ($trackExpiry)
                    <div class="flex items-start gap-3 rounded-xl
                                bg-amber-50/60 dark:bg-amber-950/30
                                border border-amber-200 dark:border-amber-900/40 px-4 py-3" wire:transition>
                        <x-ui.icon name="refresh" class="h-4 w-4 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" />
                        <p class="text-sm text-amber-700 dark:text-amber-400">
                            Expiry tracking is enabled — batch tracking has been forced on.
                        </p>
                    </div>
                @endif

                <label class="flex items-start gap-3 cursor-pointer">
                    <div class="pt-0.5">
                        <input wire:model="trackBatch" type="checkbox"
                               @if($trackExpiry) disabled @endif
                               class="rounded border-slate-300 dark:border-slate-600 text-simba-600 focus:ring-simba-600 dark:bg-zinc-800
                                      {{ $trackExpiry ? 'opacity-60 cursor-not-allowed' : '' }}" />
                    </div>
                    <div>
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Track Batch</span>
                        <p class="hint mt-0.5">Required at receipt. Enables lot/batch management.</p>
                    </div>
                </label>

                <label class="flex items-start gap-3 cursor-pointer">
                    <div class="pt-0.5">
                        <input wire:model.live="trackExpiry" type="checkbox"
                               class="rounded border-slate-300 dark:border-slate-600 text-simba-600 focus:ring-simba-600 dark:bg-zinc-800" />
                    </div>
                    <div>
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Track Expiry</span>
                        <p class="hint mt-0.5">Enables FEFO (First Expired, First Out) picking. Forces batch tracking.</p>
                    </div>
                </label>

                <label class="flex items-start gap-3 cursor-pointer">
                    <div class="pt-0.5">
                        <input wire:model="trackSerial" type="checkbox"
                               class="rounded border-slate-300 dark:border-slate-600 text-simba-600 focus:ring-simba-600 dark:bg-zinc-800" />
                    </div>
                    <div>
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Track Serial Numbers</span>
                        <p class="hint mt-0.5">Each individual unit is tracked with a unique serial number.</p>
                    </div>
                </label>

                <div class="border-t border-slate-200 dark:border-slate-700 pt-5">
                    <label class="label" for="preferredZoneId">Preferred Putaway Zone</label>
                    <select wire:model="preferredZoneId" id="preferredZoneId"
                            class="input @error('preferredZoneId') border-red-400 @enderror">
                        <option value="">— No preference —</option>
                        @foreach ($this->zones as $zone)
                            <option value="{{ $zone->id }}">{{ $zone->code }}</option>
                        @endforeach
                    </select>
                    <p class="hint mt-1">The putaway engine will try to place new stock in bins within this zone.</p>
                    @error('preferredZoneId')
                        <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                    @enderror
                </div>

            </div>
        </div>
        @endif

        {{-- ── STOCK LEVELS TAB ─────────────────────────────────────────────── --}}
        @if ($activeTab === 'stock')
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <x-ui.icon name="arrows" class="w-4 h-4" />
                    Stock Level Thresholds
                </h3>
            </div>
            <div class="card-body space-y-5">

                @php
                    $baseUomCode = $this->uomOptions->firstWhere('id', $baseUomId)?->code ?? 'base unit';
                @endphp

                <div>
                    <label class="label" for="minStock">Minimum Stock</label>
                    <div class="flex items-center gap-2">
                        <input wire:model="minStock" id="minStock" type="number" step="0.0001" min="0"
                               class="input font-mono @error('minStock') border-red-400 @enderror" />
                        <span class="shrink-0 text-sm text-slate-500 dark:text-slate-400 font-mono">{{ $baseUomCode }}</span>
                    </div>
                    @error('minStock') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                    <p class="hint mt-1">Alert triggered when stock falls below this level.</p>
                </div>

                <div>
                    <label class="label" for="maxStock">Maximum Stock</label>
                    <div class="flex items-center gap-2">
                        <input wire:model="maxStock" id="maxStock" type="number" step="0.0001" min="0"
                               class="input font-mono @error('maxStock') border-red-400 @enderror" />
                        <span class="shrink-0 text-sm text-slate-500 dark:text-slate-400 font-mono">{{ $baseUomCode }}</span>
                    </div>
                    @error('maxStock') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                    <p class="hint mt-1">Alert triggered when stock exceeds this level.</p>
                </div>

            </div>
        </div>
        @endif

        {{-- ── RULES & APPROVALS TAB ──────────────────────────────────────── --}}
        @if ($activeTab === 'rules')
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

            {{-- Left: Allocation & Variance --}}
            <div class="space-y-6">

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <x-ui.icon name="adjustments" class="w-4 h-4" />
                            Allocation Rules
                        </h3>
                    </div>
                    <div class="card-body space-y-3">
                        <p class="hint">Select one or more. FIFO and LIFO cannot be combined. Leave empty to use the global warehouse default.</p>
                        @foreach (['fifo' => 'FIFO — First In, First Out', 'fefo' => 'FEFO — First Expired, First Out', 'lifo' => 'LIFO — Last In, First Out'] as $rule => $ruleLabel)
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox"
                                       wire:model="allocationRules"
                                       value="{{ $rule }}"
                                       class="rounded border-slate-300 dark:border-slate-600 text-simba-600 focus:ring-simba-600 dark:bg-zinc-800" />
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $ruleLabel }}</span>
                            </label>
                        @endforeach
                        @error('allocationRules') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <x-ui.icon name="clipboard" class="w-4 h-4" />
                            Variance Thresholds
                        </h3>
                    </div>
                    <div class="card-body space-y-5">
                        <div>
                            <label class="label" for="varianceThresholdPct">Variance Threshold (%)</label>
                            <div class="flex items-center gap-2">
                                <input wire:model="varianceThresholdPct" id="varianceThresholdPct"
                                       type="number" step="0.01" min="0" max="100" placeholder="e.g. 5.00"
                                       class="input font-mono @error('varianceThresholdPct') border-red-400 @enderror" />
                                <span class="shrink-0 text-sm text-slate-500 dark:text-slate-400">%</span>
                            </div>
                            @error('varianceThresholdPct') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                            <p class="hint mt-1">GRN variance above this percentage requires approval.</p>
                        </div>
                        <div>
                            <label class="label" for="varianceThresholdQty">Variance Threshold (Qty)</label>
                            <input wire:model="varianceThresholdQty" id="varianceThresholdQty"
                                   type="number" step="0.0001" min="0" placeholder="e.g. 10"
                                   class="input font-mono @error('varianceThresholdQty') border-red-400 @enderror" />
                            @error('varianceThresholdQty') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                            <p class="hint mt-1">GRN variance above this absolute quantity requires approval.</p>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Right: Dispatch & Adjustment Approval --}}
            <div class="space-y-6">

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <x-ui.icon name="truck" class="w-4 h-4" />
                            Dispatch Approval
                        </h3>
                    </div>
                    <div class="card-body space-y-5">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <div class="pt-0.5">
                                <input wire:model.live="dispatchRequiresApproval" type="checkbox"
                                       class="rounded border-slate-300 dark:border-slate-600 text-simba-600 focus:ring-simba-600 dark:bg-zinc-800" />
                            </div>
                            <div>
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Require Dispatch Approval</span>
                                <p class="hint mt-0.5">Delivery orders containing this item will require warehouse manager approval before dispatch.</p>
                            </div>
                        </label>
                        @if ($dispatchRequiresApproval)
                        <div wire:transition>
                            <label class="label" for="dispatchApprovalQty">Approval Threshold (Qty)</label>
                            <input wire:model="dispatchApprovalQty" id="dispatchApprovalQty"
                                   type="number" step="0.0001" min="0" placeholder="Leave blank to always require"
                                   class="input font-mono @error('dispatchApprovalQty') border-red-400 @enderror" />
                            @error('dispatchApprovalQty') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                            <p class="hint mt-1">Only require approval when line quantity exceeds this threshold. Leave blank to always require approval.</p>
                        </div>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <x-ui.icon name="shield" class="w-4 h-4" />
                            Adjustment Approval
                        </h3>
                    </div>
                    <div class="card-body">
                        <label class="label" for="adjustmentApproval">Approval Requirement</label>
                        <select wire:model="adjustmentApproval" id="adjustmentApproval"
                                class="input @error('adjustmentApproval') border-red-400 @enderror">
                            <option value="inherit">Inherit from Category</option>
                            <option value="required">Always Require Approval</option>
                            <option value="not_required">Never Require Approval</option>
                        </select>
                        @error('adjustmentApproval') <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p> @enderror
                        <p class="hint mt-1">Overrides the category-level adjustment approval setting for this specific item.</p>
                    </div>
                </div>

            </div>
        </div>
        @endif

        {{-- Footer Actions + Tab Navigation --}}
        <div class="flex flex-col-reverse gap-3 pt-4 sm:flex-row sm:items-center sm:justify-between
                    border-t border-white/40 dark:border-white/[0.08]">

            {{-- Prev / Next --}}
            <div class="flex gap-2">
                @php
                    $tabs = ['general', 'uoms', 'barcodes', 'tracking', 'stock', 'rules'];
                    $currentIndex = array_search($activeTab, $tabs);
                @endphp

                @if ($currentIndex > 0)
                    <button type="button"
                            wire:click="$set('activeTab', '{{ $tabs[$currentIndex - 1] }}')"
                            class="btn btn-secondary btn-sm">
                        ← Previous
                    </button>
                @endif

                @if ($currentIndex < count($tabs) - 1)
                    <button type="button"
                            wire:click="$set('activeTab', '{{ $tabs[$currentIndex + 1] }}')"
                            class="btn btn-secondary btn-sm">
                        Next →
                    </button>
                @endif
            </div>

            {{-- Save --}}
            <div class="flex gap-3">
                <a href="{{ route('admin.items.show', $item) }}" wire:navigate class="btn btn-secondary text-center">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary justify-center">
                    <x-ui.icon name="check" class="w-4 h-4" />
                    Save Changes
                </button>
            </div>

        </div>

    </form>

</div>
