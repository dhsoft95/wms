@props(['options' => [], 'selected' => ''])
<div class="glass-input rounded-xl p-1 flex gap-1" role="tablist"
     x-data="{ selected: @js($selected) }"
     @method-change.window="selected = $event.detail.value">
    @foreach ($options as $option)
        <button
            type="button"
            role="tab"
            :aria-selected="selected === @js($option['value'])"
            @click="selected = @js($option['value']); $dispatch('method-change', { value: @js($option['value']) })"
            :class="selected === @js($option['value']) ? 'glass shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-700'"
            class="flex-1 rounded-lg px-3 py-2 text-xs font-semibold transition-all duration-150"
        >
            {{ $option['label'] }}
        </button>
    @endforeach
</div>
