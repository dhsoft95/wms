<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.head')
    <meta name="theme-color" content="#a22631">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body
    x-data="{
        online: navigator.onLine,
        muted: window.ScanFeedback?.muted ?? false,
        toggleMute() { this.muted = window.ScanFeedback.toggleMute(); },
        scan() { if (this.online && window.startCameraScan) window.startCameraScan(); }
    }"
    @online.window="online = true"
    @offline.window="online = false"
    class="min-h-full bg-slate-100 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100"
>

<header class="sticky top-0 z-30 bg-simba-600 pt-[env(safe-area-inset-top)] text-white shadow-md">
    <div class="mx-auto flex h-14 max-w-lg items-center gap-3 px-4">
        <a href="{{ route('scan.home') }}" wire:navigate class="font-bold tracking-tight text-lg leading-none">
            WMS Scan
        </a>

        <div class="ml-auto flex items-center gap-1">
            <button type="button" @click="toggleMute()"
                    class="grid size-11 place-items-center rounded-xl transition-colors hover:bg-white/10 active:bg-white/20"
                    :aria-label="muted ? 'Unmute scan sounds' : 'Mute scan sounds'"
                    :aria-pressed="muted.toString()">
                <svg x-show="!muted" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M11 5 6 9H2v6h4l5 4V5z"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>
                </svg>
                <svg x-show="muted" x-cloak class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M11 5 6 9H2v6h4l5 4V5z"/><path d="m23 9-6 6"/><path d="m17 9 6 6"/>
                </svg>
            </button>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" aria-label="Log out"
                        class="grid size-11 place-items-center rounded-xl transition-colors hover:bg-white/10 active:bg-white/20">
                    <x-ui.icon name="logout" class="size-5" />
                </button>
            </form>
        </div>
    </div>

    <div class="mx-auto flex max-w-lg items-center gap-2 px-4 pb-2.5 text-xs">
        <span class="size-2 shrink-0 rounded-full" :class="online ? 'bg-emerald-400' : 'bg-red-300 animate-pulse'"></span>
        <span class="truncate text-simba-100">{{ auth()->user()?->name }}</span>
        @if (session('scan_device_code'))
            <span class="ml-auto shrink-0 rounded-md bg-white/15 px-2 py-0.5 font-mono font-medium tracking-wide">
                    {{ session('scan_device_code') }}
                </span>
        @endif
    </div>

    <div x-show="!online" x-cloak x-transition.opacity
         role="alert"
         class="bg-red-600 px-4 py-2 text-center text-sm font-semibold">
        No connection. Actions are disabled until you're back online.
    </div>
</header>

<div id="scan-flash" aria-hidden="true"
     class="pointer-events-none fixed inset-0 z-50 opacity-0 transition-opacity duration-150"></div>

<main class="mx-auto max-w-lg space-y-4 px-4 pt-5 pb-32 transition-opacity"
      :class="!online && 'pointer-events-none select-none opacity-50'"
      :inert="!online">
    {{ $slot }}
</main>

<div class="pointer-events-none fixed inset-x-0 bottom-0 z-30 bg-gradient-to-t from-slate-100 via-slate-100/90 to-transparent px-4 pt-6 pb-[max(1rem,env(safe-area-inset-bottom))] dark:from-slate-950 dark:via-slate-950/90">
    <div class="mx-auto max-w-lg">
        <x-scan.button id="scan-camera-btn"
                       @click="scan()"
                       x-bind:disabled="!online"
                       class="pointer-events-auto">
            <x-ui.icon name="camera" class="size-6" />
            Scan with camera
        </x-scan.button>
    </div>
</div>

<livewire:toast />

<script data-navigate-once>
    window.ScanFeedback = window.ScanFeedback || {
        muted: localStorage.getItem('scan_muted') === '1',
        _ctx: null,
        _flashTimer: null,

        toggleMute() {
            this.muted = !this.muted;
            localStorage.setItem('scan_muted', this.muted ? '1' : '0');
            return this.muted;
        },

        success() {
            this._beep(1200, 80);
            navigator.vibrate?.(50);
            this._flash('#22c55e');
        },

        error() {
            this._beep(400, 150);
            setTimeout(() => this._beep(400, 150), 200);
            navigator.vibrate?.([100, 50, 100]);
            this._flash('#dc2626');
        },

        _audio() {
            if (!this._ctx) {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return null;
                this._ctx = new Ctx();
            }
            if (this._ctx.state === 'suspended') this._ctx.resume();
            return this._ctx;
        },

        _beep(freq, dur) {
            if (this.muted) return;
            const ctx = this._audio();
            if (!ctx) return;
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            const t = ctx.currentTime;
            o.frequency.value = freq;
            g.gain.setValueAtTime(0.3, t);
            g.gain.exponentialRampToValueAtTime(0.001, t + dur / 1000);
            o.connect(g).connect(ctx.destination);
            o.start(t);
            o.stop(t + dur / 1000);
        },

        _flash(color) {
            const el = document.getElementById('scan-flash');
            if (!el) return;
            clearTimeout(this._flashTimer);
            el.style.backgroundColor = color;
            el.style.opacity = '0.35';
            this._flashTimer = setTimeout(() => { el.style.opacity = '0'; }, 250);
        }
    };

    document.addEventListener('pointerdown', () => ScanFeedback._audio(), { once: true });

    document.addEventListener('livewire:initialized', () => {
        Livewire.on('scan-success', () => ScanFeedback.success());
        Livewire.on('scan-error', () => ScanFeedback.error());
    });
</script>

@livewireScripts
@fluxScripts
</body>
</html>
