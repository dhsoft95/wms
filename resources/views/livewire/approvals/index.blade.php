<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">Approvals Inbox</h2>
            <p class="page-sub">Requests that need a supervisor decision before stock records are updated.</p>
        </div>
        <span class="badge badge-simba !text-sm !px-3 !py-1">{{ $pendingCount }} pending</span>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="tabs">
                @foreach ([['all','All'],['adjustment','Adjustments'],['transfer','Transfers'],['discrepancy','Discrepancies']] as [$val, $lbl])
                    <button
                        wire:click="$set('statusFilter', '{{ $val }}')"
                        @class(['tab', 'active' => $statusFilter === $val])
                    >{{ $lbl }}</button>
                @endforeach
            </div>
        </div>

        <x-ui.table>
                <thead>
                    <tr>
                        <th>Request</th><th>Details</th><th>Requested By</th><th>Submitted</th>
                        <th class="text-right">Impact</th><th class="text-right">Decision</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($filteredRows as $row)
                        <tr>
                            <td>
                                <p class="mono font-semibold">{{ $row['ref'] }}</p>
                                <span class="badge {{ $row['badge'] }} mt-1">{{ $row['label'] }}</span>
                            </td>
                            <td class="text-slate-600 dark:text-slate-300">
                                {{ $row['details'] }}<br>
                                <span class="text-xs text-slate-500">{{ $row['sub'] }}</span>
                            </td>
                            <td>{{ $row['by'] }}</td>
                            <td class="tabular-nums">{{ $row['at'] }}</td>
                            <td class="text-right tabular-nums font-semibold {{ $row['impactColor'] }}">{{ $row['impact'] }}</td>
                            <td class="text-right">
                                <x-ui.row-actions :label="'Actions for ' . $row['ref']">
                                    <x-ui.row-actions.item icon="check" label="Approve" tone="success" wire:click="approve({{ $row['id'] }})" />
                                    <x-ui.row-actions.divider />
                                    <x-ui.row-actions.item icon="x" label="Reject" tone="danger" wire:click="reject({{ $row['id'] }})" />
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="!py-12 text-center text-slate-500">All caught up. No pending approvals.</td>
                        </tr>
                    @endforelse
                </tbody>
        </x-ui.table>
    </div>
</div>
