@php
    $assignerConfig = [
        'zones' => $zones->map(fn ($zone) => ['id' => $zone->id, 'name' => $zone->name, 'active' => (bool) $zone->is_active])->values()->all(),
        'rows' => $clientRows,
        'unassigned' => $unassigned,
        'assignUrl' => route('company.supervision-zones.assign'),
    ];
@endphp
<x-company-layout title="Zonas">
    @include('modules.company.settings.partials.nav-slots', ['companyNavActive' => 'zonas'])

    <div x-data="zoneAssigner(@js($assignerConfig))" class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 rounded-xl border border-slate-800 bg-slate-900 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-800 flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-white">Catálogo de zonas</h3>
                    <p class="text-xs text-slate-500 mt-1">Rutas de Supervisión. Cada cliente tiene una zona principal y puede compartirse con otras. El supervisor solo ve los clientes de sus zonas. No es la zona de Accesos/portería.</p>
                </div>
                @if ($canManage)
                    <button type="button" @click="show()" class="shrink-0 h-9 px-3 rounded-lg bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-500">
                        Asignar clientes a zonas
                    </button>
                @endif
            </div>
            @if ($unassigned > 0)
                <button type="button" @if ($canManage) @click="show({ filter: 'none' })" @endif
                        class="w-full text-left px-4 py-2 border-b border-slate-800 bg-rose-500/10 text-xs text-rose-300 hover:bg-rose-500/15">
                    {{ $unassigned }} {{ $unassigned === 1 ? 'cliente sin zona' : 'clientes sin zona' }}: ningún supervisor los ve.
                    @if ($canManage)
                        <span class="font-semibold underline">Asignar</span>
                    @endif
                </button>
            @endif
            <div class="divide-y divide-slate-800">
                @forelse ($zones as $zone)
                    <div x-data="{ editing: false }" class="px-4 py-3">
                        <div x-show="!editing" class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-3 text-sm">
                                <span class="text-white font-medium">{{ $zone->name }}</span>
                                <span class="text-xs text-slate-400">
                                    {{ $zone->clients_count }} {{ $zone->clients_count === 1 ? 'cliente' : 'clientes' }}
                                    @if ($zone->shared_clients_count > 0)
                                        · {{ $zone->shared_clients_count }} {{ $zone->shared_clients_count === 1 ? 'compartido' : 'compartidos' }}
                                    @endif
                                </span>
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $zone->is_active ? 'bg-emerald-900/40 text-emerald-300' : 'bg-rose-900/40 text-rose-300' }}">
                                    {{ $zone->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>
                            @if ($canManage)
                                <div class="flex items-center gap-3">
                                    <button type="button" @click="show({ zoneId: {{ $zone->id }} })" class="text-xs font-semibold text-indigo-300">Clientes</button>
                                    <button type="button" @click="editing = true" class="text-xs text-indigo-300">Editar</button>
                                    <form method="POST" action="{{ route('company.supervision-zones.destroy', $zone) }}" onsubmit="return confirm('¿Eliminar esta zona? Sus clientes quedan sin zona.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-rose-400">Eliminar</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                        <form x-show="editing" x-cloak method="POST" action="{{ route('company.supervision-zones.update', $zone) }}" class="space-y-3">
                            @csrf
                            @method('PUT')
                            <input type="text" name="name" value="{{ old('name', $zone->name) }}" required class="w-full h-9 rounded-lg bg-slate-950 border border-slate-700 px-3 text-sm text-white">
                            <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $zone->is_active)) class="rounded border-slate-700 text-indigo-600">
                                Activo
                            </label>
                            <div class="flex gap-2">
                                <button type="submit" class="h-9 px-3 rounded-lg bg-indigo-600 text-xs font-semibold text-white">Guardar</button>
                                <button type="button" @click="editing = false" class="h-9 px-3 rounded-lg bg-slate-800 text-xs font-semibold text-slate-300">Cancelar</button>
                            </div>
                        </form>
                    </div>
                @empty
                    <p class="px-4 py-8 text-sm text-slate-500 text-center">Sin zonas. Crea la primera.</p>
                @endforelse
            </div>
        </div>
        @if ($canManage)
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-4 h-fit">
                <h3 class="text-sm font-semibold text-white mb-3">Nueva zona</h3>
                <form method="POST" action="{{ route('company.supervision-zones.store') }}" class="space-y-3">
                    @csrf
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="Norte" class="w-full h-9 rounded-lg bg-slate-950 border border-slate-700 px-3 text-sm text-white">
                    <x-ui.field-error :messages="$errors->get('name')" />
                    <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-slate-700 text-indigo-600">
                        Activo
                    </label>
                    <button type="submit" class="w-full h-9 rounded-lg bg-indigo-600 text-sm font-semibold text-white">Crear zona</button>
                </form>
            </div>
        @endif

        @if ($canManage)
            @include('modules.company.supervision-catalogs.partials.zone-assigner-modal')
        @endif
    </div>
</x-company-layout>
