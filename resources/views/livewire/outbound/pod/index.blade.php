<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">Proof of Delivery</h2>
            <p class="page-sub">Confirm delivery and attach the signed delivery note.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <form wire:submit="save" class="lg:col-span-2 card">
            <div class="card-body space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Gate pass reference *</label>
                        <select wire:model="form.gatePassId" class="input font-mono">
                            <option value="">Select gate pass</option>
                            <option value="GP-2026-088">GP-2026-088</option>
                            <option value="GP-2026-087">GP-2026-087</option>
                        </select>
                        @error('form.gatePassId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Delivered at *</label>
                        <input wire:model="form.deliveredAt" type="datetime-local" required class="input tabular-nums">
                        @error('form.deliveredAt') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Recipient full name *</label>
                        <input wire:model="form.recipientName" type="text" required placeholder="Rashid Ally" class="input">
                        @error('form.recipientName') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Recipient phone / ID</label>
                        <input wire:model="form.recipientPhone" type="text" placeholder="+255 754 000 000" class="input tabular-nums">
                    </div>
                </div>

                <div>
                    <label class="label">Delivery condition *</label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        @foreach ([
                            'delivered_full' => ['Delivered in full',  'All items, good condition'],
                            'partial'        => ['Partial delivery',   'Some items not accepted'],
                            'damaged'        => ['Damaged on arrival', 'Claim required'],
                        ] as $val => [$label, $sub])
                            <label class="relative flex">
                                <input type="radio" wire:model="form.deliveryCondition" value="{{ $val }}" class="peer sr-only">
                                <div class="radio-card w-full">
                                    <p class="text-sm font-semibold">{{ $label }}</p>
                                    <p class="text-xs text-slate-500">{{ $sub }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @error('form.deliveryCondition') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label">Signed POD document *</label>
                    <div class="dropzone">
                        <x-ui.icon name="upload" class="w-6 h-6 text-slate-400" />
                        <p class="text-sm font-semibold">Click to upload the signed delivery note</p>
                        <p class="hint !mt-0">Photo or scan, JPG, PNG or PDF</p>
                    </div>
                </div>

                <div>
                    <label class="label">Remarks</label>
                    <textarea wire:model="form.remarks" rows="2" class="input" placeholder="Optional"></textarea>
                </div>
            </div>
            <div class="card-footer">
                <button type="button" wire:click="$refresh" class="btn btn-secondary">Clear</button>
                <button type="submit" class="btn btn-primary">Upload &amp; Confirm Delivery</button>
            </div>
        </form>

        <div class="card self-start">
            <div class="card-header">
                <h3 class="card-title">Awaiting POD</h3>
                <span class="badge badge-amber">{{ count($awaiting) }}</span>
            </div>
            <ul>
                @foreach ($awaiting as $item)
                    <li class="list-row">
                        <div>
                            <p class="text-sm font-semibold mono">{{ $item['ref'] }}</p>
                            <p class="text-xs text-slate-500">{{ $item['client'] }} · {{ $item['location'] }}</p>
                        </div>
                        <span class="text-xs {{ $item['ageColor'] ?: 'text-slate-500' }}">{{ $item['age'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
