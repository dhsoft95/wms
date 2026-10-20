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

            {{-- Package Type (read-only) --}}
            @php $isContainer = $packageType === \App\Enums\Outbound\PackageType::Container->value; @endphp
            <div class="flex items-center gap-3 p-3 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
                <x-ui.icon name="cube" class="w-5 h-5 text-slate-500 shrink-0" />
                <div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Package Type</p>
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                        {{ $packageType ? \App\Enums\Outbound\PackageType::from($packageType)->label() : '— Not set —' }}
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                {{-- Vehicle Registration No --}}
                <div>
                    <label class="label" for="dispatch-vehicle">
                        Vehicle Registration No <span class="text-rose-500">*</span>
                    </label>
                    <input wire:model="vehicleRegNo"
                           id="dispatch-vehicle"
                           type="text"
                           placeholder="T 452 ABC"
                           class="input w-full mono uppercase @error('vehicleRegNo') border-red-400 @enderror" />
                    @error('vehicleRegNo')
                        <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Seal No (required for container, optional for loose cargo) --}}
                <div>
                    <label class="label" for="dispatch-seal">
                        Seal No
                        @if ($isContainer)
                            <span class="text-rose-500">*</span>
                        @endif
                    </label>
                    <input wire:model="sealNo"
                           id="dispatch-seal"
                           type="text"
                           placeholder="SL-55021"
                           class="input w-full mono @error('sealNo') border-red-400 @enderror" />
                    @error('sealNo')
                        <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Container fields — only shown for container shipments --}}
                @if ($isContainer)
                    {{-- Container No --}}
                    <div>
                        <label class="label" for="dispatch-container-no">
                            Container No <span class="text-rose-500">*</span>
                        </label>
                        <input wire:model="containerNo"
                               id="dispatch-container-no"
                               type="text"
                               placeholder="TCKU 123456-7"
                               class="input w-full mono uppercase @error('containerNo') border-red-400 @enderror" />
                        @error('containerNo')
                            <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Container Size --}}
                    <div>
                        <label class="label" for="dispatch-container-size">
                            Container Size <span class="text-rose-500">*</span>
                        </label>
                        <select wire:model="containerSize"
                                id="dispatch-container-size"
                                class="input w-full @error('containerSize') border-red-400 @enderror">
                            <option value="">— Select size —</option>
                            @foreach ($this->containerSizes as $size)
                                <option value="{{ $size->value }}">{{ $size->label() }}</option>
                            @endforeach
                        </select>
                        @error('containerSize')
                            <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                {{-- Driver Name --}}
                <div>
                    <label class="label" for="dispatch-driver">
                        Driver Name <span class="text-rose-500">*</span>
                    </label>
                    <input wire:model="driverName"
                           id="dispatch-driver"
                           type="text"
                           placeholder="Juma Kassim"
                           class="input w-full @error('driverName') border-red-400 @enderror" />
                    @error('driverName')
                        <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Driver Phone --}}
                <div>
                    <label class="label" for="dispatch-phone">Driver Phone</label>
                    <input wire:model="driverPhone"
                           id="dispatch-phone"
                           type="tel"
                           placeholder="+255 712 000 000"
                           class="input w-full tabular-nums @error('driverPhone') border-red-400 @enderror" />
                    @error('driverPhone')
                        <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Driver ID No --}}
                <div>
                    <label class="label" for="dispatch-id-no">Driver ID No</label>
                    <input wire:model="driverIdNo"
                           id="dispatch-id-no"
                           type="text"
                           placeholder="DL-4410923"
                           class="input w-full mono @error('driverIdNo') border-red-400 @enderror" />
                    @error('driverIdNo')
                        <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Transporter --}}
                <div>
                    <label class="label" for="dispatch-transporter">Transporter</label>
                    <select wire:model="transporterPartnerId"
                            id="dispatch-transporter"
                            class="input w-full">
                        <option value="">— None / Own transport —</option>
                        @foreach ($this->transporters as $transporter)
                            <option value="{{ $transporter->id }}">{{ $transporter->name }}</option>
                        @endforeach
                    </select>
                </div>

            </div>

            {{-- Notes --}}
            <div>
                <label class="label" for="dispatch-notes">Notes</label>
                <textarea wire:model="dispatchNotes"
                          id="dispatch-notes"
                          rows="3"
                          placeholder="Optional dispatch notes…"
                          class="input w-full resize-none"></textarea>
            </div>

            <x-slot:footer>
                <button type="button"
                        wire:click="closeDispatchModal"
                        class="btn btn-secondary">
                    Cancel
                </button>
                <button type="button"
                        wire:click="confirmDispatch"
                        wire:loading.attr="disabled"
                        class="btn btn-primary">
                    <span wire:loading.remove wire:target="confirmDispatch">
                        <x-ui.icon name="arrow-right" class="w-4 h-4" /> Confirm Dispatch
                    </span>
                    <span wire:loading wire:target="confirmDispatch">Confirming…</span>
                </button>
            </x-slot:footer>

        </x-ui.modal>
    @endif

</div>
