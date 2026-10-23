@php
    /** @var \App\Contracts\Reports\ReportDefinition $definition */
    /** @var \Illuminate\Support\Collection $rows */
    /** @var array $summary */
    /** @var array|null $totals */
    /** @var array $brand   ['name','address','logoDataUri'] */
    /** @var array $filters */
    /** @var string $generatedAt */
    /** @var array $signatures  e.g. ['Count Supervisor','Warehouse Manager','Finance'] */
    $signatures = $signatures ?? [];
    $summary    = $summary    ?? [];
    $totals     = $totals     ?? null;
    $columns    = $definition->columns();
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
* { box-sizing: border-box; }
body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 9px;
    color: #1a1a1a;
    margin: 0;
    padding: 14px 16px;
}
/* ── Brand header ── */
.rpt-header {
    display: table;
    width: 100%;
    border-bottom: 2.5px solid #a22631;
    padding-bottom: 8px;
    margin-bottom: 10px;
}
.rpt-header-left  { display: table-cell; width: 60%; vertical-align: top; }
.rpt-header-right { display: table-cell; width: 40%; text-align: right; vertical-align: top; color: #555; font-size: 8px; }
.rpt-logo  { max-height: 38px; max-width: 130px; margin-bottom: 3px; display: block; }
.rpt-company  { font-size: 15px; font-weight: bold; color: #a22631; line-height: 1.2; }
.rpt-address  { font-size: 7.5px; color: #6b7280; margin-top: 1px; }
.rpt-title    { font-size: 12px; font-weight: bold; margin-top: 3px; color: #111; }
/* ── KPI boxes ── */
.kpi-row { display: table; width: 100%; margin-bottom: 10px; }
.kpi-box {
    display: table-cell;
    border: 1px solid #e5e7eb;
    border-left: 3px solid #a22631;
    padding: 5px 8px;
    margin-right: 6px;
    background: #fafafa;
    vertical-align: top;
}
.kpi-label { font-size: 7.5px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.03em; }
.kpi-value { font-size: 13px; font-weight: bold; color: #111; margin-top: 1px; }
/* ── Table ── */
table { width: 100%; border-collapse: collapse; margin-top: 2px; }
thead th {
    background: #a22631;
    color: #fff;
    font-weight: bold;
    font-size: 8px;
    padding: 4px 5px;
    text-align: left;
    border: 1px solid #8a1d28;
}
tbody tr:nth-child(even) td { background: #fafafa; }
tbody td {
    border: 1px solid #e5e7eb;
    padding: 3px 5px;
    font-size: 8px;
    vertical-align: top;
}
.text-right  { text-align: right; }
.text-center { text-align: center; }
.totals-row td {
    background: #f3f4f6 !important;
    font-weight: bold;
    border-top: 1.5px solid #a22631;
}
/* ── Signatures ── */
.sig-block { display: table; width: 100%; margin-top: 44px; }
.sig-col   { display: table-cell; text-align: center; padding: 0 8px; }
.sig-line  { border-top: 1px solid #333; margin-top: 36px; padding-top: 5px; font-size: 8px; color: #444; }
/* ── Footer ── */
.rpt-footer {
    margin-top: 16px;
    font-size: 7.5px;
    color: #9ca3af;
    text-align: center;
    border-top: 1px solid #e5e7eb;
    padding-top: 5px;
}
</style>
</head>
<body>

{{-- ── Brand header ── --}}
<div class="rpt-header">
    <div class="rpt-header-left">
        @if (!empty($brand['logoDataUri']))
            <img src="{{ $brand['logoDataUri'] }}" class="rpt-logo" alt="">
        @endif
        <div class="rpt-company">{{ $brand['name'] }}</div>
        @if (!empty($brand['address']))
            <div class="rpt-address">{{ $brand['address'] }}</div>
        @endif
        <div class="rpt-title">{{ $definition->label() }}</div>
    </div>
    <div class="rpt-header-right">
        @if (!empty($filters['date_from']) && !empty($filters['date_to']))
            <strong>Period:</strong>
            {{ \Carbon\Carbon::parse($filters['date_from'])->format('d M Y') }}
            – {{ \Carbon\Carbon::parse($filters['date_to'])->format('d M Y') }}<br>
        @elseif (!empty($filters['as_of_date']))
            <strong>As at:</strong> {{ \Carbon\Carbon::parse($filters['as_of_date'])->format('d M Y') }}<br>
        @endif
        <strong>Generated:</strong> {{ $generatedAt }}
    </div>
</div>

{{-- ── KPI summary boxes ── --}}
@if (!empty($summary))
<div class="kpi-row">
    @foreach ($summary as $label => $value)
    <div class="kpi-box">
        <div class="kpi-label">{{ $label }}</div>
        <div class="kpi-value">{{ is_numeric($value) ? number_format((float) $value, is_int($value) || floor((float)$value) == $value ? 0 : 2) : $value }}</div>
    </div>
    @endforeach
</div>
@endif

{{-- ── Data table ── --}}
<table>
    <thead>
        <tr>
            @foreach ($columns as $col)
                <th class="text-{{ $col['align'] ?? 'left' }}">{{ $col['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
        <tr>
            @foreach ($columns as $col)
            @php
                $val = data_get($row, $col['key']);
                $fmt = $col['format'] ?? 'text';
            @endphp
            <td class="text-{{ $col['align'] ?? 'left' }}">
                @if ($fmt === 'number')
                    {{ is_numeric($val) ? number_format((float) $val, 2) : ($val ?? '—') }}
                @elseif ($fmt === 'date')
                    {{ $val instanceof \Carbon\Carbon ? $val->format('d M Y') : ($val ? \Carbon\Carbon::parse($val)->format('d M Y') : '—') }}
                @elseif ($fmt === 'enum')
                    {{ $val instanceof \BackedEnum ? (method_exists($val, 'label') ? $val->label() : $val->value) : ($val ?? '—') }}
                @else
                    {{ $val ?? '—' }}
                @endif
            </td>
            @endforeach
        </tr>
        @endforeach

        {{-- Totals row --}}
        @if ($totals !== null)
        <tr class="totals-row">
            @foreach ($columns as $i => $col)
            <td class="text-{{ $col['align'] ?? 'left' }}">
                @if ($i === 0)
                    <strong>Total</strong>
                @elseif (array_key_exists($col['key'], $totals))
                    {{ number_format((float) $totals[$col['key']], 2) }}
                @endif
            </td>
            @endforeach
        </tr>
        @endif
    </tbody>
</table>

{{-- ── Signature block ── --}}
@if (!empty($signatures))
<div class="sig-block">
    @foreach ($signatures as $sigLabel)
    <div class="sig-col">
        <div class="sig-line">{{ $sigLabel }}</div>
    </div>
    @endforeach
</div>
@endif

{{-- ── Footer ── --}}
<div class="rpt-footer">
    {{ $brand['name'] }} · {{ $definition->label() }} · {{ $generatedAt }} · {{ count($rows) }} records
</div>

</body>
</html>
