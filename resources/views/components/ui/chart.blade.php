@props([
    'type'       => 'bar',
    'series'     => [],
    'categories' => [],
    'height'     => 280,
    'chartId'    => null,
    'horizontal' => false,
    'stacked'    => false,
    'unit'       => '',
])

@php
    $resolvedId = $chartId ?? 'chart-' . md5(uniqid('', true));

    $isEmpty = empty($series) || collect($series)->every(function ($s) {
        $data = is_array($s) && isset($s['data']) ? $s['data'] : (is_array($s) ? $s : []);
        return empty($data) || collect($data)->every(fn($v) => (float)$v === 0.0);
    });
@endphp

<div
    x-data="apexChart({{ Js::from([
        'type'       => $type,
        'series'     => $series,
        'categories' => $categories,
        'height'     => (int) $height,
        'horizontal' => (bool) $horizontal,
        'stacked'    => (bool) $stacked,
        'unit'       => $unit,
        'isEmpty'    => $isEmpty,
        'chartId'    => $resolvedId,
    ]) }})"
    x-init="init()"
    x-on:livewire:navigated.window="destroy()"
    wire:ignore
    class="w-full"
>
    @if ($isEmpty)
        <div class="flex items-center justify-center text-slate-400 dark:text-slate-600 text-sm italic"
             style="height: {{ $height }}px;">
            No data available
        </div>
    @else
        <div style="position:relative; height:{{ $height }}px;">
            <canvas id="{{ $resolvedId }}"></canvas>
        </div>
    @endif
</div>
