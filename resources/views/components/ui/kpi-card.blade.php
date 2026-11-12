@props([
    'label'   => '',
    'value'   => '',
    'hint'    => null,
    'icon'    => null,
    'tone'    => 'brand',
    'href'    => null,
    'spark'   => null,
    'trend'   => null,
    'loading' => false,
])

@php
    $sparkPoints = '';
    $dotLeft     = null;
    $dotTop      = null;

    if (! $loading && is_array($spark) && count($spark) >= 2) {
        $vals = array_map('floatval', array_values($spark));

        if (array_sum($vals) > 0) {
            $min   = min($vals);
            $max   = max($vals);
            $range = $max - $min;
            $n     = count($vals);
            $pts   = [];

            foreach ($vals as $idx => $v) {
                $x = $n > 1 ? round(($idx / ($n - 1)) * 100, 2) : 50;
                // Flat non-zero: centre at mid-height instead of collapsing to baseline
                $y = $range > 0
                    ? round(28 - (($v - $min) / $range) * 22 - 3, 2)
                    : 14;
                $pts[] = "{$x},{$y}";
            }

            $sparkPoints = implode(' ', $pts);
            $last        = explode(',', $pts[$n - 1]);
            $dotLeft     = (float) $last[0];
            $dotTop      = round(((float) $last[1] / 28) * 100, 2);
        }
    }

    $hasTrend  = is_array($trend) && isset($trend['direction']);
    $direction = $hasTrend ? $trend['direction'] : 'flat';
    $percent   = $hasTrend ? $trend['percent']   : 0;

    $classes = 'kpi-card kpi-card--' . $tone
        . ($href    ? ' kpi-card--link' : '')
        . ($loading ? ' is-loading'     : '');

    $ariaLabel = $label . ' KPI' . ($value !== '' ? ": {$value}" : '');
@endphp

@if($href)
    <a href="{{ $href }}" wire:navigate
       class="{{ $classes }}"
       aria-label="{{ $ariaLabel }}">
@else
    <div class="{{ $classes }}"
         role="group"
         aria-label="{{ $ariaLabel }}">
@endif

        {{-- Background icon watermark --}}
        @if($icon)
        <div class="kpi-card__bg-icon" aria-hidden="true">
            <x-ui.icon :name="$icon" class="w-full h-full" />
        </div>
        @endif

        {{-- Header --}}
        <div class="kpi-card__head">
            <span class="kpi-card__label">{{ $label }}</span>
        </div>

        {{-- Body --}}
        <div class="kpi-card__body">
            @if($loading)
                <div class="kpi-card__main">
                    <div class="kpi-skeleton kpi-skeleton--value"></div>
                    <div class="kpi-skeleton kpi-skeleton--hint" style="margin-top:.4rem"></div>
                </div>
            @else
                <div class="kpi-card__main">
                    <div class="kpi-card__value">{{ $value }}</div>
                    @if($hint)
                        <div class="kpi-card__hint">{{ $hint }}</div>
                    @endif
                </div>
                @if($hasTrend)
                    <div class="kpi-trend kpi-trend--{{ $direction }}" aria-hidden="true">
                        @if($direction === 'up')
                            <svg class="kpi-trend__icon" viewBox="0 0 12 12"><polyline points="2,9 6,3 10,9"/></svg>
                        @elseif($direction === 'down')
                            <svg class="kpi-trend__icon" viewBox="0 0 12 12"><polyline points="2,3 6,9 10,3"/></svg>
                        @else
                            <svg class="kpi-trend__icon" viewBox="0 0 12 12"><line x1="2" y1="6" x2="10" y2="6"/></svg>
                        @endif
                        {{ $percent }}%
                    </div>
                    <span class="sr-only">{{ $percent }}% {{ $direction }}</span>
                @else
                    <div class="kpi-spark-placeholder" aria-hidden="true"></div>
                @endif
            @endif
        </div>

        {{-- Sparkline --}}
        @if($sparkPoints)
        <div class="kpi-spark">
            <svg viewBox="0 0 100 28"
                 preserveAspectRatio="none"
                 role="img"
                 aria-label="Trend sparkline for {{ $label }}">
                <polyline class="kpi-spark__line" points="{{ $sparkPoints }}"/>
            </svg>
            <div class="kpi-spark__dot" style="left:{{ $dotLeft }}%;top:{{ $dotTop }}%"></div>
        </div>
        @endif

        {{-- Bottom bar --}}
        <div class="kpi-card__bar"></div>

@if($href)
    </a>
@else
    </div>
@endif
