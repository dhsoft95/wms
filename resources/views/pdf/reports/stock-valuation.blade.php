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
    .badge-ok { display: inline-block; padding: 1px 5px; border-radius: 3px; background: #d1fae5; color: #065f46; font-size: 8px; font-weight: bold; }
    .badge-low { display: inline-block; padding: 1px 5px; border-radius: 3px; background: #fef3c7; color: #92400e; font-size: 8px; font-weight: bold; }
    .summary-box { display: table; width: 100%; margin-bottom: 14px; }
    .summary-cell { display: table-cell; width: 25%; text-align: center; padding: 8px 6px; background: #f3f4f6; border-radius: 4px; margin: 2px; }
    .summary-val { font-size: 16px; font-weight: bold; color: #1e3a5f; }
    .summary-lbl { font-size: 8px; color: #666; margin-top: 2px; }
    .footer { margin-top: 18px; border-top: 1px solid #e5e7eb; padding-top: 6px; font-size: 8px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>

@php
    $title = 'STOCK VALUATION REPORT';
    $asOf  = $asOf;
@endphp
@include('pdf.reports._header')

{{-- Summary KPIs --}}
@php
    $totalSkus   = $balances->pluck('item_id')->unique()->count();
    $totalQty    = $balances->sum(fn ($b) => (float) $b->qty_on_hand);
    $totalAlloc  = $balances->sum(fn ($b) => (float) $b->qty_allocated);
    $totalAvail  = $balances->sum(fn ($b) => max(0, (float) $b->qty_on_hand - (float) $b->qty_allocated));
@endphp
<div style="display:table; width:100%; margin-bottom:14px;">
    <div style="display:table-cell; width:25%; padding:0 4px 0 0;">
        <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:4px; padding:8px 10px; text-align:center;">
            <div style="font-size:18px; font-weight:bold; color:#1d4ed8;">{{ number_format($totalSkus) }}</div>
            <div style="font-size:8px; color:#555; margin-top:2px;">SKUs In Stock</div>
        </div>
    </div>
    <div style="display:table-cell; width:25%; padding:0 4px;">
        <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:4px; padding:8px 10px; text-align:center;">
            <div style="font-size:18px; font-weight:bold; color:#16a34a;">{{ number_format($totalQty, 2) }}</div>
            <div style="font-size:8px; color:#555; margin-top:2px;">Total On Hand</div>
        </div>
    </div>
    <div style="display:table-cell; width:25%; padding:0 4px;">
        <div style="background:#fef3c7; border:1px solid #fde68a; border-radius:4px; padding:8px 10px; text-align:center;">
            <div style="font-size:18px; font-weight:bold; color:#d97706;">{{ number_format($totalAlloc, 2) }}</div>
            <div style="font-size:8px; color:#555; margin-top:2px;">Total Allocated</div>
        </div>
    </div>
    <div style="display:table-cell; width:25%; padding:0 0 0 4px;">
        <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:4px; padding:8px 10px; text-align:center;">
            <div style="font-size:18px; font-weight:bold; color:#374151;">{{ number_format($totalAvail, 2) }}</div>
            <div style="font-size:8px; color:#555; margin-top:2px;">Available</div>
        </div>
    </div>
</div>

<div class="section-title">Stock Balance by Item &amp; Location</div>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>SKU</th>
            <th>Item Name</th>
            <th>Location</th>
            <th>Batch</th>
            <th class="text-right">On Hand</th>
            <th class="text-right">Allocated</th>
            <th class="text-right">Available</th>
            <th>UoM</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($balances as $i => $b)
        <tr>
            <td class="text-right" style="color:#aaa;">{{ $i + 1 }}</td>
            <td class="mono">{{ $b->item?->sku ?? '—' }}</td>
            <td>{{ $b->item?->name ?? '—' }}</td>
            <td class="mono">{{ $b->location?->code ?? '—' }}</td>
            <td class="mono">{{ $b->batch?->batch_no ?? '—' }}</td>
            <td class="text-right mono">{{ number_format((float)$b->qty_on_hand, 2) }}</td>
            <td class="text-right mono">{{ number_format((float)$b->qty_allocated, 2) }}</td>
            <td class="text-right mono">
                @php $avail = max(0, (float)$b->qty_on_hand - (float)$b->qty_allocated); @endphp
                {{ number_format($avail, 2) }}
            </td>
            <td>{{ $b->item?->baseUom?->code ?? '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="9" style="text-align:center; padding:16px; color:#aaa;">No stock on hand.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">{{ $brandName }} &bull; Stock Valuation Report &bull; {{ $asOf }}</div>
</body>
</html>
