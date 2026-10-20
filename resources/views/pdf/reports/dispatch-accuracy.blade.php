<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; margin: 0; padding: 18px; }
    .report-header { display: table; width: 100%; border-bottom: 2px solid #1e3a5f; padding-bottom: 8px; margin-bottom: 14px; }
    .header-left { display: table-cell; width: 60%; vertical-align: top; }
    .header-right { display: table-cell; width: 40%; text-align: right; vertical-align: top; font-size: 9px; color: #555; }
    .brand { font-size: 16px; font-weight: bold; color: #1e3a5f; }
    .report-title { font-size: 13px; font-weight: bold; color: #333; margin-top: 2px; }
    .section-title { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #555; border-bottom: 1px solid #ddd; padding-bottom: 2px; margin: 12px 0 6px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    thead th { background: #1e3a5f; color: #fff; padding: 5px 6px; font-size: 9px; text-align: left; }
    tbody td { border-bottom: 1px solid #f0f0f0; padding: 4px 6px; font-size: 9px; vertical-align: top; }
    tbody tr:nth-child(even) td { background: #f9fafb; }
    .text-right { text-align: right; }
    .mono { font-family: monospace; }
    .pill-green { display:inline-block; padding:1px 5px; border-radius:3px; background:#d1fae5; color:#065f46; font-size:8px; font-weight:bold; }
    .pill-amber  { display:inline-block; padding:1px 5px; border-radius:3px; background:#fef3c7; color:#92400e; font-size:8px; font-weight:bold; }
    .pill-red   { display:inline-block; padding:1px 5px; border-radius:3px; background:#fee2e2; color:#991b1b; font-size:8px; font-weight:bold; }
    .totals-row td { background:#1e3a5f !important; color:#fff; font-weight:bold; }
    .footer { margin-top: 18px; border-top: 1px solid #e5e7eb; padding-top: 6px; font-size: 8px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>

@php $title = 'DISPATCH ACCURACY KPI REPORT'; @endphp
@include('pdf.reports._header')

@php
    $totalOrders    = $orders->count();
    $totalRequested = $orders->flatMap->lines->sum(fn ($l) => (float)$l->requested_qty);
    $totalDispatched = $orders->flatMap->lines->sum(fn ($l) => (float)($l->dispatched_qty ?? 0));
    $totalShort     = $orders->flatMap->lines->sum(fn ($l) => (float)($l->short_qty ?? 0));
    $accuracy       = $totalRequested > 0 ? round(($totalDispatched / $totalRequested) * 100, 1) : 0;
@endphp
<div style="display:table; width:100%; margin-bottom:14px;">
    @foreach ([
        ['Orders', number_format($totalOrders), '#eff6ff', '#bfdbfe', '#1d4ed8'],
        ['Requested', number_format($totalRequested, 2), '#f9fafb', '#e5e7eb', '#374151'],
        ['Dispatched', number_format($totalDispatched, 2), '#f0fdf4', '#bbf7d0', '#16a34a'],
        ['Accuracy', $accuracy . '%', $accuracy >= 95 ? '#f0fdf4' : ($accuracy >= 80 ? '#fef3c7' : '#fee2e2'), $accuracy >= 95 ? '#bbf7d0' : ($accuracy >= 80 ? '#fde68a' : '#fecaca'), $accuracy >= 95 ? '#16a34a' : ($accuracy >= 80 ? '#d97706' : '#dc2626')],
    ] as [$lbl, $val, $bg, $border, $color])
    <div style="display:table-cell; width:25%; padding:0 3px;">
        <div style="background:{{ $bg }}; border:1px solid {{ $border }}; border-radius:4px; padding:8px; text-align:center;">
            <div style="font-size:16px; font-weight:bold; color:{{ $color }};">{{ $val }}</div>
            <div style="font-size:8px; color:#555; margin-top:2px;">{{ $lbl }}</div>
        </div>
    </div>
    @endforeach
</div>

<div class="section-title">Order Fulfilment Detail</div>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>DO No.</th>
            <th>Date</th>
            <th>Consignee</th>
            <th>Status</th>
            <th class="text-right">Requested</th>
            <th class="text-right">Dispatched</th>
            <th class="text-right">Short</th>
            <th class="text-right">Accuracy</th>
        </tr>
    </thead>
    <tbody>
        @php $tReq = 0; $tDis = 0; $tSht = 0; @endphp
        @forelse ($orders as $i => $order)
        @php
            $req  = $order->lines->sum(fn ($l) => (float)$l->requested_qty);
            $dis  = $order->lines->sum(fn ($l) => (float)($l->dispatched_qty ?? 0));
            $sht  = $order->lines->sum(fn ($l) => (float)($l->short_qty ?? 0));
            $acc  = $req > 0 ? round($dis / $req * 100, 1) : 0;
            $tReq += $req; $tDis += $dis; $tSht += $sht;
        @endphp
        <tr>
            <td style="color:#aaa;">{{ $i + 1 }}</td>
            <td class="mono">{{ $order->number }}</td>
            <td>{{ $order->created_at?->format('d M Y') }}</td>
            <td>{{ $order->consigneePartner?->name ?? '—' }}</td>
            <td>
                @php $s = $order->status->value ?? $order->status; @endphp
                <span class="{{ $s === 'delivered' ? 'pill-green' : ($s === 'dispatched' ? 'pill-amber' : 'pill-red') }}">
                    {{ $order->status->label() ?? ucfirst($s) }}
                </span>
            </td>
            <td class="text-right mono">{{ number_format($req, 2) }}</td>
            <td class="text-right mono">{{ number_format($dis, 2) }}</td>
            <td class="text-right mono">{{ $sht > 0 ? number_format($sht, 2) : '—' }}</td>
            <td class="text-right" style="font-weight:bold; color:{{ $acc >= 95 ? '#16a34a' : ($acc >= 80 ? '#d97706' : '#dc2626') }}">
                {{ $acc }}%
            </td>
        </tr>
        @empty
        <tr><td colspan="9" style="text-align:center;padding:16px;color:#aaa;">No dispatch orders in this period.</td></tr>
        @endforelse
        @if ($orders->isNotEmpty())
        <tr class="totals-row">
            <td colspan="5" class="text-right">Totals</td>
            <td class="text-right mono">{{ number_format($tReq, 2) }}</td>
            <td class="text-right mono">{{ number_format($tDis, 2) }}</td>
            <td class="text-right mono">{{ number_format($tSht, 2) }}</td>
            <td class="text-right">{{ $tReq > 0 ? round($tDis / $tReq * 100, 1) : 0 }}%</td>
        </tr>
        @endif
    </tbody>
</table>

<div class="footer">{{ $brandName }} &bull; Dispatch Accuracy KPI &bull; {{ \Carbon\Carbon::parse($from)->format('d M Y') }} – {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</div>
</body>
</html>
