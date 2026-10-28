@php
$c   = $container;
$po  = $c->purchaseOrder;
$brandFooterName = addslashes($brand['name']);
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
        color: #111;
        margin: 0;
    }

    script[type="text/php"] { display: none; }

    /* ── Brand ── */
    .brand      { text-align: center; margin-bottom: 6px; }
    .brand-logo { max-height: 70px; max-width: 200px; display: inline-block; }
    .brand-name { font-size: 11px; font-weight: bold; }
    .brand-addr { font-size: 7px; color: #555; margin-top: 2px; }

    /* ── Page header ── */
    .pg-header    { width: 100%; border-collapse: collapse; border-bottom: 2px solid #111; margin-bottom: 10px; }
    .pg-header td { vertical-align: bottom; border: 0; padding: 0 4px 5px 0; }
    .pg-title     { font-size: 14px; font-weight: bold; }
    .pg-subtitle  { font-size: 9px; color: #444; margin-top: 2px; }
    .pg-meta      { font-size: 7.5px; color: #444; text-align: right; line-height: 1.9; }
    .pg-meta strong { font-size: 8px; }

    /* ── Section heading ── */
    .section-head {
        font-size: 8px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        border-bottom: 1px solid #bbb;
        padding-bottom: 3px;
        margin-bottom: 6px;
        margin-top: 10px;
    }

    /* ── Summary table (replaces KPI cards) ── */
    table.summary { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 8px; }
    table.summary td {
        border: 1px solid #ccc;
        padding: 4px 8px;
        vertical-align: top;
    }
    table.summary td.lbl { color: #555; font-size: 7.5px; width: 50%; }
    table.summary td.val { font-weight: bold; }

    /* ── Info grid ── */
    .info-grid  { width: 100%; border-collapse: separate; border-spacing: 5px 0; margin-bottom: 10px; table-layout: fixed; }
    .info-card  { border: 1px solid #ccc; padding: 6px 8px; vertical-align: top; }
    .info-label { font-size: 7px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.06em; color: #777; margin-bottom: 4px; }
    .info-row   { display: table; width: 100%; margin-bottom: 2px; }
    .info-key   { display: table-cell; font-size: 7.5px; color: #555; width: 44%; }
    .info-val   { display: table-cell; font-size: 8px; font-weight: 600; }
    .info-val.mono { font-family: 'DejaVu Sans Mono', monospace; }

    /* ── Tables ── */
    table.data { width: 100%; border-collapse: collapse; font-size: 7.5px; }
    table.data thead th {
        background: #f0f0f0;
        border: 1px solid #ccc;
        padding: 4px 5px;
        text-align: left;
        font-weight: bold;
        font-size: 7px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #333;
        white-space: nowrap;
    }
    table.data thead th.r { text-align: right; }
    table.data tbody td {
        border: 1px solid #ddd;
        padding: 3.5px 5px;
        vertical-align: top;
    }
    table.data tbody td.r    { text-align: right; }
    table.data tbody td.mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 7px; }
    table.data tbody tr:nth-child(even) td { background: #fafafa; }
    table.data tfoot td {
        border-top: 2px solid #999;
        border-bottom: 1px solid #ccc;
        padding: 4px 5px;
        font-weight: bold;
        background: #efefef;
    }
    table.data tfoot td.r { text-align: right; }

    /* ── Sign-off ── */
    .signoff { width: 100%; border-collapse: separate; border-spacing: 14px 0; margin-top: 14px; table-layout: fixed; }
    .signoff td { border-top: 1px solid #444; padding-top: 5px; vertical-align: top; font-size: 7.5px; }
    .signoff .role  { font-weight: bold; font-size: 8px; }
    .signoff .lines { margin-top: 14px; font-size: 7px; color: #777; }

    /* ── Footer note ── */
    .footer-note {
        margin-top: 10px;
        font-size: 7px;
        color: #777;
        text-align: center;
        border-top: 1px solid #ddd;
        padding-top: 4px;
    }
</style>
</head>
<body>

<script type="text/php">
    if (isset($pdf)) {
        $pdf->page_script('
            $font = $fontMetrics->get_font("DejaVu Sans", "normal");
            $size = 7;
            $color = [0.5, 0.5, 0.5];
            $left  = "{!! $brandFooterName !!}  |  Container Receipt Report";
            $center = "System generated — do not alter";
            $right = "Page " . $PAGE_NUM . " of " . $PAGE_COUNT;
            $w = $pdf->get_width();
            $h = $pdf->get_height();
            $m = 34;
            $pdf->text($m, $h - 14, $left, $font, $size, $color);
            $cw = $fontMetrics->get_text_width($center, $font, $size);
            $pdf->text(($w - $cw) / 2, $h - 14, $center, $font, $size, $color);
            $rw = $fontMetrics->get_text_width($right, $font, $size);
            $pdf->text($w - $m - $rw, $h - 14, $right, $font, $size, $color);
        ');
    }
</script>

{{-- ── Brand ── --}}
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

{{-- ── Page header ── --}}
<table class="pg-header">
    <tr>
        <td width="60%">
            <div class="pg-title">Container Receipt Report</div>
            <div class="pg-subtitle">
                {{ $c->container_no }}
                @if ($c->number && $c->number !== $c->container_no)
                    &nbsp;&middot;&nbsp;{{ $c->number }}
                @endif
                @if ($c->size_type)
                    &nbsp;&middot;&nbsp;{{ $c->size_type->label() }}
                @endif
                &nbsp;&middot;&nbsp;{{ $c->status?->label() ?? '—' }}
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

{{-- ── Container & Voyage details ── --}}
<table class="info-grid">
    <tr>
        <td class="info-card">
            <div class="info-label">Container</div>
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

<td class="info-card">
            <div class="info-label">Partners</div>
            <div class="info-row">
                <span class="info-key">Shipper / Supplier</span>
                <span class="info-val">{{ $c->shipperPartner?->name ?? $po?->supplierPartner?->name ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="info-key">Consignee</span>
                <span class="info-val">{{ $c->consigneePartner?->name ?? $po?->consigneePartner?->name ?? '—' }}</span>
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

        <td class="info-card">
            <div class="info-label">PO &amp; Seal</div>
            @if ($po)
            <div class="info-row">
                <span class="info-key">PO Number</span>
                <span class="info-val mono">{{ $po->number }}</span>
            </div>
            @if ($po->customer_po_ref)
            <div class="info-row">
                <span class="info-key">Cust. PO Ref</span>
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
                <span class="info-val">@if ($c->seal_verified === true)YES @elseif ($c->seal_verified === false)NO @else — @endif</span>
            </div>
            @if ($c->seal_mismatch_reason)
            <div class="info-row">
                <span class="info-key">Mismatch Reason</span>
                <span class="info-val">{{ $c->seal_mismatch_reason }}</span>
            </div>
            @endif
        </td>
    </tr>
</table>

{{-- ── Receipt Summary ── --}}
<div class="section-head">Receipt Summary</div>
<table class="summary">
    <tr>
        <td class="lbl">Total Lines</td>
        <td class="val">{{ $varianceSummary['total_lines'] }}</td>
        <td class="lbl">Lines with Variance</td>
        <td class="val">{{ $varianceSummary['with_variance'] }}</td>
        <td class="lbl">Short Lines</td>
        <td class="val">{{ $varianceSummary['short_lines'] }}</td>
        <td class="lbl">Excess Lines</td>
        <td class="val">{{ $varianceSummary['excess_lines'] }}</td>
    </tr>
    <tr>
        <td class="lbl">Qty Expected</td>
        <td class="val">{{ number_format($totalExpected, 2) }}</td>
        <td class="lbl">Qty Received (Good)</td>
        <td class="val">{{ number_format($totalGood, 2) }}</td>
        <td class="lbl">Qty Damaged</td>
        <td class="val">{{ number_format($totalDamaged, 2) }}</td>
        <td class="lbl">Net Variance</td>
        <td class="val">{{ ($totalVariance > 0 ? '+' : '') . number_format($totalVariance, 2) }}</td>
    </tr>
</table>

{{-- ── GRN Summary ── --}}
@if ($grnSummary->isNotEmpty())
<div class="section-head">GRN Summary ({{ $grnSummary->count() }} {{ $grnSummary->count() === 1 ? 'Receipt' : 'Receipts' }})</div>
<table class="data" style="margin-bottom:10px;">
    <thead>
        <tr>
            <th>GRN Number</th>
            <th>Status</th>
            <th>Confirmed Date</th>
            <th>Confirmed By</th>
            <th class="r">Lines</th>
            <th class="r">Qty Good</th>
            <th class="r">Qty Damaged</th>
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
            <td class="r">{{ number_format($g['total_good'], 2) }}</td>
            <td class="r">{{ $g['total_damaged'] > 0 ? number_format($g['total_damaged'], 2) : '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- ── Received Lines ── --}}
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
            <th class="r">Rcvd Good</th>
            <th class="r">Damaged</th>
            <th class="r">Variance</th>
            <th>Variance Type</th>
            <th>Remarks</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lines as $row)
        <tr>
            <td class="mono" style="white-space:nowrap;">{{ $row['grn_number'] }}</td>
            <td class="mono" style="font-weight:600;white-space:nowrap;">{{ $row['item_sku'] }}</td>
            <td>{{ $row['item_name'] }}</td>
            <td class="mono" style="font-size:7px;">{{ $row['mic_number'] ?: '—' }}</td>
            <td>{{ $row['uom'] }}</td>
            <td class="r">{{ number_format($row['expected_qty'], 2) }}</td>
            <td class="r">{{ number_format($row['received_good_qty'], 2) }}</td>
            <td class="r">{{ $row['received_damaged_qty'] > 0 ? number_format($row['received_damaged_qty'], 2) : '—' }}</td>
            <td class="r" style="{{ $row['variance_qty'] != 0 ? 'font-weight:bold;' : '' }}">
                {{ $row['variance_qty'] != 0 ? (($row['variance_qty'] > 0 ? '+' : '') . number_format($row['variance_qty'], 2)) : '—' }}
            </td>
            <td style="font-size:7px;">
                {{ $row['variance_type'] ?: ($row['is_unexpected'] ? 'Unexpected' : '—') }}
            </td>
            <td style="font-size:7px;">{{ $row['remarks'] ?: '—' }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5">TOTALS</td>
            <td class="r">{{ number_format($totalExpected, 2) }}</td>
            <td class="r">{{ number_format($totalGood, 2) }}</td>
            <td class="r">{{ $totalDamaged > 0 ? number_format($totalDamaged, 2) : '—' }}</td>
            <td class="r" style="{{ $totalVariance != 0 ? 'font-weight:bold;' : '' }}">
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
<p style="font-size:8px;color:#555;margin:8px 0;">No receiving lines recorded yet for this container.</p>
@endif

{{-- ── Notes ── --}}
@if ($c->notes || $c->release_notes)
<div class="section-head">Notes &amp; Remarks</div>
<table style="width:100%;border-collapse:separate;border-spacing:5px 0;">
    <tr>
        @if ($c->notes)
        <td style="vertical-align:top;border:1px solid #ccc;padding:5px 8px;font-size:7.5px;">
            <strong style="display:block;font-size:7px;text-transform:uppercase;color:#777;letter-spacing:0.06em;margin-bottom:3px;">Container Notes</strong>
            {{ $c->notes }}
        </td>
        @endif
        @if ($c->release_notes)
        <td style="vertical-align:top;border:1px solid #ccc;padding:5px 8px;font-size:7.5px;">
            <strong style="display:block;font-size:7px;text-transform:uppercase;color:#777;letter-spacing:0.06em;margin-bottom:3px;">Release Notes</strong>
            {{ $c->release_notes }}
        </td>
        @endif
    </tr>
</table>
@endif

{{-- ── Sign-off ── --}}
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
