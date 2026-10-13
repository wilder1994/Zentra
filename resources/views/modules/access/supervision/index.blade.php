<x-access-layout title="Registros de Supervisión">
    <x-slot:actions>
        @can('access.manage.supervision_codes')
        <x-ui.button href="{{ route('access.supervision.codes.index') }}">
            <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1115 9z"/></svg>
            Códigos
        </x-ui.button>
        @endcan
        <x-ui.button href="{{ route('access.supervision.create') }}">
            <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Nueva Supervisión
        </x-ui.button>
    </x-slot:actions>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('modules.access.partials.subnav')

        @if (session('supervision.supervisor_name'))
        <div class="mt-4 bg-indigo-900/40 border border-indigo-700 rounded-lg px-4 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-2 text-sm text-indigo-200">
                <svg class="w-4 h-4 text-indigo-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <span>Supervisor activo: <strong class="text-white">{{ session('supervision.supervisor_name') }}</strong></span>
            </div>
            <a href="{{ route('access.supervision.exit') }}" class="inline-flex items-center px-3 py-1.5 bg-slate-800 hover:bg-slate-700 rounded-lg text-xs font-medium text-slate-300 transition-colors">
                Cerrar sesión de supervisión
            </a>
        </div>
        @endif

        <div class="mt-6 bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800">
                <h3 class="text-lg font-semibold text-white">Registro de Supervisiones</h3>
                <p class="text-sm text-slate-500 mt-0.5">Visitas de supervisión registradas por los guardias y supervisores</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800">
                    <thead class="bg-slate-950/60">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Supervisor</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Registrado por</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Ubicación</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Fecha/Hora</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Tipo</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Descripción</th>
                            <th class="px-6 py-3 text-center text-xs font-semibold text-slate-400 uppercase tracking-wider">Adjuntos</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($supervisions as $supervision)
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-indigo-700 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                                        {{ strtoupper(substr($supervision->supervisor_name ?: 'S', 0, 2)) }}
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-white">{{ $supervision->supervisor_name ?? '—' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $supervision->user->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $supervision->location->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $supervision->log_time->format('d/m/Y H:i') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ring-1
                                    @if($supervision->type == 'incidente') bg-red-900/30 text-red-300 ring-red-700
                                    @elseif($supervision->type == 'novedad') bg-amber-900/30 text-amber-300 ring-amber-700
                                    @elseif($supervision->type == 'rutina') bg-emerald-900/30 text-emerald-300 ring-emerald-700
                                    @elseif($supervision->type == 'inspeccion') bg-blue-900/30 text-blue-300 ring-blue-700
                                    @else bg-slate-800 text-slate-300 ring-slate-600 @endif">
                                    {{ ucfirst($supervision->type) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-400 max-w-xs truncate">
                                <a href="{{ route('access.supervision.show', $supervision) }}" class="hover:text-indigo-400 transition-colors">{{ Str::limit($supervision->description, 70) }}</a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if($supervision->attachments->count() > 0)
                                    <span class="inline-flex items-center gap-1 text-xs text-indigo-400" title="Adjuntos">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                        {{ $supervision->attachments->count() }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-600">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <a href="{{ route('access.supervision.show', $supervision) }}" class="text-indigo-400 hover:text-indigo-300 font-medium text-xs mr-2">Ver</a>
                                <form action="{{ route('access.supervision.destroy', $supervision) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este registro de supervisión?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-400 hover:text-red-300 font-medium text-xs">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-10 w-10 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <p class="mt-2 text-sm text-slate-500">No hay registros de supervisión</p>
                                <a href="{{ route('access.supervision.create') }}" class="mt-3 inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-500 transition-colors">
                                    Crear primer registro
                                </a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $supervisions->links() }}</div>
    </div>
</x-access-layout>