<x-company-layout title="Presentación personal">
    @include('modules.company.settings.partials.nav-slots', ['companyNavActive' => 'presentacion'])

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 rounded-xl border border-slate-800 bg-slate-900 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-800">
                <h3 class="text-sm font-semibold text-white">Prendas de la presentación personal</h3>
                <p class="text-xs text-slate-500 mt-1">En cada revista el supervisor marca cada prenda activa: Bueno, Regular, Malo o No aplica. Es obligatoria mientras haya al menos una prenda activa.</p>
            </div>
            <div class="divide-y divide-slate-800">
                @forelse ($items as $item)
                    <div x-data="{ editing: false }" class="px-4 py-3">
                        <div x-show="!editing" class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-3 text-sm">
                                <span class="text-white font-medium">{{ $item->name }}</span>
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $item->is_active ? 'bg-emerald-900/40 text-emerald-300' : 'bg-rose-900/40 text-rose-300' }}">
                                    {{ $item->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="editing = true" class="text-xs text-indigo-300">Editar</button>
                                <form method="POST" action="{{ route('company.supervision-uniform-items.destroy', $item) }}" onsubmit="return confirm('¿Eliminar esta prenda?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-rose-400">Eliminar</button>
                                </form>
                            </div>
                        </div>
                        <form x-show="editing" x-cloak method="POST" action="{{ route('company.supervision-uniform-items.update', $item) }}" class="space-y-3">
                            @csrf
                            @method('PUT')
                            <input type="text" name="name" value="{{ old('name', $item->name) }}" required class="w-full h-9 rounded-lg bg-slate-950 border border-slate-700 px-3 text-sm text-white">
                            <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active)) class="rounded border-slate-700 text-indigo-600">
                                Activo
                            </label>
                            <div class="flex gap-2">
                                <button type="submit" class="h-9 px-3 rounded-lg bg-indigo-600 text-xs font-semibold text-white">Guardar</button>
                                <button type="button" @click="editing = false" class="h-9 px-3 rounded-lg bg-slate-800 text-xs font-semibold text-slate-300">Cancelar</button>
                            </div>
                        </form>
                    </div>
                @empty
                    <p class="px-4 py-8 text-sm text-slate-500 text-center">Sin prendas. Mientras no haya ninguna, la app no pide presentación personal.</p>
                @endforelse
            </div>
        </div>
        <div class="rounded-xl border border-slate-800 bg-slate-900 p-4 h-fit">
            <h3 class="text-sm font-semibold text-white mb-3">Nueva prenda</h3>
            <form method="POST" action="{{ route('company.supervision-uniform-items.store') }}" class="space-y-3">
                @csrf
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="Gorra" class="w-full h-9 rounded-lg bg-slate-950 border border-slate-700 px-3 text-sm text-white">
                <x-ui.field-error :messages="$errors->get('name')" />
                <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-slate-700 text-indigo-600">
                    Activo
                </label>
                <button type="submit" class="w-full h-9 rounded-lg bg-indigo-600 text-sm font-semibold text-white">Crear prenda</button>
            </form>
        </div>
    </div>
</x-company-layout>
