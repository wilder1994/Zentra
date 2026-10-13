<x-access-layout title="Torres / Bloques">
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @include('modules.access.partials.subnav')
            <div class="mt-6 flex justify-end">
                <a href="{{ route('access.buildings.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">Nueva Torre/Bloque</a>
            </div>
            <div class="mt-4 bg-slate-900 rounded-lg border border-slate-800 overflow-hidden">
                <table class="min-w-full divide-y divide-slate-800">
                    <thead class="bg-slate-950/60">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Código</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Nombre</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Tipo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Portería</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Unidades</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Activo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-slate-900 divide-y divide-slate-800">
                        @foreach($buildings as $building)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-white">{{ $building->code }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $building->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ ucfirst($building->type) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $building->location->name ?? '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $building->housing_units_count ?? $building->housingUnits->count() }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full {{ $building->is_active ? 'bg-green-900/30 text-green-300 ring-1 ring-green-700' : 'bg-red-900/30 text-red-300 ring-1 ring-red-700' }}">{{ $building->is_active ? 'Sí' : 'No' }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="{{ route('access.buildings.edit', $building) }}" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                <form action="{{ route('access.buildings.destroy', $building) }}" method="POST" class="inline ml-2" onsubmit="return confirm('¿Eliminar esta torre/bloque?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $buildings->links() }}</div>
        </div>
    </div>
</x-access-layout>
