<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">Department Transfers</h2>
            <p class="page-sub">Move stock between virtual warehouses and regional hubs.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <form wire:submit="save" class="lg:col-span-2 card">
            <div class="card-body space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-[1fr_auto_1fr] gap-4 items-end">
                    <div>
                        <label class="label">Source department / VW *</label>
                        <select wire:model="form.sourceDepartment" class="input">
                            <option>DAR-General Store</option><option>DAR-Tech Vault</option>
                        </select>
                    </div>
                    <div class="hidden md:flex items-center justify-center h-[38px] w-10 text-slate-400">
                        <x-ui.icon name="arrow-right" class="w-5 h-5" />
                    </div>
                    <div>
                        <label class="label">Target department / VW *</label>
                        <select wire:model="form.targetDepartment" class="input">
                            <option>Zanzibar Regional Hub</option><option>Arusha Distribution Center</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="label">SKU code *</label>
                        <input wire:model="form.skuCode" type="text" required placeholder="SKU-VOD-SIM" class="input font-mono">
                        @error('form.skuCode') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Quantity *</label>
                        <input wire:model="form.quantity" type="number" required min="1" placeholder="200" class="input tabular-nums">
                        @error('form.quantity') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Required by *</label>
                        <input wire:model="form.requiredBy" type="date" required class="input tabular-nums">
                        @error('form.requiredBy') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="label">Transport mode</label>
                    <select wire:model="form.transportMode" class="input">
                        <option>Road freight</option><option>Sea freight (ferry)</option><option>Air freight</option>
                    </select>
                </div>
                <div>
                    <label class="label">Transfer reason *</label>
                    <textarea wire:model="form.transferReason" rows="3" required placeholder="Reason for the transfer..." class="input"></textarea>
                    @error('form.transferReason') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="alert-warn">
                    <x-ui.icon name="alert" class="w-5 h-5 shrink-0" />
                    <p>Transfers above 100 units require supervisor approval before picking starts.</p>
                </div>
            </div>
            <div class="card-footer">
                <button type="button" wire:click="$refresh" class="btn btn-secondary">Clear</button>
                <button type="submit" class="btn btn-primary">Submit Transfer Request</button>
            </div>
        </form>

        <div class="card self-start">
            <div class="card-header"><h3 class="card-title">Open Transfers</h3></div>
            <ul>
                @foreach ($open as $item)
                    <li class="list-row">
                        <div>
                            <p class="text-sm font-semibold mono">{{ $item['ref'] }}</p>
                            <p class="text-xs text-slate-500">{{ $item['desc'] }}</p>
                        </div>
                        <span class="badge {{ $item['badge'] }}">{{ $item['label'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
