<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
    <meta name="theme-color" content="#a22631">
    <style>
        /* Mobile-first scan layout — large touch targets, no sidebar */
        body { @apply bg-slate-100 dark:bg-slate-950; }
        .scan-btn { @apply flex items-center justify-center gap-3 w-full rounded-2xl p-5 text-base font-semibold transition-colors active:scale-95; }
        .scan-btn-primary { @apply bg-simba-600 text-white hover:bg-simba-700; }
        .scan-btn-secondary { @apply bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:bg-slate-50; }
    </style>
</head>
<body class="min-h-screen font-sans antialiased">

    <header class="sticky top-0 z-30 flex items-center justify-between px-4 py-3 bg-simba-600 text-white shadow-sm">
        <a href="{{ route('scan.home') }}" wire:navigate class="flex items-center gap-2 font-bold text-lg tracking-tight">
            <x-ui.icon name="package" class="w-6 h-6" />
            WMS Scan
        </a>
        <div class="flex items-center gap-2">
            <span class="text-xs text-simba-200">{{ auth()->user()?->name }}</span>
            <span class="text-xs text-simba-300 ml-1">({{ session('scan_device_code', '') }})</span>
            <button type="button" id="scan-camera-btn" onclick="if(window.startCameraScan)startCameraScan()"
                    class="p-2 rounded-lg hover:bg-simba-700 transition-colors" title="Camera scan">
                <x-ui.icon name="camera" class="w-5 h-5" />
            </button>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="p-2 rounded-lg hover:bg-simba-700 transition-colors">
                    <x-ui.icon name="logout" class="w-5 h-5" />
                </button>
            </form>
        </div>
    </header>

    <div id="scan-flash" class="hidden fixed inset-0 opacity-40 z-50 pointer-events-none"></div>

    <div x-data="{online: navigator.onLine}" @online.window="online=true" @offline.window="online=false"
         x-show="!online"
         class="bg-red-600 text-white text-center text-sm py-2 font-semibold px-4">
        No connection — actions disabled
    </div>

    <main class="max-w-lg mx-auto px-4 py-6 space-y-4">
        {{ $slot }}
    </main>

    <livewire:toast />

    <script>
    window.ScanFeedback = {
        muted: localStorage.getItem('scan_muted') === '1',
        success() {
            this._beep(1200, 80);
            if (navigator.vibrate) navigator.vibrate(50);
            this._flash('bg-green-500');
        },
        error() {
            this._beep(400, 150);
            setTimeout(() => this._beep(400, 150), 200);
            if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
            this._flash('bg-red-600');
        },
        _beep(freq, dur) {
            if (this.muted) return;
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const o = ctx.createOscillator();
                const g = ctx.createGain();
                o.connect(g); g.connect(ctx.destination);
                o.frequency.value = freq;
                g.gain.setValueAtTime(0.3, ctx.currentTime);
                o.start(ctx.currentTime);
                o.stop(ctx.currentTime + dur / 1000);
            } catch(e) {}
        },
        _flash(cls) {
            const el = document.getElementById('scan-flash');
            if (!el) return;
            el.className = 'fixed inset-0 opacity-40 z-50 pointer-events-none ' + cls;
            setTimeout(() => { el.className = 'hidden fixed inset-0 opacity-40 z-50 pointer-events-none'; }, 300);
        }
    };

    document.addEventListener('livewire:initialized', () => {
        Livewire.on('scan-success', () => ScanFeedback.success());
        Livewire.on('scan-error',   () => ScanFeedback.error());
    });
    </script>

    @livewireScripts
    @fluxScripts
</body>
</html>
