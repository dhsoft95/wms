@php
    use App\Services\Foundation\SettingsService;
    use Illuminate\Support\Facades\Storage;

    $brand     = config('wms.branding', []);
    $brandName = $brand['name'] ?? config('app.name');
    $subtitle  = $brand['subtitle'] ?? 'Warehouse Management System';
    $initials  = strtoupper($brand['initials'] ?? substr($brandName, 0, 2));
    $authBg    = $brand['auth_background'] ?? 'images/auth/warehouse-bg.jpg';

    $_uploadedLogo = SettingsService::get('company.logo_path');
    $logoUrl = $_uploadedLogo
        ? Storage::disk('public')->url($_uploadedLogo)
        : (($brand['logo'] ?? null) ? asset($brand['logo']) : null);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="font-sans antialiased overflow-x-hidden">

    {{-- Background image --}}
    <div class="fixed inset-0 -z-20 scale-105 origin-center"
         style="background-image: url('{{ asset($authBg) }}'); background-size: cover; background-position: center;"
         aria-hidden="true"></div>

    {{-- Dark overlay --}}
    <div class="fixed inset-0 -z-10 bg-black/75 pointer-events-none" aria-hidden="true"></div>

    {{-- Centered content --}}
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-10">

        {{-- Glass card --}}
        <div class="relative w-full max-w-md glass dark:glass-dark rounded-2xl sm:rounded-3xl overflow-hidden shadow-2xl">
            {{-- Diagonal sheen --}}
            <div class="absolute inset-0 bg-gradient-to-br from-white/25 via-white/5 to-transparent pointer-events-none rounded-2xl sm:rounded-3xl" aria-hidden="true"></div>
            {{-- Card content --}}
            <div class="relative p-6 sm:p-10">

                {{-- Logo + brand inside card --}}
                <a href="{{ url('/') }}" class="flex flex-col items-center gap-2 mb-7 no-underline" wire:navigate>
                    @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $brandName }}"
                         class="block h-14 w-auto drop-shadow"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    @endif
                    <div class="w-14 h-14 rounded-2xl glass-brand items-center justify-center font-bold text-white text-xl shrink-0" style="display:none">
                        {{ $initials }}
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-bold text-slate-900 dark:text-white leading-tight tracking-tight">{{ $brandName }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 tracking-wide mt-0.5">{{ $subtitle }}</p>
                    </div>
                </a>

                <div class="border-t border-slate-200/60 dark:border-white/10 mb-6"></div>

                {{ $slot }}
            </div>
        </div>

    </div>

    @fluxScripts
</body>
</html>
