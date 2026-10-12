<div class="space-y-6" x-data="{ detailOpen: false, selected: null }">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Serial Lookup</h2>
            <p class="page-sub">Search any serial number to see its current position and full movement history.</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card overflow-hidden">
        <div class="flex flex-col gap-3 p-4 lg:flex-row border-b border-white/40 dark:border-white/[0.08]">
            <div class="relative flex-1">
                <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input wire:model.live.debounce.300ms="search" type="search"
                       placeholder="Search serial number or SKU…"
                       class="input w-full pl-9" />
            </div>

            <select wire:model.live="filterStatus" class="input lg:w-44">
                <option value="">All statuses</option>
                @foreach ($this->statuses as $s)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterVw" class="input lg:w-44">
                <option value="">All warehouses</option>
                @foreach ($this->virtualWarehouses as $vw)
                    <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Serial No</th>
                        <th>Item</th>
                        <th>Status</th>
                        <th>Location</th>
                        <th>Virtual Warehouse</th>
                        <th>Batch</th>
                        <th>First Received</th>
                        <th>Last Movement</th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->serials as $serial)
                        <tr>
                            <td class="font-mono text-sm font-medium text-slate-900 dark:text-white">{{ $serial->serial_no }}</td>
                            <td>
                                <div class="font-medium text-sm">{{ $serial->item->name ?? '—' }}</div>
                                <div class="text-xs text-slate-400">{{ $serial->item->sku ?? '' }}</div>
                            </td>
                            <td>
                                <span class="badge badge-{{ $serial->status->color() }}">{{ $serial->status->label() }}</span>
                            </td>
                            <td class="font-mono text-sm">{{ $serial->location->code ?? '—' }}</td>
                            <td class="text-sm">{{ $serial->virtualWarehouse->name ?? '—' }}</td>
                            <td class="text-sm text-slate-500">{{ $serial->batch->batch_no ?? '—' }}</td>
                            <td class="text-sm text-slate-500">{{ $serial->first_received_at?->format('d M Y') ?? '—' }}</td>
                            <td class="text-sm text-slate-500">{{ $serial->lastTransaction?->number ?? '—' }}</td>
                            <td>
                                <button type="button"
                                        class="icon-btn"
                                        title="View history"
                                        @click="selected = {{ $serial->id }}; detailOpen = true; $wire.loadHistory({{ $serial->id }})">
                                    <x-ui.icon name="eye" class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="empty-row">No serials found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4">
            {{ $this->serials->links() }}
        </div>
    </div>

    {{-- History Slide-over / Modal --}}
    @if ($selectedSerial ?? null)
        <div x-show="detailOpen" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-end"
             @click.self="detailOpen = false">
            <div class="w-full max-w-xl h-full bg-white dark:bg-zinc-900 shadow-2xl overflow-y-auto p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-slate-900 dark:text-white">Serial: {{ $selectedSerial->serial_no }}</h3>
                    <button @click="detailOpen = false" class="icon-btn"><x-ui.icon name="x" class="w-5 h-5" /></button>
                </div>

                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div><span class="text-slate-500">Item:</span> <span class="font-medium">{{ $selectedSerial->item->name }}</span></div>
                    <div><span class="text-slate-500">Status:</span> <span class="badge badge-{{ $selectedSerial->status->color() }}">{{ $selectedSerial->status->label() }}</span></div>
                    <div><span class="text-slate-500">Location:</span> <span class="font-mono">{{ $selectedSerial->location?->code ?? '—' }}</span></div>
                    <div><span class="text-slate-500">VW:</span> <span>{{ $selectedSerial->virtualWarehouse?->name ?? '—' }}</span></div>
                    <div><span class="text-slate-500">Batch:</span> <span>{{ $selectedSerial->batch?->batch_no ?? '—' }}</span></div>
                    <div><span class="text-slate-500">First received:</span> <span>{{ $selectedSerial->first_received_at?->format('d M Y') ?? '—' }}</span></div>
                </div>

                <h4 class="font-semibold text-sm text-slate-600 dark:text-slate-300 mt-4">Movement History</h4>
                <div class="space-y-2">
                    @forelse ($serialHistory ?? [] as $movement)
                        <div class="rounded-lg border border-white/40 dark:border-white/[0.08] p-3 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{ $movement->transaction->type->label() }}</span>
                                <span class="font-mono text-xs text-slate-400">{{ $movement->transaction->number }}</span>
                            </div>
                            <div class="text-xs text-slate-500 mt-1">
                                {{ $movement->location?->code ?? '—' }}
                                → {{ $movement->transaction->posted_at?->format('d M Y H:i') }}
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400">No movements recorded.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

</div>
