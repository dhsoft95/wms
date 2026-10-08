<div class="space-y-6">
    <div class="page-header">
        <div>
            <a href="{{ route('inbound.grn.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-simba-600 mb-2">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Back to GRNs
            </a>
            <h2 class="page-title">New Goods Receipt</h2>
            <p class="page-sub">Record what was physically received against what was expected.</p>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        {{-- Header --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Receipt Details</h3>
                <span class="badge badge-slate">Draft</span>
            </div>
            <div class="card-body grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="label">GRN number</label>
                    <input type="text" readonly value="{{ $form->grnNumber }}" class="input font-mono">
                </div>
                <div>
                    <label class="label">Container reference *</label>
                    <select wire:model="form.containerId" class="input font-mono">
                        <option value="">Select container</option>
                        <option value="MSKU1234567">MSKU1234567</option>
                        <option value="MRKU8829102">MRKU8829102</option>
                    </select>
                    @error('form.containerId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Supplier / vendor *</label>
                    <select wire:model="form.supplierId" class="input">
                        <option value="">Select supplier</option>
                        <option value="1">Azam Consumer Goods Ltd</option>
                        <option value="2">Vodacom Tanzania PLC</option>
                    </select>
                    @error('form.supplierId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Receiving warehouse *</label>
                    <select wire:model="form.receivingWarehouse" class="input">
                        <option value="">Select warehouse</option>
                        <option>DAR-General Store</option><option>DAR-Tech Vault</option><option>Cold Room</option>
                    </select>
                    @error('form.receivingWarehouse') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Received at *</label>
                    <input wire:model="form.receivedAt" type="datetime-local" required class="input tabular-nums">
                    @error('form.receivedAt') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Delivery note reference</label>
                    <input wire:model="form.deliveryNoteRef" type="text" placeholder="DN-77812" class="input font-mono">
                </div>
            </div>
        </div>

        {{-- Line items --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Line Items</h3>
                    <p class="text-xs text-slate-500 mt-0.5">{{ count($lines) }} lines</p>
                </div>
                <button type="button" wire:click="addLine" class="btn btn-secondary btn-sm">
                    <x-ui.icon name="plus" class="w-3.5 h-3.5" /> Add Line
                </button>
            </div>
            <div class="card-body space-y-3">
                <div class="hidden md:grid grid-cols-12 gap-3 px-3 section-label">
                    <span class="col-span-3">SKU Code</span><span class="col-span-2">Expected</span><span class="col-span-2">Received</span><span class="col-span-2">Batch No.</span><span class="col-span-2">Variance</span><span class="col-span-1"></span>
                </div>

                @foreach ($lines as $i => $line)
                    @php
                        $variance = ($line['received_qty'] ?? 0) - ($line['expected_qty'] ?? 0);
                        $vBadge = $variance === 0 ? 'badge-green' : ($variance < 0 ? 'badge-red' : 'badge-amber');
                    @endphp
                    <div class="grid grid-cols-2 md:grid-cols-12 gap-3 items-center p-3 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200/70 dark:border-slate-700/50">
                        <div class="col-span-2 md:col-span-3">
                            <input wire:model="lines.{{ $i }}.sku_code" type="text" placeholder="SKU code" class="input input-sm font-mono">
                        </div>
                        <div class="md:col-span-2">
                            <input wire:model.live="lines.{{ $i }}.expected_qty" type="number" min="0" placeholder="Expected" class="input input-sm tabular-nums">
                        </div>
                        <div class="md:col-span-2">
                            <input wire:model.live="lines.{{ $i }}.received_qty" type="number" min="0" placeholder="Received" class="input input-sm tabular-nums">
                        </div>
                        <div class="md:col-span-2">
                            <input wire:model="lines.{{ $i }}.batch_number" type="text" placeholder="Batch no." class="input input-sm font-mono">
                        </div>
                        <div class="md:col-span-2">
                            <span class="badge {{ $vBadge }}">
                                {{ $line['received_qty'] === '' ? 'Pending' : (($variance > 0 ? '+' : '') . $variance) }}
                            </span>
                        </div>
                        <div class="md:col-span-1 flex justify-end">
                            <button type="button" wire:click="removeLine({{ $i }})" class="icon-btn hover:!text-red-600">
                                <x-ui.icon name="trash" class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-3 border-t border-slate-200 dark:border-slate-800 divide-x divide-slate-200 dark:divide-slate-800 text-center">
                <div class="p-4"><p class="section-label">Expected</p><p class="mt-1 text-xl font-bold tabular-nums">{{ number_format($expectedTotal) }}</p></div>
                <div class="p-4"><p class="section-label">Received</p><p class="mt-1 text-xl font-bold tabular-nums">{{ number_format($receivedTotal) }}</p></div>
                <div class="p-4">
                    <p class="section-label">Variance</p>
                    <p class="mt-1 text-xl font-bold tabular-nums {{ $varianceTotal < 0 ? 'text-red-600' : ($varianceTotal > 0 ? 'text-amber-600' : 'text-emerald-600') }}">
                        {{ ($varianceTotal > 0 ? '+' : '') . number_format($varianceTotal) }}
                    </p>
                </div>
            </div>
        </div>

        <div class="flex flex-col-reverse sm:flex-row justify-end gap-3">
            <a href="{{ route('inbound.grn.index') }}" wire:navigate class="btn btn-secondary">Cancel</a>
            <button type="button" wire:click="saveDraft" class="btn btn-secondary">Save Draft</button>
            <a href="{{ route('inbound.inspections.index') }}" wire:navigate class="btn btn-secondary">Log Discrepancy</a>
            <button type="submit" class="btn btn-primary">Submit GRN</button>
        </div>
    </form>
</div>
