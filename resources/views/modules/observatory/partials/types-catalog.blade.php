@php
    $canEdit = $canEdit ?? false;
    $accentBtn = ($accent ?? 'teal') === 'indigo' ? 'bg-indigo-600' : 'bg-teal-600';
@endphp
<div class="grid lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 rounded-xl border border-slate-800 bg-slate-900 overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-800">
            <h3 class="text-sm font-semibold text-white">Tipos de hecho</h3>
            <p class="text-xs text-slate-500 mt-1">Nombre, nivel (1–3) y color del pin. El nivel suma en el tablero y en el calor de riesgo.</p>
        </div>
        <div class="divide-y divide-slate-800">
            @forelse ($types as $type)
                <div x-data="{ editing: false }" class="px-4 py-3">
                    <div x-show="!editing" class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-3 text-sm">
                            <span class="h-3 w-3 rounded-full shrink-0" style="background: {{ $type->color }}"></span>
                            <span class="text-white font-medium">{{ $type->name }}</span>
                            <span class="text-xs text-slate-400">Nivel {{ $type->level }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $type->is_active ? 'bg-emerald-900/40 text-emerald-300' : 'bg-rose-900/40 text-rose-300' }}">
                                {{ $type->is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </div>
                        @if ($canEdit)
                            <div class="flex items-center gap-2">
                                <button type="button" @click="editing = true" class="text-xs {{ ($accent ?? 'teal') === 'indigo' ? 'text-indigo-300' : 'text-teal-300' }}">Editar</button>
                                <form method="POST" action="{{ route($destroyRoute, $type) }}" onsubmit="return confirm('¿Eliminar este tipo?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-rose-400">Eliminar</button>
                                </form>
                            </div>
                        @endif
                    </div>
                    @if ($canEdit)
                        <form x-show="editing" x-cloak method="POST" action="{{ route($updateRoute, $type) }}" class="grid sm:grid-cols-4 gap-2">
                            @csrf
                            @method('PUT')
                            <input type="text" name="name" value="{{ old('name', $type->name) }}" required class="h-9 rounded-lg bg-slate-950 border border-slate-700 px-3 text-sm text-white">
                            <select name="level" class="h-9 rounded-lg bg-slate-950 border border-slate-700 px-2 text-sm text-white">
                                @foreach ([1, 2, 3] as $level)
                                    <option value="{{ $level }}" @selected((int) old('level', $type->level) === $level)>Nivel {{ $level }}</option>
                                @endforeach
                            </select>
                            <input type="color" name="color" value="{{ old('color', $type->color) }}" class="h-9 w-full rounded-lg bg-slate-950 border border-slate-700">
                            <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $type->is_active)) class="rounded border-slate-700 text-teal-600">
                                Activo
                            </label>
                            <div class="sm:col-span-4 flex gap-2">
                                <button type="submit" class="h-9 px-3 rounded-lg {{ $accentBtn }} text-xs font-semibold text-white">Guardar</button>
                                <button type="button" @click="editing = false" class="h-9 px-3 rounded-lg bg-slate-800 text-xs font-semibold text-slate-300">Cancelar</button>
                            </div>
                        </form>
                    @endif
                </div>
            @empty
                <p class="px-4 py-8 text-sm text-slate-500 text-center">Sin tipos.</p>
            @endforelse
        </div>
    </div>
    @if ($canEdit)
        <div class="rounded-xl border border-slate-800 bg-slate-900 p-4 h-fit">
            <h3 class="text-sm font-semibold text-white mb-3">Nuevo tipo</h3>
            <form method="POST" action="{{ $storeUrl ?? route($storeRoute) }}" class="space-y-3">
                @csrf
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="Porte de arma" class="w-full h-9 rounded-lg bg-slate-950 border border-slate-700 px-3 text-sm text-white">
                <x-ui.field-error :messages="$errors->get('name')" />
                <select name="level" class="w-full h-9 rounded-lg bg-slate-950 border border-slate-700 px-2 text-sm text-white">
                    @foreach ([1, 2, 3] as $level)
                        <option value="{{ $level }}" @selected((int) old('level', 2) === $level)>Nivel {{ $level }}</option>
                    @endforeach
                </select>
                <label class="block text-xs text-slate-400">
                    Color del pin
                    <input type="color" name="color" value="{{ old('color', $nextColor) }}" class="mt-1 h-9 w-full rounded-lg bg-slate-950 border border-slate-700">
                </label>
                <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-slate-700 text-teal-600">
                    Activo en el link
                </label>
                <button type="submit" class="w-full h-9 rounded-lg {{ $accentBtn }} text-sm font-semibold text-white">Crear tipo</button>
            </form>
        </div>
    @else
        <p class="text-sm text-slate-500">{{ $readOnlyNote ?? 'Solo el administrador del cliente puede crear o cambiar tipos.' }}</p>
    @endif
</div>
