@props(['value' => 0, 'neutral' => false])

@php
    $val = (int) $value;
    if ($neutral) {
        $barClass = 'bg-slate-400 dark:bg-slate-500';
    } elseif ($val >= 90) {
        $barClass = 'bg-red-500';
    } elseif ($val >= 75) {
        $barClass = 'bg-amber-500';
    } else {
        $barClass = 'bg-simba-600';
    }
@endphp

<div class="progress">
    <div class="{{ $barClass }}" style="width: {{ min($val, 100) }}%"></div>
</div>
