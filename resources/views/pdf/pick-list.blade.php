@php
    /** @var \App\Models\PickList $pickList */
    /** @var array<int, string[]> $taskSerials   [task_id => [serial_no, ...]] */
    /** @var array $brand */
    $do    = $pickList->deliveryOrder;
    $tasks = $pickList->tasks->sortBy('sequence');

    // Expand tasks into printable rows.
    // Serial-tracked items produce one row per serial number; others one row per task.
    $rows = [];
    foreach ($tasks as $task) {
        $serials   = $taskSerials[$task->id] ?? [];
        $isSerial  = $task->item?->track_serial && count($serials) > 0;

        if ($isSerial) {
            foreach ($serials as $idx => $sn) {
                $rows[] = [
                    'seq'        => $task->sequence . ($idx > 0 ? '' : ''),
                    'seq_show'   => $idx === 0,
                    'location'   => $task->location?->code ?? '—',
                    'sku'        => $task->item?->sku ?? '',
                    'name'       => $task->item?->name ?? '—',
                    'uom'        => $task->item?->baseUom?->code ?? '',
                    'batch'      => $idx === 0 ? ($task->batch?->batch_no ?? '—') : '',
                    'serial_no'  => $sn,
                    'qty'        => 1,
                    'is_serial'  => true,
                    'first'      => $idx === 0,
                    'count'      => count($serials),
                    'status'     => $task->status->value,
                ];
            }
        } else {
            $rows[] = [
                'seq'       => $task->sequence,
                'seq_show'  => true,
                'location'  => $task->location?->code ?? '—',
                'sku'       => $task->item?->sku ?? '',
                'name'      => $task->item?->name ?? '—',
                'uom'       => $task->item?->baseUom?->code ?? '',
                'batch'     => $task->batch?->batch_no ?? '—',
                'serial_no' => null,
                'qty'       => (float) $task->qty_to_pick,
                'is_serial' => false,
                'first'     => true,
                'count'     => 1,
                'status'    => $task->status->value,
            ];
        }
    }
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
            font-size: 7.5px;
            padding: 5px 6px;
            border: 1px solid #8a1d28;
            text-align: left;
        }
        .tasks thead th.center { text-align: center; }
        .tasks thead th.right  { text-align: right; }

        .tasks tbody td {
            border: 1px solid #e5e7eb;
            padding: 4px 6px;
            font-size: 8.5px;
            vertical-align: middle;
        }
        .tasks tbody tr.stripe td { background: #fafafa; }

        /* Serial row continuation — lighter border top within same task group */
        .tasks tbody tr.serial-cont td {
            border-top: 1px dashed #e5e7eb;
            background: #fffbf5;
        }

        .seq-cell    { text-align: center; font-weight: bold; font-size: 9px; color: #6b7280; }
        .loc-code    { font-family: DejaVu Sans Mono, monospace; font-weight: bold; font-size: 10px; color: #111; }
        .item-sku    { font-family: DejaVu Sans Mono, monospace; font-size: 7.5px; color: #9ca3af; }
        .item-name   { font-size: 8.5px; color: #111; }
        .serial-pill {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 8.5px;
            font-weight: bold;
            color: #1d4ed8;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 1px 5px;
            border-radius: 3px;
            display: inline-block;
        }
        .batch-txt   { font-size: 7.5px; color: #6b7280; font-style: italic; }
        .qty-cell    { text-align: right; font-weight: bold; font-size: 10px; }
        .uom-cell    { text-align: right; font-size: 8px; color: #6b7280; }
        .check-cell  { text-align: center; }
        .check-box   {
            width: 13px; height: 13px;
            border: 1.5px solid #374151;
            display: inline-block;
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
            <div class="doc-sub">Pick items from the listed locations and check off each line when done.</div>
        </td>
        <td width="40%" style="text-align:right; font-size:8px; color:#555; line-height:1.8;">
            <strong style="font-size:13px; color:#111;">{{ $pickList->number }}</strong><br>
            <strong>Delivery Order:</strong> {{ $do->number }}<br>
            <strong>Printed:</strong> {{ now()->format('d M Y H:i') }}
        </td>
    </tr>
</table>

{{-- Meta info --}}
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

{{-- Task / serial table --}}
<table class="tasks">
    <thead>
        <tr>
            <th class="center" style="width:5%">#</th>
            <th style="width:12%">Location</th>
            <th style="width:30%">Item</th>
            <th style="width:18%">Serial No.</th>
            <th style="width:12%">Batch / Lot</th>
            <th class="right" style="width:8%">Qty</th>
            <th class="right" style="width:8%">UOM</th>
            <th class="center" style="width:7%">Done ✓</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $rowIdx => $row)
            <tr class="{{ $row['is_serial'] && ! $row['first'] ? 'serial-cont' : ($rowIdx % 2 === 0 ? '' : 'stripe') }}">

                {{-- Sequence (shown only on first row of each task group) --}}
                <td class="seq-cell">
                    @if ($row['seq_show'] || $row['first'])
                        {{ $row['seq'] }}
                    @endif
                </td>

                {{-- Location (shown only on first row of each task group) --}}
                <td>
                    @if ($row['first'])
                        <div class="loc-code">{{ $row['location'] }}</div>
                    @endif
                </td>

                {{-- Item (shown only on first row) --}}
                <td>
                    @if ($row['first'])
                        <div class="item-sku">{{ $row['sku'] }}</div>
                        <div class="item-name">{{ $row['name'] }}</div>
                        @if ($row['is_serial'])
                            <div style="font-size:7px; color:#9ca3af; margin-top:1px;">
                                {{ $row['count'] }} serial unit{{ $row['count'] > 1 ? 's' : '' }}
                            </div>
                        @endif
                    @endif
                </td>

                {{-- Serial number --}}
                <td>
                    @if ($row['serial_no'])
                        <span class="serial-pill">{{ $row['serial_no'] }}</span>
                    @else
                        <span style="font-size:8px; color:#9ca3af;">—</span>
                    @endif
                </td>

                {{-- Batch (first row only) --}}
                <td>
                    @if ($row['first'] && $row['batch'] && $row['batch'] !== '—')
                        <span class="batch-txt">{{ $row['batch'] }}</span>
                    @elseif (! $row['is_serial'])
                        <span style="font-size:8px; color:#9ca3af;">—</span>
                    @endif
                </td>

                {{-- Qty --}}
                <td class="qty-cell">
                    @if (! $row['is_serial'])
                        {{ number_format($row['qty'], 2) }}
                    @else
                        1
                    @endif
                </td>

                {{-- UOM --}}
                <td class="uom-cell">{{ $row['uom'] }}</td>

                {{-- Checkbox --}}
                <td class="check-cell"><div class="check-box"></div></td>

            </tr>
        @empty
            <tr>
                <td colspan="8" style="text-align:center; color:#9ca3af; padding:16px;">No tasks found.</td>
            </tr>
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
