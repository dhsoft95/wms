<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disposal Note — {{ $disposalRequest->number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 11px; color: #1a1a1a; padding: 24px; }
        h1 { font-size: 20px; font-weight: 700; margin-bottom: 2px; }
        .sub { font-size: 11px; color: #666; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 16px 0; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin: 16px 0; }
        .field label { font-size: 9px; text-transform: uppercase; color: #888; letter-spacing: 0.05em; }
        .field p { font-size: 11px; font-weight: 500; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 10px; }
        thead { background: #f5f5f5; }
        th { text-align: left; padding: 6px 8px; border-bottom: 1px solid #ddd; font-weight: 600; font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; }
        td { padding: 6px 8px; border-bottom: 1px solid #eee; }
        .text-right { text-align: right; }
        tfoot td { font-weight: 700; border-top: 2px solid #aaa; background: #f9f9f9; }
        .section-title { font-size: 12px; font-weight: 700; margin: 18px 0 6px; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 9px; font-weight: 600; text-transform: uppercase; border: 1px solid #aaa; }
        .sig-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; margin-top: 40px; }
        .sig-line { border-top: 1px solid #aaa; margin-top: 24px; padding-top: 4px; font-size: 9px; color: #888; }
        @media print { @page { margin: 1.5cm; } }
    </style>
</head>
<body>

<div style="display:flex; justify-content:space-between; align-items:flex-start;">
    <div>
        <h1>Disposal Note</h1>
        <p class="sub">{{ $disposalRequest->number }} &bull; {{ $disposalRequest->method?->label() }}</p>
    </div>
    <div style="text-align:right;">
        <span class="badge">{{ strtoupper($disposalRequest->status->value) }}</span>
        <p class="sub" style="margin-top:4px;">{{ $disposalRequest->virtualWarehouse?->name }}</p>
    </div>
</div>

<div class="grid-3" style="margin-top:16px;">
    <div class="field">
        <label>Department</label>
        <p>{{ $disposalRequest->department?->name ?? '—' }}</p>
    </div>
    <div class="field">
        <label>Created By</label>
        <p>{{ $disposalRequest->createdBy?->name ?? '—' }}</p>
    </div>
    <div class="field">
        <label>Date</label>
        <p>{{ $disposalRequest->created_at->format('d M Y') }}</p>
    </div>
    <div class="field">
        <label>Submitted</label>
        <p>{{ $disposalRequest->submitted_at?->format('d M Y') ?? '—' }}</p>
    </div>
    <div class="field">
        <label>Approved By</label>
        <p>{{ $disposalRequest->approvedBy?->name ?? '—' }}</p>
    </div>
    <div class="field">
        <label>Approved Date</label>
        <p>{{ $disposalRequest->approved_at?->format('d M Y') ?? '—' }}</p>
    </div>
</div>

@if($disposalRequest->reason)
<div class="field" style="margin-bottom:12px;">
    <label>Reason</label>
    <p>{{ $disposalRequest->reason }}</p>
</div>
@endif

<p class="section-title">Disposal Lines</p>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Item / SKU</th>
            <th>Batch</th>
            <th>Bin</th>
            <th>Status</th>
            <th class="text-right">Qty</th>
            <th>Unit</th>
            <th class="text-right">Unit Price</th>
            <th class="text-right">Value</th>
        </tr>
    </thead>
    <tbody>
        @foreach($disposalRequest->lines as $i => $line)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>
                <strong>{{ $line->item?->name ?? '—' }}</strong><br>
                <span style="color:#888;">{{ $line->item?->sku }}</span>
            </td>
            <td>{{ $line->batch?->batch_no ?? '—' }}</td>
            <td>{{ $line->location?->code ?? '—' }}</td>
            <td>{{ $line->stock_status?->label() ?? '—' }}</td>
            <td class="text-right">{{ number_format((float) $line->qty, 4) }}</td>
            <td>{{ $line->item?->baseUom?->code ?? '—' }}</td>
            <td class="text-right">
                @if($line->value !== null && bccomp((string)$line->qty,'0',4) > 0)
                    {{ number_format((float) bcdiv((string)$line->value, (string)$line->qty, 6), 4) }}
                @else
                    —
                @endif
            </td>
            <td class="text-right">
                @if($line->value !== null)
                    {{ number_format((float) $line->value, 2) }}
                @else
                    —
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="8" class="text-right">Total Value:</td>
            <td class="text-right">
                @if($disposalRequest->total_value !== null)
                    {{ number_format((float) $disposalRequest->total_value, 2) }}
                @else
                    —
                @endif
            </td>
        </tr>
    </tfoot>
</table>

{{-- Completion details --}}
@if($disposalRequest->status === \App\Enums\Inventory\DisposalStatus::Completed)
<p class="section-title">Completion Details</p>
<div class="grid-3">
    @if($disposalRequest->method === \App\Enums\Inventory\DisposalMethod::Auction)
        <div class="field"><label>Buyer Name</label><p>{{ $disposalRequest->buyer_name ?? '—' }}</p></div>
        <div class="field"><label>Sale Price</label><p>{{ $disposalRequest->sale_price ? number_format((float)$disposalRequest->sale_price,2) : '—' }}</p></div>
        <div class="field"><label>Sale Date</label><p>{{ $disposalRequest->sale_date?->format('d M Y') ?? '—' }}</p></div>
    @else
        <div class="field"><label>Destroy Method</label><p>{{ $disposalRequest->destroy_method?->label() ?? '—' }}</p></div>
        <div class="field"><label>Destroy Date</label><p>{{ $disposalRequest->destroy_date?->format('d M Y') ?? '—' }}</p></div>
        <div class="field"><label>Witness</label><p>{{ $disposalRequest->witness_name ?? '—' }}</p></div>
    @endif
</div>
@endif

<div class="sig-grid">
    <div>
        <div class="sig-line">Prepared By / Date</div>
    </div>
    <div>
        <div class="sig-line">Approved By / Date</div>
    </div>
    <div>
        <div class="sig-line">Completed By / Date</div>
    </div>
</div>

<p style="margin-top:24px; font-size:9px; color:#aaa; text-align:center;">
    Printed on {{ now()->format('d M Y H:i') }} &bull; {{ config('app.name') }}
</p>

</body>
</html>
