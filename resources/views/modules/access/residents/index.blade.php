<x-access-layout title="Residentes">
    @include('modules.access.partials.subnav')

    <div class="mt-6 flex justify-end">
        <a href="{{ route('access.residents.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">Nuevo Residente</a>
    </div>

    <div class="mt-4 bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
        <table class="min-w-full divide-y divide-slate-800">
            <thead class="bg-slate-950/60">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Documento</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Nombre</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Vivienda</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Teléfono</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Activo</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @foreach($residents as $resident)
                <tr class="hover:bg-slate-800/40">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-white">{{ $resident->displayedDocument() }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $resident->full_name }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">
                        @foreach($resident->housingUnits as $hu)
                            <span class="inline-block bg-slate-800 px-2 py-0.5 rounded text-xs text-slate-300">{{ $hu->building->name ?? '' }} {{ $hu->unit_number }}</span>
                        @endforeach
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ ucfirst($resident->resident_type) }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $resident->displayedContact($resident->phone) }}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($resident->is_active)
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-900/30 text-emerald-300 ring-1 ring-emerald-700">
                                <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full"></span>
                                Sí
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-900/30 text-red-300 ring-1 ring-red-700">
                                <span class="w-1.5 h-1.5 bg-red-400 rounded-full"></span>
                                No
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <a href="{{ route('access.residents.show', $resident) }}" class="text-blue-400 hover:text-blue-300 mr-2">Ver</a>
                        <a href="{{ route('access.residents.edit', $resident) }}" class="text-indigo-400 hover:text-indigo-300">Editar</a>
                        <form action="{{ route('access.residents.destroy', $resident) }}" method="POST" class="inline ml-2" onsubmit="return confirm('¿Eliminar este residente?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-500 hover:text-red-400">Eliminar</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $residents->links() }}</div>
</x-access-layout>
