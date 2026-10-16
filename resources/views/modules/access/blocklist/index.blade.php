<x-access-layout title="Lista de Bloqueo">
    <x-slot:actions>
        <x-ui.button href="{{ route('access.blocklist.create') }}">
            <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
            Agregar a bloqueo
        </x-ui.button>
    </x-slot:actions>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('modules.access.partials.subnav')

        <form method="GET" action="{{ route('access.blocklist.index') }}" class="mt-6 flex flex-col sm:flex-row gap-2">
            <input type="search" name="q" value="{{ $q }}" placeholder="Buscar por cédula, placa, nombre o motivo…"
                   class="flex-1 h-10 rounded-lg border border-slate-700 bg-slate-950 px-3 text-sm text-white placeholder:text-slate-600 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30">
            <x-ui.button type="submit" size="sm" variant="secondary">Buscar</x-ui.button>
            @if ($q !== '')
                <a href="{{ route('access.blocklist.index') }}" class="inline-flex h-10 items-center px-3 text-xs text-slate-500 hover:text-slate-300">Limpiar</a>
            @endif
        </form>

        <div class="mt-4 bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800">
                <h3 class="text-lg font-semibold text-white">Entradas bloqueadas</h3>
                <p class="text-sm text-slate-500 mt-0.5">Personas (cédula) y vehículos (placa) con ingreso denegado. El motivo solo lo ven administradores.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800">
                    <thead class="bg-slate-950/60">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Tipo</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Nombre</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Cédula / placa</th>
                            @can('access.blocklist.remove')
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Motivo</th>
                            @endcan
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Bloqueado por</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Desde</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Expira</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($entries as $entry)
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php $type = $entry->normalizedType(); @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($type === 'vehicle') bg-cyan-900/30 text-cyan-300 ring-1 ring-cyan-700
                                    @elseif($type === 'resident') bg-violet-900/30 text-violet-300 ring-1 ring-violet-700
                                    @elseif($type === 'visitor') bg-blue-900/30 text-blue-300 ring-1 ring-blue-700
                                    @else bg-rose-900/30 text-rose-300 ring-1 ring-rose-700 @endif">
                                    {{ $entry->typeLabel() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-white">{{ $entry->subjectLabel() }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-slate-200">{{ $entry->identifierLabel() }}</td>
                            @can('access.blocklist.remove')
                            <td class="px-6 py-4 text-sm text-slate-400 max-w-xs truncate" title="{{ $entry->reason }}">{{ $entry->reason }}</td>
                            @endcan
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $entry->blocker?->name ?? '—' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $entry->blocked_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm {{ $entry->expires_at && $entry->expires_at->isPast() ? 'text-red-400 font-semibold' : 'text-slate-400' }}">
                                {{ $entry->expires_at?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                @can('access.blocklist.remove')
                                <form action="{{ route('access.blocklist.destroy', $entry) }}" method="POST" onsubmit="return confirm('¿Remover este bloqueo?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="inline-flex items-center px-2.5 py-1 bg-emerald-600 border border-transparent rounded-lg font-semibold text-xs text-white hover:bg-emerald-700 transition-colors shadow-sm">
                                        Remover
                                    </button>
                                </form>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ auth()->user()?->can('access.blocklist.remove') ? 8 : 7 }}" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-10 w-10 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <p class="mt-2 text-sm text-slate-500">No hay entradas bloqueadas.</p>
                                <a href="{{ route('access.blocklist.create') }}" class="mt-3 inline-flex items-center px-3 py-1.5 bg-red-600 text-white text-xs font-semibold rounded-lg hover:bg-red-700 transition-colors">
                                    Agregar a lista de bloqueo
                                </a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $entries->links() }}
    </div>
</x-access-layout>
