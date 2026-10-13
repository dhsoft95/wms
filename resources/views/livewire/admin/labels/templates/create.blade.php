<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">New Label Template</h2>
            <p class="page-sub">Define label dimensions and type. You'll design the layout after saving.</p>
        </div>
        <a href="{{ route('admin.labels.templates.index') }}" wire:navigate class="btn btn-secondary">
            <x-ui.icon name="x" class="w-4 h-4" /> Cancel
        </a>
    </div>

    <form wire:submit="save" class="max-w-2xl space-y-6">

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <x-ui.icon name="tag" class="w-4 h-4" />
                    Template Details
                </h3>
            </div>

            <div class="card-body space-y-5">

                {{-- Name --}}
                <div>
                    <label class="label" for="tpl-name">Name <span class="text-red-500">*</span></label>
                    <input
                        wire:model="name"
                        id="tpl-name"
                        type="text"
                        placeholder="e.g. Pallet LPN — Standard"
                        class="input w-full @error('name') border-red-400 @enderror"
                    />
                    @error('name')
                        <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Type --}}
                <div>
                    <label class="label" for="tpl-type">Type <span class="text-red-500">*</span></label>
                    <select
                        wire:model="type"
                        id="tpl-type"
                        class="input w-full @error('type') border-red-400 @enderror"
                    >
                        @foreach ($templateTypes as $case)
                            <option value="{{ $case->value }}">{{ $case->label() }}</option>
                        @endforeach
                    </select>
                    @error('type')
                        <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Dimensions --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="label" for="tpl-width">Width (mm) <span class="text-red-500">*</span></label>
                        <input
                            wire:model="widthMm"
                            id="tpl-width"
                            type="number"
                            min="10"
                            max="500"
                            step="1"
                            class="input w-full @error('widthMm') border-red-400 @enderror"
                        />
                        @error('widthMm')
                            <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="label" for="tpl-height">Height (mm) <span class="text-red-500">*</span></label>
                        <input
                            wire:model="heightMm"
                            id="tpl-height"
                            type="number"
                            min="10"
                            max="500"
                            step="1"
                            class="input w-full @error('heightMm') border-red-400 @enderror"
                        />
                        @error('heightMm')
                            <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- DPI --}}
                <div>
                    <label class="label">Resolution (DPI) <span class="text-red-500">*</span></label>
                    <div class="flex gap-4 mt-1">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input wire:model="dpi" type="radio" value="203" class="text-simba-600 focus:ring-simba-600" />
                            <span class="text-sm text-slate-700 dark:text-slate-300">203 DPI (standard)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input wire:model="dpi" type="radio" value="300" class="text-simba-600 focus:ring-simba-600" />
                            <span class="text-sm text-slate-700 dark:text-slate-300">300 DPI (high quality)</span>
                        </label>
                    </div>
                    @error('dpi')
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
                        <p class="hint mt-0.5">Only active templates can be used for printing.</p>
                    </div>
                </label>

            </div>
        </div>

        {{-- Footer Actions --}}
        <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
            <a href="{{ route('admin.labels.templates.index') }}" wire:navigate class="btn btn-secondary text-center">
                Cancel
            </a>
            <button type="submit" class="btn btn-primary justify-center">
                <x-ui.icon name="plus" class="w-4 h-4" /> Create & Design
            </button>
        </div>

    </form>

</div>
