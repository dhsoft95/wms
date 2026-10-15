<div class="space-y-6">
    {{-- Page header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Approvals Inbox</h2>
            <p class="page-sub">Delivery orders awaiting your approval before stock is allocated.</p>
        </div>
        @if ($this->totalPendingCount > 0)
            <span class="badge badge-simba !text-sm !px-3 !py-1">{{ $this->totalPendingCount }} pending</span>
        @endif
    </div>

    {{-- Empty state --}}
    @if ($this->pendingDoApprovals->isEmpty())
        <div class="card p-12 flex flex-col items-center justify-center text-center space-y-3">
            <div class="w-14 h-14 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                <x-ui.icon name="check" class="w-7 h-7 text-emerald-600" />
            </div>
            <p class="font-semibold text-slate-700 dark:text-slate-200">No pending approvals</p>
            <p class="text-sm text-slate-400">All delivery orders have been reviewed. Check back later.</p>
        </div>
    @else
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Pending Delivery Orders</h3>
                <span class="badge badge-amber">{{ $this->totalPendingCount }}</span>
            </div>

            <x-ui.table>
                <thead>
                    <tr>
                        <th>DO Number</th>
                        <th>Consignee</th>
                        <th>Virtual Warehouse</th>
                        <th>Requested By</th>
                        <th>Required Date</th>
                        <th>Approval Level</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->pendingDoApprovals as $approval)
                        <tr>
                            <td>
                                <a href="{{ route('outbound.orders.show', $approval->deliveryOrder) }}"
                                   wire:navigate
                                   class="mono font-semibold text-simba-700 dark:text-simba-400 hover:text-simba-600 transition-colors">
                                    {{ $approval->deliveryOrder->number }}
                                </a>
                            </td>
                            <td class="text-sm">
                                {{ $approval->deliveryOrder->consigneePartner?->name ?? '—' }}
                            </td>
                            <td class="text-sm">
                                {{ $approval->deliveryOrder->virtualWarehouse?->name ?? '—' }}
                            </td>
                            <td class="text-sm">
                                {{ $approval->deliveryOrder->requestedBy?->name ?? '—' }}
                            </td>
                            <td class="tabular-nums text-sm">
                                @if ($approval->deliveryOrder->required_date)
                                    @php
                                        $rd = \Illuminate\Support\Carbon::parse($approval->deliveryOrder->required_date);
                                        $isOverdue = $rd->isPast();
                                    @endphp
                                    <span class="{{ $isOverdue ? 'text-red-600 font-semibold' : '' }}">
                                        {{ $rd->format('d M Y') }}
                                    </span>
                                    @if ($isOverdue)
                                        <span class="badge badge-red ml-1">Overdue</span>
                                    @endif
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($approval->approver_role === 'department')
                                    <span class="badge badge-violet">Department</span>
                                @elseif ($approval->approver_role === 'warehouse')
                                    <span class="badge badge-simba">Warehouse</span>
                                @else
                                    <span class="badge badge-neutral">{{ ucfirst($approval->approver_role) }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button wire:click="approveOrder({{ $approval->id }})"
                                            wire:loading.attr="disabled"
                                            wire:confirm="Approve order {{ $approval->deliveryOrder->number }}?"
                                            class="btn btn-xs bg-emerald-600 hover:bg-emerald-700 text-white border-emerald-600 px-3 py-1.5 text-xs font-semibold rounded-lg">
                                        <x-ui.icon name="check" class="w-3.5 h-3.5 inline -mt-0.5" /> Approve
                                    </button>
                                    <button wire:click="openRejectModal({{ $approval->id }})"
                                            class="btn btn-xs bg-red-600 hover:bg-red-700 text-white border-red-600 px-3 py-1.5 text-xs font-semibold rounded-lg">
                                        <x-ui.icon name="x" class="w-3.5 h-3.5 inline -mt-0.5" /> Reject
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </div>
    @endif

    {{-- Reject Modal --}}
    @if ($showDoRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-xl w-full max-w-md mx-4 p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200">Reject Order</h3>
                    <button wire:click="$set('showDoRejectModal', false)"
                            class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                        <x-ui.icon name="x" class="w-5 h-5" />
                    </button>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Provide a reason for rejecting this order. The requester will be notified.
                </p>
                <div>
                    <label class="label">Rejection Notes <span class="text-red-500">*</span></label>
                    <textarea wire:model="rejectNotes" rows="4"
                              class="input resize-none"
                              placeholder="Describe why this order is being rejected…"></textarea>
                    @error('rejectNotes')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex justify-end gap-3 pt-1">
                    <button wire:click="$set('showDoRejectModal', false)" class="btn btn-secondary">Cancel</button>
                    <button wire:click="confirmReject" wire:loading.attr="disabled"
                            class="btn btn-primary bg-red-600 hover:bg-red-700 border-red-600">
                        <x-ui.icon name="x-circle" class="w-4 h-4" /> Confirm Rejection
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
