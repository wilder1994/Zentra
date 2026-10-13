<x-access-layout title="Centro de Operaciones">
    <x-slot:actions>
        <span class="hidden sm:inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-400/20 text-emerald-300 ring-1 ring-emerald-400/30">
            <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full mr-1.5"></span>
            Sistema Activo
        </span>
    </x-slot:actions>

    <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-slate-900 rounded-xl border border-slate-800 p-4 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-16 h-16 bg-emerald-50 rounded-bl-3xl -mr-4 -mt-4"></div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Dentro</p>
            <p class="mt-1 text-2xl font-bold text-white">{{ $activeEntries }}</p>
        </div>
        <div class="bg-slate-900 rounded-xl border border-slate-800 p-4 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-16 h-16 bg-blue-50 rounded-bl-3xl -mr-4 -mt-4"></div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Hoy</p>
            <p class="mt-1 text-2xl font-bold text-white">{{ $todayEntries }}</p>
        </div>
        <div class="bg-slate-900 rounded-xl border border-slate-800 p-4 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-16 h-16 bg-amber-50 rounded-bl-3xl -mr-4 -mt-4"></div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Correspondencia</p>
            <p class="mt-1 text-2xl font-bold text-amber-600">{{ $pendingCorrespondence }}</p>
        </div>
        <div class="bg-slate-900 rounded-xl border border-slate-800 p-4 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-16 h-16 bg-purple-50 rounded-bl-3xl -mr-4 -mt-4"></div>
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Pre-Autorizaciones</p>
            <p class="mt-1 text-2xl font-bold text-purple-600">{{ $pendingPreAuthorizations }}</p>
        </div>
    </div>

    <div class="mt-8 bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-800">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-white">Personas Dentro del Conjunto</h3>
                    <p class="text-sm text-slate-500 mt-0.5">Personas actualmente en las instalaciones</p>
                </div>
                @if($activeEntries > 0)
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-emerald-900/30 text-emerald-300 ring-1 ring-emerald-700">
                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1.5"></span>
                    {{ $activeEntries }} {{ $activeEntries === 1 ? 'persona' : 'personas' }}
                </span>
                @endif
            </div>
        </div>

        @if($peopleInside->isEmpty())
        <div class="px-6 py-12 text-center">
            <svg class="mx-auto h-16 w-16 text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <p class="mt-3 text-sm text-slate-500">No hay personas dentro del conjunto en este momento.</p>
            @can('access.register.entry')
            <a href="{{ route('access.logs.index', ['tab' => 'movimiento']) }}" class="mt-4 inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 transition-colors shadow-sm">
                <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Registrar Ingreso
            </a>
            @endcan
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800">
                <thead class="bg-slate-950/60">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Persona</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Documento</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Tipo</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Destino</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Ubicación</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Ingreso</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Tiempo</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @foreach($peopleInside as $log)
                    <tr class="{{ $log->alert_long_stay ? 'bg-red-900/20 hover:bg-red-900/30' : 'hover:bg-slate-800/40' }} transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 w-8 h-8 rounded-full {{ $log->alert_long_stay ? 'bg-gradient-to-br from-red-400 to-red-600' : 'bg-gradient-to-br from-indigo-500 to-indigo-700' }} flex items-center justify-center">
                                    <span class="text-xs font-bold text-white">{{ strtoupper(substr($log->person_name, 0, 2)) }}</span>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-white">{{ $log->person_name }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $log->person_doc }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ring-1 {{ 
                                str_contains($log->person_type, 'Vehicular') ? 'bg-cyan-900/30 text-cyan-300 ring-cyan-700' : 
                                (str_contains($log->person_type, 'Residente') ? 'bg-teal-900/30 text-teal-300 ring-teal-700' : 'bg-blue-900/30 text-blue-300 ring-blue-700') 
                            }}">{{ $log->person_type }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $log->destination }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $log->location?->name ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $log->entry_time->format('H:i') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-1.5">
                                <span class="text-sm {{ $log->alert_long_stay ? 'text-red-700 font-semibold' : 'text-slate-400' }}">
                                    {{ $log->entry_time->diffForHumans(now(), true) }}
                                </span>
                                @if($log->alert_long_stay)
                                <svg class="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                            <form action="{{ route('access.logs.exit', $log) }}" method="POST" onsubmit="return confirm('¿Registrar salida de {{ $log->person_name }}?')">
                                @csrf @method('PATCH')
                                <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-red-600 border border-transparent rounded-lg font-semibold text-xs text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors shadow-sm">
                                    Salida
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    @php
        $longStayCount = $peopleInside->where('alert_long_stay', true)->count();
    @endphp
    @if($longStayCount > 0)
    <div class="mt-4 bg-red-900/20 border border-red-800 rounded-xl p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            <div>
                <p class="text-sm text-red-300 font-medium">
                    <strong>{{ $longStayCount }}</strong> {{ $longStayCount === 1 ? 'persona lleva' : 'personas llevan' }} más de {{ config('access.alerts.long_stay_hours') }} horas dentro del conjunto. Considere verificar.
                </p>
            </div>
        </div>
    </div>
    @endif
</x-access-layout>
