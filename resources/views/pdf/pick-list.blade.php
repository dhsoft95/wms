@php
    /** @var \App\Models\PickList $pickList */
    /** @var array $brand */
    use Carbon\Carbon;
    $do    = $pickList->deliveryOrder;
    $tasks = $pickList->tasks->sortBy('sequence');
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
            font-size: 9px;
            color: #1a1a1a;
            margin: 0;
        }

        .brand       { text-align: center; margin-bottom: 8px; }
        .brand-logo  { max-height: 52px; max-width: 180px; display: inline-block; }
        .brand-addr  { font-size: 7.5px; color: #6b7280; margin-top: 3px; }

        .doc-header  { width: 100%; border-collapse: collapse; border-bottom: 2.5px solid #a22631; padding-bottom: 8px; margin-bottom: 10px; }
        .doc-header td { vertical-align: top; border: 0; padding: 0; }
        .doc-title   { font-size: 16px; font-weight: bold; color: #a22631; letter-spacing: 0.03em; }
        .doc-sub     { font-size: 9px; color: #555; margin-top: 2px; }

        .meta-grid   { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .meta-grid td { padding: 3px 6px; vertical-align: top; border: 0; }
        .meta-box    { border: 1px solid #e5e7eb; background: #fafafa; padding: 6px 10px; }
        .meta-label  { font-size: 7px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.06em; display: block; margin-bottom: 2px; }
        .meta-value  { font-size: 9.5px; font-weight: bold; color: #111; }

        .tasks       { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .tasks thead th {
            background: #a22631;
            color: #fff;
            font-weight: bold;
            font-size: 8px;
            padding: 5px 6px;
            border: 1px solid #8a1d28;
            text-align: left;
        }
        .tasks thead th.text-center { text-align: center; }
        .tasks tbody td {
            border: 1px solid #e5e7eb;
            padding: 5px 6px;
            font-size: 8.5px;
            vertical-align: middle;
        }
        .tasks tbody tr.stripe td { background: #fafafa; }
        .tasks tbody tr td:first-child { text-align: center; font-weight: bold; color: #6b7280; }

        .loc-code   { font-family: DejaVu Sans Mono, monospace; font-weight: bold; font-size: 10px; color: #111; }
        .item-sku   { font-family: DejaVu Sans Mono, monospace; font-size: 7.5px; color: #9ca3af; }
        .item-name  { font-size: 8.5px; color: #111; }
        .batch      { font-size: 7.5px; color: #6b7280; font-style: italic; }
        .qty-box    { text-align: right; font-weight: bold; font-size: 10px; }

        .check-box {
            width: 14px; height: 14px;
            border: 1.5px solid #374151;
            display: inline-block;
            margin: 0 auto;
        }

        .sig-block  { width: 100%; border-collapse: collapse; margin-top: 18px; }
        .sig-block td { width: 33%; padding: 0 8px; border: 0; vertical-align: bottom; }
        .sig-line   { border-top: 1px solid #374151; padding-top: 4px; font-size: 7.5px; color: #6b7280; }

        .rpt-footer {
            position: fixed; bottom: -12mm; left: 0; right: 0;
            font-size: 7px; color: #9ca3af;
            border-top: 1px solid #e5e7eb; padding-top: 4px;
        }
        .rpt-footer table { width: 100%; }
        .rpt-footer td    { border: 0; padding: 0; }
        .text-right       { text-align: right; }
        .pagenum:before   { content: counter(page); }
        .pagecount:before { content: counter(pages); }
    </style>
</head>
<body>

<div class="rpt-footer">
    <table>
        <tr>
            <td>{{ $brand['name'] }} · Pick List · {{ $pickList->number }} · Printed: {{ now()->format('d M Y H:i') }}</td>
            <td class="text-right">Page <span class="pagenum"></span> of <span class="pagecount"></span></td>
        </tr>
    </table>
</div>

{{-- Branding --}}
<div class="brand">
    @if (! empty($brand['logoDataUri']))
        <img src="{{ $brand['logoDataUri'] }}" class="brand-logo" alt="">
    @endif
    @if (! empty($brand['address']))
        <div class="brand-addr">{{ $brand['address'] }}</div>
    @endif
</div>

{{-- Document header --}}
<table class="doc-header">
    <tr>
        <td width="60%">
            <div class="doc-title">PICK LIST</div>
            <div class="doc-sub">Use this document to pick items from the warehouse and check off each task as completed.</div>
        </td>
        <td width="40%" style="text-align:right; font-size:8px; color:#555; line-height:1.8;">
            <strong style="font-size:13px; color:#111;">{{ $pickList->number }}</strong><br>
            <strong>Delivery Order:</strong> {{ $do->number }}<br>
            <strong>Printed:</strong> {{ now()->format('d M Y H:i') }}
        </td>
    </tr>
</table>

{{-- Meta info grid --}}
<table class="meta-grid" cellspacing="0" cellpadding="0">
    <tr>
        <td style="width:25%; padding: 0 4px 0 0;">
            <div class="meta-box">
                <span class="meta-label">Consignee</span>
                <span class="meta-value">{{ $do->consigneePartner?->name ?? '—' }}</span>
            </div>
        </td>
        <td style="width:25%; padding: 0 4px;">
            <div class="meta-box">
                <span class="meta-label">Warehouse</span>
                <span class="meta-value">{{ $do->warehouse?->name ?? '—' }}</span>
            </div>
        </td>
        <td style="width:25%; padding: 0 4px;">
            <div class="meta-box">
                <span class="meta-label">Required Date</span>
                <span class="meta-value">{{ $do->required_date?->format('d M Y') ?? '—' }}</span>
            </div>
        </td>
        <td style="width:25%; padding: 0 0 0 4px;">
            <div class="meta-box">
                <span class="meta-label">Assigned Picker</span>
                <span class="meta-value">{{ $pickList->assignedTo?->name ?? 'Unassigned' }}</span>
            </div>
        </td>
    </tr>
</table>

{{-- Task table --}}
<table class="tasks">
    <thead>
        <tr>
            <th class="text-center" style="width:5%">#</th>
            <th style="width:14%">Location</th>
            <th style="width:35%">Item</th>
            <th style="width:15%">Batch / Lot</th>
            <th style="width:10%; text-align:right;">Qty</th>
            <th style="width:10%; text-align:right;">UOM</th>
            <th class="text-center" style="width:11%">Done ✓</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($tasks as $task)
            <tr class="{{ $loop->even ? 'stripe' : '' }}">
                <td>{{ $task->sequence }}</td>
                <td>
                    <div class="loc-code">{{ $task->location?->code ?? '—' }}</div>
                    @if ($task->location?->name && $task->location->name !== $task->location->code)
                        <div style="font-size:7px;color:#9ca3af;">{{ $task->location->name }}</div>
                    @endif
                </td>
                <td>
                    <div class="item-sku">{{ $task->item?->sku ?? '—' }}</div>
                    <div class="item-name">{{ $task->item?->name ?? '—' }}</div>
                </td>
                <td>
                    <div class="batch">{{ $task->batch?->batch_no ?? '—' }}</div>
                </td>
                <td class="qty-box">{{ number_format((float) $task->qty_to_pick, 2) }}</td>
                <td style="text-align:right; font-size:8px; color:#6b7280;">
                    {{ $task->item?->baseUom?->code ?? '' }}
                </td>
                <td style="text-align:center;">
                    <div class="check-box"></div>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:16px;">No tasks found.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- Signature block --}}
<table class="sig-block">
    <tr>
        <td>
            <div style="height:28px;"></div>
            <div class="sig-line">Picker's Signature &amp; Name</div>
        </td>
        <td>
            <div style="height:28px;"></div>
            <div class="sig-line">Checker / Supervisor</div>
        </td>
        <td>
            <div style="height:28px;"></div>
            <div class="sig-line">Date &amp; Time Completed</div>
        </td>
    </tr>
</table>

</body>
</html>
