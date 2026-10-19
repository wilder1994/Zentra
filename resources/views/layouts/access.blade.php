<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@include('partials.theme-boot')
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Control de Acceso' }} — {{ config('app.name') }}</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-slate-950 text-slate-100 overflow-hidden">
    @include('partials.ops-live-surface', [
        'opsCompanyId' => auth()->user()?->security_company_id,
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
                        <h1 class="text-lg font-semibold text-white">Control de Acceso</h1>
                        @isset($activeClient)
                            <p class="text-xs text-indigo-300 mt-1 truncate" title="{{ $activeClient->name }}">{{ $activeClient->name }}</p>
                        @endisset
                        </div>
                        @include('partials.sidebar-mobile-close')
                    </div>
                    <nav class="flex-1 min-h-0 px-4 py-6 space-y-1 overflow-y-auto sidebar-scroll" @click="onNavClick($event)">
                        @foreach (config('access.navigation.access.items', []) as $item)
                            @can($item['permission'])
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs(str_replace('.index', '.*', $item['route'])) || request()->routeIs($item['route']) ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                                <span>{{ $item['label'] }}</span>
                            </a>
                            @endcan
                        @endforeach
                        @can('client.structures.manage')
                        <a href="{{ route('client.dashboard') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-300 hover:bg-slate-800 mt-4">
                            <span>Panel cliente</span>
                        </a>
                        @endcan
                    </nav>
                    @include('partials.ops-live-alerts', [
                        'opsPoll' => auth()->check() ? route('access.ops.alerts') : null,
                        'opsPanicUrl' => auth()->check() ? route('access.ops.panic') : '',
                        'showPanic' => ! auth()->user()?->hasRole('guardia') || ! empty($operatingDoor),
                    ])
                    @include('partials.sidebar-user')
                </div>
            </aside>
            @include('partials.sidebar-toggle')
        </div>

        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
<div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            @include('partials.company-service-suspended')
            @include('partials.operate-return-banner')
            <header class="bg-slate-900/80 border-b border-slate-800 backdrop-blur sticky top-0 z-20 shrink-0">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        @include('partials.sidebar-hamburger')
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold text-white truncate">{{ $title ?? 'Portería' }}</h2>
                            @if(! empty($operatingDoor))
                                <p class="text-xs text-slate-500 truncate">{{ $operatingDoor->name }}@isset($activeClient) · {{ $activeClient->name }}@endisset</p>
                            @else
                                <span class="text-xs text-slate-500">{{ now()->format('d M Y') }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        @isset($actions)
                            {{ $actions }}
                        @endisset
                        @include('partials.theme-toggle')
                    </div>
                </div>
            </header>

            @php
                $shiftUser = Auth::user();
                $turnoService = app(\App\Services\Access\TurnoService::class);
                $shiftRequired = config('access.shifts.enforced') && $shiftUser && ! $turnoService->isShiftOptionalFor($shiftUser);
                $activeShift = $shiftRequired ? $turnoService->currentFor($shiftUser) : null;
            @endphp
            @if($shiftRequired)
                @if($activeShift)
                    <div class="bg-emerald-900/50 border-b border-emerald-700/60">
                        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center justify-between gap-3">
                            <p class="text-xs text-emerald-200">
                                <span class="inline-flex items-center gap-1 font-semibold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                    Turno activo desde las {{ $activeShift->started_at->format('H:i') }}
                                </span>
                                @if($activeShift->location)
                                    · {{ $activeShift->location->name }}
                                @elseif(! empty($operatingDoor))
                                    · {{ $operatingDoor->name }}
                                @endif
                            </p>
                            <a href="{{ route('access.turnos.index') }}#cerrar-turno" class="text-xs font-semibold text-emerald-300 hover:text-emerald-100">Cerrar turno</a>
                        </div>
                    </div>
                @else
                    <div class="bg-amber-900/40 border-b border-amber-700/60">
                        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center justify-between gap-3">
                            <p class="text-xs text-amber-200 font-medium">No tienes un turno abierto. Para operar la portería debes iniciar tu turno.</p>
                            <a href="{{ route('access.turnos.open') }}" class="text-xs font-semibold text-amber-100 hover:text-white bg-amber-700/70 hover:bg-amber-600/80 px-3 py-1.5 rounded-lg transition-colors">Iniciar turno</a>
                        </div>
                    </div>
                @endif
            @endif

            <x-ui.flash-toasts rail="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8" />

            <main class="flex-1 min-w-0 overflow-y-scroll">
                <div class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-6">
                    @if (\App\Http\Middleware\EnsurePorteriaReadOnly::appliesTo(auth()->user()))
                        <div class="mb-4 rounded-lg border border-sky-500/40 bg-sky-500/10 px-3 py-2 text-xs text-sky-200">
                            Portería en <strong>solo lectura</strong>: puedes ver todo, pero los registros los hacen los vigilantes.
                        </div>
                    @endif
                    {{ $slot }}
                </div>
            </main>
        </div>
        </div>
    </div>

    @stack('scripts')
</body>
</html>