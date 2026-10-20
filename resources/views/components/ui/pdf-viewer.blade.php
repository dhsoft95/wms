@props(['url', 'label' => 'View Document', 'btnClass' => 'btn btn-ghost btn-sm gap-1.5'])

<div x-data="{ open: false, src: '' }">
    <button type="button" @click="src = '{{ $url }}'; open = true" class="{{ $btnClass }}">
        {{ $slot }}
    </button>

    <template x-teleport="body">
        <div x-show="open"
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @keydown.escape.window="open = false"
             style="display:none">

            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="open = false"></div>

            {{-- Panel --}}
            <div class="relative z-10 w-full max-w-5xl flex flex-col rounded-2xl shadow-2xl overflow-hidden"
                 style="height: 88vh;">

                {{-- Header --}}
                <div class="flex shrink-0 items-center justify-between
                            px-5 py-3
                            bg-white dark:bg-zinc-900
                            border-b border-slate-100 dark:border-white/[0.08]">
                    <span class="text-sm font-semibold text-slate-800 dark:text-white">{{ $label }}</span>
                    <div class="flex items-center gap-2">
                        <a href="{{ $url }}" download
                           class="btn btn-ghost btn-sm gap-1.5 text-xs">
                            <x-ui.icon name="arrow-down-tray" class="w-4 h-4" /> Download
                        </a>
                        <button type="button" @click="open = false"
                                class="icon-btn text-slate-400 hover:text-slate-700 dark:hover:text-slate-200"
                                aria-label="Close">
                            <x-ui.icon name="x" class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                {{-- PDF iframe — src bound lazily so it only loads when modal opens --}}
                <iframe :src="src"
                        class="flex-1 w-full border-0 bg-slate-100 dark:bg-zinc-800"></iframe>
            </div>
        </div>
    </template>
</div>
