<div @if(!$show) style="display:none" @endif data-vw-count="{{ $warehouses->count() }}" class="relative" x-data="{ open: false }" @click.outside="open = false">
    <button
        @click="open = !open"
        class="flex items-center gap-1.5 glass-input dark:glass-input-dark px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-200 transition"
        title="Switch virtual warehouse"
    >
        <x-ui.icon name="home" class="w-3.5 h-3.5 text-simba-600 shrink-0" />
        <span class="max-w-[140px] truncate">
            @if ($selected === 0)
                All my warehouses
            @else
                {{ $warehouses->firstWhere('id', $selected)?->name ?? 'All my warehouses' }}
            @endif
        </span>
        <x-ui.icon name="chevron-down" class="w-3 h-3 text-slate-400 shrink-0" />
    </button>

    <div
        x-show="open"
        x-transition
        @click="open = false"
        class="absolute left-0 mt-1.5 min-w-48 glass-solid dark:glass-solid-dark rounded-xl p-1 z-50 shadow-lg"
        style="display:none"
    >
        <button
            wire:click="select(0)"
            @class(['dropdown-item text-xs w-full text-left', 'text-simba-700 dark:text-simba-300 font-semibold' => $selected === 0])
        >
            All my warehouses
        </button>
        @foreach ($warehouses as $wh)
            <button
                wire:click="select({{ $wh->id }})"
                @class(['dropdown-item text-xs w-full text-left', 'text-simba-700 dark:text-simba-300 font-semibold' => $selected === $wh->id])
            >
                <span class="flex-1 truncate">{{ $wh->name }}</span>
                <span class="ml-auto mono text-[10px] text-slate-400 shrink-0">{{ $wh->code }}</span>
            </button>
        @endforeach
    </div>
</div>
