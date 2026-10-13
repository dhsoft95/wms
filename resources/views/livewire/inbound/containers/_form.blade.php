{{-- Shared form partial: used by both create and edit views --}}

{{-- 1. Container Identification --}}
<div class="card">
    <div class="card-header">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-simba-50 dark:bg-simba-500/15 text-simba-600 dark:text-simba-400">
                <x-ui.icon name="cube" class="h-5 w-5" />
            </div>
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Container Identification</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">ISO 6346 number, seal, and size type</p>
            </div>
        </div>
    </div>
    <div class="card-body space-y-4">
        {{-- Container No --}}
        <div>
            <label class="label">Container Number <span class="text-red-500">*</span></label>
            <div class="flex gap-2 items-start">
                <div class="flex-1">
                    <input wire:model.live="form.container_no"
                           type="text"
                           placeholder="MSCU4134997"
                           maxlength="11"
                           class="input font-mono uppercase w-full"
                           @if(isset($container) && !$container->containerNoEditable()) readonly disabled @endif>
                    @if(isset($container) && !$container->containerNoEditable())
                        <p class="hint text-slate-400">Container number cannot be changed after the container has arrived.</p>
                    @else
                        <div class="mt-1.5 flex items-center gap-1.5 text-xs" wire:key="iso-hint">
                            @php
                                $no = \App\Services\Foundation\ISO6346Validator::normalize($form->container_no ?? '');
                                $valid = strlen($no) === 11 && \App\Services\Foundation\ISO6346Validator::isValid($no);
                                $hasInput = strlen(trim($form->container_no ?? '')) >= 11;
                            @endphp
                            @if($hasInput && $valid)
                                <x-ui.icon name="check" class="w-3.5 h-3.5 text-green-600" />
                                <span class="text-green-600">Valid ISO 6346 number</span>
                            @elseif($hasInput && !$valid)
                                <x-ui.icon name="x" class="w-3.5 h-3.5 text-red-500" />
                                <span class="text-red-500">Invalid check digit or format</span>
                            @else
                                <span class="text-slate-400">4 owner letters + 1 category letter + 6 serial digits + 1 check digit</span>
                            @endif
                        </div>
                        @if($hasInput && !$valid)
                            <div class="mt-2 flex items-center gap-2">
                                <input type="checkbox" wire:model.live="form.override_number" id="override_number" class="rounded accent-brand">
                                <label for="override_number" class="text-xs text-slate-600 dark:text-slate-400 cursor-pointer">Override ISO 6346 validation</label>
                                @can('containers.override-number')
                                @else
                                    <span class="text-[11px] text-amber-600">(Requires override permission)</span>
                                @endcan
                            </div>
                            @if($form->override_number ?? false)
                                <div class="mt-2">
                                    <input wire:model="form.override_reason" type="text" placeholder="Reason for override (required)" class="input text-sm">
                                    @error('form.override_reason') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                                </div>
                            @endif
                        @endif
                    @endif
                    @error('form.container_no') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Seal No --}}
            <div>
                <label class="label">Seal Number</label>
                <input wire:model="form.seal_no" type="text" placeholder="TZ-998238" class="input font-mono">
                @error('form.seal_no') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Size Type --}}
            <div>
                <label class="label">Size / Type <span class="text-red-500">*</span></label>
                <select wire:model="form.size_type" class="input">
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
    <div class="card-header">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-simba-50 dark:bg-simba-500/15 text-simba-600 dark:text-simba-400">
                <x-ui.icon name="archive" class="h-5 w-5" />
            </div>
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Shipping Details</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Bill of lading, vessel, and voyage</p>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="label">Bill of Lading No.</label>
                <input wire:model="form.bl_no" type="text" placeholder="BL-DAR-2026-001" class="input font-mono">
                @error('form.bl_no') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Vessel Name</label>
                <input wire:model="form.vessel" type="text" placeholder="MSC Diana" class="input">
                @error('form.vessel') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Voyage Number</label>
                <input wire:model="form.voyage" type="text" placeholder="V.12" class="input font-mono">
                @error('form.voyage') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>
</div>

{{-- 3. Partners --}}
<div class="card">
    <div class="card-header">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-simba-50 dark:bg-simba-500/15 text-simba-600 dark:text-simba-400">
                <x-ui.icon name="users" class="h-5 w-5" />
            </div>
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Partners</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Shipper, consignee, and shipping line</p>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="label">Shipping Line</label>
                <select wire:model="form.shipping_line_partner_id" class="input">
                    <option value="">— Select —</option>
                    @foreach(\App\Models\Partner::ofType('shipping_line')->active()->orderBy('name')->get() as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
                @error('form.shipping_line_partner_id') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Shipper</label>
                <select wire:model="form.shipper_partner_id" class="input">
                    <option value="">— Select —</option>
                    @foreach(\App\Models\Partner::ofType('shipper')->active()->orderBy('name')->get() as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
                @error('form.shipper_partner_id') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Consignee</label>
                <select wire:model="form.consignee_partner_id" class="input">
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
    <div class="card-header">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-simba-50 dark:bg-simba-500/15 text-simba-600 dark:text-simba-400">
                <x-ui.icon name="clock" class="h-5 w-5" />
            </div>
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Schedule</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Expected time of arrival and free days</p>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="label">ETA</label>
                <input wire:model="form.eta" type="date" class="input tabular-nums">
                @error('form.eta') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Free Days</label>
                <input wire:model="form.free_days" type="number" min="0" max="365" placeholder="7" class="input tabular-nums">
                <p class="hint">Override default free days before demurrage applies.</p>
                @error('form.free_days') <p class="hint !text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>
</div>

{{-- 5. Notes --}}
<div class="card">
    <div class="card-header">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Notes</h3>
    </div>
    <div class="card-body">
        <textarea wire:model="form.notes" rows="3" placeholder="Handling instructions, special requirements, remarks…" class="input w-full"></textarea>
        @error('form.notes') <p class="hint !text-red-600">{{ $message }}</p> @enderror
    </div>
</div>
