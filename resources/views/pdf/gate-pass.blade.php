@php
    /** @var \App\Models\GatePass $gatePass */
    /** @var \App\Models\Dispatch $dispatch */
    /** @var array $brand */

    use App\Enums\Outbound\GatePassStatus;

    $do       = $dispatch->deliveryOrder;
    $isExited = $gatePass->status === GatePassStatus::Exited;

    $qty = function ($v) {
        $v = (float) $v;
        return number_format($v, floor(abs($v)) == abs($v) ? 0 : 2);
    };

    $items = $dispatch->items->sortBy(fn ($i) => $i->item?->sku)->values();
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
        .rpt-logo    { max-height: 48px; max-width: 160px; display: inline-block; }
        .rpt-name    { font-size: 12px; font-weight: bold; color: #111; margin-top: 3px; }
        .rpt-address { font-size: 7.5px; color: #6b7280; margin-top: 2px; }

        /* Header bar */
        .rpt-header { width: 100%; border-bottom: 2.5px solid #a22631; padding-bottom: 8px; margin-bottom: 10px; }
        .rpt-header td { vertical-align: bottom; border: 0; padding: 0; }
        .rpt-title  { font-size: 14px; font-weight: bold; color: #111; }
        .rpt-sub    { font-size: 7.5px; color: #6b7280; margin-top: 2px; }
        .rpt-meta   { font-size: 8px; color: #555; text-align: right; line-height: 1.7; }
        .rpt-meta strong { color: #222; }

        /* Gate pass number hero box */
        .gp-hero {
            border: 2px solid {{ $isExited ? '#15803d' : '#d97706' }};
            border-radius: 4px;
            padding: 10px 14px;
            margin-bottom: 12px;
            background: {{ $isExited ? '#f0fdf4' : '#fffbf0' }};
        }
        .gp-hero-row { width: 100%; }
        .gp-hero-row td { border: 0; padding: 0; vertical-align: middle; }
        .gp-number  { font-family: DejaVu Sans Mono, Courier New, monospace; font-size: 22px; font-weight: bold; color: #111; letter-spacing: 1px; }
        .gp-badge   {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            background: {{ $isExited ? '#dcfce7' : '#fef3c7' }};
            color: {{ $isExited ? '#15803d' : '#92400e' }};
            border: 1px solid {{ $isExited ? '#86efac' : '#fde68a' }};
        }

        /* KPI strip */
        .kpi-row { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 12px -6px; }
        .kpi-box {
            border: 1px solid #e5e7eb;
            border-left: 3px solid #a22631;
            background: #fafafa;
            padding: 5px 8px;
            vertical-align: top;
        }
        .kpi-label { font-size: 7px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
        .kpi-value { font-size: 11px; font-weight: bold; color: #111; margin-top: 2px; }
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
        .info-body td.v { color: #111; font-weight: 500; }

        /* Verification box */
        .verify-box {
            border: 1.5px solid #15803d;
            background: #f0fdf4;
            padding: 6px 10px;
            margin-bottom: 12px;
            font-size: 8px;
        }
        .verify-title { font-weight: bold; color: #15803d; font-size: 8.5px; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.04em; }
        .verify-grid  { width: 100%; }
        .verify-grid td { border: 0; padding: 1px 16px 1px 0; vertical-align: top; }
        .verify-grid .k { color: #6b7280; font-size: 7.5px; }
        .verify-grid .v { color: #111; font-weight: 600; }

        /* Items table */
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
        .totals-row td     { background: #f3f4f6; font-weight: bold; border-top: 1.5px solid #a22631; }
        .empty-row td      { text-align: center; color: #9ca3af; padding: 14px; }
        .text-right  { text-align: right; }
        .text-center { text-align: center; }
        .mono { font-family: DejaVu Sans Mono, Courier New, monospace; font-size: 8px; }

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

<div class="rpt-footer">
    <table>
        <tr>
            <td>{{ $brand['name'] }} · Gate Pass · {{ $gatePass->number }} · {{ $do->number }}</td>
            <td class="text-right">Page <span class="pagenum"></span> of <span class="pagecount"></span></td>
        </tr>
    </table>
</div>

{{-- Brand --}}
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
            <div class="rpt-title">Gate Pass</div>
            <div class="rpt-sub">Authorisation to exit the warehouse premises</div>
        </td>
        <td width="45%" class="rpt-meta">
            <strong>DO No.:</strong> {{ $do->number }}<br>
            <strong>Dispatch No.:</strong> {{ $dispatch->number }}<br>
            <strong>Dispatched:</strong> {{ $dispatch->dispatched_at?->format('d M Y H:i') ?? '—' }}<br>
            @if ($do->virtualWarehouse?->name)
                <strong>Warehouse:</strong> {{ $do->virtualWarehouse->name }}
            @endif
        </td>
    </tr>
</table>

{{-- Gate pass number hero --}}
<div class="gp-hero">
    <table class="gp-hero-row">
        <tr>
            <td>
                <div style="font-size:7px; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:3px;">Gate Pass No.</div>
                <div class="gp-number">{{ $gatePass->number }}</div>
            </td>
            <td style="text-align:right; white-space:nowrap;">
                <div class="gp-badge">{{ $gatePass->status->label() }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- KPI strip --}}
<table class="kpi-row">
    <tr>
        <td class="kpi-box">
            <div class="kpi-label">Consignee</div>
            <div class="kpi-value" style="font-size:9px;">{{ $do->consigneePartner?->name ?? '—' }}</div>
        </td>
        <td class="kpi-box">
            <div class="kpi-label">Items dispatched</div>
            <div class="kpi-value">{{ $items->count() }}</div>
        </td>
        <td class="kpi-box">
            <div class="kpi-label">Total qty</div>
            <div class="kpi-value">{{ $qty($items->sum(fn ($i) => (float) $i->qty)) }}</div>
        </td>
        <td class="kpi-box">
            <div class="kpi-label">Issued</div>
            <div class="kpi-value" style="font-size:9px;">{{ $gatePass->created_at->format('d M Y H:i') }}</div>
        </td>
    </tr>
</table>

{{-- Verification banner (only when exited) --}}
@if ($isExited)
    <div class="verify-box">
        <div class="verify-title">&#10003; Gate Exit Verified</div>
        <table class="verify-grid">
            <tr>
                <td class="k">Exited at</td>
                <td class="v">{{ $gatePass->exited_at?->format('d M Y H:i') }}</td>
                <td class="k">Verified by</td>
                <td class="v">{{ $gatePass->verifiedBy?->name ?? '—' }}</td>
            </tr>
        </table>
    </div>
@endif

{{-- Info cards --}}
<table class="info-row">
    <tr>
        {{-- Shipment --}}
        <td class="info-card">
            <div class="info-head">Shipment details</div>
            <table class="info-body">
                <tr>
                    <td class="k">Consignee</td>
                    <td class="v">{{ $do->consigneePartner?->name ?? '—' }}</td>
                </tr>
                @if ($do->consigneePartner?->phone)
                    <tr>
                        <td class="k">Phone</td>
                        <td class="v">{{ $do->consigneePartner->phone }}</td>
                    </tr>
                @endif
                @if (! empty($do->delivery_address))
                    <tr>
                        <td class="k">Delivery address</td>
                        <td class="v">{!! nl2br(e($do->delivery_address)) !!}</td>
                    </tr>
                @endif
                <tr>
                    <td class="k">Dispatched by</td>
                    <td class="v">{{ $dispatch->dispatchedBy?->name ?? '—' }}</td>
                </tr>
                @if ($dispatch->notes)
                    <tr>
                        <td class="k">Notes</td>
                        <td class="v">{{ $dispatch->notes }}</td>
                    </tr>
                @endif
            </table>
        </td>

        {{-- Vehicle / Driver --}}
        <td class="info-card">
            <div class="info-head">Vehicle &amp; driver</div>
            <table class="info-body">
                <tr>
                    <td class="k">Vehicle reg.</td>
                    <td class="v mono">{{ $dispatch->vehicle_reg_no ?? '—' }}</td>
                </tr>
                @if ($dispatch->container_no)
                    <tr>
                        <td class="k">Container</td>
                        <td class="v mono">
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
                        <td class="v mono">{{ $dispatch->seal_no }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="k">Driver</td>
                    <td class="v">{{ $dispatch->driver_name ?? '—' }}</td>
                </tr>
                @if ($dispatch->driver_phone)
                    <tr>
                        <td class="k">Driver phone</td>
                        <td class="v mono">{{ $dispatch->driver_phone }}</td>
                    </tr>
                @endif
                @if ($dispatch->driver_id_no)
                    <tr>
                        <td class="k">Driver ID</td>
                        <td class="v mono">{{ $dispatch->driver_id_no }}</td>
                    </tr>
                @endif
                @if ($dispatch->transporterPartner)
                    <tr>
                        <td class="k">Transporter</td>
                        <td class="v">{{ $dispatch->transporterPartner->name }}</td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
</table>

{{-- Items --}}
<div class="section-title">Dispatched items</div>

<table class="data">
    <thead>
        <tr>
            <th class="text-center" style="width:22px">#</th>
            <th style="width:72px">Item code</th>
            <th>Description</th>
            <th style="width:90px">Batch</th>
            <th class="text-right" style="width:54px">Qty</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($items as $item)
            <tr class="{{ $loop->even ? 'stripe' : '' }}">
                <td class="text-center">{{ $loop->iteration }}</td>
                <td class="mono">{{ $item->item?->sku ?? '—' }}</td>
                <td>{{ $item->item?->name ?? '—' }}</td>
                <td class="mono">{{ $item->batch?->batch_no ?? '—' }}</td>
                <td class="text-right"><strong>{{ $qty($item->qty) }}</strong></td>
            </tr>
        @empty
            <tr class="empty-row">
                <td colspan="5">No items recorded for this dispatch.</td>
            </tr>
        @endforelse

        @if ($items->isNotEmpty())
            <tr class="totals-row">
                <td colspan="4" class="text-right">Total</td>
                <td class="text-right">{{ $qty($items->sum(fn ($i) => (float) $i->qty)) }}</td>
            </tr>
        @endif
    </tbody>
</table>

{{-- Signatures --}}
<table class="sig-block">
    <tr>
        <td class="sig-col">
            <div class="sig-role">Dispatched by</div>
            <div class="sig-line">Signature</div>
            <div class="sig-name">{{ $dispatch->dispatchedBy?->name ?? '' }}</div>
        </td>
        <td class="sig-col">
            <div class="sig-role">Security / Gate officer</div>
            <div class="sig-line">Signature</div>
            <div class="sig-field">Name: <span>&nbsp;</span></div>
            <div class="sig-field">Badge no.: <span>&nbsp;</span></div>
            @if ($isExited)
                <div class="sig-field">Time: <span>{{ $gatePass->exited_at?->format('H:i') }}</span></div>
            @else
                <div class="sig-field">Time: <span>&nbsp;</span></div>
            @endif
        </td>
        <td class="sig-col">
            <div class="sig-role">Driver / Transporter</div>
            <div class="sig-line">Signature</div>
            <div class="sig-name">{{ $dispatch->driver_name ?? '' }}</div>
            <div class="sig-field">ID no.: <span>{{ $dispatch->driver_id_no ? $dispatch->driver_id_no : '&nbsp;' }}</span></div>
        </td>
    </tr>
</table>

</body>
</html>
