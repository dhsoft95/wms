{{--
    Stock validation error dialog.
    Triggered by the browser event: stock-errors
    Payload: { errors: string[] }
--}}
<div
    x-data="{
        open: false,
        errors: [],
        show(errs) {
            this.errors = errs;
            this.open = true;
        },
        close() {
            this.open = false;
            this.errors = [];
        },
    }"
    @stock-errors.window="show($event.detail.errors)"
    x-show="open"
    x-trap.noreturn.inert="open"
    x-cloak
    class="fixed inset-0 z-[9000] flex items-center justify-center p-4"
>
    {{-- Backdrop --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="close()"
        class="absolute inset-0 bg-black/40 backdrop-blur-sm"
    ></div>

    {{-- Dialog panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="relative z-10 w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 shadow-2xl ring-1 ring-slate-200 dark:ring-slate-700 overflow-hidden"
        @click.stop
    >
        {{-- Top colour bar --}}
        <div class="h-1 w-full bg-gradient-to-r from-red-500 to-red-400"></div>

        <div class="p-6">

            {{-- Icon + Title --}}
            <div class="flex items-start gap-4">
                <div class="shrink-0 flex items-center justify-center w-12 h-12 rounded-full bg-red-50 dark:bg-red-900/30">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">
                        Stock Validation Failed
                    </h3>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                        The following issues must be resolved before saving.
                    </p>
                </div>
            </div>

            {{-- Error list --}}
            <ul class="mt-4 space-y-2">
                <template x-for="(err, idx) in errors" :key="idx">
                    <li class="flex items-start gap-2.5 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-800/40 px-3 py-2 text-sm text-red-700 dark:text-red-300">
                        <svg class="w-4 h-4 shrink-0 mt-0.5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                        <span x-text="err"></span>
                    </li>
                </template>
            </ul>

        </div>

        {{-- Footer --}}
        <div class="px-6 pb-5 flex justify-end">
            <button
                type="button"
                @click="close()"
                class="btn btn-primary px-6"
            >
                OK, fix it
            </button>
        </div>

    </div>
</div>
