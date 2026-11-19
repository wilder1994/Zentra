<div class="space-y-4">
    <div class="rounded-xl border border-slate-800 bg-slate-950/50 px-4 py-3">
        <h4 class="text-sm font-semibold text-white">{{ $catalogLabel }}</h4>
        <p class="text-xs text-slate-500 mt-1">{{ $catalogBlurb }} Una carpeta o un tipo desactivado deja de salir al indexar. Si ya tiene documentos, no se elimina.</p>
    </div>

    @if ($canManage)
        <form method="POST" action="{{ route('company.personnel-folders.store') }}" class="rounded-xl border border-slate-800 bg-slate-950/50 p-4 flex flex-wrap items-end gap-3">
            @csrf
            @include('modules.company.settings.panels.partials.hub-token')
            <div class="min-w-[16rem] flex-1">
                <label class="block text-xs text-slate-400 mb-1" for="new-folder-name">Nueva carpeta</label>
                <input id="new-folder-name" type="text" name="name" required maxlength="120" placeholder="Nombre de la carpeta" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
            </div>
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Crear carpeta</button>
        </form>
    @endif

    @forelse ($folders as $folder)
        @php
            $folderCount = (int) ($folderCounts[$folder->slug] ?? 0);
        @endphp
        <article class="rounded-xl border border-slate-800 bg-slate-950/50 overflow-hidden" x-data="{ editing: false, adding: false }">
            <div class="px-4 py-3 border-b border-slate-800 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <span class="text-white font-medium">{{ $folder->name }}</span>
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $folder->is_active ? 'bg-emerald-900/40 text-emerald-300' : 'bg-rose-900/40 text-rose-300' }}">
                        {{ $folder->is_active ? 'Activa' : 'Desactivada' }}
                    </span>
                    <span class="text-xs text-slate-500">{{ $folderCount }} {{ $folderCount === 1 ? 'documento' : 'documentos' }}</span>
                </div>
                @if ($canManage)
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="editing = !editing" class="text-xs text-indigo-300 hover:text-indigo-200">Editar</button>
                        @if ($folder->is_active)
                            <form method="POST" action="{{ route('company.personnel-folders.deactivate', $folder) }}">
                                @csrf
                                @include('modules.company.settings.panels.partials.hub-token')
                                <button type="submit" class="text-xs text-amber-300 hover:text-amber-200">Desactivar</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('company.personnel-folders.activate', $folder) }}">
                                @csrf
                                @include('modules.company.settings.panels.partials.hub-token')
                                <button type="submit" class="text-xs text-emerald-300 hover:text-emerald-200">Activar</button>
                            </form>
                        @endif
                        @if ($folderCount === 0)
                            <form method="POST" action="{{ route('company.personnel-folders.destroy', $folder) }}" onsubmit="return confirm('¿Eliminar esta carpeta?')">
                                @csrf
                                @method('DELETE')
                                @include('modules.company.settings.panels.partials.hub-token')
                                <button type="submit" class="text-xs text-rose-400 hover:text-rose-300">Eliminar</button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
            @if ($canManage)
                <form x-show="editing" x-cloak method="POST" action="{{ route('company.personnel-folders.update', $folder) }}" class="px-4 py-3 border-b border-slate-800 flex flex-wrap gap-2">
                    @csrf
                    @method('PUT')
                    @include('modules.company.settings.panels.partials.hub-token')
                    <input type="text" name="name" value="{{ $folder->name }}" required maxlength="120" class="min-w-[16rem] flex-1 rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
                    <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white">Guardar</button>
                </form>
            @endif
            <div class="divide-y divide-slate-800">
                @forelse ($folder->types as $type)
                    @php
                        $typeCount = (int) ($typeCounts[$folder->slug.'|'.$type->slug] ?? 0);
                    @endphp
                    <div class="px-4 py-3" x-data="{ editing: false }">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-3 text-sm">
                                <span class="text-slate-200">{{ $type->name }}</span>
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $type->is_active ? 'bg-emerald-900/40 text-emerald-300' : 'bg-rose-900/40 text-rose-300' }}">
                                    {{ $type->is_active ? 'Activo' : 'Desactivado' }}
                                </span>
                                <span class="text-xs text-slate-500">{{ $typeCount }} {{ $typeCount === 1 ? 'documento' : 'documentos' }}</span>
                            </div>
                            @if ($canManage)
                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button" @click="editing = !editing" class="text-xs text-indigo-300 hover:text-indigo-200">Editar</button>
                                    @if ($type->is_active)
                                        <form method="POST" action="{{ route('company.personnel-folder-types.deactivate', $type) }}">
                                            @csrf
                                            @include('modules.company.settings.panels.partials.hub-token')
                                            <button type="submit" class="text-xs text-amber-300 hover:text-amber-200">Desactivar</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('company.personnel-folder-types.activate', $type) }}">
                                            @csrf
                                            @include('modules.company.settings.panels.partials.hub-token')
                                            <button type="submit" class="text-xs text-emerald-300 hover:text-emerald-200">Activar</button>
                                        </form>
                                    @endif
                                    @if ($typeCount === 0)
                                        <form method="POST" action="{{ route('company.personnel-folder-types.destroy', $type) }}" onsubmit="return confirm('¿Eliminar este tipo?')">
                                            @csrf
                                            @method('DELETE')
                                            @include('modules.company.settings.panels.partials.hub-token')
                                            <button type="submit" class="text-xs text-rose-400 hover:text-rose-300">Eliminar</button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>
                        @if ($canManage)
                            <form x-show="editing" x-cloak method="POST" action="{{ route('company.personnel-folder-types.update', $type) }}" class="mt-3 flex flex-wrap gap-2">
                                @csrf
                                @method('PUT')
                                @include('modules.company.settings.panels.partials.hub-token')
                                <input type="text" name="name" value="{{ $type->name }}" required maxlength="160" class="min-w-[16rem] flex-1 rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
                                <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white">Guardar</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="px-4 py-4 text-sm text-slate-500">Sin tipos de selección.</p>
                @endforelse
            </div>
            @if ($canManage)
                <form method="POST" action="{{ route('company.personnel-folders.types.store', $folder) }}" class="px-4 py-3 border-t border-slate-800 flex flex-wrap items-center gap-2">
                    @csrf
                    @include('modules.company.settings.panels.partials.hub-token')
                    <input type="text" name="name" required maxlength="160" placeholder="Nuevo tipo de selección" class="min-w-[16rem] flex-1 rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
                    <button type="submit" class="rounded-lg bg-slate-800 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700">Agregar tipo</button>
                </form>
            @endif
        </article>
    @empty
        <p class="text-sm text-slate-500">No hay carpetas.</p>
    @endforelse
</div>
