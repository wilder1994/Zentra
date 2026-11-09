<div class="grid gap-4 lg:grid-cols-2">
    @foreach ([['title' => 'EPP', 'kind' => 'ppe', 'items' => $ppe], ['title' => 'Vehículo', 'kind' => 'vehicle', 'items' => $vehicle]] as $block)
        <div class="rounded-xl border border-slate-800 bg-slate-950/50 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-800">
                <h4 class="text-sm font-semibold text-white">{{ $block['title'] }}</h4>
                <p class="text-xs text-slate-500 mt-1">Ítems al abrir turno.</p>
            </div>
            <div class="divide-y divide-slate-800">
                @forelse ($block['items'] as $item)
                    <div x-data="{ editing: false }" class="px-4 py-3">
                        <div x-show="!editing" class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-3 text-sm">
                                <span class="text-white font-medium">{{ $item->name }}</span>
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $item->is_active ? 'bg-emerald-900/40 text-emerald-300' : 'bg-rose-900/40 text-rose-300' }}">
                                    {{ $item->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>
                            @if ($canManage)
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="editing = true" class="text-xs text-indigo-300">Editar</button>
                                    <form method="POST" action="{{ route('company.supervision-preop.destroy', $item) }}" onsubmit="return confirm('¿Eliminar este ítem?')">
                                        @csrf
                                        @method('DELETE')
                                        @include('modules.company.settings.panels.partials.hub-token')
                                        <button type="submit" class="text-xs text-rose-400">Eliminar</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                        @if ($canManage)
                            <form x-show="editing" x-cloak method="POST" action="{{ route('company.supervision-preop.update', $item) }}" class="space-y-3">
                                @csrf
                                @method('PUT')
                                @include('modules.company.settings.panels.partials.hub-token')
                                <input type="text" name="name" value="{{ $item->name }}" required class="w-full h-9 rounded-lg bg-slate-950 border border-slate-700 px-3 text-sm text-white">
                                <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" @checked($item->is_active) class="rounded border-slate-700 text-indigo-600">
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
                    <p class="px-4 py-6 text-sm text-slate-500 text-center">Sin ítems.</p>
                @endforelse
            </div>
            @if ($canManage)
                <form method="POST" action="{{ route('company.supervision-preop.store') }}" class="p-4 border-t border-slate-800 space-y-2">
                    @csrf
                    @include('modules.company.settings.panels.partials.hub-token')
                    <input type="hidden" name="kind" value="{{ $block['kind'] }}">
                    <input type="text" name="name" required placeholder="Nuevo ítem" class="w-full h-9 rounded-lg bg-slate-950 border border-slate-700 px-3 text-sm text-white">
                    <button type="submit" class="w-full h-9 rounded-lg bg-indigo-600 text-sm font-semibold text-white">Agregar a {{ $block['title'] }}</button>
                </form>
            @endif
        </div>
    @endforeach
</div>
