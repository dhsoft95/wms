{{-- Shared form partial: used by both create and edit views --}}

{{-- 1. Container Identification --}}
<div class="card">
    <div class="card-header !py-3">
        <div class="flex items-center gap-2.5">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-simba-50 dark:bg-simba-500/15 text-simba-600 dark:text-simba-400">
                <x-ui.icon name="cube" class="h-4 w-4" />
            </div>
            <div>
                <h3 class="text-xs font-bold text-slate-900 dark:text-white">Container Identification</h3>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">ISO 6346 number, seal, and size type</p>
            </div>
        </div>
    </div>
    <div class="card-body !py-3 space-y-3">
        {{-- Container No --}}
        <div>
            <label class="label !text-xs !mb-1">Container Number <span class="text-red-500">*</span></label>
            <div class="flex gap-2 items-start">
                <div class="flex-1">
                    <input wire:model.live="form.container_no"
                           type="text"
                           placeholder="MSCU4134997"
                           class="input input-sm font-mono uppercase w-full"
                           @if(isset($container) && !$container->containerNoEditable()) readonly disabled @endif>
                    @if(isset($container) && !$container->containerNoEditable())
                        <p class="hint text-slate-400">Container number cannot be changed after the container has arrived.</p>
                    @endif
                    @error('form.container_no') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            {{-- Seal No --}}
            <div>
                <label class="label !text-xs !mb-1">Seal Number</label>
                <input wire:model="form.seal_no" type="text" placeholder="TZ-998238" class="input input-sm font-mono">
                @error('form.seal_no') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Size Type --}}
            <div>
                <label class="label !text-xs !mb-1">Size / Type <span class="text-red-500">*</span></label>
                <select wire:model="form.size_type" class="input input-sm">
                    @foreach(\App\Enums\ContainerSizeType::cases() as $st)
                        <option value="{{ $st->value }}">{{ $st->label() }}</option>
                    @endforeach
                </select>
                @error('form.size_type') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>
</div>

{{-- 2. Shipping Details --}}
<div class="card">
    <div class="card-header !py-3">
        <div class="flex items-center gap-2.5">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-simba-50 dark:bg-simba-500/15 text-simba-600 dark:text-simba-400">
                <x-ui.icon name="archive" class="h-4 w-4" />
            </div>
            <div>
                <h3 class="text-xs font-bold text-slate-900 dark:text-white">Shipping Details</h3>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Bill of lading, vessel, and voyage</p>
            </div>
        </div>
    </div>
    <div class="card-body !py-3">
        <div class="grid grid-cols-3 gap-3">
            <div>
                <label class="label !text-xs !mb-1">Bill of Lading No.</label>
                <input wire:model="form.bl_no" type="text" placeholder="BL-DAR-2026-001" class="input input-sm font-mono">
                @error('form.bl_no') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label !text-xs !mb-1">Vessel Name</label>
                <input wire:model="form.vessel" type="text" placeholder="MSC Diana" class="input input-sm">
                @error('form.vessel') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label !text-xs !mb-1">Voyage Number</label>
                <input wire:model="form.voyage" type="text" placeholder="V.12" class="input input-sm font-mono">
                @error('form.voyage') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>
</div>

{{-- 3. Partners --}}
<div class="card">
    <div class="card-header !py-3">
        <div class="flex items-center gap-2.5">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-simba-50 dark:bg-simba-500/15 text-simba-600 dark:text-simba-400">
                <x-ui.icon name="users" class="h-4 w-4" />
            </div>
            <div>
                <h3 class="text-xs font-bold text-slate-900 dark:text-white">Partners</h3>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Shipper, consignee, and shipping line</p>
            </div>
        </div>
    </div>
    <div class="card-body !py-3">
        <div class="grid grid-cols-3 gap-3">
            <div>
                <label class="label !text-xs !mb-1">Shipping Line</label>
                <select wire:model="form.shipping_line_partner_id" class="input input-sm">
                    <option value="">— Select —</option>
                    @foreach(\App\Models\Partner::ofType('shipping_line')->active()->orderBy('name')->get() as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
                @error('form.shipping_line_partner_id') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label !text-xs !mb-1">Shipper</label>
                <select wire:model="form.shipper_partner_id" class="input input-sm">
                    <option value="">— Select —</option>
                    @foreach(\App\Models\Partner::ofType('shipper')->active()->orderBy('name')->get() as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
                @error('form.shipper_partner_id') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label !text-xs !mb-1">Consignee</label>
                <select wire:model="form.consignee_partner_id" class="input input-sm">
                    <option value="">— Select —</option>
                    @foreach(\App\Models\Partner::ofType('consignee')->active()->orderBy('name')->get() as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
                @error('form.consignee_partner_id') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>
</div>

{{-- 4. Schedule --}}
<div class="card">
    <div class="card-header !py-3">
        <div class="flex items-center gap-2.5">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-simba-50 dark:bg-simba-500/15 text-simba-600 dark:text-simba-400">
                <x-ui.icon name="clock" class="h-4 w-4" />
            </div>
            <div>
                <h3 class="text-xs font-bold text-slate-900 dark:text-white">Schedule</h3>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Expected time of arrival and free days</p>
            </div>
        </div>
    </div>
    <div class="card-body !py-3">
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="label !text-xs !mb-1">ETA</label>
                <input wire:model="form.eta" type="date" class="input input-sm tabular-nums">
                @error('form.eta') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label !text-xs !mb-1">Free Days</label>
                <input wire:model="form.free_days" type="number" min="0" max="365" placeholder="7" class="input input-sm tabular-nums">
                <p class="hint text-[11px]">Override default free days before demurrage applies.</p>
                @error('form.free_days') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>
</div>

{{-- 5. Purchase Order --}}
<div class="card">
    <div class="card-header !py-3">
        <div class="flex items-center gap-2.5">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-simba-50 dark:bg-simba-500/15 text-simba-600 dark:text-simba-400">
                <x-ui.icon name="document" class="h-4 w-4" />
            </div>
            <div>
                <h3 class="text-xs font-bold text-slate-900 dark:text-white">Purchase Order</h3>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">PO reference and supporting document</p>
            </div>
        </div>
    </div>
    <div class="card-body !py-3 space-y-3">
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="label !text-xs !mb-1">PO Number</label>
                <input wire:model="form.po_number" type="text" placeholder="PO-2026-001" class="input input-sm font-mono">
                @error('form.po_number') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label !text-xs !mb-1">PO Document <span class="text-slate-400 font-normal">(PDF / JPG / PNG, max 10 MB)</span></label>
                <input wire:model="form.po_file" type="file" accept=".pdf,.jpg,.jpeg,.png" class="input input-sm">
                @if(isset($container) && $container->po_file_path)
                    <p class="hint text-[11px] text-slate-500 mt-1">
                        Current file attached.
                        <a href="{{ route('inbound.containers.po.download', $container) }}" target="_blank" class="text-brand-600 underline">Download</a>
                        &mdash; upload a new file to replace it.
                    </p>
                @endif
                @error('form.po_file') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>
</div>

{{-- 6. Notes --}}
<div class="card">
    <div class="card-header !py-3">
        <h3 class="text-xs font-bold text-slate-900 dark:text-white">Notes</h3>
    </div>
    <div class="card-body !py-3">
        <textarea wire:model="form.notes" rows="2" placeholder="Handling instructions, special requirements, remarks…" class="input input-sm w-full"></textarea>
        @error('form.notes') <p class="hint !text-red-600">{{ $message }}</p> @enderror
    </div>
</div>
