<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">SKU Master</h2>
            <p class="page-sub">Create and manage product definitions used across all clients.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <form wire:submit="save" class="lg:col-span-2 card">
            <div class="card-header"><h3 class="card-title">New SKU</h3></div>
            <div class="card-body space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label">SKU code *</label>
                        <input wire:model="form.skuCode" type="text" required placeholder="SKU-FL-001" class="input font-mono uppercase">
                        @error('form.skuCode') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Barcode / EAN</label>
                        <input wire:model="form.barcodeEan" type="text" placeholder="6001234567890" class="input tabular-nums">
                    </div>
                    <div>
                        <label class="label">Client *</label>
                        <select wire:model="form.clientId" class="input">
                            <option value="">Select client</option>
                            <option value="1">Azam Consumer Goods Ltd</option>
                            <option value="2">Vodacom Tanzania PLC</option>
                        </select>
                        @error('form.clientId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Item name *</label>
                        <input wire:model="form.itemName" type="text" required placeholder="Unga wa Ngano 2kg" class="input">
                        @error('form.itemName') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Category</label>
                        <input wire:model="form.category" type="text" placeholder="Flour" class="input">
                    </div>
                    <div>
                        <label class="label">Base UOM *</label>
                        <select wire:model="form.baseUom" class="input">
                            <option>Bag</option><option>Case</option><option>Carton</option><option>Pallet</option><option>Unit</option><option>Pack</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Units per pallet</label>
                        <input wire:model="form.unitsPerPallet" type="number" min="1" placeholder="40" class="input tabular-nums">
                    </div>
                    <div>
                        <label class="label">Picking strategy</label>
                        <select wire:model="form.pickingStrategy" class="input">
                            <option>FEFO</option><option>FIFO</option><option>LIFO</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Storage condition</label>
                        <select wire:model="form.storageCondition" class="input">
                            <option>Ambient</option><option>Cold Chain</option><option>Hazardous</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <p class="section-label">Stock Levels</p>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="label">Minimum stock</label>
                            <input wire:model="form.minimumStock" type="number" min="0" class="input tabular-nums">
                        </div>
                        <div>
                            <label class="label">Reorder point</label>
                            <input wire:model="form.reorderPoint" type="number" min="0" class="input tabular-nums">
                        </div>
                        <div>
                            <label class="label">Maximum stock</label>
                            <input wire:model="form.maximumStock" type="number" min="0" class="input tabular-nums">
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300 pt-1">
                        <input wire:model="form.trackExpiry" type="checkbox" class="rounded border-slate-300 text-simba-600 focus:ring-simba-600">
                        Track expiry dates (FEFO enforcement)
                    </label>
                </div>
            </div>
            <div class="card-footer">
                <button type="button" wire:click="$refresh" class="btn btn-secondary">Clear</button>
                <button type="submit" class="btn btn-primary">Create SKU</button>
            </div>
        </form>

        <div class="card self-start">
            <div class="card-header">
                <h3 class="card-title">Recently Added</h3>
                <span class="badge badge-slate">{{ count($recentSkus) }}</span>
            </div>
            <ul>
                @foreach ($recentSkus as $sku)
                    <li class="list-row">
                        <div>
                            <p class="text-sm font-semibold mono">{{ $sku['code'] }}</p>
                            <p class="text-xs text-slate-500">{{ $sku['name'] }}</p>
                            <p class="text-xs text-slate-400">{{ $sku['client'] }} · {{ $sku['uom'] }}</p>
                        </div>
                        <span class="badge badge-slate text-xs">{{ $sku['category'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
