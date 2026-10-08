@php
    $brand          = config('wms.branding', []);
    $brandName      = $brand['name'] ?? config('app.name');
    $subtitle       = $brand['subtitle'] ?? 'Warehouse Management System';
    $initials       = strtoupper($brand['initials'] ?? substr($brandName, 0, 2));
    $logo           = $brand['logo']
                      ?? (file_exists(public_path('images/logo/logo.png')) ? 'images/logo/logo.png' : null)
                      ?? (file_exists(public_path('images/logo.svg'))      ? 'images/logo.svg'      : null);
    $authBg         = $brand['auth_background'] ?? 'images/auth/warehouse-bg.jpg';
    $headline       = $brand['headline'] ?? 'Warehouse operations,';
    $headlineAccent = $brand['headline_accent'] ?? 'from yard to doorstep.';
    $tagline        = $brand['tagline'] ?? '';
    $features       = $brand['features'] ?? [];
    $pillars        = $brand['footer_pillars'] ?? ['Safe', 'Efficient', 'Connected'];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
{{-- overflow-x-hidden prevents decorative shapes from causing horizontal scroll on mobile --}}
<body class="font-sans antialiased overflow-x-hidden">

    {{-- Background image --}}
    <div class="fixed inset-0 -z-20 scale-105 origin-center"
         style="background-image: url('{{ asset($authBg) }}'); background-size: cover; background-position: center;"
         aria-hidden="true"></div>

    {{-- Left brand gradient (full-bleed) --}}
    <div class="fixed inset-0 -z-10 bg-gradient-to-r from-simba-950/90 via-simba-900/60 to-transparent pointer-events-none" aria-hidden="true"></div>

    {{-- Right darkening wash (full-bleed) --}}
    <div class="fixed inset-0 -z-10 bg-gradient-to-l from-black/20 via-transparent to-transparent pointer-events-none" aria-hidden="true"></div>

    {{-- Decorative diagonal glass shapes — hidden on mobile to prevent overflow --}}
    <div class="hidden sm:block fixed bottom-0 left-0 -z-10 w-80 h-80 rounded-tr-[80px] bg-simba-950/40 backdrop-blur-sm pointer-events-none"
         aria-hidden="true" style="transform: rotate(-15deg) translate(-40px, 40px);"></div>
    <div class="hidden sm:block fixed top-0 right-16 -z-10 w-52 h-52 rounded-bl-[60px] bg-white/5 backdrop-blur-sm pointer-events-none"
         aria-hidden="true" style="transform: rotate(20deg) translate(20px, -25px);"></div>
    <div class="hidden sm:block fixed bottom-16 right-0 -z-10 w-72 h-44 rounded-tl-[50px] bg-simba-800/25 backdrop-blur-sm pointer-events-none"
         aria-hidden="true" style="transform: rotate(-10deg) translate(25px, 20px);"></div>

    {{-- Content container — caps at 1440px, grid switches to single-column below lg --}}
    <div class="min-h-screen max-w-[1440px] mx-auto grid lg:grid-cols-[1.15fr_1fr]">

        {{-- ===== Left brand panel (desktop only) ===== --}}
        <div class="hidden lg:flex flex-col justify-between min-h-screen px-12 xl:px-16 py-12 xl:py-16 text-white">

            {{-- Brand block --}}
            <a href="{{ url('/') }}" class="flex items-center gap-4 no-underline w-fit" wire:navigate>
                @if ($logo)
                    <img src="{{ asset($logo) }}" alt="{{ $brandName }}" class="h-12 w-auto">
                @else
                    <div class="w-12 h-12 rounded-2xl glass-brand flex items-center justify-center font-bold text-white text-lg shrink-0">
                        {{ $initials }}
                    </div>
                @endif
                <div>
                    <p class="text-xl font-bold leading-tight text-white">{{ $brandName }}</p>
                    <p class="text-xs text-white/70 tracking-wide mt-0.5">{{ $subtitle }}</p>
                </div>
            </a>

            {{-- Headline, tagline, feature grid --}}
            <div class="space-y-8 max-w-2xl">
                <div class="space-y-4">
                    <h1 class="text-4xl xl:text-5xl 2xl:text-6xl font-bold leading-[1.1] tracking-tight">
                        <span class="block xl:whitespace-nowrap">{{ $headline }}</span>
                        <span class="block xl:whitespace-nowrap text-simba-300">{{ $headlineAccent }}</span>
                    </h1>
                    @if ($tagline)
                        <p class="text-white/75 text-base leading-relaxed max-w-md">{{ $tagline }}</p>
                    @endif
                </div>

                @if ($features)
                    <div class="grid grid-cols-2 gap-x-10 gap-y-6">
                        @foreach ($features as $feat)
                            <x-auth.feature-tile :icon="$feat['icon']" :title="$feat['title']" :text="$feat['text']" />
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Footer pillars --}}
            <div class="space-y-3">
                <div class="h-px bg-white/15"></div>
                <div class="flex items-center gap-2.5 text-white/60">
                    <svg class="w-4 h-4 shrink-0 text-simba-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                    </svg>
                    <span class="text-xs font-semibold uppercase tracking-widest">
                        {{ implode(' / ', $pillars) }}
                    </span>
                </div>
            </div>
        </div>

        {{-- ===== Right form panel ===== --}}
        {{-- min-h-dvh centres the card on the visible viewport area on iOS Safari --}}
        <div class="flex flex-col items-center justify-center min-h-screen min-h-dvh px-4 sm:px-6 xl:px-10 py-8 sm:py-10">

            {{-- Mobile brand block (shown below lg) --}}
            <div class="lg:hidden flex items-center gap-3 mb-5 sm:mb-8 text-white">
                @if ($logo)
                    <img src="{{ asset($logo) }}" alt="{{ $brandName }}" class="h-8 sm:h-9 w-auto">
                @else
                    <div class="w-9 h-9 rounded-xl glass-brand flex items-center justify-center font-bold text-white text-sm shrink-0">
                        {{ $initials }}
                    </div>
                @endif
                <p class="font-bold text-white text-base sm:text-lg">{{ $brandName }}</p>
            </div>

            {{-- Glass card — less rounding and padding on small screens --}}
            <div class="relative w-full max-w-lg glass dark:glass-dark rounded-2xl sm:rounded-3xl overflow-hidden">
                {{-- Diagonal sheen --}}
                <div class="absolute inset-0 bg-gradient-to-br from-white/25 via-white/5 to-transparent pointer-events-none rounded-2xl sm:rounded-3xl" aria-hidden="true"></div>
                {{-- Card content --}}
                <div class="relative p-5 sm:p-8 lg:p-12">
                    {{ $slot }}
                </div>
            </div>

        </div>
    </div>

    @fluxScripts
</body>
</html>
