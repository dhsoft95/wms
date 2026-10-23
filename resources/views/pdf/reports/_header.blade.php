@php
    $brand = \App\Services\Foundation\SettingsService::getCompanyBrand();
@endphp
<style>
    .company-logo   { max-height: 40px; max-width: 140px; margin-bottom: 4px; display: block; }
    .company-address{ font-size: 8px; color: #6b7280; margin-top: 1px; }
</style>
{{-- Shared report header partial --}}
<div class="report-header">
    <div class="header-left">
        @if ($brand['logoDataUri'])
            <img src="{{ $brand['logoDataUri'] }}" class="company-logo" alt="">
        @endif
        <div class="brand">{{ $brand['name'] }}</div>
        @if ($brand['address'])
            <div class="company-address">{{ $brand['address'] }}</div>
        @endif
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
