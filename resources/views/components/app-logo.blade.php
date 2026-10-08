@php $initials = strtoupper(substr(config('app.name', 'WMS'), 0, 2)); @endphp
<div class="flex aspect-square size-8 items-center justify-center rounded-md bg-simba-600 shrink-0">
    <span class="text-xs font-bold text-white">{{ $initials }}</span>
</div>
<div class="ml-1 grid flex-1 text-left text-sm">
    <span class="mb-0.5 truncate leading-none font-semibold">{{ config('app.name') }}</span>
</div>
