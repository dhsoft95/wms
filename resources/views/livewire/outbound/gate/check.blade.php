<div class="space-y-6">
    {{-- Page header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Gate Check</h2>
            <p class="page-sub">Scan or enter a gate pass number to verify vehicle exit.</p>
        </div>
    </div>

    {{-- Search card --}}
    <div class="card max-w-lg">
        <div class="card-body space-y-4">
            <div>
                <label class="label">Gate Pass Number</label>
                <div class="flex gap-2">
                    <input wire:model="gatePassNumber" wire:keydown.enter="search"
                           type="text" placeholder="GP-2026-00001"
                           class="input font-mono uppercase flex-1" autofocus />
                    <button wire:click="search" class="btn btn-primary">Search</button>
                </div>
                @if ($scanError)
                    <p class="hint !text-red-600 mt-1">{{ $scanError }}</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Result card (shown when found) --}}
    @if ($found)
        <div class="card max-w-lg border-2 {{ $found->status === \App\Enums\Outbound\GatePassStatus::Issued ? 'border-amber-400' : 'border-emerald-400' }}">
            <div class="card-header">
                <h3 class="card-title font-mono text-lg">{{ $found->number }}</h3>
                <span class="badge {{ $found->status === \App\Enums\Outbound\GatePassStatus::Issued ? 'badge-amber' : 'badge-emerald' }}">
                    <span class="dot"></span>{{ $found->status->label() }}
                </span>
            </div>
            <div class="card-body space-y-3 text-sm">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <p class="section-label">Delivery Order</p>
                        <p class="font-mono font-semibold">{{ $found->dispatch->deliveryOrder->number }}</p>
                    </div>
                    <div>
                        <p class="section-label">Consignee</p>
                        <p>{{ $found->dispatch->deliveryOrder->consigneePartner?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="section-label">Vehicle No</p>
                        <p class="font-mono">{{ $found->dispatch->vehicle_no }}</p>
                    </div>
                    <div>
                        <p class="section-label">Driver</p>
                        <p>{{ $found->dispatch->driver_name }}</p>
                    </div>
                    <div>
                        <p class="section-label">Items</p>
                        <p class="tabular-nums">{{ $found->dispatch->items->count() }} line(s)</p>
                    </div>
                    @if ($found->exited_at)
                        <div>
                            <p class="section-label">Exited At</p>
                            <p class="tabular-nums">{{ $found->exited_at->format('d M Y H:i') }}</p>
                        </div>
                    @endif
                </div>
            </div>
            @if ($found->status === \App\Enums\Outbound\GatePassStatus::Issued)
                <div class="card-footer">
                    @can('gate.verify')
                        <button wire:click="verifyExit" class="btn btn-primary w-full text-base py-3">
                            <x-ui.icon name="check" class="w-5 h-5" /> Confirm Gate Exit
                        </button>
                    @endcan
                </div>
            @endif
        </div>
    @endif
</div>
