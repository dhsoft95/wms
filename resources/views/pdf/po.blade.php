<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; margin: 0; padding: 20px; }
        .header { display: table; width: 100%; margin-bottom: 18px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header-left { display: table-cell; width: 60%; vertical-align: top; }
        .header-right { display: table-cell; width: 40%; text-align: right; vertical-align: top; }
        .brand-name { font-size: 18px; font-weight: bold; }
        .doc-title { font-size: 15px; font-weight: bold; color: #333; margin: 3px 0 2px; }
        .doc-sub { font-size: 10px; color: #666; }
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 9px; font-weight: bold; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .info-grid { display: table; width: 100%; margin-bottom: 14px; }
        .info-col { display: table-cell; width: 50%; vertical-align: top; padding-right: 12px; }
        .info-label { font-weight: bold; font-size: 9px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
        .info-value { font-size: 11px; margin-bottom: 8px; }
        .section-title { font-size: 10px; font-weight: bold; margin: 14px 0 6px; color: #444; border-bottom: 1px solid #ddd; padding-bottom: 3px; text-transform: uppercase; letter-spacing: 0.4px; }
        table.lines { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.lines th { background: #f3f4f6; border: 1px solid #d1d5db; padding: 5px 7px; font-size: 9px; text-align: left; font-weight: bold; color: #374151; text-transform: uppercase; }
        table.lines td { border: 1px solid #e5e7eb; padding: 5px 7px; font-size: 10px; vertical-align: top; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totals-row td { background: #f9fafb; font-weight: bold; border-top: 2px solid #d1d5db; }
        .grand-total td { background: #1e3a5f; color: #fff; font-weight: bold; }
        .approvals-table { width: 100%; border-collapse: collapse; }
        .approvals-table th { background: #f3f4f6; border: 1px solid #d1d5db; padding: 4px 7px; font-size: 9px; text-transform: uppercase; }
        .approvals-table td { border: 1px solid #e5e7eb; padding: 4px 7px; font-size: 10px; }
        .badge-approved { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 9px; font-weight: bold; background: #d1fae5; color: #065f46; }
        .badge-rejected { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 9px; font-weight: bold; background: #fee2e2; color: #991b1b; }
        .badge-pending  { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 9px; font-weight: bold; background: #fef3c7; color: #92400e; }
        .notes-box { border: 1px solid #e5e7eb; padding: 8px 10px; border-radius: 4px; font-size: 10px; color: #4b5563; background: #f9fafb; margin-bottom: 14px; }
        .signatures { display: table; width: 100%; margin-top: 40px; }
        .sig-col { display: table-cell; width: 33%; text-align: center; padding: 0 10px; }
        .sig-line { border-top: 1px solid #333; margin-top: 40px; padding-top: 5px; font-size: 10px; }
        .footer { margin-top: 20px; border-top: 1px solid #e5e7eb; padding-top: 8px; font-size: 9px; color: #9ca3af; text-align: center; }
        .highlight { color: #1d4ed8; font-weight: bold; }
    </style>
</head>
<body>

    {{-- Header --}}
    <div class="header">
        <div class="header-left">
            <div class="brand-name">{{ $brandName }}</div>
            <div class="doc-title">PURCHASE ORDER</div>
            <div class="doc-sub">Official Document — Do Not Alter</div>
        </div>
        <div class="header-right">
            <div style="font-size:13px; font-weight:bold; margin-bottom:4px;" class="highlight">{{ $po->number ?? '—' }}</div>
            @if ($po->customer_po_ref)
                <div><strong>Ref:</strong> {{ $po->customer_po_ref }}</div>
            @endif
            <div><strong>Date:</strong> {{ ($po->submitted_at ?? $po->created_at)->format('d M Y') }}</div>
            @if ($po->expected_date)
                <div><strong>Expected:</strong> {{ $po->expected_date->format('d M Y') }}</div>
            @endif
            <div style="margin-top:4px;"><span class="status-badge">{{ $po->status->label() }}</span></div>
        </div>
    </div>

    {{-- Parties --}}
    <div class="info-grid">
        <div class="info-col">
            <div class="info-label">Supplier (Bill From)</div>
            <div class="info-value">
                <strong>{{ $po->supplierPartner?->name ?? '—' }}</strong>
                @if ($po->supplierPartner?->email)<br>{{ $po->supplierPartner->email }}@endif
                @if ($po->supplierPartner?->phone)<br>{{ $po->supplierPartner->phone }}@endif
                @if ($po->supplierPartner?->address)<br>{{ $po->supplierPartner->address }}@endif
            </div>
            @if ($po->consigneePartner)
                <div class="info-label">Consignee</div>
                <div class="info-value">{{ $po->consigneePartner->name }}</div>
            @endif
        </div>
        <div class="info-col">
            <div class="info-label">Ship To / Warehouse</div>
            <div class="info-value">
                <strong>{{ $po->warehouse?->name ?? '—' }}</strong>
                @if ($po->warehouse?->address)<br>{{ $po->warehouse->address }}@endif
            </div>
            @if ($po->department)
                <div class="info-label">Department</div>
                <div class="info-value">{{ $po->department->name }}</div>
            @endif
            <div class="info-label">Requested By</div>
            <div class="info-value">{{ $po->requestedBy?->name ?? '—' }}</div>
            @if ($po->assignedSupervisor)
                <div class="info-label">Supervisor</div>
                <div class="info-value">{{ $po->assignedSupervisor->name }}</div>
            @endif
            @if ($po->assignedClerk)
                <div class="info-label">Receiving Clerk</div>
                <div class="info-value">{{ $po->assignedClerk->name }}</div>
            @endif
        </div>
    </div>

    {{-- Line Items --}}
    <div class="section-title">Order Lines</div>
    @php
        $grandTotal = '0';
        $currency   = $po->currency ?? ($po->lines->first()?->currency ?? 'USD');
    @endphp
    <table class="lines">
        <thead>
            <tr>
                <th class="text-center" style="width:28px;">#</th>
                <th>Item Code</th>
                <th>Description</th>
                <th class="text-right" style="width:60px;">Qty</th>
                <th style="width:40px;">UoM</th>
                <th class="text-right" style="width:70px;">Unit Price</th>
                <th class="text-right" style="width:70px;">Line Total</th>
                @if ($po->lines->contains(fn($l) => $l->batch_no))
                    <th>Batch</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($po->lines->sortBy('line_no') as $i => $line)
                @php
                    $lineTotal   = (float) ($line->line_total ?? ((float)$line->qty * (float)$line->unit_price));
                    $grandTotal  = bcadd($grandTotal, (string) $lineTotal, 4);
                @endphp
                <tr>
                    <td class="text-center">{{ $line->line_no ?? ($i + 1) }}</td>
                    <td style="font-family: monospace; font-size:9px;">{{ $line->item?->sku ?? $line->part_number ?? '—' }}</td>
                    <td>
                        {{ $line->item?->name ?? '—' }}
                        @if ($line->notes)
                            <div style="font-size:9px; color:#6b7280; margin-top:2px;">{{ $line->notes }}</div>
                        @endif
                    </td>
                    <td class="text-right" style="font-family: monospace;">{{ number_format((float) $line->qty, 2) }}</td>
                    <td>{{ $line->uom?->code ?? '—' }}</td>
                    <td class="text-right" style="font-family: monospace;">
                        @if ($line->unit_price > 0)
                            {{ number_format((float) $line->unit_price, 2) }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right" style="font-family: monospace;">
                        @if ($lineTotal > 0)
                            {{ number_format($lineTotal, 2) }}
                        @else
                            —
                        @endif
                    </td>
                    @if ($po->lines->contains(fn($l) => $l->batch_no))
                        <td style="font-size:9px;">{{ $line->batch_no ?? '—' }}</td>
                    @endif
                </tr>
            @endforeach
            <tr class="totals-row">
                <td colspan="{{ $po->lines->contains(fn($l) => $l->batch_no) ? 6 : 5 }}" class="text-right">Total</td>
                <td class="text-right" style="font-family: monospace;">
                    {{ $currency }} {{ number_format((float) $grandTotal, 2) }}
                </td>
                @if ($po->lines->contains(fn($l) => $l->batch_no))
                    <td></td>
                @endif
            </tr>
        </tbody>
    </table>

    {{-- Notes --}}
    @if ($po->notes)
        <div class="section-title">Notes / Terms</div>
        <div class="notes-box">{{ $po->notes }}</div>
    @endif

    {{-- Special Treatment --}}
    @if ($po->is_special_treatment && $po->special_treatment_reason)
        <div class="section-title">Special Treatment Required</div>
        <div class="notes-box" style="border-color:#fbbf24; background:#fffbeb;">{{ $po->special_treatment_reason }}</div>
    @endif

    {{-- Approval History --}}
    @if ($po->approvals->isNotEmpty())
        <div class="section-title">Approval History</div>
        <table class="approvals-table">
            <thead>
                <tr>
                    <th>Level</th>
                    <th>Approver</th>
                    <th>Decision</th>
                    <th>Date</th>
                    <th>Comment</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($po->approvals->sortBy('level') as $approval)
                    @php
                        $action = $approval->action?->value ?? '';
                        $badgeClass = match(true) {
                            str_contains($action, 'approved') || str_contains($action, 'accepted') => 'badge-approved',
                            str_contains($action, 'rejected') => 'badge-rejected',
                            default => 'badge-pending',
                        };
                    @endphp
                    <tr>
                        <td class="text-center">{{ $approval->level }}</td>
                        <td>{{ $approval->user?->name ?? '—' }}</td>
                        <td><span class="{{ $badgeClass }}">{{ ucfirst(str_replace('_', ' ', $action)) }}</span></td>
                        <td>{{ $approval->updated_at?->format('d M Y') ?? '—' }}</td>
                        <td style="font-size:9px; color:#4b5563;">{{ $approval->comment ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Signatures --}}
    <div class="signatures">
        <div class="sig-col">
            <div class="sig-line">
                Prepared By<br>
                {{ $po->requestedBy?->name ?? '' }}
            </div>
        </div>
        <div class="sig-col">
            <div class="sig-line">
                Approved By<br>
                {{ $po->assignedSupervisor?->name ?? '' }}
            </div>
        </div>
        <div class="sig-col">
            <div class="sig-line">
                Supplier Acknowledgement<br>
                {{ $po->supplierPartner?->name ?? '' }}
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        {{ $brandName }} &bull; {{ $po->number }} &bull; Generated {{ now()->format('d M Y H:i') }}
    </div>
</body>
</html>
