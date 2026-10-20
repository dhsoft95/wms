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
        .info-grid { display: table; width: 100%; margin-bottom: 15px; }
        .info-col { display: table-cell; width: 50%; vertical-align: top; padding-right: 10px; }
        .info-label { font-weight: bold; font-size: 10px; color: #666; text-transform: uppercase; }
        .info-value { font-size: 11px; margin-bottom: 8px; }
        table.lines { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table.lines th { background: #f5f5f5; border: 1px solid #ccc; padding: 5px 8px; font-size: 10px; text-align: left; }
        table.lines td { border: 1px solid #ddd; padding: 5px 8px; font-size: 10px; }
        .signatures { display: table; width: 100%; margin-top: 40px; }
        .sig-col { display: table-cell; width: 33%; text-align: center; padding: 0 10px; }
        .sig-line { border-top: 1px solid #333; margin-top: 40px; padding-top: 5px; font-size: 10px; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; background: #e5f3ff; color: #0066cc; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <div class="brand-name">{{ $brandName }}</div>
            <div class="doc-title">OUTBOUND DELIVERY NOTE (ODN)</div>
        </div>
        <div class="header-right">
            <div><strong>DO No:</strong> {{ $do->number }}</div>
            @if($dispatch)
            <div><strong>Dispatch No:</strong> {{ $dispatch->number }}</div>
            @endif
            <div><strong>Date:</strong> {{ now()->format('d M Y') }}</div>
            @if($do->required_date)
            <div><strong>Required:</strong> {{ $do->required_date->format('d M Y') }}</div>
            @endif
        </div>
    </div>

    <div class="info-grid">
        <div class="info-col">
            <div class="info-label">Consignee</div>
            <div class="info-value">
                {{ $do->consigneePartner?->name ?? '—' }}<br>
                {{ $do->delivery_address ?? '' }}
            </div>
        </div>
        @if($dispatch)
        <div class="info-col">
            <div class="info-label">Transport Details</div>
            <div class="info-value">
                <strong>Vehicle Reg:</strong> {{ $dispatch->vehicle_reg_no }}<br>
                @if($dispatch->container_no)<strong>Container No:</strong> {{ $dispatch->container_no }}<br>@endif
                @if($dispatch->container_size)<strong>Container Size:</strong> {{ $dispatch->container_size->label() }}<br>@endif
                <strong>Driver:</strong> {{ $dispatch->driver_name }}<br>
                @if($dispatch->driver_phone)<strong>Phone:</strong> {{ $dispatch->driver_phone }}<br>@endif
                @if($dispatch->seal_no)<strong>Seal No:</strong> {{ $dispatch->seal_no }}@endif
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
                <th>Expiry</th>
                <th>Qty</th>
                <th>UoM</th>
                <th>Serial Numbers</th>
            </tr>
        </thead>
        <tbody>
            @foreach($do->lines as $line)
            @php
                $picked = $line->allocations->where('status', 'picked');
                $serials = $picked->flatMap(fn($a) => $a->pickTask?->serials ?? collect())->pluck('serial_no');
                $batch = $picked->first()?->batch;
            @endphp
            <tr>
                <td>{{ $line->line_no }}</td>
                <td>{{ $line->item->sku }}</td>
                <td>{{ $line->item->name }}</td>
                <td>{{ $batch?->batch_no ?? '—' }}</td>
                <td>{{ $batch?->expiry_date?->format('d M Y') ?? '—' }}</td>
                <td>{{ number_format($line->dispatched_qty, 2) }}</td>
                <td>{{ $line->uom->code }}</td>
                <td>{{ $serials->implode(', ') ?: '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

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
