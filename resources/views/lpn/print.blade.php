<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>LPN Labels — {{ $grn->number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', monospace;
            background: #fff;
            color: #1a1a1a;
        }

        .print-header {
            padding: 16px 20px;
            border-bottom: 2px solid #1a1a1a;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .print-header h1 { font-size: 14px; font-weight: 700; }
        .print-header .meta { font-size: 11px; color: #666; }

        .labels-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            padding: 12px;
        }

        .label {
            border: 1.5px solid #1a1a1a;
            border-radius: 6px;
            padding: 10px;
            page-break-inside: avoid;
        }

        .label-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .label-title { font-size: 9px; text-transform: uppercase; letter-spacing: 0.06em; color: #666; }
        .label-grn { font-size: 9px; color: #666; }

        .lpn-code {
            font-size: 16px;
            font-weight: 700;
            font-family: monospace;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
            text-align: center;
        }

        .barcode-wrap {
            width: 100%;
            overflow: hidden;
            margin: 4px 0;
        }
        .barcode-wrap svg {
            width: 100%;
            height: 48px;
        }

        .label-footer {
            margin-top: 6px;
            font-size: 9px;
            color: #666;
            text-align: center;
        }

        @media print {
            .no-print { display: none !important; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="print-header">
    <div>
        <h1>LPN Labels — {{ $grn->number }}</h1>
        <p class="meta">Warehouse: {{ $grn->warehouse?->name }} &bull; Printed: {{ now()->format('d M Y H:i') }}</p>
    </div>
    <button class="no-print"
            onclick="window.print()"
            style="padding: 8px 16px; font-size: 13px; font-weight: 600; background: #1a1a1a; color: #fff; border: none; border-radius: 6px; cursor: pointer;">
        Print Labels
    </button>
</div>

<div class="labels-grid">
    @foreach ($labels as $label)
    <div class="label">
        <div class="label-header">
            <span class="label-title">License Plate</span>
            <span class="label-grn">{{ $label['grn_number'] }}</span>
        </div>

        <div class="lpn-code">{{ $label['lpn']->code }}</div>

        <div class="barcode-wrap">
            {!! $label['svg'] !!}
        </div>

        <div class="label-footer">{{ $label['warehouse'] }}</div>
    </div>
    @endforeach
</div>

</body>
</html>
