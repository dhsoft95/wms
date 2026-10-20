<div class="space-y-6" x-data="{ compact: $persist(false).as('movements-density') }">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">
                Movement History
                <span class="ml-2 align-middle text-sm font-medium text-slate-400 dark:text-slate-500">{{ $this->transactions->total() }}</span>
            </h2>
            <p class="page-sub">Full audit trail of all stock transactions posted to the ledger.</p>
        </div>
        @can('stock.opening-balance')
            <a href="{{ route('inventory.opening-balance') }}" wire:navigate class="btn btn-secondary">
                <x-ui.icon name="plus" class="w-4 h-4" /> Opening Balance
            </a>
        @endcan
    </div>

    {{-- Filters --}}
    <div class="card overflow-hidden" :class="compact ? 'density-compact' : ''">

        <div class="flex flex-col gap-3 p-4 lg:flex-row border-b border-white/40 dark:border-white/[0.08]">

            {{-- Date From --}}
            <div class="flex items-center gap-2 shrink-0">
                <label class="text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">From</label>
                <input wire:model.live="dateFrom" type="date" class="input lg:w-36" />
            </div>

            {{-- Date To --}}
            <div class="flex items-center gap-2 shrink-0">
                <label class="text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">To</label>
                <input wire:model.live="dateTo" type="date" class="input lg:w-36" />
            </div>

            {{-- Type --}}
            <select wire:model.live="filterType" class="input lg:w-44">
                <option value="">All types</option>
                @foreach ($this->movementTypes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            {{-- Reference No --}}
            <div class="relative flex-1">
                <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    wire:model.live.debounce.300ms="filterRefNo"
                    type="search"
                    placeholder="Reference number…"
                    class="input w-full pl-9"
                />
            </div>

            {{-- Virtual Warehouse --}}
            <select wire:model.live="filterVw" class="input lg:w-44">
                <option value="">All warehouses</option>
                @foreach ($this->virtualWarehouses as $vw)
                    <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                @endforeach
            </select>

            {{-- Density Toggle --}}
            <div class="flex items-center gap-1 tabs shrink-0">
                <button type="button" @click="compact = false" :class="!compact ? 'tab-active' : ''" class="tab text-xs">Comfortable</button>
                <button type="button" @click="compact = true"  :class="compact  ? 'tab-active' : ''" class="tab text-xs">Compact</button>
            </div>

        </div>

        {{-- Table --}}
        <div wire:loading.class="opacity-50">
            <x-ui.table class="min-w-[860px]">
                <thead>
                    <tr>
                        <th class="px-4 py-3">Number</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Items</th>
                        <th class="px-4 py-3">Posted By</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">State</th>
                        <th class="px-4 py-3 w-14 sr-only">Actions</th>
                    </tr>
                </thead>

                <tbody wire:loading.remove class="divide-y divide-white/50 dark:divide-white/[0.06]">
                @forelse ($this->transactions as $tx)
                    @php
                        $isReversed  = $tx->reversed_by_transaction_id !== null;
                        $isReversal  = $tx->reverses_transaction_id !== null;
                    @endphp
                    <tr wire:key="tx-{{ $tx->id }}"
                        class="transition hover:bg-white/30 dark:hover:bg-white/[0.04] {{ $isReversed ? 'opacity-60' : '' }}">

                        {{-- Number --}}
                        <td class="px-4 py-3.5">
                            <span class="mono text-xs font-semibold text-slate-800 dark:text-slate-100 {{ $isReversed ? 'line-through decoration-red-400' : '' }}">
                                {{ $tx->number }}
                            </span>
                        </td>

                        {{-- Type --}}
                        <td class="px-4 py-3.5">
                            @php
                                $typeBadge = match(true) {
                                    $tx->type === \App\Enums\Inventory\MovementType::OpeningBalance  => 'badge-slate',
                                    $tx->type === \App\Enums\Inventory\MovementType::Receipt         => 'badge-green',
                                    $tx->type === \App\Enums\Inventory\MovementType::Dispatch        => 'badge-red',
                                    $tx->type === \App\Enums\Inventory\MovementType::Adjustment      => 'badge-amber',
                                    $tx->type === \App\Enums\Inventory\MovementType::CountAdjustment => 'badge-amber',
                                    $tx->type === \App\Enums\Inventory\MovementType::Reversal        => 'badge-red',
                                    default                                                           => 'badge-slate',
                                };
                            @endphp
                            <span class="badge {{ $typeBadge }}">{{ $tx->type->label() }}</span>
                        </td>

                        {{-- Items summary --}}
                        <td class="px-4 py-3.5 text-sm text-slate-700 dark:text-slate-300">
                            @php
                                $itemNames = $tx->movements->map(fn ($m) => $m->item?->name)->filter()->unique()->take(2);
                                $moreCount = $tx->movements->count() - $itemNames->count();
                            @endphp
                            <span>{{ $itemNames->implode(', ') }}</span>
                            @if ($moreCount > 0)
                                <span class="text-slate-400 dark:text-slate-500"> +{{ $moreCount }} more</span>
                            @endif
                        </td>

                        {{-- Posted By --}}
                        <td class="px-4 py-3.5 text-sm text-slate-600 dark:text-slate-400">
                            {{ $tx->postedBy?->name ?? '—' }}
                        </td>

                        {{-- Date --}}
                        <td class="px-4 py-3.5 text-sm tabular-nums text-slate-600 dark:text-slate-400">
                            {{ $tx->posted_at?->format('d M Y H:i') ?? '—' }}
                        </td>

                        {{-- State --}}
                        <td class="px-4 py-3.5">
                            @if ($isReversed)
                                <span class="badge badge-red">Reversed</span>
                            @elseif ($isReversal)
                                <span class="badge badge-amber">Reversal</span>
                            @else
                                <span class="badge badge-green"><span class="dot"></span> Posted</span>
                            @endif
                        </td>

                        {{-- Actions --}}
                        <td class="px-4 py-3.5 w-14">
                            <x-ui.row-actions :label="'Actions for ' . $tx->number">
                                <x-ui.row-actions.item
                                    icon="eye"
                                    label="View Details"
                                    wire:click="viewTransaction({{ $tx->id }})"
                                />
                                @can('stock.reverse')
                                    @if (! $isReversed && ! $isReversal)
                                        <x-ui.row-actions.divider />
                                        <x-ui.row-actions.item
                                            icon="refresh"
                                            label="Reverse"
                                            tone="danger"
                                            wire:click="openReverseModal({{ $tx->id }})"
                                        />
                                    @endif
                                @endcan
                            </x-ui.row-actions>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-16 text-center">
                            <x-ui.icon name="refresh" class="mx-auto h-8 w-8 text-slate-300 dark:text-slate-600" />
                            <p class="mt-3 font-medium text-slate-900 dark:text-white">No movements found</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                @if ($dateFrom || $dateTo || $filterType || $filterRefNo || $filterVw)
                                    Try adjusting your filters.
                                @else
                                    Stock movements will appear here once transactions are posted.
                                @endif
                            </p>
                        </td>
                    </tr>
                @endforelse
                </tbody>

                <tbody wire:loading class="divide-y divide-white/50 dark:divide-white/[0.06]">
                    @for ($i = 0; $i < 6; $i++)
                        <tr class="skeleton-row">
                            <td class="px-4"><div class="skeleton-cell w-28"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-36"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-48"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-28"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-32"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-16"></div></td>
                            <td class="px-4 w-14"><div class="skeleton-cell w-8 ml-auto"></div></td>
                        </tr>
                    @endfor
                </tbody>

            </x-ui.table>
        </div>

        {{-- Pagination --}}
        @if ($this->transactions->hasPages())
            <div class="px-4 py-3 border-t border-white/40 dark:border-white/[0.08]">
                {{ $this->transactions->links() }}
            </div>
        @endif

    </div>

    {{-- ── TRANSACTION DETAIL PANEL ──────────────────────────────────────────── --}}
    @if ($viewingTransactionId && $this->viewingTransaction)
        @php $vtx = $this->viewingTransaction; @endphp
        <div class="fixed inset-0 z-50 flex items-start justify-end p-4"
             role="dialog" aria-modal="true" aria-label="Transaction Details">

            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"
                 wire:click="closeDrawer"></div>

            {{-- Panel --}}
            <div class="relative z-10 flex h-full w-full max-w-2xl flex-col rounded-xl
                        bg-white dark:bg-zinc-900
                        shadow-2xl ring-1 ring-black/[0.08] dark:ring-white/[0.08]
                        overflow-hidden">

                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-white/40 dark:border-white/[0.08] px-6 py-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <span class="mono text-sm font-bold text-slate-900 dark:text-white">{{ $vtx->number }}</span>
                            <span class="badge badge-slate">{{ $vtx->type->label() }}</span>
                            @if ($vtx->reversed_by_transaction_id)
                                <span class="badge badge-red">Reversed</span>
                            @elseif ($vtx->reverses_transaction_id)
                                <span class="badge badge-amber">Reversal of {{ $vtx->reverses?->number }}</span>
                            @endif
                        </div>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            Posted by {{ $vtx->postedBy?->name ?? 'System' }}
                            &bull; {{ $vtx->posted_at?->format('d M Y H:i') }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeDrawer"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-white/[0.08] transition">
                        <x-ui.icon name="x" class="h-4 w-4" />
                    </button>
                </div>

                {{-- Reversal / reversed notices --}}
                @if ($vtx->reversed_by_transaction_id)
                    <div class="mx-6 mt-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700/40 px-4 py-3 text-sm text-red-700 dark:text-red-400">
                        This transaction was reversed by <span class="mono font-semibold">{{ $vtx->reversal?->number }}</span>.
                    </div>
                @endif
                @if ($vtx->reverses_transaction_id)
                    <div class="mx-6 mt-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/40 px-4 py-3 text-sm text-amber-700 dark:text-amber-400">
                        This is a reversal of transaction <span class="mono font-semibold">{{ $vtx->reverses?->number }}</span>.
                    </div>
                @endif

                {{-- Meta --}}
                @if ($vtx->reference_no || $vtx->notes)
                    <div class="px-6 py-3 border-b border-white/40 dark:border-white/[0.08] flex flex-wrap gap-6 text-sm">
                        @if ($vtx->reference_no)
                            <div>
                                <p class="text-xs text-slate-400 dark:text-slate-500">Reference</p>
                                <p class="mono font-semibold text-slate-800 dark:text-slate-200">{{ $vtx->reference_no }}</p>
                            </div>
                        @endif
                        @if ($vtx->notes)
                            <div class="flex-1 min-w-0">
                                <p class="text-xs text-slate-400 dark:text-slate-500">Notes</p>
                                <p class="text-slate-700 dark:text-slate-300 truncate">{{ $vtx->notes }}</p>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Movements table --}}
                <div class="flex-1 overflow-y-auto">
                    <x-ui.table class="text-sm">
                        <thead class="text-[11px] font-semibold uppercase tracking-wider
                                      text-slate-500 dark:text-slate-400
                                      bg-white/[0.45] dark:bg-white/[0.04]
                                      border-b border-white/50 dark:border-white/[0.07] sticky top-0">
                            <tr>
                                <th class="px-5 py-3">Item</th>
                                <th class="px-5 py-3">Batch</th>
                                <th class="px-5 py-3">Location</th>
                                <th class="px-5 py-3">Warehouse</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Qty</th>
                                <th class="px-5 py-3">UoM</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/50 dark:divide-white/[0.06]">
                        @forelse ($vtx->movements as $movement)
                            @php $mvSerials = $movement->relationLoaded('serials') ? $movement->serials : collect(); @endphp
                            <tr class="transition hover:bg-white/30 dark:hover:bg-white/[0.04]">
                                <td class="px-5 py-3.5">
                                    <div class="font-medium text-slate-900 dark:text-slate-100">{{ $movement->item?->name ?? '—' }}</div>
                                    <div class="mono text-xs text-slate-400 dark:text-slate-500">{{ $movement->item?->sku }}</div>
                                </td>
                                <td class="px-5 py-3.5 mono text-xs text-slate-600 dark:text-slate-400">
                                    {{ $movement->batch?->batch_no ?? '—' }}
                                </td>
                                <td class="px-5 py-3.5 mono text-xs text-slate-700 dark:text-slate-300">
                                    {{ $movement->location?->code ?? '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-slate-600 dark:text-slate-400">
                                    {{ $movement->virtualWarehouse?->name ?? '—' }}
                                </td>
                                <td class="px-5 py-3.5">
                                    @if (($movement->stock_status?->value ?? $movement->stock_status) === 'on_hold')
                                        <span class="badge badge-amber">On Hold</span>
                                    @else
                                        <span class="badge badge-green">Available</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right tabular-nums font-semibold
                                           {{ $movement->entered_qty > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $movement->entered_qty > 0 ? '+' : '' }}{{ number_format($movement->entered_qty, 4) }}
                                </td>
                                <td class="px-5 py-3.5">
                                    @if ($movement->item?->baseUom)
                                        <span class="badge badge-slate font-mono">{{ $movement->item->baseUom->code }}</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                            {{-- Serials for this movement line --}}
                            @if ($mvSerials->isNotEmpty())
                                <tr x-data="{ open: false }">
                                    <td colspan="7" class="px-5 pb-3 pt-0">
                                        <button type="button"
                                                @click="open = !open"
                                                class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 hover:text-simba-600 dark:hover:text-simba-400 transition">
                                            <span x-text="open ? '▲' : '▶'" class="text-[10px]"></span>
                                            <span>{{ $mvSerials->count() }} serial(s) — <span class="font-mono">{{ $mvSerials->take(3)->pluck('serial_no')->implode(', ') }}{{ $mvSerials->count() > 3 ? '…' : '' }}</span></span>
                                        </button>
                                        <div x-show="open" x-collapse class="mt-2 flex flex-wrap gap-1.5">
                                            @foreach ($mvSerials as $sn)
                                                <span class="mono text-xs bg-slate-100 dark:bg-white/[0.08] text-slate-700 dark:text-slate-300 px-2 py-0.5 rounded">{{ $sn->serial_no }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-sm text-slate-400 dark:text-slate-500">
                                    No movement lines found.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </x-ui.table>
                </div>

                {{-- Footer --}}
                <div class="border-t border-white/40 dark:border-white/[0.08] px-6 py-4 flex justify-end">
                    <button type="button" wire:click="closeDrawer" class="btn btn-secondary">
                        Close
                    </button>
                </div>

            </div>
        </div>
    @endif

    {{-- ── REVERSE MODAL ─────────────────────────────────────────────────────── --}}
    @if ($reversingId)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog" aria-modal="true">

            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"
                 wire:click="closeReverseModal"></div>

            {{-- Panel --}}
            <div class="relative z-10 w-full max-w-md rounded-xl bg-white dark:bg-zinc-900
                        shadow-2xl ring-1 ring-black/[0.08] dark:ring-white/[0.08]">

                <div class="p-6">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">Reverse Transaction</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        This will post a <strong class="text-red-600 dark:text-red-400">reversal</strong> transaction that
                        negates all movement lines. This action cannot be undone.
                    </p>
                </div>

                <div class="px-6 pb-6 space-y-4">
                    <div>
                        <label class="label" for="reverse-reason">
                            Reason <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            wire:model="reverseReason"
                            id="reverse-reason"
                            rows="3"
                            placeholder="Describe why this transaction is being reversed…"
                            class="input w-full resize-none @error('reverseReason') border-red-400 @enderror"
                        ></textarea>
                        @error('reverseReason')
                            <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="button" wire:click="closeReverseModal" class="btn btn-secondary">
                            Cancel
                        </button>
                        <button type="button"
                                wire:click="reverse"
                                wire:loading.attr="disabled"
                                class="btn bg-red-600 hover:bg-red-700 text-white font-semibold">
                            <span wire:loading.remove wire:target="reverse">Post Reversal</span>
                            <span wire:loading wire:target="reverse">Reversing…</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    @endif

</div>
