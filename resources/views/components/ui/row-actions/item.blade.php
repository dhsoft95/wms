@props([
    'icon'     => null,
    'label'    => '',
    'tone'     => 'default', {{-- default | info | success | warning | danger --}}
    'href'     => null,
    'disabled' => false,
    'hint'     => null,      {{-- tooltip shown when disabled --}}
])

@php
$toneClass = match ($tone) {
    'info'    => 'text-blue-600   dark:text-blue-400',
    'success' => 'text-green-600  dark:text-green-400',
    'warning' => 'text-amber-600  dark:text-amber-400',
    'danger'  => 'text-red-600    dark:text-red-400',
    default   => 'text-zinc-800   dark:text-zinc-100',
};

$base = 'flex w-full items-center gap-3 rounded-lg px-3 h-10 text-sm font-medium
         hover:bg-zinc-900/5 dark:hover:bg-white/[0.05]
         transition-colors duration-75 text-left';
@endphp

@if ($disabled)
    {{-- Non-interactive disabled state --}}
    <div
        role="menuitem"
        aria-disabled="true"
        @if ($hint) title="{{ $hint }}" @endif
        class="{{ $base }} {{ $toneClass }} opacity-40 cursor-not-allowed pointer-events-none"
    >
        @if ($icon)
            <x-ui.icon :name="$icon" class="h-[18px] w-[18px] shrink-0" />
        @endif
        <span>{{ $label }}</span>
    </div>

@elseif ($href)
    {{-- Navigation link --}}
    <a
        href="{{ $href }}"
        role="menuitem"
        @click="closeMenu()"
        {{ $attributes->except(['class', 'href']) }}
        class="{{ $base }} {{ $toneClass }}"
    >
        @if ($icon)
            <x-ui.icon :name="$icon" class="h-[18px] w-[18px] shrink-0" />
        @endif
        <span>{{ $label }}</span>
    </a>

@else
    {{-- Action button — wire:click and other Livewire attrs pass through via $attributes --}}
    <button
        type="button"
        role="menuitem"
        @click="closeMenu()"
        {{ $attributes->except('class') }}
        class="{{ $base }} {{ $toneClass }}"
    >
        @if ($icon)
            <x-ui.icon :name="$icon" class="h-[18px] w-[18px] shrink-0" />
        @endif
        <span>{{ $label }}</span>
    </button>
@endif
