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
        .doc-subtitle { font-size: 11px; color: #666; }
        .info-grid { display: table; width: 100%; margin-bottom: 15px; }
        .info-col { display: table-cell; width: 50%; vertical-align: top; padding-right: 10px; }
        .info-label { font-weight: bold; font-size: 10px; color: #666; text-transform: uppercase; }
        .info-value { font-size: 11px; margin-bottom: 8px; }
        table.lines { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table.lines th { background: #f5f5f5; border: 1px solid #ccc; padding: 5px 8px; font-size: 10px; text-align: left; }
        table.lines td { border: 1px solid #ddd; padding: 5px 8px; font-size: 10px; }
        table.lines td.num { text-align: right; }
        .totals-row td { font-weight: bold; background: #f9f9f9; }
        .signatures { display: table; width: 100%; margin-top: 40px; }
        .sig-col { display: table-cell; width: 33%; text-align: center; padding: 0 10px; }
        .sig-line { border-top: 1px solid #333; margin-top: 40px; padding-top: 5px; font-size: 10px; }
        .notice { font-size: 10px; color: #555; border: 1px solid #ddd; padding: 8px; margin-bottom: 15px; background: #fafafa; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <div class="brand-name">{{ $brandName }}</div>
            <div class="doc-title">RECONCILIATION NOTE</div>
            <div class="doc-subtitle">Goods Received Reconciliation</div>
        </div>
        <div class="header-right">
            @if($dispatch?->rcn_number)
            <div><strong>RCN No:</strong> {{ $dispatch->rcn_number }}</div>
            @endif
            @if($dispatch?->dn_number)
            <div><strong>DN No:</strong> {{ $dispatch->dn_number }}</div>
            @endif
            <div><strong>DO No:</strong> {{ $do->number }}</div>
            <div><strong>Date:</strong> {{ now()->format('d M Y') }}</div>
            @if($do->warehouse)
            <div><strong>Warehouse:</strong> {{ $do->warehouse->name }}</div>
            @endif
        </div>
    </div>

    <div class="info-grid">
        <div class="info-col">
            <div class="info-label">Consignee / Delivery To</div>
            <div class="info-value">
                {{ $do->consigneePartner?->name ?? '—' }}<br>
                {{ $do->delivery_address ?? '' }}
            </div>
        </div>
        @if($dispatch)
        <div class="info-col">
            <div class="info-label">Dispatch Details</div>
            <div class="info-value">
                <strong>Dispatch No:</strong> {{ $dispatch->number }}<br>
                <strong>Dispatched By:</strong> {{ $dispatch->dispatchedBy?->name ?? '—' }}<br>
                <strong>Dispatched At:</strong> {{ $dispatch->dispatched_at?->format('d M Y H:i') ?? '—' }}<br>
                <strong>Vehicle:</strong> {{ $dispatch->vehicle_no }}<br>
                <strong>Driver:</strong> {{ $dispatch->driver_name }}
            </div>
        </div>
        @endif
    </div>

    <table class="lines">
        <thead>
            <tr>
                <th>#</th>
                <th>Item Code</th>
                <th>Description</th>
                <th>Batch</th>
                <th>UoM</th>
                <th>Qty Ordered</th>
                <th>Qty Dispatched</th>
                <th>Variance</th>
            </tr>
        </thead>
        <tbody>
            @php $totalOrdered = 0; $totalDispatched = 0; @endphp
            @foreach($do->lines as $line)
            @php
                $ordered    = (float) $line->requested_qty_base;
                $dispatched = (float) $line->dispatched_qty;
                $variance   = $dispatched - $ordered;
                $batch      = $line->allocations->first()?->batch;
                $totalOrdered    += $ordered;
                $totalDispatched += $dispatched;
            @endphp
            <tr>
                <td>{{ $line->line_no }}</td>
                <td>{{ $line->item->sku }}</td>
                <td>{{ $line->item->name }}</td>
                <td>{{ $batch?->batch_no ?? '—' }}</td>
                <td>{{ $line->uom->code }}</td>
                <td class="num">{{ number_format($ordered, 2) }}</td>
                <td class="num">{{ number_format($dispatched, 2) }}</td>
                <td class="num" style="{{ $variance < 0 ? 'color:#c00;' : ($variance > 0 ? 'color:#080;' : '') }}">
                    {{ $variance == 0 ? '—' : number_format($variance, 2) }}
                </td>
            </tr>
            @endforeach
            <tr class="totals-row">
                <td colspan="5" style="text-align:right;">TOTAL</td>
                <td class="num">{{ number_format($totalOrdered, 2) }}</td>
                <td class="num">{{ number_format($totalDispatched, 2) }}</td>
                <td class="num">{{ number_format($totalDispatched - $totalOrdered, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="notice">
        This Reconciliation Note confirms the quantities dispatched from the warehouse against the delivery order.
        Any variances must be noted and signed by the receiving party at the time of delivery.
    </div>

    <div class="signatures">
        <div class="sig-col">
            <div class="sig-line">Dispatched By<br>{{ $dispatch?->dispatchedBy?->name ?? '' }}</div>
        </div>
        <div class="sig-col">
            <div class="sig-line">Driver / Transporter<br>{{ $dispatch?->driver_name ?? '' }}</div>
        </div>
        <div class="sig-col">
            <div class="sig-line">Received By<br>&nbsp;</div>
        </div>
    </div>
</body>
</html>
