@props(['group'])

@php $key = $group['key'] @endphp

<div>
    {{-- Group label button (expanded mode only) --}}
    <button
        type="button"
        x-show="!sidebarRail"
        x-cloak
        @click="toggleGroup('{{ $key }}')"
        class="flex items-center justify-between w-full px-3 mb-1.5 group/gl min-h-[22px]"
    >
        <span class="text-[11px] leading-[16px] font-medium uppercase tracking-[0.08em] text-slate-400 dark:text-slate-500 group-hover/gl:text-slate-600 dark:group-hover/gl:text-slate-300 transition-colors">
            {{ $group['label'] }}
        </span>
        <x-ui.icon
            name="chevron-down"
            class="w-3 h-3 text-slate-300 dark:text-slate-600 transition-transform duration-200"
            ::class="isGroupOpen('{{ $key }}') ? '' : '-rotate-90'"
        />
    </button>

    {{-- Rail-mode group divider --}}
    <div x-show="sidebarRail" class="h-px mx-3 mb-2 bg-white/30 dark:bg-white/[0.06]"></div>

    {{-- Items — always visible in rail mode, collapsible in expanded mode --}}
    <div
        x-show="sidebarRail || isGroupOpen('{{ $key }}')"
        x-collapse.duration.200ms
        class="space-y-0.5"
    >
        @foreach ($group['items'] as $item)
            <x-nav.item :item="$item" />
        @endforeach
    </div>
</div>
