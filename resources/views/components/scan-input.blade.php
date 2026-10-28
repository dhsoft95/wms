{{--
    Reusable barcode scan input.
    Usage: <x-scan-input wire:model="scanInput" :method="'processScan'" placeholder="Scan…" />
    Listens to scan-success / scan-error browser events for green/red feedback + beep.
--}}
@props([
    'method'      => 'processScan',
    'placeholder' => 'Scan barcode or type SKU then press Enter…',
])

<div
    x-data="{
        lastScan: '',
        lastAt:   0,
        status:   null,
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
        flash(ok) {
            this.status = ok ? 'ok' : 'err';
            this.beep(ok);
            setTimeout(() => this.status = null, 1500);
        },
    }"
    @scan-success.window="flash(true)"
    @scan-error.window="flash(false)"
>
    <div class="relative">
        <x-ui.icon name="qr-code" class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
        <input
            {{ $attributes->merge(['class' => 'input !pl-10 w-full font-mono']) }}
            type="text"
            placeholder="{{ $placeholder }}"
            autocomplete="off"
            data-scan-input
            x-on:keydown.enter="
                const now = Date.now();
                if ($el.value === lastScan && now - lastAt < 600) { $el.value = ''; return; }
                lastScan = $el.value;
                lastAt   = now;
                $wire.{{ $method }}();
            "
            :class="{
                'ring-2 ring-emerald-400 !border-emerald-400': status === 'ok',
                'ring-2 ring-red-400 !border-red-400':         status === 'err',
            }"
        />
    </div>
    <p
        x-show="status !== null"
        x-transition.opacity
        class="mt-1 text-xs font-medium"
        :class="status === 'ok' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
        x-text="status === 'ok' ? '✓ Matched' : '✗ Not recognised'"
    ></p>
</div>
