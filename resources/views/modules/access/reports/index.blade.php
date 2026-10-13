<x-access-layout title="Reportes e Informes">
    @include('modules.access.partials.subnav')

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-5">
        @php $statCards = [
            ['label' => 'Total Ingresos', 'value' => $totalEntries, 'color' => 'indigo'],
            ['label' => 'Dentro', 'value' => $activeEntries, 'color' => 'emerald'],
            ['label' => 'Hoy', 'value' => $todayEntries, 'color' => 'blue'],
            ['label' => 'Visitantes', 'value' => $totalVisitors, 'color' => 'slate'],
            ['label' => 'Prom. Estadía', 'value' => $avgDuration ? round($avgDuration) . ' min' : '-', 'color' => 'amber'],
        ]; @endphp
        @foreach($statCards as $card)
        <div class="relative bg-slate-900 rounded-xl border border-slate-800 overflow-hidden group hover:shadow-md transition-all duration-200">
            <div class="absolute inset-y-0 left-0 w-1.5 bg-{{ $card['color'] }}-500"></div>
            <div class="p-5 pl-6">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ $card['label'] }}</p>
                <p class="mt-1 text-2xl font-bold text-white">{{ $card['value'] }}</p>
            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-8 bg-slate-900 rounded-xl border border-slate-800 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-white">Filtros</h3>
            <a href="{{ route('access.reports.export') }}?{{ http_build_query(request()->query()) }}" class="inline-flex items-center px-3 py-1.5 bg-emerald-600 text-white text-xs font-semibold rounded-lg hover:bg-emerald-700 transition-colors shadow-sm">
                <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Exportar Excel
            </a>
        </div>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-400">Desde</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400">Hasta</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400">Estado</label>
                <select name="status" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Activos</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completados</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400">Tipo</label>
                <select name="access_type" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    <option value="visitor" {{ request('access_type') == 'visitor' ? 'selected' : '' }}>Visitante</option>
                    <option value="visitor_vehicle" {{ request('access_type') == 'visitor_vehicle' ? 'selected' : '' }}>Visit. Vehicular</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400">Ubicación</label>
                <select name="location_id" class="mt-1 block w-full rounded-lg bg-slate-950 border-slate-700 text-white focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todas</option>
                    @foreach($locations as $loc)
                    <option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white hover:bg-indigo-700 transition-colors shadow-sm">Filtrar</button>
            </div>
        </form>
    </div>

    <div class="mt-8 bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-white">Registros de Acceso</h3>
                <p class="text-xs text-slate-500 mt-0.5">Detalle de los movimientos filtrados</p>
            </div>
            <span class="text-xs text-slate-500">{{ $logs->total() }} registros</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800">
                <thead class="bg-slate-950/60">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Persona</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Documento</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Tipo</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Anfitrión</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Ingreso</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Salida</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($logs as $log)
                    @php
                        $person = $log->visitor ?? $log->resident;
                        $personName = $person?->full_name ?? '-';
                        $personDoc = $person?->displayedDocument() ?? '-';
                    @endphp
                    <tr class="hover:bg-slate-800/40 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-white">{{ $personName }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">{{ $personDoc }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ring-1 {{ $log->access_type == 'visitor_vehicle' ? 'bg-cyan-900/30 text-cyan-300 ring-cyan-700' : 'bg-blue-900/30 text-blue-300 ring-blue-700' }}">
                                {{ $log->access_type == 'visitor_vehicle' ? 'Vehicular' : 'Visitante' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">{{ $log->host?->name ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">{{ $log->entry_time->format('d/m/Y H:i') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">{{ $log->exit_time?->format('d/m/Y H:i') ?? '-' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($log->status == 'active')
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-900/30 text-emerald-300 ring-1 ring-emerald-700">
                                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                                    Dentro
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-800/30 text-slate-400 ring-1 ring-slate-700">
                                    <span class="w-1.5 h-1.5 bg-slate-500 rounded-full"></span>
                                    Salió
                                </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-6 py-12 text-center text-sm text-slate-500">Sin resultados</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>

    <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-slate-900 rounded-xl border border-slate-800 p-5">
            <h3 class="text-sm font-semibold text-white mb-1">Accesos diarios (14 días)</h3>
            <p class="text-xs text-slate-500 mb-4">Tendencia de ingresos</p>
            <div class="relative h-64 overflow-y-auto">
                <canvas id="reportDailyChart"></canvas>
            </div>
        </div>
        <div class="bg-slate-900 rounded-xl border border-slate-800 p-5">
            <h3 class="text-sm font-semibold text-white mb-1">Distribución por tipo</h3>
            <p class="text-xs text-slate-500 mb-4">Visitantes vs vehiculares vs residentes</p>
            <div class="relative h-64 overflow-y-auto">
                <canvas id="reportTypeChart"></canvas>
            </div>
        </div>
        <div class="bg-slate-900 rounded-xl border border-slate-800 p-5 lg:col-span-2">
            <h3 class="text-sm font-semibold text-white mb-1">Top ubicaciones</h3>
            <p class="text-xs text-slate-500 mb-4">Las 5 ubicaciones con más actividad</p>
            <div class="relative h-56 overflow-y-auto">
                <canvas id="reportLocChart"></canvas>
            </div>
        </div>
    </div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;

    new Chart(document.getElementById('reportDailyChart'), {
        type: 'bar',
        data: {
            labels: @json($dailyLabels),
            datasets: [{
                label: 'Ingresos',
                data: @json($dailyData),
                backgroundColor: 'rgba(99, 102, 241, 0.2)',
                borderColor: 'rgba(99, 102, 241, 1)',
                borderWidth: 2,
                borderRadius: 4,
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false, labels: { color: '#94a3b8' } } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1, color: '#94a3b8' }, grid: { color: 'rgba(148, 163, 184, 0.1)' } }, x: { ticks: { color: '#94a3b8' }, grid: { display: false } } } }
    });

    new Chart(document.getElementById('reportTypeChart'), {
        type: 'doughnut',
        data: {
            labels: @json($typeLabels),
            datasets: [{
                data: @json($typeData),
                backgroundColor: ['rgba(59, 130, 246, 0.8)', 'rgba(6, 182, 212, 0.8)', 'rgba(16, 185, 129, 0.8)'],
                borderWidth: 0,
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12, color: '#94a3b8' } } } }
    });

    new Chart(document.getElementById('reportLocChart'), {
        type: 'bar',
        data: {
            labels: @json($locLabels),
            datasets: [{
                label: 'Ingresos',
                data: @json($locData),
                backgroundColor: ['rgba(99, 102, 241, 0.3)', 'rgba(16, 185, 129, 0.3)', 'rgba(245, 158, 11, 0.3)', 'rgba(239, 68, 68, 0.3)', 'rgba(139, 92, 246, 0.3)'],
                borderColor: ['rgba(99, 102, 241, 1)', 'rgba(16, 185, 129, 1)', 'rgba(245, 158, 11, 1)', 'rgba(239, 68, 68, 1)', 'rgba(139, 92, 246, 1)'],
                borderWidth: 2,
                borderRadius: 4,
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false, labels: { color: '#94a3b8' } } }, scales: { x: { beginAtZero: true, ticks: { stepSize: 1, color: '#94a3b8' }, grid: { color: 'rgba(148, 163, 184, 0.1)' } }, y: { ticks: { color: '#94a3b8' }, grid: { display: false } } } }
    });
});
</script>
@endpush
</x-access-layout>
