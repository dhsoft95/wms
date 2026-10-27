<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Edit Location</h2>
            <p class="page-sub">
                Editing
                <code class="font-mono text-sm bg-slate-100 dark:bg-white/[0.08] px-1.5 py-0.5 rounded">{{ $location->code }}</code>
                @if ($location->status === \App\Enums\LocationStatus::Blocked)
                    &mdash; <span class="text-red-500 font-medium">Blocked</span>
                @endif
            </p>
        </div>
        <a href="{{ route('admin.locations.index') }}" wire:navigate class="btn btn-secondary">
            <x-ui.icon name="x" class="w-4 h-4" /> Cancel
        </a>
    </div>

    {{-- Blocked notice --}}
    @if ($location->status === \App\Enums\LocationStatus::Blocked)
        <div class="rounded-xl border border-red-200 dark:border-red-800/40 bg-red-50/60 dark:bg-red-900/20 px-4 py-3 flex gap-3">
            <x-ui.icon name="x" class="mt-0.5 h-4 w-4 shrink-0 text-red-600 dark:text-red-400" />
            <div class="text-sm text-red-800 dark:text-red-300">
                <strong>This location is blocked.</strong>
                @if ($location->blocked_reason)
                    Reason: {{ $location->blocked_reason }}
                @endif
                @if ($location->blockedBy)
                    &mdash; by {{ $location->blockedBy->name }}
                @endif
                @if ($location->blocked_at)
                    on {{ $location->blocked_at->toFormattedDateString() }}.
                @endif
            </div>
        </div>
    @endif

    <form wire:submit="save" class="max-w-3xl space-y-6">

        {{-- Code Info box --}}
        <div class="rounded-xl border border-simba-200 dark:border-simba-800/50 bg-simba-50/60 dark:bg-simba-900/20 px-4 py-3 flex gap-3">
            <x-ui.icon name="eye" class="mt-0.5 h-4 w-4 shrink-0 text-simba-600 dark:text-simba-400" />
            <p class="text-sm text-simba-800 dark:text-simba-300">
                Changing the <strong>segment code</strong> will regenerate this location's code and automatically
                update all descendant codes to keep the hierarchy consistent.
            </p>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <x-ui.icon name="home" class="w-4 h-4" />
                    Location Details
                </h3>
            </div>

            <div class="card-body space-y-5">

                {{-- Warehouse --}}
                <div>
                    <label class="label" for="loc-warehouse">Warehouse <span class="required-mark">*</span></label>
                    <select
                        wire:model.live="warehouseId"
                        id="loc-warehouse"
                        class="input w-full @error('warehouseId') border-red-400 @enderror"
                    >
                        <option value="">— Select warehouse —</option>
                        @foreach ($this->warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->code }} — {{ $wh->name }}</option>
                        @endforeach
                    </select>
                    @error('warehouseId')
                        <p class="hint hint-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Level --}}
                <div>
                    <label class="label" for="loc-level">Level <span class="required-mark">*</span></label>
                    <select
                        wire:model.live="level"
                        id="loc-level"
                        class="input w-full @error('level') border-red-400 @enderror"
                    >
                        <option value="">— Select level —</option>
                        @foreach (\App\Enums\LocationLevel::cases() as $lvl)
                            <option value="{{ $lvl->value }}">{{ $lvl->label() }}</option>
                        @endforeach
                    </select>
                    @error('level')
                        <p class="hint hint-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Parent Location --}}
                @if ($level && $level !== 'zone')
                    <div>
                        <label class="label" for="loc-parent">
                            Parent Location
                            <span class="text-slate-400 dark:text-slate-500 font-normal">(optional)</span>
                        </label>
                        <select
                            wire:model.live="parentId"
                            id="loc-parent"
                            class="input w-full @error('parentId') border-red-400 @enderror"
                            {{ $this->parentOptions->isEmpty() ? 'disabled' : '' }}
                        >
                            <option value="">— No parent —</option>
                            @foreach ($this->parentOptions as $option)
                                <option value="{{ $option->id }}">{{ $option->code }} ({{ $option->level->label() }})</option>
                            @endforeach
                        </select>
                        @error('parentId')
                            <p class="hint hint-error">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                {{-- Segment Code --}}
                <div>
                    <label class="label" for="loc-segment">Segment Code <span class="required-mark">*</span></label>
                    <input
                        wire:model="segmentCode"
                        id="loc-segment"
                        type="text"
                        placeholder="e.g. A, 03, B2"
                        maxlength="20"
                        class="input w-full mono uppercase @error('segmentCode') border-red-400 @enderror"
                    />
                    <p class="hint">Alphanumeric only, no spaces or dashes.</p>
                    @error('segmentCode')
                        <p class="hint hint-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Type — only meaningful for bin-level locations --}}
                @if ($level === 'bin')
                <div>
                    <label class="label" for="loc-type">Type <span class="required-mark">*</span></label>
                    <select
                        wire:model.live="type"
                        id="loc-type"
                        class="input w-full @error('type') border-red-400 @enderror"
                    >
                        @foreach (\App\Enums\LocationType::cases() as $t)
                            <option value="{{ $t->value }}">{{ $t->label() }}</option>
                        @endforeach
                    </select>
                    @error('type')
                        <p class="hint hint-error">{{ $message }}</p>
                    @enderror
                    @if ($type)
                        <p class="hint">
                            Pickable:
                            @if (\App\Enums\LocationType::from($type)->isPickable())
                                <span class="text-emerald-600 dark:text-emerald-400 font-semibold">Yes</span>
                            @else
                                <span class="text-red-500 dark:text-red-400 font-semibold">No</span>
                            @endif
                        </p>
                    @endif
                </div>
                @else
                <div>
                    <p class="hint text-slate-500">Type is only set on bin-level locations. Parent levels default to <em>storage</em>.</p>
                </div>
                @endif

            </div>
        </div>

        {{-- Capacity Card --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <x-ui.icon name="arrows" class="w-4 h-4" />
                    Capacity, Routing &amp; Barcode
                    <span class="ml-1 text-xs font-normal text-slate-400 dark:text-slate-500">(optional)</span>
                </h3>
            </div>

            <div class="card-body space-y-5">

                {{-- Pick Sequence --}}
                <div>
                    <label class="label" for="loc-pick-seq">Pick Sequence</label>
                    <input
                        wire:model="pickSequence"
                        id="loc-pick-seq"
                        type="number"
                        min="0"
                        placeholder="e.g. 10"
                        class="input w-48 @error('pickSequence') border-red-400 @enderror"
                    />
                    <p class="hint">Lower numbers are picked first. Leave blank to use natural code order.</p>
                    @error('pickSequence')
                        <p class="hint hint-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">

                    {{-- Max Pallets --}}
                    <div>
                        <label class="label" for="loc-pallets">Max Pallets</label>
                        <input
                            wire:model="maxPallets"
                            id="loc-pallets"
                            type="number"
                            min="1"
                            placeholder="e.g. 4"
                            class="input w-full @error('maxPallets') border-red-400 @enderror"
                        />
                        @error('maxPallets')
                            <p class="hint hint-error">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Max Weight --}}
                    <div>
                        <label class="label" for="loc-weight">Max Weight (kg)</label>
                        <input
                            wire:model="maxWeightKg"
                            id="loc-weight"
                            type="number"
                            min="0"
                            step="0.001"
                            placeholder="e.g. 1500"
                            class="input w-full @error('maxWeightKg') border-red-400 @enderror"
                        />
                        @error('maxWeightKg')
                            <p class="hint hint-error">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Barcode --}}
                    <div>
                        <label class="label" for="loc-barcode">Barcode</label>
                        <input
                            wire:model="barcode"
                            id="loc-barcode"
                            type="text"
                            maxlength="100"
                            placeholder="Defaults to code"
                            class="input mono w-full @error('barcode') border-red-400 @enderror"
                        />
                        <p class="hint">Leave blank to use the generated code as barcode.</p>
                        @error('barcode')
                            <p class="hint hint-error">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </div>
        </div>

        {{-- Footer Actions --}}
        <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-3 pt-4
                    border-t border-slate-200 dark:border-slate-800">
            <a href="{{ route('admin.locations.index') }}" wire:navigate class="btn btn-secondary text-center">
                Cancel
            </a>
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="btn btn-primary justify-center">
                <span wire:loading.remove wire:target="save">
                    <x-ui.icon name="check" class="w-4 h-4" /> Save Changes
                </span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>

    </form>

</div>
