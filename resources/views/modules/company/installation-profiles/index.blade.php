<x-company-layout title="Perfiles de sitio">
    @include('modules.company.settings.partials.nav-slots', ['companyNavActive' => 'perfiles'])

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 rounded-xl border border-slate-800 bg-slate-900 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-800">
                <h3 class="text-sm font-semibold text-white">Perfiles de sitio</h3>
                <p class="text-xs text-slate-500 mt-1">Definen reglas de cada instalación (DANE, Observatorio comunidad). Se eligen al crear o editar una instalación.</p>
            </div>
            <div class="divide-y divide-slate-800">
                @forelse ($profiles as $profile)
                    <div x-data="{ editing: false }" class="px-4 py-3">
                        <div x-show="!editing" class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-3 text-sm">
                                <span class="text-white font-medium">{{ $profile->name }}</span>
                                @if ($profile->is_system)
                                    <span class="text-[10px] uppercase tracking-wide px-2 py-0.5 rounded-full bg-slate-800 text-slate-400">Sistema</span>
                                @endif
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $profile->is_active ? 'bg-emerald-900/40 text-emerald-300' : 'bg-rose-900/40 text-rose-300' }}">
                                    {{ $profile->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                                @if ($profile->requires_dane)
                                    <span class="text-xs text-amber-300/90">DANE</span>
                                @endif
                                @if ($profile->observatory_community)
                                    <span class="text-xs text-sky-300/90">Observatorio</span>
                                @endif
                                <span class="text-xs text-slate-500">{{ $profile->installations_count }} instalación{{ $profile->installations_count === 1 ? '' : 'es' }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                @if (! $profile->is_system)
                                    <button type="button" @click="editing = true" class="text-xs text-indigo-300 hover:text-indigo-200">Editar</button>
                                    <form method="POST" action="{{ route('company.installation-profiles.destroy', $profile) }}" onsubmit="return confirm('¿Eliminar este perfil?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-rose-400 hover:text-rose-300">Eliminar</button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-600">Fijo</span>
                                @endif
                            </div>
                        </div>

                        @if (! $profile->is_system)
                            <form x-show="editing" x-cloak method="POST" action="{{ route('company.installation-profiles.update', $profile) }}" class="space-y-3">
                                @csrf
                                @method('PUT')
                                <div>
                                    <label class="block text-xs text-slate-400 mb-1">Nombre</label>
                                    <input type="text" name="name" value="{{ old('name', $profile->name) }}" required class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
                                </div>
                                <div class="flex flex-wrap gap-4">
                                    <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                                        <input type="hidden" name="requires_dane" value="0">
                                        <input type="checkbox" name="requires_dane" value="1" @checked(old('requires_dane', $profile->requires_dane)) class="rounded border-slate-700 text-indigo-600">
                                        Requiere DANE
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                                        <input type="hidden" name="observatory_community" value="0">
                                        <input type="checkbox" name="observatory_community" value="1" @checked(old('observatory_community', $profile->observatory_community)) class="rounded border-slate-700 text-indigo-600">
                                        Observatorio comunidad
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $profile->is_active)) class="rounded border-slate-700 text-indigo-600">
                                        Activo
                                    </label>
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500">Guardar</button>
                                    <button type="button" @click="editing = false" class="rounded-lg bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-700">Cancelar</button>
                                </div>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="px-4 py-8 text-sm text-slate-500 text-center">Sin perfiles.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-900 p-4 h-fit">
            <h3 class="text-sm font-semibold text-white mb-3">Nuevo perfil</h3>
            <form method="POST" action="{{ route('company.installation-profiles.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Nombre</label>
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="Clínica" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
                    <x-ui.field-error :messages="$errors->get('name')" />
                </div>
                <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                    <input type="hidden" name="requires_dane" value="0">
                    <input type="checkbox" name="requires_dane" value="1" @checked(old('requires_dane')) class="rounded border-slate-700 text-indigo-600">
                    Requiere DANE
                </label>
                <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                    <input type="hidden" name="observatory_community" value="0">
                    <input type="checkbox" name="observatory_community" value="1" @checked(old('observatory_community')) class="rounded border-slate-700 text-indigo-600">
                    Observatorio comunidad
                </label>
                <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-slate-700 text-indigo-600">
                    Activo
                </label>
                <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    Crear perfil
                </button>
            </form>
        </div>
    </div>
</x-company-layout>
