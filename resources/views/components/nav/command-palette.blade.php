@props(['items'])

{{--
    $items: flat array of ['label', 'url', 'group'] — built in sidebar layout.
    Opens when commandOpen === true (set in parent x-data on <html>).
--}}
<div
    x-show="commandOpen"
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-100"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-[200] flex items-start justify-center px-4 pt-[14vh]"
    @click.self="commandOpen = false"
    @keydown.escape.window="commandOpen = false"
    style="display:none"
>
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-slate-950/40 backdrop-blur-sm"></div>

    {{-- Palette --}}
    <div
        x-data="{
            search: '',
            selected: 0,
            allItems: @js($items),
            get results() {
                if (!this.search.trim()) return this.allItems;
                const q = this.search.toLowerCase();
                return this.allItems.filter(i =>
                    i.label.toLowerCase().includes(q) || i.group.toLowerCase().includes(q)
                );
            },
            navigate() {
                const item = this.results[this.selected];
                if (item) { window.location.href = item.url; commandOpen = false; }
            },
        }"
        @keydown.up.prevent="selected = Math.max(0, selected - 1)"
        @keydown.down.prevent="selected = Math.min(results.length - 1, selected + 1)"
        @keydown.enter.prevent="navigate()"
        x-init="$watch('results', () => selected = 0)"
        class="relative w-full max-w-xl glass-solid dark:glass-solid-dark rounded-2xl overflow-hidden shadow-2xl"
    >
        {{-- Search bar --}}
        <div class="flex items-center gap-3 px-4 py-3.5"
            style="border-bottom: 1px solid rgb(255 255 255 / 0.4);"
            :style="darkMode ? 'border-bottom-color: rgb(255 255 255 / 0.08)' : ''">
            <x-ui.icon name="search" class="w-[18px] h-[18px] text-slate-400 shrink-0" />
            <input
                type="text"
                x-model="search"
                x-init="$nextTick(() => { if (commandOpen) $el.focus() })"
                @keydown.window="if(commandOpen && event.key.length === 1 && !event.ctrlKey && !event.metaKey) $el.focus()"
                placeholder="Search pages…"
                class="flex-1 bg-transparent text-[14px] leading-[20px] text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:outline-none"
            />
            <kbd class="hidden sm:inline-flex h-5 items-center gap-0.5 px-1.5 rounded text-[10px] font-mono text-slate-400 bg-white/50 dark:bg-white/[0.08] border border-white/50 dark:border-white/[0.1]">
                Esc
            </kbd>
        </div>

        {{-- Results list --}}
        <div class="max-h-[55vh] overflow-y-auto py-1.5">
            <template x-for="(item, index) in results" :key="item.url">
                <a
                    :href="item.url"
                    @click="commandOpen = false"
                    :class="selected === index ? 'bg-white/40 dark:bg-white/[0.08]' : ''"
                    @mouseenter="selected = index"
                    class="flex items-center gap-3 px-4 py-2.5 transition-colors"
                >
                    <div class="min-w-0 flex-1">
                        <p class="text-[14px] leading-[20px] font-medium text-slate-900 dark:text-slate-100 truncate"
                            x-text="item.label"></p>
                        <p class="text-[11px] leading-[16px] text-slate-400 dark:text-slate-500 truncate"
                            x-text="item.group"></p>
                    </div>
                    <svg class="w-4 h-4 text-slate-300 dark:text-slate-600 shrink-0 transition-colors"
                        :class="selected === index ? 'text-slate-500 dark:text-slate-400' : ''"
                        fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </template>

            <p
                x-show="results.length === 0"
                class="px-4 py-10 text-center text-[14px] text-slate-400 dark:text-slate-500"
            >
                No pages match "<span x-text="search" class="text-slate-600 dark:text-slate-300"></span>"
            </p>
        </div>

        {{-- Footer --}}
        <div class="flex items-center gap-4 px-4 py-2 bg-white/20 dark:bg-white/[0.02]"
            style="border-top: 1px solid rgb(255 255 255 / 0.4);"
            :style="darkMode ? 'border-top-color: rgb(255 255 255 / 0.08)' : ''">
            <span class="flex items-center gap-1 text-[11px] text-slate-400 dark:text-slate-500">
                <kbd class="inline-flex h-4 items-center px-1 rounded text-[10px] bg-white/50 dark:bg-white/[0.08]">↑↓</kbd>
                navigate
            </span>
            <span class="flex items-center gap-1 text-[11px] text-slate-400 dark:text-slate-500">
                <kbd class="inline-flex h-4 items-center px-1 rounded text-[10px] bg-white/50 dark:bg-white/[0.08]">↵</kbd>
                open
            </span>
            <span class="ml-auto text-[11px] text-slate-400 dark:text-slate-500">
                <x-ui.icon name="search" class="w-3 h-3 inline mr-0.5 opacity-60" />
                <span x-text="results.length + ' result' + (results.length === 1 ? '' : 's')"></span>
            </span>
        </div>
    </div>
</div>
