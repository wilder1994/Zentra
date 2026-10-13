<x-access-layout title="Apartamentos / Casas">
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @include('modules.access.partials.subnav')
            <div class="mt-6 flex justify-end">
                <a href="{{ route('access.housing_units.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">Nuevo Apartamento/Casa</a>
            </div>
            <div class="mt-4 bg-slate-900 rounded-lg border border-slate-800 overflow-hidden">
                <table class="min-w-full divide-y divide-slate-800">
                    <thead class="bg-slate-950/60">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Torre/Bloque</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Número</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Piso</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Tipo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Residentes</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Activo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-slate-900 divide-y divide-slate-800">
                        @foreach($units as $unit)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-white">{{ $unit->building->name ?? '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $unit->unit_number }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $unit->floor ?? '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ ucfirst($unit->type) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $unit->residents->count() }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full {{ $unit->is_active ? 'bg-green-900/30 text-green-300 ring-1 ring-green-700' : 'bg-red-900/30 text-red-300 ring-1 ring-red-700' }}">{{ $unit->is_active ? 'Sí' : 'No' }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="{{ route('access.housing_units.edit', $unit) }}" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                <form action="{{ route('access.housing_units.destroy', $unit) }}" method="POST" class="inline ml-2" onsubmit="return confirm('¿Eliminar esta unidad?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $units->links() }}</div>
        </div>
    </div>
</x-access-layout>
