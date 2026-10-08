<div class="space-y-6">
    <div class="page-header">
        <div>
            <a href="{{ route('inbound.containers.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-simba-600 mb-2">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Back to containers
            </a>
            <h2 class="page-title">Register Container</h2>
            <p class="page-sub">Capture shipment details before the container reaches the yard.</p>
        </div>
    </div>

    <form wire:submit="save" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 card">
            <div class="card-body space-y-8">
                {{-- Container details --}}
                <div class="space-y-4">
                    <p class="section-label">Container Details</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="label">Container number *</label>
                            <input wire:model="form.containerNumber" type="text" required placeholder="MSKU1234567" class="input font-mono uppercase">
                            <p class="hint">ISO 6346 format: 4 letters followed by 7 digits.</p>
                            @error('form.containerNumber') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Seal number *</label>
                            <input wire:model="form.sealNumber" type="text" required placeholder="TZ-998238" class="input font-mono">
                            @error('form.sealNumber') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Size / type *</label>
                            <select wire:model="form.sizeType" class="input">
                                <option>20ft Standard</option>
                                <option>40ft Standard</option>
                                <option>40HC High Cube</option>
                                <option>40ft Reefer</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Bill of Lading number *</label>
                            <input wire:model="form.blNumber" type="text" required placeholder="BL-DAR-2026-005" class="input font-mono">
                            @error('form.blNumber') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- Shipment --}}
                <div class="space-y-4 pt-6 border-t border-slate-100 dark:border-slate-800">
                    <p class="section-label">Shipment</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="label">Vessel name</label>
                            <input wire:model="form.vesselName" type="text" placeholder="MSC Diana" class="input">
                        </div>
                        <div>
                            <label class="label">Voyage number</label>
                            <input wire:model="form.voyageNumber" type="text" placeholder="V.12" class="input font-mono">
                        </div>
                        <div>
                            <label class="label">Shipping line</label>
                            <select wire:model="form.shippingLine" class="input">
                                <option>Maersk</option><option>MSC</option><option>CMA CGM</option><option>COSCO</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">ETA date *</label>
                            <input wire:model="form.etaDate" type="date" required class="input tabular-nums">
                            @error('form.etaDate') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Port of discharge</label>
                            <select wire:model="form.portOfDischarge" class="input">
                                <option>Dar es Salaam Port</option><option>Zanzibar Port</option><option>Tanga Port</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Consignee / client *</label>
                            <select wire:model="form.consigneeId" class="input">
                                {{-- placeholder data --}}
                                <option value="">Select client</option>
                                <option value="1">Azam Consumer Goods Ltd</option>
                                <option value="2">Vodacom Tanzania PLC</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Yard allocation --}}
                <div class="space-y-4 pt-6 border-t border-slate-100 dark:border-slate-800">
                    <p class="section-label">Yard Allocation</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="label">Yard slot</label>
                            <select wire:model="form.yardSlot" class="input">
                                <option value="">Assign on arrival</option>
                                <option>SLOT-A04</option><option>SLOT-A07</option><option>SLOT-B02</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Initial status</label>
                            <select wire:model="form.initialStatus" class="input">
                                <option>Expected</option><option>Arrived</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="label">Remarks</label>
                            <textarea wire:model="form.remarks" rows="3" placeholder="Handling instructions, special requirements..." class="input"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="{{ route('inbound.containers.index') }}" wire:navigate class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Container</button>
            </div>
        </div>

        {{-- Sidebar panel --}}
        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Before you save</h3></div>
                <ul class="card-body space-y-3 text-sm text-slate-600 dark:text-slate-300">
                    <li class="flex gap-3"><x-ui.icon name="check" class="w-4 h-4 mt-0.5 text-emerald-600 shrink-0" />Container number matches the BL and seal record.</li>
                    <li class="flex gap-3"><x-ui.icon name="check" class="w-4 h-4 mt-0.5 text-emerald-600 shrink-0" />Reefer units have power-equipped slots available.</li>
                    <li class="flex gap-3"><x-ui.icon name="check" class="w-4 h-4 mt-0.5 text-emerald-600 shrink-0" />Consignee has an active storage agreement.</li>
                </ul>
            </div>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Yard Availability</h3><span class="badge badge-green">8 free</span></div>
                <div class="card-body">
                    {{-- placeholder yard grid --}}
                    <div class="grid grid-cols-5 gap-2">
                        @foreach (['occ','occ','unload','free','occ','free','occ','free','occ','free'] as $s)
                            <div @class([
                                'h-8 rounded',
                                'bg-slate-300 dark:bg-slate-700'          => $s === 'occ',
                                'bg-simba-600'                             => $s === 'unload',
                                'border-2 border-dashed border-emerald-500'=> $s === 'free',
                            ])></div>
                        @endforeach
                    </div>
                    <div class="flex gap-4 mt-4 text-xs text-slate-500">
                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-slate-300 dark:bg-slate-700"></span>Occupied</span>
                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-simba-600"></span>Unloading</span>
                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded border-2 border-dashed border-emerald-500"></span>Free</span>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
