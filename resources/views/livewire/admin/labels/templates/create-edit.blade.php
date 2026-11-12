<div class="space-y-6"
     x-data="{
         presets: ['100x50', '100x150', '50x25', '100x100'],
         forceCustom: false,
         zoom: 1,
         get activePreset() {
             if (this.forceCustom) return 'custom';
             const p = String(this.$wire.widthMm) + 'x' + String(this.$wire.heightMm);
             return this.presets.includes(p) ? p : 'custom';
         },
         applyPreset(w, h) {
             this.forceCustom = false;
             this.$wire.$set('widthMm', w, false);
             this.$wire.$set('heightMm', h);
         },
         useCustom() {
             this.forceCustom = true;
             this.$nextTick(() => this.$refs.widthInput?.focus());
         }
     }">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <a href="{{ route('admin.labels.templates.index') }}" wire:navigate
               class="inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-simba-600 dark:text-slate-400 mb-1">
                <x-ui.icon name="chevron-left" class="w-3.5 h-3.5" /> Label Templates
            </a>
            <h2 class="page-title flex items-center gap-2">
                {{ $isEditing ? 'Edit Label Template' : 'New Label Template' }}
                @if ($isEditing)
                    <span class="badge {{ $isActive ? 'badge-green' : 'badge-slate' }} text-xs">
                        {{ $isActive ? 'Active' : 'Inactive' }}
                    </span>
                @endif
            </h2>
            <p class="page-sub">Set the label size and content. The preview updates as you type.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-6 items-start">

        {{-- Left: Form --}}
        <div class="xl:col-span-3 space-y-5">

            {{-- Step 1: Basics --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title flex items-center gap-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-simba-100 text-simba-700 dark:bg-simba-900/40 dark:text-simba-300 text-xs font-bold">1</span>
                        Basics
                    </h3>
                </div>
                <div class="card-body space-y-5">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="label" for="tpl-name">Template name <span class="required-mark">*</span></label>
                            <input wire:model.live.debounce.400ms="name" id="tpl-name" type="text"
                                   placeholder="e.g. Pallet LPN - Standard"
                                   class="input w-full @error('name') border-red-400 @enderror" />
                            @error('name')<p class="hint hint-error">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="label" for="tpl-type">What will this label be printed for? <span class="required-mark">*</span></label>
                            <select wire:model.live="type" id="tpl-type"
                                    class="input w-full @error('type') border-red-400 @enderror disabled:opacity-60 disabled:cursor-not-allowed"
                                @disabled($isEditing)>
                                @foreach ($templateTypes as $case)
                                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
                                @endforeach
                            </select>
                            @if ($isEditing)
                                <p class="hint flex items-center gap-1">
                                    <x-ui.icon name="lock-closed" class="w-3 h-3" /> Locked after creation.
                                </p>
                            @endif
                            @error('type')<p class="hint hint-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    {{-- Location levels (only for Location type) --}}
                    @if ($type === 'location')
                    <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-4 space-y-2">
                        <div>
                            <span class="text-sm font-medium text-slate-800 dark:text-slate-200">Show for location levels</span>
                            <p class="hint mt-0.5">Select which hierarchy levels this template appears for in the print dialog. Leave all unchecked to show for every level.</p>
                        </div>
                        <div class="flex flex-wrap gap-2 pt-1">
                            @foreach ($this->locationLevels as $lvl)
                                @php $checked = in_array($lvl->value, $forLevels, true); @endphp
                                <label class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 cursor-pointer text-sm transition
                                    {{ $checked
                                        ? 'border-simba-500 bg-simba-50 dark:bg-simba-900/20 text-simba-700 dark:text-simba-300 font-medium'
                                        : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:border-slate-300 dark:hover:border-slate-600' }}">
                                    <input type="checkbox"
                                           wire:model.live="forLevels"
                                           value="{{ $lvl->value }}"
                                           class="sr-only" />
                                    {{ $lvl->label() }}
                                </label>
                            @endforeach
                        </div>
                        @if (empty($forLevels))
                            <p class="text-xs text-slate-500 dark:text-slate-400 pt-1">All levels selected (no filter)</p>
                        @endif
                    </div>
                    @endif

                    {{-- Active toggle --}}
                    <label class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 dark:border-slate-700 px-4 py-3 cursor-pointer hover:bg-slate-50 dark:hover:bg-white/[0.02] transition">
                        <div>
                            <span class="text-sm font-medium text-slate-800 dark:text-slate-200">Available for printing</span>
                            <p class="hint mt-0.5">Only active templates appear in the print dialog.</p>
                        </div>
                        <span class="relative inline-flex shrink-0">
                            <input wire:model.live="isActive" type="checkbox" class="peer sr-only" />
                            <span class="h-6 w-11 rounded-full bg-slate-300 dark:bg-slate-600 transition peer-checked:bg-simba-600 dark:peer-checked:bg-simba-600 peer-focus-visible:ring-2 peer-focus-visible:ring-simba-500 peer-focus-visible:ring-offset-2 dark:peer-focus-visible:ring-offset-zinc-900"></span>
                            <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                        </span>
                    </label>

                </div>
            </div>

            {{-- Step 2: Size & print quality --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title flex items-center gap-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-simba-100 text-simba-700 dark:bg-simba-900/40 dark:text-simba-300 text-xs font-bold">2</span>
                        Label Size &amp; Print Quality
                    </h3>
                </div>
                <div class="card-body space-y-5">

                    <div>
                        <label class="label">Label size <span class="required-mark">*</span></label>
                        <p class="hint mb-3">Pick a common roll size or enter your own. Must match the labels loaded in the printer.</p>

                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                            @foreach ([
                                ['100', '50',  'Shelf / Bin'],
                                ['100', '150', 'Shipping'],
                                ['50',  '25',  'Small item'],
                                ['100', '100', 'Square / Pallet'],
                            ] as [$pw, $ph, $use])
                                @php
                                    $scale = 36 / max($pw, $ph);
                                    $tw = round($pw * $scale);
                                    $th = round($ph * $scale);
                                @endphp
                                <button type="button"
                                        @click="applyPreset('{{ $pw }}', '{{ $ph }}')"
                                        :class="activePreset === '{{ $pw }}x{{ $ph }}'
                                            ? 'border-simba-500 bg-simba-50 dark:bg-simba-900/20 ring-1 ring-simba-500'
                                            : 'border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600'"
                                        class="flex flex-col items-center gap-2 rounded-xl border-2 p-3 transition">
                                    <span class="flex h-10 items-center justify-center">
                                        <span class="rounded-sm border-2 border-current text-slate-400 dark:text-slate-500"
                                              :class="activePreset === '{{ $pw }}x{{ $ph }}' && '!text-simba-500'"
                                              style="width: {{ $tw }}px; height: {{ $th }}px;"></span>
                                    </span>
                                    <span class="text-sm font-semibold text-slate-800 dark:text-slate-200 tabular-nums">{{ $pw }} × {{ $ph }}</span>
                                    <span class="text-[11px] text-slate-500 dark:text-slate-400 -mt-1.5">{{ $use }}</span>
                                </button>
                            @endforeach

                            <button type="button"
                                    @click="useCustom()"
                                    :class="activePreset === 'custom'
                                        ? 'border-simba-500 bg-simba-50 dark:bg-simba-900/20 ring-1 ring-simba-500'
                                        : 'border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600 border-dashed'"
                                    class="flex flex-col items-center gap-2 rounded-xl border-2 p-3 transition col-span-2 sm:col-span-1">
                                <span class="flex h-10 items-center justify-center">
                                    <x-ui.icon name="adjustments-horizontal" class="w-6 h-6 text-slate-400" />
                                </span>
                                <span class="text-sm font-semibold text-slate-800 dark:text-slate-200">Custom</span>
                                <span class="text-[11px] text-slate-500 dark:text-slate-400 -mt-1.5">Any size</span>
                            </button>
                        </div>

                        <div x-show="activePreset === 'custom'" x-collapse
                             class="grid grid-cols-2 gap-4 mt-4">
                            <div>
                                <label class="label text-xs" for="tpl-w">Width</label>
                                <div class="relative">
                                    <input x-ref="widthInput" wire:model.live.debounce.400ms="widthMm" id="tpl-w"
                                           type="number" min="10" max="500" step="1"
                                           class="input w-full pr-10 @error('widthMm') border-red-400 @enderror" />
                                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">mm</span>
                                </div>
                                @error('widthMm')<p class="hint hint-error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="label text-xs" for="tpl-h">Height</label>
                                <div class="relative">
                                    <input wire:model.live.debounce.400ms="heightMm" id="tpl-h"
                                           type="number" min="10" max="500" step="1"
                                           class="input w-full pr-10 @error('heightMm') border-red-400 @enderror" />
                                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">mm</span>
                                </div>
                                @error('heightMm')<p class="hint hint-error">{{ $message }}</p>@enderror
                            </div>
                            <p class="hint col-span-2 -mt-2">Allowed range: 10 to 500 mm.</p>
                        </div>

                        @if ($errors->has('widthMm') || $errors->has('heightMm'))
                            <p class="hint hint-error mt-2" x-show="activePreset !== 'custom'">Size is invalid. Choose Custom to fix it.</p>
                        @endif
                    </div>

                    {{-- DPI segmented control --}}
                    <div>
                        <label class="label">Printer resolution <span class="required-mark">*</span></label>
                        <p class="hint mb-2">Check the printer's spec sheet. Most Zebra desktop printers are 203 DPI.</p>
                        <div class="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 p-1 bg-slate-50 dark:bg-white/[0.03]">
                            @foreach (['203' => 'Standard', '300' => 'High quality'] as $dpiVal => $dpiLabel)
                                <label class="cursor-pointer">
                                    <input wire:model.live="dpi" type="radio" value="{{ $dpiVal }}" class="peer sr-only" />
                                    <span class="block rounded-md px-4 py-1.5 text-sm text-slate-600 dark:text-slate-300 transition
                                                 peer-checked:bg-white dark:peer-checked:bg-zinc-700 peer-checked:shadow-sm peer-checked:text-simba-700 dark:peer-checked:text-white peer-checked:font-semibold
                                                 peer-focus-visible:ring-2 peer-focus-visible:ring-simba-500">
                                        {{ $dpiVal }} DPI
                                        <span class="ml-1 text-xs font-normal opacity-70">{{ $dpiLabel }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                </div>
            </div>

            {{-- Step 3: Content (simple mode) --}}
            @if ($mode === 'simple')
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-simba-100 text-simba-700 dark:bg-simba-900/40 dark:text-simba-300 text-xs font-bold">3</span>
                            Label Content
                        </h3>
                    </div>
                    <div class="card-body space-y-6">

                        {{-- Layout --}}
                        <div>
                            <label class="label">Layout <span class="required-mark">*</span></label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-1">
                                @foreach ([
                                    ['big_code',     'Big Code',     'Large readable code, barcode below.'],
                                    ['barcode_left', 'Barcode Left', 'Barcode left, details on the right.'],
                                    ['barcode_top',  'Barcode Top',  'Barcode on top, details below.'],
                                ] as [$lval, $llabel, $ldesc])
                                    @php $selected = $layout === $lval; @endphp
                                    <button type="button"
                                            wire:click="$set('layout', '{{ $lval }}')"
                                            class="group relative rounded-xl border-2 p-3 text-left transition
                                               {{ $selected
                                                    ? 'border-simba-500 bg-simba-50 dark:bg-simba-900/20'
                                                    : 'border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600' }}">

                                        @if ($selected)
                                            <span class="absolute right-2 top-2 flex h-5 w-5 items-center justify-center rounded-full bg-simba-600 text-white">
                                            <x-ui.icon name="check" class="w-3 h-3" />
                                        </span>
                                        @endif

                                        {{-- Schematic thumbnail --}}
                                        <div class="mb-3 h-16 rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-zinc-900 p-1.5 flex gap-1.5
                                                {{ $lval === 'barcode_left' ? 'flex-row' : 'flex-col' }}">
                                            @php
                                                $bars = 'background: repeating-linear-gradient(90deg, currentColor 0 2px, transparent 2px 3px, currentColor 3px 4px, transparent 4px 6px);';
                                            @endphp
                                            @if ($lval === 'big_code')
                                                <div class="h-4 w-3/4 rounded-sm bg-slate-700 dark:bg-slate-300"></div>
                                                <div class="flex-1 text-slate-700 dark:text-slate-300" style="{{ $bars }}"></div>
                                            @elseif ($lval === 'barcode_left')
                                                <div class="w-1/3 text-slate-700 dark:text-slate-300" style="{{ $bars }}"></div>
                                                <div class="flex-1 flex flex-col justify-center gap-1">
                                                    <div class="h-1.5 w-full rounded-sm bg-slate-400"></div>
                                                    <div class="h-1.5 w-3/4 rounded-sm bg-slate-300 dark:bg-slate-500"></div>
                                                    <div class="h-1.5 w-5/6 rounded-sm bg-slate-300 dark:bg-slate-500"></div>
                                                </div>
                                            @else
                                                <div class="h-6 text-slate-700 dark:text-slate-300" style="{{ $bars }}"></div>
                                                <div class="flex-1 flex flex-col justify-center gap-1">
                                                    <div class="h-1.5 w-full rounded-sm bg-slate-400"></div>
                                                    <div class="h-1.5 w-2/3 rounded-sm bg-slate-300 dark:bg-slate-500"></div>
                                                </div>
                                            @endif
                                        </div>

                                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $llabel }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 leading-snug">{{ $ldesc }}</p>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Symbology --}}
                        <div>
                            <label class="label" for="tpl-sym">Barcode type <span class="required-mark">*</span></label>
                            <select wire:model.live="symbology" id="tpl-sym" class="input w-full sm:w-72">
                                <option value="code128">Code 128 (recommended for most scanners)</option>
                                <option value="qr">QR Code (phones and 2D scanners)</option>
                                <option value="code39">Code 39 (legacy systems)</option>
                                <option value="ean13">EAN-13 (retail products, 13 digits)</option>
                            </select>
                        </div>

                        {{-- Fields --}}
                        <div>
                            <div class="flex items-end justify-between gap-3 mb-1">
                                <label class="label mb-0">Extra details to print</label>
                                @if (count($this->availableFields))
                                    <div class="flex gap-3 text-xs">
                                        <button type="button" class="font-medium text-simba-600 hover:underline"
                                                wire:click="$set('fields', @js(array_values($this->availableFields)))">Select all</button>
                                        <button type="button" class="font-medium text-slate-500 hover:underline"
                                                wire:click="$set('fields', [])">Clear</button>
                                    </div>
                                @endif
                            </div>
                            <p class="hint mb-3">
                                <span class="font-mono font-medium text-slate-700 dark:text-slate-300">{{ $this->mainCodeField }}</span>
                                is always printed as the main code.
                            </p>

                            @if (count($this->availableFields))
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($this->availableFields as $field)
                                        <label class="cursor-pointer select-none">
                                            <input type="checkbox" wire:model.live="fields" value="{{ $field }}" class="peer sr-only" />
                                            <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-mono transition
                                                     border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-slate-300 dark:hover:border-slate-500
                                                     peer-checked:border-simba-500 peer-checked:bg-simba-50 peer-checked:text-simba-700
                                                     dark:peer-checked:bg-simba-900/30 dark:peer-checked:text-simba-300 dark:peer-checked:border-simba-500
                                                     peer-focus-visible:ring-2 peer-focus-visible:ring-simba-500">
                                            {{ $field }}
                                        </span>
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-slate-400">No extra fields for this label type.</p>
                            @endif
                        </div>

                        {{-- Options --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ([
                                ['showCodeText', 'Readable text under barcode', 'Lets staff type the code if a scan fails.'],
                                ['showCompany',  'Company name',                'Small text at the bottom of the label.'],
                            ] as [$prop, $title, $desc])
                                <label class="flex items-start gap-3 rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-2.5 cursor-pointer hover:bg-slate-50 dark:hover:bg-white/[0.02] transition">
                                    <input wire:model.live="{{ $prop }}" type="checkbox"
                                           class="mt-0.5 rounded border-slate-300 dark:border-slate-600 text-simba-600 focus:ring-simba-600 dark:bg-zinc-800" />
                                    <div>
                                        <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $title }}</span>
                                        <p class="hint mt-0.5">{{ $desc }}</p>
                                    </div>
                                </label>
                            @endforeach
                        </div>

                    </div>
                </div>
            @endif

            {{-- Advanced element editor --}}
            <div class="card">
                <button type="button"
                        wire:click="$toggle('showAdvanced')"
                        aria-expanded="{{ $showAdvanced ? 'true' : 'false' }}"
                        class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left hover:bg-slate-50 dark:hover:bg-white/[0.02] rounded-xl transition">
                    <span class="flex items-center gap-3">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 dark:bg-white/[0.06]">
                            <x-ui.icon name="code" class="w-4 h-4 text-slate-500" />
                        </span>
                        <span>
                            <span class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-200">
                                Advanced editor
                                @if ($mode === 'custom')
                                    <span class="badge badge-amber text-xs">Custom mode</span>
                                @endif
                            </span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">Position each element by hand. Optional.</span>
                        </span>
                    </span>
                    <x-ui.icon name="{{ $showAdvanced ? 'chevron-up' : 'chevron-down' }}" class="w-4 h-4 text-slate-400" />
                </button>

                @if ($showAdvanced)
                    <div class="border-t border-slate-200 dark:border-white/[0.08] p-5 space-y-4">

                        @if ($mode === 'custom')
                            <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 px-4 py-3 flex items-start gap-3">
                                <x-ui.icon name="exclamation-triangle" class="w-4 h-4 text-amber-600 mt-0.5 shrink-0" />
                                <div class="flex-1 text-sm text-amber-800 dark:text-amber-200">
                                    Custom mode is on, so the Label Content settings are ignored.
                                </div>
                                <button type="button" wire:click="resetToSimple"
                                        wire:confirm="Discard custom elements and go back to the simple layout?"
                                        class="shrink-0 text-sm font-semibold text-amber-800 dark:text-amber-200 underline hover:no-underline">
                                    Reset to simple
                                </button>
                            </div>
                        @else
                            <p class="hint">Adding or editing an element switches this template to custom mode.</p>
                        @endif

                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Add element:</span>
                            @foreach ([
                                'field'   => 'variable',
                                'text'    => 'pencil',
                                'barcode' => 'qr-code',
                                'line'    => 'minus',
                                'box'     => 'stop',
                            ] as $kind => $kicon)
                                <button type="button" wire:click="addElement('{{ $kind }}')"
                                        class="btn btn-secondary text-xs py-1 px-2.5">
                                    <x-ui.icon name="{{ $kicon }}" class="w-3.5 h-3.5" /> {{ ucfirst($kind) }}
                                </button>
                            @endforeach
                        </div>

                        @php
                            $wMm = max(10.0, (float) ($widthMm ?: 100));
                            $hMm = max(10.0, (float) ($heightMm ?: 50));
                            $oobCount = 0;
                            foreach ($elements as $el) {
                                $ex = (float) ($el['x_mm'] ?? 0); $ey = (float) ($el['y_mm'] ?? 0);
                                $ew = (float) ($el['w_mm'] ?? 0); $eh = (float) ($el['h_mm'] ?? 0);
                                if ($ex < 0 || $ey < 0 || $ex + $ew > $wMm || $ey + $eh > $hMm) $oobCount++;
                            }
                        @endphp

                        <div x-data="{
                            open: @js(array_fill(0, count($elements), false)),
                            init() {
                                this.$watch(() => this.$wire.elements.length, (newLen, oldLen) => {
                                    if (newLen > oldLen) {
                                        const arr = new Array(newLen).fill(false);
                                        arr[newLen - 1] = true;
                                        this.open = arr;
                                    } else {
                                        this.open = this.open.slice(0, newLen);
                                    }
                                });
                            },
                            isOpen(i) { return this.open[i] ?? false; },
                            toggle(i) { const a = [...this.open]; a[i] = !a[i]; this.open = a; },
                            expandAll()  { this.open = new Array(this.$wire.elements.length).fill(true); },
                            collapseAll(){ this.open = new Array(this.$wire.elements.length).fill(false); }
                        }" class="space-y-2">

                            @if (count($elements))
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ count($elements) }} {{ \Illuminate\Support\Str::plural('element', count($elements)) }}
                                        @if ($oobCount > 0)
                                            <span class="ml-1.5 inline-flex items-center gap-1 rounded-full bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 px-2 py-0.5 text-[11px] font-semibold">
                                                <x-ui.icon name="exclamation-triangle" class="w-3 h-3" /> {{ $oobCount }} off label
                                            </span>
                                        @endif
                                    </span>
                                    <div class="flex gap-3 text-xs">
                                        <button type="button" @click="expandAll()" class="font-medium text-simba-600 hover:underline">Expand all</button>
                                        <button type="button" @click="collapseAll()" class="text-slate-500 hover:underline">Collapse all</button>
                                    </div>
                                </div>

                                <div class="divide-y divide-slate-200 dark:divide-slate-700 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
                                    @foreach ($elements as $i => $el)
                                        @php
                                            $ex = (float) ($el['x_mm'] ?? 0); $ey = (float) ($el['y_mm'] ?? 0);
                                            $ew = (float) ($el['w_mm'] ?? 0); $eh = (float) ($el['h_mm'] ?? 0);
                                            $isOob = $ex < 0 || $ey < 0 || $ex + $ew > $wMm || $ey + $eh > $hMm;
                                            $kindColor = match ($el['kind']) {
                                                'field'   => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
                                                'text'    => 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
                                                'barcode' => 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300',
                                                'line'    => 'bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300',
                                                'box'     => 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300',
                                                default   => 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
                                            };
                                        @endphp
                                        <div wire:key="el-{{ $i }}-{{ $el['kind'] }}"
                                             class="{{ $isOob ? 'bg-red-50/50 dark:bg-red-950/20' : '' }} transition-colors">

                                            {{-- Summary row --}}
                                            <div @click="toggle({{ $i }})"
                                                 class="flex items-center gap-2 px-3 py-2 cursor-pointer select-none hover:bg-slate-50 dark:hover:bg-white/[0.03] transition-colors"
                                                 :class="isOpen({{ $i }}) ? 'bg-slate-50 dark:bg-white/[0.02]' : ''">
                                                <x-ui.icon name="chevron-right"
                                                           class="w-3.5 h-3.5 text-slate-400 shrink-0 transition-transform duration-150"
                                                           ::class="isOpen({{ $i }}) ? 'rotate-90' : ''" />
                                                <span class="flex h-5 min-w-[1.25rem] items-center justify-center rounded bg-slate-100 dark:bg-slate-800 px-1.5 text-[11px] font-bold text-slate-500 tabular-nums shrink-0">{{ $i + 1 }}</span>
                                                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide {{ $kindColor }} shrink-0">{{ $el['kind'] }}</span>
                                                @if (! empty($el['field_key']))
                                                    <span class="text-xs font-mono text-slate-500 dark:text-slate-400 truncate min-w-0">{{ $el['field_key'] }}</span>
                                                @elseif (! empty($el['value']))
                                                    <span class="text-xs text-slate-400 truncate min-w-0">"{{ $el['value'] }}"</span>
                                                @endif
                                                <span class="ml-auto text-[11px] text-slate-400 dark:text-slate-500 tabular-nums shrink-0 whitespace-nowrap">
                                                    {{ $el['x_mm'] ?? 0 }},{{ $el['y_mm'] ?? 0 }} &middot; {{ $el['w_mm'] ?? 0 }}&times;{{ $el['h_mm'] ?? 0 }}
                                                </span>
                                                @if ($isOob)
                                                    <span class="inline-flex items-center gap-0.5 rounded-full bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 px-1.5 py-0.5 text-[10px] font-semibold shrink-0">
                                                        <x-ui.icon name="exclamation-triangle" class="w-2.5 h-2.5" /> Off label
                                                    </span>
                                                @endif
                                                <button type="button" wire:click="removeElement({{ $i }})" @click.stop
                                                        title="Remove"
                                                        class="ml-1 shrink-0 rounded p-1 text-slate-300 dark:text-slate-600 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600 dark:hover:text-red-400 transition">
                                                    <x-ui.icon name="trash" class="w-3.5 h-3.5" />
                                                </button>
                                            </div>

                                            {{-- Expanded edit body --}}
                                            <div x-show="isOpen({{ $i }})" x-collapse
                                                 class="border-t border-slate-100 dark:border-slate-800 px-3 pb-3 pt-2 space-y-2">

                                                {{-- Row 1: X Y W H --}}
                                                <div class="grid grid-cols-12 gap-2">
                                                    @foreach (['x_mm' => 'X', 'y_mm' => 'Y', 'w_mm' => 'W', 'h_mm' => 'H'] as $coord => $clabel)
                                                        <div class="col-span-3">
                                                            <label class="label text-[11px] mb-0.5">{{ $clabel }} <span class="font-normal text-slate-400">mm</span></label>
                                                            <input type="number" step="0.5"
                                                                   value="{{ $el[$coord] ?? '' }}"
                                                                   wire:change="updateElement({{ $i }}, { '{{ $coord }}': parseFloat($event.target.value) })"
                                                                   class="input w-full h-8 py-1 text-xs {{ $isOob ? 'border-red-300 dark:border-red-700' : '' }}" />
                                                        </div>
                                                    @endforeach
                                                </div>

                                                {{-- Row 2: type-specific --}}
                                                @if (in_array($el['kind'], ['field', 'text']))
                                                    <div class="grid grid-cols-12 gap-2">
                                                        <div class="col-span-5">
                                                            @if ($el['kind'] === 'field')
                                                                <label class="label text-[11px] mb-0.5">Data field</label>
                                                                <input type="text" value="{{ $el['field_key'] ?? '' }}"
                                                                       list="available-fields"
                                                                       wire:change="updateElement({{ $i }}, { field_key: $event.target.value })"
                                                                       class="input w-full h-8 py-1 text-xs font-mono" />
                                                            @else
                                                                <label class="label text-[11px] mb-0.5">Text</label>
                                                                <input type="text" value="{{ $el['value'] ?? '' }}"
                                                                       wire:change="updateElement({{ $i }}, { value: $event.target.value })"
                                                                       class="input w-full h-8 py-1 text-xs" />
                                                            @endif
                                                        </div>
                                                        <div class="col-span-2">
                                                            <label class="label text-[11px] mb-0.5">pt</label>
                                                            <input type="number" min="4" max="72" value="{{ $el['font_size_pt'] ?? 10 }}"
                                                                   wire:change="updateElement({{ $i }}, { font_size_pt: parseInt($event.target.value) })"
                                                                   class="input w-full h-8 py-1 text-xs" />
                                                        </div>
                                                        <div class="col-span-3">
                                                            <label class="label text-[11px] mb-0.5">Align</label>
                                                            <select wire:change="updateElement({{ $i }}, { align: $event.target.value })"
                                                                    class="input w-full h-8 py-1 text-xs">
                                                                @foreach (['left', 'center', 'right'] as $a)
                                                                    <option value="{{ $a }}" @selected(($el['align'] ?? 'left') === $a)>{{ ucfirst($a) }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="col-span-2 flex items-end pb-1">
                                                            <label class="flex items-center gap-1 cursor-pointer">
                                                                <input type="checkbox" @checked($el['bold'] ?? false)
                                                                       wire:change="updateElement({{ $i }}, { bold: $event.target.checked })"
                                                                       class="rounded text-simba-600" />
                                                                <span class="text-xs font-bold text-slate-600 dark:text-slate-300">Bold</span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                @endif

                                                @if ($el['kind'] === 'barcode')
                                                    <div class="grid grid-cols-12 gap-2">
                                                        <div class="col-span-5">
                                                            <label class="label text-[11px] mb-0.5">Data field</label>
                                                            <input type="text" value="{{ $el['field_key'] ?? '' }}"
                                                                   list="available-fields"
                                                                   wire:change="updateElement({{ $i }}, { field_key: $event.target.value })"
                                                                   class="input w-full h-8 py-1 text-xs font-mono" />
                                                        </div>
                                                        <div class="col-span-5">
                                                            <label class="label text-[11px] mb-0.5">Barcode type</label>
                                                            <select wire:change="updateElement({{ $i }}, { symbology: $event.target.value })"
                                                                    class="input w-full h-8 py-1 text-xs">
                                                                @foreach (['code128' => 'Code 128', 'qr' => 'QR', 'code39' => 'Code 39', 'ean13' => 'EAN-13'] as $sym => $symLabel)
                                                                    <option value="{{ $sym }}" @selected(($el['symbology'] ?? 'code128') === $sym)>{{ $symLabel }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="col-span-2 flex items-end pb-1">
                                                            <label class="flex items-center gap-1 cursor-pointer">
                                                                <input type="checkbox" @checked($el['show_text'] ?? true)
                                                                       wire:change="updateElement({{ $i }}, { show_text: $event.target.checked })"
                                                                       class="rounded text-simba-600" />
                                                                <span class="text-xs text-slate-600 dark:text-slate-300">Text</span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                @endif

                                                @if (in_array($el['kind'], ['line', 'box']))
                                                    <div class="grid grid-cols-12 gap-2">
                                                        <div class="col-span-3">
                                                            <label class="label text-[11px] mb-0.5">Thickness <span class="font-normal text-slate-400">mm</span></label>
                                                            <input type="number" step="0.1" min="0.1" value="{{ $el['thickness_mm'] ?? 0.5 }}"
                                                                   wire:change="updateElement({{ $i }}, { thickness_mm: parseFloat($event.target.value) })"
                                                                   class="input w-full h-8 py-1 text-xs" />
                                                        </div>
                                                    </div>
                                                @endif

                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="rounded-lg border-2 border-dashed border-slate-200 dark:border-slate-700 py-8 text-center">
                                    <x-ui.icon name="squares-plus" class="mx-auto w-7 h-7 text-slate-300 dark:text-slate-600 mb-2" />
                                    <p class="text-sm text-slate-500 dark:text-slate-400">No custom elements yet.</p>
                                    <p class="hint">Use the buttons above, or keep using the simple layout.</p>
                                </div>
                            @endif

                        </div>

                        <datalist id="available-fields">
                            <option value="{{ $this->mainCodeField }}"></option>
                            @foreach ($this->availableFields as $field)
                                <option value="{{ $field }}"></option>
                            @endforeach
                        </datalist>

                    </div>
                @endif
            </div>

            {{-- Action bar (glass, floating) --}}
            <div class="sticky bottom-4 z-30 mt-6">
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 shadow-lg bg-white/80 backdrop-blur-sm dark:bg-zinc-900/80">
                    <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-2 px-4 py-3">
                        <p class="hidden sm:block text-xs text-slate-500 dark:text-slate-400">
                            @if ($errors->any())
                                <span class="text-red-600 dark:text-red-400 font-medium">Please fix the highlighted fields.</span>
                            @else
                                Changes are not saved until you click save.
                            @endif
                        </p>
                        <div class="flex flex-col-reverse sm:flex-row gap-2">
                            <a href="{{ route('admin.labels.templates.index') }}" wire:navigate class="btn btn-secondary justify-center">
                                Cancel
                            </a>
                            <button type="button" wire:click="saveAndSetDefault"
                                    wire:loading.attr="disabled" wire:target="saveAndSetDefault"
                                    class="btn btn-secondary justify-center">
                                <x-ui.icon name="star" class="w-4 h-4" /> Save as Default
                            </button>
                            <button type="button" wire:click="save"
                                    wire:loading.attr="disabled" wire:target="save"
                                    class="btn btn-primary justify-center">
                                <x-ui.icon name="check" class="w-4 h-4" wire:loading.remove wire:target="save" />
                                <x-ui.icon name="arrow-path" class="w-4 h-4 animate-spin" wire:loading wire:target="save" />
                                {{ $isEditing ? 'Save Changes' : 'Create Template' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        {{-- Right: Live Preview --}}
        <div class="xl:col-span-2 xl:sticky xl:top-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title flex items-center gap-2 w-full">
                        <x-ui.icon name="eye" class="w-4 h-4" /> Live Preview
                        <span class="ml-auto flex items-center gap-2">
                            <span wire:loading.delay class="flex items-center gap-1 text-xs font-normal text-slate-400">
                                <x-ui.icon name="arrow-path" class="w-3.5 h-3.5 animate-spin" /> Updating
                            </span>
                            <span class="badge {{ $mode === 'simple' ? 'badge-green' : 'badge-amber' }} text-xs">
                                {{ $mode === 'simple' ? 'Simple' : 'Custom' }}
                            </span>
                        </span>
                    </h3>
                </div>
                <div class="card-body">

                    <div class="relative rounded-lg p-4 overflow-auto min-h-[220px] flex items-start justify-center
                                bg-slate-100 dark:bg-slate-800/70
                                [background-image:radial-gradient(theme(colors.slate.300)_1px,transparent_1px)]
                                dark:[background-image:radial-gradient(theme(colors.slate.700)_1px,transparent_1px)]
                                [background-size:12px_12px]">
                        @if ($this->svgPreview)
                            <div class="origin-top transition-transform duration-200 shadow-lg ring-1 ring-black/5 bg-white [&>svg]:block [&>svg]:max-w-full [&>svg]:h-auto"
                                 :style="`transform: scale(${zoom}); transform-origin: top center; margin-bottom: calc((${zoom} - 1) * 100%)`">
                                {!! $this->svgPreview !!}
                            </div>
                        @else
                            <div class="text-center">
                                <x-ui.icon name="photo" class="mx-auto w-8 h-8 mb-2 text-slate-300 dark:text-slate-600" />
                                <p class="text-sm text-slate-500 dark:text-slate-400">Preview will appear here</p>
                                <p class="hint">Enter a valid size to start.</p>
                            </div>
                        @endif
                    </div>

                    @if ($this->svgPreview)
                        <div class="mt-3 flex items-center justify-center gap-1">
                            @foreach ([[0.75,'75%'],[1.0,'100%'],[1.5,'150%']] as [$z, $zl])
                                <button type="button" @click="zoom = {{ $z }}"
                                        :class="zoom === {{ $z }} ? 'bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-100' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800'"
                                        class="rounded px-2 py-0.5 text-xs font-medium transition">{{ $zl }}</button>
                            @endforeach
                        </div>
                    @endif

                    @if (! empty($previewWarnings))
                        <div class="mt-4 rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-3 space-y-1.5">
                            <p class="text-xs font-semibold text-amber-800 dark:text-amber-300">
                                {{ count($previewWarnings) }} {{ \Illuminate\Support\Str::plural('issue', count($previewWarnings)) }} to check
                            </p>
                            @foreach ($previewWarnings as $warn)
                                <div class="flex items-start gap-2 text-xs text-amber-700 dark:text-amber-400">
                                    <x-ui.icon name="exclamation-triangle" class="w-3.5 h-3.5 mt-0.5 shrink-0" />
                                    {{ $warn }}
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <dl class="mt-4 grid grid-cols-3 divide-x divide-slate-200 dark:divide-slate-700 rounded-lg border border-slate-200 dark:border-slate-700 text-center">
                        <div class="py-2">
                            <dt class="text-[11px] uppercase tracking-wide text-slate-400">Size</dt>
                            <dd class="text-sm font-semibold text-slate-700 dark:text-slate-200 tabular-nums">{{ $widthMm }}×{{ $heightMm }}<span class="text-xs font-normal text-slate-400"> mm</span></dd>
                        </div>
                        <div class="py-2">
                            <dt class="text-[11px] uppercase tracking-wide text-slate-400">Resolution</dt>
                            <dd class="text-sm font-semibold text-slate-700 dark:text-slate-200 tabular-nums">{{ $dpi }}<span class="text-xs font-normal text-slate-400"> DPI</span></dd>
                        </div>
                        <div class="py-2">
                            <dt class="text-[11px] uppercase tracking-wide text-slate-400">Elements</dt>
                            <dd class="text-sm font-semibold text-slate-700 dark:text-slate-200 tabular-nums">{{ count($elements) }}</dd>
                        </div>
                    </dl>

                    @if ($isEditing)
                        <button type="button" wire:click="testPrint" wire:loading.attr="disabled" wire:target="testPrint"
                                class="btn btn-secondary w-full mt-4 justify-center">
                            <x-ui.icon name="printer" class="w-4 h-4" />
                            <span wire:loading.remove wire:target="testPrint">Send Test Print</span>
                            <span wire:loading wire:target="testPrint">Sending...</span>
                        </button>
                    @else
                        <p class="hint text-center mt-4">Save the template to enable test printing.</p>
                    @endif

                </div>
            </div>
        </div>

    </div>


</div>
