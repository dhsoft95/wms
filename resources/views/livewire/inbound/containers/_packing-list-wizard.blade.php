{{-- ── Packing List Import Wizard ──────────────────────────────────────────── --}}
@if($plOpen)
<div class="fixed inset-0 z-50 flex items-start sm:items-center justify-center p-3 sm:p-6
            bg-gradient-to-b from-slate-950/70 via-slate-900/60 to-brand-950/50
            backdrop-blur-xl"
     wire:click.self="closePlWizard">

    {{-- Modal shell — uses project's Tier-2 glass card --}}
    <div class="card w-full max-w-3xl p-0 overflow-hidden flex flex-col
                shadow-[0_32px_80px_-12px_rgba(0,0,0,0.45),inset_0_1px_0_0_rgba(255,255,255,0.55)]
                dark:shadow-[0_32px_80px_-12px_rgba(0,0,0,0.70),inset_0_1px_0_0_rgba(255,255,255,0.08)]"
         style="max-height: 92vh;">

        {{-- ── Header ─────────────────────────────────────────────────────── --}}
        <div class="flex items-center justify-between px-6 py-4
                    border-b border-white/50 dark:border-white/[0.08] shrink-0
                    bg-gradient-to-r from-white/30 to-white/10
                    dark:from-white/[0.04] dark:to-transparent">
            <div>
                <div class="flex items-center gap-2.5 mb-0.5">
                    <div class="w-7 h-7 rounded-lg glass-brand flex items-center justify-center shrink-0">
                        <x-ui.icon name="upload" class="w-3.5 h-3.5 text-brand-600 dark:text-brand-400" />
                    </div>
                    <h3 class="font-bold text-slate-900 dark:text-white tracking-tight">Import Packing List</h3>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 ml-9.5">
                    Step {{ $plStep }} of 4 &mdash;
                    @if($plStep === 1) Upload file
                    @elseif($plStep === 2) Confirm header row
                    @elseif($plStep === 3) Map columns
                    @else Review &amp; confirm
                    @endif
                </p>
            </div>
            <button wire:click="closePlWizard"
                    class="icon-btn !text-slate-400 hover:!text-slate-700 dark:hover:!text-slate-200">
                <x-ui.icon name="x" class="w-5 h-5" />
            </button>
        </div>

        {{-- ── Step indicator ──────────────────────────────────────────────── --}}
        <div class="px-6 py-3 border-b border-white/40 dark:border-white/[0.06] shrink-0
                    bg-white/20 dark:bg-white/[0.02]">
            <div class="flex items-center gap-0">
                @foreach([1 => 'Upload', 2 => 'Header', 3 => 'Columns', 4 => 'Review'] as $n => $label)
                    <div class="flex-1 flex items-center gap-2">
                        {{-- Step circle --}}
                        @if($plStep > $n)
                            <div class="flex items-center justify-center w-6 h-6 rounded-full
                                        bg-brand-500/20 dark:bg-brand-400/20
                                        text-brand-600 dark:text-brand-400 shrink-0 transition-all">
                                <x-ui.icon name="check" class="w-3.5 h-3.5" />
                            </div>
                        @elseif($plStep === $n)
                            <div class="flex items-center justify-center w-6 h-6 rounded-full
                                        glass-brand text-white shrink-0 shadow-sm
                                        shadow-brand-500/30 text-xs font-bold transition-all">
                                {{ $n }}
                            </div>
                        @else
                            <div class="flex items-center justify-center w-6 h-6 rounded-full
                                        bg-white/30 dark:bg-white/[0.06] border border-white/50 dark:border-white/10
                                        text-slate-400 dark:text-slate-500 shrink-0 text-xs font-medium transition-all">
                                {{ $n }}
                            </div>
                        @endif

                        {{-- Label --}}
                        <span @class([
                            'text-xs font-medium hidden sm:inline transition-colors',
                            'text-slate-900 dark:text-white font-semibold' => $plStep === $n,
                            'text-brand-600 dark:text-brand-400'           => $plStep > $n,
                            'text-slate-400 dark:text-slate-500'           => $plStep < $n,
                        ])>{{ $label }}</span>
                    </div>

                    @if(!$loop->last)
                        <div @class([
                            'w-8 h-px mx-1 shrink-0 transition-colors',
                            'bg-brand-300 dark:bg-brand-700' => $plStep > $n,
                            'bg-slate-200 dark:bg-white/10'  => $plStep <= $n,
                        ])></div>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- ── Scrollable body ─────────────────────────────────────────────── --}}
        <div class="overflow-y-auto flex-1 px-6 py-6 space-y-5
                    bg-gradient-to-b from-white/10 to-transparent
                    dark:from-white/[0.02] dark:to-transparent">

            {{-- Step 1: Upload ──────────────────────────────────────────────── --}}
            @if($plStep === 1)
                <div class="space-y-5">
                    <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                        Upload a supplier packing list. Title rows above the header are detected automatically.
                    </p>

                    {{-- Drop zone --}}
                    <label @class([
                        'group relative block rounded-2xl border-2 border-dashed p-8 text-center cursor-pointer transition-all duration-200',
                        'border-brand-400 dark:border-brand-500 bg-brand-50/50 dark:bg-brand-900/10'
                            => $plFile,
                        'border-white/50 dark:border-white/[0.12] hover:border-brand-400 dark:hover:border-brand-500
                         hover:bg-brand-50/30 dark:hover:bg-brand-900/10'
                            => !$plFile,
                    ])>
                        <div class="space-y-3">
                            <div @class([
                                'mx-auto w-12 h-12 rounded-2xl flex items-center justify-center transition-colors',
                                'glass-brand shadow-sm shadow-brand-500/20' => $plFile,
                                'bg-white/40 dark:bg-white/[0.06] border border-white/60 dark:border-white/10
                                 group-hover:glass-brand group-hover:shadow-sm group-hover:shadow-brand-500/20' => !$plFile,
                            ])>
                                <x-ui.icon name="{{ $plFile ? 'check' : 'upload' }}"
                                           class="w-5 h-5 {{ $plFile ? 'text-white' : 'text-slate-400 group-hover:text-brand-500' }}" />
                            </div>

                            @if($plFile)
                                <div>
                                    <p class="text-sm font-semibold text-brand-700 dark:text-brand-300">
                                        {{ is_object($plFile) ? $plFile->getClientOriginalName() : 'File selected' }}
                                    </p>
                                    <p class="text-xs text-slate-400 mt-0.5">Click to change file</p>
                                </div>
                            @else
                                <div>
                                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">
                                        Choose a file <span class="font-normal text-slate-400">or drag and drop</span>
                                    </p>
                                    <p class="text-xs text-slate-400 mt-1">.xlsx · .xls · .csv &nbsp;·&nbsp; max 20 MB</p>
                                </div>
                            @endif
                        </div>
                        <input wire:model="plFile" type="file" accept=".xlsx,.xls,.csv" class="sr-only">
                        @error('plFile')
                            <p class="absolute inset-x-0 bottom-2 text-xs text-red-600 text-center">{{ $message }}</p>
                        @enderror
                    </label>

                    {{-- Sample download --}}
                    <div class="flex items-center justify-between px-1">
                        <p class="text-xs text-slate-400">Not sure about the format?</p>
                        <a href="{{ route('containers.packing-list.sample') }}" target="_blank"
                           class="inline-flex items-center gap-1.5 text-xs font-semibold
                                  text-brand-600 dark:text-brand-400 hover:underline transition-colors">
                            <x-ui.icon name="download" class="w-3.5 h-3.5" />
                            Download sample file
                        </a>
                    </div>

                    {{-- Loading --}}
                    <div wire:loading wire:target="plUploadAndDetect"
                         class="flex items-center gap-2 text-xs text-brand-600 dark:text-brand-400 font-medium">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                        </svg>
                        Analysing file structure…
                    </div>
                </div>
            @endif

            {{-- Step 2: Confirm header row ─────────────────────────────────── --}}
            @if($plStep === 2)
                <div class="space-y-4">
                    <div class="flex items-start gap-3 p-3.5 rounded-xl
                                bg-brand-50/60 dark:bg-brand-900/15
                                border border-brand-200/60 dark:border-brand-700/30">
                        <x-ui.icon name="alert" class="w-4 h-4 text-brand-600 dark:text-brand-400 shrink-0 mt-0.5" />
                        <p class="text-xs text-brand-700 dark:text-brand-300 leading-relaxed">
                            The <strong>highlighted row</strong> was auto-detected as the column header.
                            Click a different row to change it.
                        </p>
                    </div>

                    <div class="overflow-hidden rounded-xl
                                border border-white/50 dark:border-white/[0.08]
                                shadow-sm shadow-black/5 dark:shadow-black/20">
                        <table class="w-full text-xs">
                            <tbody>
                            @foreach($plSampleRows as $rowIdx => $row)
                                <tr wire:click="$set('plHeaderRowIdx', {{ $rowIdx }})"
                                    @class([
                                        'cursor-pointer transition-all duration-150 select-none',
                                        'bg-brand-50/80 dark:bg-brand-900/25
                                         border-l-[3px] border-brand-500
                                         [&>td]:font-semibold [&>td]:text-slate-800 dark:[&>td]:text-white'
                                            => $plHeaderRowIdx === $rowIdx,
                                        'hover:bg-white/60 dark:hover:bg-white/[0.04]
                                         border-l-[3px] border-transparent'
                                            => $plHeaderRowIdx !== $rowIdx,
                                    ])>
                                    <td class="px-3 py-2.5 w-10 text-slate-400 font-mono text-center shrink-0">
                                        @if($plHeaderRowIdx === $rowIdx)
                                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full
                                                         glass-brand text-[10px] text-white font-bold">
                                                {{ $rowIdx + 1 }}
                                            </span>
                                        @else
                                            {{ $rowIdx + 1 }}
                                        @endif
                                    </td>
                                    @foreach(array_slice($row, 0, 8) as $cell)
                                        <td class="px-3 py-2.5 text-slate-600 dark:text-slate-400
                                                   max-w-[140px] truncate whitespace-nowrap">
                                            {{ mb_strimwidth((string) $cell, 0, 28, '…') ?: '—' }}
                                        </td>
                                    @endforeach
                                </tr>
                                @if(!$loop->last)
                                    <tr class="border-t border-white/40 dark:border-white/[0.05]">
                                        <td colspan="9" class="p-0"></td>
                                    </tr>
                                @endif
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-slate-400 text-right">Showing first {{ count($plSampleRows) }} rows</p>
                </div>
            @endif

            {{-- Step 3: Column mapping ──────────────────────────────────────── --}}
            @if($plStep === 3)
                <div class="space-y-5">
                    {{-- Profile selector --}}
                    @if($this->plProfiles->isNotEmpty())
                        <div class="flex items-end gap-3 p-4 rounded-xl
                                    glass-solid dark:glass-solid-dark">
                            <div class="flex-1">
                                <label class="label text-xs mb-1">Saved Profile</label>
                                <select wire:model="plProfileId" class="input w-full text-xs">
                                    <option value="">— None —</option>
                                    @foreach($this->plProfiles as $profile)
                                        <option value="{{ $profile->id }}">{{ $profile->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button wire:click="plApplyProfile"
                                    class="btn btn-secondary text-xs px-4 shrink-0">Apply</button>
                        </div>
                    @endif

                    {{-- Mapping table --}}
                    <div class="overflow-hidden rounded-xl
                                border border-white/50 dark:border-white/[0.08]
                                shadow-sm shadow-black/5 dark:shadow-black/20">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-white/40 dark:border-white/[0.08]
                                           bg-white/30 dark:bg-white/[0.03]">
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold
                                               text-slate-500 uppercase tracking-wider w-1/2">
                                        File Column
                                    </th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold
                                               text-slate-500 uppercase tracking-wider w-1/2">
                                        Maps To
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($plFileColumns as $colIdx => $col)
                                <tr @class([
                                    'transition-colors',
                                    'border-t border-white/30 dark:border-white/[0.05]' => $colIdx > 0,
                                    'bg-brand-50/30 dark:bg-brand-900/10' => ($plMapping[$col] ?? '') !== '',
                                    'hover:bg-white/40 dark:hover:bg-white/[0.03]' => ($plMapping[$col] ?? '') === '',
                                ])>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            @if(($plMapping[$col] ?? '') !== '')
                                                <span class="w-1.5 h-1.5 rounded-full bg-brand-500 shrink-0"></span>
                                            @else
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-300 dark:bg-white/20 shrink-0"></span>
                                            @endif
                                            <span class="font-mono text-xs text-slate-700 dark:text-slate-300">{{ $col }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <select wire:model.live="plMapping.{{ $col }}"
                                                class="input w-full text-xs py-1.5">
                                            @foreach(\App\Services\Inbound\PackingListParser::fieldOptions() as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Save profile toggle --}}
                    <label class="flex items-start gap-3 p-4 rounded-xl cursor-pointer transition-all duration-200
                                  border border-white/50 dark:border-white/[0.08]
                                  {{ $plSaveProfile ? 'glass-brand' : 'bg-white/20 dark:bg-white/[0.03] hover:bg-white/30 dark:hover:bg-white/[0.05]' }}">
                        <input wire:model.live="plSaveProfile" type="checkbox" id="plSaveProfile"
                               class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 shrink-0">
                        <div class="flex-1 space-y-3 min-w-0">
                            <div>
                                <p class="text-sm font-semibold
                                          {{ $plSaveProfile ? 'text-brand-800 dark:text-brand-200' : 'text-slate-700 dark:text-slate-300' }}">
                                    Save mapping profile
                                </p>
                                <p class="text-xs mt-0.5
                                          {{ $plSaveProfile ? 'text-brand-600/80 dark:text-brand-400/80' : 'text-slate-400' }}">
                                    Auto-apply next time you import from the same shipper
                                </p>
                            </div>
                            @if($plSaveProfile)
                                <div>
                                    <label class="label text-xs mb-1 !text-brand-700 dark:!text-brand-300">
                                        Profile name
                                    </label>
                                    <input wire:model="plProfileName" type="text"
                                           class="input w-full text-xs"
                                           placeholder="e.g. Acme Supplier">
                                </div>
                            @endif
                        </div>
                    </label>
                </div>
            @endif

            {{-- Step 4: Review & confirm ────────────────────────────────────── --}}
            @if($plStep === 4)
                @php
                    $unknownCount = count(array_filter($plRows, fn($r) => $r['unknown_sku'] && !$r['item_id']));
                    $errorCount   = count(array_filter($plRows, fn($r) => !empty($r['errors'])));
                @endphp

                {{-- Summary stat strip --}}
                <div class="grid grid-cols-3 gap-3">
                    <div class="glass-solid dark:glass-solid-dark rounded-xl p-3 text-center">
                        <p class="text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ count($plRows) }}</p>
                        <p class="text-[11px] text-slate-500 mt-0.5">Rows</p>
                    </div>
                    <div @class([
                        'rounded-xl p-3 text-center',
                        'glass-brand' => $unknownCount > 0,
                        'glass-solid dark:glass-solid-dark' => $unknownCount === 0,
                    ])>
                        <p @class([
                            'text-2xl font-bold tabular-nums',
                            'text-brand-700 dark:text-brand-300' => $unknownCount > 0,
                            'text-slate-900 dark:text-white' => $unknownCount === 0,
                        ])>{{ $unknownCount }}</p>
                        <p class="text-[11px] text-slate-500 mt-0.5">Unknown SKUs</p>
                    </div>
                    <div @class([
                        'rounded-xl p-3 text-center',
                        'bg-red-50/70 dark:bg-red-900/15 border border-red-200/50 dark:border-red-700/30' => $errorCount > 0,
                        'bg-green-50/70 dark:bg-green-900/15 border border-green-200/50 dark:border-green-700/30' => $errorCount === 0,
                    ])>
                        <p @class([
                            'text-2xl font-bold tabular-nums',
                            'text-red-600 dark:text-red-400'   => $errorCount > 0,
                            'text-green-600 dark:text-green-400' => $errorCount === 0,
                        ])>{{ $errorCount > 0 ? $errorCount : '✓' }}</p>
                        <p class="text-[11px] text-slate-500 mt-0.5">{{ $errorCount > 0 ? 'Warnings' : 'All valid' }}</p>
                    </div>
                </div>

                {{-- Rows table --}}
                <div class="overflow-hidden rounded-xl
                            border border-white/50 dark:border-white/[0.08]
                            shadow-sm shadow-black/5 dark:shadow-black/20">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="bg-white/30 dark:bg-white/[0.03]
                                           border-b border-white/40 dark:border-white/[0.08]">
                                    <th class="px-3 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider w-8">#</th>
                                    <th class="px-3 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">SKU</th>
                                    <th class="px-3 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Description</th>
                                    <th class="px-3 py-3 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Qty</th>
                                    <th class="px-3 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">UoM</th>
                                    <th class="px-3 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Batch</th>
                                    <th class="px-3 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">VW</th>
                                    <th class="px-3 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($plRows as $rowIdx => $row)
                                {{-- Data row --}}
                                <tr wire:key="pl-row-{{ $rowIdx }}"
                                    @class([
                                        'transition-colors',
                                        'border-t border-white/30 dark:border-white/[0.05]' => $rowIdx > 0,
                                        'bg-amber-50/60 dark:bg-amber-900/15'
                                            => $row['unknown_sku'] && !$row['item_id'],
                                        'bg-red-50/40 dark:bg-red-900/10'
                                            => !empty($row['errors']) && !($row['unknown_sku'] && !$row['item_id']),
                                        'hover:bg-white/40 dark:hover:bg-white/[0.03]'
                                            => empty($row['errors']) && !($row['unknown_sku'] && !$row['item_id']),
                                    ])>
                                    <td class="px-3 py-2.5 text-slate-400 font-mono text-center">{{ $rowIdx + 1 }}</td>
                                    <td class="px-3 py-2.5 font-mono font-semibold text-slate-800 dark:text-slate-200">
                                        {{ $row['item_sku'] ?? $row['raw_sku'] ?: '—' }}
                                    </td>
                                    <td class="px-3 py-2.5 text-slate-600 dark:text-slate-400 max-w-[160px] truncate">
                                        {{ $row['item_name'] ?? $row['raw_description'] ?: '—' }}
                                    </td>
                                    <td class="px-3 py-2.5 text-right tabular-nums font-semibold text-slate-800 dark:text-slate-200">
                                        {{ $row['qty_base'] > 0 ? number_format($row['qty_base'], 2) : ($row['raw_qty'] ?: '—') }}
                                        @if($row['qty_base'] > 0 && $row['raw_uom'] && $row['base_uom_code'] && $row['base_uom_code'] !== $row['raw_uom'])
                                            <span class="font-normal text-slate-400 text-[10px] ml-0.5">({{ $row['raw_qty'] }}×)</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-slate-500">{{ $row['base_uom_code'] ?? $row['uom_code'] ?? '—' }}</td>
                                    <td class="px-3 py-2.5 font-mono text-slate-500">{{ $row['raw_batch'] ?: '—' }}</td>
                                    <td class="px-3 py-2.5 min-w-[140px]">
                                        @if($row['vw_id'])
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md
                                                         bg-slate-100 dark:bg-white/[0.07] text-slate-600 dark:text-slate-400
                                                         text-[10px] font-medium">
                                                {{ $row['vw_name'] }}
                                            </span>
                                        @else
                                            <select wire:model.live="plRows.{{ $rowIdx }}.vw_id"
                                                    class="input text-xs py-0.5 px-2 w-36 !border-red-300 dark:!border-red-700">
                                                <option value="">— Assign VW —</option>
                                                @foreach($this->virtualWarehouses as $vw)
                                                    <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5">
                                        @if($row['unknown_sku'] && !$row['item_id'])
                                            <span class="badge badge-amber text-[10px]">Unknown SKU</span>
                                        @elseif(!empty($row['errors']))
                                            <span class="badge badge-red text-[10px]"
                                                  title="{{ implode(', ', $row['errors']) }}">
                                                ⚠ {{ implode(' · ', $row['errors']) }}
                                            </span>
                                        @else
                                            <span class="badge badge-green text-[10px]">OK</span>
                                        @endif
                                    </td>
                                </tr>
                                {{-- Unknown SKU resolution sub-row --}}
                                @if($row['unknown_sku'] && !$row['item_id'])
                                    <tr wire:key="pl-resolve-{{ $rowIdx }}"
                                        class="bg-amber-50/40 dark:bg-amber-900/10
                                               border-b border-amber-200/40 dark:border-amber-700/20">
                                        <td colspan="8" class="px-3 py-2.5">
                                            <div class="flex items-center gap-2 flex-wrap pl-3">
                                                <span class="text-[11px] text-amber-700 dark:text-amber-400 font-medium shrink-0">
                                                    Map "{{ $row['raw_sku'] }}" →
                                                </span>
                                                <select wire:model.live="plItemOverrides.{{ $rowIdx }}"
                                                        class="input text-xs py-1 flex-1 min-w-0 max-w-xs">
                                                    <option value="">— Select existing item —</option>
                                                    @foreach($this->items as $existingItem)
                                                        <option value="{{ $existingItem->id }}">
                                                            {{ $existingItem->sku }} – {{ $existingItem->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <span class="text-xs text-slate-400 shrink-0">or</span>
                                                <button wire:click="plOpenQuickCreate({{ $rowIdx }})"
                                                        class="btn btn-secondary text-xs px-3 py-1 h-auto shrink-0">
                                                    <x-ui.icon name="plus" class="w-3 h-3" /> Create
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Import mode --}}
                <div class="space-y-2">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        Import mode
                    </p>
                    @foreach(['replace' => ['Replace existing lines', 'All ' . $container->expectedLines->count() . ' existing lines will be deleted first.'],
                               'append'  => ['Append to existing', 'New lines will be added after existing lines.']] as $val => $info)
                        <label @class([
                            'flex items-start gap-3 p-4 rounded-xl cursor-pointer transition-all duration-200 border',
                            'glass-brand border-brand-400/40 dark:border-brand-600/30 shadow-sm shadow-brand-500/10'
                                => $plMode === $val,
                            'border-white/50 dark:border-white/[0.08] bg-white/20 dark:bg-white/[0.03]
                             hover:bg-white/30 dark:hover:bg-white/[0.05]'
                                => $plMode !== $val,
                        ])>
                            <input wire:model.live="plMode" type="radio" value="{{ $val }}"
                                   class="mt-0.5 h-4 w-4 text-brand-600 focus:ring-brand-500 shrink-0">
                            <div>
                                <p @class([
                                    'text-sm font-semibold',
                                    'text-brand-800 dark:text-brand-200' => $plMode === $val,
                                    'text-slate-800 dark:text-slate-200' => $plMode !== $val,
                                ])>{{ $info[0] }}</p>
                                <p @class([
                                    'text-xs mt-0.5',
                                    'text-brand-600/80 dark:text-brand-400/80' => $plMode === $val,
                                    'text-slate-400' => $plMode !== $val,
                                ])>{{ $info[1] }}</p>
                            </div>
                        </label>
                    @endforeach
                </div>
            @endif

        </div>{{-- /scrollable body --}}

        {{-- ── Footer ─────────────────────────────────────────────────────── --}}
        <div class="flex items-center justify-between px-6 py-4
                    border-t border-white/40 dark:border-white/[0.08] shrink-0
                    bg-white/20 dark:bg-white/[0.02]">
            <div>
                @if($plStep === 2)
                    <button wire:click="$set('plStep', 1)" class="btn btn-ghost text-xs">
                        <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Back
                    </button>
                @elseif($plStep === 3)
                    <button wire:click="$set('plStep', 2)" class="btn btn-ghost text-xs"
                            title="Change the auto-detected header row">
                        <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Change header row
                    </button>
                @elseif($plStep === 4)
                    <button wire:click="$set('plStep', 3)" class="btn btn-ghost text-xs">
                        <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Back
                    </button>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <button wire:click="closePlWizard" class="btn btn-ghost text-xs">Cancel</button>

                @if($plStep === 1)
                    <button wire:click="plUploadAndDetect"
                            wire:loading.attr="disabled"
                            wire:target="plUploadAndDetect,plFile"
                            class="btn btn-primary text-xs">
                        <span wire:loading.remove wire:target="plUploadAndDetect">
                            <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" /> Upload &amp; Detect
                        </span>
                        <span wire:loading wire:target="plUploadAndDetect">Uploading…</span>
                    </button>

                @elseif($plStep === 2)
                    <button wire:click="plConfirmHeader" class="btn btn-primary text-xs">
                        Confirm Header <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                    </button>

                @elseif($plStep === 3)
                    <button wire:click="plProcessMapping"
                            wire:loading.attr="disabled"
                            wire:target="plProcessMapping"
                            class="btn btn-primary text-xs">
                        <span wire:loading.remove wire:target="plProcessMapping">
                            Review Import <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" />
                        </span>
                        <span wire:loading wire:target="plProcessMapping">Parsing…</span>
                    </button>

                @elseif($plStep === 4)
                    @php $canImport = $unknownCount === 0 && $errorCount === 0; @endphp
                    <button wire:click="plConfirmImport"
                            wire:loading.attr="disabled"
                            wire:target="plConfirmImport"
                            @if($plMode === 'replace' && $container->expectedLines->count() > 0)
                                wire:confirm="This will replace all {{ $container->expectedLines->count() }} existing lines. Continue?"
                            @endif
                            @disabled(!$canImport)
                            title="{{ !$canImport ? 'Resolve all warnings before importing' : '' }}"
                            class="btn btn-primary text-xs disabled:opacity-40 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="plConfirmImport">
                            <x-ui.icon name="check" class="w-3.5 h-3.5" />
                            Import {{ count($plRows) }} line(s)
                        </span>
                        <span wire:loading wire:target="plConfirmImport">Saving…</span>
                    </button>
                @endif
            </div>
        </div>

    </div>{{-- /modal --}}
</div>{{-- /overlay --}}
@endif

{{-- ── Quick Create Item sub-modal ────────────────────────────────────────── --}}
@if($plQcOpen)
<div class="fixed inset-0 z-[60] flex items-center justify-center p-4
            bg-slate-950/60 backdrop-blur-xl"
     wire:click.self="$set('plQcOpen', false)">

    <div class="card w-full max-w-sm p-0 overflow-hidden
                shadow-[0_24px_60px_-8px_rgba(0,0,0,0.50)]">

        {{-- QC header --}}
        <div class="flex items-center justify-between px-5 py-4
                    border-b border-white/50 dark:border-white/[0.08]
                    bg-gradient-to-r from-white/20 to-transparent dark:from-white/[0.03] dark:to-transparent">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-lg glass-brand flex items-center justify-center">
                    <x-ui.icon name="plus" class="w-3.5 h-3.5 text-brand-600 dark:text-brand-400" />
                </div>
                <h3 class="font-bold text-slate-900 dark:text-white text-sm">Create Item</h3>
            </div>
            <button wire:click="$set('plQcOpen', false)" class="icon-btn !text-slate-400">
                <x-ui.icon name="x" class="w-5 h-5" />
            </button>
        </div>

        {{-- QC body --}}
        <div class="px-5 py-5 space-y-3.5">
            <div>
                <label class="label text-xs mb-1">SKU <span class="text-red-500">*</span></label>
                <input wire:model="plQcSku" type="text" class="input w-full font-mono text-xs"
                       placeholder="SKU-001">
                @error('plQcSku') <p class="hint !text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label text-xs mb-1">Name <span class="text-red-500">*</span></label>
                <input wire:model="plQcName" type="text" class="input w-full text-xs"
                       placeholder="Product name">
                @error('plQcName') <p class="hint !text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label text-xs mb-1">Category <span class="text-red-500">*</span></label>
                    <select wire:model="plQcCategoryId" class="input w-full text-xs">
                        <option value="">— Select —</option>
                        @foreach($this->plCategories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    @error('plQcCategoryId') <p class="hint !text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label text-xs mb-1">Base UoM <span class="text-red-500">*</span></label>
                    <select wire:model="plQcBaseUomId" class="input w-full text-xs">
                        <option value="">— Select —</option>
                        @foreach($this->plBaseUoms as $uom)
                            <option value="{{ $uom->id }}">{{ $uom->code }}</option>
                        @endforeach
                    </select>
                    @error('plQcBaseUomId') <p class="hint !text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex items-center gap-1 p-3 rounded-xl
                        bg-white/30 dark:bg-white/[0.04]
                        border border-white/50 dark:border-white/[0.08]">
                <label @class([
                    'flex-1 flex items-center gap-2.5 cursor-pointer p-1.5 rounded-lg transition-colors',
                    'bg-brand-50 dark:bg-brand-900/20' => $plQcTrackBatch,
                ])>
                    <input wire:model="plQcTrackBatch" type="checkbox"
                           class="h-4 w-4 rounded text-brand-600">
                    <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Track Batch</span>
                </label>
                <div class="w-px h-6 bg-white/40 dark:bg-white/10 shrink-0"></div>
                <label @class([
                    'flex-1 flex items-center gap-2.5 cursor-pointer p-1.5 rounded-lg transition-colors',
                    'bg-brand-50 dark:bg-brand-900/20' => $plQcTrackExpiry,
                ])>
                    <input wire:model="plQcTrackExpiry" type="checkbox"
                           class="h-4 w-4 rounded text-brand-600">
                    <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Track Expiry</span>
                </label>
            </div>
        </div>

        {{-- QC footer --}}
        <div class="flex justify-end gap-2 px-5 py-4
                    border-t border-white/40 dark:border-white/[0.08]
                    bg-white/10 dark:bg-white/[0.02]">
            <button wire:click="$set('plQcOpen', false)" class="btn btn-ghost text-xs">Cancel</button>
            <button wire:click="plQuickCreateItem" wire:loading.attr="disabled" class="btn btn-primary text-xs">
                Create Item
            </button>
        </div>
    </div>
</div>
@endif
