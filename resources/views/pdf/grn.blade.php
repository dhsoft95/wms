@php
    /** @var \App\Models\Grn $grn */
    /** @var array|null $brand  ['name','address','logoDataUri'] */
    /** @var string|null $brandName */

    $brand = $brand ?? ['name' => $brandName ?? '', 'address' => null, 'logoDataUri' => null];

    $qty = function ($v) {
        $v = (float) $v;
        return number_format($v, floor(abs($v)) == abs($v) ? 0 : 2);
    };

    $signed = function ($v) use ($qty) {
        $v = (float) $v;
        return ($v > 0 ? '+' : '') . $qty($v);
    };

    $lines = $grn->lines->values()->map(function ($line) {
        $good     = (float) ($line->received_good_qty ?? 0);
        $damaged  = (float) ($line->received_damaged_qty ?? 0);
        $expected = (float) ($line->expected_qty ?? 0);

        $receipts = $line->receipts ?? collect();

        $batchesFor = fn ($cond) => $receipts->where('condition', $cond)->pluck('batch_no')->filter()->unique()->values();
        $serialsFor = fn ($cond) => $receipts->where('condition', $cond)->flatMap(fn ($r) => $r->serial_nos ?? [])->filter()->unique()->values();

        return (object) [
            'sku'             => $line->item?->sku ?? '—',
            'name'            => $line->item?->name ?? '—',
            'uom'             => $line->uom?->code ?? '—',
            'expected'        => $expected,
            'good'            => $good,
            'damaged'         => $damaged,
            'variance'        => ($good + $damaged) - $expected,
            'good_batches'    => $batchesFor('good'),
            'good_serials'    => $serialsFor('good'),
            'damaged_batches' => $batchesFor('damaged'),
            'damaged_serials' => $serialsFor('damaged'),
        ];
    });

    $hasDamaged = $lines->sum('damaged') > 0;
    $singleUom  = $lines->pluck('uom')->unique()->count() === 1;
    $colCount   = $hasDamaged ? 9 : 8;

    $fullLines    = $lines->filter(fn ($l) => $l->variance == 0 && $l->damaged == 0)->count();
    $shortLines   = $lines->filter(fn ($l) => $l->variance < 0)->count();
    $overLines    = $lines->filter(fn ($l) => $l->variance > 0)->count();
    $damagedLines = $lines->filter(fn ($l) => $l->damaged > 0)->count();

    $receivedAt = $grn->confirmed_at ?? $grn->created_at;

    $tone = fn ($v) => $v < 0 ? 'neg' : ($v > 0 ? 'pos' : 'zero');

    $rowClass = function ($l, $even) {
        $cls = $even ? 'stripe' : '';
        if ($l->damaged > 0 || $l->variance < 0) {
            return trim($cls . ' flag');
        }
        if ($l->variance > 0) {
            return trim($cls . ' warn');
        }
        return $cls;
    };
@endphp
    <!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14mm 12mm 18mm 12mm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8.5px;
            color: #1a1a1a;
            margin: 0;
        }

        /* Brand */
        .rpt-brand   { text-align: center; margin-bottom: 8px; }
        .rpt-logo    { max-height: 52px; max-width: 180px; display: inline-block; }
        .rpt-address { font-size: 7.5px; color: #6b7280; margin-top: 3px; }

        /* Header */
        .rpt-header { width: 100%; border-bottom: 2.5px solid #a22631; padding-bottom: 8px; margin-bottom: 10px; }
        .rpt-header td { vertical-align: bottom; border: 0; padding: 0; }
        .rpt-title  { font-size: 13px; font-weight: bold; color: #111; }
        .rpt-sub    { font-size: 7.5px; color: #6b7280; margin-top: 2px; }
        .rpt-meta   { font-size: 8px; color: #555; text-align: right; line-height: 1.6; }
        .rpt-meta strong { color: #222; }

        /* KPI */
        .kpi-row   { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 12px -6px; table-layout: fixed; }
        .kpi-box   { border: 1px solid #e5e7eb; background: #fff; padding: 6px 8px; vertical-align: top; }
        .kpi-label { font-size: 7px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
        .kpi-value { font-size: 13px; font-weight: bold; color: #111; margin-top: 2px; }
        .kpi-hint  { font-size: 7px; color: #6b7280; margin-top: 1px; }

        /* Info cards */
        .info-row { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 12px -6px; }
        .info-card { border: 1px solid #e5e7eb; vertical-align: top; padding: 0; width: 50%; }
        .info-head {
            background: #f3f4f6;
            color: #a22631;
            font-weight: bold;
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 4px 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-body { width: 100%; border-collapse: collapse; }
        .info-body td { padding: 3px 8px; font-size: 8px; border: 0; }
        .info-body td.k { color: #6b7280; width: 38%; }
        .info-body td.v { color: #111; }

        /* Table */
        .section-title {
            font-size: 9px;
            font-weight: bold;
            color: #a22631;
            margin: 4px 0 5px;
        }
        .data { width: 100%; border-collapse: collapse; }
        .data thead { display: table-header-group; }
        .data tr    { page-break-inside: avoid; }
        .data th {
            background: #a22631;
            color: #fff;
            font-weight: bold;
            font-size: 7.5px;
            padding: 4px 5px;
            border: 1px solid #8a1d28;
            text-align: left;
        }
        .data td {
            border: 1px solid #e5e7eb;
            padding: 3px 5px;
            font-size: 8px;
            vertical-align: top;
        }
        .data tr.stripe td { background: #fafafa; }
        .data tr.flag td   { background: #fdecec; }
        .data tr.flag td:first-child { border-left: 2px solid #b91c1c; }
        .data tr.warn td   { background: #fff7e6; }
        .data tr.warn td:first-child { border-left: 2px solid #d97706; }
        .totals-row td     { background: #f3f4f6; font-weight: bold; border-top: 1.5px solid #a22631; }
        .empty-row td      { text-align: center; color: #9ca3af; padding: 14px; }

        .text-left   { text-align: left; }
        .text-right  { text-align: right; }
        .text-center { text-align: center; }
        .neg  { color: #b91c1c; font-weight: bold; }
        .pos  { color: #b45309; font-weight: bold; }
        .zero { color: #9ca3af; }

        .tag {
            display: inline-block;
            padding: 0 4px;
            font-size: 6.5px;
            font-weight: bold;
            border-radius: 2px;
            margin-right: 3px;
        }
        .tag-good    { background: #dcfce7; color: #166534; }
        .tag-damaged { background: #fee2e2; color: #991b1b; }
        .trace       { font-size: 7.5px; color: #374151; line-height: 1.5; }
        .trace .lbl  { color: #6b7280; }

        /* Notes */
        .notes { margin-top: 8px; font-size: 7.5px; color: #6b7280; }

        /* Signatures */
        .sig-block { width: 100%; margin-top: 34px; border-collapse: separate; border-spacing: 14px 0; page-break-inside: avoid; }
        .sig-col   { vertical-align: top; width: 50%; }
        .sig-line  { border-top: 1px solid #333; margin-top: 34px; padding-top: 4px; font-size: 8px; color: #222; font-weight: bold; text-align: center; }
        .sig-name  { font-size: 7.5px; color: #6b7280; text-align: center; margin-top: 2px; }

        /* Footer */
        .rpt-footer {
            position: fixed;
            bottom: -12mm;
            left: 0;
            right: 0;
            font-size: 7px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 4px;
        }
        .rpt-footer table { width: 100%; }
        .rpt-footer td { border: 0; padding: 0; }
        .pagenum:before   { content: counter(page); }
        .pagecount:before { content: counter(pages); }
    </style>
</head>
<body>

<div class="rpt-footer">
    <table>
        <tr>
            <td>{{ $brand['name'] }} · Goods Received Note · {{ $grn->number }} · {{ $lines->count() }} lines</td>
            <td class="text-right">Page <span class="pagenum"></span> of <span class="pagecount"></span></td>
        </tr>
    </table>
</div>

<div class="rpt-brand">
    @if (! empty($brand['logoDataUri']))
        <img src="{{ $brand['logoDataUri'] }}" class="rpt-logo" alt="">
    @endif
    @if (! empty($brand['address']))
        <div class="rpt-address">{{ $brand['address'] }}</div>
    @endif
</div>

<table class="rpt-header">
    <tr>
        <td width="50%">
            <div class="rpt-title">Goods Received Note</div>
            <div class="rpt-sub">Original — retain for your records</div>
        </td>
        <td width="50%" class="rpt-meta">
            <strong>GRN No.:</strong> {{ $grn->number }}<br>
            @if ($grn->purchaseOrder)
                <strong>PO No.:</strong> {{ $grn->purchaseOrder->number }}<br>
            @endif
            <strong>Date:</strong> {{ $receivedAt->format('d M Y H:i') }}<br>
            <strong>Warehouse:</strong> {{ $grn->warehouse?->name ?? '—' }}
            @if ($grn->receivingDock)
                · <strong>Dock:</strong> {{ $grn->receivingDock->code }}
            @endif
        </td>
    </tr>
</table>

<table class="kpi-row">
    <tr>
        <td class="kpi-box">
            <div class="kpi-label">Lines</div>
            <div class="kpi-value">{{ $lines->count() }}</div>
        </td>
        <td class="kpi-box">
            <div class="kpi-label">Received in full</div>
            <div class="kpi-value">{{ $fullLines }} / {{ $lines->count() }}</div>
        </td>
        <td class="kpi-box">
            <div class="kpi-label">Short lines</div>
            <div class="kpi-value">{{ $shortLines }}</div>
        </td>
        <td class="kpi-box">
            <div class="kpi-label">Over lines</div>
            <div class="kpi-value">{{ $overLines }}</div>
        </td>
        <td class="kpi-box">
            <div class="kpi-label">Damaged lines</div>
            <div class="kpi-value">{{ $damagedLines }}</div>
            @if ($hasDamaged && $singleUom)
                <div class="kpi-hint">{{ $qty($lines->sum('damaged')) }} {{ $lines->first()->uom }}</div>
            @endif
        </td>
    </tr>
</table>

<table class="info-row">
    <tr>
        <td class="info-card">
            <div class="info-head">Supplier and transport</div>
            <table class="info-body">
                <tr><td class="k">Supplier</td><td class="v">{{ $grn->purchaseOrder?->supplierPartner?->name ?? '—' }}</td></tr>
                @if ($grn->transporter_name)
                    <tr><td class="k">Transporter</td><td class="v">{{ $grn->transporter_name }}</td></tr>
                @endif
                @if ($grn->vehicle_plate)
                    <tr><td class="k">Vehicle</td><td class="v">{{ $grn->vehicle_plate }}@if ($grn->vehicle_type) ({{ $grn->vehicle_type }})@endif</td></tr>
                @endif
                @if ($grn->driver_name)
                    <tr><td class="k">Driver</td><td class="v">{{ $grn->driver_name }}</td></tr>
                @endif
            </table>
        </td>
        <td class="info-card">
            <div class="info-head">Receiving details</div>
            <table class="info-body">
                <tr><td class="k">Received by</td><td class="v">{{ $grn->confirmedBy?->name ?? '—' }}</td></tr>
                <tr><td class="k">Delivery type</td><td class="v">{{ ucfirst($grn->delivery_type ?? 'full') }}</td></tr>
                @if ($grn->container_no)
                    <tr><td class="k">Container</td><td class="v">{{ $grn->container_no }}</td></tr>
                @endif
                @if ($grn->seal_check_result)
                    <tr>
                        <td class="k">Seal check</td>
                        <td class="v {{ strtolower($grn->seal_check_result) === 'intact' ? '' : 'neg' }}">{{ ucfirst($grn->seal_check_result) }}</td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
</table>

<div class="section-title">Received items</div>

<table class="data">
    <thead>
    <tr>
        <th class="text-center" style="width: 18px">#</th>
        <th style="width: 70px">Item code</th>
        <th>Description</th>
        <th class="text-center" style="width: 34px">UoM</th>
        <th class="text-right" style="width: 50px">Expected</th>
        <th class="text-right" style="width: 50px">Good</th>
        @if ($hasDamaged)
            <th class="text-right" style="width: 50px">Damaged</th>
        @endif
        <th class="text-right" style="width: 50px">Variance</th>
        <th style="width: 150px">Batch / serials</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($lines as $i => $l)
        <tr class="{{ $rowClass($l, $loop->even) }}">
            <td class="text-center">{{ $i + 1 }}</td>
            <td>{{ $l->sku }}</td>
            <td>{{ $l->name }}</td>
            <td class="text-center">{{ $l->uom }}</td>
            <td class="text-right">{{ $qty($l->expected) }}</td>
            <td class="text-right">{{ $qty($l->good) }}</td>
            @if ($hasDamaged)
                <td class="text-right {{ $l->damaged > 0 ? 'neg' : 'zero' }}">{{ $qty($l->damaged) }}</td>
            @endif
            <td class="text-right {{ $tone($l->variance) }}">{{ $signed($l->variance) }}</td>
            <td class="trace">
                @php
                    $hasGoodTrace    = $l->good_batches->isNotEmpty() || $l->good_serials->isNotEmpty();
                    $hasDamagedTrace = $l->damaged_batches->isNotEmpty() || $l->damaged_serials->isNotEmpty();
                @endphp
                @if ($hasGoodTrace)
                    @if ($hasDamaged)<span class="tag tag-good">GOOD</span>@endif
                    @if ($l->good_batches->isNotEmpty())<span class="lbl">Batch:</span> {{ $l->good_batches->implode(', ') }}<br>@endif
                    @if ($l->good_serials->isNotEmpty())<span class="lbl">S/N:</span> {{ $l->good_serials->implode(', ') }}<br>@endif
                @endif
                @if ($hasDamagedTrace)
                    <span class="tag tag-damaged">DAMAGED</span>
                    @if ($l->damaged_batches->isNotEmpty())<span class="lbl">Batch:</span> {{ $l->damaged_batches->implode(', ') }}<br>@endif
                    @if ($l->damaged_serials->isNotEmpty())<span class="lbl">S/N:</span> {{ $l->damaged_serials->implode(', ') }}@endif
                @endif
                @if (! $hasGoodTrace && ! $hasDamagedTrace)
                    —
                @endif
            </td>
        </tr>
    @empty
        <tr class="empty-row">
            <td colspan="{{ $colCount }}">No lines on this GRN.</td>
        </tr>
    @endforelse

    @if ($lines->isNotEmpty() && $singleUom)
        @php $totalVariance = $lines->sum('variance'); @endphp
        <tr class="totals-row">
            <td colspan="4" class="text-right">Total ({{ $lines->first()->uom }})</td>
            <td class="text-right">{{ $qty($lines->sum('expected')) }}</td>
            <td class="text-right">{{ $qty($lines->sum('good')) }}</td>
            @if ($hasDamaged)
                <td class="text-right neg">{{ $qty($lines->sum('damaged')) }}</td>
            @endif
            <td class="text-right {{ $tone($totalVariance) }}">{{ $signed($totalVariance) }}</td>
            <td></td>
        </tr>
    @endif
    </tbody>
</table>

@if ($shortLines || $overLines || $damagedLines)
    <div class="notes">
        Red rows are short or damaged lines, and amber rows are over-received.
        Variance is good plus damaged, minus expected.
    </div>
@endif

@if (! empty($grn->notes))
    <div class="notes"><strong>Remarks:</strong> {{ $grn->notes }}</div>
@endif

<table class="sig-block">
    <tr>
        <td class="sig-col">
            <div class="sig-line">Received by</div>
            <div class="sig-name">{{ $grn->confirmedBy?->name ?? '' }}</div>
        </td>
        <td class="sig-col">
            <div class="sig-line">Supplier / driver</div>
            <div class="sig-name">{{ $grn->driver_name ?? '' }}</div>
        </td>
    </tr>
</table>

</body>
</html>
