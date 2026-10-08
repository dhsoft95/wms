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
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="p-2 rounded-lg hover:bg-simba-700 transition-colors">
                    <x-ui.icon name="logout" class="w-5 h-5" />
                </button>
            </form>
        </div>
    </header>

    <main class="max-w-lg mx-auto px-4 py-6 space-y-4">
        {{ $slot }}
    </main>

    <livewire:toast />
    @livewireScripts
    @fluxScripts
</body>
</html>
