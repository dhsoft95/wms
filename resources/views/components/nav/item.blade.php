@props(['item'])

<a
    href="{{ $item['url'] }}"
    wire:navigate
    @class(['sidebar-link', 'active' => $item['is_active']])
    x-bind:title="sidebarRail ? '{{ addslashes($item['label']) }}' : null"
>
    <x-ui.icon
        :name="$item['icon']"
        @class(['w-[18px] h-[18px] shrink-0', 'opacity-100' => $item['is_active'], 'opacity-70' => !$item['is_active']])
    />
    <span class="flex-1 truncate" x-show="!sidebarRail" x-cloak>{{ $item['label'] }}</span>
    @if(!empty($item['badge']))
        <span x-show="!sidebarRail" x-cloak>
            <x-nav.badge :count="$item['badge']" />
        </span>
    @endif
</a>
