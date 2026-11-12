@props([
    'variant' => 'primary',
    'href'    => null,
])

@php
$base = implode(' ', [
    'relative flex w-full items-center justify-center gap-3',
    'min-h-14 rounded-2xl px-5',
    'text-base font-semibold leading-none',
    'transition active:scale-[0.97]',
    'focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
    'disabled:pointer-events-none disabled:opacity-50 disabled:active:scale-100',
]);

$styles = [
    'primary' => implode(' ', [
        'bg-simba-600 text-white',
        'shadow-lg shadow-simba-900/25',
        'hover:bg-simba-700',
        'focus-visible:ring-simba-600',
        'dark:hover:bg-simba-500',
    ]),
    'secondary' => implode(' ', [
        'bg-white dark:bg-slate-800',
        'text-slate-800 dark:text-slate-100',
        'border border-slate-200 dark:border-slate-700',
        'hover:bg-slate-50 dark:hover:bg-slate-700/60',
        'focus-visible:ring-slate-400',
    ]),
];

$cls = $base . ' ' . ($styles[$variant] ?? $styles['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" wire:navigate {{ $attributes->merge(['class' => $cls]) }}>
        {{ $slot }}
    </a>
@else
    <button type="button" {{ $attributes->merge(['class' => $cls]) }}>
        {{ $slot }}
    </button>
@endif
