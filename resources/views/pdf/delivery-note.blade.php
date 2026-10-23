@php
    /** @var \App\Models\DeliveryOrder $do */
    /** @var \App\Models\Dispatch|null $dispatch */
    /** @var array|null $brand */

    use App\Enums\Outbound\AllocationStatus;

    $brand = $brand ?? \App\Support\PdfBrand::make();

    $qty = function ($v) {
        $v = (float) $v;
        return number_format($v, floor(abs($v)) == abs($v) ? 0 : 2);
    };

    $signed = function ($v) use ($qty) {
        $v = (float) $v;
        return ($v > 0 ? '+' : '') . $qty($v);
    };

    $lines = $do->lines->sortBy('line_no')->values()->map(function ($line) {
        // Filter to picked allocations using the enum (not string) to avoid PHP 8.1 cast mismatch
        $picked = $line->allocations->filter(
            fn ($a) => $a->status === AllocationStatus::Picked
        );

        // Collect every unique batch across all picked allocations
        $batches = $picked
            ->map(fn ($a) => $a->batch)
            ->filter()
            ->unique('id')
            ->map(fn ($b) => (object) [
                'no'     => $b->batch_no,
                'expiry' => $b->expiry_date?->format('d M Y'),
            ])
            ->values();

        // Collect every serial across all picked allocations' pick tasks
        $serials = $picked
            ->flatMap(fn ($a) => $a->pickTask?->serials ?? collect())
            ->pluck('serial_no')
            ->filter()
            ->unique()
            ->values();

        // requested_qty is the ordered quantity on DeliveryOrderLine
        $ordered    = $line->requested_qty !== null ? (float) $line->requested_qty : null;
        $dispatched = (float) ($line->dispatched_qty ?? 0);

        return (object) [
            'no'         => $line->line_no,
            'sku'        => $line->item?->sku ?? '—',
            'name'       => $line->item?->name ?? '—',
            'uom'        => $line->uom?->code ?? '—',
            'ordered'    => $ordered,
            'dispatched' => $dispatched,
            'variance'   => $ordered !== null ? $dispatched - $ordered : null,
            'batches'    => $batches,
            'serials'    => $serials,
        ];
    });

    $hasOrdered  = $lines->contains(fn ($l) => $l->ordered !== null);
    $singleUom   = $lines->pluck('uom')->unique()->count() === 1;
    $colCount    = $hasOrdered ? 8 : 6;

    $fullLines   = $lines->filter(fn ($l) => $l->variance !== null && $l->variance == 0)->count();
    $shortLines  = $lines->filter(fn ($l) => $l->variance !== null && $l->variance < 0)->count();
    $overLines   = $lines->filter(fn ($l) => $l->variance !== null && $l->variance > 0)->count();
    $serialLines = $lines->filter(fn ($l) => $l->serials->isNotEmpty())->count();

    $dispatchedAt = $dispatch?->dispatched_at ?? now();
    $isLate       = $do->required_date && $dispatchedAt->copy()->startOfDay()->gt($do->required_date->copy()->startOfDay());

    $tone = fn ($v) => $v === null ? '' : ($v < 0 ? 'neg' : ($v > 0 ? 'pos' : 'zero'));

    $rowClass = function ($l, $even) {
        $base = $even ? 'stripe' : '';
        if ($l->variance !== null && $l->variance < 0) return trim($base . ' flag');
        if ($l->variance !== null && $l->variance > 0) return trim($base . ' warn');
        return $base;
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
        .rpt-name    { font-size: 12px; font-weight: bold; color: #111; margin-top: 3px; }
        .rpt-address { font-size: 7.5px; color: #6b7280; margin-top: 2px; }

        /* Header */
        .rpt-header { width: 100%; border-bottom: 2.5px solid #a22631; padding-bottom: 8px; margin-bottom: 10px; }
        .rpt-header td { vertical-align: bottom; border: 0; padding: 0; }
        .rpt-title  { font-size: 14px; font-weight: bold; color: #111; }
        .rpt-sub    { font-size: 7.5px; color: #6b7280; margin-top: 2px; }
        .rpt-meta   { font-size: 8px; color: #555; text-align: right; line-height: 1.7; }
        .rpt-meta strong { color: #222; }
        .late       { color: #b91c1c; font-weight: bold; }

        /* KPI */
        .kpi-row { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 12px -6px; }
        .kpi-box {
            border: 1px solid #e5e7eb;
            border-left: 3px solid #a22631;
            background: #fafafa;
            padding: 6px 8px;
            vertical-align: top;
        }
        .kpi-box.tone-danger  { border-left-color: #b91c1c; background: #fdf2f2; }
        .kpi-box.tone-warning { border-left-color: #d97706; background: #fffbf0; }
        .kpi-box.tone-success { border-left-color: #15803d; background: #f0fdf4; }
        .kpi-label { font-size: 7px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
        .kpi-value { font-size: 13px; font-weight: bold; color: #111; margin-top: 2px; }
        .kpi-hint  { font-size: 7px; color: #6b7280; margin-top: 1px; }

        /* Info cards */
        .info-row  { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 12px -6px; }
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
        .info-body     { width: 100%; border-collapse: collapse; }
        .info-body td  { padding: 3px 8px; font-size: 8px; border: 0; vertical-align: top; }
        .info-body td.k { color: #6b7280; width: 38%; white-space: nowrap; }
        .info-body td.v { color: #111; }
        .not-dispatched { color: #9ca3af; font-style: italic; font-size: 8px; padding: 8px 8px; }

        /* Table */
        .section-title { font-size: 9px; font-weight: bold; color: #a22631; margin: 4px 0 5px; }
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
        .data tr.flag td:first-child { border-left: 2.5px solid #b91c1c; }
        .data tr.warn td   { background: #fff7e6; }
        .data tr.warn td:first-child { border-left: 2.5px solid #d97706; }
        .totals-row td     { background: #f3f4f6; font-weight: bold; border-top: 1.5px solid #a22631; }
        .empty-row td      { text-align: center; color: #9ca3af; padding: 14px; }

        .text-left   { text-align: left; }
        .text-right  { text-align: right; }
        .text-center { text-align: center; }
        .neg  { color: #b91c1c; font-weight: bold; }
        .pos  { color: #b45309; font-weight: bold; }
        .zero { color: #9ca3af; }

        /* Batch / serial trace */
        .trace      { font-size: 7.5px; color: #374151; line-height: 1.6; }
        .trace .lbl { color: #6b7280; }
        .trace .exp { color: #6b7280; font-style: italic; }

        /* Notes / legend */
        .notes { margin-top: 6px; font-size: 7.5px; color: #6b7280; line-height: 1.5; }
        .notes strong { color: #374151; }

        /* Signatures */
        .sig-block { width: 100%; margin-top: 36px; border-collapse: separate; border-spacing: 14px 0; page-break-inside: avoid; }
        .sig-col   { vertical-align: top; width: 33%; }
        .sig-role  { font-size: 8px; font-weight: bold; color: #222; text-align: center; padding-bottom: 36px; }
        .sig-line  { border-top: 1px solid #333; padding-top: 4px; font-size: 7.5px; color: #6b7280; text-align: center; }
        .sig-name  { font-size: 8px; font-weight: bold; color: #222; text-align: center; margin-top: 2px; min-height: 10px; }
        .sig-field { font-size: 7.5px; color: #6b7280; margin-top: 5px; }
        .sig-field span { display: inline-block; border-bottom: 1px dotted #9ca3af; width: 70%; }

        /* Footer */
        .rpt-footer {
            position: fixed;
            bottom: -12mm;
            left: 0; right: 0;
            font-size: 7px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 4px;
        }
        .rpt-footer table { width: 100%; }
        .rpt-footer td    { border: 0; padding: 0; }
        .pagenum:before   { content: counter(page); }
        .pagecount:before { content: counter(pages); }
    </style>
</head>
<body>

{{-- Fixed footer repeats on every page --}}
<div class="rpt-footer">
    <table>
        <tr>
            <td>{{ $brand['name'] }} · Outbound Delivery Note · {{ $do->number }}{{ $dispatch ? ' · ' . $dispatch->number : '' }}</td>
            <td class="text-right">Page <span class="pagenum"></span> of <span class="pagecount"></span></td>
        </tr>
    </table>
</div>

{{-- Brand block --}}
<div class="rpt-brand">
    @if (! empty($brand['logoDataUri']))
        <img src="{{ $brand['logoDataUri'] }}" class="rpt-logo" alt="{{ $brand['name'] }}">
    @else
        <div class="rpt-name">{{ $brand['name'] }}</div>
    @endif
    @if (! empty($brand['address']))
        <div class="rpt-address">{{ $brand['address'] }}</div>
    @endif
</div>

{{-- Document header --}}
<table class="rpt-header">
    <tr>
        <td width="55%">
            <div class="rpt-title">Outbound Delivery Note</div>
            <div class="rpt-sub">Consignee copy — sign and return on receipt</div>
        </td>
        <td width="45%" class="rpt-meta">
            <strong>DO No.:</strong> {{ $do->number }}<br>
            @if ($dispatch)
                <strong>Dispatch No.:</strong> {{ $dispatch->number }}<br>
            @endif
            <strong>Dispatch date:</strong>
            @if ($dispatch?->dispatched_at)
                {{ $dispatch->dispatched_at->format('d M Y H:i') }}
            @else
                {{ now()->format('d M Y H:i') }} <em style="color:#9ca3af">(not yet dispatched)</em>
            @endif
            <br>
            @if ($do->required_date)
                <strong>Required:</strong>
                <span @if ($isLate) class="late" @endif>
                    {{ $do->required_date->format('d M Y') }}@if ($isLate) (late)@endif
                </span><br>
            @endif
            @if ($do->warehouse?->name)
                <strong>Warehouse:</strong> {{ $do->warehouse->name }}
            @endif
        </td>
    </tr>
</table>

{{-- KPI strip --}}
<table class="kpi-row">
    <tr>
        <td class="kpi-box">
            <div class="kpi-label">Lines</div>
            <div class="kpi-value">{{ $lines->count() }}</div>
        </td>
        @if ($hasOrdered)
            <td class="kpi-box {{ $fullLines === $lines->count() ? 'tone-success' : ($shortLines > 0 ? 'tone-warning' : '') }}">
                <div class="kpi-label">Dispatched in full</div>
                <div class="kpi-value">{{ $fullLines }} / {{ $lines->count() }}</div>
            </td>
            <td class="kpi-box {{ $shortLines > 0 ? 'tone-danger' : 'tone-success' }}">
                <div class="kpi-label">Short lines</div>
                <div class="kpi-value">{{ $shortLines }}</div>
            </td>
        @endif
        <td class="kpi-box">
            <div class="kpi-label">Total dispatched</div>
            @if ($singleUom)
                <div class="kpi-value">{{ $qty($lines->sum('dispatched')) }} {{ $lines->first()?->uom }}</div>
            @else
                <div class="kpi-value">{{ $lines->count() }} lines</div>
                <div class="kpi-hint">Mixed units of measure</div>
            @endif
        </td>
        <td class="kpi-box {{ $serialLines > 0 ? '' : '' }}">
            <div class="kpi-label">Serialised lines</div>
            <div class="kpi-value">{{ $serialLines }}</div>
        </td>
    </tr>
</table>

{{-- Info cards --}}
<table class="info-row">
    <tr>
        {{-- Consignee --}}
        <td class="info-card">
            <div class="info-head">Consignee</div>
            <table class="info-body">
                <tr>
                    <td class="k">Name</td>
                    <td class="v">{{ $do->consigneePartner?->name ?? '—' }}</td>
                </tr>
                @if (! empty($do->delivery_address))
                    <tr>
                        <td class="k">Delivery address</td>
                        <td class="v">{!! nl2br(e($do->delivery_address)) !!}</td>
                    </tr>
                @endif
                @if ($do->consigneePartner?->phone)
                    <tr>
                        <td class="k">Phone</td>
                        <td class="v">{{ $do->consigneePartner->phone }}</td>
                    </tr>
                @endif
                @if (! empty($do->customer_reference))
                    <tr>
                        <td class="k">Customer ref.</td>
                        <td class="v">{{ $do->customer_reference }}</td>
                    </tr>
                @endif
            </table>
        </td>

        {{-- Transport --}}
        <td class="info-card">
            <div class="info-head">Transport details</div>
            @if ($dispatch)
                <table class="info-body">
                    <tr>
                        <td class="k">Vehicle reg.</td>
                        <td class="v">{{ $dispatch->vehicle_reg_no ?? '—' }}</td>
                    </tr>
                    @if ($dispatch->container_no)
                        <tr>
                            <td class="k">Container</td>
                            <td class="v">
                                {{ $dispatch->container_no }}
                                @if ($dispatch->container_size)
                                    ({{ $dispatch->container_size->label() }})
                                @endif
                            </td>
                        </tr>
                    @endif
                    @if ($dispatch->seal_no)
                        <tr>
                            <td class="k">Seal no.</td>
                            <td class="v">{{ $dispatch->seal_no }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="k">Driver</td>
                        <td class="v">{{ $dispatch->driver_name ?? '—' }}</td>
                    </tr>
                    @if ($dispatch->driver_phone)
                        <tr>
                            <td class="k">Driver phone</td>
                            <td class="v">{{ $dispatch->driver_phone }}</td>
                        </tr>
                    @endif
                </table>
            @else
                <div class="not-dispatched">Not yet dispatched</div>
            @endif
        </td>
    </tr>
</table>

{{-- Lines table --}}
<div class="section-title">Dispatched items</div>

<table class="data">
    <thead>
        <tr>
            <th class="text-center" style="width:22px">#</th>
            <th style="width:70px">Item code</th>
            <th>Description</th>
            <th class="text-center" style="width:34px">UoM</th>
            @if ($hasOrdered)
                <th class="text-right" style="width:50px">Ordered</th>
            @endif
            <th class="text-right" style="width:54px">Dispatched</th>
            @if ($hasOrdered)
                <th class="text-right" style="width:48px">Variance</th>
            @endif
            <th style="width:160px">Batch / expiry / serials</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($lines as $l)
            <tr class="{{ $rowClass($l, $loop->even) }}">
                <td class="text-center">{{ $l->no }}</td>
                <td>{{ $l->sku }}</td>
                <td>{{ $l->name }}</td>
                <td class="text-center">{{ $l->uom }}</td>
                @if ($hasOrdered)
                    <td class="text-right">{{ $l->ordered !== null ? $qty($l->ordered) : '—' }}</td>
                @endif
                <td class="text-right"><strong>{{ $qty($l->dispatched) }}</strong></td>
                @if ($hasOrdered)
                    <td class="text-right {{ $tone($l->variance) }}">
                        {{ $l->variance !== null ? $signed($l->variance) : '—' }}
                    </td>
                @endif
                <td class="trace">
                    @foreach ($l->batches as $b)
                        <span class="lbl">Batch:</span> {{ $b->no }}
                        @if ($b->expiry)
                            <span class="exp">· exp. {{ $b->expiry }}</span>
                        @endif
                        <br>
                    @endforeach
                    @if ($l->serials->isNotEmpty())
                        <span class="lbl">S/N:</span> {{ $l->serials->implode(', ') }}
                    @endif
                    @if ($l->batches->isEmpty() && $l->serials->isEmpty())
                        —
                    @endif
                </td>
            </tr>
        @empty
            <tr class="empty-row">
                <td colspan="{{ $colCount }}">No lines on this delivery order.</td>
            </tr>
        @endforelse

        @if ($lines->isNotEmpty() && $singleUom)
            @php
                $totalVariance = $hasOrdered ? $lines->whereNotNull('variance')->sum('variance') : null;
            @endphp
            <tr class="totals-row">
                <td colspan="{{ $hasOrdered ? 4 : 3 }}" class="text-right">
                    Total ({{ $lines->first()->uom }})
                </td>
                @if ($hasOrdered)
                    <td class="text-right">{{ $qty($lines->sum('ordered')) }}</td>
                @endif
                <td class="text-right">{{ $qty($lines->sum('dispatched')) }}</td>
                @if ($hasOrdered)
                    <td class="text-right {{ $tone($totalVariance) }}">
                        {{ $totalVariance !== null ? $signed($totalVariance) : '—' }}
                    </td>
                @endif
                <td></td>
            </tr>
        @endif
    </tbody>
</table>

{{-- Legend / notes --}}
@if ($shortLines > 0 || $overLines > 0)
    <div class="notes">
        @if ($shortLines > 0)
            <span style="color:#b91c1c">&#9632;</span> Red rows were dispatched <strong>short</strong> against the order.
        @endif
        @if ($overLines > 0)
            &nbsp;&nbsp;<span style="color:#d97706">&#9632;</span> Orange rows were dispatched <strong>over</strong> the order quantity.
        @endif
        Variance = dispatched &minus; ordered.
    </div>
@endif

@if (! empty($do->notes))
    <div class="notes"><strong>Remarks:</strong> {{ $do->notes }}</div>
@endif

<div class="notes">Goods received in good order and condition unless noted above.</div>

{{-- Signatures --}}
<table class="sig-block">
    <tr>
        <td class="sig-col">
            <div class="sig-role">Dispatched by</div>
            <div class="sig-line">Signature</div>
            <div class="sig-name">{{ $dispatch?->dispatchedBy?->name ?? '' }}</div>
        </td>
        <td class="sig-col">
            <div class="sig-role">Driver / transporter</div>
            <div class="sig-line">Signature</div>
            <div class="sig-name">{{ $dispatch?->driver_name ?? '' }}</div>
        </td>
        <td class="sig-col">
            <div class="sig-role">Received by (consignee)</div>
            <div class="sig-line">Signature</div>
            <div class="sig-field">Name: <span>&nbsp;</span></div>
            <div class="sig-field">Date: <span>&nbsp;</span></div>
            <div class="sig-field">Stamp:</div>
        </td>
    </tr>
</table>

</body>
</html>
