@props(['title' => null])

<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <meta name="color-scheme" content="dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>{{ $title ?? 'Zentra' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak]{display:none!important}
        html,body{overflow-x:hidden;overscroll-behavior-y:none;-webkit-tap-highlight-color:transparent}
        .resident-shell input,
        .resident-shell select,
        .resident-shell textarea{font-size:16px!important;line-height:1.4}
        .resident-shell input[type="date"],
        .resident-shell input[type="time"]{min-height:2.75rem}
    </style>
</head>
<body class="resident-shell min-h-dvh bg-slate-950 text-slate-100 antialiased" x-data="{ menu: false }">
    <div class="mx-auto flex min-h-dvh w-full max-w-md flex-col px-4"
         style="padding-top:max(0.75rem,env(safe-area-inset-top,0px));padding-bottom:max(1rem,env(safe-area-inset-bottom,0px));padding-left:max(1rem,env(safe-area-inset-left,0px));padding-right:max(1rem,env(safe-area-inset-right,0px))">
        <header class="mb-3 flex min-h-11 items-center gap-2">
            @auth('resident')
                <button type="button"
                        @click="menu = true"
                        class="-ml-1.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-slate-100 active:bg-slate-800"
                        aria-label="Abrir menú">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" aria-hidden="true">
                        <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/>
                    </svg>
                </button>
            @endauth
            @if ($title)
                <h1 class="min-w-0 flex-1 truncate text-[1.05rem] font-semibold leading-tight text-white">{{ $title }}</h1>
            @else
                <div class="flex-1"></div>
            @endif
            @auth('resident')
                <form method="POST" action="{{ route('resident-app.logout') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="rounded-lg px-2 py-2 text-xs text-slate-400 active:bg-slate-800 active:text-white">Salir</button>
                </form>
            @endauth
        </header>

        @if (session('success'))
            <p class="mb-3 rounded-xl border border-teal-800/60 bg-teal-950/40 px-3 py-2.5 text-sm text-teal-100">{{ session('success') }}</p>
        @endif
        @if (session('error'))
            <p class="mb-3 rounded-xl border border-red-800/60 bg-red-950/40 px-3 py-2.5 text-sm text-red-100">{{ session('error') }}</p>
        @endif

        <main class="min-w-0 flex-1">
            {{ $slot }}
        </main>
    </div>

    @auth('resident')
        <div x-show="menu" x-cloak class="fixed inset-0 z-50" @keydown.escape.window="menu = false">
            <div class="absolute inset-0 bg-black/60" @click="menu = false"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>
            <aside class="absolute inset-y-0 left-0 flex w-[min(18rem,88vw)] flex-col border-r border-slate-800 bg-slate-950 shadow-2xl"
                   style="padding-top:env(safe-area-inset-top,0px);padding-bottom:env(safe-area-inset-bottom,0px)"
                   x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                   x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
                   @click.stop>
                <div class="flex items-center justify-between border-b border-slate-800 px-4 py-4">
                    <div>
                        <p class="text-sm font-semibold text-white">Zentra</p>
                        <p class="text-[11px] text-slate-500">Menú residente</p>
                    </div>
                    <button type="button" class="rounded-lg px-3 py-2 text-xs text-slate-400 active:bg-slate-900 active:text-white" @click="menu = false">Cerrar</button>
                </div>
                <nav class="flex-1 space-y-1 overflow-y-auto p-3 text-sm">
                    @php
                        $items = [
                            ['route' => 'resident-app.home', 'match' => 'resident-app.home', 'label' => 'Inicio'],
                            ['route' => 'resident-app.unit', 'match' => 'resident-app.unit', 'label' => 'Mi unidad'],
                            ['route' => 'resident-app.authorizations', 'match' => 'resident-app.authorizations*', 'label' => 'Autorizar'],
                            ['route' => 'resident-app.correspondence', 'match' => 'resident-app.correspondence', 'label' => 'Correspondencia'],
                            ['route' => 'resident-app.bookings', 'match' => 'resident-app.bookings*', 'label' => 'Reservas'],
                            ['route' => 'resident-app.blocklist', 'match' => 'resident-app.blocklist', 'label' => 'Bloqueos'],
                            ['route' => 'resident-app.profile', 'match' => 'resident-app.profile', 'label' => 'Perfil'],
                        ];
                    @endphp
                    @foreach ($items as $item)
                        <a href="{{ route($item['route']) }}"
                           @click="menu = false"
                           class="block rounded-xl px-3 py-3.5 {{ request()->routeIs($item['match']) ? 'bg-teal-700 text-white' : 'text-slate-300 active:bg-slate-900' }}">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
            </aside>
        </div>

        <script>
            (function () {
                const bootPush = async () => {
                    if (!window.Capacitor?.Plugins?.PushNotifications) return;
                    const Push = window.Capacitor.Plugins.PushNotifications;
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
                    const register = async (token) => {
                        if (!token || !csrf) return;
                        await fetch(@json(route('resident-app.devices.store')), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ push_token: token, platform: 'android' }),
                            credentials: 'same-origin',
                        });
                    };
                    Push.addListener('registration', (t) => register(t.value));
                    const perm = await Push.requestPermissions();
                    if (perm.receive === 'granted') await Push.register();
                };
                document.addEventListener('DOMContentLoaded', () => { bootPush().catch(() => {}); });
            })();
        </script>
    @endauth
</body>
</html>
