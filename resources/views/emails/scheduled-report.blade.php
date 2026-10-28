<x-mail::message>
# Scheduled Report: {{ $scheduleName }}

Your scheduled **{{ $reportType }}** report is ready.

@if ($dateFrom && $dateTo)
**Period:** {{ $dateFrom }} → {{ $dateTo }}
@endif

**Format:** {{ $format }} &nbsp;·&nbsp; **Rows:** {{ number_format($rows ?? 0) }}

The report file is attached to this email.

<x-mail::subcopy>
Generated on {{ $generatedAt }}. This is an automated report — do not reply to this email.
</x-mail::subcopy>
</x-mail::message>
