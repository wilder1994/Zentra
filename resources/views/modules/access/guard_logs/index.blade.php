<x-access-layout title="Minutas de Vigilancia">
    <x-slot:actions>
        @can('access.manage.supervision')
        <x-ui.button href="{{ route('access.supervision.index') }}">
            <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1115 9z"/></svg>
            Supervisión
        </x-ui.button>
        @endcan
        <x-ui.button href="{{ route('access.guard_logs.create') }}">
            <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Nueva Minuta
        </x-ui.button>
    </x-slot:actions>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('modules.access.partials.subnav')

        <div class="mt-6 bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800">
                <h3 class="text-lg font-semibold text-white">Registro de Novedades</h3>
                <p class="text-sm text-slate-500 mt-0.5">Todas las minutas registradas por los guardias</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800">
                    <thead class="bg-slate-950/60">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Guardia</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Ubicación</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Fecha/Hora</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Tipo</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Turno</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Descripción</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Geo</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($logs as $log)
                        <tr class="hover:bg-slate-800/40 transition-colors {{ $log->is_panic ? 'bg-red-900/20 hover:bg-red-900/30' : '' }}">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-amber-500 to-amber-700 flex items-center justify-center text-white text-xs font-bold">
                                        {{ strtoupper(substr($log->user->name, 0, 2)) }}
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-white">{{ $log->user->name }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $log->location->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $log->log_time->format('d/m/Y H:i') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ring-1
                                    @if($log->is_panic || $log->type == 'incidente') bg-red-900/30 text-red-300 ring-red-700
                                    @elseif($log->type == 'novedad') bg-amber-900/30 text-amber-300 ring-amber-700
                                    @elseif($log->type == 'turno') bg-blue-900/30 text-blue-300 ring-blue-700
                                    @else bg-slate-800 text-slate-300 ring-slate-600 @endif">
                                    @if($log->is_panic)🚨 Pánico @else{{ ucfirst($log->type) }}@endif
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ ucfirst($log->shift_type) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-400 max-w-xs truncate">
                                <a href="{{ route('access.guard_logs.show', $log) }}" class="hover:text-indigo-400 transition-colors">{{ Str::limit($log->description, 80) }}</a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if($log->latitude && $log->longitude)
                                    <span class="inline-flex items-center text-xs text-indigo-400" title="{{ $log->latitude }}, {{ $log->longitude }}">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    </span>
                                @else
                                    <span class="text-xs text-slate-600">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <a href="{{ route('access.guard_logs.show', $log) }}" class="text-indigo-400 hover:text-indigo-300 font-medium text-xs mr-2">Ver</a>
                                <form action="{{ route('access.guard_logs.destroy', $log) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar esta minuta?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-400 hover:text-red-300 font-medium text-xs">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-10 w-10 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <p class="mt-2 text-sm text-slate-500">No hay minutas registradas</p>
                                <a href="{{ route('access.guard_logs.create') }}" class="mt-3 inline-flex items-center px-3 py-1.5 bg-amber-600 text-white text-xs font-semibold rounded-lg hover:bg-amber-700 transition-colors">
                                    Crear primera minuta
                                </a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $logs->links() }}</div>
    </div>
</x-access-layout>