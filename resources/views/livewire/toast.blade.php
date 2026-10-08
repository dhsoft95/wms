<div
    x-data="{
        toasts: [],
        show(message, type = 'success') {
            const id = Date.now();
            this.toasts.push({ id, message, type });
            setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 3500);
        }
    }"
    @toast.window="show($event.detail.message, $event.detail.type ?? 'success')"
    class="fixed bottom-4 right-4 z-[60] flex flex-col gap-2 no-print"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="true"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            :class="{
                'bg-emerald-600': toast.type === 'success',
                'bg-red-600':     toast.type === 'error',
                'bg-slate-800 dark:bg-slate-700': toast.type === 'info'
            }"
            class="flex items-center gap-3 min-w-[260px] max-w-sm px-4 py-3 rounded-lg shadow-lg text-sm font-medium text-white"
        >
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                    :d="toast.type === 'error'
                        ? 'M6 18L18 6M6 6l12 12'
                        : toast.type === 'info'
                            ? 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
                            : 'M5 13l4 4L19 7'"
                />
            </svg>
            <span x-text="toast.message"></span>
        </div>
    </template>
</div>
