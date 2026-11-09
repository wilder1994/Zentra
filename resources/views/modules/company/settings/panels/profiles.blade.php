<div class="grid gap-4 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-xl border border-slate-800 bg-slate-950/50 overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-800">
            <h4 class="text-sm font-semibold text-white">{{ $catalogLabel }}</h4>
            <p class="text-xs text-slate-500 mt-1">{{ $catalogBlurb }}</p>
        </div>
        <div class="divide-y divide-slate-800">
            @foreach ($profiles as $profile)
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
                            <span class="text-xs text-slate-500">{{ $profile->installations_count }} {{ $profile->installations_count === 1 ? 'instalación' : 'instalaciones' }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            @if (! $profile->is_system && $canManage)
                                <button type="button" @click="editing = true" class="text-xs text-indigo-300">Editar</button>
                                <form method="POST" action="{{ route('company.installation-profiles.destroy', $profile) }}" onsubmit="return confirm('¿Eliminar este perfil?')">
                                    @csrf
                                    @method('DELETE')
                                    @include('modules.company.settings.panels.partials.hub-token')
                                    <button type="submit" class="text-xs text-rose-400">Eliminar</button>
                                </form>
                            @elseif ($profile->is_system)
                                <span class="text-xs text-slate-600">Fijo</span>
                            @endif
                        </div>
                    </div>
                    @if (! $profile->is_system && $canManage)
                        <form x-show="editing" x-cloak method="POST" action="{{ route('company.installation-profiles.update', $profile) }}" class="space-y-3">
                            @csrf
                            @method('PUT')
                            @include('modules.company.settings.panels.partials.hub-token')
                            <input type="text" name="name" value="{{ $profile->name }}" required class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
                            <div class="flex flex-wrap gap-4">
                                <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                                    <input type="hidden" name="requires_dane" value="0">
                                    <input type="checkbox" name="requires_dane" value="1" @checked($profile->requires_dane) class="rounded border-slate-700 text-indigo-600">
                                    Requiere DANE
                                </label>
                                <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                                    <input type="hidden" name="observatory_community" value="0">
                                    <input type="checkbox" name="observatory_community" value="1" @checked($profile->observatory_community) class="rounded border-slate-700 text-indigo-600">
                                    Observatorio comunidad
                                </label>
                                <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" @checked($profile->is_active) class="rounded border-slate-700 text-indigo-600">
                                    Activo
                                </label>
                            </div>
                            <div class="flex gap-2">
                                <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white">Guardar</button>
                                <button type="button" @click="editing = false" class="rounded-lg bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-300">Cancelar</button>
                            </div>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    @if ($canManage)
        <div class="rounded-xl border border-slate-800 bg-slate-950/50 p-4 h-fit">
            <h4 class="text-sm font-semibold text-white mb-3">Nuevo perfil</h4>
            <form method="POST" action="{{ route('company.installation-profiles.store') }}" class="space-y-3">
                @csrf
                @include('modules.company.settings.panels.partials.hub-token')
                <input type="text" name="name" required placeholder="Clínica" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
                <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                    <input type="hidden" name="requires_dane" value="0">
                    <input type="checkbox" name="requires_dane" value="1" class="rounded border-slate-700 text-indigo-600">
                    Requiere DANE
                </label>
                <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                    <input type="hidden" name="observatory_community" value="0">
                    <input type="checkbox" name="observatory_community" value="1" class="rounded border-slate-700 text-indigo-600">
                    Observatorio comunidad
                </label>
                <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-700 text-indigo-600">
                    Activo
                </label>
                <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Crear perfil</button>
            </form>
        </div>
    @endif
</div>
