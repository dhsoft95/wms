<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">Dispatch &amp; Gate Release</h2>
            <p class="page-sub">Verify the load, capture vehicle details and issue the gate pass.</p>
        </div>
    </div>

    <form wire:submit="save" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 card">
            <div class="card-body space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="label">Order reference *</label>
                        <select wire:model="form.orderId" class="input font-mono">
                            <option value="">Select order</option>
                            <option value="ORD-2026-101">ORD-2026-101</option>
                            <option value="ORD-2026-102">ORD-2026-102</option>
                        </select>
                        @error('form.orderId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Gate pass number</label>
                        <input type="text" readonly value="{{ $form->gatePassNumber }}" class="input font-mono">
                    </div>
                    <div>
                        <label class="label">Departure time *</label>
                        <input wire:model="form.departureTime" type="datetime-local" required class="input tabular-nums">
                        @error('form.departureTime') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="space-y-4 pt-6 border-t border-slate-100 dark:border-slate-800">
                    <p class="section-label">Vehicle &amp; Driver</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="label">Vehicle plate *</label>
                            <input wire:model="form.vehiclePlate" type="text" required placeholder="T 452 ABC" class="input font-mono uppercase">
                            @error('form.vehiclePlate') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Trailer number</label>
                            <input wire:model="form.trailerNumber" type="text" placeholder="T 118 DEF" class="input font-mono uppercase">
                        </div>
                        <div>
                            <label class="label">Seal number *</label>
                            <input wire:model="form.sealNumber" type="text" required placeholder="SL-55021" class="input font-mono">
                            @error('form.sealNumber') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Driver name *</label>
                            <input wire:model="form.driverName" type="text" required placeholder="Juma Kassim" class="input">
                            @error('form.driverName') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Driver phone *</label>
                            <input wire:model="form.driverPhone" type="tel" required placeholder="+255 712 000 000" class="input tabular-nums">
                            @error('form.driverPhone') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">Licence number</label>
                            <input wire:model="form.licenceNumber" type="text" placeholder="DL-4410923" class="input font-mono">
                        </div>
                    </div>
                </div>

                <div class="space-y-3 pt-6 border-t border-slate-100 dark:border-slate-800">
                    <p class="section-label">Release Checklist</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach ([
                            ['field' => 'checklistLoadVerified',       'label' => 'Load verified against picklist'],
                            ['field' => 'checklistVehicleInspected',   'label' => 'Vehicle condition inspected'],
                            ['field' => 'checklistSealApplied',        'label' => 'Seal applied and recorded'],
                            ['field' => 'checklistDocumentsHandedOver','label' => 'Delivery documents handed to driver'],
                        ] as $check)
                            <label class="flex items-center gap-3 p-3 rounded-lg border border-slate-200 dark:border-slate-700 text-sm cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <input wire:model="form.{{ $check['field'] }}" type="checkbox" required class="rounded border-slate-300 text-simba-600 focus:ring-simba-600">
                                {{ $check['label'] }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="{{ route('outbound.orders.index') }}" wire:navigate class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Issue Gate Pass &amp; Release</button>
            </div>
        </div>

        <div class="self-start space-y-4">
            <div class="card overflow-hidden">
                <div class="bg-simba-600 text-white px-5 py-4">
                    <p class="text-[11px] uppercase tracking-wider text-simba-100">Gate Pass</p>
                    <p class="text-xl font-bold font-mono">{{ $form->gatePassNumber }}</p>
                </div>
                <div class="card-body space-y-3 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">Order</span><span class="mono font-semibold">{{ $form->orderId ?: 'ORD-2026-101' }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Packages</span><span class="tabular-nums font-semibold">120 bags</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Gross weight</span><span class="tabular-nums">3,000 kg</span></div>
                    <div class="pt-3 border-t border-dashed border-slate-300 dark:border-slate-700 font-mono text-center text-xs tracking-[0.3em] text-slate-500">|||| | ||| || |||| | ||</div>
                </div>
            </div>
            <a href="{{ route('reports.print') }}" wire:navigate class="btn btn-secondary w-full">
                <x-ui.icon name="printer" class="w-4 h-4" /> Print Gate Pass
            </a>
        </div>
    </form>
</div>
