<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">Internal Movements</h2>
            <p class="page-sub">Move stock between bins within the same warehouse.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <form wire:submit="save" class="lg:col-span-2 card">
            <div class="card-body space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label">SKU code *</label>
                        <input wire:model="form.skuCode" type="text" required placeholder="SKU-AZ-001" class="input font-mono">
                        @error('form.skuCode') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Quantity to move *</label>
                        <input wire:model="form.quantity" type="number" required min="1" placeholder="50" class="input tabular-nums">
                        @error('form.quantity') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-[1fr_auto_1fr] gap-4 items-end">
                    <div>
                        <label class="label">Source bin *</label>
                        <input wire:model="form.sourceBin" type="text" required placeholder="DAR-A-01-01-A" class="input font-mono uppercase">
                        @error('form.sourceBin') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="hidden md:flex items-center justify-center h-[38px] w-10 text-slate-400">
                        <x-ui.icon name="arrow-right" class="w-5 h-5" />
                    </div>
                    <div>
                        <label class="label">Target bin *</label>
                        <input wire:model="form.targetBin" type="text" required placeholder="DAR-A-03-02-B" class="input font-mono uppercase">
                        @error('form.targetBin') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="label">Movement reason *</label>
                    <select wire:model="form.movementReason" class="input">
                        <option>Replenishment to pick face</option>
                        <option>Consolidation</option>
                        <option>Re-slotting</option>
                        <option>Damaged stock quarantine</option>
                    </select>
                </div>
                <div>
                    <label class="label">Notes</label>
                    <textarea wire:model="form.notes" rows="2" class="input" placeholder="Optional"></textarea>
                </div>
            </div>
            <div class="card-footer">
                <button type="button" wire:click="$refresh" class="btn btn-secondary">Clear</button>
                <button type="submit" class="btn btn-primary">Execute Movement</button>
            </div>
        </form>

        <div class="card self-start">
            <div class="card-header"><h3 class="card-title">Recent Movements</h3></div>
            <ul>
                @foreach ($recent as $item)
                    <li class="list-row">
                        <div>
                            <p class="text-sm font-semibold mono">{{ $item['ref'] }}</p>
                            <p class="text-xs text-slate-500 mono">{{ $item['from'] }} to {{ $item['to'] }}</p>
                        </div>
                        <span class="text-sm font-semibold tabular-nums">{{ number_format($item['qty']) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
