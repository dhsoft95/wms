<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; margin: 0; padding: 20px; }
        .header { display: table; width: 100%; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header-left { display: table-cell; width: 60%; }
        .header-right { display: table-cell; width: 40%; text-align: right; vertical-align: top; }
        .brand-name { font-size: 18px; font-weight: bold; }
        .doc-title { font-size: 16px; font-weight: bold; color: #333; margin-bottom: 5px; }
        .doc-sub { font-size: 10px; color: #666; }
        .info-grid { display: table; width: 100%; margin-bottom: 15px; }
        .info-col { display: table-cell; width: 50%; vertical-align: top; padding-right: 10px; }
        .info-label { font-weight: bold; font-size: 10px; color: #666; text-transform: uppercase; }
        .info-value { font-size: 11px; margin-bottom: 8px; }
        table.lines { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table.lines th { background: #f5f5f5; border: 1px solid #ccc; padding: 5px 8px; font-size: 10px; text-align: left; }
        table.lines td { border: 1px solid #ddd; padding: 5px 8px; font-size: 10px; vertical-align: top; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .badge-good { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 9px; font-weight: bold; background: #d1fae5; color: #065f46; }
        .badge-damaged { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 9px; font-weight: bold; background: #fef3c7; color: #92400e; }
        .totals-row { background: #f9fafb; font-weight: bold; }
        .signatures { display: table; width: 100%; margin-top: 40px; }
        .sig-col { display: table-cell; width: 50%; text-align: center; padding: 0 10px; }
        .sig-line { border-top: 1px solid #333; margin-top: 40px; padding-top: 5px; font-size: 10px; }
        .section-title { font-size: 11px; font-weight: bold; margin: 12px 0 6px; color: #444; border-bottom: 1px solid #ddd; padding-bottom: 3px; }
    </style>
</head>
<body>

    {{-- Header --}}
    <div class="header">
        <div class="header-left">
            <div class="brand-name">{{ $brandName }}</div>
            <div class="doc-title">GOODS RECEIVED NOTE (GRN)</div>
            <div class="doc-sub">Original — retain for your records</div>
        </div>
        <div class="header-right">
            <div><strong>GRN No:</strong> {{ $grn->number }}</div>
            @if ($grn->purchaseOrder)
                <div><strong>PO No:</strong> {{ $grn->purchaseOrder->number }}</div>
            @endif
            <div><strong>Date:</strong> {{ ($grn->confirmed_at ?? $grn->created_at)->format('d M Y') }}</div>
            <div><strong>Warehouse:</strong> {{ $grn->warehouse?->name ?? '—' }}</div>
            @if ($grn->receivingDock)
                <div><strong>Dock:</strong> {{ $grn->receivingDock->code }}</div>
            @endif
        </div>
    </div>

    {{-- Supplier / Transport --}}
    <div class="info-grid">
        <div class="info-col">
            <div class="info-label">Supplier</div>
            <div class="info-value">
                {{ $grn->purchaseOrder?->supplierPartner?->name ?? '—' }}
            </div>
            @if ($grn->vehicle_plate || $grn->driver_name)
                <div class="info-label">Transport</div>
                <div class="info-value">
                    @if ($grn->vehicle_plate)<strong>Vehicle:</strong> {{ $grn->vehicle_plate }}<br>@endif
                    @if ($grn->vehicle_type){{ $grn->vehicle_type }}<br>@endif
                    @if ($grn->driver_name)<strong>Driver:</strong> {{ $grn->driver_name }}<br>@endif
                    @if ($grn->transporter_name)<strong>Transporter:</strong> {{ $grn->transporter_name }}@endif
                </div>
            @endif
        </div>
        <div class="info-col">
            <div class="info-label">Receiving Details</div>
            <div class="info-value">
                <strong>Received By:</strong> {{ $grn->confirmedBy?->name ?? '—' }}<br>
                <strong>Delivery Type:</strong> {{ ucfirst($grn->delivery_type ?? 'full') }}<br>
                @if ($grn->seal_check_result)
                    <strong>Seal Check:</strong> {{ ucfirst($grn->seal_check_result) }}<br>
                @endif
                @if ($grn->container_no)
                    <strong>Container:</strong> {{ $grn->container_no }}<br>
                @endif
            </div>
        </div>
    </div>

    {{-- Lines --}}
    @php
        $hasDamaged   = $grn->lines->sum(fn ($l) => (float) ($l->received_damaged_qty ?? 0)) > 0;
        $goodLines    = $hasDamaged ? $grn->lines->filter(fn ($l) => (float) ($l->received_good_qty ?? 0) > 0)->values() : $grn->lines->values();
        $damagedLines = $hasDamaged ? $grn->lines->filter(fn ($l) => (float) ($l->received_damaged_qty ?? 0) > 0)->values() : collect();
    @endphp

    @if ($hasDamaged)
        {{-- Good Items Table --}}
        <div class="section-title">Good Items</div>
        <table class="lines">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item Code</th>
                    <th>Description</th>
                    <th class="text-right">Expected</th>
                    <th class="text-right">Received (Good)</th>
                    <th>UoM</th>
                    <th>Batch / Serials</th>
                </tr>
            </thead>
            <tbody>
                @php $totalGood = 0; @endphp
                @foreach ($goodLines as $i => $line)
                    @php
                        $totalGood += (float) ($line->received_good_qty ?? 0);
                        $goodReceipts = $line->receipts->where('condition', 'good');
                        $batches = $goodReceipts->pluck('batch_no')->filter()->unique()->values();
                        $serials = $goodReceipts->flatMap(fn ($r) => $r->serial_nos ?? [])->filter()->unique()->values();
                    @endphp
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td>{{ $line->item?->sku ?? '—' }}</td>
                        <td>{{ $line->item?->name ?? '—' }}</td>
                        <td class="text-right">{{ number_format((float) ($line->expected_qty ?? 0), 2) }}</td>
                        <td class="text-right"><span class="badge-good">{{ number_format((float) $line->received_good_qty, 2) }}</span></td>
                        <td>{{ $line->uom?->code ?? '—' }}</td>
                        <td>
                            @if ($batches->isNotEmpty())<strong>Batch:</strong> {{ $batches->implode(', ') }}<br>@endif
                            @if ($serials->isNotEmpty())<strong>S/N:</strong> {{ $serials->implode(', ') }}@endif
                            @if ($batches->isEmpty() && $serials->isEmpty())—@endif
                        </td>
                    </tr>
                @endforeach
                <tr class="totals-row">
                    <td colspan="4" class="text-right">Total Good</td>
                    <td class="text-right">{{ number_format($totalGood, 2) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>

        {{-- Damaged Items Table --}}
        <div class="section-title">Damaged Items</div>
        <table class="lines">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item Code</th>
                    <th>Description</th>
                    <th class="text-right">Expected</th>
                    <th class="text-right">Received (Damaged)</th>
                    <th>UoM</th>
                    <th>Batch / Serials</th>
                </tr>
            </thead>
            <tbody>
                @php $totalDamaged = 0; @endphp
                @foreach ($damagedLines as $i => $line)
                    @php
                        $totalDamaged += (float) ($line->received_damaged_qty ?? 0);
                        $damagedReceipts = $line->receipts->where('condition', 'damaged');
                        $batches = $damagedReceipts->pluck('batch_no')->filter()->unique()->values();
                        $serials = $damagedReceipts->flatMap(fn ($r) => $r->serial_nos ?? [])->filter()->unique()->values();
                    @endphp
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td>{{ $line->item?->sku ?? '—' }}</td>
                        <td>{{ $line->item?->name ?? '—' }}</td>
                        <td class="text-right">{{ number_format((float) ($line->expected_qty ?? 0), 2) }}</td>
                        <td class="text-right"><span class="badge-damaged">{{ number_format((float) $line->received_damaged_qty, 2) }}</span></td>
                        <td>{{ $line->uom?->code ?? '—' }}</td>
                        <td>
                            @if ($batches->isNotEmpty())<strong>Batch:</strong> {{ $batches->implode(', ') }}<br>@endif
                            @if ($serials->isNotEmpty())<strong>S/N:</strong> {{ $serials->implode(', ') }}@endif
                            @if ($batches->isEmpty() && $serials->isEmpty())—@endif
                        </td>
                    </tr>
                @endforeach
                <tr class="totals-row">
                    <td colspan="4" class="text-right">Total Damaged</td>
                    <td class="text-right">{{ number_format($totalDamaged, 2) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>

    @else
        {{-- All Good — single table, no damaged column --}}
        <div class="section-title">Received Items</div>
        <table class="lines">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item Code</th>
                    <th>Description</th>
                    <th class="text-right">Expected</th>
                    <th class="text-right">Received</th>
                    <th>UoM</th>
                    <th>Batch / Serials</th>
                </tr>
            </thead>
            <tbody>
                @php $totalGood = 0; @endphp
                @foreach ($goodLines as $i => $line)
                    @php
                        $totalGood += (float) ($line->received_good_qty ?? 0);
                        $goodReceipts = $line->receipts->where('condition', 'good');
                        $batches = $goodReceipts->pluck('batch_no')->filter()->unique()->values();
                        $serials = $goodReceipts->flatMap(fn ($r) => $r->serial_nos ?? [])->filter()->unique()->values();
                    @endphp
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td>{{ $line->item?->sku ?? '—' }}</td>
                        <td>{{ $line->item?->name ?? '—' }}</td>
                        <td class="text-right">{{ number_format((float) ($line->expected_qty ?? 0), 2) }}</td>
                        <td class="text-right"><span class="badge-good">{{ number_format((float) $line->received_good_qty, 2) }}</span></td>
                        <td>{{ $line->uom?->code ?? '—' }}</td>
                        <td>
                            @if ($batches->isNotEmpty())<strong>Batch:</strong> {{ $batches->implode(', ') }}<br>@endif
                            @if ($serials->isNotEmpty())<strong>S/N:</strong> {{ $serials->implode(', ') }}@endif
                            @if ($batches->isEmpty() && $serials->isEmpty())—@endif
                        </td>
                    </tr>
                @endforeach
                <tr class="totals-row">
                    <td colspan="4" class="text-right">Total</td>
                    <td class="text-right">{{ number_format($totalGood, 2) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>
    @endif

    {{-- Signatures --}}
    <div class="signatures">
        <div class="sig-col">
            <div class="sig-line">Received By<br>{{ $grn->confirmedBy?->name ?? '' }}</div>
        </div>
        <div class="sig-col">
            <div class="sig-line">Supplier / Driver<br>{{ $grn->driver_name ?? '' }}</div>
        </div>
    </div>

</body>
</html>
