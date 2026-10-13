<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">Cycle Count</h2>
            <p class="page-sub">Session <span class="mono font-semibold text-slate-700 dark:text-slate-200">CC-2026-041</span> · Zone A · Assigned to Onesmo Mlelwa</p>
        </div>
        <div class="flex gap-2">
            <button wire:click="saveProgress" class="btn btn-secondary">Save Progress</button>
            <button wire:click="submitCount" class="btn btn-primary">Submit Count</button>
        </div>
    </div>

    <div class="card">
        <x-ui.table>
                <thead>
                    <tr><th>Bin</th><th>SKU</th><th>Description</th><th class="text-right">System Qty</th><th>Counted Qty</th><th>Variance</th></tr>
                </thead>
                <tbody>
                    @foreach ($rows as $i => $row)
                        @php
                            $variance = $row['countedQty'] !== null ? ($row['countedQty'] - $row['systemQty']) : null;
                            $vBadge = $variance === null ? 'badge-slate' : ($variance === 0 ? 'badge-green' : ($variance < 0 ? 'badge-red' : 'badge-amber'));
                            $vLabel = $variance === null ? 'Pending' : (($variance > 0 ? '+' : '') . $variance);
                        @endphp
                        <tr>
                            <td class="mono font-semibold">{{ $row['bin'] }}</td>
                            <td class="mono">{{ $row['sku'] }}</td>
                            <td>{{ $row['desc'] }}</td>
                            <td class="text-right tabular-nums">{{ number_format($row['systemQty']) }}</td>
                            <td>
                                <input
                                    wire:model.live="rows.{{ $i }}.countedQty"
                                    type="number"
                                    placeholder="Count"
                                    class="input input-sm w-28 tabular-nums"
                                >
                            </td>
                            <td><span class="badge {{ $vBadge }}">{{ $vLabel }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
        </x-ui.table>
    </div>
</div>
