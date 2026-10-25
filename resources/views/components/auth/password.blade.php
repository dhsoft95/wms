@props(['label' => 'Password'])
@php
    $fieldName  = $attributes->get('wire:model') ?: $attributes->get('name', '');
    $hasError   = $fieldName && $errors->has($fieldName);
    $inputId    = $attributes->get('id', ($fieldName ?: 'pw') . '_' . Str::random(4));
    $inputClass = 'rounded-xl h-14 w-full pl-12 pr-12 text-sm'
        . ' bg-white/70 dark:bg-slate-700/50'
        . ' border border-white/80 dark:border-slate-600/60'
        . ' backdrop-blur-sm'
        . ' text-zinc-900 dark:text-white'
        . ' placeholder:text-zinc-400 dark:placeholder:text-zinc-500'
        . ' focus:outline-none focus:ring-2 focus:ring-simba-600/40 focus:border-simba-600 transition'
        . ($hasError ? ' !ring-2 !ring-red-400/50 !border-red-400/50' : '');
@endphp
<div class="space-y-1.5" x-data="{ show: false }">
    @if ($label)
        <label class="block text-xs font-semibold text-zinc-800 dark:text-zinc-100" for="{{ $inputId }}">{{ $label }}</label>
    @endif
    <div class="relative">
        <input
            id="{{ $inputId }}"
            :type="show ? 'text' : 'password'"
            {{ $attributes->except(['label', 'type'])->merge(['class' => $inputClass]) }}
        >
        <div class="absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none text-[color:var(--auth-icon)]" aria-hidden="true">
            <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
            </svg>
        </div>
        <button
            type="button"
            @click="show = !show"
            class="absolute right-3.5 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-100 transition-colors"
            :aria-label="show ? 'Hide password' : 'Show password'"
        >
            <svg x-show="!show" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <svg x-show="show" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-cloak>
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
            </svg>
        </button>
    </div>
    @if ($fieldName)
        @error($fieldName)
            <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
        @enderror
    @endif
</div>
