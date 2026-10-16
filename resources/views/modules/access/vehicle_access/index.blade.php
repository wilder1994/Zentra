<x-access-layout title="Vehículos - Residentes">
    <x-slot:actions>
        <x-ui.button href="{{ route('access.vehicle_access.entry') }}">
            <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Ingreso Vehicular
        </x-ui.button>
    </x-slot:actions>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @include('modules.access.partials.subnav')

        @if($activeVehicles->count())
        <div class="mt-6 bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-white">Vehículos Dentro</h3>
                    <p class="text-sm text-slate-500 mt-0.5">Vehículos actualmente en la instalación</p>
                </div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-900/30 text-emerald-300 ring-1 ring-emerald-700">
                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1.5"></span>
                    {{ $activeVehicles->count() }} dentro
                </span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800">
                    <thead class="bg-slate-950/60">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Placa</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Propietario</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Vehículo</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Ingreso</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach($activeVehicles as $log)
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-cyan-900/30 text-cyan-300 ring-1 ring-cyan-700 uppercase">{{ $log->vehicle->plate }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $log->user->name ?? $log->resident?->full_name ?? '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $log->vehicle->brand }} {{ $log->vehicle->model }} <span class="text-slate-500">({{ $log->vehicle->color }})</span></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $log->entry_time->format('H:i') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <form action="{{ route('access.vehicle_access.exit', $log) }}" method="POST" onsubmit="return confirm('¿Registrar salida?')">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-red-600 border border-transparent rounded-lg font-semibold text-xs text-white hover:bg-red-700 transition-colors shadow-sm">Registrar Salida</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <div class="mt-6 bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800">
                <h3 class="text-lg font-semibold text-white">Registros del Día</h3>
                <p class="text-sm text-slate-500 mt-0.5">Todos los movimientos vehiculares del día</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800">
                    <thead class="bg-slate-950/60">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Placa</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Propietario</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Ingreso</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Salida</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse($todayLogs as $log)
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-cyan-900/30 text-cyan-300 ring-1 ring-cyan-700 uppercase">{{ $log->vehicle->plate }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $log->user->name ?? $log->resident?->full_name ?? '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $log->entry_time->format('H:i') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $log->exit_time?->format('H:i') ?? '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($log->status == 'active')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-900/30 text-emerald-300 ring-1 ring-emerald-700">
                                        <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                                        Dentro
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-900/30 text-slate-400 ring-1 ring-slate-600">
                                        <span class="w-1.5 h-1.5 bg-slate-500 rounded-full"></span>
                                        Salió
                                    </span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-10 w-10 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10m0 0V21h14v-5m0 0h3l2-4-2-4h-3l-2 4h-1"/></svg>
                                <p class="mt-2 text-sm text-slate-500">Sin registros hoy</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $todayLogs->links() }}</div>
    </div>
</x-access-layout>
