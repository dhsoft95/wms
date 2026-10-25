<div class="space-y-6">
    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Put-Away Tasks</h2>
            <p class="page-sub">Assign and execute putaway tasks to move goods from dock to storage.</p>
        </div>
        <div class="flex items-center gap-2">
            <label class="flex items-center gap-2 cursor-pointer text-sm text-slate-600 dark:text-slate-400">
                <input wire:model.live="myTasksOnly" type="checkbox"
                       class="rounded text-simba-600 focus:ring-simba-600" />
                My Tasks Only
            </label>
        </div>
    </div>

    <div class="card">
        {{-- Status Tabs --}}
        <div class="card-header flex-col sm:flex-row gap-3">
            <div class="tabs overflow-x-auto">
                <button wire:click="$set('statusFilter','all')"
                        @class(['tab','active' => $statusFilter === 'all'])>
                    All <span class="badge badge-neutral ml-1">{{ $this->statusCounts['all'] ?? 0 }}</span>
                </button>
                @foreach ($this->statuses as $status)
                    <button wire:click="$set('statusFilter','{{ $status->value }}')"
                            @class(['tab','active' => $statusFilter === $status->value])>
                        {{ $status->label() }}
                        @if (($this->statusCounts[$status->value] ?? 0) > 0)
                            <span class="badge {{ $status->color() }} ml-1">{{ $this->statusCounts[$status->value] }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
            <div class="relative ml-auto">
                <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                <input wire:model.live.debounce.300ms="search" type="text"
                       placeholder="Search item, GRN…"
                       class="input !pl-9 sm:w-56" />
            </div>
        </div>

        <x-ui.table>
            <thead>
                <tr>
                    <th>GRN</th>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>From</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->tasks as $task)
                    <tr wire:key="task-{{ $task->id }}">
                        <td class="mono text-sm">{{ $task->grn?->number }}</td>
                        <td>
                            <div class="mono text-xs text-simba-600 dark:text-simba-400">{{ $task->item?->sku }}</div>
                            <div class="text-sm">{{ $task->item?->name }}</div>
                        </td>
                        <td class="tabular-nums">{{ number_format((float)$task->qty, 2) }}</td>
                        <td class="mono text-xs">{{ $task->fromLocation?->code }}</td>
                        <td class="mono text-xs">
                            @if ($task->actualLocation)
                                <span class="font-semibold text-emerald-600 dark:text-emerald-400">
                                    {{ $task->actualLocation->code }}
                                </span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $statusLabel = match($task->status) {
                                    \App\Enums\PutawayTaskStatus::Open     => 'Putaway In Progress',
                                    \App\Enums\PutawayTaskStatus::Assigned => 'Putaway In Progress',
                                    default                                => $task->status->label(),
                                };
                            @endphp
                            <span class="badge {{ $task->status->color() }}">
                                <span class="dot"></span>{{ $statusLabel }}
                            </span>
                        </td>
                        <td class="text-right">
                            @if (in_array($task->status->value, ['open', 'assigned', 'in_progress']))
                                @can('execute', $task)
                                    <button wire:click="openExecute({{ $task->id }})"
                                            class="btn btn-primary btn-sm">
                                        Execute
                                    </button>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="!py-12 text-center text-slate-500">No putaway tasks found.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        <div class="px-4 py-3 border-t border-white/40 dark:border-white/[0.06]">
            {{ $this->tasks->links() }}
        </div>
    </div>

    {{-- Execute Putaway Modal --}}
    @if ($showExecuteModal && $this->executingTask)
    @php
        $task           = $this->executingTask;
        $taskQty        = (float) $task->qty;
        $trackSerial    = (bool) $task->item?->track_serial;
        $serialDriven   = $trackSerial && !empty($taskSerials);

        if ($serialDriven) {
            $alloc   = collect($splitRows)->sum(fn($r) => count((array)($r['serials'] ?? [])));
            $allocOk = $alloc === (int) $taskQty && $taskQty > 0;
        } else {
            $alloc   = array_sum(array_map(fn($r) => (float)($r['qty'] ?? 0), $splitRows));
            $allocOk = abs($alloc - $taskQty) < 0.0001 && $taskQty > 0;
        }
    @endphp
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-lg flex flex-col max-h-[90vh]">

            {{-- Header --}}
            <div class="flex items-start justify-between px-6 pt-5 pb-4 border-b border-slate-200 dark:border-slate-800 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-simba-50 dark:bg-simba-900/30 flex items-center justify-center shrink-0">
                        <x-ui.icon name="arrow-down-tray" class="w-4.5 h-4.5 text-simba-600 dark:text-simba-400" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Execute Putaway</h3>
                        <p class="text-xs text-slate-500 mt-0.5 font-mono">GRN: {{ $task->grn?->number }}</p>
                    </div>
                </div>
                <button wire:click="closeExecute" class="icon-btn mt-0.5">
                    <x-ui.icon name="x" class="w-4 h-4" />
                </button>
            </div>

            {{-- Item info strip --}}
            <div class="px-6 py-3 grid grid-cols-3 gap-4 bg-slate-50 dark:bg-white/[0.03] border-b border-slate-200 dark:border-slate-700 shrink-0 text-sm">
                <div class="col-span-2">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-0.5">Item</p>
                    <p class="font-mono font-bold text-slate-800 dark:text-slate-100 text-sm">{{ $task->item?->sku }}</p>
                    <p class="text-slate-500 dark:text-slate-400 text-xs truncate">{{ $task->item?->name }}</p>
                </div>
                <div class="space-y-2">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-0.5">Total Qty</p>
                        <p class="tabular-nums font-bold text-slate-800 dark:text-slate-100">{{ number_format($taskQty, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-0.5">From</p>
                        <p class="font-mono text-slate-700 dark:text-slate-300 text-xs">{{ $task->fromLocation?->code ?? '—' }}</p>
                    </div>
                </div>
            </div>

            {{-- Draft restored banner --}}
            @if ($hasDraft)
            <div class="px-6 py-2.5 shrink-0 flex items-center justify-between bg-amber-50 dark:bg-amber-950/30 border-b border-amber-200 dark:border-amber-800/50">
                <div class="flex items-center gap-2 text-sm text-amber-800 dark:text-amber-300">
                    <x-ui.icon name="clock" class="w-4 h-4 shrink-0" />
                    <span>Draft restored from a previous session.</span>
                </div>
                <button wire:click="discardDraft"
                        class="text-xs font-semibold text-amber-700 dark:text-amber-400 hover:underline">
                    Start fresh
                </button>
            </div>
            @endif

            {{-- Allocation progress bar --}}
            <div class="px-6 py-3 shrink-0 border-b border-slate-200 dark:border-slate-700">
                <div class="flex items-center justify-between text-xs mb-1.5">
                    <span class="text-slate-500 font-medium">{{ $serialDriven ? 'Serials assigned' : 'Quantity allocated' }}</span>
                    <span @class([
                        'tabular-nums font-bold',
                        'text-emerald-600 dark:text-emerald-400' => $allocOk,
                        'text-amber-600 dark:text-amber-400'     => !$allocOk && $alloc > 0,
                        'text-slate-400'                          => $alloc == 0,
                    ])>
                        @if ($serialDriven)
                            {{ $alloc }} / {{ (int) $taskQty }}
                        @else
                            {{ number_format($alloc, 2) }} / {{ number_format($taskQty, 2) }}
                        @endif
                    </span>
                </div>
                @php
                    $pct = $taskQty > 0 ? min(100, ($alloc / $taskQty) * 100) : 0;
                @endphp
                <div class="h-1.5 w-full rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                    <div @class([
                        'h-full rounded-full transition-all duration-300',
                        'bg-emerald-500' => $allocOk,
                        'bg-amber-400'   => !$allocOk && $alloc > 0,
                        'bg-slate-300'   => $alloc == 0,
                    ]) style="width: {{ $pct }}%"></div>
                </div>
            </div>

            {{-- Bin rows --}}
            <div class="overflow-y-auto flex-1 px-6 py-4 space-y-3">
                @foreach ($splitRows as $i => $row)
                    <div wire:key="split-row-{{ $i }}"
                         class="rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">

                        @php
                            $takenLocationIds = collect($splitRows)
                                ->filter(fn($r, $idx) => $idx !== $i && !empty($r['locationId']))
                                ->pluck('locationId')
                                ->map(fn($id) => (int) $id)
                                ->values()
                                ->all();
                        @endphp

                        {{-- Row body: bin (flex-1) | qty (fixed) | remove (icon) --}}
                        <div class="p-3 flex flex-col sm:flex-row sm:items-end gap-3">

                            {{-- Bin combobox --}}
                            <div class="flex-1 min-w-0"
                                 wire:key="bin-sel-{{ $i }}"
                                 x-data="{
                                     open: false,
                                     search: '',
                                     allBins: [],
                                     loading: false,
                                     focusedIdx: -1,
                                     triggerEl: null,
                                     panelStyle: 'top:0;left:0;width:300px;max-height:320px',
                                     rowIdx: {{ $i }},
                                     takenIds: @js($takenLocationIds),
                                     get filtered() {
                                         const s = this.search.toLowerCase();
                                         return s
                                             ? this.allBins.filter(r => r.code.toLowerCase().includes(s) || (r.barcode && r.barcode.toLowerCase().includes(s)))
                                             : this.allBins;
                                     },
                                     computePosition() {
                                         if (!this.triggerEl) return;
                                         const r = this.triggerEl.getBoundingClientRect();
                                         const vh = window.innerHeight, vw = window.innerWidth;
                                         const pw = Math.max(r.width, 300);
                                         const below = vh - r.bottom - 8, above = r.top - 8;
                                         let top, avail;
                                         if (below >= 200 || below >= above) {
                                             top = r.bottom + 4; avail = Math.min(320, below);
                                         } else {
                                             avail = Math.min(320, above); top = r.top - avail - 4;
                                         }
                                         let left = r.left;
                                         if (left + pw > vw - 8) left = vw - pw - 8;
                                         if (left < 8) left = 8;
                                         this.panelStyle = 'top:' + Math.round(top) + 'px;left:' + Math.round(left) + 'px;width:' + Math.round(pw) + 'px;max-height:' + Math.round(avail) + 'px';
                                     },
                                     async openDropdown(el) {
                                         this.triggerEl = el; this.open = true; this.search = ''; this.focusedIdx = -1;
                                         this.computePosition();
                                         this._rp = () => { if (this.open) this.computePosition(); };
                                         window.addEventListener('scroll', this._rp, true);
                                         window.addEventListener('resize', this._rp);
                                         if (this.allBins.length === 0) {
                                             this.loading = true;
                                             this.allBins = await $wire.getBinSuggestions('');
                                             this.loading = false;
                                         }
                                         this.$nextTick(() => this.$refs.binSearch?.focus());
                                     },
                                     closeDropdown() {
                                         this.open = false; this.focusedIdx = -1;
                                         window.removeEventListener('scroll', this._rp, true);
                                         window.removeEventListener('resize', this._rp);
                                         this.$nextTick(() => this.triggerEl?.focus());
                                     },
                                     handleKey(e) {
                                         if (!this.open) return;
                                         const avail = this.filtered.filter(r => !this.takenIds.includes(r.id));
                                         if (e.key === 'ArrowDown') {
                                             e.preventDefault(); this.focusedIdx = Math.min(this.focusedIdx + 1, avail.length - 1);
                                             this.$nextTick(() => this.$refs.binList?.querySelector('[data-focused]')?.scrollIntoView({ block: 'nearest' }));
                                         } else if (e.key === 'ArrowUp') {
                                             e.preventDefault(); this.focusedIdx = Math.max(this.focusedIdx - 1, 0);
                                             this.$nextTick(() => this.$refs.binList?.querySelector('[data-focused]')?.scrollIntoView({ block: 'nearest' }));
                                         } else if (e.key === 'Enter' && this.focusedIdx >= 0 && avail[this.focusedIdx]) {
                                             e.preventDefault(); const loc = avail[this.focusedIdx];
                                             $wire.selectSplitBin(this.rowIdx, loc.id, loc.code); this.closeDropdown();
                                         } else if (e.key === 'Escape') {
                                             e.preventDefault(); this.closeDropdown();
                                         }
                                     }
                                 }">
                                <label class="label text-xs">
                                    Destination {{ $i + 1 }} <span class="text-red-500">*</span>
                                </label>

                                @if (empty($row['locationId']))
                                    {{-- Trigger button --}}
                                    <button type="button"
                                            @click="open ? closeDropdown() : openDropdown($el)"
                                            class="input w-full flex items-center justify-between gap-2 text-left cursor-pointer">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <x-ui.icon name="map-pin" class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                            <span class="text-slate-400 text-sm">Select bin…</span>
                                        </div>
                                        <span class="transition-transform duration-200 shrink-0" :class="open ? 'rotate-180' : ''">
                                            <x-ui.icon name="chevron-down" class="w-4 h-4 text-slate-400" />
                                        </span>
                                    </button>

                                    {{-- Panel teleported to <body> to escape all overflow clipping --}}
                                    <template x-teleport="body">
                                        <div>
                                            {{-- Transparent backdrop — closes on outside click --}}
                                            <div x-show="open"
                                                 x-cloak
                                                 style="position:fixed;inset:0;z-index:9998"
                                                 @click="closeDropdown()"></div>

                                            {{-- Dropdown panel — fixed-positioned, anchored to trigger --}}
                                            <div x-show="open"
                                                 x-cloak
                                                 :style="'position:fixed;z-index:9999;' + panelStyle"
                                                 class="rounded-xl border border-slate-200 dark:border-white/[0.08] shadow-2xl bg-white dark:bg-zinc-900 flex flex-col overflow-hidden">

                                                {{-- Search input (pinned to top) --}}
                                                <div class="p-2 border-b border-slate-100 dark:border-white/[0.06] shrink-0">
                                                    <div class="relative">
                                                        <x-ui.icon name="search" class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                                                        <input type="text"
                                                               x-ref="binSearch"
                                                               x-model="search"
                                                               @keydown="handleKey($event)"
                                                               placeholder="Filter bins…"
                                                               class="input !pl-8 !py-1.5 text-sm w-full"
                                                               autocomplete="off" />
                                                    </div>
                                                </div>

                                                {{-- Scrollable list --}}
                                                <div x-ref="binList" class="overflow-y-auto flex-1">
                                                    <template x-if="loading">
                                                        <div class="px-3 py-5 text-center text-xs text-slate-400">Loading bins…</div>
                                                    </template>
                                                    <template x-if="!loading && filtered.length === 0">
                                                        <div class="px-3 py-5 text-center text-xs text-slate-400">No bins match.</div>
                                                    </template>
                                                    <template x-for="(loc, idx) in filtered" :key="loc.id">
                                                        <button type="button"
                                                                :data-focused="focusedIdx === idx ? '' : null"
                                                                @mousedown.prevent="if (!takenIds.includes(loc.id)) { $wire.selectSplitBin(rowIdx, loc.id, loc.code); closeDropdown(); }"
                                                                :disabled="takenIds.includes(loc.id)"
                                                                :class="{
                                                                    'opacity-40 cursor-not-allowed': takenIds.includes(loc.id),
                                                                    'bg-simba-50 dark:bg-simba-900/20': !takenIds.includes(loc.id) && focusedIdx === idx,
                                                                    'hover:bg-slate-50 dark:hover:bg-white/[0.04]': !takenIds.includes(loc.id) && focusedIdx !== idx
                                                                }"
                                                                class="w-full flex items-center justify-between px-3 py-2.5 text-sm transition-colors border-t first:border-0 border-slate-100 dark:border-white/[0.06]">
                                                            <div class="flex items-center gap-2">
                                                                <x-ui.icon name="map-pin" class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                                                <span x-text="loc.code" class="font-mono font-semibold text-slate-800 dark:text-slate-200"></span>
                                                            </div>
                                                            <div class="flex items-center gap-2 shrink-0">
                                                                <span x-show="loc.barcode && loc.barcode !== loc.code"
                                                                      x-text="loc.barcode"
                                                                      class="text-xs text-slate-400 font-mono"></span>
                                                                <span x-show="takenIds.includes(loc.id)"
                                                                      class="text-[10px] text-slate-400 italic">in use</span>
                                                            </div>
                                                        </button>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                @else
                                    {{-- Selected state chip --}}
                                    <div class="flex items-center gap-2 rounded-lg border border-emerald-200 dark:border-emerald-800/50 bg-emerald-50 dark:bg-emerald-900/20 px-3 py-2">
                                        <x-ui.icon name="map-pin" class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                                        <span class="font-mono text-sm font-bold text-emerald-700 dark:text-emerald-300 flex-1">{{ $row['locationCode'] }}</span>
                                        <button wire:click="clearSplitBin({{ $i }})"
                                                class="text-emerald-500 hover:text-red-500 transition-colors">
                                            <x-ui.icon name="x" class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                @endif
                            </div>

                            @if (! $serialDriven)
                            {{-- Quantity input --}}
                            <div class="w-28 shrink-0">
                                <label class="label text-xs">Quantity <span class="text-red-500">*</span></label>
                                <input wire:model.live="splitRows.{{ $i }}.qty"
                                       type="number"
                                       min="0.0001"
                                       step="any"
                                       placeholder="0.00"
                                       class="input w-full tabular-nums text-sm text-right" />
                            </div>
                            @endif

                            @if (count($splitRows) > 1)
                            {{-- Remove row --}}
                            <div class="shrink-0 self-end">
                                <button wire:click="removeSplitRow({{ $i }})"
                                        title="Remove destination"
                                        class="h-9 w-9 flex items-center justify-center rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 transition-colors">
                                    <x-ui.icon name="trash" class="w-4 h-4" />
                                </button>
                            </div>
                            @endif

                        </div>

                        {{-- Serials --}}
                        @if ($trackSerial && !empty($taskSerials))
                            @php
                                $rowSelected   = (array)($row['serials'] ?? []);
                                $takenByOthers = collect($splitRows)
                                    ->forget($i)
                                    ->flatMap(fn($r) => (array)($r['serials'] ?? []))
                                    ->toArray();
                            @endphp
                            <div class="px-3 pb-3 border-t border-slate-100 dark:border-slate-700/60 pt-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Serial Numbers</span>
                                    <span @class([
                                        'text-xs tabular-nums font-bold',
                                        'text-emerald-600 dark:text-emerald-400' => count($rowSelected) > 0,
                                        'text-slate-400' => count($rowSelected) === 0,
                                    ])>{{ count($rowSelected) }} selected</span>
                                </div>
                                <div class="max-h-32 overflow-y-auto rounded-lg border border-slate-200 dark:border-slate-700 divide-y divide-slate-100 dark:divide-white/[0.05]">
                                    @foreach ($taskSerials as $serial)
                                        @php
                                            $isChecked = in_array($serial, $rowSelected, true);
                                            $isTaken   = in_array($serial, $takenByOthers, true);
                                        @endphp
                                        <label @class([
                                            'flex items-center gap-2.5 px-3 py-1.5 cursor-pointer select-none transition-colors',
                                            'bg-emerald-50 dark:bg-emerald-900/20'           => $isChecked,
                                            'opacity-40 cursor-not-allowed'                  => $isTaken,
                                            'hover:bg-slate-50 dark:hover:bg-white/[0.03]'   => !$isTaken && !$isChecked,
                                        ])>
                                            <input type="checkbox"
                                                   wire:click="toggleRowSerial({{ $i }}, '{{ $serial }}')"
                                                   @checked($isChecked)
                                                   @disabled($isTaken)
                                                   class="rounded border-slate-300 dark:border-slate-600 text-simba-600 focus:ring-simba-500 shrink-0" />
                                            <span @class([
                                                'font-mono text-xs flex-1',
                                                'text-emerald-700 dark:text-emerald-300 font-bold' => $isChecked,
                                                'text-slate-700 dark:text-slate-300'               => !$isChecked,
                                            ])>{{ $serial }}</span>
                                            @if ($isTaken)
                                                <span class="text-[10px] text-slate-400 italic">in use</span>
                                            @endif
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach

                <button wire:click="addSplitRow"
                        class="w-full flex items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 dark:border-slate-700 py-3 text-sm font-medium text-slate-500 hover:border-simba-400 hover:text-simba-600 dark:hover:border-simba-600 dark:hover:text-simba-400 transition-colors">
                    <x-ui.icon name="plus" class="w-4 h-4" />
                    Add another destination
                </button>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-between px-6 py-4 border-t border-slate-200 dark:border-slate-800 shrink-0 bg-slate-50 dark:bg-white/[0.02] rounded-b-2xl">
                <button wire:click="saveDraft"
                        class="btn btn-secondary text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-800/60 hover:bg-amber-50 dark:hover:bg-amber-950/30 gap-1.5">
                    <x-ui.icon name="clock" class="w-4 h-4" /> Save Draft
                </button>
                <div class="flex gap-2">
                    <button wire:click="closeExecute" class="btn btn-ghost">Cancel</button>
                    <button wire:click="executeSplitTask"
                            wire:loading.attr="disabled"
                            @class([
                                'btn btn-primary gap-1.5',
                                'opacity-50 cursor-not-allowed' => !$allocOk,
                            ])
                            @disabled(! $allocOk)>
                        <x-ui.icon name="check-badge" class="w-4 h-4" />
                        <span wire:loading.remove wire:target="executeSplitTask">Confirm Putaway</span>
                        <span wire:loading wire:target="executeSplitTask">Processing…</span>
                    </button>
                </div>
            </div>

        </div>
    </div>
    @endif
</div>
