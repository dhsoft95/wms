{{--
    Bulk Line Upload widget (anonymous component).
    Drop inside any Livewire Create view that uses WithBulkLineUpload trait.
    No props needed — all state is on the parent Livewire component.
--}}

@if($blkOpen)
    <x-ui.modal title="Bulk Line Upload" wireClose="blkReset" maxWidth="xl">

        {{-- ── Step indicator ──────────────────────────────────────────────── --}}
        <ol class="flex items-center gap-3 mb-6 text-xs font-medium">
            <li class="flex items-center gap-2 {{ $blkParsed ? 'text-emerald-600 dark:text-emerald-400' : 'text-simba-600' }}">
                <span class="flex h-6 w-6 items-center justify-center rounded-full ring-1
                    {{ $blkParsed
                        ? 'bg-emerald-50 ring-emerald-200 dark:bg-emerald-900/20 dark:ring-emerald-800'
                        : 'bg-simba-600 text-white ring-simba-600' }}">
                    @if($blkParsed)
                        <x-ui.icon name="check" class="w-3.5 h-3.5" />
                    @else
                        1
                    @endif
                </span>
                Upload
            </li>
            <li class="h-px flex-1 {{ $blkParsed ? 'bg-emerald-200 dark:bg-emerald-800' : 'bg-slate-200 dark:bg-slate-700' }}"></li>
            <li class="flex items-center gap-2 {{ $blkParsed ? 'text-simba-600' : 'text-slate-400 dark:text-slate-500' }}">
                <span class="flex h-6 w-6 items-center justify-center rounded-full ring-1
                    {{ $blkParsed
                        ? 'bg-simba-600 text-white ring-simba-600'
                        : 'bg-white ring-slate-200 dark:bg-zinc-900 dark:ring-slate-700' }}">
                    2
                </span>
                Review &amp; Apply
            </li>
        </ol>

        @if(! $blkParsed)
            {{-- ── Step 1: Upload ──────────────────────────────────────────── --}}
            <div class="space-y-6">

                {{-- Download template --}}
                <div class="flex items-center gap-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/40 p-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white dark:bg-zinc-900 ring-1 ring-slate-200 dark:ring-slate-700">
                        <x-ui.icon name="download" class="w-5 h-5 text-slate-500 dark:text-slate-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-200">Start with the template</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Pre-formatted XLSX with a column guide sheet.</p>
                    </div>
                    <button type="button"
                            wire:click="downloadBulkTemplate"
                            wire:loading.attr="disabled"
                            wire:target="downloadBulkTemplate"
                            class="btn btn-sm btn-secondary shrink-0">
                        <span wire:loading.remove wire:target="downloadBulkTemplate" class="inline-flex items-center gap-1.5">
                            <x-ui.icon name="download" class="w-4 h-4" /> Template
                        </span>
                        <span wire:loading wire:target="downloadBulkTemplate">Preparing…</span>
                    </button>
                </div>

                {{-- Drop zone --}}
                <div x-data="{ dragging: false, uploading: false, progress: 0 }"
                     x-on:livewire-upload-start="uploading = true; progress = 0"
                     x-on:livewire-upload-finish="uploading = false"
                     x-on:livewire-upload-error="uploading = false"
                     x-on:livewire-upload-progress="progress = $event.detail.progress">

                    <label class="label">
                        Upload File <span class="text-red-500">*</span>
                    </label>

                    <div class="relative rounded-xl border-2 border-dashed px-6 py-8 text-center transition-colors"
                         :class="dragging
                            ? 'border-simba-500 bg-simba-50/50 dark:bg-simba-900/10'
                            : '{{ $errors->has('blkFile')
                                    ? 'border-red-300 dark:border-red-800 bg-red-50/40 dark:bg-red-900/10'
                                    : 'border-slate-300 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-600 bg-white dark:bg-zinc-900' }}'"
                         x-on:dragover="dragging = true"
                         x-on:dragleave="dragging = false"
                         x-on:drop="dragging = false">

                        <input type="file"
                               wire:model="blkFile"
                               accept=".xlsx,.csv"
                               class="absolute inset-0 h-full w-full cursor-pointer opacity-0">

                        @if($blkFile && is_object($blkFile) && method_exists($blkFile, 'getClientOriginalName'))
                            {{-- File selected --}}
                            <div class="pointer-events-none flex flex-col items-center gap-2">
                                <div class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-50 dark:bg-emerald-900/20 ring-1 ring-emerald-200 dark:ring-emerald-800">
                                    <x-ui.icon name="check-circle" class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                                </div>
                                <p class="text-sm font-medium text-slate-800 dark:text-slate-200 break-all">
                                    {{ $blkFile->getClientOriginalName() }}
                                </p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Click or drop to replace</p>
                            </div>
                        @else
                            {{-- Empty --}}
                            <div class="pointer-events-none flex flex-col items-center gap-2">
                                <div class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 ring-1 ring-slate-200 dark:ring-slate-700">
                                    <x-ui.icon name="upload" class="w-5 h-5 text-slate-500 dark:text-slate-400" />
                                </div>
                                <p class="text-sm text-slate-700 dark:text-slate-300">
                                    <span class="font-semibold text-simba-600">Click to choose</span> or drag a file here
                                </p>
                                <p class="text-xs text-slate-400">.xlsx or .csv, up to 5 MB</p>
                            </div>
                        @endif

                        {{-- Upload progress --}}
                        <div x-show="uploading" x-cloak class="mt-4">
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-full rounded-full bg-simba-600 transition-all"
                                     :style="`width: ${progress}%`"></div>
                            </div>
                            <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400 tabular-nums">
                                Uploading… <span x-text="progress"></span>%
                            </p>
                        </div>
                    </div>

                    @error('blkFile')
                    <p class="hint !text-red-600 mt-1.5 flex items-center gap-1">
                        <x-ui.icon name="x-circle" class="w-3.5 h-3.5" /> {{ $message }}
                    </p>
                    @enderror
                </div>

                {{-- Mode selector --}}
                <fieldset>
                    <legend class="label">Import Mode</legend>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="relative cursor-pointer">
                            <input type="radio" wire:model="blkMode" value="append" class="peer sr-only">
                            <div class="h-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-zinc-900 p-4 transition
                                        hover:border-slate-300 dark:hover:border-slate-600
                                        peer-checked:border-simba-500 peer-checked:ring-1 peer-checked:ring-simba-500
                                        peer-focus-visible:ring-2 peer-focus-visible:ring-simba-500">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Append</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Add rows after the existing lines.</p>
                            </div>
                            <span class="pointer-events-none absolute right-3 top-3 hidden h-5 w-5 items-center justify-center rounded-full bg-simba-600 text-white peer-checked:flex">
                                <x-ui.icon name="check" class="w-3 h-3" />
                            </span>
                        </label>

                        <label class="relative cursor-pointer">
                            <input type="radio" wire:model="blkMode" value="replace" class="peer sr-only">
                            <div class="h-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-zinc-900 p-4 transition
                                        hover:border-slate-300 dark:hover:border-slate-600
                                        peer-checked:border-simba-500 peer-checked:ring-1 peer-checked:ring-simba-500
                                        peer-focus-visible:ring-2 peer-focus-visible:ring-simba-500">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Replace</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Clear existing lines, then import.</p>
                            </div>
                            <span class="pointer-events-none absolute right-3 top-3 hidden h-5 w-5 items-center justify-center rounded-full bg-simba-600 text-white peer-checked:flex">
                                <x-ui.icon name="check" class="w-3 h-3" />
                            </span>
                        </label>
                    </div>
                </fieldset>
            </div>

            <x-slot:footer>
                <button type="button" wire:click="blkReset" class="btn btn-secondary">Cancel</button>
                <button type="button"
                        wire:click="blkUpload"
                        wire:loading.attr="disabled"
                        wire:target="blkUpload,blkFile"
                        class="btn btn-primary">
                    <span wire:loading.remove wire:target="blkUpload" class="inline-flex items-center gap-1.5">
                        <x-ui.icon name="upload" class="w-4 h-4" /> Upload &amp; Validate
                    </span>
                    <span wire:loading wire:target="blkUpload">Validating…</span>
                </button>
            </x-slot:footer>

        @else
            {{-- ── Step 2: Preview / Confirm ───────────────────────────────── --}}
            @php
                $validCount = count($blkValid);
                $errorCount = count($blkErrors);
            @endphp

            <div class="space-y-6">

                {{-- Summary stats --}}
                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20 p-3">
                        <div class="flex items-center gap-1.5 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                            <x-ui.icon name="check-circle" class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                            Valid
                        </div>
                        <p class="mt-1 text-2xl font-semibold tabular-nums text-emerald-700 dark:text-emerald-300">{{ $validCount }}</p>
                    </div>

                    <div class="rounded-xl border p-3
                        {{ $errorCount > 0
                            ? 'border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20'
                            : 'border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/40' }}">
                        <div class="flex items-center gap-1.5 text-xs font-medium
                            {{ $errorCount > 0 ? 'text-red-700 dark:text-red-300' : 'text-slate-500 dark:text-slate-400' }}">
                            <x-ui.icon name="x-circle" class="w-4 h-4 {{ $errorCount > 0 ? 'text-red-600 dark:text-red-400' : '' }}" />
                            Errors
                        </div>
                        <p class="mt-1 text-2xl font-semibold tabular-nums
                            {{ $errorCount > 0 ? 'text-red-700 dark:text-red-300' : 'text-slate-400 dark:text-slate-500' }}">{{ $errorCount }}</p>
                    </div>

                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/40 p-3">
                        <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Processed</div>
                        <p class="mt-1 text-2xl font-semibold tabular-nums text-slate-700 dark:text-slate-300">{{ $blkTotal }}</p>
                    </div>
                </div>

                {{-- Mode reminder --}}
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Mode:
                    <span class="font-semibold text-slate-700 dark:text-slate-300">{{ ucfirst($blkMode) }}</span>
                    @if($blkMode === 'replace')
                        — existing lines will be cleared before applying.
                    @else
                        — rows will be added after existing lines.
                    @endif
                </p>

                {{-- Error table --}}
                @if($errorCount > 0)
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase tracking-wide text-red-600 dark:text-red-400">
                                Validation Errors
                            </p>
                            @if($validCount > 0)
                                <p class="text-xs text-slate-500 dark:text-slate-400">Rows with errors will be skipped.</p>
                            @endif
                        </div>
                        <div class="max-h-64 overflow-y-auto rounded-xl border border-red-200 dark:border-red-800">
                            <table class="w-full text-sm">
                                <thead class="sticky top-0 z-10 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-xs font-semibold uppercase tracking-wide backdrop-blur">
                                <tr>
                                    <th class="text-left px-3 py-2 w-16">Row</th>
                                    <th class="text-left px-3 py-2 w-32">Column</th>
                                    <th class="text-left px-3 py-2">Message</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-red-100 dark:divide-red-900/30">
                                @foreach($blkErrors as $err)
                                    <tr class="bg-white dark:bg-zinc-900 hover:bg-red-50/40 dark:hover:bg-red-900/10">
                                        <td class="px-3 py-2 align-top">
                                                <span class="inline-flex min-w-[2rem] justify-center rounded-md bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 text-xs tabular-nums text-slate-600 dark:text-slate-400">
                                                    {{ $err['row'] }}
                                                </span>
                                        </td>
                                        <td class="px-3 py-2 align-top font-mono text-xs text-slate-600 dark:text-slate-400">{{ $err['column'] }}</td>
                                        <td class="px-3 py-2 align-top text-red-700 dark:text-red-300">{{ $err['message'] }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Status callout --}}
                @if($validCount === 0)
                    <div class="flex items-start gap-3 rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-4 py-3 text-sm text-amber-700 dark:text-amber-300">
                        <x-ui.icon name="x-circle" class="w-5 h-5 shrink-0 mt-0.5" />
                        <div>
                            <p class="font-semibold">No valid rows to import</p>
                            <p class="mt-0.5 text-amber-700/80 dark:text-amber-300/80">Fix the errors above and re-upload the file.</p>
                        </div>
                    </div>
                @elseif($errorCount === 0)
                    <div class="flex items-start gap-3 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-300">
                        <x-ui.icon name="check-circle" class="w-5 h-5 shrink-0 mt-0.5" />
                        <p>All rows passed validation and are ready to apply.</p>
                    </div>
                @endif
            </div>

            <x-slot:footer>
                <button type="button" wire:click="blkReset" class="btn btn-secondary">
                    <span class="inline-flex items-center gap-1.5">
                        <x-ui.icon name="arrow-left" class="w-4 h-4" /> Re-upload
                    </span>
                </button>
                <button type="button"
                        wire:click="blkConfirm"
                        wire:loading.attr="disabled"
                        wire:target="blkConfirm"
                        @disabled($validCount === 0)
                        class="btn btn-primary disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="blkConfirm" class="inline-flex items-center gap-1.5">
                        <x-ui.icon name="check" class="w-4 h-4" />
                        @if($validCount > 0)
                            Apply {{ $validCount }} row{{ $validCount !== 1 ? 's' : '' }}
                        @else
                            Confirm &amp; Apply
                        @endif
                    </span>
                    <span wire:loading wire:target="blkConfirm">Applying…</span>
                </button>
            </x-slot:footer>
        @endif

    </x-ui.modal>
@endif
