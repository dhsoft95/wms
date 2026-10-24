<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Dispatch &amp; Gate</h2>
            <p class="page-sub">Process outbound shipments and record gate exit.</p>
        </div>
    </div>

    <div class="card">
        {{-- Tabs + Search --}}
        <div class="card-header flex-col sm:flex-row gap-3">
            <div class="tabs overflow-x-auto">
                <button wire:click="$set('tab','ready')"
                        @class(['tab', 'active' => $tab === 'ready'])>
                    Ready to Dispatch
                </button>
                <button wire:click="$set('tab','recent')"
                        @class(['tab', 'active' => $tab === 'recent'])>
                    Recent Dispatches
                </button>
            </div>

            <div class="relative ml-auto">
                <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                <input wire:model.live.debounce.300ms="search"
                       type="text"
                       placeholder="{{ $tab === 'ready' ? 'Search DO, consignee…' : 'Search dispatch, DO…' }}"
                       class="input !pl-9 sm:w-64" />
            </div>
        </div>

        {{-- ── Ready to Dispatch tab ────────────────────────────────────────── --}}
        @if ($tab === 'ready')
            <x-ui.table>
                <thead>
                    <tr>
                        <th>DO Number</th>
                        <th>Consignee</th>
                        <th>VW / Warehouse</th>
                        <th>Package Type</th>
                        <th>Required Date</th>
                        <th>Lines</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->readyOrders as $do)
                        <tr wire:key="ready-{{ $do->id }}">
                            <td class="mono font-semibold">{{ $do->number }}</td>
                            <td>{{ $do->consigneePartner?->name ?? '—' }}</td>
                            <td>
                                <div class="text-sm font-medium text-slate-800 dark:text-slate-100">
                                    {{ $do->virtualWarehouse?->name ?? '—' }}
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $do->warehouse?->name ?? '—' }}
                                </div>
                            </td>
                            <td class="text-sm">
                                @if ($do->package_type)
                                    <span class="badge {{ $do->package_type === \App\Enums\Outbound\PackageType::Container ? 'badge-blue' : 'badge-slate' }}">
                                        {{ $do->package_type->label() }}
                                    </span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="tabular-nums text-sm @if($do->required_date && $do->required_date->isPast()) text-red-600 dark:text-red-400 font-semibold @endif">
                                {{ $do->required_date?->format('d M Y') ?? '—' }}
                                @if ($do->required_date && $do->required_date->isPast())
                                    <span class="block text-xs font-normal">Overdue</span>
                                @endif
                            </td>
                            <td class="tabular-nums text-sm">
                                {{ $do->lines_count ?? $do->lines->count() }}
                            </td>
                            <td class="text-right">
                                <button wire:click="openDispatchModal({{ $do->id }})"
                                        class="btn btn-primary btn-sm">
                                    <x-ui.icon name="arrow-right" class="w-3.5 h-3.5" /> Dispatch
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="!py-12 text-center text-slate-500">
                                @if ($search)
                                    No orders match your search.
                                @else
                                    No orders ready to dispatch.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.table>

            <div class="px-4 py-3 border-t border-white/40 dark:border-white/[0.06]">
                {{ $this->readyOrders->links() }}
            </div>
        @endif

        {{-- ── Recent Dispatches tab ────────────────────────────────────────── --}}
        @if ($tab === 'recent')
            <x-ui.table>
                <thead>
                    <tr>
                        <th>Dispatch #</th>
                        <th>DO Number</th>
                        <th>Consignee</th>
                        <th>Vehicle Reg</th>
                        <th>Driver</th>
                        <th>Dispatched At</th>
                        <th>Gate Pass</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->recentDispatches as $dispatch)
                        <tr wire:key="dispatch-{{ $dispatch->id }}">
                            <td class="mono font-semibold">{{ $dispatch->number }}</td>
                            <td class="mono text-sm text-simba-600 dark:text-simba-400">
                                {{ $dispatch->deliveryOrder?->number ?? '—' }}
                            </td>
                            <td>{{ $dispatch->deliveryOrder?->consigneePartner?->name ?? '—' }}</td>
                            <td class="mono text-sm">{{ $dispatch->vehicle_reg_no ?? '—' }}</td>
                            <td class="text-sm">{{ $dispatch->driver_name ?? '—' }}</td>
                            <td class="tabular-nums text-sm">
                                {{ $dispatch->dispatched_at?->format('d M Y H:i') ?? '—' }}
                            </td>
                            <td>
                                @if ($dispatch->gatePass)
                                    <div class="text-sm mono">{{ $dispatch->gatePass->number }}</div>
                                    @php
                                        $gpStatus = $dispatch->gatePass->status;
                                        $gpBadge  = $gpStatus->value === 'exited' ? 'badge-green' : 'badge-amber';
                                    @endphp
                                    <span class="badge {{ $gpBadge }} mt-1">
                                        <span class="dot"></span>{{ $gpStatus->label() }}
                                    </span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <x-ui.pdf-viewer
                                        :url="route('outbound.dispatch.dn.download', $dispatch->id)"
                                        :label="'ODN — ' . $dispatch->number"
                                        btnClass="btn btn-secondary btn-sm gap-1.5">
                                        <x-ui.icon name="document-arrow-down" class="w-3.5 h-3.5" /> ODN
                                    </x-ui.pdf-viewer>

                                    @if ($dispatch->gatePass && $dispatch->gatePass->status->value === 'issued')
                                        <button wire:click="verifyGateExit({{ $dispatch->gatePass->id }})"
                                                wire:confirm="Mark gate exit for {{ $dispatch->number }}?"
                                                class="btn btn-primary btn-sm">
                                            <x-ui.icon name="check" class="w-3.5 h-3.5" /> Verify Exit
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="!py-12 text-center text-slate-500">
                                @if ($search)
                                    No dispatches match your search.
                                @else
                                    No recent dispatches found.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.table>

            <div class="px-4 py-3 border-t border-white/40 dark:border-white/[0.06]">
                {{ $this->recentDispatches->links() }}
            </div>
        @endif
    </div>

    {{-- ── Dispatch Modal ────────────────────────────────────────────────────── --}}
    @if ($showDispatchModal)
        <x-ui.modal title="Confirm Dispatch" wireClose="closeDispatchModal" maxWidth="xl">

            @php
                $isContainer = $packageType === \App\Enums\Outbound\PackageType::Container->value;
                $lines       = $this->actionDo?->lines ?? collect();
                $shortLines  = $lines->filter(
                    fn ($l) => bccomp((string) $l->picked_qty, (string) $l->requested_qty_base, 4) < 0
                );
                $errorClass  = '!border-rose-400 focus:!ring-rose-500/30';
            @endphp

            <div class="space-y-6">

                {{-- Summary strip --}}
                <div class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 px-4 py-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
                            <x-ui.icon name="cube" class="w-5 h-5 text-slate-500" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[11px] font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Package Type</p>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100 truncate">
                                {{ $packageType ? \App\Enums\Outbound\PackageType::from($packageType)->label() : 'Not set' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                <span class="inline-flex items-center rounded-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 px-2.5 py-1 text-xs font-medium text-slate-600 dark:text-slate-300 tabular-nums">
                    {{ $lines->count() }} {{ Str::plural('line', $lines->count()) }}
                </span>
                        @if ($shortLines->isNotEmpty())
                            <span class="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900/40 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:text-amber-300 tabular-nums">
                        {{ $shortLines->count() }} short
                    </span>
                        @endif
                    </div>
                </div>

                {{-- Short-pick warning --}}
                @if ($shortLines->isNotEmpty())
                    <div class="rounded-xl border border-amber-200 dark:border-amber-800/60 bg-amber-50/70 dark:bg-amber-900/10 overflow-hidden">
                        <div class="flex items-start gap-3 px-4 pt-4">
                            <x-ui.icon name="exclamation-triangle" class="w-5 h-5 mt-0.5 text-amber-600 dark:text-amber-400 shrink-0" />
                            <div>
                                <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Short-pick detected</p>
                                <p class="mt-0.5 text-xs leading-relaxed text-amber-700 dark:text-amber-400">
                                    These lines were not fully picked. Only picked quantities will be dispatched.
                                </p>
                            </div>
                        </div>

                        <div class="mx-4 mt-3 overflow-x-auto rounded-lg border border-amber-200 dark:border-amber-800/60 bg-white dark:bg-slate-900">
                            <table class="w-full text-xs">
                                <thead class="bg-amber-50 dark:bg-amber-900/20">
                                <tr class="text-[11px] uppercase tracking-wider text-amber-700 dark:text-amber-400">
                                    <th class="px-3 py-2 text-left font-semibold">Item</th>
                                    <th class="px-3 py-2 text-right font-semibold w-20">Ordered</th>
                                    <th class="px-3 py-2 text-right font-semibold w-20">Picked</th>
                                    <th class="px-3 py-2 text-right font-semibold w-24">Remaining</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($shortLines as $line)
                                    @php
                                        $remaining = bcsub((string) $line->requested_qty_base, (string) $line->picked_qty, 4);
                                        $uom       = $line->uom?->code;
                                    @endphp
                                    <tr class="align-middle">
                                        <td class="px-3 py-2">
                                            <p class="font-medium text-slate-800 dark:text-slate-200 truncate max-w-[14rem]">{{ $line->item?->name }}</p>
                                            <p class="font-mono text-[11px] text-slate-400">{{ $line->item?->sku }}</p>
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums text-slate-600 dark:text-slate-400">
                                            {{ number_format((float) $line->requested_qty_base, 2) }}
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums font-medium text-emerald-700 dark:text-emerald-400">
                                            {{ number_format((float) $line->picked_qty, 2) }}
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums font-semibold text-amber-700 dark:text-amber-400 whitespace-nowrap">
                                            {{ number_format((float) $remaining, 2) }}
                                            @if ($uom)
                                                <span class="ml-0.5 text-[10px] font-medium text-slate-400">{{ $uom }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <label class="m-4 flex items-start gap-3 rounded-lg border border-amber-200 dark:border-amber-800/60 bg-white dark:bg-slate-900 px-3 py-2.5 cursor-pointer hover:border-amber-300 dark:hover:border-amber-700 transition-colors">
                            <input type="checkbox"
                                   wire:model="createBackorder"
                                   class="mt-0.5 rounded border-amber-400 text-simba-600 focus:ring-simba-600">
                            <span>
                        <span class="block text-sm font-medium text-slate-800 dark:text-slate-200">Create a backorder</span>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">A new order will be raised for the remaining quantities.</span>
                    </span>
                        </label>
                    </div>
                @endif

                {{-- Vehicle --}}
                <section class="space-y-3">
                    <h4 class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Vehicle</h4>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4">
                        <div class="space-y-1.5">
                            <label class="label" for="dispatch-vehicle">
                                Registration No <span class="text-rose-500">*</span>
                            </label>
                            <input wire:model="vehicleRegNo"
                                   id="dispatch-vehicle"
                                   type="text"
                                   placeholder="T 452 ABC"
                                   class="input w-full mono uppercase @error('vehicleRegNo') {{ $errorClass }} @enderror" />
                            @error('vehicleRegNo')
                            <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-1.5">
                            <label class="label" for="dispatch-seal">
                                Seal No
                                @if ($isContainer)
                                    <span class="text-rose-500">*</span>
                                @else
                                    <span class="text-xs font-normal text-slate-400">(optional)</span>
                                @endif
                            </label>
                            <input wire:model="sealNo"
                                   id="dispatch-seal"
                                   type="text"
                                   placeholder="SL-55021"
                                   class="input w-full mono uppercase @error('sealNo') {{ $errorClass }} @enderror" />
                            @error('sealNo')
                            <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="label" for="dispatch-transporter">Transporter</label>
                            <select wire:model="transporterPartnerId"
                                    id="dispatch-transporter"
                                    class="input w-full">
                                <option value="">None / Own transport</option>
                                @foreach ($this->transporters as $transporter)
                                    <option value="{{ $transporter->id }}">{{ $transporter->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </section>

                {{-- Container (container shipments only) --}}
                @if ($isContainer)
                    <section class="space-y-3 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                        <h4 class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Container</h4>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4">
                            <div class="space-y-1.5">
                                <label class="label" for="dispatch-container-no">
                                    Container No <span class="text-rose-500">*</span>
                                </label>
                                <input wire:model="containerNo"
                                       id="dispatch-container-no"
                                       type="text"
                                       placeholder="TCKU 123456-7"
                                       class="input w-full mono uppercase @error('containerNo') {{ $errorClass }} @enderror" />
                                @error('containerNo')
                                <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-1.5">
                                <label class="label" for="dispatch-container-size">
                                    Container Size <span class="text-rose-500">*</span>
                                </label>
                                <select wire:model="containerSize"
                                        id="dispatch-container-size"
                                        class="input w-full @error('containerSize') {{ $errorClass }} @enderror">
                                    <option value="">Select size</option>
                                    @foreach ($this->containerSizes as $size)
                                        <option value="{{ $size->value }}">{{ $size->label() }}</option>
                                    @endforeach
                                </select>
                                @error('containerSize')
                                <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </section>
                @endif

                {{-- Driver --}}
                <section class="space-y-3">
                    <h4 class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Driver</h4>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4">
                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="label" for="dispatch-driver">
                                Full Name <span class="text-rose-500">*</span>
                            </label>
                            <input wire:model="driverName"
                                   id="dispatch-driver"
                                   type="text"
                                   placeholder="Juma Kassim"
                                   class="input w-full @error('driverName') {{ $errorClass }} @enderror" />
                            @error('driverName')
                            <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-1.5">
                            <label class="label" for="dispatch-phone">Phone</label>
                            <input wire:model="driverPhone"
                                   id="dispatch-phone"
                                   type="tel"
                                   placeholder="+255 712 000 000"
                                   class="input w-full tabular-nums @error('driverPhone') {{ $errorClass }} @enderror" />
                            @error('driverPhone')
                            <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-1.5">
                            <label class="label" for="dispatch-id-no">ID / License No</label>
                            <input wire:model="driverIdNo"
                                   id="dispatch-id-no"
                                   type="text"
                                   placeholder="DL-4410923"
                                   class="input w-full mono uppercase @error('driverIdNo') {{ $errorClass }} @enderror" />
                            @error('driverIdNo')
                            <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </section>

                {{-- Notes --}}
                <section class="space-y-1.5">
                    <label class="label" for="dispatch-notes">Notes</label>
                    <textarea wire:model="dispatchNotes"
                              id="dispatch-notes"
                              rows="3"
                              placeholder="Optional dispatch notes..."
                              class="input w-full resize-none"></textarea>
                </section>

            </div>

            <x-slot:footer>
                <div class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-end">
                    <button type="button"
                            wire:click="closeDispatchModal"
                            class="btn btn-secondary w-full sm:w-auto">
                        Cancel
                    </button>
                    <button type="button"
                            wire:click="confirmDispatch"
                            wire:loading.attr="disabled"
                            wire:target="confirmDispatch"
                            class="btn btn-primary w-full sm:w-auto inline-flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="confirmDispatch" class="inline-flex items-center gap-2">
                    Confirm Dispatch
                    <x-ui.icon name="arrow-right" class="w-4 h-4" />
                </span>
                        <span wire:loading wire:target="confirmDispatch" class="inline-flex items-center gap-2">
                    <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    Confirming...
                </span>
                    </button>


                </div>
            </x-slot:footer>

        </x-ui.modal>
    @endif

</div>
