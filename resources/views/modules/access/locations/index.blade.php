<x-access-layout title="Puntos de acceso">
    <p class="mb-4 text-sm text-slate-400">Puertas, porterías y accesos del cliente. El nombre lo define el cliente.</p>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @include('modules.access.partials.subnav')
            <div class="mt-6 flex justify-end">
                <a href="{{ route('access.locations.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">Nuevo punto de acceso</a>
            </div>
            <div class="mt-4 bg-slate-900 rounded-lg border border-slate-800 overflow-hidden">
                <table class="min-w-full divide-y divide-slate-800">
                    <thead class="bg-slate-950/60">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Código</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Nombre</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Instalación</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Dirección</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Activo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-400 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-slate-900 divide-y divide-slate-800">
                        @forelse($locations as $location)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-white">{{ $location->code }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $location->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $location->installation?->name ?? '—' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $location->address ?? '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full {{ $location->is_active ? 'bg-green-900/30 text-green-300 ring-1 ring-green-700' : 'bg-red-900/30 text-red-300 ring-1 ring-red-700' }}">{{ $location->is_active ? 'Sí' : 'No' }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="{{ route('access.locations.edit', $location) }}" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                <form action="{{ route('access.locations.destroy', $location) }}" method="POST" class="inline ml-2" onsubmit="return confirm('¿Eliminar este punto de acceso?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-sm text-slate-500">Sin puntos de acceso. Cree el primero.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $locations->links() }}</div>
        </div>
    </div>
</x-access-layout>
