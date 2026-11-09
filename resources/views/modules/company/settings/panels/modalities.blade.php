<div class="grid gap-4 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-xl border border-slate-800 bg-slate-950/50 overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-800">
            <h4 class="text-sm font-semibold text-white">{{ $catalogLabel }}</h4>
            <p class="text-xs text-slate-500 mt-1">{{ $catalogBlurb }}</p>
        </div>
        <div class="divide-y divide-slate-800">
            @forelse ($modalities as $modality)
                <div x-data="{ editing: false }" class="px-4 py-3">
                    <div x-show="!editing" class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-3 text-sm">
                            <span class="text-white font-medium">{{ $modality->label() }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $modality->is_active ? 'bg-emerald-900/40 text-emerald-300' : 'bg-rose-900/40 text-rose-300' }}">
                                {{ $modality->is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </div>
                        @if ($canManage)
                            <div class="flex items-center gap-2">
                                <button type="button" @click="editing = true" class="text-xs text-indigo-300">Editar</button>
                                <form method="POST" action="{{ route('company.supervision-post-modalities.destroy', $modality) }}" onsubmit="return confirm('¿Eliminar esta modalidad?')">
                                    @csrf
                                    @method('DELETE')
                                    @include('modules.company.settings.panels.partials.hub-token')
                                    <button type="submit" class="text-xs text-rose-400">Eliminar</button>
                                </form>
                            </div>
                        @endif
                    </div>
                    @if ($canManage)
                        <form x-show="editing" x-cloak method="POST" action="{{ route('company.supervision-post-modalities.update', $modality) }}" class="space-y-3">
                            @csrf
                            @method('PUT')
                            @include('modules.company.settings.panels.partials.hub-token')
                            <div class="grid grid-cols-2 gap-2">
                                <input type="number" name="hours" min="1" max="24" value="{{ $modality->hours }}" required class="w-full h-9 rounded-lg bg-slate-950 border border-slate-700 px-3 text-sm text-white">
                                <input type="text" name="name" value="{{ $modality->name }}" maxlength="80" placeholder="Diurno" class="w-full h-9 rounded-lg bg-slate-950 border border-slate-700 px-3 text-sm text-white">
                            </div>
                            <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked($modality->is_active) class="rounded border-slate-700 text-indigo-600">
                                Activo
                            </label>
                            <div class="flex gap-2">
                                <button type="submit" class="h-9 px-3 rounded-lg bg-indigo-600 text-xs font-semibold text-white">Guardar</button>
                                <button type="button" @click="editing = false" class="h-9 px-3 rounded-lg bg-slate-800 text-xs font-semibold text-slate-300">Cancelar</button>
                            </div>
                        </form>
                    @endif
                </div>
            @empty
                <p class="px-4 py-8 text-sm text-slate-500 text-center">Sin modalidades.</p>
            @endforelse
        </div>
    </div>
    @if ($canManage)
        <div class="rounded-xl border border-slate-800 bg-slate-950/50 p-4 h-fit">
            <h4 class="text-sm font-semibold text-white mb-3">Nueva modalidad</h4>
            <form method="POST" action="{{ route('company.supervision-post-modalities.store') }}" class="space-y-3">
                @csrf
                @include('modules.company.settings.panels.partials.hub-token')
                <input type="number" name="hours" min="1" max="24" required placeholder="12" class="w-full h-9 rounded-lg bg-slate-950 border border-slate-700 px-3 text-sm text-white">
                <input type="text" name="name" maxlength="80" placeholder="Nombre opcional" class="w-full h-9 rounded-lg bg-slate-950 border border-slate-700 px-3 text-sm text-white">
                <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-700 text-indigo-600">
                    Activo
                </label>
                <button type="submit" class="w-full h-9 rounded-lg bg-indigo-600 text-sm font-semibold text-white">Crear modalidad</button>
            </form>
        </div>
    @endif
</div>
