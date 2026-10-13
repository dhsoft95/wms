@php
use App\Support\Navigation\NavigationBuilder;

$navGroups    = app(NavigationBuilder::class)->build();
$navGroupKeys = array_column($navGroups, 'key');
$allNavItems  = collect($navGroups)
    ->flatMap(fn ($g) => collect($g['items'])->map(fn ($i) => [
        'label' => $i['label'],
        'url'   => $i['url'],
        'group' => $g['label'],
    ]))
    ->values()
    ->all();

$navGroupPaths = collect($navGroups)->map(fn ($g) => [
    'key'   => $g['key'],
    'paths' => collect($g['items'])->map(fn ($i) => parse_url($i['url'], PHP_URL_PATH))->values()->all(),
])->values()->all();

$branding  = config('wms.branding');
$brandName = $branding['name'] ?? config('app.name', 'Warehouse');
$brandSub  = $branding['subtitle'] ?? 'Warehouse Management System';
$initials  = $branding['initials'] ?? 'WMS';
$logoPath  = $branding['logo'] ?? null;
$hasLogo   = !empty($logoPath);
$appBg     = $branding['app_background'];
$authBg    = $branding['auth_background'];
$bgImage   = asset(file_exists(public_path($appBg)) ? $appBg : $authBg);
@endphp
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    x-data="{
        sidebarOpen: false,
        sidebarRail: $persist(false).as('wms_nav_rail'),
        darkMode: localStorage.getItem('wms_theme') === 'dark',
        commandOpen: false,
        openGroups: $persist({}).as('wms_nav_groups'),
        currentPath: window.location.pathname,
        navGroupPaths: @js($navGroupPaths),
        init() {
            // Reset persisted sidebar state when a different user logs in so that
            // user B never inherits user A's rail/collapse preference.
            const currentUserId = {{ auth()->id() ?? 'null' }};
            const storedUserId  = parseInt(localStorage.getItem('wms_user_id') || '0');
            if (currentUserId && storedUserId && storedUserId !== currentUserId) {
                this.sidebarRail = false;
                this.openGroups  = {};
            }
            if (currentUserId) {
                localStorage.setItem('wms_user_id', currentUserId);
            }

            this.$watch('darkMode', val => {
                document.documentElement.classList.toggle('dark', val);
                localStorage.setItem('wms_theme', val ? 'dark' : 'light');
            });
            document.documentElement.classList.toggle('dark', this.darkMode);
            document.addEventListener('keydown', (e) => {
                const tag = e.target.tagName;
                const editable = ['INPUT','TEXTAREA','SELECT'].includes(tag) || e.target.isContentEditable;
                if (e.key === '[' && !editable && !e.ctrlKey && !e.metaKey) {
                    this.sidebarRail = !this.sidebarRail;
                }
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    this.commandOpen = !this.commandOpen;
                }
            });

            const syncActiveGroup = () => {
                this.currentPath = window.location.pathname;
                const activeKey = this.activeGroupKey();
                if (activeKey !== null && this.openGroups[activeKey] === false) {
                    const g = { ...this.openGroups };
                    g[activeKey] = true;
                    this.openGroups = g;
                }
            };
            document.addEventListener('livewire:navigated', syncActiveGroup);
            syncActiveGroup();
        },
        isNavLinkActive(path) {
            const p = this.currentPath;
            return p === path || (path.length > 1 && p.startsWith(path + '/'));
        },
        activeGroupKey() {
            for (const group of this.navGroupPaths) {
                for (const path of group.paths) {
                    if (this.isNavLinkActive(path)) return group.key;
                }
            }
            return null;
        },
        isGroupOpen(key) { return this.openGroups[key] !== false; },
        toggleGroup(key) {
            const g = { ...this.openGroups };
            g[key] = !this.isGroupOpen(key);
            this.openGroups = g;
        },
        expandAllGroups() { this.openGroups = {}; },
        collapseAllGroups(keys) {
            const g = {};
            keys.forEach(k => g[k] = false);
            this.openGroups = g;
        },
    }"
    :class="{ 'dark': darkMode }"
>
<head>
    @include('partials.head')
</head>
<body class="font-app app-body text-slate-900 dark:text-slate-100 antialiased">

{{-- ─── Background image layer ─────────────────────────────────────────────── --}}
<div class="app-bg-layer fixed inset-0 -z-10 overflow-hidden">
    <div
        class="absolute inset-0 scale-105 blur-md"
        style="background-image: url('{{ $bgImage }}'); background-size: cover; background-position: center;"
    ></div>
    <div class="absolute inset-0 dark:hidden"
        style="background: linear-gradient(160deg, rgb(255 255 255 / 0.68) 0%, rgb(255 255 255 / 0.62) 70%, color-mix(in oklab, var(--color-simba-950) 8%, rgb(255 255 255 / 0.62)) 100%);"
    ></div>
    <div class="absolute inset-0 hidden dark:block"
        style="background: linear-gradient(160deg, rgb(10 10 10 / 0.65) 0%, rgb(10 10 10 / 0.60) 70%, color-mix(in oklab, var(--color-simba-950) 25%, rgb(10 10 10 / 0.62)) 100%);"
    ></div>
</div>

<div class="h-screen flex overflow-hidden">

    {{-- Mobile sidebar overlay --}}
    <div
        x-show="sidebarOpen"
        x-transition:enter="transition-opacity ease-linear duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="sidebarOpen = false"
        class="lg:hidden fixed inset-0 z-30 bg-zinc-950/30 backdrop-blur-sm"
        style="display:none"
    ></div>

    {{-- ─── Sidebar ─────────────────────────────────────────────────────────── --}}
    <aside
        :class="[
            sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
            sidebarRail ? 'w-[72px]' : 'w-64',
        ]"
        class="fixed inset-y-0 left-0 z-40 lg:static flex flex-col shrink-0 glass-nav dark:glass-nav-dark transition-all duration-200 ease-in-out"
        style="border-right: 1px solid rgb(255 255 255 / 0.4);"
        :style="darkMode ? 'border-right-color: rgb(255 255 255 / 0.1)' : 'border-right-color: rgb(255 255 255 / 0.4)'"
    >
        {{-- ── Brand block ──────────────────────────────────────────────────── --}}
        <div class="h-16 flex items-center shrink-0 overflow-hidden transition-all duration-200"
            :class="sidebarRail ? 'px-0 justify-center' : 'px-5 gap-3'"
            style="border-bottom: 1px solid rgb(255 255 255 / 0.3);"
        >
            <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3 min-w-0">
                @if ($hasLogo)
                    <img src="{{ asset($logoPath) }}" alt="{{ $brandName }}"
                         class="w-9 h-9 rounded-xl object-contain shrink-0"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <div class="w-9 h-9 rounded-xl glass-brand items-center justify-center font-black text-[12px] tracking-tight text-white shrink-0" style="display:none">
                        {{ $initials }}
                    </div>
                @else
                    <div class="w-9 h-9 rounded-xl glass-brand flex items-center justify-center font-black text-[12px] tracking-tight text-white shrink-0">
                        {{ $initials }}
                    </div>
                @endif
                <div class="min-w-0 overflow-hidden" x-show="!sidebarRail" x-cloak>
                    <p class="font-bold text-slate-900 dark:text-white text-sm leading-tight truncate">{{ $brandName }}</p>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400 leading-tight truncate">{{ $brandSub }}</p>
                </div>
            </a>
            <button @click="sidebarOpen = false" class="ml-auto icon-btn lg:hidden shrink-0" x-show="!sidebarRail" x-cloak>
                <x-ui.icon name="x" class="w-5 h-5" />
            </button>
        </div>

        {{-- ── Navigation ───────────────────────────────────────────────────── --}}
        <nav class="flex-1 overflow-y-auto sidebar-scroll px-2 py-3 space-y-4">
            @foreach ($navGroups as $group)
                <x-nav.group :group="$group" />
            @endforeach
        </nav>

        {{-- ── Sidebar footer ───────────────────────────────────────────────── --}}
        <div class="shrink-0 px-2 py-2 space-y-0.5"
            style="border-top: 1px solid rgb(255 255 255 / 0.3);"
            :style="darkMode ? 'border-top-color: rgb(255 255 255 / 0.08)' : ''">

            {{-- Expand / Collapse all (expanded mode only) --}}
            <div class="flex items-center gap-1 px-1 mb-1" x-show="!sidebarRail" x-cloak>
                <button type="button"
                    @click="expandAllGroups()"
                    class="flex-1 rounded-lg px-2 py-1 text-[11px] font-medium text-slate-400 dark:text-slate-500 hover:bg-white/40 dark:hover:bg-white/[0.06] hover:text-slate-600 dark:hover:text-slate-300 transition-colors text-center"
                    title="Expand all groups">
                    Expand all
                </button>
                <button type="button"
                    @click="collapseAllGroups(@js($navGroupKeys))"
                    class="flex-1 rounded-lg px-2 py-1 text-[11px] font-medium text-slate-400 dark:text-slate-500 hover:bg-white/40 dark:hover:bg-white/[0.06] hover:text-slate-600 dark:hover:text-slate-300 transition-colors text-center"
                    title="Collapse all groups">
                    Collapse all
                </button>
            </div>

            {{-- Collapse / Expand toggle --}}
            <button
                type="button"
                @click="sidebarRail = !sidebarRail"
                class="sidebar-link w-full"
                :title="sidebarRail ? 'Expand sidebar  [' : 'Collapse sidebar  ['"
            >
                <x-ui.icon
                    name="chevron-double-left"
                    class="w-[18px] h-[18px] shrink-0 opacity-70 transition-transform duration-200"
                    ::class="sidebarRail ? 'rotate-180' : ''"
                />
                <span class="flex-1 truncate text-left" x-show="!sidebarRail" x-cloak>Collapse</span>
            </button>

            {{-- Version (expanded only) --}}
            <div class="px-3 pt-1" x-show="!sidebarRail" x-cloak>
                <p class="text-[10px] text-slate-400 dark:text-slate-600">v{{ config('app.version', '1.0.0') }}</p>
            </div>
        </div>
    </aside>

    {{-- ─── Main column ─────────────────────────────────────────────────────── --}}
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        {{-- Topbar --}}
        <header class="h-16 shrink-0 flex items-center gap-3 px-4 sm:px-6 glass-nav dark:glass-nav-dark sticky top-0 z-20 no-print"
            style="border-bottom: 1px solid rgb(255 255 255 / 0.4);"
            x-bind:style="darkMode ? 'border-bottom-color: rgb(255 255 255 / 0.08)' : 'border-bottom-color: rgb(255 255 255 / 0.4)'"
        >
            <button @click="sidebarOpen = true" class="icon-btn lg:hidden">
                <x-ui.icon name="menu" class="w-5 h-5" />
            </button>

            {{-- Breadcrumb / page title --}}
            @if (($breadcrumbGroup ?? null) || ($title ?? null))
                <div class="min-w-0">
                    @if ($breadcrumbGroup ?? null)
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                            {{ $breadcrumbGroup }}
                        </p>
                    @endif
                    @if ($title ?? null)
                        <h1 class="text-sm font-bold truncate text-slate-900 dark:text-white leading-tight">
                            {{ $title }}
                        </h1>
                    @endif
                </div>
            @endif

            <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
                {{-- Search (opens command palette) --}}
                <div class="relative hidden md:block">
                    <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                    <button type="button"
                        @click="commandOpen = true"
                        class="glass-input dark:glass-input-dark pl-9 pr-3 w-56 h-8 rounded-lg text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-left transition flex items-center gap-2 focus:outline-none focus:ring-2 focus:ring-simba-600/30">
                        Search pages…
                        <kbd class="ml-auto hidden lg:inline-flex items-center gap-0.5 px-1 rounded text-[10px] font-mono bg-white/50 dark:bg-white/[0.08]">
                            <span class="text-[10px]">⌘K</span>
                        </kbd>
                    </button>
                </div>

                {{-- VW switcher --}}
                <livewire:vw-switcher />

                {{-- Dark mode toggle --}}
                <button @click="darkMode = !darkMode" class="icon-btn" title="Toggle theme">
                    <x-ui.icon name="moon" class="w-5 h-5 dark:hidden" />
                    <x-ui.icon name="sun" class="w-5 h-5 hidden dark:block" />
                </button>

                {{-- Notifications --}}
                <livewire:notification-bell />

                {{-- User menu --}}
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button
                        @click="open = !open"
                        class="flex items-center gap-2.5 pl-2 sm:pl-3 sm:border-l"
                        style="border-color: rgb(255 255 255 / 0.4);"
                    >
                        <div class="w-8 h-8 rounded-full glass-brand text-white font-bold flex items-center justify-center text-xs">
                            {{ auth()->user()?->initials() ?? 'U' }}
                        </div>
                        <div class="hidden md:block text-left">
                            <p class="text-xs font-semibold leading-tight text-slate-800 dark:text-slate-100">{{ auth()->user()?->name ?? 'User' }}</p>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 leading-tight">{{ auth()->user()?->email ?? '' }}</p>
                        </div>
                        <x-ui.icon name="chevron-down" class="w-4 h-4 text-slate-400 hidden md:block" />
                    </button>

                    <div x-show="open" x-transition class="absolute right-0 mt-2 w-52 glass-solid dark:glass-solid-dark rounded-2xl p-1.5 z-50" style="display:none">
                        <div class="px-3 py-2 mb-1" style="border-bottom: 1px solid rgb(255 255 255 / 0.4);">
                            <p class="text-xs font-semibold text-slate-800 dark:text-slate-100">{{ auth()->user()?->name }}</p>
                            <p class="text-[11px] text-slate-400 truncate">{{ auth()->user()?->email }}</p>
                        </div>
                        <a href="{{ route('profile.index') }}" wire:navigate class="dropdown-item">
                            <x-ui.icon name="user" class="w-4 h-4" />
                            My Profile
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item !text-red-600 w-full text-left">
                                <x-ui.icon name="logout" class="w-4 h-4" />
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto">
            <div class="w-full max-w-[1600px] mx-auto px-6 lg:px-8 xl:px-10 py-8">
                {{ $slot }}
            </div>
        </main>
    </div>
</div>

{{-- Command palette --}}
<x-nav.command-palette :items="$allNavItems" />

{{-- Toast notifications --}}
<livewire:toast />

@fluxScripts
@livewireScripts
</body>
</html>
