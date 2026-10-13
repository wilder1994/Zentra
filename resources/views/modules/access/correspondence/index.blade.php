<x-access-layout title="Paquetes y Encomiendas">
    <x-slot:actions>
        <x-ui.button href="{{ route('access.correspondence.create') }}">
            <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Registrar
        </x-ui.button>
    </x-slot:actions>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('modules.access.partials.subnav')

        <div class="mt-6 bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800">
                <h3 class="text-lg font-semibold text-white">Correspondencia Recibida</h3>
                <p class="text-sm text-slate-500 mt-0.5">Gestión de paquetes, documentos y encomiendas</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800">
                    <thead class="bg-slate-950/60">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Destinatario</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Tipo</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Transportadora</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Recibido</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($correspondence as $c)
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-pink-500 to-pink-700 flex items-center justify-center text-white text-xs font-bold">
                                        {{ strtoupper(substr($c->resident?->full_name ?? $c->host?->name ?? '?', 0, 2)) }}
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-white">
                                            @if($c->resident)
                                                {{ $c->resident->full_name }}
                                            @elseif($c->housingUnit)
                                                {{ $c->housingUnit->full_label }}
                                            @elseif($c->host)
                                                {{ $c->host->name }}
                                            @else
                                                -
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($c->package_type == 'caja') bg-orange-900/30 text-orange-300 ring-1 ring-orange-700
                                    @elseif($c->package_type == 'sobre') bg-blue-900/30 text-blue-300 ring-1 ring-blue-700
                                    @elseif($c->package_type == 'documento') bg-gray-900/30 text-gray-300 ring-1 ring-gray-700
                                    @else bg-purple-900/30 text-purple-300 ring-1 ring-purple-700 @endif">
                                    {{ ucfirst($c->package_type) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $c->carrier ?? '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $c->received_at->format('d/m/Y H:i') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($c->status == 'pending')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-900/30 text-amber-300 ring-1 ring-amber-700">
                                        <span class="w-1.5 h-1.5 bg-amber-500 rounded-full"></span>
                                        Pendiente
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-900/30 text-emerald-300 ring-1 ring-emerald-700">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        Entregado
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('access.correspondence.show', $c) }}" class="text-indigo-600 hover:text-indigo-800 font-medium text-xs">Ver</a>
                                    @if($c->status == 'pending')
                                    <form action="{{ route('access.correspondence.deliver', $c) }}" method="POST" class="inline" onsubmit="return confirm('¿Marcar como entregado?')">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="text-emerald-600 hover:text-emerald-800 font-medium text-xs">Entregar</button>
                                    </form>
                                    @endif
                                    <form action="{{ route('access.correspondence.destroy', $c) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 font-medium text-xs">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-10 w-10 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <p class="mt-2 text-sm text-slate-500">No hay correspondencia registrada</p>
                                <a href="{{ route('access.correspondence.create') }}" class="mt-3 inline-flex items-center px-3 py-1.5 bg-pink-600 text-white text-xs font-semibold rounded-lg hover:bg-pink-700 transition-colors">
                                    Registrar primera correspondencia
                                </a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $correspondence->links() }}</div>
    </div>
</x-access-layout>
