@props(['count' => null])

@if($count)
<span class="min-w-[20px] px-1.5 py-0.5 rounded-full bg-simba-500/15 text-simba-700 dark:text-simba-300 text-[10px] font-bold leading-[16px] text-center tabular-nums">
    {{ $count > 99 ? '99+' : $count }}
</span>
@endif
