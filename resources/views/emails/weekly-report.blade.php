<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: Arial, sans-serif; font-size: 14px; color: #1a1a1a; background: #f5f5f5; margin: 0; padding: 0; }
  .wrapper { max-width: 640px; margin: 24px auto; background: #fff; border-radius: 8px; overflow: hidden; }
  .header { background: #a22631; color: #fff; padding: 24px 28px; }
  .header h1 { margin: 0 0 4px; font-size: 20px; }
  .header p { margin: 0; font-size: 13px; opacity: 0.85; }
  .body { padding: 24px 28px; }
  .section { margin-bottom: 24px; }
  .section h2 { font-size: 14px; font-weight: 700; color: #a22631; border-bottom: 1px solid #e5e7eb; padding-bottom: 6px; margin: 0 0 10px; }
  .summary { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px 16px; font-size: 13px; line-height: 1.7; white-space: pre-line; }
  .summary.empty { color: #9ca3af; font-style: italic; }
  .footer { padding: 16px 28px; background: #f9fafb; border-top: 1px solid #e5e7eb; font-size: 11px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>Weekly Warehouse Report</h1>
    <p>{{ $department->name }} &mdash; Week commencing {{ \Carbon\Carbon::parse($weekStart)->format('d M Y') }}</p>
  </div>
  <div class="body">

    @php
      $labels = [
        'stock-summary'      => 'Stock Summary',
        'inbound'            => 'Inbound',
        'outbound'           => 'Outbound',
        'expiry-ageing'      => 'Expiry & Ageing',
        'yard'               => 'Yard',
        'adjustments-counts' => 'Adjustments & Counts',
      ];
    @endphp

    @foreach ($labels as $key => $label)
    <div class="section">
      <h2>{{ $label }}</h2>
      @if (!empty($summaries[$key]))
        <div class="summary">{{ $summaries[$key] }}</div>
      @else
        <div class="summary empty">Summary unavailable.</div>
      @endif
    </div>
    @endforeach

  </div>
  <div class="footer">
    AI-generated summary &mdash; check figures in the system &mdash; {{ now()->format('d M Y H:i') }}
  </div>
</div>
</body>
</html>
