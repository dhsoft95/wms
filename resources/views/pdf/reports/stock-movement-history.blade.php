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
    .type-receipt  { display:inline-block; padding:1px 5px; border-radius:3px; background:#dbeafe; color:#1e40af; font-size:8px; }
    .type-putaway  { display:inline-block; padding:1px 5px; border-radius:3px; background:#d1fae5; color:#065f46; font-size:8px; }
    .type-pick     { display:inline-block; padding:1px 5px; border-radius:3px; background:#fef3c7; color:#92400e; font-size:8px; }
    .type-dispatch { display:inline-block; padding:1px 5px; border-radius:3px; background:#fee2e2; color:#991b1b; font-size:8px; }
    .type-other    { display:inline-block; padding:1px 5px; border-radius:3px; background:#f3f4f6; color:#374151; font-size:8px; }
    .qty-pos { color: #16a34a; font-weight: bold; }
    .qty-neg { color: #dc2626; font-weight: bold; }
    .footer { margin-top: 18px; border-top: 1px solid #e5e7eb; padding-top: 6px; font-size: 8px; color: #9ca3af; text-align: center; }
    .note { font-size: 8px; color: #ef4444; margin-bottom: 6px; }
</style>
</head>
<body>

@php $title = 'STOCK MOVEMENT HISTORY'; @endphp
@include('pdf.reports._header')

@php
    $totalTxns = $transactions->count();
    $typeMap = fn($t) => match($t) {
        'receipt', 'putaway' => 'type-receipt',
        'pick'               => 'type-pick',
        'dispatch'           => 'type-dispatch',
        'relocation', 'adjustment', 'count_adjustment' => 'type-other',
        default              => 'type-other',
    };
@endphp

@if ($totalTxns >= 500)
<p class="note">* Report limited to the latest 500 transactions. Narrow the date range for a complete view.</p>
@endif

<div class="section-title">Transactions ({{ number_format($totalTxns) }})</div>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Date &amp; Time</th>
            <th>Type</th>
            <th>Ref No.</th>
            <th>SKU</th>
            <th>Item</th>
            <th>Location</th>
            <th class="text-right">Qty</th>
            <th>Posted By</th>
        </tr>
    </thead>
    <tbody>
        @php $seq = 0; @endphp
        @forelse ($transactions as $txn)
            @foreach ($txn->movements as $movement)
            @php $seq++; $qty = (float)$movement->qty; @endphp
            <tr>
                <td style="color:#aaa;">{{ $seq }}</td>
                <td>{{ $txn->created_at?->format('d M Y H:i') }}</td>
                <td>
                    @php $t = $txn->type?->value ?? $txn->type ?? ''; @endphp
                    <span class="{{ $typeMap($t) }}">{{ $txn->type?->label() ?? ucfirst(str_replace('_', ' ', $t)) }}</span>
                </td>
                <td class="mono">{{ $txn->reference_no ?? '—' }}</td>
                <td class="mono">{{ $movement->item?->sku ?? '—' }}</td>
                <td>{{ $movement->item?->name ?? '—' }}</td>
                <td class="mono">{{ $movement->location?->code ?? '—' }}</td>
                <td class="text-right mono {{ $qty >= 0 ? 'qty-pos' : 'qty-neg' }}">
                    {{ $qty >= 0 ? '+' : '' }}{{ number_format($qty, 4) }}
                </td>
                <td>{{ $txn->postedBy?->name ?? '—' }}</td>
            </tr>
            @endforeach
        @empty
        <tr><td colspan="9" style="text-align:center;padding:16px;color:#aaa;">No movements in this period.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">{{ $brandName }} &bull; Stock Movement History &bull; {{ \Carbon\Carbon::parse($from)->format('d M Y') }} – {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</div>
</body>
</html>
