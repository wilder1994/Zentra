<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    @include('partials.theme-boot')
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title }} — Zentra</title>

        @include('partials.favicon')
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-slate-100">
        <div class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-8 sm:px-6">
            <img
                src="{{ asset('images/welcome/hero-background.png') }}"
                alt=""
                aria-hidden="true"
                class="pointer-events-none fixed inset-0 h-full w-full object-cover object-center opacity-40"
            >
            <div class="pointer-events-none fixed inset-0 bg-slate-950/70"></div>

            <div class="relative z-10 w-full max-w-md">
                <div class="mb-6 text-center">
                    <a href="{{ url('/') }}" class="inline-flex">
                        <img
                            src="{{ asset('images/branding/logo-zentra.png') }}"
                            alt="Zentra"
                            class="mx-auto h-14 w-auto sm:h-16"
                        >
                    </a>
                </div>

                <div class="rounded-2xl border border-white/10 bg-slate-950/75 p-6 shadow-2xl shadow-black/40 backdrop-blur-md sm:p-8">
                    <div class="mb-4 flex justify-end">@include('partials.theme-toggle')</div>
                    <header class="mb-6 space-y-1 text-center">
                        <h2 class="text-2xl font-bold text-white">{{ $title }}</h2>
                        @if ($subtitle)
                            <p class="text-sm text-slate-400">{{ $subtitle }}</p>
                        @endif
                    </header>

                    {{ $slot }}

                    <p class="mt-6 text-center">
                        <a
                            href="{{ url('/') }}"
                            class="text-sm font-medium text-cyan-400 transition hover:text-cyan-300"
                        >
                            &larr; Volver al inicio
                        </a>
                    </p>
                </div>

                <p class="mt-4 text-center text-xs text-slate-500">
                    Acceso restringido a usuarios autorizados.
                </p>
            </div>
        </div>
        <x-ui.flash-toasts />
    </body>
</html>
