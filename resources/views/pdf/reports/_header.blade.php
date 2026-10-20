{{-- Shared report header partial --}}
<div class="report-header">
    <div class="header-left">
        <div class="brand">{{ $brandName }}</div>
        <div class="report-title">{{ $title }}</div>
    </div>
    <div class="header-right">
        @if (isset($from, $to))
            <div><strong>Period:</strong> {{ \Carbon\Carbon::parse($from)->format('d M Y') }} – {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</div>
        @endif
        @if (isset($asOf))
            <div><strong>As at:</strong> {{ $asOf }}</div>
        @endif
        <div><strong>Generated:</strong> {{ now()->format('d M Y H:i') }}</div>
    </div>
</div>
