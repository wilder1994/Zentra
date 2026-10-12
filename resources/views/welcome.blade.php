<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    @include('partials.theme-boot')
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Zentra — Accesos, supervisión de campo y observatorio para empresas de seguridad y entidades.">

        <title>Zentra — Accesos, supervisión y observatorio</title>

        @include('partials.favicon')
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-slate-100">
        <div class="relative min-h-screen overflow-x-hidden">
            <img
                src="{{ asset('images/welcome/hero-background.png') }}"
                alt=""
                aria-hidden="true"
                class="pointer-events-none fixed inset-0 h-full w-full object-cover object-center"
            >
            <div class="pointer-events-none fixed inset-0 bg-gradient-to-br from-slate-950/90 via-slate-950/75 to-slate-900/85"></div>
            <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(ellipse_at_top_right,_rgba(6,182,212,0.08),_transparent_50%)]"></div>

            <div class="relative z-10 flex min-h-screen flex-col">
                <header class="shrink-0 border-b border-white/5 bg-slate-950/50 backdrop-blur-md">
                    <div class="mx-auto flex w-full max-w-[96rem] items-center justify-between px-6 py-3 lg:px-8">
                        <a href="{{ url('/') }}" class="inline-flex shrink-0 items-center">
                            <img
                                src="{{ asset('images/branding/logo-zentra.png') }}"
                                alt="Zentra — WCodex"
                                class="h-11 w-auto sm:h-12 lg:h-14"
                            >
                        </a>

                        <nav class="flex items-center gap-3">
                            @include('partials.theme-toggle')
                            @if (Route::has('login'))
                                @auth
                                    <a
                                        href="{{ route('home') }}"
                                        class="rounded-lg bg-cyan-500 px-5 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                                    >
                                        Ir al panel
                                    </a>
                                @else
                                    <a
                                        href="{{ route('login') }}"
                                        class="rounded-lg bg-cyan-500 px-5 py-2.5 text-sm font-semibold text-slate-950 shadow-lg shadow-cyan-500/20 transition hover:bg-cyan-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 sm:px-6"
                                    >
                                        Iniciar sesión
                                    </a>
                                @endauth
                            @endif
                        </nav>
                    </div>
                </header>

                <main class="mx-auto flex w-full max-w-[96rem] flex-1 flex-col px-6 py-8 lg:px-8 lg:py-10">
                    <section class="grid flex-1 grid-cols-1 items-center gap-8 sm:gap-10 lg:grid-cols-5 lg:gap-10">
                        <div class="flex flex-col justify-center gap-6 lg:col-span-2">
                            <div class="space-y-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-400">
                                    PLATAFORMA ZENTRA
                                </p>
                                <h1 class="text-3xl font-bold leading-[1.08] text-white sm:text-4xl lg:text-5xl">
                                    Accesos, supervisión y observatorio
                                </h1>
                                <p class="max-w-md text-sm leading-relaxed text-slate-400 sm:text-base">
                                    Portería, rondas de campo y denuncias de comunidad en un solo panel para la empresa de seguridad.
                                </p>
                            </div>

                            @guest
                                <div class="max-w-md rounded-xl border border-cyan-500 bg-slate-900/95 p-3.5 space-y-2.5">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-md bg-cyan-500/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-cyan-400">
                                            Licencia SaaS
                                        </span>
                                        <span class="rounded-md bg-white/5 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">
                                            Anual −{{ number_format($annualDiscount * 100, 0) }}%
                                        </span>
                                    </div>
                                    <p class="text-sm font-medium text-slate-200 tabular-nums">
                                        Desde {{ '$'.number_format($minMonthlyAmount, 0, ',', '.') }} / mes · 1 instalación
                                    </p>
                                    <a
                                        href="{{ route('planes.index') }}"
                                        class="flex w-full items-center justify-center rounded-lg bg-cyan-500 px-5 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                                    >
                                        Ver planes y contratar
                                    </a>
                                    <a href="{{ route('login') }}" class="block text-center text-xs text-slate-500 hover:text-slate-300">
                                        Ya tengo cuenta · Iniciar sesión
                                    </a>
                                </div>
                            @endguest
                        </div>

                        <div class="relative flex min-h-[16rem] sm:min-h-[22rem] lg:col-span-3 lg:min-h-[28rem]">
                            <div class="absolute -inset-3 rounded-3xl bg-cyan-500/15 blur-3xl"></div>
                            <img
                                src="{{ asset('images/welcome/hero-dashboard.png') }}"
                                alt="Panel de administración central de Zentra"
                                class="relative h-full w-full rounded-xl border border-white/10 object-cover object-center shadow-2xl shadow-black/50 sm:rounded-2xl"
                            >
                        </div>
                    </section>

                    <section class="mt-8 space-y-3">
                        <div class="flex items-end justify-between gap-3">
                            <h2 class="text-lg font-semibold text-white">Planes</h2>
                            <a href="{{ route('planes.index') }}" class="text-sm text-cyan-400 hover:text-cyan-300">Arma tu plan</a>
                        </div>
                        <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($accessMatrix as $row)
                                <li class="rounded-xl border border-white/10 bg-white/5 p-3">
                                    <a href="{{ route('planes.index') }}" class="block">
                                        <p class="text-sm font-semibold text-cyan-300">{{ $row['label'] }}</p>
                                        <p class="text-xs text-slate-500">{{ $row['range'] }} · {{ number_format($row['employees'], 0, ',', '.') }} empleados</p>
                                        <p class="text-xs text-slate-500">{{ $row['posts'] }} puestos por instalación · {{ $row['per_post'] }} empleados por puesto</p>
                                        <p class="mt-1 text-lg font-bold text-white tabular-nums">{{ '$'.number_format($row['unit_net'], 0, ',', '.') }}<span class="text-xs font-normal text-slate-500">/mes por instalación de Accesos</span></p>
                                        <p class="text-[11px] text-emerald-400">−{{ number_format($row['discount'] * 100, 0) }}% · desde {{ '$'.number_format($row['from_monthly'], 0, ',', '.') }}/mes</p>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>

                    <section class="mt-8 pb-2 sm:mt-10">
                        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-5">
                            <li class="group flex gap-4 rounded-xl border border-white/10 bg-white/5 p-4 backdrop-blur-md transition duration-300 hover:border-cyan-400/30 hover:bg-white/[0.07] hover:shadow-lg hover:shadow-cyan-500/5 lg:p-5">
                                <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-cyan-500/15 text-cyan-400 transition group-hover:bg-cyan-500/25">
                                    <svg class="size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-base font-semibold text-cyan-300">Accesos</p>
                                    <p class="mt-1 text-sm leading-snug text-slate-400">Portería, visitantes y vehículos en tiempo real.</p>
                                </div>
                            </li>
                            <li class="group flex gap-4 rounded-xl border border-white/10 bg-white/5 p-4 backdrop-blur-md transition duration-300 hover:border-cyan-400/30 hover:bg-white/[0.07] hover:shadow-lg hover:shadow-cyan-500/5 lg:p-5">
                                <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-cyan-500/15 text-cyan-400 transition group-hover:bg-cyan-500/25">
                                    <svg class="size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.485 1.12 1.37 4.111a1.125 1.125 0 0 0 2.092-.172L21 12.75M4.5 12.75l2.053 6.159a1.125 1.125 0 0 0 2.092.171L12 12.75m-7.5-3 3.75-6.75a1.125 1.125 0 0 1 1.95 0L12 8.25m0 0 1.7-3.06a1.125 1.125 0 0 1 1.95 0L19.5 9.75" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-base font-semibold text-cyan-300">Supervisión</p>
                                    <p class="mt-1 text-sm leading-snug text-slate-400">Rondas, mapa en vivo y app de campo (PWA y APK).</p>
                                </div>
                            </li>
                            <li class="group flex gap-4 rounded-xl border border-white/10 bg-white/5 p-4 backdrop-blur-md transition duration-300 hover:border-cyan-400/30 hover:bg-white/[0.07] hover:shadow-lg hover:shadow-cyan-500/5 sm:col-span-2 lg:col-span-1 lg:p-5">
                                <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-cyan-500/15 text-cyan-400 transition group-hover:bg-cyan-500/25">
                                    <svg class="size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-base font-semibold text-cyan-300">Observatorio</p>
                                    <p class="mt-1 text-sm leading-snug text-slate-400">Reportes de comunidad y tablero por colegio o instalación.</p>
                                </div>
                            </li>
                        </ul>
                    </section>
                </main>

                <footer class="shrink-0 border-t border-white/10 bg-slate-950/40 backdrop-blur-sm">
                    <div class="mx-auto flex w-full max-w-[96rem] flex-col items-center justify-between gap-1 px-6 py-3.5 text-sm text-slate-400 sm:flex-row lg:px-8">
                        <p>Zentra &copy; {{ date('Y') }}</p>
                        <p>WCodex &middot; Innovative Software Solutions</p>
                    </div>
                </footer>
            </div>
        </div>
    </body>
</html>
