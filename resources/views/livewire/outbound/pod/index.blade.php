<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Proof of Delivery</h2>
            <p class="page-sub">Record delivery confirmation and upload POD documents.</p>
        </div>
    </div>

    <div class="card">
        {{-- Tabs + Search --}}
        <div class="card-header flex-col sm:flex-row gap-3">
            <div class="tabs overflow-x-auto">
                <button wire:click="$set('tab','awaiting')"
                        @class(['tab', 'active' => $tab === 'awaiting'])>
                    Awaiting POD
                    @php $awaitingCount = $this->awaitingOrders->total(); @endphp
                    @if ($awaitingCount > 0)
                        <span class="badge badge-amber ml-1">{{ $awaitingCount }}</span>
                    @endif
                </button>
                <button wire:click="$set('tab','delivered')"
                        @class(['tab', 'active' => $tab === 'delivered'])>
                    Delivered
                </button>
            </div>

            <div class="relative ml-auto">
                <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                <input wire:model.live.debounce.300ms="search"
                       type="text"
                       placeholder="Search DO, consignee…"
                       class="input !pl-9 sm:w-64" />
            </div>
        </div>

        {{-- ── Awaiting POD tab ─────────────────────────────────────────────── --}}
        @if ($tab === 'awaiting')
            <x-ui.table>
                <thead>
                    <tr>
                        <th>DO Number</th>
                        <th>Consignee</th>
                        <th>Dispatched</th>
                        <th>Vehicle No</th>
                        <th>Days Since Dispatch</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->awaitingOrders as $do)
                        @php
                            $latestDispatch = $do->dispatches->sortByDesc('dispatched_at')->first();
                            $dispatchedAt   = $latestDispatch?->dispatched_at;
                            $daysSince      = $dispatchedAt
                                ? (int) $dispatchedAt->startOfDay()->diffInDays(now()->startOfDay())
                                : null;
                        @endphp
                        <tr wire:key="awaiting-{{ $do->id }}">
                            <td class="mono font-semibold">{{ $do->number }}</td>
                            <td>{{ $do->consigneePartner?->name ?? '—' }}</td>
                            <td class="tabular-nums text-sm">
                                {{ $dispatchedAt?->format('d M Y H:i') ?? '—' }}
                            </td>
                            <td class="mono text-sm">
                                {{ $latestDispatch?->vehicle_no ?? '—' }}
                            </td>
                            <td class="tabular-nums text-sm @if($daysSince !== null && $daysSince > 3) text-red-600 dark:text-red-400 font-semibold @endif">
                                @if ($daysSince !== null)
                                    {{ $daysSince }} {{ Str::plural('day', $daysSince) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-right">
                                @can('uploadPod', $do)
                                    <button wire:click="openPodModal({{ $do->id }})"
                                            class="btn btn-primary btn-sm">
                                        <x-ui.icon name="check" class="w-3.5 h-3.5" /> Record POD
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="!py-12 text-center text-slate-500">
                                @if ($search)
                                    No orders match your search.
                                @else
                                    No orders awaiting POD.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.table>

            <div class="px-4 py-3 border-t border-white/40 dark:border-white/[0.06]">
                {{ $this->awaitingOrders->links() }}
            </div>
        @endif

        {{-- ── Delivered tab ────────────────────────────────────────────────── --}}
        @if ($tab === 'delivered')
            <x-ui.table>
                <thead>
                    <tr>
                        <th>DO Number</th>
                        <th>Consignee</th>
                        <th>Required Date</th>
                        <th>Delivered At</th>
                        <th>Condition</th>
                        <th>POD File</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->deliveredOrders as $do)
                        @php
                            $pod = $do->proofOfDelivery;
                            $conditionBadge = match($pod?->condition) {
                                'good'     => 'badge-green',
                                'partial'  => 'badge-amber',
                                'rejected' => 'badge-red',
                                default    => 'badge-neutral',
                            };
                            $conditionLabel = match($pod?->condition) {
                                'good'     => 'Good',
                                'partial'  => 'Partial',
                                'rejected' => 'Rejected',
                                default    => '—',
                            };
                        @endphp
                        <tr wire:key="delivered-{{ $do->id }}">
                            <td class="mono font-semibold">{{ $do->number }}</td>
                            <td>{{ $do->consigneePartner?->name ?? '—' }}</td>
                            <td class="tabular-nums text-sm">
                                {{ $do->required_date?->format('d M Y') ?? '—' }}
                            </td>
                            <td class="tabular-nums text-sm">
                                {{ $pod?->received_at?->format('d M Y H:i') ?? '—' }}
                            </td>
                            <td>
                                @if ($pod)
                                    <span class="badge {{ $conditionBadge }}">
                                        <span class="dot"></span>{{ $conditionLabel }}
                                    </span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($pod?->file_path)
                                    <a href="{{ Storage::url($pod->file_path) }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="inline-flex items-center gap-1 text-sm text-simba-600 dark:text-simba-400 hover:underline">
                                        <x-ui.icon name="document" class="w-4 h-4 shrink-0" />
                                        View POD
                                    </a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="!py-12 text-center text-slate-500">
                                @if ($search)
                                    No orders match your search.
                                @else
                                    No delivered orders found.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.table>

            <div class="px-4 py-3 border-t border-white/40 dark:border-white/[0.06]">
                {{ $this->deliveredOrders->links() }}
            </div>
        @endif
    </div>

    {{-- ── POD Modal ─────────────────────────────────────────────────────────── --}}
    @if ($showPodModal)
        <x-ui.modal title="Record Proof of Delivery" wireClose="closePodModal" maxWidth="xl">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                {{-- Received By --}}
                <div class="sm:col-span-2">
                    <label class="label" for="pod-received-by">
                        Received By <span class="required-mark">*</span>
                    </label>
                    <input wire:model="receivedBy"
                           id="pod-received-by"
                           type="text"
                           placeholder="Rashid Ally"
                           class="input w-full @error('receivedBy') border-red-400 @enderror" />
                    @error('receivedBy')
                        <p class="hint hint-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Received At --}}
                <div>
                    <label class="label" for="pod-received-at">
                        Received At <span class="required-mark">*</span>
                    </label>
                    <input wire:model="receivedAt"
                           id="pod-received-at"
                           type="datetime-local"
                           class="input w-full tabular-nums @error('receivedAt') border-red-400 @enderror" />
                    @error('receivedAt')
                        <p class="hint hint-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Condition --}}
                <div>
                    <label class="label" for="pod-condition">
                        Condition <span class="required-mark">*</span>
                    </label>
                    <select wire:model="condition"
                            id="pod-condition"
                            class="input w-full @error('condition') border-red-400 @enderror">
                        <option value="good">Good — delivered in full</option>
                        <option value="partial">Partial — some items not accepted</option>
                        <option value="rejected">Rejected — delivery refused</option>
                    </select>
                    @error('condition')
                        <p class="hint hint-error">{{ $message }}</p>
                    @enderror
                </div>

            </div>

            {{-- Notes --}}
            <div>
                <label class="label" for="pod-notes">Notes</label>
                <textarea wire:model="podNotes"
                          id="pod-notes"
                          rows="3"
                          placeholder="Optional remarks…"
                          class="input w-full resize-none"></textarea>
            </div>

            {{-- POD File upload --}}
            <div>
                <label class="label" for="pod-file">Upload POD Document</label>
                <input wire:model="podFile"
                       id="pod-file"
                       type="file"
                       accept=".pdf,.jpg,.jpeg,.png"
                       class="block w-full text-sm text-slate-600 dark:text-slate-400
                              file:mr-3 file:py-1.5 file:px-3
                              file:rounded-md file:border-0
                              file:text-sm file:font-medium
                              file:bg-simba-50 file:text-simba-700
                              dark:file:bg-simba-900/30 dark:file:text-simba-300
                              hover:file:bg-simba-100 dark:hover:file:bg-simba-900/50
                              cursor-pointer" />
                <p class="hint">PDF, JPG or PNG — max 10 MB</p>
                @error('podFile')
                    <p class="hint hint-error">{{ $message }}</p>
                @enderror

                {{-- Upload progress --}}
                <div wire:loading wire:target="podFile" class="mt-2 flex items-center gap-2 text-sm text-slate-500">
                    <svg class="animate-spin h-4 w-4 text-simba-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                    Uploading…
                </div>
            </div>

            <x-slot:footer>
                <button type="button"
                        wire:click="closePodModal"
                        class="btn btn-secondary">
                    Cancel
                </button>
                <button type="button"
                        wire:click="confirmPod"
                        wire:loading.attr="disabled"
                        class="btn btn-primary">
                    <span wire:loading.remove wire:target="confirmPod">
                        <x-ui.icon name="check" class="w-4 h-4" /> Confirm POD
                    </span>
                    <span wire:loading wire:target="confirmPod">Saving…</span>
                </button>
            </x-slot:footer>

        </x-ui.modal>
    @endif

</div>
