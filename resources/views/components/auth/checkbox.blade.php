@props(['label' => ''])
<label class="flex items-center gap-2.5 cursor-pointer select-none group">
    <input
        type="checkbox"
        {{ $attributes->merge(['class' => 'w-4 h-4 rounded border-zinc-300 dark:border-white/30 bg-white/50 dark:bg-zinc-900/60 text-simba-600 focus:ring-simba-600/40 focus:ring-2 cursor-pointer transition']) }}
        @if($label) aria-label="{{ $label }}" @endif
    >
    @if ($label)
        <span class="text-sm text-zinc-700 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-zinc-100 transition-colors">{{ $label }}</span>
    @endif
</label>
