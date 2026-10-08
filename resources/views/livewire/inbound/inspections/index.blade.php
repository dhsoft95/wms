<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">Inspections & Discrepancies</h2>
            <p class="page-sub">Report shortages, damages and wrong items found during receiving.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <form wire:submit="save" class="lg:col-span-2 card">
            <div class="card-body space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="label">GRN reference *</label>
                        <select wire:model="form.grnId" class="input font-mono">
                            <option value="">Select GRN</option>
                            <option value="GRN-2026-089">GRN-2026-089</option>
                            <option value="GRN-2026-090">GRN-2026-090</option>
                        </select>
                        @error('form.grnId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Container</label>
                        <input wire:model="form.container" type="text" placeholder="Auto-filled from GRN" class="input font-mono">
                    </div>
                    <div>
                        <label class="label">Issue type *</label>
                        <select wire:model="form.issueType" class="input">
                            <option>Shortage (missing qty)</option>
                            <option>Damaged packaging / goods</option>
                            <option>Wrong item supplied</option>
                            <option>Excess quantity</option>
                        </select>
                        @error('form.issueType') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Affected SKU *</label>
                        <input wire:model="form.affectedSku" type="text" required placeholder="SKU-AZ-001" class="input font-mono">
                        @error('form.affectedSku') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Affected quantity *</label>
                        <input wire:model="form.affectedQuantity" type="number" required min="1" placeholder="12" class="input tabular-nums">
                        @error('form.affectedQuantity') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="label">Severity *</label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        @foreach (['Low' => 'Minor variance', 'Medium' => 'Disputed quantity', 'High' => 'Severe damage or rejection'] as $sev => $desc)
                            <label class="relative flex">
                                <input type="radio" wire:model="form.severity" value="{{ $sev }}" class="peer sr-only">
                                <div class="radio-card w-full">
                                    <p class="text-sm font-semibold">{{ $sev }}</p>
                                    <p class="text-xs text-slate-500">{{ $desc }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @error('form.severity') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label">Inspection remarks</label>
                    <textarea wire:model="form.remarks" rows="3" placeholder="Describe the discrepancy and how it was found..." class="input"></textarea>
                </div>

                <div>
                    <label class="label">Photo evidence</label>
                    <div class="dropzone">
                        <x-ui.icon name="upload" class="w-6 h-6 text-slate-400" />
                        <p class="text-sm font-semibold">Click to upload photos</p>
                        <p class="hint !mt-0">JPG, PNG or PDF up to 10MB each</p>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="button" wire:click="$refresh" class="btn btn-secondary">Clear</button>
                <button type="submit" class="btn btn-primary">Save Inspection Report</button>
            </div>
        </form>

        <div class="card self-start">
            <div class="card-header"><h3 class="card-title">Recent Inspections</h3></div>
            <ul>
                @foreach ($recent as $item)
                    <li class="list-row">
                        <div>
                            <p class="text-sm font-semibold mono">{{ $item['ref'] }}</p>
                            <p class="text-xs text-slate-500">{{ $item['type'] }} · {{ $item['grn'] }}</p>
                        </div>
                        <span class="badge {{ $item['badge'] }}">{{ $item['label'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
