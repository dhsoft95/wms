@php
    /** @var \App\Contracts\Reports\ReportDefinition $definition */
    /** @var \Illuminate\Support\Collection $rows */
    /** @var array $summary     label => scalar | ['value' => , 'hint' => , 'tone' => 'danger|warning|success'] */
    /** @var array|null $totals key => value (grand total row) */
    /** @var array $meta        label => value (shown in header) */
    /** @var array $brand       ['name','address','logoDataUri'] */
    /** @var array $filters */
    /** @var string $generatedAt */
    /** @var string|null $notes */

    $summary  = $summary ?? [];
    $totals   = $totals ?? null;
    $meta     = $meta ?? [];
    $notes    = $notes ?? null;
    $columns  = $definition->columns();
    $colCount = count($columns);

    $groupBy  = method_exists($definition, 'groupBy') ? $definition->groupBy() : null;
    $rowClass = method_exists($definition, 'rowClass')
        ? fn ($row) => (string) $definition->rowClass($row)
        : fn ($row) => '';

    $groups = $groupBy ? $rows->groupBy($groupBy) : collect(['' => $rows]);

    $totalCols = collect($columns)->filter(fn ($c) => $c['total'] ?? false)->pluck('key')->all();

    $numericFormats = ['number', 'quantity', 'signed', 'percent', 'money'];

    $autoDecimals = fn ($v) => floor(abs((float) $v)) == abs((float) $v) ? 0 : 2;

    $fmt = function ($val, array $col) use ($autoDecimals, $numericFormats) {
        $type = $col['format'] ?? 'text';
        $dec  = $col['decimals'] ?? null;

        if ($val === null || $val === '') {
            return '—';
        }

        if (in_array($type, $numericFormats, true) && ! is_numeric($val)) {
            return (string) $val;
        }

        return match ($type) {
            'number'   => number_format((float) $val, $dec ?? 2),
            'quantity' => number_format((float) $val, $dec ?? $autoDecimals($val)),
            'signed'   => ((float) $val > 0 ? '+' : '') . number_format((float) $val, $dec ?? $autoDecimals($val)),
            'percent'  => number_format((float) $val, $dec ?? 1) . '%',
            'money'    => number_format((float) $val, $dec ?? 2),
            'date'     => \Carbon\Carbon::parse($val)->format('d M Y'),
            'datetime' => \Carbon\Carbon::parse($val)->format('d M Y H:i'),
            'enum'     => $val instanceof \BackedEnum
                ? (method_exists($val, 'label') ? $val->label() : $val->value)
                : (string) $val,
            default    => (string) $val,
        };
    };

    $tone = function ($val, array $col) {
        if (($col['tone'] ?? null) !== 'signed' || ! is_numeric($val)) {
            return '';
        }

        return $val < 0 ? 'neg' : ($val > 0 ? 'pos' : 'zero');
    };

    $align = fn (array $col) => 'text-' . ($col['align']
        ?? (in_array($col['format'] ?? 'text', $numericFormats, true) ? 'right' : 'left'));
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
        .rpt-meta   { font-size: 8px; color: #555; text-align: right; line-height: 1.6; }
        .rpt-meta strong { color: #222; }

        /* KPI */
        .kpi-row { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 12px -6px; }
        .kpi-box {
            border: 1px solid #e5e7eb;
            border-left: 3px solid #a22631;
            background: #fafafa;
            padding: 6px 8px;
            vertical-align: top;
        }
        .kpi-box.tone-danger  { border-left-color: #b91c1c; }
        .kpi-box.tone-warning { border-left-color: #d97706; }
        .kpi-box.tone-success { border-left-color: #15803d; }
        .kpi-label { font-size: 7px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
        .kpi-value { font-size: 13px; font-weight: bold; color: #111; margin-top: 2px; }
        .kpi-hint  { font-size: 7px; color: #6b7280; margin-top: 1px; }

        /* Table */
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
        .data tr.muted td  { color: #9ca3af; }

        .group-row td {
            background: #f3f4f6;
            font-weight: bold;
            font-size: 8px;
            color: #a22631;
            border-top: 1.5px solid #d1d5db;
        }
        .subtotal-row td { background: #f9fafb; font-weight: bold; font-size: 7.5px; color: #374151; }
        .totals-row td   { background: #f3f4f6; font-weight: bold; border-top: 1.5px solid #a22631; }
        .empty-row td    { text-align: center; color: #9ca3af; padding: 14px; }

        .text-left   { text-align: left; }
        .text-right  { text-align: right; }
        .text-center { text-align: center; }
        .neg  { color: #b91c1c; font-weight: bold; }
        .pos  { color: #b45309; font-weight: bold; }
        .zero { color: #9ca3af; }

        /* Notes */
        .notes { margin-top: 8px; font-size: 7.5px; color: #6b7280; }

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
            <td>{{ $brand['name'] }} · {{ $definition->label() }} · {{ $generatedAt }} · {{ $rows->count() }} records</td>
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
            <div class="rpt-title">{{ $definition->label() }}</div>
        </td>
        <td width="50%" class="rpt-meta">
            @foreach ($meta as $label => $value)
                <strong>{{ $label }}:</strong> {{ $value }}<br>
            @endforeach
            @if (! empty($filters['date_from']) && ! empty($filters['date_to']))
                <strong>Period:</strong>
                {{ \Carbon\Carbon::parse($filters['date_from'])->format('d M Y') }}
                – {{ \Carbon\Carbon::parse($filters['date_to'])->format('d M Y') }}<br>
            @elseif (! empty($filters['as_of_date']))
                <strong>As at:</strong> {{ \Carbon\Carbon::parse($filters['as_of_date'])->format('d M Y') }}<br>
            @endif
            <strong>Generated:</strong> {{ $generatedAt }}
        </td>
    </tr>
</table>

@if (! empty($summary))
    <table class="kpi-row">
        <tr>
            @foreach ($summary as $label => $item)
                @php
                    $value = is_array($item) ? ($item['value'] ?? null) : $item;
                    $hint  = is_array($item) ? ($item['hint'] ?? null) : null;
                    $kTone = is_array($item) && ! empty($item['tone']) ? 'tone-' . $item['tone'] : '';
                @endphp
                <td class="kpi-box {{ $kTone }}">
                    <div class="kpi-label">{{ $label }}</div>
                    <div class="kpi-value">
                        {{ is_numeric($value) ? number_format((float) $value, $autoDecimals($value)) : $value }}
                    </div>
                    @if ($hint)
                        <div class="kpi-hint">{{ $hint }}</div>
                    @endif
                </td>
            @endforeach
        </tr>
    </table>
@endif

<table class="data">
    <thead>
    <tr>
        @foreach ($columns as $col)
            <th class="{{ $align($col) }}" @isset($col['width']) style="width: {{ $col['width'] }}" @endisset>
                {{ $col['label'] }}
            </th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @forelse ($groups as $groupLabel => $groupRows)
        @if ($groupBy)
            <tr class="group-row">
                <td colspan="{{ $colCount }}">{{ $groupLabel ?: '—' }} ({{ $groupRows->count() }})</td>
            </tr>
        @endif

        @foreach ($groupRows as $row)
            <tr class="{{ trim(($loop->even ? 'stripe ' : '') . $rowClass($row)) }}">
                @foreach ($columns as $col)
                    @php $val = data_get($row, $col['key']); @endphp
                    <td class="{{ $align($col) }} {{ $tone($val, $col) }}">{{ $fmt($val, $col) }}</td>
                @endforeach
            </tr>
        @endforeach

        @if ($groupBy && ! empty($totalCols))
            <tr class="subtotal-row">
                @foreach ($columns as $c => $col)
                    @php
                        $sub = in_array($col['key'], $totalCols, true)
                            ? $groupRows->sum(fn ($r) => (float) data_get($r, $col['key']))
                            : null;
                    @endphp
                    <td class="{{ $align($col) }} {{ $sub !== null ? $tone($sub, $col) : '' }}">
                        @if ($c === 0)
                            Subtotal
                        @elseif ($sub !== null)
                            {{ $fmt($sub, $col) }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @endif
    @empty
        <tr class="empty-row">
            <td colspan="{{ $colCount }}">No records found for the selected filters.</td>
        </tr>
    @endforelse

    @if ($totals !== null && $rows->isNotEmpty())
        <tr class="totals-row">
            @foreach ($columns as $c => $col)
                @php $tVal = $totals[$col['key']] ?? null; @endphp
                <td class="{{ $align($col) }} {{ $tVal !== null ? $tone($tVal, $col) : '' }}">
                    @if ($c === 0)
                        Total
                    @elseif ($tVal !== null)
                        {{ $fmt($tVal, $col) }}
                    @endif
                </td>
            @endforeach
        </tr>
    @endif
    </tbody>
</table>

@if ($notes)
    <div class="notes">{{ $notes }}</div>
@endif

</body>
</html>
