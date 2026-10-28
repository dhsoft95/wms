@php
/**
 * Variables injected by ContainerReportService::build()
 * @var array  $brand          ['name','address','logoDataUri']
 * @var \App\Models\Container  $container
 * @var \Illuminate\Support\Collection $lines
 * @var array  $varianceSummary
 * @var \Illuminate\Support\Collection $grnSummary
 * @var float  $totalExpected
 * @var float  $totalGood
 * @var float  $totalDamaged
 * @var float  $totalVariance
 * @var string $generatedAt
 */

$c   = $container;
$po  = $c->purchaseOrder;
$fmt = function ($val, string $type = 'text', int $dec = 2): string {
    if ($val === null || $val === '') return '—';
    return match ($type) {
        'number' => number_format((float) $val, $dec),
        'signed' => ((float) $val > 0 ? '+' : '') . number_format((float) $val, $dec),
        'date'   => ($val instanceof \Carbon\Carbon ? $val : \Carbon\Carbon::parse($val))->format('d M Y'),
        'dt'     => ($val instanceof \Carbon\Carbon ? $val : \Carbon\Carbon::parse($val))->format('d M Y H:i'),
        default  => (string) $val,
    };
};
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page {
        margin: 14mm 12mm 22mm 12mm;
        size: A4 landscape;
    }
    * { box-sizing: border-box; }
    body {
        font-family: DejaVu Sans, Arial, sans-serif;
        font-size: 8.5px;
        color: #1a1a1a;
        margin: 0;
    }

    /* ── DomPDF footer ─────────────────────────────────────── */
    script[type="text/php"] { display: none; }

    /* ── Brand ─────────────────────────────────────────────── */
    .brand      { text-align: center; margin-bottom: 6px; }
    .brand-logo { max-height: 44px; max-width: 140px; display: inline-block; }
    .brand-name { font-size: 11px; font-weight: bold; color: #111; }
    .brand-addr { font-size: 7px; color: #6b7280; margin-top: 2px; }

    /* ── Page header row ────────────────────────────────────── */
    .pg-header    { width: 100%; border-collapse: collapse; border-bottom: 2.5px solid #a22631; padding-bottom: 6px; margin-bottom: 10px; }
    .pg-header td { vertical-align: bottom; border: 0; padding: 0 4px 4px 0; }
    .pg-title     { font-size: 14px; font-weight: bold; color: #111; letter-spacing: 0.02em; }
    .pg-subtitle  { font-size: 9px; color: #555; margin-top: 1px; }
    .pg-meta      { font-size: 7.5px; color: #555; text-align: right; line-height: 1.8; }
    .pg-meta strong { color: #222; font-size: 8px; }

    /* ── Info grid (detail cards) ───────────────────────────── */
    .info-grid  { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin-bottom: 10px; table-layout: fixed; }
    .info-card  { border: 1px solid #e5e7eb; border-radius: 4px; padding: 6px 8px; vertical-align: top; }
    .info-label { font-size: 7px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.06em; color: #9ca3af; margin-bottom: 4px; }
    .info-row   { display: table; width: 100%; margin-bottom: 2px; }
    .info-key   { display: table-cell; font-size: 7.5px; color: #6b7280; width: 42%; }
    .info-val   { display: table-cell; font-size: 8px; font-weight: 600; color: #111; }
    .info-val.mono { font-family: 'DejaVu Sans Mono', monospace; }

    /* ── Status badge ───────────────────────────────────────── */
    .status-badge {
        display: inline-block;
        padding: 1px 7px;
        border-radius: 99px;
        font-size: 7.5px;
        font-weight: bold;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }
    .status-expected  { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .status-arrived   { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
    .status-parked    { background: #fefce8; color: #a16207; border: 1px solid #fef08a; }
    .status-unloading { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
    .status-unloaded  { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
    .status-released  { background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; }
    .status-cancelled { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

    /* ── KPI summary row ────────────────────────────────────── */
    .kpi-row   { width: 100%; border-collapse: separate; border-spacing: 5px 0; margin-bottom: 10px; table-layout: fixed; }
    .kpi-cell  { border: 1px solid #e5e7eb; border-radius: 4px; padding: 5px 8px; text-align: center; }
    .kpi-label { font-size: 7px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.06em; color: #9ca3af; }
    .kpi-value { font-size: 13px; font-weight: bold; color: #111; margin-top: 2px; }
    .kpi-value.green { color: #15803d; }
    .kpi-value.red   { color: #b91c1c; }
    .kpi-value.amber { color: #b45309; }

    /* ── Section heading ────────────────────────────────────── */
    .section-head {
        font-size: 8px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: #a22631;
        border-bottom: 1px solid #f3f4f6;
        padding-bottom: 3px;
        margin-bottom: 6px;
        margin-top: 10px;
    }

    /* ── Tables ─────────────────────────────────────────────── */
    table.data { width: 100%; border-collapse: collapse; font-size: 7.5px; }
    table.data thead th {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        padding: 4px 5px;
        text-align: left;
        font-weight: bold;
        font-size: 7px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6b7280;
        white-space: nowrap;
    }
    table.data thead th.r { text-align: right; }
    table.data tbody td {
        border: 1px solid #f3f4f6;
        padding: 3.5px 5px;
        vertical-align: top;
        color: #374151;
    }
    table.data tbody td.r { text-align: right; }
    table.data tbody td.mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 7px; }
    table.data tbody tr:nth-child(even) td { background: #fafafa; }
    table.data tfoot td {
        border-top: 2px solid #d1d5db;
        border-bottom: 1px solid #d1d5db;
        padding: 4px 5px;
        font-weight: bold;
        font-size: 7.5px;
        background: #f3f4f6;
    }
    table.data tfoot td.r { text-align: right; }

    .var-short   { color: #b45309; }
    .var-excess  { color: #a22631; }
    .var-damaged { color: #b91c1c; }
    .var-mixed   { color: #b45309; }
    .var-none    { color: #9ca3af; }
    .unexpected  { background: #fff7ed !important; }

    /* ── GRN mini table ─────────────────────────────────────── */
    table.grn-table { width: 100%; border-collapse: collapse; font-size: 7.5px; }
    table.grn-table td, table.grn-table th {
        border: 1px solid #e5e7eb;
        padding: 3px 6px;
    }
    table.grn-table th {
        background: #f9fafb;
        font-weight: bold;
        font-size: 7px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6b7280;
    }
    table.grn-table td.r { text-align: right; }

    /* ── Sign-off block ─────────────────────────────────────── */
    .signoff { width: 100%; border-collapse: separate; border-spacing: 12px 0; margin-top: 14px; table-layout: fixed; }
    .signoff td {
        border-top: 1px solid #374151;
        padding-top: 5px;
        vertical-align: top;
        font-size: 7.5px;
        color: #374151;
    }
    .signoff .role  { font-weight: bold; font-size: 8px; color: #111; }
    .signoff .lines { margin-top: 14px; font-size: 7px; color: #9ca3af; }

    /* ── Footer note ─────────────────────────────────────────── */
    .footer-note {
        margin-top: 10px;
        font-size: 7px;
        color: #9ca3af;
        text-align: center;
        border-top: 1px solid #f3f4f6;
        padding-top: 4px;
    }
</style>
</head>
<body>

{{-- DomPDF page footer --}}
<script type="text/php">
    if (isset($pdf)) {
        $pdf->page_script('
            $font = $fontMetrics->get_font("DejaVu Sans", "normal");
            $size = 7;
            $color = [0.6, 0.6, 0.6];
            $left  = "' . addslashes($brand['name']) . '  |  Container Receipt Report";
            $center = "System generated — do not alter";
            $right = "Page " . $PAGE_NUM . " of " . $PAGE_COUNT;
            $w = $PAGE_WIDTH;
            $h = $PAGE_HEIGHT;
            $m = 34;
            $pdf->text($m, $h - 14, $left, $font, $size, $color);
            $cw = $fontMetrics->get_text_width($center, $font, $size, $color);
            $pdf->text(($w - $cw) / 2, $h - 14, $center, $font, $size, $color);
            $rw = $fontMetrics->get_text_width($right, $font, $size, $color);
            $pdf->text($w - $m - $rw, $h - 14, $right, $font, $size, $color);
        ');
    }
</script>

{{-- ── Brand ────────────────────────────────────────────────── --}}
<div class="brand">
    @if ($brand['logoDataUri'])
        <img src="{{ $brand['logoDataUri'] }}" class="brand-logo" alt="{{ $brand['name'] }}" />
    @else
        <div class="brand-name">{{ $brand['name'] }}</div>
    @endif
    @if ($brand['address'])
        <div class="brand-addr">{{ $brand['address'] }}</div>
    @endif
</div>

{{-- ── Page header ─────────────────────────────────────────── --}}
<table class="pg-header">
    <tr>
        <td width="55%">
            <div class="pg-title">Container Receipt Report</div>
            <div class="pg-subtitle">
                {{ $c->container_no }}
                @if ($c->number && $c->number !== $c->container_no)
                    &nbsp;&middot;&nbsp;{{ $c->number }}
                @endif
                @if ($c->size_type)
                    &nbsp;&middot;&nbsp;{{ $c->size_type->label() }}
                @endif
                &nbsp;&nbsp;
                <span class="status-badge status-{{ $c->status?->value ?? 'expected' }}">{{ $c->status?->label() ?? '—' }}</span>
            </div>
        </td>
        <td class="pg-meta">
            @if ($po)
                <strong>PO:</strong> {{ $po->number }}<br>
            @endif
            @if ($c->bl_no)
                <strong>BL No:</strong> {{ $c->bl_no }}<br>
            @endif
            <strong>Generated:</strong> {{ $generatedAt }}
        </td>
    </tr>
</table>

{{-- ── KPI row ──────────────────────────────────────────────── --}}
<table class="kpi-row">
    <tr>
        <td class="kpi-cell">
            <div class="kpi-label">Total Lines</div>
            <div class="kpi-value">{{ $varianceSummary['total_lines'] }}</div>
        </td>
        <td class="kpi-cell">
            <div class="kpi-label">Qty Expected</div>
            <div class="kpi-value">{{ number_format($totalExpected, 2) }}</div>
        </td>
        <td class="kpi-cell">
            <div class="kpi-label">Qty Received (Good)</div>
            <div class="kpi-value green">{{ number_format($totalGood, 2) }}</div>
        </td>
        <td class="kpi-cell">
            <div class="kpi-label">Qty Damaged</div>
            <div class="kpi-value {{ $totalDamaged > 0 ? 'red' : '' }}">{{ number_format($totalDamaged, 2) }}</div>
        </td>
        <td class="kpi-cell">
            <div class="kpi-label">Net Variance</div>
            <div class="kpi-value {{ $totalVariance < 0 ? 'red' : ($totalVariance > 0 ? 'amber' : '') }}">
                {{ ($totalVariance > 0 ? '+' : '') . number_format($totalVariance, 2) }}
            </div>
        </td>
        <td class="kpi-cell">
            <div class="kpi-label">Lines with Variance</div>
            <div class="kpi-value {{ $varianceSummary['with_variance'] > 0 ? 'amber' : 'green' }}">
                {{ $varianceSummary['with_variance'] }}
            </div>
        </td>
        <td class="kpi-cell">
            <div class="kpi-label">GRN Count</div>
            <div class="kpi-value">{{ $grnSummary->count() }}</div>
        </td>
    </tr>
</table>

{{-- ── Container & Voyage details ──────────────────────────── --}}
<table class="info-grid">
    <tr>
        {{-- Container Details --}}
        <td class="info-card">
            <div class="info-label">Container Details</div>
            <div class="info-row">
                <span class="info-key">Container No</span>
                <span class="info-val mono">{{ $c->container_no ?? '—' }}</span>
            </div>
            @if ($c->internal_number)
            <div class="info-row">
                <span class="info-key">Internal No</span>
                <span class="info-val mono">{{ $c->internal_number }}</span>
            </div>
            @endif
            @if ($c->iso_number)
            <div class="info-row">
                <span class="info-key">ISO No</span>
                <span class="info-val mono">{{ $c->iso_number }}</span>
            </div>
            @endif
            <div class="info-row">
                <span class="info-key">Size / Type</span>
                <span class="info-val">{{ $c->size_type?->label() ?? '—' }}</span>
            </div>
            @if ($c->free_days)
            <div class="info-row">
                <span class="info-key">Free Days</span>
                <span class="info-val">{{ $c->free_days }} days</span>
            </div>
            @endif
        </td>

        {{-- Voyage Details --}}
        <td class="info-card">
            <div class="info-label">Voyage Details</div>
            <div class="info-row">
                <span class="info-key">Vessel</span>
                <span class="info-val">{{ $c->vessel ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="info-key">Voyage</span>
                <span class="info-val mono">{{ $c->voyage ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="info-key">BL No</span>
                <span class="info-val mono">{{ $c->bl_no ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="info-key">ETA</span>
                <span class="info-val">{{ $fmt($c->eta, 'date') }}</span>
            </div>
            <div class="info-row">
                <span class="info-key">Arrived</span>
                <span class="info-val">{{ $fmt($c->arrived_at, 'dt') }}</span>
            </div>
            @if ($c->unloaded_at)
            <div class="info-row">
                <span class="info-key">Unloaded</span>
                <span class="info-val">{{ $fmt($c->unloaded_at, 'dt') }}</span>
            </div>
            @endif
            @if ($c->released_at)
            <div class="info-row">
                <span class="info-key">Released</span>
                <span class="info-val">{{ $fmt($c->released_at, 'dt') }}</span>
            </div>
            @endif
        </td>

        {{-- Partners --}}
        <td class="info-card">
            <div class="info-label">Partners</div>
            <div class="info-row">
                <span class="info-key">Shipper</span>
                <span class="info-val">{{ $c->shipperPartner?->name ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="info-key">Consignee</span>
                <span class="info-val">{{ $c->consigneePartner?->name ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="info-key">Shipping Line</span>
                <span class="info-val">{{ $c->shippingLinePartner?->name ?? '—' }}</span>
            </div>
            @if ($c->releasedToPartner)
            <div class="info-row">
                <span class="info-key">Released To</span>
                <span class="info-val">{{ $c->releasedToPartner->name }}</span>
            </div>
            @endif
        </td>

        {{-- PO & Seal --}}
        <td class="info-card">
            <div class="info-label">PO & Seal Verification</div>
            @if ($po)
            <div class="info-row">
                <span class="info-key">PO Number</span>
                <span class="info-val mono">{{ $po->number }}</span>
            </div>
            @if ($po->customer_po_ref)
            <div class="info-row">
                <span class="info-key">Customer PO Ref</span>
                <span class="info-val mono">{{ $po->customer_po_ref }}</span>
            </div>
            @endif
            @elseif ($c->po_number)
            <div class="info-row">
                <span class="info-key">PO Number</span>
                <span class="info-val mono">{{ $c->po_number }}</span>
            </div>
            @endif
            <div class="info-row">
                <span class="info-key">Original Seal</span>
                <span class="info-val mono">{{ $c->seal_no ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="info-key">Arrival Seal</span>
                <span class="info-val mono">{{ $c->arrival_seal_no ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="info-key">Seal Verified</span>
                <span class="info-val">
                    @if ($c->seal_verified === true)
                        <span style="color:#15803d;font-weight:bold;">YES</span>
                    @elseif ($c->seal_verified === false)
                        <span style="color:#b91c1c;font-weight:bold;">NO</span>
                    @else
                        —
                    @endif
                </span>
            </div>
            @if ($c->seal_mismatch_reason)
            <div class="info-row">
                <span class="info-key">Mismatch Reason</span>
                <span class="info-val" style="color:#b91c1c;">{{ $c->seal_mismatch_reason }}</span>
            </div>
            @endif
        </td>
    </tr>
</table>

{{-- ── GRN Summary ──────────────────────────────────────────── --}}
@if ($grnSummary->isNotEmpty())
<div class="section-head">GRN Summary ({{ $grnSummary->count() }} {{ $grnSummary->count() === 1 ? 'Receipt' : 'Receipts' }})</div>
<table class="grn-table" style="margin-bottom:10px;">
    <thead>
        <tr>
            <th>GRN Number</th>
            <th>Status</th>
            <th>Confirmed Date</th>
            <th>Confirmed By</th>
            <th style="text-align:right;">Lines</th>
            <th style="text-align:right;">Qty Good</th>
            <th style="text-align:right;">Qty Damaged</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($grnSummary as $g)
        <tr>
            <td class="mono" style="font-weight:600;">{{ $g['number'] }}</td>
            <td>{{ $g['status'] }}</td>
            <td>{{ $g['confirmed_at'] }}</td>
            <td>{{ $g['confirmed_by'] }}</td>
            <td class="r">{{ $g['line_count'] }}</td>
            <td class="r" style="color:#15803d;font-weight:600;">{{ number_format($g['total_good'], 2) }}</td>
            <td class="r" style="{{ $g['total_damaged'] > 0 ? 'color:#b91c1c;font-weight:600;' : 'color:#9ca3af;' }}">{{ number_format($g['total_damaged'], 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- ── Received Lines detail ────────────────────────────────── --}}
@if ($lines->isNotEmpty())
<div class="section-head">Received Lines — Item Detail ({{ $lines->count() }} lines)</div>
<table class="data">
    <thead>
        <tr>
            <th>GRN No</th>
            <th>SKU</th>
            <th>Item Name</th>
            <th>MIC No</th>
            <th>UOM</th>
            <th class="r">Expected</th>
            <th class="r">Good Rcvd</th>
            <th class="r">Damaged</th>
            <th class="r">Variance</th>
            <th>Variance Type</th>
            <th>Remarks</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lines as $row)
        @php
            $varClass = match($row['variance_type_raw']) {
                'short'   => 'var-short',
                'excess'  => 'var-excess',
                'damaged' => 'var-damaged',
                'mixed'   => 'var-mixed',
                default   => 'var-none',
            };
        @endphp
        <tr class="{{ $row['is_unexpected'] ? 'unexpected' : '' }}">
            <td class="mono" style="white-space:nowrap;">{{ $row['grn_number'] }}</td>
            <td class="mono" style="font-weight:600;white-space:nowrap;">{{ $row['item_sku'] }}</td>
            <td>{{ $row['item_name'] }}</td>
            <td class="mono" style="font-size:7px;">{{ $row['mic_number'] ?: '—' }}</td>
            <td>{{ $row['uom'] }}</td>
            <td class="r">{{ number_format($row['expected_qty'], 2) }}</td>
            <td class="r" style="color:#15803d;font-weight:600;">{{ number_format($row['received_good_qty'], 2) }}</td>
            <td class="r" style="{{ $row['received_damaged_qty'] > 0 ? 'color:#b91c1c;font-weight:600;' : 'color:#9ca3af;' }}">
                {{ number_format($row['received_damaged_qty'], 2) }}
            </td>
            <td class="r {{ $varClass }}" style="font-weight:{{ $row['variance_qty'] != 0 ? 'bold' : 'normal' }};">
                {{ $row['variance_qty'] != 0 ? (($row['variance_qty'] > 0 ? '+' : '') . number_format($row['variance_qty'], 2)) : '—' }}
            </td>
            <td class="{{ $varClass }}" style="font-size:7px;">
                {{ $row['variance_type'] ?: ($row['is_unexpected'] ? 'Unexpected' : '—') }}
            </td>
            <td style="font-size:7px;color:#6b7280;">{{ $row['remarks'] ?: '—' }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" style="font-weight:bold;">TOTALS</td>
            <td class="r">{{ number_format($totalExpected, 2) }}</td>
            <td class="r" style="color:#15803d;">{{ number_format($totalGood, 2) }}</td>
            <td class="r" style="{{ $totalDamaged > 0 ? 'color:#b91c1c;' : 'color:#9ca3af;' }}">{{ number_format($totalDamaged, 2) }}</td>
            <td class="r {{ $totalVariance < 0 ? 'var-short' : ($totalVariance > 0 ? 'var-excess' : 'var-none') }}">
                {{ $totalVariance != 0 ? (($totalVariance > 0 ? '+' : '') . number_format($totalVariance, 2)) : '—' }}
            </td>
            <td colspan="2">
                {{ $varianceSummary['with_variance'] }} line(s) with variance
                @if ($varianceSummary['unexpected'] > 0)
                    &middot; {{ $varianceSummary['unexpected'] }} unexpected
                @endif
            </td>
        </tr>
    </tfoot>
</table>
@else
<div class="section-head">Received Lines</div>
<p style="font-size:8px;color:#6b7280;margin:8px 0;">No receiving lines recorded yet for this container.</p>
@endif

{{-- ── Notes ────────────────────────────────────────────────── --}}
@if ($c->notes || $c->release_notes || ($varianceSummary['with_variance'] > 0 && $c->discrepancies?->isNotEmpty()))
<div class="section-head">Notes &amp; Remarks</div>
<table style="width:100%;border-collapse:separate;border-spacing:6px 0;">
    <tr>
        @if ($c->notes)
        <td style="vertical-align:top;border:1px solid #e5e7eb;border-radius:4px;padding:5px 8px;font-size:7.5px;color:#374151;">
            <strong style="display:block;font-size:7px;text-transform:uppercase;color:#9ca3af;letter-spacing:0.06em;margin-bottom:3px;">Container Notes</strong>
            {{ $c->notes }}
        </td>
        @endif
        @if ($c->release_notes)
        <td style="vertical-align:top;border:1px solid #e5e7eb;border-radius:4px;padding:5px 8px;font-size:7.5px;color:#374151;">
            <strong style="display:block;font-size:7px;text-transform:uppercase;color:#9ca3af;letter-spacing:0.06em;margin-bottom:3px;">Release Notes</strong>
            {{ $c->release_notes }}
        </td>
        @endif
    </tr>
</table>
@endif

{{-- ── Sign-off ─────────────────────────────────────────────── --}}
<table class="signoff">
    <tr>
        <td>
            <div class="role">Prepared By</div>
            <div class="lines">Name: ______________________</div>
            <div class="lines">Signature: __________________</div>
            <div class="lines">Date: _______________________</div>
        </td>
        <td>
            <div class="role">Checked By</div>
            <div class="lines">Name: ______________________</div>
            <div class="lines">Signature: __________________</div>
            <div class="lines">Date: _______________________</div>
        </td>
        <td>
            <div class="role">Warehouse Manager</div>
            <div class="lines">Name: ______________________</div>
            <div class="lines">Signature: __________________</div>
            <div class="lines">Date: _______________________</div>
        </td>
        <td>
            <div class="role">Approved By</div>
            <div class="lines">Name: ______________________</div>
            <div class="lines">Signature: __________________</div>
            <div class="lines">Date: _______________________</div>
        </td>
    </tr>
</table>

<div class="footer-note">
    This document was system-generated on {{ $generatedAt }}. It is a controlled document — any manual alterations render it invalid.
</div>

</body>
</html>
