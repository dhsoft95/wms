@php
    /**
     * Variables injected by ReceivedDispatchedReportService::build()
     * @var array  $brand         ['name','address','logoDataUri']
     * @var string $dateFrom
     * @var string $dateTo
     * @var string|null $warehouseName
     * @var array  $summary       label => formatted string
     * @var \Illuminate\Support\Collection $received
     * @var \Illuminate\Support\Collection $dispatched
     * @var \Illuminate\Support\Collection $movements
     * @var \Illuminate\Support\Collection $remarks
     * @var string $generatedAt
     * @var string $generatedBy
     */

    $fmt = function ($val, string $type = 'text', int $dec = 2): string {
        if ($val === null || $val === '') return '—';
        return match ($type) {
            'number' => number_format((float) $val, $dec),
            'signed' => ((float) $val > 0 ? '+' : '') . number_format((float) $val, $dec),
            'date'   => \Carbon\Carbon::parse($val)->format('d M Y'),
            default  => (string) $val,
        };
    };

    $toneClass = function (float $val): string {
        if ($val < 0) return 'neg';
        if ($val > 0) return 'pos';
        return 'zero';
    };

    $periodLabel = \Carbon\Carbon::parse($dateFrom)->format('d M Y')
        . ' – '
        . \Carbon\Carbon::parse($dateTo)->format('d M Y');

    // Received subtotals
    $recExpected   = $received->sum('expected_qty');
    $recGood       = $received->sum('received_good_qty');
    $recDamaged    = $received->sum('received_damaged_qty');
    $recVariance   = $received->sum('variance_qty');

    // Dispatched subtotals
    $dispDispatched = $dispatched->sum('dispatched_qty');
    $dispShort      = $dispatched->sum('short_qty');

    // Movement grand totals
    $movOpening    = $movements->sum('opening_qty');
    $movReceived   = $movements->sum('received_qty');
    $movDispatched = $movements->sum('dispatched_qty');
    $movClosing    = $movements->sum('closing_qty');
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 14mm 12mm 20mm 12mm; size: A4 landscape; }
    * { box-sizing: border-box; }
    body {
        font-family: DejaVu Sans, Arial, sans-serif;
        font-size: 8.5px;
        color: #1a1a1a;
        margin: 0;
    }

    /* Brand */
    .brand       { text-align: center; margin-bottom: 6px; }
    .brand-logo  { max-height: 48px; max-width: 160px; display: inline-block; }
    .brand-addr  { font-size: 7px; color: #6b7280; margin-top: 2px; }

    /* Page header */
    .pg-header   { width: 100%; border-collapse: collapse; border-bottom: 2.5px solid #a22631; padding-bottom: 8px; margin-bottom: 10px; }
    .pg-header td { vertical-align: bottom; border: 0; padding: 0 4px 4px 0; }
    .pg-title    { font-size: 13px; font-weight: bold; color: #111; }
    .pg-meta     { font-size: 8px; color: #555; text-align: right; line-height: 1.7; }
    .pg-meta strong { color: #222; }

    /* Summary KPIs */
    .kpi-row   { width: 100%; border-collapse: separate; border-spacing: 5px 0; margin-bottom: 12px; table-layout: fixed; }
    .kpi-box   { border: 1px solid #e5e7eb; background: #fafafa; padding: 5px 7px; vertical-align: top; }
    .kpi-label { font-size: 7px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
    .kpi-value { font-size: 12px; font-weight: bold; color: #111; margin-top: 2px; }

    /* Section headers */
    .section-title {
        font-size: 9px;
        font-weight: bold;
        color: #a22631;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-left: 3px solid #a22631;
        padding: 2px 0 2px 6px;
        margin: 12px 0 5px 0;
    }

    /* Tables */
    .data { width: 100%; border-collapse: collapse; }
    .data thead { display: table-header-group; }
    .data tr    { page-break-inside: avoid; }
    .data th {
        background: #374151;
        color: #fff;
        font-size: 7.5px;
        font-weight: bold;
        padding: 3px 4px;
        border: 1px solid #2d3748;
        white-space: nowrap;
    }
    .data td {
        border: 1px solid #e5e7eb;
        padding: 2.5px 4px;
        font-size: 8px;
        vertical-align: top;
    }
    .data tr.stripe td { background: #f9fafb; }

    /* Variance highlight */
    .data tr.has-variance td   { background: #fff7ed; }
    .data tr.has-variance td:first-child { border-left: 2px solid #d97706; }
    .data tr.has-damage td     { background: #fef2f2; }
    .data tr.has-damage td:first-child   { border-left: 2px solid #b91c1c; }
    .data tr.has-short td      { background: #fff7ed; }
    .data tr.has-short td:first-child    { border-left: 2px solid #d97706; }

    /* Subtotal / totals rows */
    .subtotal-row td { background: #f3f4f6; font-weight: bold; font-size: 8px; border-top: 1.5px solid #9ca3af; }
    .totals-row td   { background: #e5e7eb; font-weight: bold; font-size: 8px; border-top: 2px solid #a22631; }
    .empty-row td    { text-align: center; color: #9ca3af; padding: 12px; font-style: italic; }

    .text-left   { text-align: left; }
    .text-right  { text-align: right; }
    .text-center { text-align: center; }
    .neg  { color: #b91c1c; font-weight: bold; }
    .pos  { color: #166534; }
    .zero { color: #9ca3af; }

    /* Remarks table */
    .remarks-table { width: 100%; border-collapse: collapse; }
    .remarks-table th { background: #f3f4f6; color: #374151; font-size: 7.5px; padding: 3px 5px; border: 1px solid #d1d5db; }
    .remarks-table td { border: 1px solid #e5e7eb; padding: 2.5px 5px; font-size: 8px; }
    .remarks-table tr.stripe td { background: #f9fafb; }
    .badge        { border-radius: 2px; padding: 1px 4px; font-size: 7px; font-weight: bold; }
    .badge-damage { background: #fef2f2; color: #b91c1c; }
    .badge-var    { background: #fff7ed; color: #d97706; }
    .badge-short  { background: #fffbeb; color: #b45309; }
    .no-remarks   { color: #9ca3af; font-style: italic; font-size: 8.5px; padding: 8px 0; }

    /* Sign-off */
    .signoff-table { width: 100%; border-collapse: separate; border-spacing: 10px 0; margin-top: 20px; table-layout: fixed; }
    .signoff-cell  { border: 1px solid #d1d5db; padding: 8px 10px 16px 10px; vertical-align: top; }
    .signoff-role  { font-size: 7.5px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
    .signoff-name  { font-size: 8.5px; font-weight: bold; color: #111; margin-bottom: 20px; }
    .signoff-line  { border-bottom: 1px solid #9ca3af; margin-bottom: 4px; }
    .signoff-date  { font-size: 7.5px; color: #9ca3af; }

    /* System footer note */
    .sys-note { font-size: 7px; color: #9ca3af; text-align: center; margin-top: 16px; }
</style>
</head>
<body>

{{-- dompdf PHP footer script: Page X of Y + company line --}}
<script type="text/php">
    if (isset($pdf)) {
        $font  = $fontMetrics->getFont("DejaVu Sans", "normal");
        $gray  = [0.6, 0.6, 0.6];
        $size  = 7;
        $y     = $pdf->get_height() - 14;
        $w     = $pdf->get_width();

        $left  = "{{ addslashes($brand['name']) }} \xB7 {{ addslashes($brand['address'] ?? '') }}";
        $mid   = "System generated report";
        $right = "Page {PAGE_NUM} of {PAGE_COUNT}";

        $pdf->page_text(10,        $y, $left,  $font, $size, $gray);
        $pdf->page_text($w / 2 - 30, $y, $mid, $font, $size, $gray);
        $pdf->page_text($w - 65,   $y, $right, $font, $size, $gray);
        $pdf->line(10, $y - 3, $w - 10, $y - 3, [0.9, 0.9, 0.9], 0.5);
    }
</script>

{{-- ── BRAND ── --}}
<div class="brand">
    @if (! empty($brand['logoDataUri']))
        <img src="{{ $brand['logoDataUri'] }}" class="brand-logo" alt="">
    @endif
    @if (! empty($brand['address']))
        <div class="brand-addr">{{ $brand['address'] }}</div>
    @endif
</div>

{{-- ── PAGE HEADER ── --}}
<table class="pg-header">
    <tr>
        <td width="55%">
            <div class="pg-title">Received &amp; Dispatched Report</div>
        </td>
        <td width="45%" class="pg-meta">
            <strong>Period:</strong> {{ $periodLabel }}<br>
            @if ($warehouseName)
                <strong>Warehouse:</strong> {{ $warehouseName }}<br>
            @endif
            <strong>Generated:</strong> {{ $generatedAt }}<br>
            <strong>Generated by:</strong> {{ $generatedBy }}
        </td>
    </tr>
</table>

{{-- ── SUMMARY KPIs ── --}}
<table class="kpi-row">
    <tr>
        @foreach ($summary as $label => $value)
            <td class="kpi-box">
                <div class="kpi-label">{{ $label }}</div>
                <div class="kpi-value">{{ $value }}</div>
            </td>
        @endforeach
    </tr>
</table>

{{-- ═══════════════════════════════════════════ --}}
{{-- SECTION 1: RECEIVED                        --}}
{{-- ═══════════════════════════════════════════ --}}
<div class="section-title">Received</div>

<table class="data">
    <thead>
        <tr>
            <th class="text-left"  style="width:7%">Date</th>
            <th class="text-left"  style="width:8%">GRN No.</th>
            <th class="text-left"  style="width:10%">Reference</th>
            <th class="text-left"  style="width:10%">Supplier</th>
            <th class="text-left"  style="width:7%">SKU</th>
            <th class="text-left"  style="width:20%">Product</th>
            <th class="text-right" style="width:8%">Expected</th>
            <th class="text-right" style="width:9%">Received Good</th>
            <th class="text-right" style="width:7%">Damaged</th>
            <th class="text-right" style="width:7%">Variance</th>
            <th class="text-left"  style="width:7%">Var. Type</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($received as $row)
        @php
            $hasDamage   = (float) $row->received_damaged_qty > 0;
            $hasVariance = (float) $row->variance_qty !== 0.0 && ! $hasDamage;
            $rowClass    = $hasDamage ? 'has-damage' : ($hasVariance ? 'has-variance' : ($loop->even ? 'stripe' : ''));
        @endphp
        <tr class="{{ $rowClass }}">
            <td class="text-left">{{ $fmt($row->date, 'date') }}</td>
            <td class="text-left">{{ $row->grn_number }}</td>
            <td class="text-left">{{ $row->reference ?? '—' }}</td>
            <td class="text-left">{{ $row->supplier_name ?? '—' }}</td>
            <td class="text-left">{{ $row->sku }}</td>
            <td class="text-left">{{ $row->item_name }}</td>
            <td class="text-right">{{ $fmt($row->expected_qty, 'number') }}</td>
            <td class="text-right">{{ $fmt($row->received_good_qty, 'number') }}</td>
            <td class="text-right {{ (float)$row->received_damaged_qty > 0 ? 'neg' : 'zero' }}">
                {{ $fmt($row->received_damaged_qty, 'number') }}
            </td>
            <td class="text-right {{ $toneClass((float) $row->variance_qty) }}">
                {{ $fmt($row->variance_qty, 'signed') }}
            </td>
            <td class="text-left">{{ $row->variance_type ?: '—' }}</td>
        </tr>
    @empty
        <tr class="empty-row"><td colspan="11">No receipts recorded for this period.</td></tr>
    @endforelse

    @if ($received->isNotEmpty())
        <tr class="subtotal-row">
            <td colspan="6" class="text-left">Subtotal</td>
            <td class="text-right">{{ number_format((float) $recExpected, 2) }}</td>
            <td class="text-right">{{ number_format((float) $recGood, 2) }}</td>
            <td class="text-right {{ (float)$recDamaged > 0 ? 'neg' : 'zero' }}">{{ number_format((float) $recDamaged, 2) }}</td>
            <td class="text-right {{ $toneClass((float) $recVariance) }}">{{ ((float)$recVariance > 0 ? '+' : '') . number_format((float) $recVariance, 2) }}</td>
            <td></td>
        </tr>
    @endif
    </tbody>
</table>

{{-- ═══════════════════════════════════════════ --}}
{{-- SECTION 2: DISPATCHED                      --}}
{{-- ═══════════════════════════════════════════ --}}
<div class="section-title">Dispatched</div>

<table class="data">
    <thead>
        <tr>
            <th class="text-left"  style="width:8%">Date</th>
            <th class="text-left"  style="width:9%">Dispatch No.</th>
            <th class="text-left"  style="width:10%">Order Ref</th>
            <th class="text-left"  style="width:14%">Consignee</th>
            <th class="text-left"  style="width:7%">SKU</th>
            <th class="text-left"  style="width:18%">Product</th>
            <th class="text-right" style="width:9%">Dispatched</th>
            <th class="text-right" style="width:7%">Short</th>
            <th class="text-left"  style="width:18%">Vehicle / Driver</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($dispatched as $row)
        @php
            $hasShort = (float) $row->short_qty > 0;
            $rowClass = $hasShort ? 'has-short' : ($loop->even ? 'stripe' : '');
        @endphp
        <tr class="{{ $rowClass }}">
            <td class="text-left">{{ $fmt($row->date, 'date') }}</td>
            <td class="text-left">{{ $row->dispatch_number ?? '—' }}</td>
            <td class="text-left">{{ $row->order_ref ?? '—' }}</td>
            <td class="text-left">{{ $row->consignee_name ?? '—' }}</td>
            <td class="text-left">{{ $row->sku }}</td>
            <td class="text-left">{{ $row->item_name }}</td>
            <td class="text-right">{{ $fmt($row->dispatched_qty, 'number') }}</td>
            <td class="text-right {{ $hasShort ? 'neg' : 'zero' }}">{{ $fmt($row->short_qty, 'number') }}</td>
            <td class="text-left">{{ $row->vehicle_driver ?: '—' }}</td>
        </tr>
    @empty
        <tr class="empty-row"><td colspan="9">No dispatches recorded for this period.</td></tr>
    @endforelse

    @if ($dispatched->isNotEmpty())
        <tr class="subtotal-row">
            <td colspan="6" class="text-left">Subtotal</td>
            <td class="text-right">{{ number_format((float) $dispDispatched, 2) }}</td>
            <td class="text-right {{ (float)$dispShort > 0 ? 'neg' : 'zero' }}">{{ number_format((float) $dispShort, 2) }}</td>
            <td></td>
        </tr>
    @endif
    </tbody>
</table>

{{-- ═══════════════════════════════════════════ --}}
{{-- SECTION 3: STOCK MOVEMENT SUMMARY PER SKU  --}}
{{-- ═══════════════════════════════════════════ --}}
<div class="section-title">Stock Movement Summary (per SKU)</div>

<table class="data">
    <thead>
        <tr>
            <th class="text-left"  style="width:10%">SKU</th>
            <th class="text-left"  style="width:30%">Product</th>
            <th class="text-right" style="width:12%">Opening</th>
            <th class="text-right" style="width:12%">Received</th>
            <th class="text-right" style="width:12%">Dispatched</th>
            <th class="text-right" style="width:12%">Adjustments</th>
            <th class="text-right" style="width:12%">Closing</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($movements as $row)
        <tr class="{{ $loop->even ? 'stripe' : '' }}">
            <td class="text-left">{{ $row->sku }}</td>
            <td class="text-left">{{ $row->item_name }}</td>
            <td class="text-right">{{ number_format((float) $row->opening_qty, 2) }}</td>
            <td class="text-right pos">{{ number_format((float) $row->received_qty, 2) }}</td>
            <td class="text-right">{{ number_format((float) $row->dispatched_qty, 2) }}</td>
            <td class="text-right {{ $toneClass((float) $row->adjustment_qty) }}">
                {{ ((float)$row->adjustment_qty > 0 ? '+' : '') . number_format((float) $row->adjustment_qty, 2) }}
            </td>
            <td class="text-right">{{ number_format((float) $row->closing_qty, 2) }}</td>
        </tr>
    @empty
        <tr class="empty-row"><td colspan="7">No stock movements recorded for this period.</td></tr>
    @endforelse

    @if ($movements->isNotEmpty())
        <tr class="totals-row">
            <td colspan="2" class="text-left">Grand Total</td>
            <td class="text-right">{{ number_format((float) $movOpening, 2) }}</td>
            <td class="text-right">{{ number_format((float) $movReceived, 2) }}</td>
            <td class="text-right">{{ number_format((float) $movDispatched, 2) }}</td>
            <td class="text-right"></td>
            <td class="text-right">{{ number_format((float) $movClosing, 2) }}</td>
        </tr>
    @endif
    </tbody>
</table>

{{-- ═══════════════════════════════════════════ --}}
{{-- SECTION 4: REMARKS & DISCREPANCIES         --}}
{{-- ═══════════════════════════════════════════ --}}
<div class="section-title">Remarks &amp; Discrepancies</div>

@if ($remarks->isEmpty())
    <div class="no-remarks">No remarks — no variances, damages, or shorts recorded for this period.</div>
@else
    <table class="remarks-table">
        <thead>
            <tr>
                <th class="text-center" style="width:8%">Type</th>
                <th class="text-left"   style="width:12%">Reference</th>
                <th class="text-left"   style="width:10%">SKU</th>
                <th class="text-left"   style="width:30%">Product</th>
                <th class="text-left"   style="width:40%">Note</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($remarks as $remark)
            @php
                $badgeClass = match ($remark['type']) {
                    'Damage'   => 'badge-damage',
                    'Variance' => 'badge-var',
                    default    => 'badge-short',
                };
            @endphp
            <tr class="{{ $loop->even ? 'stripe' : '' }}">
                <td class="text-center">
                    <span class="badge {{ $badgeClass }}">{{ $remark['type'] }}</span>
                </td>
                <td class="text-left">{{ $remark['ref'] }}</td>
                <td class="text-left">{{ $remark['sku'] }}</td>
                <td class="text-left">{{ $remark['item'] }}</td>
                <td class="text-left">{{ $remark['note'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

{{-- ═══════════════════════════════════════════ --}}
{{-- SECTION 5: SIGN-OFF                        --}}
{{-- ═══════════════════════════════════════════ --}}
<table class="signoff-table">
    <tr>
        <td class="signoff-cell">
            <div class="signoff-role">Prepared by</div>
            <div class="signoff-name">{{ $generatedBy }}</div>
            <div class="signoff-line">&nbsp;</div>
            <div class="signoff-date">Signature &amp; Date</div>
        </td>
        <td class="signoff-cell">
            <div class="signoff-role">Checked by</div>
            <div class="signoff-name">&nbsp;</div>
            <div class="signoff-line">&nbsp;</div>
            <div class="signoff-date">Signature &amp; Date</div>
        </td>
        <td class="signoff-cell">
            <div class="signoff-role">Approved by</div>
            <div class="signoff-name">&nbsp;</div>
            <div class="signoff-line">&nbsp;</div>
            <div class="signoff-date">Signature &amp; Date</div>
        </td>
    </tr>
</table>

<div class="sys-note">System generated report &mdash; {{ $brand['name'] }}</div>

</body>
</html>
