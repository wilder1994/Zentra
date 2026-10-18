<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@include('partials.theme-boot')
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Panel cliente' }} — {{ config('app.name') }}</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="font-sans antialiased bg-slate-950 text-slate-100 overflow-hidden">
    @include('partials.ops-live-surface', [
        'opsCompanyId' => isset($activeClient) ? $activeClient->security_company_id : auth()->user()?->security_company_id,
        'opsClientId' => isset($activeClient) ? $activeClient->id : null,
    ])
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
                        <h1 class="text-lg font-semibold text-white">Panel cliente</h1>
                        @isset($activeClient)
                            <p class="text-xs text-indigo-300 mt-1 truncate" title="{{ $activeClient->name }}">{{ $activeClient->name }}</p>
                        @endisset
                        </div>
                        @include('partials.sidebar-mobile-close')
                    </div>
                    <nav class="flex-1 min-h-0 px-4 py-6 space-y-1 overflow-y-auto sidebar-scroll" @click="onNavClick($event)">
                        @foreach (config('access.navigation.client.items', []) as $item)
                            @php
                                $module = $item['module'] ?? null;
                                $moduleOk = $module === null || (isset($activeClient) && $activeClient->panelModuleEnabled($module));
                            @endphp
                            @if ($moduleOk)
                            @canany(array_values(array_filter([$item['permission'] ?? null, $item['any'] ?? null])))
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs(str_replace('.index', '.*', $item['route'])) || request()->routeIs($item['route']) ? 'bg-teal-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                                <span>{{ $item['label'] }}</span>
                            </a>
                            @endcanany
                            @endif
                        @endforeach
                        @if (isset($activeClient) && $activeClient->has_access && $activeClient->show_personnel_folders)
                        @can('company.documents.view')
                        <a href="{{ route('client.personnel-documents.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('client.personnel-documents.*') ? 'bg-teal-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                            <span>Documentos</span>
                        </a>
                        @endcan
                        @endif
                    </nav>
                    @include('partials.ops-live-alerts', [
                        'opsPoll' => auth()->check() ? route('client.ops.alerts') : null,
                        'opsPanicUrl' => auth()->check() ? route('client.ops.panic') : '',
                        'showPanic' => true,
                    ])
                    @include('partials.sidebar-user')
                </div>
            </aside>
            @include('partials.sidebar-toggle')
        </div>

        <div class="flex-1 flex flex-col min-w-0 min-h-0 overflow-y-auto">
            @include('partials.company-service-suspended')
            @include('partials.operate-return-banner')
            @php
                $rail = ($wide ?? false)
                    ? 'w-full px-4 sm:px-6 lg:px-8'
                    : 'max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8';
            @endphp
            <header class="sticky top-0 z-10 shrink-0">
                <div class="bg-slate-900/80 border-b border-slate-800 backdrop-blur">
                    <div class="{{ $rail }} py-3 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3 min-w-0">
                            @include('partials.sidebar-hamburger')
                            <div class="min-w-0">
                            @isset($header)
                                {{ $header }}
                            @else
                                <h2 class="text-base font-semibold text-white truncate">{{ $title ?? 'Panel cliente' }}</h2>
                            @endisset
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
                    <div class="{{ $rail }} flex flex-wrap items-start gap-1.5 -mt-px pt-0 pb-2">
                        {{ $headerTabs }}
                    </div>
                @endisset
            </header>

            <x-ui.flash-toasts :rail="$rail" />

            <main class="flex-1 {{ $rail }} py-6">
                {{ $slot }}
            </main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
