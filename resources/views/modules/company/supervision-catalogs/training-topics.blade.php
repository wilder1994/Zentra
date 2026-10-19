<x-company-layout title="Temas de capacitación">
    @include('modules.company.settings.partials.nav-slots', ['companyNavActive' => 'temas'])

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 rounded-xl border border-slate-800 bg-slate-900 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-800">
                <h3 class="text-sm font-semibold text-white">Temas de capacitación</h3>
                <p class="text-xs text-slate-500 mt-1">El supervisor elige el tema en la app y puede crear uno nuevo (no editar ni borrar). No se repiten temas iguales sin contar tildes ni mayúsculas. Un tema con capacitaciones no se elimina: se desactiva y deja de salir en la app, pero se conserva en los reportes.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach (["all" => "Todos", "app" => "Creados en campo", "inactive" => "Inactivos"] as $key => $label)
                        <a href="{{ route('company.supervision-training-topics.index', $key === 'all' ? [] : ['filter' => $key]) }}"
                           @class(['inline-flex items-center gap-1.5 h-7 px-3 rounded-full text-xs font-semibold border', 'border-indigo-600 bg-indigo-600 text-white' => $filter === $key, 'border-slate-700 text-slate-400 hover:text-white' => $filter !== $key])>
                            {{ $label }} <span class="text-[10px] opacity-75">{{ $counts[$key] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
            <div class="divide-y divide-slate-800">
                @forelse ($topics as $topic)
                    <div x-data="{ editing: false }" class="px-4 py-3">
                        <div x-show="!editing" class="flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2 text-sm">
                                    <span class="text-white font-medium">{{ $topic->name }}</span>
                                    <span class="text-xs px-2 py-0.5 rounded-full {{ $topic->is_active ? 'bg-emerald-900/40 text-emerald-300' : 'bg-rose-900/40 text-rose-300' }}">
                                        {{ $topic->is_active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                    @if ($topic->created_from === \App\Models\SupervisorTrainingTopic::FROM_APP)
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-amber-900/40 text-amber-300">Creado en campo</span>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    {{ $topic->trainings_count }} {{ $topic->trainings_count === 1 ? 'capacitación' : 'capacitaciones' }}
                                    @if ($topic->createdBy)
                                        · {{ $topic->createdBy->name }} · {{ $topic->created_at?->timezone(config('app.timezone'))->format('d/m/Y') }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="editing = true" class="text-xs text-indigo-300">Editar</button>
                                @if ($topic->trainings_count === 0)
                                    <form method="POST" action="{{ route('company.supervision-training-topics.destroy', $topic) }}" onsubmit="return confirm('¿Eliminar este tema?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-rose-400">Eliminar</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('company.supervision-training-topics.update', $topic) }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="name" value="{{ $topic->name }}">
                                        <input type="hidden" name="is_active" value="{{ $topic->is_active ? 0 : 1 }}">
                                        <button type="submit" class="text-xs {{ $topic->is_active ? 'text-amber-300' : 'text-emerald-300' }}" title="Tiene capacitaciones: no se puede eliminar">{{ $topic->is_active ? 'Desactivar' : 'Activar' }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        <form x-show="editing" x-cloak method="POST" action="{{ route('company.supervision-training-topics.update', $topic) }}" class="space-y-3">
                            @csrf
                            @method('PUT')
                            <input type="text" name="name" value="{{ old('name', $topic->name) }}" required class="w-full h-9 rounded-lg bg-slate-950 border border-slate-700 px-3 text-sm text-white">
                            <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $topic->is_active)) class="rounded border-slate-700 text-indigo-600">
                                Activo
                            </label>
                            <div class="flex gap-2">
                                <button type="submit" class="h-9 px-3 rounded-lg bg-indigo-600 text-xs font-semibold text-white">Guardar</button>
                                <button type="button" @click="editing = false" class="h-9 px-3 rounded-lg bg-slate-800 text-xs font-semibold text-slate-300">Cancelar</button>
                            </div>
                        </form>
                    </div>
                @empty
                    <p class="px-4 py-8 text-sm text-slate-500 text-center">{{ $filter === 'all' ? 'Sin temas. Créelos aquí o el supervisor los crea desde la app.' : 'No hay temas con este filtro.' }}</p>
                @endforelse
            </div>
        </div>
        <div class="rounded-xl border border-slate-800 bg-slate-900 p-4 h-fit">
            <h3 class="text-sm font-semibold text-white mb-3">Nuevo tema</h3>
            <form method="POST" action="{{ route('company.supervision-training-topics.store') }}" class="space-y-3">
                @csrf
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="Uso del extintor" class="w-full h-9 rounded-lg bg-slate-950 border border-slate-700 px-3 text-sm text-white">
                <x-ui.field-error :messages="$errors->get('name')" />
                <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-slate-700 text-indigo-600">
                    Activo
                </label>
                <button type="submit" class="w-full h-9 rounded-lg bg-indigo-600 text-sm font-semibold text-white">Crear tema</button>
            </form>
        </div>
    </div>
</x-company-layout>
