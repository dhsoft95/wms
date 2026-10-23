<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; margin: 0; padding: 16px; }
        .header { display: table; width: 100%; margin-bottom: 16px; border-bottom: 2px solid #a22631; padding-bottom: 10px; }
        .header-left { display: table-cell; width: 65%; }
        .header-right { display: table-cell; width: 35%; text-align: right; vertical-align: top; font-size: 9px; color: #666; }
        .brand-name { font-size: 16px; font-weight: bold; color: #a22631; }
        .doc-title { font-size: 13px; font-weight: bold; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f5f5f5; border: 1px solid #ccc; padding: 4px 6px; font-size: 9px; text-align: left; font-weight: bold; }
        td { border: 1px solid #ddd; padding: 4px 6px; font-size: 9px; vertical-align: top; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer { margin-top: 20px; font-size: 8px; color: #999; text-align: center; border-top: 1px solid #eee; padding-top: 6px; }
    </style>
</head>
<body>

    <div class="header">
        <div class="header-left">
            <div class="brand-name">{{ $brandName }}</div>
            <div class="doc-title">Pending Orders Report</div>
        </div>
        <div class="header-right">
            @if (!empty($filters['date_from']))
                Period: {{ \Carbon\Carbon::parse($filters['date_from'])->format('d M Y') }}
                — {{ \Carbon\Carbon::parse($filters['date_to'])->format('d M Y') }}
            @endif
            <br>Generated: {{ $generatedAt }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($definition->columns() as $col)
                    <th class="text-{{ $col['align'] ?? 'left' }}">{{ $col['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @foreach ($definition->columns() as $col)
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
        </tbody>
    </table>

    <div class="footer">{{ $brandName }} · Pending Orders Report · {{ $generatedAt }} · {{ count($rows) }} records</div>

</body>
</html>
