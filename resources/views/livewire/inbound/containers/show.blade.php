@php
    $dwell       = $container->dwellDays();
    $dwellStatus = $container->dwellStatus();
    $status      = $container->status;
@endphp

<div>
    <div class="space-y-6">

        {{-- ── Page Header ─────────────────────────────────────────────────────────── --}}
        <div class="flex flex-col gap-4 pb-6 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200/80 dark:border-white/[0.08]">
            <div class="min-w-0 space-y-1.5">
                <div class="flex items-center gap-2">
                    <a href="{{ route('inbound.containers.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 hover:text-simba-600 dark:text-slate-400 dark:hover:text-simba-400 transition-colors">
                        <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Containers
                    </a>
                </div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h2 class="text-2xl font-bold font-mono tracking-tight text-slate-900 dark:text-white">{{ $container->container_no }}</h2>
                    <span class="badge {{ $status->color() }}"><span class="dot"></span>{{ $status->label() }}</span>
                    @if($container->arrived_at)
                        <span @class([
                        'badge',
                        'badge-neutral' => $dwellStatus === 'ok',
                        'badge-amber'   => $dwellStatus === 'warning',
                        'badge-red'     => $dwellStatus === 'critical',
                    ])>{{ $dwell }}d dwell</span>
                    @endif
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 font-mono flex items-center gap-2">
                    <span>{{ $container->number }}</span>
                    <span>&middot;</span>
                    <span>{{ $container->size_type?->label() }}</span>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <a href="{{ route('inbound.containers.report.pdf', $container) }}"
                   target="_blank"
                   class="btn btn-secondary shadow-sm">
                    <x-ui.icon name="printer" class="w-4 h-4" /> Print Report
                </a>
                @if($status === \App\Enums\ContainerStatus::Expected)
                    @can('arrive', $container)
                        <button wire:click="$set('arrivalModalOpen', true)" class="btn btn-primary shadow-sm">
                            <x-ui.icon name="check" class="w-4 h-4" /> Confirm Arrival
                        </button>
                    @endcan
                    @can('update', $container)
                        <a href="{{ route('inbound.containers.edit', $container) }}" wire:navigate class="btn btn-secondary shadow-sm">
                            <x-ui.icon name="pencil" class="w-4 h-4" /> Edit
                        </a>
                    @endcan
                    @can('cancel', $container)
                        <button wire:click="$set('cancelModalOpen', true)" class="btn btn-ghost text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">
                            <x-ui.icon name="x" class="w-4 h-4" /> Cancel
                        </button>
                    @endcan
                @elseif($status === \App\Enums\ContainerStatus::Arrived)
                    @can('park', $container)
                        <button wire:click="$set('parkModalOpen', true)" class="btn btn-primary shadow-sm">
                            <x-ui.icon name="flag" class="w-4 h-4" /> Assign Yard Slot
                        </button>
                    @endcan
                    @can('update', $container)
                        <a href="{{ route('inbound.containers.edit', $container) }}" wire:navigate class="btn btn-ghost">
                            <x-ui.icon name="pencil" class="w-4 h-4" /> Edit
                        </a>
                    @endcan
                @elseif($status === \App\Enums\ContainerStatus::Parked)
                    @can('moveSlot', $container)
                        <button wire:click="$set('moveModalOpen', true)" class="btn btn-secondary shadow-sm">
                            <x-ui.icon name="arrows" class="w-4 h-4" /> Move Slot
                        </button>
                    @endcan
                    @can('update', $container)
                        <a href="{{ route('inbound.containers.edit', $container) }}" wire:navigate class="btn btn-ghost">
                            <x-ui.icon name="pencil" class="w-4 h-4" /> Edit
                        </a>
                    @endcan
                @elseif($status === \App\Enums\ContainerStatus::Unloading)
                    @can('markUnloaded', $container)
                        <button wire:click="markUnloaded" class="btn btn-primary shadow-sm">
                            <x-ui.icon name="check" class="w-4 h-4" /> Mark as Unloaded
                        </button>
                    @endcan
                @elseif($status === \App\Enums\ContainerStatus::Unloaded)
                    @can('release', $container)
                        <button wire:click="$set('releaseModalOpen', true)" class="btn btn-primary shadow-sm">
                            <x-ui.icon name="truck" class="w-4 h-4" /> Release Container
                        </button>
                    @endcan
                @endif
            </div>
        </div>

        {{-- ── Tab Navigation ───────────────────────────────────────────────────────── --}}
        <div class="flex border-b border-slate-200/80 dark:border-white/[0.08] gap-6">
            @foreach([
                ['overview', 'Overview'],
                ['lines',    'Order Lines'],
            ] as [$tab, $label])
                <button wire:click="$set('activeTab','{{ $tab }}')" @class([
                'pb-3 text-sm font-medium border-b-2 transition-colors relative -mb-[1px]',
                'border-simba-600 text-simba-600 dark:border-simba-400 dark:text-simba-400' => $activeTab === $tab,
                'border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200' => $activeTab !== $tab,
            ])>
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- ── Tab: Overview ───────────────────────────────────────────────────────── --}}
        @if($activeTab === 'overview')
            @php $grn = $container->grns->first(); @endphp
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- Container & Shipping --}}
                <section class="card overflow-hidden border border-slate-200/80 dark:border-white/[0.08] shadow-sm rounded-xl bg-white dark:bg-zinc-900">
                    <div class="px-5 py-4 bg-slate-50/75 dark:bg-white/[0.02] border-b border-slate-200/60 dark:border-white/[0.06]">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Container Details</h3>
                    </div>
                    <dl class="divide-y divide-slate-100 dark:divide-white/[0.05] px-5">
                        <div class="grid grid-cols-[150px_1fr] items-center py-3">
                            <dt class="text-xs font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider">Container No.</dt>
                            <dd class="font-mono font-semibold text-sm text-slate-900 dark:text-white">{{ $container->container_no }}</dd>
                        </div>
                        <div class="grid grid-cols-[150px_1fr] items-center py-3">
                            <dt class="text-xs font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider">Size / Type</dt>
                            <dd class="text-sm text-slate-800 dark:text-slate-200 font-medium">{{ $container->size_type?->label() ?? '—' }}</dd>
                        </div>
                        <div class="grid grid-cols-[150px_1fr] items-center py-3">
                            <dt class="text-xs font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider">Seal No.</dt>
                            <dd class="font-mono text-sm text-slate-800 dark:text-slate-200">{{ $container->seal_no ?? '—' }}</dd>
                        </div>
                        @if($container->arrival_seal_no)
                            <div class="grid grid-cols-[150px_1fr] items-center py-3">
                                <dt class="text-xs font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider">Arrival Seal No.</dt>
                                <dd class="flex items-center gap-2">
                                    <span class="font-mono text-sm text-slate-800 dark:text-slate-200">{{ $container->arrival_seal_no }}</span>
                                    @if($container->seal_verified)
                                        <span class="badge badge-green text-[10px]"><x-ui.icon name="check" class="w-3 h-3" /> Match</span>
                                    @else
                                        <span class="badge badge-red text-[10px]"><x-ui.icon name="x" class="w-3 h-3" /> Mismatch</span>
                                    @endif
                                </dd>
                            </div>
                        @endif
                        <div class="grid grid-cols-[150px_1fr] items-center py-3">
                            <dt class="text-xs font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider">Bill of Lading</dt>
                            <dd class="font-mono text-sm text-slate-800 dark:text-slate-200">{{ $container->bl_no ?? '—' }}</dd>
                        </div>
                        <div class="grid grid-cols-[150px_1fr] items-center py-3">
                            <dt class="text-xs font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider">Vessel / Voyage</dt>
                            <dd class="text-sm text-slate-800 dark:text-slate-200">
                                {{ $container->vessel ?? '—' }}
                                @if($container->voyage) <span class="text-slate-400 text-xs font-mono ml-1.5 px-1.5 py-0.5 rounded bg-slate-100 dark:bg-white/5">V.{{ $container->voyage }}</span> @endif
                            </dd>
                        </div>
                        <div class="grid grid-cols-[150px_1fr] items-center py-3">
                            <dt class="text-xs font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider">Shipping Line</dt>
                            <dd class="text-sm text-slate-800 dark:text-slate-200">{{ $container->shippingLinePartner?->name ?? '—' }}</dd>
                        </div>
                        <div class="grid grid-cols-[150px_1fr] items-center py-3">
                            <dt class="text-xs font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider">Shipper</dt>
                            <dd class="text-sm text-slate-800 dark:text-slate-200">{{ $container->shipperPartner?->name ?? '—' }}</dd>
                        </div>
                        <div class="grid grid-cols-[150px_1fr] items-center py-3">
                            <dt class="text-xs font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider">Consignee</dt>
                            <dd class="text-sm text-slate-800 dark:text-slate-200">{{ $container->consigneePartner?->name ?? '—' }}</dd>
                        </div>
                        @if($container->releasedToPartner)
                            <div class="grid grid-cols-[150px_1fr] items-center py-3">
                                <dt class="text-xs font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider">Released To</dt>
                                <dd class="text-sm text-slate-800 dark:text-slate-200 font-medium">{{ $container->releasedToPartner->name }}</dd>
                            </div>
                        @endif
                        <div class="grid grid-cols-[150px_1fr] items-center py-3">
                            <dt class="text-xs font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider">Internal #</dt>
                            <dd class="font-mono text-sm text-slate-500">{{ $container->number }}</dd>
                        </div>
                    </dl>
                </section>

                {{-- Schedule, Yard & Receiving --}}
                <div class="space-y-6">
                    <section class="card overflow-hidden border border-slate-200/80 dark:border-white/[0.08] shadow-sm rounded-xl bg-white dark:bg-zinc-900">
                        <div class="px-5 py-4 bg-slate-50/75 dark:bg-white/[0.02] border-b border-slate-200/60 dark:border-white/[0.06]">
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Schedule & Yard</h3>
                        </div>
                        <dl class="divide-y divide-slate-100 dark:divide-white/[0.05] px-5">
                            <div class="grid grid-cols-[150px_1fr] items-center py-3">
                                <dt class="text-xs font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider">ETA</dt>
                                <dd class="text-sm tabular-nums text-slate-800 dark:text-slate-200">{{ $container->eta?->format('d M Y') ?? '—' }}</dd>
                            </div>
                            <div class="grid grid-cols-[150px_1fr] items-center py-3">
                                <dt class="text-xs font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider">Arrived At</dt>
                                <dd class="text-sm tabular-nums text-slate-800 dark:text-slate-200">{{ $container->arrived_at?->format('d M Y H:i') ?? '—' }}</dd>
                            </div>
                            <div class="grid grid-cols-[150px_1fr] items-center py-3">
                                <dt class="text-xs font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider">Dwell</dt>
                                <dd class="flex items-center gap-2">
                                    @if($container->arrived_at)
                                        <span @class(['badge','badge-neutral'=>$dwellStatus==='ok','badge-amber'=>$dwellStatus==='warning','badge-red'=>$dwellStatus==='critical'])>{{ $dwell }}d</span>
                                        <span class="text-xs text-slate-500 font-medium">({{ $container->demurrageDays() }}d demurrage)</span>
                                    @else
                                        <span class="text-sm text-slate-400">—</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="grid grid-cols-[150px_1fr] items-center py-3">
                                <dt class="text-xs font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider">Free Days</dt>
                                <dd class="text-sm tabular-nums text-slate-800 dark:text-slate-200">{{ $container->free_days ?? '(default)' }}</dd>
                            </div>
                            <div class="grid grid-cols-[150px_1fr] items-center py-3">
                                <dt class="text-xs font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider">Yard Slot</dt>
                                <dd>
                                    @if($container->yardLocation)
                                        <span class="font-mono text-xs px-2 py-0.5 rounded bg-zinc-900/5 dark:bg-white/10 text-slate-800 dark:text-slate-200 font-medium">{{ $container->yardLocation->code }}</span>
                                    @else
                                        <span class="text-sm text-slate-400 italic">Not assigned</span>
                                    @endif
                                </dd>
                            </div>
                            @if($container->released_at)
                                <div class="grid grid-cols-[150px_1fr] items-center py-3">
                                    <dt class="text-xs font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider">Released At</dt>
                                    <dd class="text-sm tabular-nums text-slate-800 dark:text-slate-200">{{ $container->released_at->format('d M Y H:i') }}</dd>
                                </div>
                            @endif
                        </dl>
                    </section>

                    {{-- Receiving Info (shown once a GRN exists) --}}
                    @if($grn)
                        <section class="card overflow-hidden border border-slate-200/80 dark:border-white/[0.08] shadow-sm rounded-xl bg-white dark:bg-zinc-900">
                            <div class="px-5 py-4 bg-slate-50/75 dark:bg-white/[0.02] border-b border-slate-200/60 dark:border-white/[0.06] flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Receiving</h3>
                                <span class="badge badge-slate font-mono text-[10px]">{{ $grn->number }}</span>
                            </div>
                            <dl class="divide-y divide-slate-100 dark:divide-white/[0.05] px-5">
                                <div class="grid grid-cols-[150px_1fr] items-center py-3">
                                    <dt class="text-xs font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider">Receiving Dock</dt>
                                    <dd class="font-mono text-sm text-slate-800 dark:text-slate-200">{{ $grn->receivingDock?->code ?? '—' }}</dd>
                                </div>
                                <div class="grid grid-cols-[150px_1fr] items-center py-3">
                                    <dt class="text-xs font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider">Vehicle Type</dt>
                                    <dd class="text-sm text-slate-800 dark:text-slate-200">{{ $grn->vehicle_type ?? '—' }}</dd>
                                </div>
                                <div class="grid grid-cols-[150px_1fr] items-center py-3">
                                    <dt class="text-xs font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider">Vehicle Plate</dt>
                                    <dd class="font-mono text-sm text-slate-800 dark:text-slate-200">{{ $grn->vehicle_plate ?? '—' }}</dd>
                                </div>
                                <div class="grid grid-cols-[150px_1fr] items-center py-3">
                                    <dt class="text-xs font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider">Driver</dt>
                                    <dd class="text-sm text-slate-800 dark:text-slate-200">
                                        {{ $grn->driver_name ?? '—' }}
                                        @if($grn->driver_phone)
                                            <span class="text-slate-400 text-xs ml-1 font-mono">({{ $grn->driver_phone }})</span>
                                        @endif
                                    </dd>
                                </div>
                                <div class="grid grid-cols-[150px_1fr] items-center py-3">
                                    <dt class="text-xs font-medium text-slate-400 dark:text-slate-500 uppercase tracking-wider">Transporter</dt>
                                    <dd class="text-sm text-slate-800 dark:text-slate-200">{{ $grn->transporter_name ?? '—' }}</dd>
                                </div>
                            </dl>
                        </section>
                    @endif

                    {{-- Notes --}}
                    @if($container->notes)
                        <section class="card overflow-hidden border border-slate-200/80 dark:border-white/[0.08] shadow-sm rounded-xl bg-white dark:bg-zinc-900">
                            <div class="px-5 py-4 bg-slate-50/75 dark:bg-white/[0.02] border-b border-slate-200/60 dark:border-white/[0.06]">
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Notes</h3>
                            </div>
                            <div class="px-5 py-4">
                                <p class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-wrap leading-relaxed">{{ $container->notes }}</p>
                            </div>
                        </section>
                    @endif
                </div>

            </div>
        @endif

        {{-- ── Tab: Order Lines ────────────────────────────────────────────────────── --}}
        @if($activeTab === 'lines')
            @php $poLines = $container->purchaseOrder?->lines->load('item', 'uom') ?? collect(); @endphp
            <div class="space-y-4">
                <div>
                    <h3 class="font-semibold text-sm text-slate-900 dark:text-white">Order Lines</h3>
                    @if($container->purchaseOrder)
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            PO <span class="font-mono font-medium text-slate-700 dark:text-slate-300">{{ $container->purchaseOrder->number }}</span>
                            · {{ $poLines->count() }} lines
                        </p>
                    @endif
                </div>

                <div class="card overflow-hidden border border-slate-200/80 dark:border-white/[0.08] shadow-sm rounded-xl bg-white dark:bg-zinc-900">
                    <x-ui.table>
                        <thead>
                        <tr class="bg-slate-50/75 dark:bg-white/[0.02] border-b border-slate-200/60 dark:border-white/[0.06]">
                            <th class="w-12 py-3 px-4 font-semibold text-xs text-slate-400 uppercase tracking-wider">#</th>
                            <th class="py-3 px-4 font-semibold text-xs text-slate-400 uppercase tracking-wider">SKU</th>
                            <th class="py-3 px-4 font-semibold text-xs text-slate-400 uppercase tracking-wider">Description</th>
                            <th class="py-3 px-4 font-semibold text-xs text-slate-400 uppercase tracking-wider">Part No.</th>
                            <th class="py-3 px-4 font-semibold text-xs text-slate-400 uppercase tracking-wider">MIC No.</th>
                            <th class="py-3 px-4 font-semibold text-xs text-slate-400 uppercase tracking-wider text-right">Qty</th>
                            <th class="py-3 px-4 font-semibold text-xs text-slate-400 uppercase tracking-wider">UOM</th>
                            <th class="py-3 px-4 font-semibold text-xs text-slate-400 uppercase tracking-wider">Batch</th>
                            <th class="py-3 px-4 font-semibold text-xs text-slate-400 uppercase tracking-wider">Expiry</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/[0.05]">
                        @forelse($poLines->sortBy('line_no') as $line)
                            <tr wire:key="poline-{{ $line->id }}" class="hover:bg-slate-50/50 dark:hover:bg-white/[0.02] transition-colors">
                                <td class="py-3 px-4 text-slate-400 text-xs tabular-nums">{{ $line->line_no }}</td>
                                <td class="py-3 px-4 font-mono text-xs text-slate-800 dark:text-slate-200 font-medium">{{ $line->item?->sku ?? '—' }}</td>
                                <td class="py-3 px-4 text-sm text-slate-800 dark:text-slate-200 max-w-[200px] truncate">{{ $line->item?->name ?? '—' }}</td>
                                <td class="py-3 px-4 font-mono text-xs text-slate-500">{{ $line->part_number ?? '—' }}</td>
                                <td class="py-3 px-4 font-mono text-xs text-slate-500">{{ $line->mic_number ?? '—' }}</td>
                                <td class="py-3 px-4 text-right tabular-nums text-sm font-semibold text-slate-900 dark:text-white">{{ rtrim(rtrim(number_format((float)$line->qty, 4, '.', ','), '0'), '.') }}</td>
                                <td class="py-3 px-4 text-xs text-slate-500 font-medium">{{ $line->uom?->code ?? '—' }}</td>
                                <td class="py-3 px-4 font-mono text-xs text-slate-500">{{ $line->batch_no ?? '—' }}</td>
                                <td class="py-3 px-4 text-xs text-slate-500 tabular-nums">{{ $line->expiry_date?->format('d M Y') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-12">
                                    <x-ui.empty-state icon="list" title="No order lines" text="No purchase order is linked to this container." :cols="9" />
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </x-ui.table>
                </div>
            </div>
        @endif

    </div>

    {{-- ══════════════════════════════════════════════════════════════════════════════
         MODALS
    ══════════════════════════════════════════════════════════════════════════════ --}}

    {{-- Confirm Arrival Modal --}}
    @if($arrivalModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 backdrop-blur-sm p-4"
             x-data wire:click.self="$set('arrivalModalOpen', false)">
            <div class="card w-full max-w-md p-6 space-y-5 bg-white dark:bg-zinc-900 rounded-xl shadow-xl border border-slate-200 dark:border-white/10">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-base text-slate-900 dark:text-white">Confirm Arrival</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Record when the container arrived at the yard</p>
                    </div>
                    <button wire:click="$set('arrivalModalOpen', false)" class="icon-btn">
                        <x-ui.icon name="x" class="w-5 h-5" />
                    </button>
                </div>

                <div class="space-y-4" x-data="{
                manifestSeal: '{{ $container->seal_no }}',
                arrivalSeal: @entangle('arrivalSealNo'),
                get sealMatch() { return this.manifestSeal !== '' && this.arrivalSeal !== '' && this.manifestSeal === this.arrivalSeal; },
                get sealMismatch() { return this.manifestSeal !== '' && this.arrivalSeal !== '' && this.manifestSeal !== this.arrivalSeal; }
            }">
                    <div>
                        <label class="label font-medium text-xs text-slate-700 dark:text-slate-300 mb-1 block">Arrival Date & Time <span class="required-mark text-red-500">*</span></label>
                        <input wire:model="arrivedAt" type="datetime-local" class="input w-full tabular-nums">
                        @error('arrivedAt') <p class="hint hint-error text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label font-medium text-xs text-slate-700 dark:text-slate-300 mb-1 block">Arrival Seal Number <span class="required-mark text-red-500">*</span></label>
                        <input wire:model="arrivalSealNo" x-model="arrivalSeal" type="text" placeholder="Enter seal number at arrival" class="input w-full font-mono">
                        @if($container->seal_no)
                            <div class="mt-2 flex items-center gap-2 text-xs bg-slate-50 dark:bg-white/[0.03] p-2.5 rounded-lg border border-slate-200/60 dark:border-white/5">
                                <span class="text-slate-400">Manifest seal:</span>
                                <span class="font-mono font-medium text-slate-700 dark:text-slate-300">{{ $container->seal_no }}</span>
                                <span x-show="sealMatch" class="flex items-center gap-1 text-green-600 ml-auto font-medium">
                                <x-ui.icon name="check" class="w-3.5 h-3.5" /> Match
                            </span>
                                <span x-show="sealMismatch" class="flex items-center gap-1 text-red-500 ml-auto font-medium">
                                <x-ui.icon name="x" class="w-3.5 h-3.5" /> Mismatch — discrepancy created
                            </span>
                            </div>
                        @endif
                        @error('arrivalSealNo') <p class="hint hint-error text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-white/5">
                    <button wire:click="$set('arrivalModalOpen', false)" class="btn btn-ghost">Cancel</button>
                    <button wire:click="confirmArrival" wire:loading.attr="disabled" class="btn btn-primary shadow-sm">
                        Confirm Arrival
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Park Modal --}}
    @if($parkModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 backdrop-blur-sm p-4"
             x-data wire:click.self="$set('parkModalOpen', false)">
            <div class="card w-full max-w-sm p-6 space-y-5 bg-white dark:bg-zinc-900 rounded-xl shadow-xl border border-slate-200 dark:border-white/10">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-base text-slate-900 dark:text-white">Assign Yard Slot</h3>
                    <button wire:click="$set('parkModalOpen', false)" class="icon-btn">
                        <x-ui.icon name="x" class="w-5 h-5" />
                    </button>
                </div>
                <div>
                    <label class="label font-medium text-xs text-slate-700 dark:text-slate-300 mb-1 block">Select Free Yard Slot <span class="required-mark text-red-500">*</span></label>
                    <select wire:model="parkLocationId" class="input w-full">
                        <option value="">— Select slot —</option>
                        @foreach($this->freeYardSlots as $slot)
                            <option value="{{ $slot->id }}">{{ $slot->code }}</option>
                        @endforeach
                    </select>
                    @error('parkLocationId') <p class="hint hint-error text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    @if($this->freeYardSlots->isEmpty())
                        <p class="hint !text-amber-600 text-xs mt-1.5 font-medium flex items-center gap-1">
                            <x-ui.icon name="alert" class="w-3.5 h-3.5" /> No free yard slots available.
                        </p>
                    @endif
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-white/5">
                    <button wire:click="$set('parkModalOpen', false)" class="btn btn-ghost">Cancel</button>
                    <button wire:click="park" wire:loading.attr="disabled" class="btn btn-primary shadow-sm">
                        Assign Slot
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Move Slot Modal --}}
    @if($moveModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 backdrop-blur-sm p-4"
             x-data wire:click.self="$set('moveModalOpen', false)">
            <div class="card w-full max-w-sm p-6 space-y-5 bg-white dark:bg-zinc-900 rounded-xl shadow-xl border border-slate-200 dark:border-white/10">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-base text-slate-900 dark:text-white">Move Yard Slot</h3>
                    <button wire:click="$set('moveModalOpen', false)" class="icon-btn">
                        <x-ui.icon name="x" class="w-5 h-5" />
                    </button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="label font-medium text-xs text-slate-700 dark:text-slate-300 mb-1 block">New Yard Slot <span class="required-mark text-red-500">*</span></label>
                        <select wire:model="moveLocationId" class="input w-full">
                            <option value="">— Select slot —</option>
                            @foreach($this->freeYardSlots as $slot)
                                <option value="{{ $slot->id }}">{{ $slot->code }}</option>
                            @endforeach
                        </select>
                        @error('moveLocationId') <p class="hint hint-error text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label font-medium text-xs text-slate-700 dark:text-slate-300 mb-1 block">Reason <span class="required-mark text-red-500">*</span></label>
                        <input wire:model="moveReason" type="text" placeholder="e.g. Clear path for loading" class="input w-full">
                        @error('moveReason') <p class="hint hint-error text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-white/5">
                    <button wire:click="$set('moveModalOpen', false)" class="btn btn-ghost">Cancel</button>
                    <button wire:click="moveSlot" wire:loading.attr="disabled" class="btn btn-primary shadow-sm">
                        Move
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Release Modal --}}
    @if($releaseModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 backdrop-blur-sm p-4"
             x-data wire:click.self="$set('releaseModalOpen', false)">
            <div class="card w-full max-w-md p-6 space-y-5 bg-white dark:bg-zinc-900 rounded-xl shadow-xl border border-slate-200 dark:border-white/10">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-base text-slate-900 dark:text-white">Release Container</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Record the final release of this container</p>
                    </div>
                    <button wire:click="$set('releaseModalOpen', false)" class="icon-btn">
                        <x-ui.icon name="x" class="w-5 h-5" />
                    </button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="label font-medium text-xs text-slate-700 dark:text-slate-300 mb-1 block">Release Date & Time <span class="required-mark text-red-500">*</span></label>
                        <input wire:model="releasedAt" type="datetime-local" class="input w-full tabular-nums">
                        @error('releasedAt') <p class="hint hint-error text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label font-medium text-xs text-slate-700 dark:text-slate-300 mb-1 block">Released To <span class="required-mark text-red-500">*</span></label>
                        <select wire:model="releasedToPartnerId" class="input w-full">
                            <option value="">— Select partner —</option>
                            @foreach($this->releasePartners as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                        @error('releasedToPartnerId') <p class="hint hint-error text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label font-medium text-xs text-slate-700 dark:text-slate-300 mb-1 block">Notes</label>
                        <textarea wire:model="releaseNotes" rows="2" class="input w-full" placeholder="Optional release notes…"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-white/5">
                    <button wire:click="$set('releaseModalOpen', false)" class="btn btn-ghost">Cancel</button>
                    <button wire:click="release" wire:loading.attr="disabled" class="btn btn-primary shadow-sm">
                        Release
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Cancel Modal --}}
    @if($cancelModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 backdrop-blur-sm p-4"
             x-data wire:click.self="$set('cancelModalOpen', false)">
            <div class="card w-full max-w-sm p-6 space-y-5 bg-white dark:bg-zinc-900 rounded-xl shadow-xl border border-slate-200 dark:border-white/10">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-base text-slate-900 dark:text-white">Cancel Container</h3>
                    <button wire:click="$set('cancelModalOpen', false)" class="icon-btn">
                        <x-ui.icon name="x" class="w-5 h-5" />
                    </button>
                </div>
                <div class="flex gap-3 p-3.5 rounded-lg bg-red-50 dark:bg-red-500/10 border border-red-200/50 dark:border-red-500/20">
                    <x-ui.icon name="alert" class="w-5 h-5 text-red-600 dark:text-red-400 shrink-0 mt-0.5" />
                    <p class="text-xs text-red-700 dark:text-red-300 leading-relaxed">This action cannot be undone. The container will be marked as cancelled.</p>
                </div>
                <div>
                    <label class="label font-medium text-xs text-slate-700 dark:text-slate-300 mb-1 block">Reason <span class="required-mark text-red-500">*</span></label>
                    <textarea wire:model="cancelReason" rows="3" class="input w-full" placeholder="Minimum 10 characters required…"></textarea>
                    @error('cancelReason') <p class="hint hint-error text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-white/5">
                    <button wire:click="$set('cancelModalOpen', false)" class="btn btn-ghost">Keep Container</button>
                    <button wire:click="cancel" wire:loading.attr="disabled" class="btn btn-primary bg-red-600 hover:bg-red-700 text-white shadow-sm">
                        Cancel Container
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Add/Edit Line Modal --}}
    @if($lineModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 backdrop-blur-sm p-4"
             x-data wire:click.self="$set('lineModalOpen', false)">
            <div class="card w-full max-w-lg p-6 space-y-5 bg-white dark:bg-zinc-900 rounded-xl shadow-xl border border-slate-200 dark:border-white/10 overflow-y-auto max-h-[90vh]">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-base text-slate-900 dark:text-white">{{ $editingLineId ? 'Edit Line' : 'Add Expected Line' }}</h3>
                    <button wire:click="$set('lineModalOpen', false)" class="icon-btn">
                        <x-ui.icon name="x" class="w-5 h-5" />
                    </button>
                </div>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label font-medium text-xs text-slate-700 dark:text-slate-300 mb-1 block">Raw SKU</label>
                            <input wire:model="lineRawSku" type="text" class="input w-full font-mono" placeholder="e.g. SKU-001">
                        </div>
                        <div>
                            <label class="label font-medium text-xs text-slate-700 dark:text-slate-300 mb-1 block">Map to Item</label>
                            <select wire:model="lineItemId" class="input w-full">
                                <option value="">— Unmapped —</option>
                                @foreach($this->items as $item)
                                    <option value="{{ $item->id }}">{{ $item->sku }} – {{ $item->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="label font-medium text-xs text-slate-700 dark:text-slate-300 mb-1 block">Description</label>
                        <input wire:model="lineRawDesc" type="text" class="input w-full" placeholder="Product description">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label font-medium text-xs text-slate-700 dark:text-slate-300 mb-1 block">Qty <span class="required-mark text-red-500">*</span></label>
                            <input wire:model="lineQty" type="number" step="0.0001" min="0" class="input w-full tabular-nums">
                            @error('lineQty') <p class="hint hint-error text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label font-medium text-xs text-slate-700 dark:text-slate-300 mb-1 block">UOM</label>
                            <select wire:model="lineUomId" class="input w-full">
                                <option value="">— Select —</option>
                                @foreach($this->uoms as $uom)
                                    <option value="{{ $uom->id }}">{{ $uom->code }} – {{ $uom->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label font-medium text-xs text-slate-700 dark:text-slate-300 mb-1 block">Batch No.</label>
                            <input wire:model="lineBatchNo" type="text" class="input w-full font-mono" placeholder="BATCH-001">
                        </div>
                        <div>
                            <label class="label font-medium text-xs text-slate-700 dark:text-slate-300 mb-1 block">Expiry Date</label>
                            <input wire:model="lineExpiry" type="date" class="input w-full tabular-nums">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label font-medium text-xs text-slate-700 dark:text-slate-300 mb-1 block">Weight (kg)</label>
                            <input wire:model="lineWeightKg" type="number" step="0.001" min="0" class="input w-full tabular-nums">
                        </div>
                        <div>
                            <label class="label font-medium text-xs text-slate-700 dark:text-slate-300 mb-1 block">Virtual Warehouse <span class="required-mark text-red-500">*</span></label>
                            <select wire:model="lineVwId" class="input w-full">
                                <option value="">— Select VW —</option>
                                @foreach($this->virtualWarehouses as $vw)
                                    <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                                @endforeach
                            </select>
                            @error('lineVwId') <p class="hint hint-error text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="label font-medium text-xs text-slate-700 dark:text-slate-300 mb-1 block">Notes</label>
                        <input wire:model="lineNotes" type="text" class="input w-full" placeholder="Line notes…">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-white/5">
                    <button wire:click="$set('lineModalOpen', false)" class="btn btn-ghost">Cancel</button>
                    <button wire:click="saveLine" wire:loading.attr="disabled" class="btn btn-primary shadow-sm">Save Line</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Upload Document Modal --}}
    @if($docModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 backdrop-blur-sm p-4"
             x-data wire:click.self="$set('docModalOpen', false)">
            <div class="card w-full max-w-md p-6 space-y-5 bg-white dark:bg-zinc-900 rounded-xl shadow-xl border border-slate-200 dark:border-white/10">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-base text-slate-900 dark:text-white">Upload Document</h3>
                    <button wire:click="$set('docModalOpen', false)" class="icon-btn">
                        <x-ui.icon name="x" class="w-5 h-5" />
                    </button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="label font-medium text-xs text-slate-700 dark:text-slate-300 mb-1 block">Document Type <span class="required-mark text-red-500">*</span></label>
                        <select wire:model="docType" class="input w-full">
                            @foreach(\App\Enums\ContainerDocumentType::cases() as $dt)
                                <option value="{{ $dt->value }}">{{ $dt->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label font-medium text-xs text-slate-700 dark:text-slate-300 mb-1 block">File <span class="required-mark text-red-500">*</span></label>
                        <div class="relative border-2 border-dashed border-slate-300 dark:border-zinc-700 rounded-xl py-6 px-4 text-center cursor-pointer hover:border-simba-500 dark:hover:border-simba-400 transition-colors bg-slate-50/50 dark:bg-white/[0.01]"
                             onclick="this.querySelector('input[type=file]').click()">
                            <x-ui.icon name="upload" class="w-8 h-8 mx-auto text-slate-400 mb-2" wire:loading.remove wire:target="docFile" />
                            <x-ui.icon name="refresh" class="w-8 h-8 mx-auto text-simba-600 animate-spin hidden mb-2" wire:loading wire:target="docFile" />
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Drop file here or click to browse</p>
                            <p class="text-xs text-slate-400 mt-1">Maximum file size: 20 MB</p>
                            <input type="file" wire:model="docFile" class="sr-only" />
                        </div>
                        @if($docFile)
                            <p class="text-xs text-green-600 dark:text-green-400 mt-2 font-medium flex items-center gap-1.5 bg-green-50 dark:bg-green-500/10 p-2 rounded-lg">
                                <x-ui.icon name="check" class="w-3.5 h-3.5" /> {{ is_object($docFile) ? $docFile->getClientOriginalName() : 'File selected' }}
                            </p>
                        @endif
                        @error('docFile') <p class="hint hint-error text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-white/5">
                    <button wire:click="$set('docModalOpen', false)" class="btn btn-ghost">Cancel</button>
                    <button wire:click="uploadDocument" wire:loading.attr="disabled" class="btn btn-primary shadow-sm">Upload</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Add/Edit Discrepancy Modal --}}
    @if($discModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 backdrop-blur-sm p-4"
             x-data wire:click.self="$set('discModalOpen', false)">
            <div class="card w-full max-w-sm p-6 space-y-5 bg-white dark:bg-zinc-900 rounded-xl shadow-xl border border-slate-200 dark:border-white/10">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-base text-slate-900 dark:text-white">{{ $editingDiscId ? 'Edit Discrepancy' : 'Report Discrepancy' }}</h3>
                    <button wire:click="$set('discModalOpen', false)" class="icon-btn">
                        <x-ui.icon name="x" class="w-5 h-5" />
                    </button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="label font-medium text-xs text-slate-700 dark:text-slate-300 mb-1 block">Type <span class="required-mark text-red-500">*</span></label>
                        <select wire:model="discType" class="input w-full">
                            @foreach(\App\Enums\ContainerDiscrepancyType::cases() as $dt)
                                <option value="{{ $dt->value }}">{{ $dt->label() }}</option>
                            @endforeach
                        </select>
                        @error('discType') <p class="hint hint-error text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label font-medium text-xs text-slate-700 dark:text-slate-300 mb-1 block">Description <span class="required-mark text-red-500">*</span></label>
                        <textarea wire:model="discDescription" rows="3" class="input w-full" placeholder="Describe the discrepancy (min 5 chars)…"></textarea>
                        @error('discDescription') <p class="hint hint-error text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-white/5">
                    <button wire:click="$set('discModalOpen', false)" class="btn btn-ghost">Cancel</button>
                    <button wire:click="saveDiscrepancy" wire:loading.attr="disabled" class="btn btn-primary shadow-sm">Save</button>
                </div>
            </div>
        </div>
    @endif

    @include('livewire.inbound.containers._packing-list-wizard')
</div>
