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
    .badge-good { display: inline-block; padding: 1px 5px; border-radius: 3px; background: #d1fae5; color: #065f46; font-size: 8px; }
    .badge-damaged { display: inline-block; padding: 1px 5px; border-radius: 3px; background: #fef3c7; color: #92400e; font-size: 8px; }
    .badge-variance { display: inline-block; padding: 1px 5px; border-radius: 3px; background: #fee2e2; color: #991b1b; font-size: 8px; }
    .totals-row td { background: #1e3a5f !important; color: #fff; font-weight: bold; }
    .footer { margin-top: 18px; border-top: 1px solid #e5e7eb; padding-top: 6px; font-size: 8px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>

@php $title = 'RECEIVING SUMMARY REPORT'; @endphp
@include('pdf.reports._header')

{{-- KPI row --}}
@php
    $totalGrns     = $grns->count();
    $totalExpected = $grns->flatMap->lines->sum(fn ($l) => (float)$l->expected_qty);
    $totalGood     = $grns->flatMap->lines->sum(fn ($l) => (float)($l->received_good_qty ?? 0));
    $totalDamaged  = $grns->flatMap->lines->sum(fn ($l) => (float)($l->received_damaged_qty ?? 0));
@endphp
<div style="display:table; width:100%; margin-bottom:14px;">
    @foreach ([
        ['GRNs', number_format($totalGrns), '#eff6ff', '#bfdbfe', '#1d4ed8'],
        ['Expected', number_format($totalExpected, 2), '#f0fdf4', '#bbf7d0', '#16a34a'],
        ['Good', number_format($totalGood, 2), '#f0fdf4', '#bbf7d0', '#16a34a'],
        ['Damaged', number_format($totalDamaged, 2), '#fef3c7', '#fde68a', '#d97706'],
    ] as [$lbl, $val, $bg, $border, $color])
    <div style="display:table-cell; width:25%; padding:0 3px;">
        <div style="background:{{ $bg }}; border:1px solid {{ $border }}; border-radius:4px; padding:8px; text-align:center;">
            <div style="font-size:16px; font-weight:bold; color:{{ $color }};">{{ $val }}</div>
            <div style="font-size:8px; color:#555; margin-top:2px;">{{ $lbl }}</div>
        </div>
    </div>
    @endforeach
</div>

<div class="section-title">GRN List</div>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>GRN No.</th>
            <th>Confirmed</th>
            <th>PO No.</th>
            <th>Supplier</th>
            <th>Warehouse</th>
            <th class="text-right">Expected</th>
            <th class="text-right">Good</th>
            <th class="text-right">Damaged</th>
            <th>Confirmed By</th>
        </tr>
    </thead>
    <tbody>
        @php $rowExpected = 0; $rowGood = 0; $rowDamaged = 0; @endphp
        @forelse ($grns as $i => $grn)
        @php
            $exp     = $grn->lines->sum(fn ($l) => (float)$l->expected_qty);
            $good    = $grn->lines->sum(fn ($l) => (float)($l->received_good_qty ?? 0));
            $damaged = $grn->lines->sum(fn ($l) => (float)($l->received_damaged_qty ?? 0));
            $rowExpected += $exp; $rowGood += $good; $rowDamaged += $damaged;
        @endphp
        <tr>
            <td style="color:#aaa;">{{ $i + 1 }}</td>
            <td class="mono">{{ $grn->number }}</td>
            <td>{{ $grn->confirmed_at?->format('d M Y') }}</td>
            <td class="mono">{{ $grn->purchaseOrder?->number ?? '—' }}</td>
            <td>{{ $grn->purchaseOrder?->supplierPartner?->name ?? '—' }}</td>
            <td>{{ $grn->warehouse?->name ?? '—' }}</td>
            <td class="text-right mono">{{ number_format($exp, 2) }}</td>
            <td class="text-right mono">
                @if ($good > 0) <span class="badge-good">{{ number_format($good, 2) }}</span> @else — @endif
            </td>
            <td class="text-right mono">
                @if ($damaged > 0) <span class="badge-damaged">{{ number_format($damaged, 2) }}</span> @else — @endif
            </td>
            <td>{{ $grn->confirmedBy?->name ?? '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="10" style="text-align:center;padding:16px;color:#aaa;">No GRNs in this period.</td></tr>
        @endforelse
        @if ($grns->isNotEmpty())
        <tr class="totals-row">
            <td colspan="6" class="text-right">Totals</td>
            <td class="text-right mono">{{ number_format($rowExpected, 2) }}</td>
            <td class="text-right mono">{{ number_format($rowGood, 2) }}</td>
            <td class="text-right mono">{{ number_format($rowDamaged, 2) }}</td>
            <td></td>
        </tr>
        @endif
    </tbody>
</table>

<div class="footer">{{ $brandName }} &bull; Receiving Summary &bull; {{ \Carbon\Carbon::parse($from)->format('d M Y') }} – {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</div>
</body>
</html>
