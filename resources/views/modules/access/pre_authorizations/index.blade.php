<x-access-layout title="Autorizaciones de Ingreso">
    <x-slot:actions>
        <x-ui.button href="{{ route('access.pre_authorizations.create') }}">
            <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Nueva Pre-Autorización
        </x-ui.button>
    </x-slot:actions>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('modules.access.partials.subnav')

        <div class="mt-6 bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800">
                <h3 class="text-lg font-semibold text-white">Autorizaciones Registradas</h3>
                <p class="text-sm text-slate-500 mt-0.5">Visitas pre-autorizadas por residentes y anfitriones</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800">
                    <thead class="bg-slate-950/60">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Visitante</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Anfitrión</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Ubicación</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Fecha</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">QR</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($preAuthorizations as $pa)
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-purple-500 to-purple-700 flex items-center justify-center text-white text-xs font-bold">
                                        {{ strtoupper(substr($pa->visitor->full_name, 0, 2)) }}
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-white">{{ $pa->visitor->full_name }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $pa->host->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $pa->location->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">
                                {{ $pa->scheduled_date->format('d/m/Y') }}
                                @if(($pa->recurrence ?? 'puntual') !== 'puntual')
                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-purple-900/30 text-purple-300 ring-1 ring-purple-700">{{ ucfirst($pa->recurrence) }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ring-1
                                    @if($pa->status == 'pending') bg-amber-900/30 text-amber-300 ring-amber-700
                                    @elseif($pa->status == 'used') bg-blue-900/30 text-blue-300 ring-blue-700
                                    @elseif($pa->status == 'cancelled') bg-red-900/30 text-red-300 ring-red-700
                                    @else bg-gray-900/30 text-gray-300 ring-gray-700 @endif">
                                    {{ ucfirst($pa->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <button onclick="navigator.clipboard.writeText('{{ $pa->qr_code }}')" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 font-medium text-xs">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                                    Copiar QR
                                </button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('access.pre_authorizations.show', $pa) }}" class="text-indigo-600 hover:text-indigo-800 font-medium text-xs">Ver</a>
                                    @if($pa->status == 'pending')
                                    <form action="{{ route('access.pre_authorizations.destroy', $pa) }}" method="POST" class="inline" onsubmit="return confirm('¿Cancelar esta pre-autorización?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 font-medium text-xs">Cancelar</button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-10 w-10 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                <p class="mt-2 text-sm text-slate-500">No hay pre-autorizaciones registradas</p>
                                <a href="{{ route('access.pre_authorizations.create') }}" class="mt-3 inline-flex items-center px-3 py-1.5 bg-purple-600 text-white text-xs font-semibold rounded-lg hover:bg-purple-700 transition-colors">
                                    Crear primera pre-autorización
                                </a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $preAuthorizations->links() }}</div>
    </div>
</x-access-layout>
