<div class="space-y-6">
    <div class="page-header no-print">
        <div>
            <h2 class="page-title">Print Document</h2>
            <p class="page-sub">Review and print the goods received note.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('reports.index') }}" wire:navigate class="btn btn-secondary no-print">Back</a>
            <button type="button" onclick="window.print()" class="btn btn-primary no-print">
                <x-ui.icon name="printer" class="w-4 h-4" /> Print
            </button>
        </div>
    </div>

    <div class="card print:shadow-none print:border-none" id="print-area">
        <div class="card-body space-y-6">
            {{-- Header --}}
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">Goods Received Note</p>
                    <p class="text-sm text-slate-500 mt-0.5">Warehouse Management System</p>
                </div>
                <div class="text-right">
                    <p class="text-xl font-bold font-mono text-simba-600">{{ $grn['number'] }}</p>
                    <p class="text-xs text-slate-500">{{ $grn['receivedAt'] }}</p>
                </div>
            </div>

            {{-- Meta --}}
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 pt-4 border-t border-slate-200 dark:border-slate-700">
                <div>
                    <p class="section-label">Container</p>
                    <p class="font-semibold mono">{{ $grn['container'] }}</p>
                </div>
                <div>
                    <p class="section-label">Supplier</p>
                    <p class="font-semibold">{{ $grn['supplier'] }}</p>
                </div>
                <div>
                    <p class="section-label">Warehouse</p>
                    <p class="font-semibold">{{ $grn['warehouse'] }}</p>
                </div>
                <div>
                    <p class="section-label">Received at</p>
                    <p class="font-semibold tabular-nums">{{ $grn['receivedAt'] }}</p>
                </div>
                <div>
                    <p class="section-label">Prepared by</p>
                    <p class="font-semibold">{{ $grn['preparedBy'] }}</p>
                </div>
            </div>

            {{-- Lines table --}}
            <div class="pt-4 border-t border-slate-200 dark:border-slate-700">
                <p class="section-label mb-3">Line Items</p>
                <table class="table">
                    <thead>
                        <tr>
                            <th>SKU Code</th>
                            <th>Description</th>
                            <th class="text-right">Expected</th>
                            <th class="text-right">Received</th>
                            <th class="text-right">Variance</th>
                            <th>Condition</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $line)
                            <tr>
                                <td class="mono">{{ $line['sku'] }}</td>
                                <td>{{ $line['description'] }}</td>
                                <td class="text-right tabular-nums">{{ number_format($line['expected']) }}</td>
                                <td class="text-right tabular-nums">{{ number_format($line['received']) }}</td>
                                <td class="text-right tabular-nums {{ ($line['received'] - $line['expected']) < 0 ? 'text-red-600 font-semibold' : 'text-green-600' }}">
                                    {{ number_format($line['received'] - $line['expected']) }}
                                </td>
                                <td>{{ $line['condition'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Signature blocks --}}
            <div class="grid grid-cols-2 gap-12 pt-8">
                <div>
                    <div class="h-12 border-b border-slate-400 mb-2"></div>
                    <p class="text-xs text-slate-500">Receiver signature &amp; stamp</p>
                </div>
                <div>
                    <div class="h-12 border-b border-slate-400 mb-2"></div>
                    <p class="text-xs text-slate-500">Warehouse manager signature</p>
                </div>
            </div>
        </div>
    </div>
</div>
