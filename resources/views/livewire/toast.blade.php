<div
    x-data="{
        toasts: [],
        _seq: 0,

        _colors: {
            success: { border: '#059669', bar: '#059669', icon: 'text-emerald-600' },
            error:   { border: '#dc2626', bar: '#dc2626', icon: 'text-red-600'     },
            info:    { border: '#a22631', bar: '#a22631', icon: 'text-simba-600'   },
        },

        _icons: {
            success: 'M5 13l4 4L19 7',
            error:   'M6 18L18 6M6 6l12 12',
            info:    'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        },

        show(message, type = 'success', duration = 3500) {
            const key = this._colors[type] ? type : 'info';
            const id  = ++this._seq;
            this.toasts.push({
                id,
                message,
                type:     key,
                duration,
                c:        this._colors[key],
                icon:     this._icons[key],
                visible:  false,
                paused:   false,
            });
            this.$nextTick(() => {
                const t = this.toasts.find(t => t.id === id);
                if (t) t.visible = true;
            });
        },

        dismiss(id) {
            const t = this.toasts.find(t => t.id === id);
            if (!t) return;
            if (t._raf) { cancelAnimationFrame(t._raf); t._raf = null; }
            t.visible = false;
            setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 220);
        },

        _startTimer(toast, el) {
            let bar = null;
            let remaining = toast.duration;
            let last = performance.now();

            const tick = (now) => {
                if (!bar) bar = el.querySelector('[data-bar]');
                if (!toast.paused) {
                    remaining -= (now - last);
                    last = now;
                    const pct = Math.max(0, remaining / toast.duration);
                    if (bar) bar.style.transform = 'scaleX(' + pct + ')';
                    if (remaining <= 0) { this.dismiss(toast.id); return; }
                } else {
                    last = now;
                }
                toast._raf = requestAnimationFrame(tick);
            };

            toast._raf = requestAnimationFrame(tick);
        },
    }"
    @toast.window="show($event.detail.message, $event.detail.type ?? 'success', $event.detail.duration ?? 3500)"
    class="fixed bottom-4 right-4 z-[60] flex flex-col gap-2 no-print"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="toast.visible"
            x-init="$nextTick(() => _startTimer(toast, $el))"
            @mouseenter="toast.paused = true"
            @mouseleave="toast.paused = false"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-x-0"
            x-transition:leave-end="opacity-0 translate-x-4"
            :style="{ 'border-left': '3px solid ' + toast.c.border }"
            role="status"
            aria-live="polite"
            class="relative overflow-hidden glass dark:glass-dark flex items-center gap-3 min-w-[260px] max-w-sm px-4 py-3 rounded-xl shadow-lg text-sm font-medium text-slate-800 dark:text-slate-100"
        >
            {{-- Type icon --}}
            <svg
                class="w-4 h-4 shrink-0"
                :class="toast.c.icon"
                fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" :d="toast.icon" />
            </svg>

            {{-- Message --}}
            <span class="flex-1" x-text="toast.message"></span>

            {{-- Dismiss button --}}
            <button
                type="button"
                @click="dismiss(toast.id)"
                class="ml-1 shrink-0 rounded p-0.5 opacity-40 hover:opacity-100 transition-opacity"
                aria-label="Dismiss"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            {{-- Progress bar track --}}
            <div class="absolute bottom-0 left-0 right-0 h-[3px] bg-slate-900/5 dark:bg-white/10 rounded-b-xl"></div>

            {{-- Progress bar fill (scaleX driven by RAF, not Alpine reactivity) --}}
            <div
                data-bar
                class="absolute bottom-0 left-0 h-[3px] w-full origin-left rounded-b-xl"
                :style="{ background: toast.c.bar }"
            ></div>
        </div>
    </template>
</div>
