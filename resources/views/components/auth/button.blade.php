@props(['type' => 'submit', 'loadingText' => 'Please wait…'])
@php $target = $attributes->get('wire:target', ''); @endphp
<button
    type="{{ $type }}"
    {{ $attributes->except(['type', 'loadingText'])->merge(['class' =>
        'group relative w-full h-14 rounded-2xl font-semibold text-sm tracking-wide text-white
         inline-flex items-center justify-center whitespace-nowrap
         transition-all duration-200
         bg-gradient-to-b from-simba-500 via-simba-600 to-simba-700
         border border-simba-400/25
         shadow-[inset_0_1px_0_0_rgb(255_255_255_/_0.22),inset_0_-1px_0_0_rgb(0_0_0_/_0.18),0_4px_20px_0_rgb(162_38_49_/_0.45)]
         hover:brightness-110
         hover:shadow-[inset_0_1px_0_0_rgb(255_255_255_/_0.28),inset_0_-1px_0_0_rgb(0_0_0_/_0.22),0_8px_32px_0_rgb(162_38_49_/_0.60)]
         hover:-translate-y-0.5
         active:translate-y-0 active:brightness-95 active:shadow-none
         disabled:opacity-55 disabled:cursor-not-allowed disabled:transform-none disabled:brightness-100
         focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-simba-500/60
         motion-reduce:transition-none motion-reduce:hover:transform-none'
    ]) }}
>
    {{-- Subtle inner sheen overlay --}}
    <span class="pointer-events-none absolute inset-0 rounded-2xl bg-gradient-to-b from-white/10 to-transparent" aria-hidden="true"></span>

    <span class="relative inline-flex items-center justify-center gap-2 whitespace-nowrap">
        <svg
            @if($target) wire:loading wire:target="{{ $target }}" @else wire:loading @endif
            class="animate-spin w-4 h-4 shrink-0"
            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 5.373 22 12h-4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
        </svg>
        <span @if($target) wire:loading.remove wire:target="{{ $target }}" @else wire:loading.remove @endif>{{ $slot }}</span>
        <span @if($target) wire:loading wire:target="{{ $target }}" @else wire:loading @endif>{{ $loadingText }}</span>
    </span>
</button>
