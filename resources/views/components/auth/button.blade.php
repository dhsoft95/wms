@props(['type' => 'submit', 'loadingText' => 'Please wait…'])
@php $target = $attributes->get('wire:target', ''); @endphp
<button
    type="{{ $type }}"
    {{ $attributes->except(['type', 'loadingText'])->merge(['class' =>
        'relative w-full h-14 rounded-xl font-semibold text-sm text-white transition-all duration-200
         bg-gradient-to-r from-simba-700 to-simba-600
         shadow-[0_4px_20px_0_rgb(162_38_49_/_0.4),inset_0_1px_0_0_rgb(255_255_255_/_0.2)]
         hover:shadow-[0_6px_28px_0_rgb(162_38_49_/_0.55),inset_0_1px_0_0_rgb(255_255_255_/_0.25)]
         hover:-translate-y-0.5
         active:translate-y-0 active:shadow-none
         disabled:opacity-60 disabled:cursor-not-allowed disabled:transform-none
         focus:outline-none focus:ring-2 focus:ring-simba-600/50
         motion-reduce:transition-none motion-reduce:hover:transform-none'
    ]) }}
>
    <span class="flex items-center justify-center gap-2">
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
