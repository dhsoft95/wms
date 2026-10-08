@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
@php
    $logoPath   = config('wms.branding.logo');
    $logoPng    = public_path('images/logo/logo.png');
    $logoSvg    = public_path('images/logo.svg');
@endphp
@if ($logoPath)
    <img src="{{ asset($logoPath) }}" alt="{{ config('app.name') }}" style="height: 40px; width: auto; display: block; margin: 0 auto;">
@elseif (file_exists($logoPng))
    <img src="{{ asset('images/logo/logo.png') }}" alt="{{ config('app.name') }}" style="height: 40px; width: auto; display: block; margin: 0 auto;">
@elseif (file_exists($logoSvg))
    <img src="{{ asset('images/logo.svg') }}" alt="{{ config('app.name') }}" style="height: 40px; width: auto; display: block; margin: 0 auto;">
@else
    <table cellpadding="0" cellspacing="0" border="0" style="margin: 0 auto;">
        <tr>
            <td style="background-color: #a22631; border-radius: 8px; width: 36px; height: 36px; text-align: center; vertical-align: middle; padding: 0;">
                <span style="color: #ffffff; font-family: 'Sora', -apple-system, sans-serif; font-size: 13px; font-weight: 600; line-height: 36px; display: block;">{{ strtoupper(substr(config('app.name', 'W'), 0, 2)) }}</span>
            </td>
            <td style="padding-left: 10px; vertical-align: middle;">
                <span style="color: #18181b; font-family: 'Sora', -apple-system, sans-serif; font-size: 18px; font-weight: 700;">{{ config('app.name') }}</span>
            </td>
        </tr>
    </table>
@endif
</a>
</td>
</tr>
