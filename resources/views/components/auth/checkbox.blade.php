@props(['label' => ''])
<label class="flex items-center gap-2.5 cursor-pointer select-none group">
    <input
        type="checkbox"
        {{ $attributes->merge(['class' => 'w-4 h-4 rounded border-white/70 bg-white/50 text-simba-600 focus:ring-simba-600/40 focus:ring-2 cursor-pointer transition']) }}
        @if($label) aria-label="{{ $label }}" @endif
    >
    @if ($label)
        <span class="text-sm text-slate-600 group-hover:text-slate-800 transition-colors">{{ $label }}</span>
    @endif
</label>
