@php
    $tints = 7;
@endphp
<div
    class="expediente-files"
    x-data="{ open: null, creating: false, editingFolder: null, creatingType: null, editingType: null }"
>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h4 class="text-sm font-semibold text-white">{{ $catalogLabel }}</h4>
            <p class="text-xs text-slate-500 mt-1">Elige una carpeta para ver sus tipos. Desactivar la oculta al indexar, sin borrarla.</p>
        </div>
        @if ($canManage)
            <button type="button" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500" @click="creating = !creating">
                Crear carpeta
            </button>
        @endif
    </div>

    @if ($canManage)
        <form x-show="creating" x-cloak method="POST" action="{{ route('company.personnel-folders.store') }}" class="flex flex-wrap items-center gap-2">
            @csrf
            @include('modules.company.settings.panels.partials.hub-token')
            <input type="text" name="name" required maxlength="120" placeholder="Nombre de la carpeta" class="min-w-[16rem] flex-1 rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
            <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white">Guardar</button>
        </form>
    @endif

    <div class="expediente-file-list">
        @forelse ($folders as $folder)
            @php
                $folderCount = (int) ($folderCounts[$folder->slug] ?? 0);
                $tint = $loop->index % $tints;
            @endphp
            <button
                type="button"
                class="expediente-file {{ $folder->is_active ? '' : 'is-off' }}"
                @click="open = {{ $folder->id }}; editingFolder = null; creatingType = null; editingType = null"
            >
                <span class="expediente-file-icon expediente-tint-{{ $tint }}" aria-hidden="true"></span>
                <span class="expediente-file-name">{{ $folder->name }}</span>
                <span class="expediente-file-meta">
                    {{ $folder->is_active ? $folder->types->count().' tipos' : 'Desactivada' }}
                </span>
            </button>
        @empty
            <p class="text-sm text-slate-500">No hay carpetas.</p>
        @endforelse
    </div>

    @foreach ($folders as $folder)
        @php
            $folderCount = (int) ($folderCounts[$folder->slug] ?? 0);
        @endphp
        <div class="expediente-modal" x-show="open === {{ $folder->id }}" x-cloak @click.self="open = null">
            <div class="expediente-modal-card" role="dialog" aria-modal="true" aria-label="{{ $folder->name }}">
                <div class="expediente-modal-bar">
                    <div class="min-w-0">
                        <p class="text-white font-semibold truncate">{{ $folder->name }}</p>
                        <p class="text-xs text-slate-500">{{ $folderCount }} {{ $folderCount === 1 ? 'documento' : 'documentos' }} · {{ $folder->is_active ? 'Activa' : 'Desactivada' }}</p>
                    </div>
                    <button type="button" class="folder-link" @click="open = null">Cerrar</button>
                </div>
                <div class="expediente-modal-body">
                    @if ($canManage)
                        <div class="flex flex-wrap items-center gap-2 mb-3">
                            <button type="button" class="folder-link" @click="editingFolder = editingFolder === {{ $folder->id }} ? null : {{ $folder->id }}">Editar</button>
                            @if ($folder->is_active)
                                <form method="POST" action="{{ route('company.personnel-folders.deactivate', $folder) }}">
                                    @csrf
                                    @include('modules.company.settings.panels.partials.hub-token')
                                    <button type="submit" class="folder-link">Desactivar</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('company.personnel-folders.activate', $folder) }}">
                                    @csrf
                                    @include('modules.company.settings.panels.partials.hub-token')
                                    <button type="submit" class="folder-link">Activar</button>
                                </form>
                            @endif
                            @if ($folderCount === 0)
                                <form method="POST" action="{{ route('company.personnel-folders.destroy', $folder) }}" onsubmit="return confirm('¿Eliminar esta carpeta?')">
                                    @csrf
                                    @method('DELETE')
                                    @include('modules.company.settings.panels.partials.hub-token')
                                    <button type="submit" class="folder-link is-danger">Eliminar</button>
                                </form>
                            @endif
                            <button type="button" class="folder-link" @click="creatingType = creatingType === {{ $folder->id }} ? null : {{ $folder->id }}">Crear tipo</button>
                        </div>
                        <form x-show="editingFolder === {{ $folder->id }}" x-cloak method="POST" action="{{ route('company.personnel-folders.update', $folder) }}" class="mb-3 flex flex-wrap gap-2">
                            @csrf
                            @method('PUT')
                            @include('modules.company.settings.panels.partials.hub-token')
                            <input type="text" name="name" value="{{ $folder->name }}" required maxlength="120" class="min-w-[12rem] flex-1 rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
                            <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white">Guardar</button>
                        </form>
                        <form x-show="creatingType === {{ $folder->id }}" x-cloak method="POST" action="{{ route('company.personnel-folders.types.store', $folder) }}" class="mb-3 flex flex-wrap gap-2">
                            @csrf
                            @include('modules.company.settings.panels.partials.hub-token')
                            <input type="text" name="name" required maxlength="160" placeholder="Nombre del tipo" class="min-w-[12rem] flex-1 rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
                            <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white">Guardar</button>
                        </form>
                    @endif

                    @forelse ($folder->types as $type)
                        @php
                            $typeCount = (int) ($typeCounts[$folder->slug.'|'.$type->slug] ?? 0);
                        @endphp
                        <div class="expediente-type-row">
                            <div class="min-w-0">
                                <p class="text-sm text-white">{{ $type->name }}</p>
                                <p class="text-xs text-slate-500">{{ $type->is_active ? 'Activo' : 'Desactivado' }} · {{ $typeCount }} {{ $typeCount === 1 ? 'documento' : 'documentos' }}</p>
                            </div>
                            @if ($canManage)
                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button" class="folder-link" @click="editingType = editingType === {{ $type->id }} ? null : {{ $type->id }}">Editar</button>
                                    @if ($type->is_active)
                                        <form method="POST" action="{{ route('company.personnel-folder-types.deactivate', $type) }}">
                                            @csrf
                                            @include('modules.company.settings.panels.partials.hub-token')
                                            <button type="submit" class="folder-link">Desactivar</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('company.personnel-folder-types.activate', $type) }}">
                                            @csrf
                                            @include('modules.company.settings.panels.partials.hub-token')
                                            <button type="submit" class="folder-link">Activar</button>
                                        </form>
                                    @endif
                                    @if ($typeCount === 0)
                                        <form method="POST" action="{{ route('company.personnel-folder-types.destroy', $type) }}" onsubmit="return confirm('¿Eliminar este tipo?')">
                                            @csrf
                                            @method('DELETE')
                                            @include('modules.company.settings.panels.partials.hub-token')
                                            <button type="submit" class="folder-link is-danger">Eliminar</button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>
                        @if ($canManage)
                            <form x-show="editingType === {{ $type->id }}" x-cloak method="POST" action="{{ route('company.personnel-folder-types.update', $type) }}" class="mb-2 flex flex-wrap gap-2">
                                @csrf
                                @method('PUT')
                                @include('modules.company.settings.panels.partials.hub-token')
                                <input type="text" name="name" value="{{ $type->name }}" required maxlength="160" class="min-w-[12rem] flex-1 rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
                                <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white">Guardar</button>
                            </form>
                        @endif
                    @empty
                        <p class="text-sm text-slate-500">Esta carpeta no tiene tipos.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endforeach
</div>
