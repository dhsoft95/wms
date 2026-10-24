@php
    use App\Enums\MovementStatus;

    $isOpen      = in_array($movement->status, [MovementStatus::Draft, MovementStatus::Submitted]);
    $isCancelled = $movement->status === MovementStatus::Cancelled;
    $executedAt  = $movement->executed_at ?? $movement->posted_at;
    $lineCount   = $movement->lines->count();

    $formatQty = fn ($qty) => rtrim(rtrim(number_format((float) $qty, 4, '.', ','), '0'), '.');
@endphp

<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div class="min-w-0">
            <a href="{{ route('warehouse.movements.index') }}" wire:navigate
               class="inline-flex items-center gap-1.5 mb-2 text-xs font-semibold text-slate-500 transition-colors hover:text-simba-600 dark:text-slate-400 dark:hover:text-simba-400">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5 shrink-0" />
                <span>Back to Movements</span>
            </a>
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="page-title font-mono truncate">{{ $movement->number }}</h2>
                <span class="badge {{ $movement->status->badge() }}">
                    <span class="dot"></span>{{ $movement->status->label() }}
                </span>
            </div>
        </div>

        @if($isOpen)
            <div class="flex flex-wrap items-center gap-2">
                @can('cancel', $movement)
                    <button type="button"
                            wire:click="cancel"
                            wire:loading.attr="disabled"
                            wire:target="cancel,execute"
                            wire:confirm="Cancel this movement? This cannot be undone."
                            class="btn btn-secondary inline-flex items-center justify-center gap-1.5 h-10 px-4 text-red-600 hover:text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:text-red-300 dark:hover:bg-red-500/10 disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="cancel" class="inline-flex items-center gap-1.5">
                            <x-ui.icon name="x-mark" class="w-4 h-4 shrink-0" />
                            <span>Cancel</span>
                        </span>
                        <span wire:loading.inline-flex wire:target="cancel" class="items-center gap-1.5">
                            <svg class="w-4 h-4 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25" />
                                <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                            </svg>
                            <span>Cancelling…</span>
                        </span>
                    </button>
                @endcan

                @can('execute', $movement)
                    <button type="button"
                            wire:click="execute"
                            wire:loading.attr="disabled"
                            wire:target="execute,cancel"
                            wire:confirm="Execute this movement? Stock will be relocated and this cannot be undone."
                            class="btn btn-primary inline-flex items-center justify-center gap-1.5 h-10 px-4 disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="execute" class="inline-flex items-center gap-1.5">
                            <x-ui.icon name="check" class="w-4 h-4 shrink-0" />
                            <span>Execute Movement</span>
                        </span>
                        <span wire:loading.inline-flex wire:target="execute" class="items-center gap-1.5">
                            <svg class="w-4 h-4 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25" />
                                <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                            </svg>
                            <span>Executing…</span>
                        </span>
                    </button>
                @endcan
            </div>
        @endif
    </div>

    {{-- Meta strip --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <div class="card card-body !py-3">
            <p class="text-xs font-medium tracking-wide uppercase text-slate-400">Virtual Warehouse</p>
            <p class="mt-1 text-sm font-semibold truncate text-slate-800 dark:text-slate-200"
               title="{{ $movement->virtualWarehouse?->name }}">
                {{ $movement->virtualWarehouse?->name ?? '—' }}
            </p>
        </div>

        <div class="card card-body !py-3">
            <p class="text-xs font-medium tracking-wide uppercase text-slate-400">Lines</p>
            <p class="mt-1 text-sm font-semibold tabular-nums text-slate-800 dark:text-slate-200">
                {{ $lineCount }} {{ Str::plural('line', $lineCount) }}
            </p>
        </div>

        <div class="card card-body !py-3">
            <p class="text-xs font-medium tracking-wide uppercase text-slate-400">Created By</p>
            <p class="mt-1 text-sm font-semibold truncate text-slate-800 dark:text-slate-200">
                {{ $movement->createdBy?->name ?? '—' }}
            </p>
            <p class="mt-0.5 text-xs tabular-nums text-slate-400">
                {{ $movement->created_at?->format('d M Y · H:i') ?? '—' }}
            </p>
        </div>

        <div class="card card-body !py-3">
            @if($movement->executedBy)
                <p class="text-xs font-medium tracking-wide uppercase text-slate-400">Executed By</p>
                <p class="mt-1 text-sm font-semibold truncate text-slate-800 dark:text-slate-200">
                    {{ $movement->executedBy->name }}
                </p>
                <p class="mt-0.5 text-xs tabular-nums text-slate-400">
                    {{ $executedAt?->format('d M Y · H:i') ?? '—' }}
                </p>
            @elseif($isCancelled)
                <p class="text-xs font-medium tracking-wide uppercase text-slate-400">Status</p>
                <p class="mt-1 text-sm font-semibold text-red-600 dark:text-red-400">Cancelled</p>
                <p class="mt-0.5 text-xs text-slate-400">No stock was moved</p>
            @else
                <p class="text-xs font-medium tracking-wide uppercase text-slate-400">Awaiting</p>
                <p class="mt-1 text-sm font-semibold text-amber-600 dark:text-amber-400">Execution</p>
                <p class="mt-0.5 text-xs text-slate-400">Stock not yet moved</p>
            @endif
        </div>
    </div>

    {{-- Main grid --}}
    <div class="grid grid-cols-1 gap-6 {{ $movement->notes ? 'lg:grid-cols-3' : '' }}">

        {{-- Lines table --}}
        <div class="card min-w-0 {{ $movement->notes ? 'lg:col-span-2' : '' }}">
            <div class="card-header">
                <h3 class="card-title">Movement Lines</h3>
            </div>

            <div class="overflow-x-auto">
                <x-ui.table>
                    <thead>
                    <tr>
                        <th class="w-10">#</th>
                        <th>Item</th>
                        <th>Route</th>
                        <th>Batch</th>
                        <th class="text-right">Qty</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($movement->lines as $line)
                        <tr wire:key="mov-line-{{ $line->id }}" class="align-top">
                            <td class="text-xs tabular-nums text-slate-400">{{ $loop->iteration }}</td>

                            <td class="min-w-[12rem]">
                                <div class="text-sm font-medium text-slate-800 dark:text-slate-200">
                                    {{ $line->item?->name ?? '—' }}
                                </div>
                                <div class="font-mono text-xs text-slate-500 dark:text-slate-400">
                                    {{ $line->item?->sku }}
                                </div>

                                @if($line->serials->isNotEmpty())
                                    <div class="flex flex-wrap gap-1 mt-2">
                                        @foreach($line->serials as $serial)
                                            <span wire:key="mov-line-{{ $line->id }}-serial-{{ $serial->id }}"
                                                  class="badge badge-slate font-mono text-xs">
                                                    {{ $serial->serial_number }}
                                                </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>

                            <td class="whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <span class="badge badge-slate font-mono text-xs">{{ $line->fromLocation?->code ?? '—' }}</span>
                                    <x-ui.icon name="arrow-right" class="w-3.5 h-3.5 shrink-0 text-slate-400" />
                                    <span class="badge badge-blue font-mono text-xs">{{ $line->toLocation?->code ?? '—' }}</span>
                                </div>
                            </td>

                            <td class="text-sm whitespace-nowrap text-slate-500 dark:text-slate-400">
                                @if($line->batch_no)
                                    <span class="block font-mono">{{ $line->batch_no }}</span>
                                    @if($line->expiry_date)
                                        <span class="block text-xs text-slate-400">Exp. {{ $line->expiry_date->format('d M Y') }}</span>
                                    @endif
                                @else
                                    <span class="text-slate-300 dark:text-slate-600">—</span>
                                @endif
                            </td>

                            <td class="text-right whitespace-nowrap">
                                    <span class="font-semibold tabular-nums text-slate-800 dark:text-slate-200">
                                        {{ $formatQty($line->qty) }}
                                    </span>
                                <span class="ml-1 font-mono text-xs text-slate-500">{{ $line->uom?->code }}</span>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state title="No lines recorded." :cols="5" />
                    @endforelse
                    </tbody>
                </x-ui.table>
            </div>
        </div>

        {{-- Notes --}}
        @if($movement->notes)
            <div class="card self-start">
                <div class="card-header">
                    <h3 class="card-title">Notes</h3>
                </div>
                <div class="card-body">
                    <p class="text-sm whitespace-pre-line text-slate-600 dark:text-slate-400">{{ $movement->notes }}</p>
                </div>
            </div>
        @endif

    </div>

</div>
