@php
    $dwell       = $container->dwellDays();
    $dwellStatus = $container->dwellStatus();
    $status      = $container->status;
@endphp

<div>
<div class="space-y-6">

    {{-- ── Page Header ─────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col gap-5 pb-6 sm:flex-row sm:items-start sm:justify-between border-b border-white/40 dark:border-white/[0.08]">
        <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
                <a href="{{ route('inbound.containers.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-simba-600">
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
            <p class="mt-1 text-xs text-slate-400 font-mono">{{ $container->number }} &middot; {{ $container->size_type?->label() }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2 shrink-0">
            @if($status === \App\Enums\ContainerStatus::Expected)
                @can('arrive', $container)
                    <button wire:click="$set('arrivalModalOpen', true)" class="btn btn-primary">
                        <x-ui.icon name="check" class="w-4 h-4" /> Confirm Arrival
                    </button>
                @endcan
                @can('update', $container)
                    <a href="{{ route('inbound.containers.edit', $container) }}" wire:navigate class="btn btn-secondary">
                        <x-ui.icon name="pencil" class="w-4 h-4" /> Edit
                    </a>
                @endcan
                @can('cancel', $container)
                    <button wire:click="$set('cancelModalOpen', true)" class="btn btn-ghost !text-red-600">
                        <x-ui.icon name="x" class="w-4 h-4" /> Cancel
                    </button>
                @endcan
            @elseif($status === \App\Enums\ContainerStatus::Arrived)
                @can('park', $container)
                    <button wire:click="$set('parkModalOpen', true)" class="btn btn-primary">
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
                    <button wire:click="$set('moveModalOpen', true)" class="btn btn-secondary">
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
                    <button wire:click="markUnloaded" class="btn btn-primary">
                        <x-ui.icon name="check" class="w-4 h-4" /> Mark as Unloaded
                    </button>
                @endcan
            @elseif($status === \App\Enums\ContainerStatus::Unloaded)
                @can('release', $container)
                    <button wire:click="$set('releaseModalOpen', true)" class="btn btn-primary">
                        <x-ui.icon name="truck" class="w-4 h-4" /> Release Container
                    </button>
                @endcan
            @endif
        </div>
    </div>

    {{-- ── Tab Navigation ───────────────────────────────────────────────────────── --}}
    <div class="tabs border-b border-white/40 dark:border-white/[0.08] -mb-6 pb-0">
        @foreach([
            ['overview',     'Overview'],
            ['lines',        'Expected Lines'],
            ['documents',    'Documents'],
            ['discrepancies','Discrepancies'],
            ['history',      'History'],
            ['variance',     'Received & Variance'],
        ] as [$tab, $label])
            <button wire:click="$set('activeTab','{{ $tab }}')" @class(['tab','active' => $activeTab === $tab])>
                {{ $label }}
                @if($tab === 'discrepancies' && $container->discrepancies->where('resolved_at', null)->count() > 0)
                    <span class="badge badge-red ml-1 text-[10px]">{{ $container->discrepancies->where('resolved_at', null)->count() }}</span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- ── Tab: Overview ───────────────────────────────────────────────────────── --}}
    @if($activeTab === 'overview')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Container Details --}}
            <section class="card overflow-hidden">
                <div class="card-header py-4">
                    <h3 class="card-title">Container Details</h3>
                </div>
                <dl class="divide-y divide-white/50 dark:divide-white/[0.06] px-5">
                    <div class="grid grid-cols-[180px_1fr] items-center py-3.5">
                        <dt class="section-label">Container No.</dt>
                        <dd class="font-mono font-semibold text-sm">{{ $container->container_no }}</dd>
                    </div>
                    <div class="grid grid-cols-[180px_1fr] items-center py-3.5">
                        <dt class="section-label">Seal No. (Manifest)</dt>
                        <dd class="font-mono text-sm text-slate-700 dark:text-slate-300">{{ $container->seal_no ?? '—' }}</dd>
                    </div>
                    @if($container->arrival_seal_no)
                        <div class="grid grid-cols-[180px_1fr] items-center py-3.5">
                            <dt class="section-label">Arrival Seal No.</dt>
                            <dd class="flex items-center gap-2">
                                <span class="font-mono text-sm text-slate-700 dark:text-slate-300">{{ $container->arrival_seal_no }}</span>
                                @if($container->seal_verified)
                                    <span class="badge badge-green text-[10px]">
                                        <x-ui.icon name="check" class="w-3 h-3" /> Match
                                    </span>
                                @else
                                    <span class="badge badge-red text-[10px]">
                                        <x-ui.icon name="x" class="w-3 h-3" /> Mismatch
                                    </span>
                                @endif
                            </dd>
                        </div>
                    @endif
                    <div class="grid grid-cols-[180px_1fr] items-center py-3.5">
                        <dt class="section-label">Size / Type</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">{{ $container->size_type?->label() }}</dd>
                    </div>
                    <div class="grid grid-cols-[180px_1fr] items-center py-3.5">
                        <dt class="section-label">Internal #</dt>
                        <dd class="font-mono text-sm text-slate-500">{{ $container->number }}</dd>
                    </div>
                </dl>
            </section>

            {{-- Shipping Section --}}
            <section class="card overflow-hidden">
                <div class="card-header py-4">
                    <h3 class="card-title">Shipping</h3>
                </div>
                <dl class="divide-y divide-white/50 dark:divide-white/[0.06] px-5">
                    <div class="grid grid-cols-[180px_1fr] items-center py-3.5">
                        <dt class="section-label">Bill of Lading</dt>
                        <dd class="font-mono text-sm text-slate-700 dark:text-slate-300">{{ $container->bl_no ?? '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-[180px_1fr] items-center py-3.5">
                        <dt class="section-label">Vessel</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">{{ $container->vessel ?? '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-[180px_1fr] items-center py-3.5">
                        <dt class="section-label">Voyage</dt>
                        <dd class="font-mono text-sm text-slate-700 dark:text-slate-300">{{ $container->voyage ? 'V.'.$container->voyage : '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-[180px_1fr] items-center py-3.5">
                        <dt class="section-label">Shipping Line</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">{{ $container->shippingLinePartner?->name ?? '—' }}</dd>
                    </div>
                </dl>
            </section>

            {{-- Partners Section --}}
            <section class="card overflow-hidden">
                <div class="card-header py-4">
                    <h3 class="card-title">Partners</h3>
                </div>
                <dl class="divide-y divide-white/50 dark:divide-white/[0.06] px-5">
                    <div class="grid grid-cols-[180px_1fr] items-center py-3.5">
                        <dt class="section-label">Shipper</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">{{ $container->shipperPartner?->name ?? '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-[180px_1fr] items-center py-3.5">
                        <dt class="section-label">Consignee</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">{{ $container->consigneePartner?->name ?? '—' }}</dd>
                    </div>
                    @if($container->releasedToPartner)
                        <div class="grid grid-cols-[180px_1fr] items-center py-3.5">
                            <dt class="section-label">Released To</dt>
                            <dd class="text-sm text-slate-700 dark:text-slate-300">{{ $container->releasedToPartner->name }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            {{-- Schedule + Yard Section --}}
            <section class="card overflow-hidden">
                <div class="card-header py-4">
                    <h3 class="card-title">Schedule & Yard</h3>
                </div>
                <dl class="divide-y divide-white/50 dark:divide-white/[0.06] px-5">
                    <div class="grid grid-cols-[180px_1fr] items-center py-3.5">
                        <dt class="section-label">ETA</dt>
                        <dd class="text-sm tabular-nums text-slate-700 dark:text-slate-300">{{ $container->eta?->format('d M Y') ?? '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-[180px_1fr] items-center py-3.5">
                        <dt class="section-label">Arrived At</dt>
                        <dd class="text-sm tabular-nums text-slate-700 dark:text-slate-300">{{ $container->arrived_at?->format('d M Y H:i') ?? '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-[180px_1fr] items-center py-3.5">
                        <dt class="section-label">Free Days</dt>
                        <dd class="text-sm tabular-nums text-slate-700 dark:text-slate-300">{{ $container->free_days ?? '(default)' }}</dd>
                    </div>
                    <div class="grid grid-cols-[180px_1fr] items-center py-3.5">
                        <dt class="section-label">Dwell Days</dt>
                        <dd>
                            @if($container->arrived_at)
                                <span @class([
                                    'badge',
                                    'badge-neutral' => $dwellStatus === 'ok',
                                    'badge-amber'   => $dwellStatus === 'warning',
                                    'badge-red'     => $dwellStatus === 'critical',
                                ])>{{ $dwell }}d</span>
                            @else
                                <span class="text-sm text-slate-400">—</span>
                            @endif
                        </dd>
                    </div>
                    <div class="grid grid-cols-[180px_1fr] items-center py-3.5">
                        <dt class="section-label">Demurrage Days</dt>
                        <dd class="text-sm tabular-nums text-slate-700 dark:text-slate-300">
                            @if($container->arrived_at)
                                {{ $container->demurrageDays() }}d
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="grid grid-cols-[180px_1fr] items-center py-3.5">
                        <dt class="section-label">Yard Slot</dt>
                        <dd>
                            @if($container->yardLocation)
                                <span class="font-mono text-xs px-2 py-0.5 rounded-md bg-zinc-900/5 dark:bg-white/10 text-slate-700 dark:text-slate-200">{{ $container->yardLocation->code }}</span>
                            @else
                                <span class="text-sm text-slate-400 italic">Not assigned</span>
                            @endif
                        </dd>
                    </div>
                    @if($container->released_at)
                        <div class="grid grid-cols-[180px_1fr] items-center py-3.5">
                            <dt class="section-label">Released At</dt>
                            <dd class="text-sm tabular-nums text-slate-700 dark:text-slate-300">{{ $container->released_at->format('d M Y H:i') }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            {{-- Notes --}}
            @if($container->notes)
                <section class="card overflow-hidden lg:col-span-2">
                    <div class="card-header py-4">
                        <h3 class="card-title">Notes</h3>
                    </div>
                    <div class="px-5 py-4">
                        <p class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-wrap">{{ $container->notes }}</p>
                    </div>
                </section>
            @endif
        </div>
    @endif

    {{-- ── Tab: Expected Lines ──────────────────────────────────────────────────── --}}
    @if($activeTab === 'lines')
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white">Expected Lines</h3>
                    <p class="text-xs text-slate-500 mt-0.5">{{ $container->expectedLines->count() }} lines · {{ $container->expectedLines->sum('qty') }} total qty</p>
                </div>
                @if($container->linesEditable())
                    <div class="flex items-center gap-2">
                        @can('update', $container)
                            <button wire:click="openPackingListWizard" class="btn btn-secondary">
                                <x-ui.icon name="upload" class="w-4 h-4" /> Import packing list
                            </button>
                        @endcan
                        <button wire:click="openLineModal()" class="btn btn-primary">
                            <x-ui.icon name="plus" class="w-4 h-4" /> Add Line
                        </button>
                    </div>
                @endif
            </div>

            <div class="card overflow-hidden">
                <x-ui.table>
                    <thead>
                        <tr>
                            <th class="w-12">#</th>
                            <th>SKU / Raw SKU</th>
                            <th>Description</th>
                            <th>Qty</th>
                            <th>UOM</th>
                            <th>Batch</th>
                            <th>Expiry</th>
                            <th>Virtual WH</th>
                            <th>Mapped</th>
                            @if($container->linesEditable())
                                <th class="w-20"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($container->expectedLines->sortBy('line_no') as $line)
                            <tr wire:key="line-{{ $line->id }}">
                                <td class="text-slate-400 text-xs tabular-nums">{{ $line->line_no }}</td>
                                <td class="font-mono text-xs text-slate-700 dark:text-slate-300">
                                    {{ $line->item?->sku ?? $line->raw_sku ?? '—' }}
                                </td>
                                <td class="text-sm text-slate-700 dark:text-slate-300 max-w-[200px] truncate">
                                    {{ $line->item?->name ?? $line->raw_description ?? '—' }}
                                </td>
                                <td class="tabular-nums text-sm">{{ number_format((float)$line->qty, 2) }}</td>
                                <td class="text-xs text-slate-500">{{ $line->uom?->code ?? '—' }}</td>
                                <td class="font-mono text-xs text-slate-500">{{ $line->batch_no ?? '—' }}</td>
                                <td class="text-xs text-slate-500 tabular-nums">{{ $line->expiry_date?->format('d M Y') ?? '—' }}</td>
                                <td class="text-xs text-slate-600 dark:text-slate-400">{{ $line->virtualWarehouse?->name ?? '—' }}</td>
                                <td>
                                    @if($line->isMapped())
                                        <x-ui.icon name="check" class="w-4 h-4 text-green-600" />
                                    @else
                                        <span class="text-slate-300 dark:text-slate-600">—</span>
                                    @endif
                                </td>
                                @if($container->linesEditable())
                                    <td>
                                        <div class="flex items-center gap-1">
                                            <button wire:click="openLineModal({{ $line->id }})" class="icon-btn" title="Edit">
                                                <x-ui.icon name="pencil" class="w-4 h-4" />
                                            </button>
                                            <button wire:click="deleteLine({{ $line->id }})" wire:confirm="Remove this line?" class="icon-btn !text-red-500 hover:!text-red-700" title="Remove">
                                                <x-ui.icon name="x" class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <x-ui.empty-state icon="list" title="No expected lines" text="Add lines from the packing list before unloading." :cols="$container->linesEditable() ? 10 : 9" />
                        @endforelse
                    </tbody>
                </x-ui.table>
            </div>

            {{-- Totals per VW --}}
            @if($container->expectedLines->count() > 0)
                <div class="card p-4">
                    <h4 class="text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wide mb-3">Totals by Virtual Warehouse</h4>
                    <div class="flex flex-wrap gap-4">
                        @foreach($container->expectedLines->groupBy('virtual_warehouse_id') as $vwId => $lines)
                            <div class="text-sm">
                                <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $lines->first()->virtualWarehouse?->name ?? 'Unassigned' }}</span>
                                <span class="text-slate-500 ml-2">{{ number_format($lines->sum('qty'), 2) }} units · {{ $lines->count() }} lines</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- ── Tab: Documents ───────────────────────────────────────────────────────── --}}
    @if($activeTab === 'documents')
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-slate-900 dark:text-white">Documents</h3>
                @can('uploadDocument', $container)
                    <button wire:click="$set('docModalOpen', true)" class="btn btn-primary">
                        <x-ui.icon name="upload" class="w-4 h-4" /> Upload Document
                    </button>
                @endcan
            </div>

            <div class="card overflow-hidden">
                <x-ui.table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>File Name</th>
                            <th>Size</th>
                            <th>Uploaded By</th>
                            <th>Date</th>
                            <th class="w-20"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($container->documents->sortByDesc('created_at') as $doc)
                            <tr wire:key="doc-{{ $doc->id }}">
                                <td>
                                    <span class="badge badge-slate text-[10px]">{{ $doc->type->label() }}</span>
                                </td>
                                <td class="font-mono text-xs text-slate-700 dark:text-slate-300 max-w-[240px] truncate" title="{{ $doc->original_name }}">
                                    {{ $doc->original_name }}
                                </td>
                                <td class="text-xs text-slate-500 tabular-nums">{{ $doc->sizeHuman() }}</td>
                                <td class="text-sm text-slate-500">{{ $doc->uploadedBy?->name ?? '—' }}</td>
                                <td class="text-xs text-slate-500 tabular-nums">{{ $doc->created_at->format('d M Y H:i') }}</td>
                                <td>
                                    <div class="flex items-center gap-1">
                                        <a href="{{ route('containers.documents.download', [$container, $doc]) }}"
                                           class="icon-btn" title="View" target="_blank">
                                            <x-ui.icon name="eye" class="w-4 h-4" />
                                        </a>
                                        <a href="{{ route('containers.documents.download', [$container, $doc]) . '?download=1' }}"
                                           class="icon-btn" title="Download">
                                            <x-ui.icon name="download" class="w-4 h-4" />
                                        </a>
                                        @can('deleteDocument', $container)
                                            <button wire:click="deleteDocument({{ $doc->id }})" wire:confirm="Delete this document?" class="icon-btn !text-red-500" title="Delete">
                                                <x-ui.icon name="x" class="w-4 h-4" />
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <x-ui.empty-state icon="doc" title="No documents" text="Upload a Bill of Lading, Packing List, or other supporting documents." :cols="6" />
                        @endforelse
                    </tbody>
                </x-ui.table>
            </div>
        </div>
    @endif

    {{-- ── Tab: Discrepancies ───────────────────────────────────────────────────── --}}
    @if($activeTab === 'discrepancies')
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-slate-900 dark:text-white">Discrepancies</h3>
                @can('addDiscrepancy', $container)
                    <button wire:click="openDiscModal()" class="btn btn-primary">
                        <x-ui.icon name="plus" class="w-4 h-4" /> Add Discrepancy
                    </button>
                @endcan
            </div>

            <div class="card overflow-hidden">
                <x-ui.table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Reported By</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="w-32"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($container->discrepancies->sortByDesc('created_at') as $disc)
                            <tr wire:key="disc-{{ $disc->id }}">
                                <td>
                                    <span class="badge badge-slate text-[10px]">{{ $disc->type->label() }}</span>
                                </td>
                                <td class="text-sm text-slate-700 dark:text-slate-300 max-w-[300px]">{{ $disc->description }}</td>
                                <td class="text-sm text-slate-500">{{ $disc->reportedBy?->name ?? '—' }}</td>
                                <td class="text-xs text-slate-500 tabular-nums">{{ $disc->created_at->format('d M Y') }}</td>
                                <td>
                                    @if($disc->isResolved())
                                        <span class="badge badge-green text-[10px]">Resolved</span>
                                    @else
                                        <span class="badge badge-red text-[10px]">Open</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex items-center gap-1">
                                        @if(!$disc->isResolved())
                                            @can('resolveDiscrepancy', $container)
                                                <button wire:click="resolveDiscrepancy({{ $disc->id }})" class="btn btn-ghost text-xs px-2 py-1 h-auto">
                                                    Resolve
                                                </button>
                                            @endcan
                                        @else
                                            <span class="text-xs text-slate-400" title="{{ $disc->resolution_notes }}">
                                                by {{ $disc->resolvedBy?->name ?? '?' }}
                                            </span>
                                        @endif
                                        @can('addDiscrepancy', $container)
                                            @if(!$disc->isResolved())
                                                <button wire:click="openDiscModal({{ $disc->id }})" class="icon-btn" title="Edit">
                                                    <x-ui.icon name="pencil" class="w-4 h-4" />
                                                </button>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <x-ui.empty-state icon="check" title="No discrepancies" text="No issues have been reported for this container." :cols="6" />
                        @endforelse
                    </tbody>
                </x-ui.table>
            </div>
        </div>
    @endif

    {{-- ── Tab: History ─────────────────────────────────────────────────────────── --}}
    @if($activeTab === 'history')
        <div class="space-y-4">
            <h3 class="font-semibold text-slate-900 dark:text-white">Status History</h3>

            @if($container->statusHistory->count() > 0 || $container->yardMoves->count() > 0)
                <div class="card overflow-hidden">
                    <div class="divide-y divide-white/40 dark:divide-white/[0.08]">
                        @foreach($container->statusHistory->sortByDesc('created_at') as $h)
                            <div class="flex items-start gap-4 px-5 py-4">
                                <div class="flex-none w-8 h-8 rounded-full bg-simba-50 dark:bg-simba-500/15 flex items-center justify-center">
                                    <x-ui.icon name="refresh" class="w-4 h-4 text-simba-600 dark:text-simba-400" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200">
                                        Status changed:
                                        @if($h->from_status)
                                            <span class="badge {{ $h->from_status->color() }} text-[10px]">{{ $h->from_status->label() }}</span>
                                            <x-ui.icon name="arrow-right" class="w-3.5 h-3.5 inline text-slate-400" />
                                        @endif
                                        <span class="badge {{ $h->to_status->color() }} text-[10px]">{{ $h->to_status->label() }}</span>
                                    </p>
                                    @if($h->notes)
                                        <p class="text-xs text-slate-500 mt-0.5">{{ $h->notes }}</p>
                                    @endif
                                    <p class="text-xs text-slate-400 mt-1">{{ $h->user?->name ?? 'System' }} &middot; {{ $h->created_at->format('d M Y H:i') }}</p>
                                </div>
                            </div>
                        @endforeach
                        @foreach($container->yardMoves->sortByDesc('created_at') as $move)
                            <div class="flex items-start gap-4 px-5 py-4">
                                <div class="flex-none w-8 h-8 rounded-full bg-amber-50 dark:bg-amber-500/15 flex items-center justify-center">
                                    <x-ui.icon name="arrows" class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200">
                                        Yard move:
                                        <span class="font-mono text-xs">{{ $move->fromLocation?->code ?? '?' }}</span>
                                        <x-ui.icon name="arrow-right" class="w-3.5 h-3.5 inline text-slate-400" />
                                        <span class="font-mono text-xs">{{ $move->toLocation?->code ?? '?' }}</span>
                                    </p>
                                    @if($move->reason)
                                        <p class="text-xs text-slate-500 mt-0.5">{{ $move->reason }}</p>
                                    @endif
                                    <p class="text-xs text-slate-400 mt-1">{{ $move->user?->name ?? 'System' }} &middot; {{ $move->created_at->format('d M Y H:i') }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="card p-8 text-center">
                    <x-ui.icon name="refresh" class="w-8 h-8 mx-auto text-slate-300 dark:text-slate-600" />
                    <p class="mt-3 text-sm font-medium text-slate-700 dark:text-slate-300">No history yet</p>
                    <p class="text-xs text-slate-400 mt-1">Status changes and yard moves will appear here.</p>
                </div>
            @endif
        </div>
    @endif

    {{-- ── Tab: Received & Variance ─────────────────────────────────────────────── --}}
    @if($activeTab === 'variance')
        <div class="card p-8 text-center">
            <x-ui.icon name="archive" class="w-10 h-10 mx-auto text-slate-300 dark:text-slate-600" />
            <p class="mt-4 text-sm font-medium text-slate-700 dark:text-slate-300">Receiving data not yet available</p>
            <p class="text-xs text-slate-400 mt-1.5 max-w-sm mx-auto">Receiving data and variance reports will be available here once Module 4 (Receiving) is configured and goods have been received against this container.</p>
        </div>
    @endif

</div>

{{-- ══════════════════════════════════════════════════════════════════════════════
     MODALS
══════════════════════════════════════════════════════════════════════════════ --}}

{{-- Confirm Arrival Modal --}}
@if($arrivalModalOpen)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm"
         x-data wire:click.self="$set('arrivalModalOpen', false)">
        <div class="card w-full max-w-md p-6 space-y-5">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-900 dark:text-white">Confirm Arrival</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Record when the container arrived at the yard</p>
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
                    <label class="label">Arrival Date & Time <span class="required-mark">*</span></label>
                    <input wire:model="arrivedAt" type="datetime-local" class="input w-full tabular-nums">
                    @error('arrivedAt') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Arrival Seal Number <span class="required-mark">*</span></label>
                    <input wire:model="arrivalSealNo" x-model="arrivalSeal" type="text" placeholder="Enter seal number at arrival" class="input w-full font-mono">
                    @if($container->seal_no)
                        <div class="mt-1.5 flex items-center gap-2 text-xs">
                            <span class="text-slate-400">Manifest seal:</span>
                            <span class="font-mono text-slate-600 dark:text-slate-300">{{ $container->seal_no }}</span>
                            <span x-show="sealMatch" class="flex items-center gap-1 text-green-600">
                                <x-ui.icon name="check" class="w-3.5 h-3.5" /> Match
                            </span>
                            <span x-show="sealMismatch" class="flex items-center gap-1 text-red-500">
                                <x-ui.icon name="x" class="w-3.5 h-3.5" /> Mismatch — discrepancy will be created
                            </span>
                        </div>
                    @endif
                    @error('arrivalSealNo') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-1">
                <button wire:click="$set('arrivalModalOpen', false)" class="btn btn-ghost">Cancel</button>
                <button wire:click="confirmArrival" wire:loading.attr="disabled" class="btn btn-primary">
                    Confirm Arrival
                </button>
            </div>
        </div>
    </div>
@endif

{{-- Park Modal --}}
@if($parkModalOpen)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm"
         x-data wire:click.self="$set('parkModalOpen', false)">
        <div class="card w-full max-w-sm p-6 space-y-5">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-slate-900 dark:text-white">Assign Yard Slot</h3>
                <button wire:click="$set('parkModalOpen', false)" class="icon-btn">
                    <x-ui.icon name="x" class="w-5 h-5" />
                </button>
            </div>
            <div>
                <label class="label">Select Free Yard Slot <span class="required-mark">*</span></label>
                <select wire:model="parkLocationId" class="input w-full">
                    <option value="">— Select slot —</option>
                    @foreach($this->freeYardSlots as $slot)
                        <option value="{{ $slot->id }}">{{ $slot->code }}</option>
                    @endforeach
                </select>
                @error('parkLocationId') <p class="hint hint-error">{{ $message }}</p> @enderror
                @if($this->freeYardSlots->isEmpty())
                    <p class="hint !text-amber-600">No free yard slots available.</p>
                @endif
            </div>
            <div class="flex justify-end gap-2">
                <button wire:click="$set('parkModalOpen', false)" class="btn btn-ghost">Cancel</button>
                <button wire:click="park" wire:loading.attr="disabled" class="btn btn-primary">
                    Assign Slot
                </button>
            </div>
        </div>
    </div>
@endif

{{-- Move Slot Modal --}}
@if($moveModalOpen)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm"
         x-data wire:click.self="$set('moveModalOpen', false)">
        <div class="card w-full max-w-sm p-6 space-y-5">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-slate-900 dark:text-white">Move Yard Slot</h3>
                <button wire:click="$set('moveModalOpen', false)" class="icon-btn">
                    <x-ui.icon name="x" class="w-5 h-5" />
                </button>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="label">New Yard Slot <span class="required-mark">*</span></label>
                    <select wire:model="moveLocationId" class="input w-full">
                        <option value="">— Select slot —</option>
                        @foreach($this->freeYardSlots as $slot)
                            <option value="{{ $slot->id }}">{{ $slot->code }}</option>
                        @endforeach
                    </select>
                    @error('moveLocationId') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Reason <span class="required-mark">*</span></label>
                    <input wire:model="moveReason" type="text" placeholder="e.g. Clear path for loading" class="input w-full">
                    @error('moveReason') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button wire:click="$set('moveModalOpen', false)" class="btn btn-ghost">Cancel</button>
                <button wire:click="moveSlot" wire:loading.attr="disabled" class="btn btn-primary">
                    Move
                </button>
            </div>
        </div>
    </div>
@endif

{{-- Release Modal --}}
@if($releaseModalOpen)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm"
         x-data wire:click.self="$set('releaseModalOpen', false)">
        <div class="card w-full max-w-md p-6 space-y-5">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-900 dark:text-white">Release Container</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Record the final release of this container</p>
                </div>
                <button wire:click="$set('releaseModalOpen', false)" class="icon-btn">
                    <x-ui.icon name="x" class="w-5 h-5" />
                </button>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="label">Release Date & Time <span class="required-mark">*</span></label>
                    <input wire:model="releasedAt" type="datetime-local" class="input w-full tabular-nums">
                    @error('releasedAt') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Released To <span class="required-mark">*</span></label>
                    <select wire:model="releasedToPartnerId" class="input w-full">
                        <option value="">— Select partner —</option>
                        @foreach($this->releasePartners as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                    @error('releasedToPartnerId') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Notes</label>
                    <textarea wire:model="releaseNotes" rows="2" class="input w-full" placeholder="Optional release notes…"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button wire:click="$set('releaseModalOpen', false)" class="btn btn-ghost">Cancel</button>
                <button wire:click="release" wire:loading.attr="disabled" class="btn btn-primary">
                    Release
                </button>
            </div>
        </div>
    </div>
@endif

{{-- Cancel Modal --}}
@if($cancelModalOpen)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm"
         x-data wire:click.self="$set('cancelModalOpen', false)">
        <div class="card w-full max-w-sm p-6 space-y-5">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-slate-900 dark:text-white">Cancel Container</h3>
                <button wire:click="$set('cancelModalOpen', false)" class="icon-btn">
                    <x-ui.icon name="x" class="w-5 h-5" />
                </button>
            </div>
            <div class="flex gap-3 p-3 rounded-xl bg-red-50 dark:bg-red-500/10">
                <x-ui.icon name="alert" class="w-5 h-5 text-red-600 shrink-0 mt-0.5" />
                <p class="text-sm text-red-700 dark:text-red-400">This action cannot be undone. The container will be marked as cancelled.</p>
            </div>
            <div>
                <label class="label">Reason <span class="required-mark">*</span></label>
                <textarea wire:model="cancelReason" rows="3" class="input w-full" placeholder="Minimum 10 characters required…"></textarea>
                @error('cancelReason') <p class="hint hint-error">{{ $message }}</p> @enderror
            </div>
            <div class="flex justify-end gap-2">
                <button wire:click="$set('cancelModalOpen', false)" class="btn btn-ghost">Keep Container</button>
                <button wire:click="cancel" wire:loading.attr="disabled" class="btn btn-primary !bg-red-600 !hover:bg-red-700">
                    Cancel Container
                </button>
            </div>
        </div>
    </div>
@endif

{{-- Add/Edit Line Modal --}}
@if($lineModalOpen)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm"
         x-data wire:click.self="$set('lineModalOpen', false)">
        <div class="card w-full max-w-lg p-6 space-y-5 overflow-y-auto" style="max-height: 90vh;">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-slate-900 dark:text-white">{{ $editingLineId ? 'Edit Line' : 'Add Expected Line' }}</h3>
                <button wire:click="$set('lineModalOpen', false)" class="icon-btn">
                    <x-ui.icon name="x" class="w-5 h-5" />
                </button>
            </div>
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="label">Raw SKU</label>
                        <input wire:model="lineRawSku" type="text" class="input w-full font-mono" placeholder="e.g. SKU-001">
                    </div>
                    <div>
                        <label class="label">Map to Item</label>
                        <select wire:model="lineItemId" class="input w-full">
                            <option value="">— Unmapped —</option>
                            @foreach($this->items as $item)
                                <option value="{{ $item->id }}">{{ $item->sku }} – {{ $item->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="label">Description</label>
                    <input wire:model="lineRawDesc" type="text" class="input w-full" placeholder="Product description">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="label">Qty <span class="required-mark">*</span></label>
                        <input wire:model="lineQty" type="number" step="0.0001" min="0" class="input w-full tabular-nums">
                        @error('lineQty') <p class="hint hint-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">UOM</label>
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
                        <label class="label">Batch No.</label>
                        <input wire:model="lineBatchNo" type="text" class="input w-full font-mono" placeholder="BATCH-001">
                    </div>
                    <div>
                        <label class="label">Expiry Date</label>
                        <input wire:model="lineExpiry" type="date" class="input w-full tabular-nums">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="label">Weight (kg)</label>
                        <input wire:model="lineWeightKg" type="number" step="0.001" min="0" class="input w-full tabular-nums">
                    </div>
                    <div>
                        <label class="label">Virtual Warehouse <span class="required-mark">*</span></label>
                        <select wire:model="lineVwId" class="input w-full">
                            <option value="">— Select VW —</option>
                            @foreach($this->virtualWarehouses as $vw)
                                <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                            @endforeach
                        </select>
                        @error('lineVwId') <p class="hint hint-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="label">Notes</label>
                    <input wire:model="lineNotes" type="text" class="input w-full" placeholder="Line notes…">
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-1">
                <button wire:click="$set('lineModalOpen', false)" class="btn btn-ghost">Cancel</button>
                <button wire:click="saveLine" wire:loading.attr="disabled" class="btn btn-primary">Save Line</button>
            </div>
        </div>
    </div>
@endif

{{-- Upload Document Modal --}}
@if($docModalOpen)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm"
         x-data wire:click.self="$set('docModalOpen', false)">
        <div class="card w-full max-w-md p-6 space-y-5">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-slate-900 dark:text-white">Upload Document</h3>
                <button wire:click="$set('docModalOpen', false)" class="icon-btn">
                    <x-ui.icon name="x" class="w-5 h-5" />
                </button>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="label">Document Type <span class="required-mark">*</span></label>
                    <select wire:model="docType" class="input w-full">
                        @foreach(\App\Enums\ContainerDocumentType::cases() as $dt)
                            <option value="{{ $dt->value }}">{{ $dt->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">File <span class="required-mark">*</span></label>
                    <div class="relative border-2 border-dashed border-slate-300 dark:border-zinc-600 rounded-xl py-6 px-4 text-center cursor-pointer hover:border-simba-400 transition-colors"
                         onclick="this.querySelector('input[type=file]').click()">
                        <x-ui.icon name="upload" class="w-8 h-8 mx-auto text-slate-400 mb-2" wire:loading.remove wire:target="docFile" />
                        <x-ui.icon name="refresh" class="w-8 h-8 mx-auto text-brand animate-spin hidden mb-2" wire:loading wire:target="docFile" />
                        <p class="text-sm text-slate-500">Drop file here or click to browse</p>
                        <p class="text-xs text-slate-400 mt-1">Max 20 MB</p>
                        <input type="file" wire:model="docFile" class="sr-only" />
                    </div>
                    @if($docFile)
                        <p class="text-xs text-green-600 mt-2">
                            <x-ui.icon name="check" class="w-3.5 h-3.5 inline" /> {{ is_object($docFile) ? $docFile->getClientOriginalName() : 'File selected' }}
                        </p>
                    @endif
                    @error('docFile') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button wire:click="$set('docModalOpen', false)" class="btn btn-ghost">Cancel</button>
                <button wire:click="uploadDocument" wire:loading.attr="disabled" class="btn btn-primary">Upload</button>
            </div>
        </div>
    </div>
@endif

{{-- Add/Edit Discrepancy Modal --}}
@if($discModalOpen)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm"
         x-data wire:click.self="$set('discModalOpen', false)">
        <div class="card w-full max-w-sm p-6 space-y-5">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-slate-900 dark:text-white">{{ $editingDiscId ? 'Edit Discrepancy' : 'Report Discrepancy' }}</h3>
                <button wire:click="$set('discModalOpen', false)" class="icon-btn">
                    <x-ui.icon name="x" class="w-5 h-5" />
                </button>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="label">Type <span class="required-mark">*</span></label>
                    <select wire:model="discType" class="input w-full">
                        @foreach(\App\Enums\ContainerDiscrepancyType::cases() as $dt)
                            <option value="{{ $dt->value }}">{{ $dt->label() }}</option>
                        @endforeach
                    </select>
                    @error('discType') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Description <span class="required-mark">*</span></label>
                    <textarea wire:model="discDescription" rows="3" class="input w-full" placeholder="Describe the discrepancy (min 5 chars)…"></textarea>
                    @error('discDescription') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button wire:click="$set('discModalOpen', false)" class="btn btn-ghost">Cancel</button>
                <button wire:click="saveDiscrepancy" wire:loading.attr="disabled" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>
@endif

@include('livewire.inbound.containers._packing-list-wizard')
</div>{{-- single Livewire root --}}
