<x-layouts.resident-app title="Ingresar">
    <div class="flex flex-col justify-center gap-6 py-8">
        <div>
            <p class="text-2xl font-semibold tracking-tight text-white">Zentra</p>
            <p class="mt-1 text-sm text-slate-400">Acceso de residentes</p>
        </div>

        <form method="POST" action="{{ route('resident-app.login.store') }}" class="space-y-4">
            @csrf
            <div>
                <label for="login" class="block text-xs text-slate-400 mb-1">Usuario</label>
                <input id="login" type="text" name="login" value="{{ old('login') }}" required autocomplete="username"
                       placeholder="nombre.apellido@sufijo"
                       class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-3 text-sm text-white placeholder:text-slate-600">
                @error('login')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password" class="block text-xs text-slate-400 mb-1">Contraseña</label>
                <input id="password" type="password" name="password" required autocomplete="current-password"
                       class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-3 text-sm text-white">
                @error('password')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="w-full rounded-xl bg-teal-600 py-3 text-sm font-semibold text-white hover:bg-teal-500">
                Entrar
            </button>
        </form>
    </div>
</x-layouts.resident-app>
