@props(['label' => '', 'icon' => null])
@php
    $fieldName  = $attributes->get('wire:model') ?: $attributes->get('name', '');
    $hasError   = $fieldName && $errors->has($fieldName);
    $inputClass = 'glass-input rounded-xl h-14 w-full text-sm'
        . ' text-zinc-900 dark:text-white'
        . ' placeholder:text-zinc-400 dark:placeholder:text-zinc-500'
        . ' focus:outline-none focus:ring-2 focus:ring-simba-600/40 focus:border-simba-600 transition'
        . ($icon ? ' pl-12 pr-4' : ' px-4')
        . ($hasError ? ' ring-2 ring-red-400/50 !border-red-400/50' : '');
    $inputId    = $attributes->get('id', $fieldName ?: Str::random(8));
@endphp
<div class="space-y-1.5">
    @if ($label)
        <label class="block text-xs font-semibold text-zinc-800 dark:text-zinc-100" for="{{ $inputId }}">{{ $label }}</label>
    @endif
    <div class="relative">
        <input
            id="{{ $inputId }}"
            {{ $attributes->except(['label', 'icon'])->merge(['class' => $inputClass]) }}
        >
        @if ($icon)
            <div class="absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none text-[color:var(--auth-icon)]" aria-hidden="true">
                @if ($icon === 'mail')
                    <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                @elseif ($icon === 'lock')
                    <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                @elseif ($icon === 'user')
                    <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                @elseif ($icon === 'key')
                    <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg>
                @endif
            </div>
        @endif
    </div>
    @if ($fieldName)
        @error($fieldName)
            <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
        @enderror
    @endif
</div>
