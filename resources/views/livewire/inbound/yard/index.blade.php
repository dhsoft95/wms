<div class="space-y-6">

    {{-- Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Yard Map</h2>
            <p class="page-sub">Visual overview of yard slot occupancy. Click a slot to see container details.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('inbound.containers.index') }}" wire:navigate class="btn btn-secondary">
                <x-ui.icon name="list" class="w-4 h-4" /> All Containers
            </a>
        </div>
    </div>

    {{-- Stats Bar --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="card p-4 text-center">
            <p class="text-2xl font-bold tabular-nums">{{ $this->totalSlots }}</p>
            <p class="text-xs text-slate-500 mt-1">Total Slots</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-bold tabular-nums text-simba-600 dark:text-simba-400">{{ $this->occupiedCount }}</p>
            <p class="text-xs text-slate-500 mt-1">Occupied</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-bold tabular-nums text-green-600 dark:text-green-400">{{ $this->totalSlots - $this->occupiedCount }}</p>
            <p class="text-xs text-slate-500 mt-1">Free</p>
        </div>
        <div class="card p-4 text-center">
            @php $utilPct = $this->totalSlots > 0 ? round($this->occupiedCount / $this->totalSlots * 100) : 0; @endphp
            <p class="text-2xl font-bold tabular-nums {{ $utilPct >= 90 ? 'text-red-600' : ($utilPct >= 70 ? 'text-amber-600' : 'text-slate-700 dark:text-slate-200') }}">
                {{ $utilPct }}%
            </p>
            <p class="text-xs text-slate-500 mt-1">Utilization</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card p-4 flex flex-wrap items-center gap-3">
        <div>
            <label class="label sr-only">Zone</label>
            <select wire:model.live="filterZone" class="input text-sm py-1.5 px-2 h-auto w-40">
                <option value="">All zones</option>
                @foreach($this->zones as $zone)
                    <option value="{{ $zone }}">{{ $zone }}</option>
                @endforeach
            </select>
        </div>
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" wire:model.live="showFreeOnly" class="rounded accent-brand">
            <span class="text-sm text-slate-700 dark:text-slate-300">Show free slots only</span>
        </label>
        <div class="ml-auto flex items-center gap-4 text-xs text-slate-500">
            <span class="flex items-center gap-1.5">
                <span class="w-4 h-4 rounded border-2 border-dashed border-green-500 inline-block"></span> Free
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-4 h-4 rounded bg-simba-500 inline-block"></span> Occupied
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-4 h-4 rounded bg-amber-500 inline-block"></span> Warning dwell
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-4 h-4 rounded bg-red-500 inline-block"></span> Critical dwell
            </span>
        </div>
    </div>

    <div class="flex gap-6 items-start">
        {{-- Yard Grid --}}
        <div class="flex-1 min-w-0">
            @if($this->yardSlots->isEmpty())
                <div class="card p-10 text-center">
                    <x-ui.icon name="flag" class="w-10 h-10 mx-auto text-slate-300 dark:text-slate-600" />
                    <p class="mt-3 text-sm font-medium text-slate-700 dark:text-slate-300">No yard slots configured</p>
                    <p class="text-xs text-slate-400 mt-1">Add yard slot locations in the Locations master data section.</p>
                </div>
            @else
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-8 gap-2">
                    @foreach($this->yardSlots as $slot)
                        @php
                            $container = $this->activeContainersBySlot[$slot->id] ?? null;
                            $isSelected = $selectedSlotId === $slot->id;
                            $dwellStatus = 'ok';
                            if ($container) {
                                $arrivedAt = $container['arrived_at'] ?? null;
                                if ($arrivedAt) {
                                    $dwell = (int) \Carbon\Carbon::parse($arrivedAt)->startOfDay()->diffInDays(now()->startOfDay());
                                    $warnDays = (int) \App\Models\Setting::get('container_dwell_warning_days', 7);
                                    $critDays = (int) \App\Models\Setting::get('container_dwell_critical_days', 14);
                                    if ($dwell >= $critDays) $dwellStatus = 'critical';
                                    elseif ($dwell >= $warnDays) $dwellStatus = 'warning';
                                }
                            }
                        @endphp
                        <button
                            wire:click="selectSlot({{ $slot->id }})"
                            title="{{ $slot->code }}{{ $container ? ': ' . $container['container_no'] : ' (free)' }}"
                            @class([
                                'relative flex flex-col items-center justify-center p-2 rounded-lg border-2 text-center transition-all min-h-[80px] text-xs font-medium',
                                // Free
                                'border-dashed border-green-400 bg-green-50/40 dark:bg-green-950/20 text-green-700 dark:text-green-400 hover:border-green-500' => !$container,
                                // Occupied — ok dwell
                                'border-simba-400 bg-simba-50 dark:bg-simba-950/30 text-simba-800 dark:text-simba-300 hover:border-simba-500' => $container && $dwellStatus === 'ok',
                                // Occupied — warning dwell
                                'border-amber-400 bg-amber-50 dark:bg-amber-950/30 text-amber-800 dark:text-amber-300 hover:border-amber-500' => $container && $dwellStatus === 'warning',
                                // Occupied — critical dwell
                                'border-red-400 bg-red-50 dark:bg-red-950/30 text-red-800 dark:text-red-400 hover:border-red-500' => $container && $dwellStatus === 'critical',
                                // Selected ring
                                'ring-2 ring-offset-1 ring-simba-600 dark:ring-simba-400' => $isSelected,
                            ])>
                            <span class="font-mono text-[10px] text-slate-500 dark:text-slate-400 leading-none mb-1">{{ $slot->code }}</span>
                            @if($container)
                                <span class="font-mono font-bold text-[11px] leading-none">{{ substr($container['container_no'], 0, 7) }}&hellip;</span>
                                <span class="text-[10px] mt-0.5 leading-none opacity-70">{{ $container['size_type'] ?? '' }}</span>
                                @if($container['arrived_at'] ?? null)
                                    @php
                                        $d = (int) \Carbon\Carbon::parse($container['arrived_at'])->startOfDay()->diffInDays(now()->startOfDay());
                                    @endphp
                                    <span class="mt-1 text-[10px] font-semibold">{{ $d }}d</span>
                                @endif
                            @else
                                <span class="text-[10px] text-green-600 dark:text-green-400 mt-1">Free</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Side panel --}}
        @if($selectedSlotId)
            <div class="w-72 shrink-0">
                <div class="card overflow-hidden">
                    @if($this->selectedContainer)
                        @php $sc = $this->selectedContainer; $dwell = $sc->dwellDays(); $dwellStatus = $sc->dwellStatus(); @endphp
                        <div class="card-header py-4">
                            <div>
                                <h3 class="font-mono font-bold text-sm text-slate-900 dark:text-white">{{ $sc->container_no }}</h3>
                                <p class="text-xs text-slate-400 font-mono mt-0.5">{{ $sc->number }}</p>
                            </div>
                            <button wire:click="$set('selectedSlotId', null)" class="icon-btn">
                                <x-ui.icon name="x" class="w-4 h-4" />
                            </button>
                        </div>
                        <dl class="divide-y divide-white/50 dark:divide-white/[0.06] px-4">
                            <div class="py-3 flex justify-between items-center">
                                <dt class="section-label">Status</dt>
                                <dd><span class="badge {{ $sc->status->color() }}"><span class="dot"></span>{{ $sc->status->label() }}</span></dd>
                            </div>
                            <div class="py-3 flex justify-between items-center">
                                <dt class="section-label">Yard Slot</dt>
                                <dd class="font-mono text-xs">{{ $sc->yardLocation?->code ?? '—' }}</dd>
                            </div>
                            <div class="py-3 flex justify-between items-center">
                                <dt class="section-label">Dwell</dt>
                                <dd>
                                    <span @class([
                                        'badge',
                                        'badge-neutral' => $dwellStatus === 'ok',
                                        'badge-amber'   => $dwellStatus === 'warning',
                                        'badge-red'     => $dwellStatus === 'critical',
                                    ])>{{ $dwell }}d</span>
                                </dd>
                            </div>
                            <div class="py-3 flex justify-between items-center">
                                <dt class="section-label">Size</dt>
                                <dd class="text-xs text-slate-600 dark:text-slate-400">{{ $sc->size_type?->label() }}</dd>
                            </div>
                            <div class="py-3 flex justify-between items-center">
                                <dt class="section-label">Shipping Line</dt>
                                <dd class="text-xs text-slate-600 dark:text-slate-400">{{ $sc->shippingLinePartner?->name ?? '—' }}</dd>
                            </div>
                            <div class="py-3 flex justify-between items-center">
                                <dt class="section-label">Lines</dt>
                                <dd class="text-xs tabular-nums text-slate-600 dark:text-slate-400">{{ $sc->expectedLines->count() }} lines</dd>
                            </div>
                        </dl>
                        <div class="p-4 border-t border-white/40 dark:border-white/[0.08]">
                            <a href="{{ route('inbound.containers.show', $sc) }}" wire:navigate class="btn btn-primary w-full text-sm">
                                <x-ui.icon name="eye" class="w-4 h-4" /> View Container
                            </a>
                        </div>
                    @else
                        <div class="p-6 text-center">
                            <x-ui.icon name="flag" class="w-8 h-8 mx-auto text-slate-300 dark:text-slate-600" />
                            <p class="mt-3 text-sm font-medium text-slate-700 dark:text-slate-300">Free slot</p>
                            <p class="text-xs text-slate-400 mt-1">No active container in this slot.</p>
                            <button wire:click="$set('selectedSlotId', null)" class="btn btn-ghost mt-3 text-xs">Deselect</button>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>

</div>
