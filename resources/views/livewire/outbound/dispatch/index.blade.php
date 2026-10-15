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
                            <td colspan="6" class="!py-12 text-center text-slate-500">
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
                        <th>Vehicle No</th>
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
                            <td class="mono text-sm">{{ $dispatch->vehicle_no ?? '—' }}</td>
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
                                @if ($dispatch->gatePass && $dispatch->gatePass->status->value === 'issued')
                                    <button wire:click="verifyGateExit({{ $dispatch->gatePass->id }})"
                                            wire:confirm="Mark gate exit for {{ $dispatch->number }}?"
                                            class="btn btn-primary btn-sm">
                                        <x-ui.icon name="check" class="w-3.5 h-3.5" /> Verify Exit
                                    </button>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
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

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                {{-- Vehicle Registration No --}}
                <div>
                    <label class="label" for="dispatch-vehicle">
                        Vehicle Registration No <span class="text-rose-500">*</span>
                    </label>
                    <input wire:model="vehicleNo"
                           id="dispatch-vehicle"
                           type="text"
                           placeholder="T 452 ABC"
                           class="input w-full mono uppercase @error('vehicleNo') border-red-400 @enderror" />
                    @error('vehicleNo')
                        <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Seal No --}}
                <div>
                    <label class="label" for="dispatch-seal">Seal No</label>
                    <input wire:model="sealNo"
                           id="dispatch-seal"
                           type="text"
                           placeholder="SL-55021"
                           class="input w-full mono" />
                </div>

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
