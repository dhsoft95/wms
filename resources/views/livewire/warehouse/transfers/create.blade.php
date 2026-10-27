@php
    $fmt = fn ($qty) => rtrim(rtrim(number_format((float) $qty, 4, '.', ','), '0'), '.');

    $hasFromVw    = ! empty($fromVwId);
    $fromVwName   = $warehouses->firstWhere('id', $fromVwId)?->name;
    $toVwName     = $warehouses->firstWhere('id', $toVwId)?->name;
    $filledLines  = collect($lines)->filter(fn ($l) => ! empty($l['item_id']) && (float) ($l['qty'] ?? 0) > 0)->count();
    $physicalReady = ! $isPhysical || collect($lines)->every(fn ($l) => ! empty($l['to_location_id']));
@endphp

<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div class="min-w-0">
            <a href="{{ route('warehouse.transfers.index') }}" wire:navigate
               class="inline-flex items-center gap-1.5 mb-2 text-xs font-semibold text-slate-500 transition-colors hover:text-simba-600 dark:text-slate-400 dark:hover:text-simba-400">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5 shrink-0" />
                <span>Back to Transfers</span>
            </a>
            <h2 class="page-title">New Department Transfer</h2>
            <p class="page-sub">Transfer stock ownership between virtual warehouses.</p>
        </div>
    </div>

    <form wire:submit="save" class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Main column --}}
        <div class="space-y-6 lg:col-span-2 min-w-0">

            {{-- Warehouses --}}
            <div class="card">
                <div class="card-header">
                    <div class="flex items-center gap-2.5">
                        <span class="flex items-center justify-center w-6 h-6 text-xs font-bold rounded-full bg-simba-50 text-simba-600 dark:bg-simba-500/10 dark:text-simba-400">1</span>
                        <h3 class="card-title">Warehouses</h3>
                    </div>
                </div>
                <div class="card-body space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_auto_1fr] md:items-start">
                        <div>
                            <label class="label">From <span class="required-mark">*</span></label>
                            <select wire:model.live="fromVwId" class="input @error('fromVwId') border-red-400 @enderror">
                                <option value="">Select source warehouse…</option>
                                @foreach($warehouses as $vw)
                                    <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                                @endforeach
                            </select>
                            @error('fromVwId') <p class="hint hint-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="hidden md:flex items-center justify-center w-10 h-10 mt-6 rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800">
                            <x-ui.icon name="arrow-right" class="w-4 h-4 shrink-0" />
                        </div>

                        <div>
                            <label class="label">To <span class="required-mark">*</span></label>
                            <select wire:model.live="toVwId" class="input @error('toVwId') border-red-400 @enderror">
                                <option value="">Select destination warehouse…</option>
                                @foreach($warehouses as $vw)
                                    <option value="{{ $vw->id }}" @disabled($vw->id == $fromVwId)>{{ $vw->name }}</option>
                                @endforeach
                            </select>
                            @error('toVwId') <p class="hint hint-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="label">Notes <span class="text-xs font-normal text-slate-400">(optional)</span></label>
                        <textarea wire:model="notes" rows="2" class="input resize-none" placeholder="Reason for transfer, reference, etc."></textarea>
                    </div>

                    {{-- Physical relocation toggle --}}
                    <div class="flex items-start gap-4 p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/30">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Physical Relocation</p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                @if($isPhysical)
                                    Stock will physically move to a new bin <span class="font-semibold">and</span> change ownership.
                                @else
                                    Ownership changes only  stock stays in the same bin.
                                @endif
                            </p>
                        </div>
                        <button type="button"
                                wire:click="$toggle('isPhysical')"
                                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none
                                       {{ $isPhysical ? 'bg-simba-500' : 'bg-slate-200 dark:bg-slate-700' }}">
                            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out
                                         {{ $isPhysical ? 'translate-x-5' : 'translate-x-0' }}"></span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Lines --}}
            <div class="card">
                <div class="card-header">
                    <div class="flex items-center gap-2.5">
                        <span class="flex items-center justify-center w-6 h-6 text-xs font-bold rounded-full bg-simba-50 text-simba-600 dark:bg-simba-500/10 dark:text-simba-400">2</span>
                        <h3 class="card-title">Lines</h3>
                        <span class="badge badge-slate text-xs tabular-nums">{{ count($lines) }}</span>
                    </div>
                    <button type="button" wire:click="$set('blkOpen', true)"
                            class="btn btn-sm btn-ghost inline-flex items-center gap-1.5"
                        @disabled(! $hasFromVw)>
                        <x-ui.icon name="upload" class="w-4 h-4 shrink-0" />
                        <span>Bulk Upload</span>
                    </button>
                </div>

                <div class="card-body space-y-4">
                    @if(! $hasFromVw)
                        <div class="flex items-center gap-2 px-4 py-3 text-sm border rounded-xl border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400">
                            <x-ui.icon name="exclamation" class="w-4 h-4 shrink-0" />
                            <span>Select a source warehouse to start adding lines.</span>
                        </div>
                    @endif

                    @foreach($lines as $i => $line)
                        @php
                            $stockAtBin = $this->stockByLine[$i] ?? [];
                            $hasBin     = ! empty($line['location_id']);
                            $hasItem    = ! empty($line['item_id']);
                            $available  = $this->availableByLine[$i] ?? null;
                        @endphp

                        <div wire:key="trf-line-{{ $i }}"
                             class="rounded-xl border border-slate-200 dark:border-slate-700 {{ $hasFromVw ? '' : 'opacity-60' }}">

                            {{-- Line header --}}
                            <div class="flex items-center justify-between px-4 py-2.5 border-b border-slate-100 dark:border-slate-800">
                                <span class="text-xs font-semibold tracking-wide uppercase text-slate-500">Line {{ $i + 1 }}</span>
                                @if(count($lines) > 1)
                                    <button type="button" wire:click="removeLine({{ $i }})"
                                            class="icon-btn text-slate-400 hover:text-red-500" title="Remove line">
                                        <x-ui.icon name="trash" class="w-4 h-4 shrink-0" />
                                    </button>
                                @endif
                            </div>

                            <div class="p-4 space-y-4">

                                {{-- Bin + Item --}}
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="{{ $isPhysical ? 'md:col-span-2' : '' }}">
                                        <div class="grid grid-cols-1 gap-4 {{ $isPhysical ? 'md:grid-cols-[1fr_auto_1fr]' : '' }} md:items-start">
                                            <div>
                                                <label class="label">Source Bin <span class="required-mark">*</span></label>
                                                <select wire:model.live="lines.{{ $i }}.location_id"
                                                        class="input font-mono @error('lines.'.$i.'.location_id') border-red-400 @enderror"
                                                    @disabled(! $hasFromVw)>
                                                    <option value="">{{ $hasFromVw ? 'Select bin…' : 'Select warehouse first' }}</option>
                                                    @if($hasFromVw)
                                                        @foreach($this->locations as $loc)
                                                            <option value="{{ $loc->id }}">{{ $loc->code }}</option>
                                                        @endforeach
                                                    @endif
                                                </select>
                                                @error('lines.'.$i.'.location_id') <p class="hint hint-error">{{ $message }}</p> @enderror
                                            </div>

                                            @if($isPhysical)
                                                <div class="hidden md:flex items-center justify-center w-10 h-10 mt-5 rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800 shrink-0">
                                                    <x-ui.icon name="arrow-right" class="w-4 h-4 shrink-0" />
                                                </div>
                                                <div>
                                                    <label class="label">Destination Bin <span class="required-mark">*</span></label>
                                                    <select wire:model="lines.{{ $i }}.to_location_id"
                                                            class="input font-mono @error('lines.'.$i.'.to_location_id') border-red-400 @enderror"
                                                        @disabled(! $hasBin)>
                                                        <option value="">{{ $hasBin ? 'Select destination…' : 'Select source bin first' }}</option>
                                                        @if($hasBin)
                                                            @foreach($this->locations as $loc)
                                                                <option value="{{ $loc->id }}" @disabled($loc->id == ($line['location_id'] ?? 0))>
                                                                    {{ $loc->code }}
                                                                </option>
                                                            @endforeach
                                                        @endif
                                                    </select>
                                                    @error('lines.'.$i.'.to_location_id') <p class="hint hint-error">{{ $message }}</p> @enderror
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <div>
                                        <label class="label">Item <span class="required-mark">*</span></label>
                                        <select wire:model.live="lines.{{ $i }}.item_id"
                                                class="input @error('lines.'.$i.'.item_id') border-red-400 @enderror"
                                            @disabled(! $hasBin || ! $hasFromVw || count($stockAtBin) === 0)>
                                            <option value="">
                                                @if(! $hasFromVw) Select warehouse first
                                                @elseif(! $hasBin) Select bin first
                                                @elseif(count($stockAtBin) === 0) No stock in this bin
                                                @else Select item…
                                                @endif
                                            </option>
                                            @if($hasBin && $hasFromVw)
                                                @foreach($stockAtBin as $stock)
                                                    <option value="{{ $stock['item']?->id }}">
                                                        {{ $stock['item']?->sku }} — {{ $stock['item']?->name }} ({{ $fmt($stock['qty']) }})
                                                    </option>
                                                @endforeach
                                            @endif
                                        </select>
                                        @error('lines.'.$i.'.item_id') <p class="hint hint-error">{{ $message }}</p> @enderror
                                    </div>
                                </div>{{-- end bin+item grid --}}

                                {{-- Stock at bin (shown until an item is picked) --}}
                                @if($hasBin && $hasFromVw && ! $hasItem)
                                    @if(count($stockAtBin) > 0)
                                        <div class="overflow-hidden border rounded-lg border-slate-200 dark:border-slate-700">
                                            <div class="flex items-center gap-2 px-3 py-2 border-b bg-slate-50 border-slate-200 dark:bg-slate-800/50 dark:border-slate-700">
                                                <x-ui.icon name="archive" class="w-3.5 h-3.5 shrink-0 text-slate-400" />
                                                <span class="text-xs font-semibold tracking-wide uppercase text-slate-500">
                                                    In this bin · {{ count($stockAtBin) }} {{ Str::plural('item', count($stockAtBin)) }}
                                                </span>
                                            </div>
                                            <div class="overflow-y-auto divide-y max-h-44 divide-slate-100 dark:divide-slate-800">
                                                @foreach($stockAtBin as $stock)
                                                    <button type="button"
                                                            wire:click="$set('lines.{{ $i }}.item_id', {{ $stock['item']?->id }})"
                                                            class="flex items-center justify-between w-full gap-3 px-3 py-2 text-sm text-left transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                                        <span class="min-w-0 truncate">
                                                            <span class="font-mono text-xs font-semibold text-simba-600 dark:text-simba-400">{{ $stock['item']?->sku }}</span>
                                                            <span class="ml-2 text-slate-600 dark:text-slate-300">{{ $stock['item']?->name }}</span>
                                                        </span>
                                                        <span class="font-semibold tabular-nums shrink-0 text-emerald-600 dark:text-emerald-400">
                                                            {{ $fmt($stock['qty']) }}
                                                        </span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    @else
                                        <div class="flex items-center gap-2 px-3 py-2.5 text-sm border rounded-lg border-amber-200 bg-amber-50/60 text-amber-700 dark:border-amber-800/50 dark:bg-amber-950/20 dark:text-amber-400">
                                            <x-ui.icon name="exclamation" class="w-4 h-4 shrink-0" />
                                            <span>No available stock in this bin for the selected warehouse.</span>
                                        </div>
                                    @endif
                                @endif

                                {{-- Qty / UoM / Batch --}}
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <div class="flex items-baseline justify-between">
                                            <label class="label">Qty <span class="required-mark">*</span></label>
                                            @if($hasItem && $available !== null)
                                                <span class="text-xs text-slate-400">
                                                    of
                                                    <span class="font-semibold tabular-nums {{ (float) $available > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500' }}">
                                                        {{ $fmt($available) }}
                                                    </span>
                                                </span>
                                            @endif
                                        </div>
                                        <input wire:model="lines.{{ $i }}.qty" type="number" step="0.0001" min="0.0001"
                                               @if($available !== null) max="{{ (float) $available }}" @endif
                                               class="input mono tabular-nums @error('lines.'.$i.'.qty') border-red-400 @enderror"
                                               placeholder="0"
                                            @disabled(! $hasItem)>
                                        @error('lines.'.$i.'.qty') <p class="hint hint-error">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label class="label">UoM <span class="required-mark">*</span></label>
                                        <select wire:model="lines.{{ $i }}.uom_id"
                                                class="input font-mono @error('lines.'.$i.'.uom_id') border-red-400 @enderror"
                                            @disabled(! $hasItem)>
                                            <option value="">Select…</option>
                                            @foreach($this->uoms as $uom)
                                                <option value="{{ $uom->id }}">{{ $uom->code }}</option>
                                            @endforeach
                                        </select>
                                        @error('lines.'.$i.'.uom_id') <p class="hint hint-error">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label class="label">Batch No <span class="text-xs font-normal text-slate-400">(optional)</span></label>
                                        <input wire:model="lines.{{ $i }}.batch_no" type="text"
                                               class="input mono @error('lines.'.$i.'.batch_no') border-red-400 @enderror"
                                               placeholder="e.g. BAT-001"
                                            @disabled(! $hasItem)>
                                        @error('lines.'.$i.'.batch_no') <p class="hint hint-error">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                            </div>
                        </div>
                    @endforeach

                    <button type="button" wire:click="addLine"
                            class="flex items-center justify-center w-full gap-1.5 py-3 text-sm font-semibold transition-colors border-2 border-dashed rounded-xl border-slate-200 text-slate-500 hover:border-simba-300 hover:text-simba-600 dark:border-slate-700 dark:text-slate-400 dark:hover:border-simba-500/50 dark:hover:text-simba-400 disabled:opacity-50 disabled:cursor-not-allowed"
                        @disabled(! $hasFromVw)>
                        <x-ui.icon name="plus" class="w-4 h-4 shrink-0" />
                        <span>Add Line</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Summary sidebar --}}
        <div class="lg:col-span-1">
            <div class="card lg:sticky lg:top-6">
                <div class="card-header">
                    <h3 class="card-title">Summary</h3>
                </div>

                <div class="card-body space-y-4">
                    <div class="space-y-2">
                        <div class="flex items-center gap-3 p-3 rounded-lg bg-slate-50 dark:bg-slate-800/50">
                            <span class="text-xs font-medium tracking-wide uppercase w-10 text-slate-400">From</span>
                            <span class="text-sm font-semibold truncate {{ $fromVwName ? 'text-slate-800 dark:text-slate-200' : 'text-slate-400' }}">
                                {{ $fromVwName ?? 'Not selected' }}
                            </span>
                        </div>
                        <div class="flex justify-center text-slate-300 dark:text-slate-600">
                            <x-ui.icon name="arrow-down" class="w-4 h-4 shrink-0" />
                        </div>
                        <div class="flex items-center gap-3 p-3 rounded-lg bg-slate-50 dark:bg-slate-800/50">
                            <span class="text-xs font-medium tracking-wide uppercase w-10 text-slate-400">To</span>
                            <span class="text-sm font-semibold truncate {{ $toVwName ? 'text-slate-800 dark:text-slate-200' : 'text-slate-400' }}">
                                {{ $toVwName ?? 'Not selected' }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-3 text-sm border-t border-slate-100 dark:border-slate-800">
                        <span class="text-slate-500">Type</span>
                        <span class="font-semibold {{ $isPhysical ? 'text-blue-600 dark:text-blue-400' : 'text-slate-600 dark:text-slate-400' }}">
                            {{ $isPhysical ? 'Physical relocation' : 'Ownership only' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-500">Lines ready</span>
                        <span class="font-semibold tabular-nums {{ $filledLines === count($lines) && $filledLines > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-800 dark:text-slate-200' }}">
                            {{ $filledLines }} / {{ count($lines) }}
                        </span>
                    </div>
                </div>

                <div class="card-footer flex-col !items-stretch gap-2">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="save"
                            class="btn btn-primary inline-flex items-center justify-center gap-1.5 h-10 w-full disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-1.5">
                            <x-ui.icon name="check" class="w-4 h-4 shrink-0" />
                            <span>Save Draft</span>
                        </span>
                        <span wire:loading.inline-flex wire:target="save" class="items-center gap-1.5">
                            <svg class="w-4 h-4 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25" />
                                <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                            </svg>
                            <span>Saving…</span>
                        </span>
                    </button>
                    <a href="{{ route('warehouse.transfers.index') }}" wire:navigate
                       class="btn btn-secondary inline-flex items-center justify-center h-10 w-full">
                        Cancel
                    </a>
                </div>
            </div>
        </div>

    </form>

    @include('components.bulk-line-upload')
</div>
