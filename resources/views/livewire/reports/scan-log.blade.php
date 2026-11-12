<div class="space-y-6">

    <div class="page-header">
        <div>
            <h2 class="page-title">Scan Log</h2>
            <p class="page-sub">Audit trail of all barcode scans.</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card p-4">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
            <div>
                <label class="form-label">From</label>
                <input type="date" wire:model.live="dateFrom" class="input" />
            </div>
            <div>
                <label class="form-label">To</label>
                <input type="date" wire:model.live="dateTo" class="input" />
            </div>
            <div>
                <label class="form-label">User</label>
                <select wire:model.live="userId" class="input">
                    <option value="">All users</option>
                    @foreach ($this->users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Device</label>
                <select wire:model.live="deviceId" class="input">
                    <option value="">All devices</option>
                    @foreach ($this->devices as $d)
                        <option value="{{ $d->id }}">{{ $d->code }}{{ $d->name ? ' — ' . $d->name : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Result</label>
                <select wire:model.live="success" class="input">
                    <option value="">All</option>
                    <option value="1">Success</option>
                    <option value="0">Failed</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card overflow-hidden">
        <x-ui.table>
            <thead>
                <tr>
                    <th class="px-4 py-3">Time</th>
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Device</th>
                    <th class="px-4 py-3">Route</th>
                    <th class="px-4 py-3">Raw Value</th>
                    <th class="px-4 py-3">Resolved Type</th>
                    <th class="px-4 py-3">Result</th>
                    <th class="px-4 py-3">Error</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->events as $event)
                    <tr>
                        <td class="px-4 py-2 text-sm whitespace-nowrap">{{ $event->created_at->format('Y-m-d H:i:s') }}</td>
                        <td class="px-4 py-2 text-sm">{{ $event->user?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-sm">{{ $event->scannerDevice?->code ?? '—' }}</td>
                        <td class="px-4 py-2 text-sm text-gray-500">{{ $event->route_name ?? '—' }}</td>
                        <td class="px-4 py-2 text-sm font-mono">{{ $event->raw_value }}</td>
                        <td class="px-4 py-2 text-sm">{{ $event->resolved_type ?? '—' }}</td>
                        <td class="px-4 py-2 text-sm">
                            @if ($event->success)
                                <span class="badge badge-green">OK</span>
                            @else
                                <span class="badge badge-red">Fail</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-sm text-red-600">{{ $event->error_message ?? '' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-sm text-gray-500">No scan events found.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        @if ($this->events->hasPages())
            <div class="px-4 py-3 border-t">
                {{ $this->events->links() }}
            </div>
        @endif
    </div>

</div>
