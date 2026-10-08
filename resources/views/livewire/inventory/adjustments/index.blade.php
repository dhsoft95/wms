<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">Stock Adjustments</h2>
            <p class="page-sub">Correct system balances after counts, damages or found stock.</p>
        </div>
    </div>

    <form wire:submit="save" class="card max-w-4xl">
        <div class="card-body space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="label">SKU code *</label>
                    <input wire:model="form.skuCode" type="text" required class="input font-mono">
                    @error('form.skuCode') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Location bin *</label>
                    <input wire:model="form.locationBin" type="text" required class="input font-mono uppercase">
                    @error('form.locationBin') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Reason code *</label>
                    <select wire:model="form.reasonCode" class="input">
                        <option>CYCLE_COUNT_VARIANCE</option>
                        <option>DAMAGED_IN_STORAGE</option>
                        <option>FOUND_STOCK</option>
                        <option>EXPIRED_WRITE_OFF</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700/60">
                <div>
                    <label class="label">System quantity</label>
                    <input type="number" readonly value="{{ $form->systemQuantity }}" class="input tabular-nums">
                </div>
                <div>
                    <label class="label">Physical count *</label>
                    <input wire:model.live="form.physicalCount" type="number" required min="0" class="input tabular-nums">
                    @error('form.physicalCount') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Variance</label>
                    <div class="h-[38px] flex items-center">
                        @php
                            $variance = $this->variance;
                            $vBadge = $variance === 0 ? 'badge-slate' : ($variance < 0 ? 'badge-red' : 'badge-amber');
                        @endphp
                        <span class="badge {{ $vBadge }} !text-sm !px-3 !py-1">
                            {{ ($variance > 0 ? '+' : '') . $variance }}
                        </span>
                    </div>
                </div>
            </div>

            <div>
                <label class="label">Justification *</label>
                <textarea wire:model="form.justification" rows="3" required placeholder="Explain why the adjustment is needed..." class="input"></textarea>
                @error('form.justification') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="alert-info">
                <x-ui.icon name="info" class="w-5 h-5 shrink-0" />
                <p>Adjustments do not change stock until approved. Approved adjustments are written to the audit log with your user ID.</p>
            </div>
        </div>
        <div class="card-footer">
            <button type="button" wire:click="$refresh" class="btn btn-secondary">Clear</button>
            <button type="submit" class="btn btn-primary">Request Approval</button>
        </div>
    </form>
</div>
