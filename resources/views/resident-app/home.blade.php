<x-layouts.resident-app title="Inicio">
    <div class="space-y-4">
        <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
            <p class="text-xs text-slate-500">Hola</p>
            <p class="text-lg font-semibold text-white truncate">{{ $appUser?->member?->full_name ?? $appUser?->username }}</p>
            <p class="mt-1 text-xs text-slate-400 truncate">{{ $appUser?->member?->structure?->full_path ?? '—' }}</p>
            <p class="text-[11px] text-slate-500 truncate">{{ $appUser?->client?->name }}</p>
        </section>

        <section class="grid grid-cols-2 gap-3">
            <a href="{{ route('resident-app.authorizations') }}" class="rounded-2xl border border-slate-800 bg-slate-900 p-4 active:border-teal-600 active:bg-slate-800">
                <p class="text-sm font-medium text-white">Autorizar</p>
                <p class="mt-1 text-xs text-slate-500">Persona o vehículo</p>
            </a>
            <a href="{{ route('resident-app.correspondence') }}" class="rounded-2xl border border-slate-800 bg-slate-900 p-4 active:border-teal-600 active:bg-slate-800">
                <p class="text-sm font-medium text-white">Correspondencia</p>
                <p class="mt-1 text-xs text-slate-500">Paquetes en portería</p>
            </a>
            <a href="{{ route('resident-app.bookings') }}" class="rounded-2xl border border-slate-800 bg-slate-900 p-4 active:border-teal-600 active:bg-slate-800">
                <p class="text-sm font-medium text-white">Reservas</p>
                <p class="mt-1 text-xs text-slate-500">Zonas comunes</p>
            </a>
            <a href="{{ route('resident-app.blocklist') }}" class="rounded-2xl border border-slate-800 bg-slate-900 p-4 active:border-teal-600 active:bg-slate-800">
                <p class="text-sm font-medium text-white">Bloqueos</p>
                <p class="mt-1 text-xs text-slate-500">Consulta activa</p>
            </a>
            <a href="{{ route('resident-app.unit') }}" class="rounded-2xl border border-slate-800 bg-slate-900 p-4 active:border-teal-600 active:bg-slate-800">
                <p class="text-sm font-medium text-white">Mi unidad</p>
                <p class="mt-1 text-xs text-slate-500">Personas y vehículos</p>
            </a>
            <a href="{{ route('resident-app.profile') }}" class="rounded-2xl border border-slate-800 bg-slate-900 p-4 active:border-teal-600 active:bg-slate-800">
                <p class="text-sm font-medium text-white">Perfil</p>
                <p class="mt-1 text-xs text-slate-500">Cuenta y acceso</p>
            </a>
        </section>
    </div>
</x-layouts.resident-app>
