<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Edit Warehouse</h2>
            <p class="page-sub">Update details for <strong>{{ $warehouse->name }}</strong>.</p>
        </div>
        <a href="{{ route('admin.warehouses.index') }}" wire:navigate class="btn btn-secondary">
            <x-ui.icon name="x" class="w-4 h-4" /> Cancel
        </a>
    </div>

    <form wire:submit="save" class="max-w-2xl space-y-6">

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <x-ui.icon name="home" class="w-4 h-4" />
                    Warehouse Details
                </h3>
            </div>

            <div class="card-body space-y-5">

                {{-- Code --}}
                <div>
                    <label class="label" for="wh-code">Code <span class="text-red-500">*</span></label>
                    <input
                        wire:model="code"
                        id="wh-code"
                        type="text"
                        placeholder="e.g. WH-001"
                        maxlength="10"
                        class="input w-full mono uppercase @error('code') border-red-400 @enderror"
                    />
                    <p class="hint">Short unique identifier, max 10 characters. Will be uppercased automatically.</p>
                    @error('code')
                        <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Name --}}
                <div>
                    <label class="label" for="wh-name">Name <span class="text-red-500">*</span></label>
                    <input
                        wire:model="name"
                        id="wh-name"
                        type="text"
                        placeholder="e.g. Main Distribution Centre"
                        class="input w-full @error('name') border-red-400 @enderror"
                    />
                    @error('name')
                        <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Address --}}
                <div>
                    <label class="label" for="wh-address">
                        Address <span class="text-slate-400 dark:text-slate-500 font-normal">(optional)</span>
                    </label>
                    <textarea
                        wire:model="address"
                        id="wh-address"
                        rows="3"
                        placeholder="Physical address or location description"
                        class="input w-full resize-none @error('address') border-red-400 @enderror"
                    ></textarea>
                    @error('address')
                        <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Is Active --}}
                <label class="flex items-start gap-3 cursor-pointer">
                    <div class="pt-0.5">
                        <input
                            wire:model="isActive"
                            type="checkbox"
                            class="rounded border-slate-300 dark:border-slate-600 text-simba-600 focus:ring-simba-600 dark:bg-zinc-800"
                        />
                    </div>
                    <div>
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Active</span>
                        <p class="hint mt-0.5">Inactive warehouses won't be selectable when creating locations.</p>
                    </div>
                </label>

            </div>
        </div>

        {{-- Receiving Defaults --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <x-ui.icon name="archive" class="w-4 h-4" />
                    Receiving Defaults
                </h3>
            </div>
            <div class="card-body space-y-5">

                <div>
                    <label class="label" for="wh-dock">Default Receiving Dock</label>
                    <select wire:model="defaultDockLocationId" id="wh-dock"
                            class="input @error('defaultDockLocationId') border-red-400 @enderror">
                        <option value="">— None —</option>
                        @foreach ($this->dockLocations as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->code }}</option>
                        @endforeach
                    </select>
                    <p class="hint">Default dock location for GRNs created in this warehouse.</p>
                    @error('defaultDockLocationId')
                        <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="label" for="wh-quarantine">Default Quarantine Location</label>
                    <select wire:model="defaultQuarantineLocationId" id="wh-quarantine"
                            class="input @error('defaultQuarantineLocationId') border-red-400 @enderror">
                        <option value="">— None —</option>
                        @foreach ($this->quarantineLocations as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->code }}</option>
                        @endforeach
                    </select>
                    <p class="hint">Damaged goods are posted to this location.</p>
                    @error('defaultQuarantineLocationId')
                        <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                    @enderror
                </div>

            </div>
        </div>

        {{-- Footer Actions --}}
        <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
            <a href="{{ route('admin.warehouses.index') }}" wire:navigate class="btn btn-secondary text-center">
                Cancel
            </a>
            <button type="submit" class="btn btn-primary justify-center">
                <x-ui.icon name="check" class="w-4 h-4" /> Save Changes
            </button>
        </div>

    </form>

</div>
