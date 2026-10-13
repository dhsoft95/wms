<div class="space-y-6" x-data="{ compact: $persist(false).as('soh-density') }">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">
                Stock on Hand
                <span class="ml-2 align-middle text-sm font-medium text-slate-400 dark:text-slate-500">{{ $this->balances->total() }}</span>
            </h2>
            <p class="page-sub">Live balances by SKU, location and virtual warehouse.</p>
        </div>
        <button type="button" disabled
                title="Available in Module 10"
                class="btn btn-secondary opacity-50 cursor-not-allowed">
            <x-ui.icon name="download" class="w-4 h-4" /> Export
        </button>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card p-4">
            <p class="text-xs text-slate-500 dark:text-slate-400">Active SKUs</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($this->stats['total_skus']) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-slate-500 dark:text-slate-400">Total On Hand</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ number_format($this->stats['total_on_hand']) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-slate-500 dark:text-slate-400">Allocated</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-amber-600 dark:text-amber-400">{{ number_format($this->stats['total_allocated']) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-slate-500 dark:text-slate-400">Below Min Stock</p>
            <p class="mt-1 text-2xl font-bold tabular-nums {{ $this->stats['below_min'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">{{ number_format($this->stats['below_min']) }}</p>
        </div>
    </div>

    {{-- Filters + Table --}}
    <div class="card overflow-hidden" :class="compact ? 'density-compact' : ''">

        {{-- Filter Bar --}}
        <div class="flex flex-col gap-3 p-4 lg:flex-row border-b border-white/40 dark:border-white/[0.08]">

            {{-- Search --}}
            <div class="relative flex-1">
                <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    placeholder="Search SKU or item name…"
                    class="input w-full pl-9"
                />
            </div>

            {{-- Category --}}
            <select wire:model.live="filterCategory" class="input lg:w-44">
                <option value="">All categories</option>
                @foreach ($this->categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>

            {{-- Location --}}
            <select wire:model.live="filterLocation" class="input lg:w-44">
                <option value="">All locations</option>
                @foreach ($this->locations as $loc)
                    <option value="{{ $loc->id }}">{{ $loc->code }}</option>
                @endforeach
            </select>

            {{-- Virtual Warehouse --}}
            <select wire:model.live="filterVw" class="input lg:w-44">
                <option value="">All warehouses</option>
                @foreach ($this->virtualWarehouses as $vw)
                    <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                @endforeach
            </select>

            {{-- Status --}}
            <select wire:model.live="filterStatus" class="input lg:w-36">
                <option value="">All statuses</option>
                <option value="available">Available</option>
                <option value="on_hold">On Hold</option>
            </select>

            {{-- Expiring --}}
            <select wire:model.live="filterExpiring" class="input lg:w-40">
                <option value="">Any expiry</option>
                <option value="7">Expiring in 7 days</option>
                <option value="14">Expiring in 14 days</option>
                <option value="30">Expiring in 30 days</option>
            </select>

            {{-- Density Toggle --}}
            <div class="flex items-center gap-1 tabs shrink-0">
                <button type="button" @click="compact = false" :class="!compact ? 'tab-active' : ''" class="tab text-xs">Comfortable</button>
                <button type="button" @click="compact = true"  :class="compact  ? 'tab-active' : ''" class="tab text-xs">Compact</button>
            </div>

        </div>

        {{-- Table --}}
        <div wire:loading.class="opacity-50">
            <x-ui.table class="min-w-[1100px]">
                <thead>
                    <tr>
                        <th class="px-4 py-3">SKU</th>
                        <th class="px-4 py-3">Item / Category</th>
                        <th class="px-4 py-3">Batch</th>
                        <th class="px-4 py-3">Expiry</th>
                        <th class="px-4 py-3">Location</th>
                        <th class="px-4 py-3">Warehouse</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">On Hand</th>
                        <th class="px-4 py-3 text-right">Allocated</th>
                        <th class="px-4 py-3 text-right">Available</th>
                        <th class="px-4 py-3">UoM</th>
                        <th class="px-4 py-3 w-14 sr-only">Actions</th>
                    </tr>
                </thead>

                <tbody wire:loading.remove class="divide-y divide-white/50 dark:divide-white/[0.06]">
                @forelse ($this->balances as $balance)
                    @php
                        $isLowStock = $balance->item?->min_stock !== null && $balance->qty_on_hand < $balance->item->min_stock;
                        $available  = $balance->qty_on_hand - $balance->qty_allocated;

                        $expiryClass = '';
                        if ($balance->batch?->expiry_date) {
                            $daysToExpiry = now()->diffInDays($balance->batch->expiry_date, false);
                            if ($daysToExpiry <= 30) {
                                $expiryClass = $daysToExpiry <= 0 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-red-600 dark:text-red-400';
                            } elseif ($daysToExpiry <= 60) {
                                $expiryClass = 'text-amber-600 dark:text-amber-400';
                            }
                        }
                    @endphp
                    <tr wire:key="bal-{{ $balance->id }}"
                        class="transition hover:bg-white/30 dark:hover:bg-white/[0.04] {{ $isLowStock ? 'bg-red-50/50 dark:bg-red-950/20' : '' }}">

                        {{-- SKU --}}
                        <td class="px-4 py-3.5">
                            @if ($balance->item)
                                <a href="{{ route('admin.items.show', $balance->item) }}" wire:navigate
                                   class="mono font-semibold text-simba-700 dark:text-simba-400 hover:underline text-xs">
                                    {{ $balance->item->sku }}
                                </a>
                            @else
                                <span class="mono text-xs text-slate-400">—</span>
                            @endif
                        </td>

                        {{-- Item / Category --}}
                        <td class="px-4 py-3.5">
                            <div class="font-medium text-slate-900 dark:text-slate-100">{{ $balance->item?->name ?? '—' }}</div>
                            @if ($balance->item?->category)
                                <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">{{ $balance->item->category->name }}</p>
                            @endif
                        </td>

                        {{-- Batch --}}
                        <td class="px-4 py-3.5">
                            @if ($balance->batch)
                                <span class="mono text-xs text-slate-700 dark:text-slate-300">{{ $balance->batch->batch_no }}</span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>

                        {{-- Expiry --}}
                        <td class="px-4 py-3.5 tabular-nums text-sm {{ $expiryClass }}">
                            {{ $balance->batch?->expiry_date?->format('d M Y') ?? '—' }}
                        </td>

                        {{-- Location --}}
                        <td class="px-4 py-3.5">
                            <span class="mono text-xs text-slate-700 dark:text-slate-300">{{ $balance->location?->code ?? '—' }}</span>
                        </td>

                        {{-- Virtual Warehouse --}}
                        <td class="px-4 py-3.5 text-sm text-slate-600 dark:text-slate-300">
                            {{ $balance->virtualWarehouse?->name ?? '—' }}
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3.5">
                            @if ($balance->stock_status?->value === 'available' || $balance->stock_status === 'available')
                                <span class="badge badge-green"><span class="dot"></span> Available</span>
                            @elseif ($balance->stock_status?->value === 'on_hold' || $balance->stock_status === 'on_hold')
                                <span class="badge badge-amber">On Hold</span>
                            @else
                                <span class="badge badge-slate">{{ $balance->stock_status }}</span>
                            @endif
                        </td>

                        {{-- On Hand --}}
                        <td class="px-4 py-3.5 text-right tabular-nums font-semibold text-slate-900 dark:text-slate-100">
                            {{ number_format($balance->qty_on_hand, 2) }}
                        </td>

                        {{-- Allocated --}}
                        <td class="px-4 py-3.5 text-right tabular-nums text-amber-600 dark:text-amber-400">
                            {{ number_format($balance->qty_allocated, 2) }}
                        </td>

                        {{-- Available --}}
                        <td class="px-4 py-3.5 text-right tabular-nums font-bold {{ $available <= 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                            {{ number_format($available, 2) }}
                        </td>

                        {{-- UoM --}}
                        <td class="px-4 py-3.5">
                            @if ($balance->item?->baseUom)
                                <span class="badge badge-slate font-mono">{{ $balance->item->baseUom->code }}</span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>

                        {{-- Row actions --}}
                        <td class="px-4 py-3.5 w-14">
                            @if ($balance->item?->track_serial)
                                <button type="button"
                                        wire:click="viewSerials({{ $balance->id }})"
                                        title="View Serials"
                                        class="flex h-7 w-7 items-center justify-center rounded text-slate-400 hover:text-simba-600 hover:bg-simba-50 dark:hover:bg-simba-900/20 transition">
                                    <x-ui.icon name="list" class="h-4 w-4" />
                                </button>
                            @endif
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-5 py-16 text-center">
                            <x-ui.icon name="package" class="mx-auto h-8 w-8 text-slate-300 dark:text-slate-600" />
                            @if ($search || $filterCategory || $filterLocation || $filterVw || $filterStatus || $filterExpiring)
                                <p class="mt-3 font-medium text-slate-900 dark:text-white">No stock records match your filters</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    <button wire:click="$set('search', ''); $set('filterCategory', ''); $set('filterLocation', ''); $set('filterVw', ''); $set('filterStatus', ''); $set('filterExpiring', '')"
                                            class="text-simba-600 dark:text-simba-400 hover:underline">
                                        Clear filters
                                    </button>
                                </p>
                            @else
                                <p class="mt-3 font-medium text-slate-900 dark:text-white">No stock on hand</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Post an opening balance or receive goods to see stock here.</p>
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>

                <tbody wire:loading class="divide-y divide-white/50 dark:divide-white/[0.06]">
                    @for ($i = 0; $i < 8; $i++)
                        <tr class="skeleton-row">
                            <td class="px-4"><div class="skeleton-cell w-24"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-40"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-20"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-24"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-28"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-32"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-20"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-16 ml-auto"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-16 ml-auto"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-16 ml-auto"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-10"></div></td>
                        </tr>
                    @endfor
                </tbody>

            </x-ui.table>
        </div>

        {{-- Pagination --}}
        @if ($this->balances->hasPages())
            <div class="px-4 py-3 border-t border-white/40 dark:border-white/[0.08]">
                {{ $this->balances->links() }}
            </div>
        @endif

    </div>

    {{-- ── VIEW SERIALS MODAL ────────────────────────────────────────────────── --}}
    @if ($viewingSerialsId && $this->viewingSerialsBalance)
        @php
            $vsBalance = $this->viewingSerialsBalance['balance'];
            $vsSerials = $this->viewingSerialsBalance['serials'];
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog" aria-modal="true" aria-label="Serial Numbers">

            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"
                 wire:click="closeSerials"></div>

            <div class="relative z-10 w-full max-w-lg rounded-xl bg-white dark:bg-zinc-900
                        shadow-2xl ring-1 ring-black/[0.08] dark:ring-white/[0.08]
                        flex flex-col max-h-[80vh]">

                <div class="flex items-center justify-between px-6 py-4 border-b border-white/40 dark:border-white/[0.08]">
                    <div>
                        <h3 class="font-semibold text-slate-900 dark:text-white">Serial Numbers</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ $vsBalance->item?->name }} &bull; {{ $vsBalance->location?->code }} &bull; {{ $vsBalance->virtualWarehouse?->name }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeSerials"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition">
                        <x-ui.icon name="x" class="h-4 w-4" />
                    </button>
                </div>

                <div class="px-6 py-3 border-b border-white/40 dark:border-white/[0.08] flex items-center gap-3 text-sm">
                    <span class="text-slate-500 dark:text-slate-400">{{ $vsSerials->count() }} serial(s) on hand</span>
                    <span class="{{ $vsSerials->count() === (int) round((float) $vsBalance->qty_on_hand) ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }} font-medium">
                        {{ $vsSerials->count() === (int) round((float) $vsBalance->qty_on_hand) ? '✓ Count matches balance' : '⚠ Count mismatch with balance' }}
                    </span>
                </div>

                <div class="overflow-y-auto flex-1">
                    <x-ui.table class="text-sm">
                        <thead class="sticky top-0 bg-white/90 dark:bg-zinc-900/90 border-b border-white/40 dark:border-white/[0.08]">
                            <tr>
                                <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                                <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Serial No</th>
                                <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/50 dark:divide-white/[0.06]">
                            @foreach ($vsSerials as $idx => $sn)
                                <tr class="hover:bg-white/30 dark:hover:bg-white/[0.04]">
                                    <td class="px-5 py-2.5 text-xs text-slate-400 tabular-nums w-10">{{ $idx + 1 }}</td>
                                    <td class="px-5 py-2.5 font-mono text-xs font-semibold text-slate-800 dark:text-slate-200">{{ $sn->serial_no }}</td>
                                    <td class="px-5 py-2.5">
                                        <span class="badge badge-{{ $sn->status->color() }}">{{ $sn->status->label() }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                </div>

                <div class="px-6 py-4 border-t border-white/40 dark:border-white/[0.08] flex justify-end">
                    <button type="button" wire:click="closeSerials" class="btn btn-secondary">Close</button>
                </div>

            </div>
        </div>
    @endif

</div>
