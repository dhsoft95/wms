{{--
    Reusable barcode scan input.
    Usage: <x-scan-input wire:model="scanInput" :method="'processScan'" placeholder="Scan..." />
    Listens to scan-success / scan-error browser events for green/red feedback and beep.

    Props:
      method      – Livewire method to call (default: processScan)
      placeholder – Input placeholder text
      dedupMs     – Milliseconds to suppress the same scan being submitted twice (default: 600, 0 = disable)
--}}
@props([
    'method'      => 'processScan',
    'placeholder' => 'Scan barcode or type SKU then press Enter...',
    'dedupMs'     => 600,
])

<div
    x-data="{
        focused:  false,
        lastScan: '',
        lastAt:   0,
        dedupMs:  {{ (int) $dedupMs }},
        status:   null,
        message:  '',
        beep(ok) {
            try {
                const ctx  = new (window.AudioContext || window.webkitAudioContext)();
                const osc  = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.frequency.value = ok ? 880 : 220;
                osc.type = 'square';
                gain.gain.setValueAtTime(0.12, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.15);
                osc.start();
                osc.stop(ctx.currentTime + 0.15);
            } catch (e) {}
        },
        flash(ok, msg) {
            this.message = msg || (ok ? 'Matched' : 'Not recognised');
            this.status  = ok ? 'ok' : 'err';
            this.beep(ok);
            setTimeout(() => { this.status = null; this.message = ''; }, 1500);
        },
        iconClass() {
            if (this.status === 'ok')  return 'bg-emerald-50 text-emerald-600 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-400 dark:ring-emerald-500/30';
            if (this.status === 'err') return 'bg-red-50 text-red-600 ring-red-200 dark:bg-red-500/15 dark:text-red-400 dark:ring-red-500/30';
            if (this.focused)          return 'bg-simba-50 text-simba-700 ring-simba-200 dark:bg-simba-500/15 dark:text-simba-300 dark:ring-simba-500/30';
            return 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-zinc-800 dark:text-slate-300 dark:ring-zinc-700';
        },
        submit(el) {
            if (!el.value.trim()) return;
            if (!navigator.onLine) {
                this.flash(false, 'Offline — scan not sent');
                return;
            }
            const now = Date.now();
            if (this.dedupMs > 0 && el.value === this.lastScan && now - this.lastAt < this.dedupMs) {
                el.value = '';
                return;
            }
            this.lastScan = el.value;
            this.lastAt   = now;
            $wire.{{ $method }}().finally(() => $nextTick(() => el.focus()));
        },
    }"
    @scan-success.window="flash(true, $event.detail?.message)"
    @scan-error.window="flash(false, $event.detail?.message)"
>
    <div class="relative">
        <span
            class="pointer-events-none absolute left-2.5 top-1/2 z-10 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg ring-1 ring-inset transition-colors duration-150"
            x-bind:class="iconClass()"
            aria-hidden="true"
        >
            {{-- Idle / focused: scan frame --}}
            <svg x-show="status === null" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 class="h-[18px] w-[18px] shrink-0">
                <path d="M3 7V5a2 2 0 0 1 2-2h2" />
                <path d="M17 3h2a2 2 0 0 1 2 2v2" />
                <path d="M21 17v2a2 2 0 0 1-2 2h-2" />
                <path d="M7 21H5a2 2 0 0 1-2-2v-2" />
                <path d="M7 8v8" />
                <path d="M10.5 8v8" />
                <path d="M14 8v8" />
                <path d="M17 8v8" />
            </svg>

            {{-- Success --}}
            <svg x-show="status === 'ok'" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                 class="h-[18px] w-[18px] shrink-0">
                <path d="M20 6 9 17l-5-5" />
            </svg>

            {{-- Error --}}
            <svg x-show="status === 'err'" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                 class="h-[18px] w-[18px] shrink-0">
                <path d="M18 6 6 18" />
                <path d="m6 6 12 12" />
            </svg>
        </span>

        <input
            {{ $attributes->merge(['class' => 'input !pl-14 !pr-24 w-full font-mono bg-white dark:bg-zinc-900']) }}
            type="text"
            placeholder="{{ $placeholder }}"
            autocomplete="off"
            autocorrect="off"
            autocapitalize="off"
            spellcheck="false"
            data-scan-input
            x-on:focus="focused = true"
            x-on:blur="focused = false"
            x-on:keydown.enter.prevent="submit($el)"
            x-on:keydown.tab.prevent="submit($el)"
            x-bind:class="{
                'ring-2 ring-emerald-400 !border-emerald-400': status === 'ok',
                'ring-2 ring-red-400 !border-red-400': status === 'err',
            }"
        />

        <span
            x-show="focused && status === null"
            x-transition.opacity
            x-cloak
            class="pointer-events-none absolute inset-y-0 right-3 flex items-center gap-1.5"
        >
            <span class="relative flex h-2 w-2 shrink-0">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-simba-500 opacity-75"></span>
                <span class="relative inline-flex h-2 w-2 rounded-full bg-simba-600"></span>
            </span>
            <span class="select-none text-xs font-medium text-simba-600 dark:text-simba-400">Ready</span>
        </span>
    </div>

    <p
        x-show="status !== null"
        x-transition.opacity
        x-cloak
        class="mt-1 text-xs font-medium"
        x-bind:class="status === 'ok' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
        x-text="message"
    ></p>
</div>

{{-- Global scan capture: routes fast hardware-scanner keystrokes to the first visible [data-scan-input] --}}
<script>
(function () {
    if (window.__wmsGlobalScanListenerAttached) return;
    window.__wmsGlobalScanListenerAttached = true;

    let buffer = '';
    let lastKey = 0;
    const MAX_GAP_MS  = 50;
    const MIN_CHARS   = 4;

    document.addEventListener('keydown', function (e) {
        // If the event is already targeting a scan input, let the Alpine handler deal with it
        if (e.target && e.target.hasAttribute('data-scan-input')) return;
        // Don't intercept typing in ordinary text fields
        if (e.target && ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;

        const now = Date.now();
        const gap = now - lastKey;
        lastKey   = now;

        if (e.key === 'Enter' || e.key === 'Tab') {
            if (buffer.length >= MIN_CHARS) {
                e.preventDefault();
                const scanEl = document.querySelector('[data-scan-input]:not([disabled])');
                if (scanEl) {
                    scanEl.value = buffer;
                    // Sync wire:model then fire the Alpine keydown handler
                    scanEl.dispatchEvent(new Event('input', { bubbles: true }));
                    scanEl.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
                    scanEl.focus();
                }
            }
            buffer = '';
            return;
        }

        // Reset buffer if too long a pause between keystrokes (human typing, not scanner)
        if (gap > MAX_GAP_MS && buffer.length > 0) {
            buffer = '';
        }

        if (e.key.length === 1) {
            buffer += e.key;
        }
    }, true);
})();
</script>
