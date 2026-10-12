<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@include('partials.theme-boot')
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Zentra' }} — {{ config('app.name') }}</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-slate-950 text-slate-100 min-h-screen">
    <header class="border-b border-white/10 bg-slate-950/80 backdrop-blur-md sticky top-0 z-20">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3 sm:px-6">
            <a href="{{ url('/') }}" class="inline-flex items-center gap--2">
                <img src="{{ asset('images/branding/logo-zentra.png') }}" alt="Zentra" class="h-9 w-auto">
            </a>
            <nav class="flex items-center gap-3 text-sm">
                @include('partials.theme-toggle')
                <a href="{{ route('planes.index') }}" class="text-slate-300 hover:text-white">Planes</a>
                @guest
                    <a href="{{ route('planes.cart') }}" class="text-slate-300 hover:text-white" x-data="planCartBadge">
                        Carrito <span class="ui-chip ui-chip-accent" x-show="count > 0" x-text="count" x-cloak></span>
                    </a>
                @endguest
                @auth
                    <a href="{{ route('home') }}" class="rounded-lg bg-cyan-500 px-4 py-2 font-semibold text-slate-950">Ir al panel</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg bg-cyan-500 px-4 py-2 font-semibold text-slate-950">Iniciar sesión</a>
                @endauth
            </nav>
        </div>
    </header>

    <x-ui.flash-toasts rail="mx-auto max-w-5xl px-4 sm:px-6" />

    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        @yield('content')
    </main>

    <footer class="border-t border-white/10 py-6 text-center text-xs text-slate-500">
        Zentra &copy; {{ date('Y') }} · WCodex · Innovative Software Solutions
    </footer>
    @stack('scripts')
</body>
</html>
