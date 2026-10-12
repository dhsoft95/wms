@props([
    'label' => 'Actions',  {{-- aria-label on the trigger --}}
])

{{--
  Kebab (⋮) row-action trigger + floating menu.

  No x-teleport: the menu uses position:fixed with getBoundingClientRect()
  coordinates, so it renders at the correct viewport position without needing
  to be teleported. position:fixed is only broken by transform/filter/perspective
  on an ancestor — overflow:hidden/auto does NOT affect it.
  Keeping the panel inside the Livewire component's DOM means wire:click works.

  Usage:
    <x-ui.row-actions :label="'Actions for ' . $item->name">
        <x-ui.row-actions.item icon="eye"   label="View"   :href="route('items.show', $item)" />
        <x-ui.row-actions.item icon="pencil" label="Edit"  :href="route('items.edit', $item)" />
        <x-ui.row-actions.divider />
        <x-ui.row-actions.item icon="x" label="Deactivate" tone="danger" wire:click="deactivate({{ $item->id }})" />
    </x-ui.row-actions>
--}}
<div
    x-data="rowActionsMenu()"
    @keydown.escape.window="closeMenu()"
    class="flex justify-end"
>
    {{-- Trigger --}}
    <button
        x-ref="trigger"
        type="button"
        @click.stop="open ? closeMenu() : openMenu()"
        :aria-expanded="open.toString()"
        aria-haspopup="menu"
        aria-label="{{ $label }}"
        class="h-8 w-8 rounded-lg inline-flex items-center justify-center
               text-zinc-400 dark:text-zinc-500
               hover:bg-zinc-900/5 dark:hover:bg-white/10
               hover:text-zinc-700 dark:hover:text-zinc-200
               transition-colors duration-100"
        :class="open && 'ring-1 ring-simba-300 dark:ring-simba-700 bg-simba-50/60 dark:bg-simba-950/30 !text-simba-600 dark:!text-simba-400'"
    >
        <x-ui.icon name="ellipsis-vertical" class="h-4 w-4" />
    </button>

    {{-- Transparent overlay — click anywhere outside to close --}}
    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 z-[9998]"
        @click="closeMenu()"
        aria-hidden="true"
    ></div>

    {{-- Menu panel — position:fixed keeps it out of table overflow clipping --}}
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-[90ms]"
        x-transition:enter-start="opacity-0 scale-[0.96]"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-[70ms]"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-[0.96]"
        :style="`position:fixed;top:${menuY}px;left:${menuX}px;z-index:9999;${menuAbove ? 'transform:translateY(-100%)' : ''}`"
        :class="menuAbove ? 'origin-bottom-right' : 'origin-top-right'"
        role="menu"
        aria-orientation="vertical"
        @scroll.window="closeMenu()"
        @keydown.arrow-down.prevent="
            const items = [...$el.querySelectorAll('[role=menuitem]:not([aria-disabled=true])')];
            const idx   = items.indexOf(document.activeElement);
            items[(idx + 1) % items.length]?.focus();
        "
        @keydown.arrow-up.prevent="
            const items = [...$el.querySelectorAll('[role=menuitem]:not([aria-disabled=true])')];
            const idx   = items.indexOf(document.activeElement);
            items[(idx - 1 + items.length) % items.length]?.focus();
        "
        @keydown.tab="closeMenu()"
        class="w-52 min-w-[200px] rounded-xl p-1.5
               bg-white dark:bg-zinc-900
               shadow-xl shadow-black/[0.08] dark:shadow-black/50
               ring-1 ring-black/[0.06] dark:ring-white/[0.08]"
    >
        {{ $slot }}
    </div>
</div>
