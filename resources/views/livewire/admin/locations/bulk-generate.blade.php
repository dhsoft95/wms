<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Bulk Generate Locations</h2>
            <p class="page-sub">Create a sequential range of locations (e.g. bins 01–20) in one operation.</p>
        </div>
        <a href="{{ route('admin.locations.index') }}" wire:navigate class="btn btn-secondary">
            <x-ui.icon name="x" class="w-4 h-4" /> Cancel
        </a>
    </div>

    @if (! $previewing)

        {{-- ── STEP 1: Configuration Form ─────────────────────────────────── --}}

        <form wire:submit="preview" class="max-w-2xl space-y-6">

            {{-- Info --}}
            <div class="rounded-xl border border-simba-200 dark:border-simba-800/50 bg-simba-50/60 dark:bg-simba-900/20 px-4 py-3 flex gap-3">
                <x-ui.icon name="eye" class="mt-0.5 h-4 w-4 shrink-0 text-simba-600 dark:text-simba-400" />
                <p class="text-sm text-simba-800 dark:text-simba-300">
                    Provide a <strong>start</strong> and <strong>end</strong> segment value. Numeric ranges
                    (e.g. <code class="font-mono bg-simba-100 dark:bg-simba-900/50 px-1 rounded">01</code>–<code class="font-mono bg-simba-100 dark:bg-simba-900/50 px-1 rounded">20</code>)
                    will be zero-padded. Alphabetic single-character ranges
                    (e.g. <code class="font-mono bg-simba-100 dark:bg-simba-900/50 px-1 rounded">A</code>–<code class="font-mono bg-simba-100 dark:bg-simba-900/50 px-1 rounded">Z</code>)
                    are also supported.
                </p>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <x-ui.icon name="home" class="w-4 h-4" />
                        Configuration
                    </h3>
                </div>

                <div class="card-body space-y-5">

                    {{-- Warehouse --}}
                    <div>
                        <label class="label" for="bg-warehouse">Warehouse <span class="text-red-500">*</span></label>
                        <select
                            wire:model.live="warehouseId"
                            id="bg-warehouse"
                            class="input w-full @error('warehouseId') border-red-400 @enderror"
                        >
                            <option value="">— Select warehouse —</option>
                            @foreach ($this->warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->code }} — {{ $wh->name }}</option>
                            @endforeach
                        </select>
                        @error('warehouseId')
                            <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Parent Location (optional) --}}
                    <div>
                        <label class="label" for="bg-parent">
                            Parent Location
                            <span class="text-slate-400 dark:text-slate-500 font-normal">(optional)</span>
                        </label>
                        <select
                            wire:model.live="parentId"
                            id="bg-parent"
                            class="input w-full"
                            {{ (! $warehouseId || $this->parentOptions->isEmpty()) ? 'disabled' : '' }}
                        >
                            <option value="">— Root level (no parent) —</option>
                            @foreach ($this->parentOptions as $option)
                                <option value="{{ $option->id }}">{{ $option->code }} ({{ $option->level->label() }})</option>
                            @endforeach
                        </select>
                        <p class="hint">Leave blank to create root-level locations directly under the warehouse.</p>
                    </div>

                    {{-- Level & Type in a grid --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="label" for="bg-level">Level <span class="text-red-500">*</span></label>
                            <select
                                wire:model.live="level"
                                id="bg-level"
                                class="input w-full @error('level') border-red-400 @enderror"
                            >
                                @foreach (\App\Enums\LocationLevel::cases() as $lvl)
                                    <option value="{{ $lvl->value }}">{{ $lvl->label() }}</option>
                                @endforeach
                            </select>
                            @error('level')
                                <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="label" for="bg-type">Type <span class="text-red-500">*</span></label>
                            <select
                                wire:model="type"
                                id="bg-type"
                                class="input w-full @error('type') border-red-400 @enderror"
                            >
                                @foreach (\App\Enums\LocationType::cases() as $t)
                                    <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                @endforeach
                            </select>
                            @error('type')
                                <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                </div>
            </div>

            {{-- Range Card --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <x-ui.icon name="arrows" class="w-4 h-4" />
                        Segment Range
                    </h3>
                </div>

                <div class="card-body space-y-5">

                    {{-- Optional prefix --}}
                    <div>
                        <label class="label" for="bg-prefix">
                            Segment Prefix
                            <span class="text-slate-400 dark:text-slate-500 font-normal">(optional)</span>
                        </label>
                        <input
                            wire:model="segmentPrefix"
                            id="bg-prefix"
                            type="text"
                            maxlength="10"
                            placeholder="e.g. R (would produce R01, R02…)"
                            class="input mono uppercase w-full"
                        />
                        <p class="hint">Prepended to each generated segment value.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">

                        {{-- Start --}}
                        <div>
                            <label class="label" for="bg-start">Start <span class="text-red-500">*</span></label>
                            <input
                                wire:model="segmentStart"
                                id="bg-start"
                                type="text"
                                maxlength="10"
                                placeholder="01 or A"
                                class="input mono uppercase w-full @error('segmentStart') border-red-400 @enderror"
                            />
                            @error('segmentStart')
                                <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- End --}}
                        <div>
                            <label class="label" for="bg-end">End <span class="text-red-500">*</span></label>
                            <input
                                wire:model="segmentEnd"
                                id="bg-end"
                                type="text"
                                maxlength="10"
                                placeholder="20 or Z"
                                class="input mono uppercase w-full @error('segmentEnd') border-red-400 @enderror"
                            />
                            @error('segmentEnd')
                                <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Padding --}}
                        <div>
                            <label class="label" for="bg-padding">Digit Padding</label>
                            <input
                                wire:model="segmentPadding"
                                id="bg-padding"
                                type="number"
                                min="1"
                                max="6"
                                placeholder="2"
                                class="input w-full @error('segmentPadding') border-red-400 @enderror"
                            />
                            <p class="hint">Zero-pad numeric values to this length. Ignored for alphabetic ranges.</p>
                            @error('segmentPadding')
                                <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>

                </div>
            </div>

            {{-- Footer --}}
            <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-3 pt-4
                        border-t border-slate-200 dark:border-slate-800">
                <a href="{{ route('admin.locations.index') }}" wire:navigate class="btn btn-secondary text-center">
                    Cancel
                </a>
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="btn btn-primary justify-center">
                    <span wire:loading.remove wire:target="preview">
                        <x-ui.icon name="eye" class="w-4 h-4" /> Preview
                    </span>
                    <span wire:loading wire:target="preview">Generating preview…</span>
                </button>
            </div>

        </form>

    @else

        {{-- ── STEP 2: Preview & Confirm ───────────────────────────────────── --}}

        @php
            $newCount      = collect($previewList)->where('exists', false)->count();
            $skippedCount  = collect($previewList)->where('exists', true)->count();
        @endphp

        <div class="max-w-2xl space-y-6">

            {{-- Summary --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div class="rounded-xl bg-white/60 dark:bg-white/[0.06] ring-1 ring-slate-200 dark:ring-white/[0.08] px-4 py-3 text-center">
                    <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ count($previewList) }}</p>
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mt-0.5">Total</p>
                </div>
                <div class="rounded-xl bg-emerald-50/60 dark:bg-emerald-900/20 ring-1 ring-emerald-200 dark:ring-emerald-700/30 px-4 py-3 text-center">
                    <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">{{ $newCount }}</p>
                    <p class="text-xs font-medium text-emerald-600 dark:text-emerald-500 mt-0.5">Will be created</p>
                </div>
                <div class="rounded-xl bg-amber-50/60 dark:bg-amber-900/20 ring-1 ring-amber-200 dark:ring-amber-700/30 px-4 py-3 text-center">
                    <p class="text-2xl font-bold text-amber-700 dark:text-amber-400">{{ $skippedCount }}</p>
                    <p class="text-xs font-medium text-amber-600 dark:text-amber-500 mt-0.5">Already exist</p>
                </div>
            </div>

            {{-- Preview Table --}}
            <div class="card overflow-hidden">
                <div class="card-header">
                    <h3 class="card-title">
                        <x-ui.icon name="eye" class="w-4 h-4" />
                        Preview
                    </h3>
                </div>

                <div class="overflow-x-auto max-h-96 overflow-y-auto">
                    <table class="w-full text-left text-sm">

                        <thead class="text-[11px] font-semibold uppercase tracking-wider sticky top-0
                                      text-slate-500 dark:text-slate-400
                                      bg-white/90 dark:bg-zinc-900/90
                                      border-b border-white/50 dark:border-white/[0.07]">
                        <tr>
                            <th class="px-5 py-3">Code</th>
                            <th class="px-5 py-3">Segment</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                        </thead>

                        <tbody class="divide-y divide-white/50 dark:divide-white/[0.06]">
                        @foreach ($previewList as $item)
                            <tr class="transition {{ $item['exists'] ? 'opacity-50' : 'hover:bg-white/30 dark:hover:bg-white/[0.04]' }}">
                                <td class="px-5 py-2.5">
                                    <span class="font-mono text-xs font-semibold text-slate-800 dark:text-slate-100">
                                        {{ $item['code'] }}
                                    </span>
                                </td>
                                <td class="px-5 py-2.5 font-mono text-xs text-slate-500 dark:text-slate-400">
                                    {{ $item['segment'] }}
                                </td>
                                <td class="px-5 py-2.5">
                                    @if ($item['exists'])
                                        <span class="badge badge-amber">Already exists</span>
                                    @else
                                        <span class="badge badge-green">New</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>

                    </table>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3 pt-4
                        border-t border-slate-200 dark:border-slate-800">
                <button type="button"
                        wire:click="backToForm"
                        class="btn btn-secondary">
                    <x-ui.icon name="x" class="w-4 h-4" /> Back
                </button>

                @if ($newCount > 0)
                    <button type="button"
                            wire:click="generate"
                            wire:loading.attr="disabled"
                            class="btn btn-primary justify-center">
                        <span wire:loading.remove wire:target="generate">
                            <x-ui.icon name="check" class="w-4 h-4" />
                            Create {{ $newCount }} Location{{ $newCount !== 1 ? 's' : '' }}
                        </span>
                        <span wire:loading wire:target="generate">Creating…</span>
                    </button>
                @else
                    <p class="text-sm text-amber-600 dark:text-amber-400 font-medium">All locations already exist — nothing to create.</p>
                @endif
            </div>

        </div>

    @endif

</div>
