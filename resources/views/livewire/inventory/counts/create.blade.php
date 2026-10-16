<div class="space-y-6">
    <div class="page-header">
        <div>
            <a href="{{ route('inventory.counts.index') }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-simba-600 mb-2">
                <x-ui.icon name="chevron-right" class="w-3.5 h-3.5 rotate-180" /> Back
            </a>
            <h2 class="page-title">New Cycle Count</h2>
        </div>
    </div>

    <form wire:submit="save" class="card max-w-2xl">
        <div class="card-header"><h3 class="text-card-title">Count Setup</h3></div>
        <div class="card-body space-y-5">
            <div>
                <label class="label">Virtual Warehouse *</label>
                <select wire:model="virtualWarehouseId" class="input">
                    <option value="">Select…</option>
                    @foreach($warehouses as $vw)
                        <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                    @endforeach
                </select>
                @error('virtualWarehouseId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label">Bins to count (leave empty to count all)</label>
                <select wire:model="locationIds" multiple class="input h-40">
                    @foreach($bins as $bin)
                        <option value="{{ $bin->id }}">{{ $bin->code }}</option>
                    @endforeach
                </select>
                @error('locationIds') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label">Notes</label>
                <textarea wire:model="notes" rows="2" class="input"></textarea>
            </div>

            <div class="alert-info">
                <x-ui.icon name="info" class="w-5 h-5 shrink-0" />
                <p>System quantities will be snapshot at creation. Counters will not see them (blind count). Bins included in this count will be locked for picking and putaway until the count is posted.</p>
            </div>
        </div>
        <div class="card-footer">
            <a href="{{ route('inventory.counts.index') }}" wire:navigate class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Create Count</button>
        </div>
    </form>
</div>
