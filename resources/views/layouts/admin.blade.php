<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@include('partials.theme-boot')
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Panel Plataforma' }} — {{ config('app.name') }}</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-slate-950 text-slate-100 overflow-hidden">
    <div class="h-screen flex overflow-hidden" x-data="panelSidebar">
        @include('partials.sidebar-backdrop')
        <div
            class="fixed inset-y-0 left-0 z-50 h-full transform transition-transform duration-200 ease-out lg:relative lg:z-auto lg:transform-none shrink-0"
            :class="mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            @keydown.escape.window="closeMobile()"
        >
            <aside class="h-full flex flex-col bg-slate-900 border-r border-slate-800 overflow-hidden transition-[width] duration-200 ease-out"
                   :class="open ? 'w-64' : 'w-64 lg:w-0 lg:border-r-0'">
                <div class="w-64 h-full min-h-0 flex flex-col">
                    <div class="px-6 py-5 border-b border-slate-800 shrink-0 flex items-start justify-between gap-2">
                        <div class="min-w-0">
                        <p class="text-xs uppercase tracking-wider text-slate-500">Zentra</p>
                        <h1 class="text-lg font-semibold text-white">Panel Plataforma</h1>
                        <p class="text-xs text-violet-300 mt-1">Súper Admin</p>
                        </div>
                        @include('partials.sidebar-mobile-close')
                    </div>
                    <nav class="flex-1 min-h-0 px-4 py-6 space-y-1 overflow-y-auto sidebar-scroll" @click="onNavClick($event)">
                        @foreach (config('access.navigation.admin.items', []) as $item)
                            @can($item['permission'])
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs(str_replace('.index', '.*', $item['route'])) || request()->routeIs($item['route']) ? 'bg-violet-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                                <span>{{ $item['label'] }}</span>
                            </a>
                            @endcan
                        @endforeach
                    </nav>
                    @include('partials.sidebar-user')
                </div>
            </aside>
            @include('partials.sidebar-toggle')
        </div>

        <div class="flex-1 flex flex-col min-w-0 min-h-0 overflow-y-auto">
            <header class="sticky top-0 z-10 shrink-0">
                <div class="bg-slate-900 border-b border-slate-800">
                    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3 min-w-0">
                            @include('partials.sidebar-hamburger')
                            <div class="min-w-0">
                            <h2 class="text-base font-semibold text-white truncate">{{ $title ?? 'Panel Plataforma' }}</h2>
                            <div class="mt-0.5 text-xs flex flex-wrap items-center gap-x-4 gap-y-0.5">
                                @isset($subtitle)
                                    {{ $subtitle }}
                                @else
                                    <span class="text-slate-500">Plataforma · Súper Admin</span>
                                @endisset
                            </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0 flex-wrap justify-end">
                            @isset($actions)
                                {{ $actions }}
                            @endisset
                            @include('partials.theme-toggle')
                        </div>
                    </div>
                </div>

                @isset($headerTabs)
                    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap items-start gap-1.5 -mt-px pt-0 pb-2">
                        {{ $headerTabs }}
                    </div>
                @endisset
            </header>

            <x-ui.flash-toasts />

            @if ($resumeSupportCompany ?? null)
                <div class="fixed bottom-4 right-4 z-50 max-w-sm"
                    x-data="{ show: true, timer: null, start() { this.timer = setTimeout(() => this.show = false, 3000) } }"
                    x-init="start()" x-show="show" x-cloak
                    x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-2"
                    @mouseenter="clearTimeout(timer)" @mouseleave="start()" role="status">
                    <div class="rounded-lg border border-violet-500/40 bg-violet-950/95 px-4 py-3 text-sm text-violet-100 shadow-xl backdrop-blur flex items-center gap-3">
                        <p class="min-w-0">Saliste de <span class="font-medium text-white">{{ $resumeSupportCompany->displayName() }}</span></p>
                        <form method="POST" action="{{ route('admin.companies.enter', $resumeSupportCompany) }}" class="shrink-0">
                            @csrf
                            <button type="submit" class="h-8 px-3 rounded-lg bg-violet-600 text-white text-xs font-medium hover:bg-violet-500">
                                Entrar de nuevo
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            <main class="flex-1 max-w-[1600px] mx-auto w-full px-4 sm:px-6 lg:px-8 py-4 min-h-0 flex flex-col">
                {{ $slot }}
            </main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
