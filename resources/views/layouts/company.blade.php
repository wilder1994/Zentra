<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@include('partials.theme-boot')
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Panel Empresa' }} — {{ config('app.name') }}</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .company-shell-rail {
            width: 100%;
            max-width: 100%;
            margin-inline: auto;
            padding-inline: 1rem;
        }
        @media (min-width: 640px) {
            .company-shell-rail { padding-inline: 1.25rem; }
        }
        @media (min-width: 1024px) {
            .company-shell-rail { padding-inline: 1.5rem; }
        }
        @media (min-width: 1280px) {
            .company-shell-rail { padding-inline: 1.75rem; }
        }
        @media (min-width: 1536px) {
            .company-shell-rail {
                padding-inline: 2rem;
                max-width: 100rem;
            }
        }
        @media (min-width: 1920px) {
            .company-shell-rail {
                max-width: 110rem;
                padding-inline: 2.5rem;
            }
        }
    </style>
    @stack('styles')
</head>
<body class="font-sans antialiased bg-slate-950 text-slate-100 overflow-hidden">
    @php
        $onClientsCrud = request()->routeIs('company.clients.index')
            || request()->routeIs('company.clients.show')
            || request()->routeIs('company.clients.create')
            || request()->routeIs('company.clients.edit');
        $onInstallations = request()->routeIs('company.installations.*');
        $onObservatory = request()->routeIs('company.observatory.*');
        $companyContext = $companyContext ?? ['company_name' => null, 'is_quota_full' => true];
        $supportMode = $supportMode ?? ['active' => false, 'company_name' => null, 'company_id' => null];
    @endphp
    @include('partials.ops-live-surface', [
        'opsCompanyId' => $supportMode['company_id'] ?? auth()->user()?->security_company_id,
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
                <h1 class="text-lg font-semibold text-white">Panel Empresa</h1>
                </div>
                @include('partials.sidebar-mobile-close')
            </div>
            <nav class="flex-1 min-h-0 px-4 py-6 space-y-1 overflow-y-auto sidebar-scroll" @click="onNavClick($event)">
                @php $canMod = $canMod ?? []; @endphp
                @if (! empty($canMod['dashboard']))
                @can('company.dashboard')
                <a href="{{ route('company.dashboard') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('company.dashboard') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    <span>Mi empresa</span>
                </a>
                @endcan
                @endif
                @if (! empty($canMod['billing']))
                @can('company.billing.manage')
                <a href="{{ route('company.billing.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('company.billing.*') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    <span>Facturación</span>
                </a>
                @endcan
                @endif
                @if (! empty($canMod['clients']))
                @can('company.clients.view')
                <a href="{{ route('company.clients.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ $onClientsCrud ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    <span>Clientes</span>
                </a>
                @endcan
                @endif
                @if (! empty($canMod['installations']))
                @can('company.installations.view')
                <a href="{{ route('company.installations.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ $onInstallations ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    <span>Instalaciones</span>
                </a>
                @endcan
                @endif
                @if (! empty($canMod['observatory']))
                @can('observatory.view')
                <a href="{{ route('company.observatory.events.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ $onObservatory ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    <span>Observatorio</span>
                </a>
                @endcan
                @endif
                @if (! empty($canMod['supervision']))
                @can('company.supervision.view')
                <a href="{{ route('company.supervision.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('company.supervision.*') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    <span>Supervisión</span>
                </a>
                @endcan
                @endif
                @if (! empty($canMod['panics']))
                @can('ops.panic.attend')
                <a href="{{ route('company.panics.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('company.panics.*') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    <span>Atención de pánicos</span>
                </a>
                @endcan
                @endif
                @if (! empty($canMod['downloads']))
                @can('company.downloads.view')
                <a href="{{ route('company.downloads.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('company.downloads.*') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    <span>Descargas</span>
                </a>
                @endcan
                @endif
                @if (! empty($canMod['employees']))
                @can('company.employees.view')
                <a href="{{ route('company.employees.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('company.employees.*') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    <span>Empleados</span>
                </a>
                @endcan
                @endif
                @if (! empty($canMod['documents']))
                @can('company.documents.view')
                <a href="{{ route('company.personnel-documents.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('company.personnel-documents.*') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    <span>Documentos</span>
                </a>
                @endcan
                @endif
                @if (! empty($canMod['users']))
                @canany(['company.users.view', 'company.users.assign'])
                <a href="{{ route('company.users.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('company.users.*') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    <span>Usuarios</span>
                </a>
                @endcanany
                @endif
                @if (! empty($canMod['profile']))
                @can('company.profile.manage')
                <a href="{{ route('company.settings.edit') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('company.settings.*') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    <span>Mis datos</span>
                </a>
                @endcan
                @endif
                @if (! empty($canMod['settings']))
                @canany(['company.settings.view', 'company.settings.manage'])
                @php
                    $onAjustes = request()->routeIs('company.job-titles.*')
                        || request()->routeIs('company.collaborator-types.*')
                        || request()->routeIs('company.structure-types.*')
                        || request()->routeIs('company.supervision-zones.*')
                        || request()->routeIs('company.supervision-shifts.*')
                        || request()->routeIs('company.supervision-post-modalities.*')
                        || request()->routeIs('company.supervision-preop.*')
                        || request()->routeIs('company.supervision-document-types.*')
                        || request()->routeIs('company.supervision-control-book-types.*')
                        || request()->routeIs('company.supervision-weapon-types.*')
                        || request()->routeIs('company.supervision-weapon-brands.*')
                        || request()->routeIs('company.supervision-risk-types.*')
                        || request()->routeIs('company.supervision-alarm-types.*')
                        || request()->routeIs('company.supervision-support-types.*');
                @endphp
                <a href="{{ route('company.job-titles.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium {{ $onAjustes ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    <span>Ajustes</span>
                </a>
                @endcanany
                @endif
            </nav>
                    @include('partials.ops-live-alerts', [
                        'opsPoll' => auth()->check() ? route('company.ops.alerts') : null,
                        'opsPanicUrl' => auth()->check() ? route('company.ops.panic') : '',
                        'opsClaimUrl' => auth()->check() && auth()->user()?->can('ops.panic.attend')
                            ? route('company.panics.claim')
                            : '',
                        'showPanic' => auth()->user()?->canany(['company.supervision.view', 'observatory.view'])
                            && auth()->user()?->hasAnyRole(['company-admin', 'colaborador']),
                    ])
                    @include('partials.sidebar-user')
                </div>
            </aside>
            @include('partials.sidebar-toggle')
        </div>

        <div class="flex-1 flex flex-col min-w-0 min-h-0 overflow-y-auto">
            @include('partials.company-service-suspended')
            @if (! empty($supportMode['active']))
                <div class="shrink-0 border-b border-amber-800/60 bg-amber-950/50">
                    <div class="company-shell-rail py-2.5 flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-amber-100 truncate">
                                Entraste como · {{ $supportMode['company_name'] ?? 'Empresa' }}
                            </p>
                            <p class="text-xs text-amber-200/70">
                                Panel empresa · súper admin en sesión de soporte · auditado
                            </p>
                        </div>
                        <form method="POST" action="{{ route('admin.support.exit') }}" class="shrink-0">
                            @csrf
                            <x-ui.button type="submit" variant="secondary" size="sm">
                                Salir al panel plataforma
                            </x-ui.button>
                        </form>
                    </div>
                </div>
            @endif
            <header class="sticky top-0 z-10 shrink-0">
                <div class="bg-slate-900/80 border-b border-slate-800 backdrop-blur">
                    <div class="company-shell-rail py-3 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3 min-w-0">
                            @include('partials.sidebar-hamburger')
                            <div class="min-w-0">
                            @isset($header)
                                {{ $header }}
                            @else
                                <h2 class="text-base font-semibold text-white truncate">{{ $title ?? 'Panel Empresa' }}</h2>
                                <div class="mt-0.5 text-xs flex flex-wrap items-center gap-x-4 gap-y-0.5">
                                    @isset($subtitle)
                                        {{ $subtitle }}
                                    @elseif ($companyContext['company_name'])
                                        <span class="text-slate-500 truncate">{{ $companyContext['company_name'] }}</span>
                                    @endif
                                </div>
                            @endisset
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0 flex-wrap justify-end">
                            @include('partials.theme-toggle')
                            <div class="flex items-center gap-2 flex-wrap justify-end {{ ! empty($companyServiceSuspended) ? 'pointer-events-none opacity-40' : '' }}">
                            @isset($actions)
                                {{ $actions }}
                            @elseif (request()->routeIs('company.clients.*') && ! request()->routeIs('company.clients.create'))
                                @can('company.clients.manage')
                                    <x-ui.button :href="route('company.clients.create')" size="sm">
                                        + Cliente
                                    </x-ui.button>
                                @endcan
                            @endif
                            </div>
                        </div>
                    </div>
                </div>

                @isset($headerTabs)
                    <div class="company-shell-rail flex flex-wrap items-start gap-1.5 -mt-px pt-0 pb-2">
                        {{ $headerTabs }}
                    </div>
                @endisset
            </header>

            <x-ui.flash-toasts rail="company-shell-rail" />

            <main class="company-shell-rail flex-1 w-full py-4 sm:py-5 {{ ! empty($companyServiceSuspended) ? 'pointer-events-none select-none opacity-60' : '' }}">
                {{ $slot }}
            </main>
        </div>
    </div>
    @stack('modals')
    @if (request()->routeIs('company.employees.index'))
        @include('modules.company.employees.partials.import-modal')
    @endif
    @if (request()->routeIs('company.personnel-documents.index'))
        @include('modules.company.personnel-documents.partials.parafiscal-import-modal')
    @endif
    @if (request()->routeIs('company.clients.index'))
        @include('modules.company.clients.partials.import-modal')
    @endif
    @stack('scripts')
</body>
</html>
