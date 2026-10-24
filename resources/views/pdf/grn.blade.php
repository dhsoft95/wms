@php
    /** @var \App\Models\Grn $grn */
    /** @var array $brand  ['name','address','logoDataUri'] */

    $brand = array_merge(
        ['name' => '', 'address' => null, 'logoDataUri' => null],
        $brand ?? []
    );

    $qty    = fn ($v): string => number_format((float) $v, floor(abs((float) $v)) == abs((float) $v) ? 0 : 2);
    $signed = fn ($v): string => ((float) $v > 0 ? '+' : '') . $qty($v);
    $tone   = fn ($v): string => (float) $v < 0 ? 'neg' : ((float) $v > 0 ? 'pos' : 'zero');

    // ── Pre-process lines ──────────────────────────────────────────────────
    $lines = $grn->lines->values()->map(function ($line) {
        $good     = (float) ($line->received_good_qty ?? 0);
        $damaged  = (float) ($line->received_damaged_qty ?? 0);
        $expected = (float) ($line->expected_qty ?? 0);
        $receipts = $line->receipts ?? collect();

        // condition is cast to ReceiptCondition enum — compare via .value
        $trace = function (string $conditionValue) use ($receipts) {
            $rows = $receipts->where('condition.value', $conditionValue);
            return (object) [
                'batches'  => $rows->pluck('batch_no')->filter()->unique()->values(),
                'expiries' => $rows->pluck('expiry_date')->filter()
                                   ->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d M Y'))
                                   ->unique()->values(),
                'serials'  => $rows->flatMap(fn ($r) => $r->serial_nos ?? [])->filter()->unique()->values(),
            ];
        };

        return (object) [
            'sku'          => $line->item?->sku ?? '—',
            'name'         => $line->item?->name ?? '—',
            'uom'          => $line->uom?->code ?? '—',
            'expected'     => $expected,
            'good'         => $good,
            'damaged'      => $damaged,
            'variance'     => ($good + $damaged) - $expected,
            'goodTrace'    => $trace('good'),
            'damagedTrace' => $trace('damaged'),
        ];
    });

    // ── Aggregates ─────────────────────────────────────────────────────────
    $lineCount    = $lines->count();
    $hasDamaged   = $lines->sum('damaged') > 0;
    $singleUom    = $lines->pluck('uom')->unique()->count() === 1;
    $colCount     = $hasDamaged ? 9 : 8;
    $fullLines    = $lines->filter(fn ($l) => $l->variance == 0 && $l->damaged == 0)->count();
    $damagedLines = $lines->filter(fn ($l) => $l->damaged > 0)->count();
    $hasVariance  = $lines->contains(fn ($l) => $l->variance != 0 || $l->damaged > 0);

    // ── Related objects ────────────────────────────────────────────────────
    $receivedAt = $grn->confirmed_at ?? $grn->created_at;
    $po         = $grn->purchaseOrder;
    $supplier   = $po?->supplierPartner;
    $consignee  = $po?->consigneePartner;
    $sealIntact = strtolower((string) ($grn->seal_check_result ?? '')) === 'intact';

    $supplierAddr = collect([
        $supplier?->address,
        $supplier?->city,
        $supplier?->country,
    ])->filter()->implode(', ');

    // ── Row colour helper ──────────────────────────────────────────────────
    $rowClass = function ($l, bool $even): string {
        $cls = $even ? ['stripe'] : [];
        if ($l->damaged > 0 || $l->variance < 0) { $cls[] = 'flag'; }
        elseif ($l->variance > 0)                 { $cls[] = 'warn'; }
        return implode(' ', $cls);
    };

    $hasTrace = fn ($t): bool => $t->batches->isNotEmpty()
                              || $t->expiries->isNotEmpty()
                              || $t->serials->isNotEmpty();
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $grn->number }}</title>
    <style>
        @page { margin: 14mm 12mm 18mm 12mm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8.5px;
            color: #1a1a1a;
            margin: 0;
        }
        table { border-collapse: collapse; }

        /* ── Header ──────────────────────────────────────────────────── */
        .hdr { width: 100%; margin-bottom: 8px; }
        .hdr td { vertical-align: top; padding: 0; }
        .hdr-logo { max-height: 46px; max-width: 170px; margin-bottom: 4px; display: block; }
        .hdr-company { font-size: 10px; font-weight: bold; color: #111; }
        .hdr-address { font-size: 7.5px; color: #6b7280; margin-top: 2px; line-height: 1.5; }
        .doc-title { font-size: 15px; font-weight: bold; color: #a22631; text-align: right; }
        .doc-sub { font-size: 7.5px; color: #6b7280; text-align: right; margin: 2px 0 6px; }
        .meta { margin-left: auto; border: 1px solid #e5e7eb; }
        .meta td { padding: 3px 8px; font-size: 8px; border-bottom: 1px solid #f0f0f0; }
        .meta td.k { color: #6b7280; background: #f9fafb; white-space: nowrap; }
        .meta td.v { font-weight: bold; color: #111; text-align: right; }
        .brand-rule { height: 2.5px; background: #a22631; margin: 0 0 10px; }

        /* ── From / To ───────────────────────────────────────────────── */
        .parties { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 5px 0; margin-bottom: 10px; }
        .party { border: 1px solid #e5e7eb; vertical-align: top; padding: 0; }
        .party-head {
            background: #a22631;
            color: #fff;
            font-weight: bold;
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 4px 8px;
        }
        .party-body { padding: 6px 8px; }
        .party-name { font-size: 9.5px; font-weight: bold; color: #111; margin-bottom: 2px; }
        .party-line { font-size: 7.8px; color: #374151; line-height: 1.55; }
        .party-line .lbl { color: #6b7280; }

        /* ── KPIs ────────────────────────────────────────────────────── */
        .kpi { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 5px 0; margin-bottom: 12px; }
        .kpi td { border: 1px solid #e5e7eb; padding: 7px 10px; vertical-align: top; }
        .kpi-label { font-size: 6.8px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
        .kpi-value { font-size: 14px; font-weight: bold; color: #111; margin-top: 3px; }
        .kpi-value.neg { color: #b91c1c; }
        .kpi-hint { font-size: 7px; color: #6b7280; margin-top: 2px; }

        /* ── Items table ─────────────────────────────────────────────── */
        .section-title { font-size: 9px; font-weight: bold; color: #a22631; margin: 2px 0 5px; }
        .data { width: 100%; }
        .data thead { display: table-header-group; }
        .data tr { page-break-inside: avoid; }
        .data th {
            background: #a22631;
            color: #fff;
            font-weight: bold;
            font-size: 7.5px;
            padding: 4px 5px;
            border: 1px solid #8a1d28;
            text-align: left;
        }
        .data td { border: 1px solid #e5e7eb; padding: 3px 5px; font-size: 8px; vertical-align: top; }
        .data tr.stripe td { background: #fafafa; }
        .data tr.flag td { background: #fdecec; }
        .data tr.flag td:first-child { border-left: 2px solid #b91c1c; }
        .data tr.warn td { background: #fff7e6; }
        .data tr.warn td:first-child { border-left: 2px solid #d97706; }
        .data tr.totals td { background: #f3f4f6; font-weight: bold; border-top: 1.5px solid #a22631; }
        .data tr.empty td { text-align: center; color: #9ca3af; padding: 14px; }
        .code { font-family: DejaVu Sans Mono, monospace; font-size: 7.6px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .neg { color: #b91c1c; font-weight: bold; }
        .pos { color: #b45309; font-weight: bold; }
        .zero { color: #9ca3af; }

        /* ── Trace cell ──────────────────────────────────────────────── */
        .tag { display: inline-block; padding: 0 4px; font-size: 6.5px; font-weight: bold; border-radius: 2px; margin-bottom: 2px; }
        .tag-good    { background: #dcfce7; color: #166534; }
        .tag-damaged { background: #fee2e2; color: #991b1b; }
        .trace { font-size: 7.4px; color: #374151; line-height: 1.6; }
        .trace .lbl { color: #6b7280; }
        .trace-group { margin-bottom: 2px; }
        .trace-group + .trace-group { margin-top: 4px; padding-top: 3px; border-top: 1px dashed #e5e7eb; }

        .notes { margin-top: 6px; font-size: 7.5px; color: #6b7280; line-height: 1.5; }

        /* ── Transport / Receiving cards ─────────────────────────────── */
        .cards { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 5px 0; margin-top: 12px; page-break-inside: avoid; }
        .card  { border: 1px solid #e5e7eb; vertical-align: top; padding: 0; }
        .card-head {
            background: #f3f4f6;
            color: #a22631;
            font-weight: bold;
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 4px 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        .card-body { width: 100%; }
        .card-body td { padding: 3px 8px; font-size: 8px; }
        .card-body td.k { color: #6b7280; width: 38%; white-space: nowrap; }
        .card-body td.v { color: #111; }

        /* ── Remarks ─────────────────────────────────────────────────── */
        .remarks { margin-top: 10px; border: 1px solid #e5e7eb; padding: 6px 8px; font-size: 8px; page-break-inside: avoid; }
        .remarks strong { color: #a22631; }

        /* ── Signatures ──────────────────────────────────────────────── */
        .sig { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 14px 0; margin-top: 30px; page-break-inside: avoid; }
        .sig td { vertical-align: top; }
        .sig-box { margin-top: 30px; border-top: 1px solid #333; padding-top: 5px; text-align: center; }
        .sig-role { font-size: 8px; font-weight: bold; }
        .sig-name { font-size: 7.5px; color: #6b7280; margin-top: 2px; }
        .sig-date { font-size: 7px; color: #9ca3af; margin-top: 6px; }

        /* ── Footer (fixed, appears on every page) ───────────────────── */
        .footer {
            position: fixed;
            bottom: -13mm;
            left: 0;
            right: 0;
            font-size: 7px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 3px;
        }
        .footer table { width: 100%; }
        .footer td { padding: 0; }
        .pagenum:before   { content: counter(page); }
        .pagecount:before { content: counter(pages); }
    </style>
</head>
<body>

{{-- ── Footer (renders on every page) ──────────────────────────────────── --}}
<div class="footer">
    <table>
        <tr>
            <td>{{ $brand['name'] }} · Goods Received Note {{ $grn->number }} · Generated {{ now()->format('d M Y H:i') }} · Confidential</td>
            <td class="text-right">Page <span class="pagenum"></span> of <span class="pagecount"></span></td>
        </tr>
    </table>
</div>

{{-- ── 1. Header ──────────────────────────────────────────────────────── --}}
<table class="hdr">
    <tr>
        <td width="55%">
            @if (! empty($brand['logoDataUri']))
                <img src="{{ $brand['logoDataUri'] }}" class="hdr-logo" alt="">
            @endif
            <div class="hdr-company">{{ $brand['name'] }}</div>
            @if (! empty($brand['address']))
                <div class="hdr-address">{!! nl2br(e($brand['address'])) !!}</div>
            @endif
        </td>
        <td width="45%">
            <div class="doc-title">Goods Received Note</div>
            <div class="doc-sub">Original — retain for your records</div>
            <table class="meta">
                <tr><td class="k">GRN No.</td>   <td class="v code">{{ $grn->number }}</td></tr>
                @if ($po)
                    <tr><td class="k">PO No.</td><td class="v code">{{ $po->number }}</td></tr>
                @endif
                <tr><td class="k">Received</td>   <td class="v">{{ $receivedAt->format('d M Y H:i') }}</td></tr>
            </table>
        </td>
    </tr>
</table>
<div class="brand-rule"></div>

{{-- ── 2. From / To ───────────────────────────────────────────────────── --}}
<table class="parties">
    <tr>
        {{-- FROM: supplier --}}
        <td class="party">
            <div class="party-head">From (Supplier)</div>
            <div class="party-body">
                <div class="party-name">{{ $supplier?->name ?? '—' }}</div>
                @if ($supplierAddr)
                    <div class="party-line">{{ $supplierAddr }}</div>
                @endif
                @if ($supplier?->phone)
                    <div class="party-line"><span class="lbl">Tel:</span> {{ $supplier->phone }}</div>
                @endif
                @if ($supplier?->email)
                    <div class="party-line"><span class="lbl">Email:</span> {{ $supplier->email }}</div>
                @endif
                @if ($po?->customer_po_ref)
                    <div class="party-line"><span class="lbl">Supplier / Customer PO ref:</span> {{ $po->customer_po_ref }}</div>
                @endif
            </div>
        </td>
        {{-- TO: destination --}}
        <td class="party">
            <div class="party-head">To (Destination)</div>
            <div class="party-body">
                <div class="party-name">{{ $consignee?->name ?? $brand['name'] }}</div>
                <div class="party-line"><span class="lbl">Warehouse:</span> {{ $grn->warehouse?->name ?? '—' }}</div>
                @if ($grn->warehouse?->address)
                    <div class="party-line">{{ $grn->warehouse->address }}</div>
                @endif
                @if ($grn->receivingDock)
                    <div class="party-line">
                        <span class="lbl">Receiving dock:</span>
                        <span class="code">{{ $grn->receivingDock->code }}</span>
                    </div>
                @endif
                <div class="party-line">
                    <span class="lbl">Delivery type:</span> {{ ucfirst($grn->delivery_type ?? 'full') }}
                </div>
            </div>
        </td>
    </tr>
</table>

{{-- ── 3. KPIs ─────────────────────────────────────────────────────────── --}}
<table class="kpi">
    <tr>
        {{-- Lines --}}
        <td>
            <div class="kpi-label">Lines</div>
            <div class="kpi-value">{{ $lineCount }}</div>
        </td>

        {{-- Received in full --}}
        <td>
            <div class="kpi-label">Received in full</div>
            <div class="kpi-value">{{ $fullLines }} / {{ $lineCount }}</div>
        </td>

        {{-- Total received — only when all lines share one UoM --}}
        @if ($singleUom && $lineCount)
            <td>
                <div class="kpi-label">Total received</div>
                <div class="kpi-value">{{ $qty($lines->sum('good') + $lines->sum('damaged')) }}</div>
                <div class="kpi-hint">{{ $lines->first()->uom }}</div>
            </td>
        @endif

        {{-- Damaged lines --}}
        <td>
            <div class="kpi-label">Damaged lines</div>
            <div class="kpi-value {{ $damagedLines > 0 ? 'neg' : '' }}">{{ $damagedLines }}</div>
            @if ($hasDamaged && $singleUom)
                <div class="kpi-hint">{{ $qty($lines->sum('damaged')) }} {{ $lines->first()->uom }} damaged</div>
            @endif
        </td>
    </tr>
</table>

{{-- ── 4. Received items table ─────────────────────────────────────────── --}}
<div class="section-title">Received Items</div>
<table class="data">
    <thead>
        <tr>
            <th class="text-center" style="width:18px">#</th>
            <th style="width:72px">Item code</th>
            <th>Description</th>
            <th class="text-center" style="width:32px">UoM</th>
            <th class="text-right" style="width:50px">Expected</th>
            <th class="text-right" style="width:48px">Good</th>
            @if ($hasDamaged)
                <th class="text-right" style="width:50px">Damaged</th>
            @endif
            <th class="text-right" style="width:50px">Variance</th>
            <th style="width:150px">Batch / Expiry / Serials</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($lines as $i => $l)
        <tr class="{{ $rowClass($l, $loop->even) }}">
            <td class="text-center">{{ $i + 1 }}</td>
            <td class="code">{{ $l->sku }}</td>
            <td>{{ $l->name }}</td>
            <td class="text-center">{{ $l->uom }}</td>
            <td class="text-right">{{ $qty($l->expected) }}</td>
            <td class="text-right">{{ $qty($l->good) }}</td>
            @if ($hasDamaged)
                <td class="text-right {{ $l->damaged > 0 ? 'neg' : 'zero' }}">{{ $qty($l->damaged) }}</td>
            @endif
            <td class="text-right {{ $tone($l->variance) }}">{{ $signed($l->variance) }}</td>

            {{-- Trace: GOOD / DAMAGED tags, batch, expiry, serials. No empty gaps. --}}
            <td class="trace">
                @foreach (['good' => $l->goodTrace, 'damaged' => $l->damagedTrace] as $conditionKey => $t)
                    @if ($hasTrace($t))
                        <div class="trace-group">
                            @if ($conditionKey === 'damaged' || $hasDamaged)
                                <div><span class="tag tag-{{ $conditionKey }}">{{ strtoupper($conditionKey) }}</span></div>
                            @endif
                            @if ($t->batches->isNotEmpty())
                                <div><span class="lbl">Batch:</span> {{ $t->batches->implode(', ') }}</div>
                            @endif
                            @if ($t->expiries->isNotEmpty())
                                <div><span class="lbl">Expiry:</span> {{ $t->expiries->implode(', ') }}</div>
                            @endif
                            @if ($t->serials->isNotEmpty())
                                <div><span class="lbl">S/N:</span> <span class="code">{{ $t->serials->implode(', ') }}</span></div>
                            @endif
                        </div>
                    @endif
                @endforeach
                @if (! $hasTrace($l->goodTrace) && ! $hasTrace($l->damagedTrace))
                    <span class="zero">—</span>
                @endif
            </td>
        </tr>
    @empty
        <tr class="empty">
            <td colspan="{{ $colCount }}">No lines on this GRN.</td>
        </tr>
    @endforelse

    {{-- Totals row — only when all lines share one UoM --}}
    @if ($lineCount && $singleUom)
        @php $totalVariance = $lines->sum('variance'); @endphp
        <tr class="totals">
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

@if ($hasVariance)
    <div class="notes">
        Red rows = short or damaged; amber rows = over-received. Variance = (good + damaged) − expected.
    </div>
@endif

{{-- ── 5. Transport & Receiving cards ─────────────────────────────────── --}}
<table class="cards">
    <tr>
        {{-- Transport details --}}
        <td class="card">
            <div class="card-head">Transport Details</div>
            <table class="card-body">
                <tr><td class="k">Transporter</td>
                    <td class="v">{{ $grn->transporter_name ?: '—' }}</td></tr>
                <tr><td class="k">Vehicle</td>
                    <td class="v">
                        {{ $grn->vehicle_plate ?: '—' }}
                        @if ($grn->vehicle_type) &nbsp;({{ $grn->vehicle_type }}) @endif
                    </td></tr>
                <tr><td class="k">Driver</td>
                    <td class="v">{{ $grn->driver_name ?: '—' }}</td></tr>
                @if ($grn->container?->container_no)
                    <tr><td class="k">Container no.</td>
                        <td class="v code">{{ $grn->container->container_no }}</td></tr>
                @endif
                @if ($grn->seal_check_result)
                    <tr><td class="k">Seal check</td>
                        <td class="v {{ $sealIntact ? '' : 'neg' }}">
                            {{ ucfirst($grn->seal_check_result) }}
                            @if (! $sealIntact && $grn->seal_mismatch_reason)
                                — {{ $grn->seal_mismatch_reason }}
                            @endif
                        </td></tr>
                @endif
            </table>
        </td>

        {{-- Receiving details --}}
        <td class="card">
            <div class="card-head">Receiving Details</div>
            <table class="card-body">
                <tr><td class="k">Received by</td>
                    <td class="v">{{ $grn->confirmedBy?->name ?? '—' }}</td></tr>
                <tr><td class="k">Date &amp; time</td>
                    <td class="v">{{ $receivedAt->format('d M Y H:i') }}</td></tr>
                <tr><td class="k">Warehouse</td>
                    <td class="v">{{ $grn->warehouse?->name ?? '—' }}</td></tr>
                @if ($grn->receivingDock)
                    <tr><td class="k">Dock</td>
                        <td class="v code">{{ $grn->receivingDock->code }}</td></tr>
                @endif
                <tr><td class="k">Delivery type</td>
                    <td class="v">{{ ucfirst($grn->delivery_type ?? 'full') }}</td></tr>
            </table>
        </td>
    </tr>
</table>

{{-- ── 6. Remarks ───────────────────────────────────────────────────────── --}}
@if (! empty($grn->notes))
    <div class="remarks"><strong>Remarks:</strong> {{ $grn->notes }}</div>
@endif

{{-- ── 7. Signatures ────────────────────────────────────────────────────── --}}
<table class="sig">
    <tr>
        <td>
            <div class="sig-box">
                <div class="sig-role">Received by</div>
                <div class="sig-name">{{ $grn->confirmedBy?->name ?? '' }}</div>
                <div class="sig-date">Date: ____________</div>
            </div>
        </td>
        <td>
            <div class="sig-box">
                <div class="sig-role">Checked by (Supervisor)</div>
                <div class="sig-name">&nbsp;</div>
                <div class="sig-date">Date: ____________</div>
            </div>
        </td>
        <td>
            <div class="sig-box">
                <div class="sig-role">Delivered by (Driver)</div>
                <div class="sig-name">{{ $grn->driver_name ?? '' }}</div>
                <div class="sig-date">Date: ____________</div>
            </div>
        </td>
    </tr>
</table>

</body>
</html>
