@php
$nav = [
    ['group' => 'Overview', 'can' => null, 'items' => [
        ['label' => 'Dashboard',       'icon' => 'home',  'route' => 'dashboard',        'can' => null],
        ['label' => 'Approvals Inbox', 'icon' => 'inbox', 'route' => 'approvals.index',  'can' => 'approvals.view', 'badge' => null],
    ]],
    ['group' => 'Inbound', 'can' => 'containers.view', 'items' => [
        ['label' => 'Containers & Yard', 'icon' => 'package',   'route' => 'inbound.containers.index', 'can' => 'containers.view'],
        ['label' => 'Receiving (GRN)',   'icon' => 'clipboard', 'route' => 'inbound.grn.index',        'can' => 'receiving.view'],
        ['label' => 'Inspections',       'icon' => 'search',    'route' => 'inbound.inspections.index','can' => 'inspections.view'],
    ]],
    ['group' => 'Warehouse Operations', 'can' => 'putaway.view', 'items' => [
        ['label' => 'Put-Away',             'icon' => 'archive', 'route' => 'warehouse.putaway.index',   'can' => 'putaway.view'],
        ['label' => 'Internal Movements',   'icon' => 'arrows',  'route' => 'warehouse.movements.index', 'can' => 'movements.view'],
        ['label' => 'Department Transfers', 'icon' => 'truck',   'route' => 'warehouse.transfers.index', 'can' => 'transfers.view'],
    ]],
    ['group' => 'Inventory Control', 'can' => 'inventory.view', 'items' => [
        ['label' => 'Stock on Hand',     'icon' => 'list',        'route' => 'inventory.stock.index',       'can' => 'inventory.view'],
        ['label' => 'Stock Adjustments', 'icon' => 'adjustments', 'route' => 'inventory.adjustments.index', 'can' => 'adjustments.view'],
        ['label' => 'Cycle Counts',      'icon' => 'refresh',     'route' => 'inventory.counts.index',      'can' => 'cycle-counts.view'],
    ]],
    ['group' => 'Outbound', 'can' => 'orders.view', 'items' => [
        ['label' => 'Orders',            'icon' => 'clipboard',  'route' => 'outbound.orders.index',   'can' => 'orders.view'],
        ['label' => 'Dispatch & Gate',   'icon' => 'truck',      'route' => 'outbound.dispatch.index', 'can' => 'dispatch.view'],
        ['label' => 'Proof of Delivery', 'icon' => 'badge-check','route' => 'outbound.pod.index',      'can' => 'pod.view'],
    ]],
    ['group' => 'Reports & Documents', 'can' => 'reports.view', 'items' => [
        ['label' => 'Reports Library',     'icon' => 'chart-bar', 'route' => 'reports.index', 'can' => 'reports.view'],
        ['label' => 'Printable Documents', 'icon' => 'printer',   'route' => 'reports.print', 'can' => 'reports.view'],
    ]],
    ['group' => 'Administration', 'can' => 'users.manage', 'items' => [
        ['label' => 'SKU Master',  'icon' => 'package', 'route' => 'admin.skus.index',        'can' => 'skus.create'],
        ['label' => 'Users',       'icon' => 'users',   'route' => 'admin.users.index',       'can' => 'users.manage'],
        ['label' => 'Roles',       'icon' => 'shield',  'route' => 'admin.roles.index',       'can' => 'roles.manage'],
        ['label' => 'Departments', 'icon' => 'cog',     'route' => 'admin.departments.index', 'can' => 'departments.manage'],
    ]],
];
@endphp
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    x-data="{
        sidebarOpen: false,
        darkMode: localStorage.getItem('wms_theme') === 'dark',
        init() {
            this.$watch('darkMode', val => {
                document.documentElement.classList.toggle('dark', val);
                localStorage.setItem('wms_theme', val ? 'dark' : 'light');
            });
            document.documentElement.classList.toggle('dark', this.darkMode);
        }
    }"
    :class="{ 'dark': darkMode }"
>
<head>
    @include('partials.head')
</head>
<body class="font-sans bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 antialiased">

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
        class="lg:hidden fixed inset-0 z-30 bg-slate-950/60 backdrop-blur-sm"
        style="display:none"
    ></div>

    {{-- ─── Sidebar ─────────────────────────────────────────────────────────── --}}
    <aside
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        class="fixed inset-y-0 left-0 z-40 w-64 lg:static flex flex-col shrink-0 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 transition-transform duration-200"
    >
        {{-- Logo --}}
        <div class="h-16 flex items-center gap-3 px-5 border-b border-slate-200 dark:border-slate-800 shrink-0">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 min-w-0" wire:navigate>
                <div class="w-8 h-8 rounded-lg bg-simba-600 text-white flex items-center justify-center font-black text-[13px] tracking-tight shrink-0">
                    WMS
                </div>
                <div class="min-w-0">
                    <p class="font-bold text-slate-900 dark:text-white text-sm leading-tight truncate">
                        {{ config('app.name', 'Warehouse') }}
                    </p>
                    <p class="text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-wider">Management System</p>
                </div>
            </a>
            <button @click="sidebarOpen = false" class="ml-auto icon-btn lg:hidden shrink-0">
                <x-ui.icon name="x" class="w-5 h-5" />
            </button>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-5 scrollbar-thin">
            @foreach ($nav as $group)
                @php
                    $visibleItems = array_filter($group['items'], function ($item) {
                        return empty($item['can']) || auth()->user()?->can($item['can']);
                    });
                @endphp
                @if (count($visibleItems) > 0)
                    <div>
                        <p class="px-3 mb-1.5 text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">
                            {{ $group['group'] }}
                        </p>
                        <div class="space-y-0.5">
                            @foreach ($visibleItems as $item)
                                <a
                                    href="{{ route($item['route']) }}"
                                    wire:navigate
                                    @class(['sidebar-link', 'active' => request()->routeIs($item['route'])])
                                >
                                    <x-ui.icon :name="$item['icon']" class="w-[18px] h-[18px] shrink-0 opacity-70" />
                                    <span class="flex-1 truncate">{{ $item['label'] }}</span>
                                    @if (!empty($item['badge']))
                                        <span class="min-w-[20px] px-1.5 py-0.5 rounded-full bg-simba-500/15 text-simba-700 dark:text-simba-300 text-[10px] font-bold text-center">
                                            {{ $item['badge'] }}
                                        </span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </nav>

        {{-- User footer --}}
        <div class="shrink-0 border-t border-slate-200 dark:border-slate-800 p-3">
            <div class="flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-slate-50 dark:hover:bg-white/5 transition group">
                {{-- Avatar --}}
                <a href="{{ route('profile.index') }}" wire:navigate class="flex items-center gap-3 min-w-0 flex-1">
                    <div class="w-8 h-8 rounded-full bg-simba-600 text-white font-bold flex items-center justify-center text-xs shrink-0">
                        {{ auth()->user()?->initials() ?? 'U' }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate leading-tight">
                            {{ auth()->user()?->name ?? 'User' }}
                        </p>
                        <p class="text-[10px] text-slate-400 dark:text-slate-500 truncate">
                            {{ ucwords(str_replace('-', ' ', auth()->user()?->roleSlug() ?? 'user')) }}
                        </p>
                    </div>
                </a>

                {{-- Logout icon --}}
                <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                    @csrf
                    <button
                        type="submit"
                        title="Sign out"
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:text-red-400 dark:hover:bg-red-500/10 transition"
                    >
                        <x-ui.icon name="logout" class="w-4 h-4" />
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- ─── Main column ─────────────────────────────────────────────────────── --}}
    <div class="flex-1 flex flex-col min-w-0">

        <header class="h-16 shrink-0 flex items-center gap-3 px-4 sm:px-6 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 no-print">
            <button @click="sidebarOpen = true" class="icon-btn lg:hidden">
                <x-ui.icon name="menu" class="w-5 h-5" />
            </button>

            {{-- Breadcrumb / page title --}}
            <div class="min-w-0">
                @if ($breadcrumbGroup ?? null)
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        {{ $breadcrumbGroup }}
                    </p>
                @endif
                <h1 class="text-sm font-bold truncate text-slate-900 dark:text-white leading-tight">
                    {{ $title ?? config('app.name') }}
                </h1>
            </div>

            <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
                {{-- Search --}}
                <div class="relative hidden md:block">
                    <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                    <input type="text" placeholder="Search SKU…" class="input !pl-9 w-56 !bg-slate-50 dark:!bg-slate-800 !text-xs">
                </div>

                {{-- Dark mode toggle --}}
                <button @click="darkMode = !darkMode" class="icon-btn" title="Toggle theme">
                    <x-ui.icon name="moon" class="w-5 h-5 dark:hidden" />
                    <x-ui.icon name="sun" class="w-5 h-5 hidden dark:block" />
                </button>

                {{-- Notifications --}}
                <button class="icon-btn relative" title="Notifications">
                    <x-ui.icon name="bell" class="w-5 h-5" />
                    <span class="absolute top-2 right-2 w-2 h-2 rounded-full bg-simba-600 ring-2 ring-white dark:ring-slate-900"></span>
                </button>

                {{-- User menu --}}
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button
                        @click="open = !open"
                        class="flex items-center gap-2.5 pl-2 sm:pl-3 sm:border-l border-slate-200 dark:border-slate-800"
                    >
                        <div class="w-8 h-8 rounded-full bg-simba-600 text-white font-bold flex items-center justify-center text-xs">
                            {{ auth()->user()?->initials() ?? 'U' }}
                        </div>
                        <div class="hidden md:block text-left">
                            <p class="text-xs font-semibold leading-tight text-slate-800 dark:text-slate-100">{{ auth()->user()?->name ?? 'User' }}</p>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 leading-tight">{{ auth()->user()?->email ?? '' }}</p>
                        </div>
                        <x-ui.icon name="chevron-down" class="w-4 h-4 text-slate-400 hidden md:block" />
                    </button>

                    <div x-show="open" x-transition class="absolute right-0 mt-2 w-52 card p-1.5 z-50" style="display:none">
                        <div class="px-3 py-2 mb-1 border-b border-slate-100 dark:border-slate-800">
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
            <div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
                {{ $slot }}
            </div>
        </main>
    </div>
</div>

{{-- Toast notifications --}}
<livewire:toast />

@fluxScripts
@livewireScripts
</body>
</html>
