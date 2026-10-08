<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">Stock on Hand &amp; Traceability</h2>
            <p class="page-sub">Live balances by SKU, location and virtual warehouse.</p>
        </div>
        <button wire:click="export" class="btn btn-secondary">
            <x-ui.icon name="download" class="w-4 h-4" /> Export
        </button>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card p-4"><p class="text-xs text-slate-500">Active SKUs</p><p class="mt-1 text-2xl font-bold tabular-nums">1,284</p></div>
        <div class="card p-4"><p class="text-xs text-slate-500">Total Units</p><p class="mt-1 text-2xl font-bold tabular-nums">48,250</p></div>
        <div class="card p-4"><p class="text-xs text-slate-500">Allocated</p><p class="mt-1 text-2xl font-bold tabular-nums text-amber-600">9,870</p></div>
        <div class="card p-4"><p class="text-xs text-slate-500">Below Reorder Point</p><p class="mt-1 text-2xl font-bold tabular-nums text-red-600">7</p></div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="relative flex-1 sm:flex-none">
                <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                <input wire:model.live="search" type="text" placeholder="Search SKU, description, bin" class="input !pl-9 sm:w-72">
            </div>
            <select wire:model.live="warehouseFilter" class="input !w-auto">
                <option value="">All warehouses</option>
                <option>DAR-General Store</option>
                <option>DAR-Tech Vault</option>
                <option>Cold Room</option>
            </select>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>SKU</th><th>Description</th><th>Warehouse</th><th>Bin</th>
                        <th class="text-right">Total</th><th class="text-right">Allocated</th><th class="text-right">Available</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="mono font-semibold">{{ $row['sku'] }}</td>
                            <td>
                                <p>{{ $row['desc'] }}</p>
                                <p class="text-xs text-slate-500">{{ $row['cat'] }}</p>
                            </td>
                            <td>{{ $row['warehouse'] }}</td>
                            <td class="mono">{{ $row['bin'] }}</td>
                            <td class="text-right tabular-nums font-semibold">{{ number_format($row['total']) }}</td>
                            <td class="text-right tabular-nums text-amber-600">{{ number_format($row['allocated']) }}</td>
                            <td class="text-right tabular-nums font-bold {{ $row['available'] < 500 ? 'text-red-600' : 'text-emerald-600' }}">{{ number_format($row['available']) }}</td>
                            <td><span class="badge {{ $row['badge'] }}">{{ $row['label'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="!py-12 text-center text-slate-500">No stock records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
