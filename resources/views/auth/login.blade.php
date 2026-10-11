@php
    $inputClass = 'mt-1 block w-full rounded-lg border border-white/10 bg-slate-900/60 px-4 py-2.5 text-sm text-white shadow-sm placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400/30';
    $labelClass = 'block text-sm font-medium text-slate-300';
@endphp

<x-auth-layout
    title="Iniciar sesión"
    subtitle="Ingresa con tu cuenta corporativa o de cliente."
>
    <x-auth-session-status class="mb-4 text-sm font-medium text-cyan-300" :status="session('status')" />

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-amber-400/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
            {{ session('status') }}
        </div>
    @endif

    <div x-data="{ takeover: {{ $errors->has('session_takeover') ? 'true' : 'false' }} }">
    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Usuario o correo')" class="{{ $labelClass }}" />
            <x-text-input
                id="email"
                class="{{ $inputClass }}"
                type="text"
                name="email"
                :value="old('email')"
                required
                autofocus
                autocomplete="username"
                placeholder="usuario o correo"
            />
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-sm text-red-400" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Contraseña')" class="{{ $labelClass }}" />
            <x-text-input
                id="password"
                class="{{ $inputClass }}"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="••••••••"
            />
            <x-input-error :messages="$errors->get('password')" class="mt-2 text-sm text-red-400" />
        </div>

        <div class="flex items-center justify-between gap-4">
            <label for="remember_me" class="inline-flex cursor-pointer items-center">
                <input
                    id="remember_me"
                    type="checkbox"
                    name="remember"
                    class="rounded border-white/20 bg-slate-900/60 text-cyan-500 shadow-sm focus:ring-cyan-400/40 focus:ring-offset-0"
                >
                <span class="ms-2 text-sm text-slate-400">Recordarme</span>
            </label>

            @if (Route::has('password.request'))
                <a
                    href="{{ route('password.request') }}"
                    class="text-sm font-medium text-cyan-400 transition hover:text-cyan-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/50 rounded"
                >
                    ¿Olvidaste tu contraseña?
                </a>
            @endif
        </div>

        <button
            type="submit"
            class="flex w-full items-center justify-center rounded-lg bg-cyan-500 px-6 py-3 text-sm font-semibold text-slate-950 shadow-lg shadow-cyan-500/25 transition hover:bg-cyan-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900"
        >
            Ingresar al sistema
        </button>
    </form>

        <div
            x-show="takeover"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4"
        >
            <div class="w-full max-w-sm rounded-2xl border border-white/10 bg-slate-900 p-5 shadow-2xl">
                <p class="text-sm text-slate-200">Tienes una sesión abierta. ¿Cerrar la otra y seguir aquí?</p>
                <form method="POST" action="{{ route('login.takeover') }}" class="mt-4 flex gap-2">
                    @csrf
                    <button type="button" class="flex-1 rounded-lg border border-white/10 px-3 py-2 text-sm text-slate-300" @click="takeover = false">No</button>
                    <button type="submit" class="flex-1 rounded-lg bg-cyan-500 px-3 py-2 text-sm font-semibold text-slate-950">Sí, cerrar la otra</button>
                </form>
            </div>
        </div>
    </div>
</x-auth-layout>
