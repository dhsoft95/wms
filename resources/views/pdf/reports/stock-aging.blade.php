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
    .text-center { text-align: center; }
    .mono { font-family: monospace; }
    .age-fresh  { background: #d1fae5; color: #065f46; display:inline-block; padding:1px 5px; border-radius:3px; font-size:8px; font-weight:bold; }
    .age-ok     { background: #dbeafe; color: #1e40af; display:inline-block; padding:1px 5px; border-radius:3px; font-size:8px; font-weight:bold; }
    .age-watch  { background: #fef3c7; color: #92400e; display:inline-block; padding:1px 5px; border-radius:3px; font-size:8px; font-weight:bold; }
    .age-old    { background: #fed7aa; color: #9a3412; display:inline-block; padding:1px 5px; border-radius:3px; font-size:8px; font-weight:bold; }
    .age-critical { background: #fee2e2; color: #991b1b; display:inline-block; padding:1px 5px; border-radius:3px; font-size:8px; font-weight:bold; }
    .totals-row td { background:#1e3a5f !important; color:#fff; font-weight:bold; }
    .footer { margin-top: 18px; border-top: 1px solid #e5e7eb; padding-top: 6px; font-size: 8px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>

@php $title = 'STOCK AGING REPORT'; @endphp
@include('pdf.reports._header')

{{-- Bucket summary --}}
@php
    $buckets = $balances->groupBy('age_bucket');
    $bucketOrder = ['0–30 days', '31–60 days', '61–90 days', '91–180 days', '180+ days'];
    $bucketColors = ['#16a34a', '#1d4ed8', '#d97706', '#ea580c', '#dc2626'];
@endphp
<div class="section-title">Age Distribution</div>
<div style="display:table; width:100%; margin-bottom:14px;">
    @foreach ($bucketOrder as $k => $bucket)
    @php $group = $buckets->get($bucket, collect()); @endphp
    <div style="display:table-cell; width:20%; padding:0 3px;">
        <div style="border:1px solid #e5e7eb; border-radius:4px; padding:8px; text-align:center;">
            <div style="font-size:14px; font-weight:bold; color:{{ $bucketColors[$k] }};">{{ number_format($group->count()) }}</div>
            <div style="font-size:9px; font-weight:bold; color:#374151; margin-top:1px;">{{ $bucket }}</div>
            <div style="font-size:8px; color:#555;">{{ number_format($group->sum(fn($b) => (float)$b->qty_on_hand), 2) }} units</div>
        </div>
    </div>
    @endforeach
</div>

<div class="section-title">Stock Age Detail</div>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>SKU</th>
            <th>Item Name</th>
            <th>Location</th>
            <th>Batch</th>
            <th>Received</th>
            <th class="text-right">Age (days)</th>
            <th class="text-right">On Hand</th>
            <th>Bucket</th>
            <th>UoM</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($balances->sortByDesc('age_days') as $i => $b)
        @php
            $ageClass = match ($b->age_bucket) {
                '0–30 days'    => 'age-fresh',
                '31–60 days'   => 'age-ok',
                '61–90 days'   => 'age-watch',
                '91–180 days'  => 'age-old',
                default        => 'age-critical',
            };
        @endphp
        <tr>
            <td style="color:#aaa;">{{ $i + 1 }}</td>
            <td class="mono">{{ $b->item?->sku ?? '—' }}</td>
            <td>{{ $b->item?->name ?? '—' }}</td>
            <td class="mono">{{ $b->location?->code ?? '—' }}</td>
            <td class="mono">{{ $b->batch?->batch_no ?? '—' }}</td>
            <td>{{ \Carbon\Carbon::parse($b->received_at)->format('d M Y') }}</td>
            <td class="text-right mono" style="font-weight:bold;">{{ $b->age_days }}</td>
            <td class="text-right mono">{{ number_format((float)$b->qty_on_hand, 2) }}</td>
            <td><span class="{{ $ageClass }}">{{ $b->age_bucket }}</span></td>
            <td>{{ $b->item?->baseUom?->code ?? '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="10" style="text-align:center;padding:16px;color:#aaa;">No stock with received date.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">{{ $brandName }} &bull; Stock Aging Report &bull; As at {{ $asOf }}</div>
</body>
</html>
