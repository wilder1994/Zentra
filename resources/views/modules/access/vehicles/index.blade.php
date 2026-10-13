<x-access-layout title="Vehículos Registrados">
    <x-slot:actions>
        <x-ui.button href="{{ route('access.vehicles.create') }}">
            <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Nuevo Vehículo
        </x-ui.button>
    </x-slot:actions>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('modules.access.partials.subnav')
        <div class="mt-6 bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
            <table class="min-w-full divide-y divide-slate-800">
                <thead class="bg-slate-950/60">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Placa</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Marca</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Modelo</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Color</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Tipo</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Propietario</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @foreach($vehicles as $vehicle)
                    <tr class="hover:bg-slate-800/40 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-white">{{ $vehicle->plate }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $vehicle->brand ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $vehicle->model ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $vehicle->color ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ ucfirst($vehicle->type) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $vehicle->owner?->name ?? $vehicle->visitor?->full_name ?? $vehicle->resident?->full_name ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <a href="{{ route('access.vehicles.edit', $vehicle) }}" class="text-indigo-600 hover:text-indigo-400 font-medium">Editar</a>
                            <form action="{{ route('access.vehicles.destroy', $vehicle) }}" method="POST" class="inline ml-2" onsubmit="return confirm('¿Eliminar vehículo?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-400 font-medium">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $vehicles->links() }}</div>
    </div>
</x-access-layout>
