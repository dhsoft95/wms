<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">New Customer Order</h2>
            <p class="page-sub">Create an order or material request and queue it for allocation.</p>
        </div>
    </div>

    <form wire:submit="save" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Order Details</h3></div>
                <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Order reference</label>
                        <input type="text" readonly value="{{ $form->orderReference }}" class="input font-mono">
                    </div>
                    <div>
                        <label class="label">Client / customer *</label>
                        <select wire:model="form.clientId" class="input">
                            <option value="">Select client</option>
                            <option value="1">Azam Consumer Goods Ltd</option>
                            <option value="2">Vodacom Tanzania PLC</option>
                        </select>
                        @error('form.clientId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Required delivery date *</label>
                        <input wire:model="form.requiredDeliveryDate" type="date" required class="input tabular-nums">
                        @error('form.requiredDeliveryDate') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Source warehouse *</label>
                        <select wire:model="form.sourceWarehouse" class="input">
                            <option>DAR-General Store</option><option>DAR-Tech Vault</option><option>Cold Room</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="label">Delivery address *</label>
                        <input wire:model="form.deliveryAddress" type="text" required placeholder="Mikocheni Industrial Area, Dar es Salaam" class="input">
                        @error('form.deliveryAddress') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Contact person</label>
                        <input wire:model="form.contactPerson" type="text" placeholder="Rashid Ally" class="input">
                    </div>
                    <div>
                        <label class="label">Contact phone</label>
                        <input wire:model="form.contactPhone" type="tel" placeholder="+255 712 000 000" class="input tabular-nums">
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Order Lines</h3>
                    <button type="button" wire:click="addLine" class="btn btn-secondary btn-sm">
                        <x-ui.icon name="plus" class="w-3.5 h-3.5" /> Add Line
                    </button>
                </div>
                <div class="card-body space-y-3">
                    <div class="hidden md:grid grid-cols-12 gap-3 px-3 section-label">
                        <span class="col-span-3">SKU Code</span><span class="col-span-5">Description</span><span class="col-span-3">Quantity</span><span class="col-span-1"></span>
                    </div>
                    @foreach ($lines as $i => $line)
                        <div class="grid grid-cols-2 md:grid-cols-12 gap-3 items-center p-3 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200/70 dark:border-slate-700/50">
                            <div class="md:col-span-3">
                                <input wire:model="lines.{{ $i }}.sku_code" type="text" placeholder="SKU code" class="input input-sm font-mono">
                            </div>
                            <div class="md:col-span-5">
                                <input wire:model="lines.{{ $i }}.description" type="text" placeholder="Description" class="input input-sm">
                            </div>
                            <div class="md:col-span-3">
                                <input wire:model.live="lines.{{ $i }}.quantity" type="number" min="1" placeholder="Qty" class="input input-sm tabular-nums">
                            </div>
                            <div class="md:col-span-1 flex justify-end">
                                <button type="button" wire:click="removeLine({{ $i }})" class="icon-btn hover:!text-red-600">
                                    <x-ui.icon name="trash" class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card self-start lg:sticky lg:top-0">
            <div class="card-header"><h3 class="card-title">Order Summary</h3></div>
            <div class="card-body space-y-3 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">Lines</span><span class="font-semibold tabular-nums">{{ $lineCount }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Total quantity</span><span class="font-semibold tabular-nums">{{ number_format($totalQty) }}</span></div>
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
                    <label class="label">Priority</label>
                    <select wire:model="form.priority" class="input">
                        <option>Standard</option><option>Express</option><option>Critical</option>
                    </select>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300 pt-1">
                    <input wire:model="form.autoAllocate" type="checkbox" class="rounded border-slate-300 text-simba-600 focus:ring-simba-600">
                    Auto-allocate using FEFO
                </label>
            </div>
            <div class="card-footer !justify-stretch flex-col">
                <button type="submit" class="btn btn-primary w-full">Save &amp; Allocate Order</button>
                <button type="button" wire:click="saveDraft" class="btn btn-secondary w-full">Save Draft</button>
            </div>
        </div>
    </form>
</div>
