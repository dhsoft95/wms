@props(['item'])

<a
    href="{{ $item['url'] }}"
    wire:navigate
    class="sidebar-link"
    :class="{ 'active': $root.isNavLinkActive('{{ parse_url($item['url'], PHP_URL_PATH) }}') }"
    x-bind:title="sidebarRail ? '{{ addslashes($item['label']) }}' : null"
>
    <x-ui.icon
        :name="$item['icon']"
        class="w-[18px] h-[18px] shrink-0"
        x-bind:class="$root.isNavLinkActive('{{ parse_url($item['url'], PHP_URL_PATH) }}') ? 'opacity-100' : 'opacity-70'"
    />
    <span class="flex-1 truncate" x-show="!sidebarRail" x-cloak>{{ $item['label'] }}</span>
    @if(!empty($item['badge']))
        <span x-show="!sidebarRail" x-cloak>
            <x-nav.badge :count="$item['badge']" />
        </span>
    @endif
</a>
