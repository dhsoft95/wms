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
    .pos { color: #16a34a; font-weight: bold; }
    .neg { color: #dc2626; font-weight: bold; }
    .totals-row td { background: #1e3a5f !important; color: #fff; font-weight: bold; }
    .footer { margin-top: 18px; border-top: 1px solid #e5e7eb; padding-top: 6px; font-size: 8px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>

@php $title = 'CYCLE COUNT VARIANCE REPORT'; @endphp
@include('pdf.reports._header')

@php
    $totalLines    = $lines->count();
    $totalShortage = $lines->where('variance', '<', 0)->sum(fn ($l) => abs((float)$l->variance));
    $totalSurplus  = $lines->where('variance', '>', 0)->sum(fn ($l) => (float)$l->variance);
@endphp
<div style="display:table; width:100%; margin-bottom:14px;">
    @foreach ([
        ['Variance Lines', number_format($totalLines), '#eff6ff', '#bfdbfe', '#1d4ed8'],
        ['Shortage Qty', number_format($totalShortage, 2), '#fee2e2', '#fecaca', '#dc2626'],
        ['Surplus Qty', number_format($totalSurplus, 2), '#f0fdf4', '#bbf7d0', '#16a34a'],
    ] as [$lbl, $val, $bg, $border, $color])
    <div style="display:table-cell; width:33%; padding:0 3px;">
        <div style="background:{{ $bg }}; border:1px solid {{ $border }}; border-radius:4px; padding:8px; text-align:center;">
            <div style="font-size:16px; font-weight:bold; color:{{ $color }};">{{ $val }}</div>
            <div style="font-size:8px; color:#555; margin-top:2px;">{{ $lbl }}</div>
        </div>
    </div>
    @endforeach
</div>

<div class="section-title">Variance Detail</div>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Count Ref</th>
            <th>Date</th>
            <th>SKU</th>
            <th>Item Name</th>
            <th>Location</th>
            <th class="text-right">Expected</th>
            <th class="text-right">Counted</th>
            <th class="text-right">Variance</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($lines as $i => $line)
        @php $var = (float)$line->variance; @endphp
        <tr>
            <td style="color:#aaa;">{{ $i + 1 }}</td>
            <td class="mono">{{ $line->count?->number ?? '#' . $line->count?->id }}</td>
            <td>{{ $line->created_at?->format('d M Y') ?? '—' }}</td>
            <td class="mono">{{ $line->item?->sku ?? '—' }}</td>
            <td>{{ $line->item?->name ?? '—' }}</td>
            <td class="mono">{{ $line->location?->code ?? '—' }}</td>
            <td class="text-right mono">{{ number_format((float)($line->system_qty ?? 0), 2) }}</td>
            <td class="text-right mono">{{ number_format((float)$line->counted_qty, 2) }}</td>
            <td class="text-right mono {{ $var > 0 ? 'pos' : 'neg' }}">
                {{ $var > 0 ? '+' : '' }}{{ number_format($var, 2) }}
            </td>
        </tr>
        @empty
        <tr><td colspan="9" style="text-align:center;padding:16px;color:#aaa;">No variances found in this period.</td></tr>
        @endforelse
        @if ($lines->isNotEmpty())
        <tr class="totals-row">
            <td colspan="8" class="text-right">Net Variance</td>
            <td class="text-right mono">
                @php $net = $lines->sum(fn ($l) => (float)$l->variance); @endphp
                {{ $net >= 0 ? '+' : '' }}{{ number_format($net, 2) }}
            </td>
        </tr>
        @endif
    </tbody>
</table>

<div class="footer">{{ $brandName }} &bull; Cycle Count Variances &bull; {{ \Carbon\Carbon::parse($from)->format('d M Y') }} – {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</div>
</body>
</html>
