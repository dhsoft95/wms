<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Edit Label Template</h2>
            <p class="page-sub">{{ $template->name }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.labels.templates.index') }}" wire:navigate class="btn btn-secondary">
                <x-ui.icon name="arrow-left" class="w-4 h-4" /> Back
            </a>
        </div>
    </div>

    <form wire:submit="save">
        <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">

            {{-- ── Left Panel ─────────────────────────────────────────────── --}}
            <div class="xl:col-span-3 space-y-5">

                {{-- Basic Settings Card --}}
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <x-ui.icon name="adjustments" class="w-4 h-4" />
                            Template Settings
                        </h3>
                    </div>
                    <div class="card-body space-y-4">

                        {{-- Name --}}
                        <div>
                            <label class="label" for="et-name">Name <span class="required-mark">*</span></label>
                            <input wire:model="name" id="et-name" type="text" class="input w-full @error('name') border-red-400 @enderror" />
                            @error('name') <p class="hint hint-error">{{ $message }}</p> @enderror
                        </div>

                        {{-- Type --}}
                        <div>
                            <label class="label" for="et-type">Type <span class="required-mark">*</span></label>
                            <select wire:model.live="type" id="et-type" class="input w-full @error('type') border-red-400 @enderror">
                                @foreach ($templateTypes as $case)
                                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
                                @endforeach
                            </select>
                            @error('type') <p class="hint hint-error">{{ $message }}</p> @enderror
                        </div>

                        {{-- Dimensions --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="label" for="et-width">Width (mm) <span class="required-mark">*</span></label>
                                <input wire:model.live="widthMm" id="et-width" type="number" min="10" max="500" class="input w-full @error('widthMm') border-red-400 @enderror" />
                                @error('widthMm') <p class="hint hint-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="label" for="et-height">Height (mm) <span class="required-mark">*</span></label>
                                <input wire:model.live="heightMm" id="et-height" type="number" min="10" max="500" class="input w-full @error('heightMm') border-red-400 @enderror" />
                                @error('heightMm') <p class="hint hint-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        {{-- DPI --}}
                        <div>
                            <label class="label">Resolution (DPI)</label>
                            <div class="flex gap-4 mt-1">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input wire:model="dpi" type="radio" value="203" class="text-simba-600 focus:ring-simba-600" />
                                    <span class="text-sm text-slate-700 dark:text-slate-300">203 DPI</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input wire:model="dpi" type="radio" value="300" class="text-simba-600 focus:ring-simba-600" />
                                    <span class="text-sm text-slate-700 dark:text-slate-300">300 DPI</span>
                                </label>
                            </div>
                        </div>

                        {{-- Active --}}
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input wire:model="isActive" type="checkbox" class="rounded border-slate-300 dark:border-slate-600 text-simba-600 focus:ring-simba-600 dark:bg-zinc-800" />
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Active</span>
                        </label>

                    </div>
                </div>

                {{-- Elements Card --}}
                <div class="card" x-data="{ openIdx: null }">
                    <div class="card-header flex items-center justify-between">
                        <h3 class="card-title">
                            <x-ui.icon name="template" class="w-4 h-4" />
                            Elements
                            <span class="ml-2 text-xs font-normal text-slate-400">({{ count($elements) }})</span>
                        </h3>

                        {{-- Add Element Dropdown --}}
                        <div x-data="{ open: false }" class="relative">
                            <button type="button" @click="open = !open" class="btn btn-secondary btn-sm">
                                <x-ui.icon name="plus" class="w-3.5 h-3.5" /> Add Element
                                <x-ui.icon name="chevron-down" class="w-3 h-3 ml-1" />
                            </button>
                            <div
                                x-show="open"
                                x-cloak
                                @click.outside="open = false"
                                class="absolute right-0 mt-1 w-40 rounded-lg shadow-lg bg-white dark:bg-zinc-800 border border-slate-200 dark:border-slate-700 z-10 py-1"
                            >
                                @foreach (['text' => 'Text', 'field' => 'Field', 'barcode' => 'Barcode', 'line' => 'Line', 'box' => 'Box'] as $kind => $label)
                                    <button
                                        type="button"
                                        wire:click="addElement('{{ $kind }}')"
                                        @click="open = false; openIdx = {{ count($elements) }}"
                                        class="w-full text-left px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-zinc-700"
                                    >
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($elements as $idx => $el)
                            <div wire:key="el-{{ $idx }}" x-data="{ expanded: openIdx === {{ $idx }} }" class="px-4 py-3">

                                {{-- Element Row Header --}}
                                <div class="flex items-center justify-between gap-2">
                                    <button
                                        type="button"
                                        @click="expanded = !expanded"
                                        class="flex items-center gap-2 text-left flex-1 min-w-0"
                                    >
                                        {{-- Kind icon --}}
                                        @switch($el['kind'])
                                            @case('text')
                                                <x-ui.icon name="text" class="w-4 h-4 shrink-0 text-slate-500" />
                                                @break
                                            @case('field')
                                                <x-ui.icon name="tag" class="w-4 h-4 shrink-0 text-brand-500" />
                                                @break
                                            @case('barcode')
                                                <x-ui.icon name="qr-code" class="w-4 h-4 shrink-0 text-amber-500" />
                                                @break
                                            @case('line')
                                                <x-ui.icon name="minus" class="w-4 h-4 shrink-0 text-slate-400" />
                                                @break
                                            @case('box')
                                                <x-ui.icon name="view-grid" class="w-4 h-4 shrink-0 text-slate-400" />
                                                @break
                                            @default
                                                <x-ui.icon name="template" class="w-4 h-4 shrink-0 text-slate-400" />
                                        @endswitch

                                        <span class="text-sm font-medium capitalize text-slate-700 dark:text-slate-300">{{ $el['kind'] }}</span>

                                        @if ($el['kind'] === 'text' && !empty($el['value']))
                                            <span class="text-xs text-slate-400 truncate">"{{ $el['value'] }}"</span>
                                        @elseif (in_array($el['kind'], ['field', 'barcode']) && !empty($el['field_key']))
                                            <span class="text-xs text-slate-400 truncate mono">{{ $el['field_key'] }}</span>
                                        @endif

                                        <span class="text-xs text-slate-400 shrink-0 ml-auto">{{ $el['x_mm'] }},{{ $el['y_mm'] }} mm</span>
                                        <x-ui.icon name="chevron-down" class="w-3.5 h-3.5 shrink-0 text-slate-400 transition-transform" x-bind:class="expanded ? 'rotate-180' : ''" />
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="removeElement({{ $idx }})"
                                        wire:confirm="Remove this element?"
                                        class="btn btn-ghost btn-xs text-red-500 hover:text-red-700 shrink-0"
                                    >
                                        <x-ui.icon name="trash" class="w-3.5 h-3.5" />
                                    </button>
                                </div>

                                {{-- Element Properties (expanded) --}}
                                <div x-show="expanded" x-cloak class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">

                                    {{-- Common: x, y, w, h --}}
                                    @foreach (['x_mm' => 'X (mm)', 'y_mm' => 'Y (mm)', 'w_mm' => 'W (mm)', 'h_mm' => 'H (mm)'] as $field => $fieldLabel)
                                        <div>
                                            <label class="label text-xs">{{ $fieldLabel }}</label>
                                            <input
                                                type="number"
                                                step="0.5"
                                                value="{{ $el[$field] ?? 0 }}"
                                                wire:change="updateElement({{ $idx }}, {'{{ $field }}': parseFloat($event.target.value)})"
                                                class="input w-full text-sm"
                                            />
                                        </div>
                                    @endforeach

                                    {{-- Text-specific --}}
                                    @if ($el['kind'] === 'text')
                                        <div class="col-span-2 sm:col-span-4">
                                            <label class="label text-xs">Value</label>
                                            <input
                                                type="text"
                                                value="{{ $el['value'] ?? '' }}"
                                                wire:change="updateElement({{ $idx }}, {'value': $event.target.value})"
                                                class="input w-full text-sm"
                                                placeholder="Static text to display"
                                            />
                                        </div>
                                    @endif

                                    {{-- Field-specific --}}
                                    @if ($el['kind'] === 'field')
                                        <div class="col-span-2 sm:col-span-4">
                                            <label class="label text-xs">Field Key</label>
                                            <select
                                                wire:change="updateElement({{ $idx }}, {'field_key': $event.target.value})"
                                                class="input w-full text-sm"
                                            >
                                                <option value="">— select field —</option>
                                                @foreach ($availableFields as $fieldKey)
                                                    <option value="{{ $fieldKey }}" @selected(($el['field_key'] ?? '') === $fieldKey)>{{ $fieldKey }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif

                                    {{-- Barcode-specific --}}
                                    @if ($el['kind'] === 'barcode')
                                        <div class="col-span-2">
                                            <label class="label text-xs">Field Key</label>
                                            <select
                                                wire:change="updateElement({{ $idx }}, {'field_key': $event.target.value})"
                                                class="input w-full text-sm"
                                            >
                                                <option value="">— select field —</option>
                                                @foreach ($availableFields as $fieldKey)
                                                    <option value="{{ $fieldKey }}" @selected(($el['field_key'] ?? '') === $fieldKey)>{{ $fieldKey }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-span-2">
                                            <label class="label text-xs">Symbology</label>
                                            <select
                                                wire:change="updateElement({{ $idx }}, {'symbology': $event.target.value})"
                                                class="input w-full text-sm"
                                            >
                                                @foreach (['code128' => 'Code 128', 'qr' => 'QR Code', 'ean13' => 'EAN-13', 'datamatrix' => 'Data Matrix'] as $sym => $symLabel)
                                                    <option value="{{ $sym }}" @selected(($el['symbology'] ?? 'code128') === $sym)>{{ $symLabel }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-span-2 sm:col-span-4">
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    @checked($el['show_text'] ?? true)
                                                    wire:change="updateElement({{ $idx }}, {'show_text': $event.target.checked})"
                                                    class="rounded border-slate-300 text-simba-600 focus:ring-simba-600"
                                                />
                                                <span class="text-xs text-slate-700 dark:text-slate-300">Show human-readable text below barcode</span>
                                            </label>
                                        </div>
                                    @endif

                                    {{-- Text/Field font props --}}
                                    @if (in_array($el['kind'], ['text', 'field']))
                                        <div>
                                            <label class="label text-xs">Font Size (pt)</label>
                                            <input
                                                type="number"
                                                min="6"
                                                max="72"
                                                value="{{ $el['font_size_pt'] ?? 10 }}"
                                                wire:change="updateElement({{ $idx }}, {'font_size_pt': parseInt($event.target.value)})"
                                                class="input w-full text-sm"
                                            />
                                        </div>
                                        <div>
                                            <label class="label text-xs">Align</label>
                                            <select
                                                wire:change="updateElement({{ $idx }}, {'align': $event.target.value})"
                                                class="input w-full text-sm"
                                            >
                                                @foreach (['left' => 'Left', 'center' => 'Center', 'right' => 'Right'] as $align => $alignLabel)
                                                    <option value="{{ $align }}" @selected(($el['align'] ?? 'left') === $align)>{{ $alignLabel }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="flex items-end pb-1">
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    @checked($el['bold'] ?? false)
                                                    wire:change="updateElement({{ $idx }}, {'bold': $event.target.checked})"
                                                    class="rounded border-slate-300 text-simba-600 focus:ring-simba-600"
                                                />
                                                <span class="text-xs text-slate-700 dark:text-slate-300">Bold</span>
                                            </label>
                                        </div>
                                    @endif

                                    {{-- Line/Box thickness --}}
                                    @if (in_array($el['kind'], ['line', 'box']))
                                        <div>
                                            <label class="label text-xs">Thickness (mm)</label>
                                            <input
                                                type="number"
                                                step="0.1"
                                                min="0.1"
                                                value="{{ $el['thickness_mm'] ?? 0.5 }}"
                                                wire:change="updateElement({{ $idx }}, {'thickness_mm': parseFloat($event.target.value)})"
                                                class="input w-full text-sm"
                                            />
                                        </div>
                                    @endif

                                </div>

                            </div>
                        @empty
                            <div class="px-4 py-10 text-center">
                                <x-ui.icon name="template" class="mx-auto h-7 w-7 text-slate-300 dark:text-slate-600" />
                                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">No elements yet. Use "Add Element" to build your layout.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Save Button --}}
                <div class="flex justify-end gap-3 pt-2">
                    <a href="{{ route('admin.labels.templates.index') }}" wire:navigate class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <x-ui.icon name="save" class="w-4 h-4" /> Save Template
                    </button>
                </div>

            </div>

            {{-- ── Right Panel: Canvas Preview ─────────────────────────────── --}}
            <div class="xl:col-span-2">
                <div class="card sticky top-6">
                    <div class="card-header flex items-center justify-between">
                        <h3 class="card-title">
                            <x-ui.icon name="eye" class="w-4 h-4" />
                            Preview
                        </h3>
                        <button type="button" wire:click="previewZpl" class="btn btn-secondary btn-sm">
                            <x-ui.icon name="printer" class="w-3.5 h-3.5" /> Preview ZPL
                        </button>
                    </div>
                    <div class="card-body p-4">
                        {{-- Canvas --}}
                        @php
                            $canvasMaxWidth = 360;
                            $w = (float) ($widthMm ?: 100);
                            $h = (float) ($heightMm ?: 150);
                            $scale = min($canvasMaxWidth / $w, 4);
                            $canvasW = (int) round($w * $scale);
                            $canvasH = (int) round($h * $scale);
                        @endphp

                        <div class="flex justify-center">
                            <div
                                class="relative border-2 border-dashed border-slate-300 dark:border-slate-600 bg-white dark:bg-zinc-900 overflow-hidden"
                                style="width: {{ $canvasW }}px; height: {{ $canvasH }}px;"
                            >
                                @foreach ($elements as $idx => $el)
                                    @php
                                        $ex = (int) round(($el['x_mm'] ?? 0) * $scale);
                                        $ey = (int) round(($el['y_mm'] ?? 0) * $scale);
                                        $ew = (int) round(($el['w_mm'] ?? 30) * $scale);
                                        $eh = (int) round(($el['h_mm'] ?? 10) * $scale);
                                        $ew = min($ew, $canvasW - $ex);
                                        $eh = min($eh, $canvasH - $ey);
                                    @endphp
                                    <div
                                        class="absolute text-[9px] leading-tight overflow-hidden
                                            @if($el['kind'] === 'text' || $el['kind'] === 'field') bg-slate-100 dark:bg-slate-700/50 border border-slate-300 dark:border-slate-600 text-slate-800 dark:text-slate-200 px-0.5
                                            @elseif($el['kind'] === 'barcode') bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 flex items-center justify-center text-amber-600
                                            @elseif($el['kind'] === 'line') bg-slate-400 dark:bg-slate-500
                                            @elseif($el['kind'] === 'box') border-2 border-slate-500 dark:border-slate-400
                                            @endif"
                                        style="left: {{ $ex }}px; top: {{ $ey }}px; width: {{ $ew }}px; height: {{ max(4, $eh) }}px;"
                                        title="{{ $el['kind'] }}{{ isset($el['value']) ? ': '.$el['value'] : '' }}{{ isset($el['field_key']) ? ': '.$el['field_key'] : '' }}"
                                    >
                                        @if ($el['kind'] === 'text')
                                            <span @class(['font-bold' => $el['bold'] ?? false])>{{ $el['value'] ?? '...' }}</span>
                                        @elseif ($el['kind'] === 'field')
                                            <span class="italic opacity-75">{{ $el['field_key'] ?? 'field' }}</span>
                                        @elseif ($el['kind'] === 'barcode')
                                            <x-ui.icon name="qr-code" class="w-3 h-3" />
                                        @endif
                                    </div>
                                @endforeach

                                @if (empty($elements))
                                    <div class="absolute inset-0 flex items-center justify-center text-xs text-slate-400 dark:text-slate-600 pointer-events-none">
                                        {{ $widthMm }} × {{ $heightMm }} mm
                                    </div>
                                @endif
                            </div>
                        </div>

                        <p class="mt-3 text-xs text-center text-slate-400 dark:text-slate-600">
                            Scale ~{{ round($scale, 1) }}px/mm &middot; {{ count($elements) }} element(s)
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </form>

</div>
