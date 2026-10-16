<x-resident-layout title="Resumen">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-white">Bienvenido, {{ auth()->user()?->name }}</h2>
                <p class="text-sm text-slate-400 mt-1">Panel de residente — resumen de actividad.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('resident.pre-authorizations.create') }}" class="rounded-lg bg-teal-600 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-500">
                    <svg class="w-3.5 h-3.5 inline mr-1 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    Nueva Pre-Autorización
                </a>
                <a href="{{ route('resident.messages.create') }}" class="rounded-lg bg-slate-700 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-600">
                    <svg class="w-3.5 h-3.5 inline mr-1 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    Redactar Mensaje
                </a>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                <div class="flex items-center gap-2 text-slate-500 text-xs uppercase mb-1">
                    <svg class="w-4 h-4 text-teal-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    Mensajes no leídos
                </div>
                <p class="text-2xl font-bold text-white mt-1">{{ $unreadMessages }}</p>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                <div class="flex items-center gap-2 text-slate-500 text-xs uppercase mb-1">
                    <svg class="w-4 h-4 text-teal-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                    Ingresos hoy
                </div>
                <p class="text-2xl font-bold text-white mt-1">{{ $todayEntries }}</p>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                <div class="flex items-center gap-2 text-slate-500 text-xs uppercase mb-1">
                    <svg class="w-4 h-4 text-teal-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Pre-Autorizaciones activas
                </div>
                <p class="text-2xl font-bold text-white mt-1">{{ $activePreAuths }}</p>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                <div class="flex items-center gap-2 text-slate-500 text-xs uppercase mb-1">
                    <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                    Correspondencia pendiente
                </div>
                <p class="text-2xl font-bold text-amber-400 mt-1">{{ $pendingCorrespondence->count() }}</p>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 rounded-xl border border-slate-800 bg-slate-900 p-5">
                <h3 class="text-sm font-semibold text-white mb-4">Ingresos últimos 7 días</h3>
                <canvas id="residentChart" height="100"></canvas>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <h3 class="text-sm font-semibold text-white mb-4">Resumen del mes</h3>
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-400">Total ingresos</span>
                        <span class="text-white font-bold">{{ $monthEntries }}</span>
                    </div>
                    <div class="h-px bg-slate-800"></div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-400">Activos ahora</span>
                        <span class="text-white font-bold">{{ $activeEntries }}</span>
                    </div>
                    <div class="h-px bg-slate-800"></div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-400">Pre-Autorizaciones activas</span>
                        <span class="text-white font-bold">{{ $activePreAuths }}</span>
                    </div>
                    <div class="h-px bg-slate-800"></div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-400">Correspondencia pendiente</span>
                        <span class="text-amber-400 font-bold">{{ $pendingCorrespondence->count() }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-6">
            <div class="rounded-xl border border-slate-800 overflow-hidden">
                <div class="px-4 py-3 bg-slate-950/60 border-b border-slate-800 flex justify-between items-center">
                    <h3 class="text-sm font-semibold text-white">Últimas Pre-Autorizaciones</h3>
                    <a href="{{ route('resident.pre-authorizations.index') }}" class="text-xs text-teal-400 hover:text-teal-300">Ver todas</a>
                </div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($preAuthorizations as $pa)
                            <tr>
                                <td class="px-4 py-3 text-white">{{ $pa->visitor?->full_name ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-400">{{ $pa->scheduled_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded text-xs font-medium {{
                                        $pa->status === 'pending' ? 'bg-yellow-900/50 text-yellow-300' :
                                        ($pa->status === 'used' ? 'bg-green-900/50 text-green-300' :
                                        ($pa->status === 'cancelled' ? 'bg-red-900/50 text-red-300' : 'bg-slate-800 text-slate-400'))
                                    }}">{{ $pa->status }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-6 text-center text-slate-500">Sin pre-autorizaciones.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="rounded-xl border border-slate-800 overflow-hidden">
                <div class="px-4 py-3 bg-slate-950/60 border-b border-slate-800 flex justify-between items-center">
                    <h3 class="text-sm font-semibold text-white">Correspondencia Pendiente</h3>
                    <a href="{{ route('resident.correspondence.index') }}" class="text-xs text-teal-400 hover:text-teal-300">Ver todas</a>
                </div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($pendingCorrespondence as $c)
                            <tr>
                                <td class="px-4 py-3 text-white">{{ $c->carrier ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-400">{{ $c->package_type }}</td>
                                <td class="px-4 py-3 text-slate-400">{{ $c->received_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-6 text-center text-slate-500">Sin correspondencia pendiente.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-6">
            <div class="rounded-xl border border-slate-800 overflow-hidden">
                <div class="px-4 py-3 bg-slate-950/60 border-b border-slate-800 flex justify-between items-center">
                    <h3 class="text-sm font-semibold text-white">Actividad de mi unidad</h3>
                    <span class="text-xs text-slate-500">{{ $unitActivity->count() }} registros</span>
                </div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($unitActivity as $log)
                            <tr>
                                <td class="px-4 py-3 text-white">{{ $log->person_name }}</td>
                                <td class="px-4 py-3 text-slate-400">{{ $log->person_doc }}</td>
                                <td class="px-4 py-3 text-slate-400">{{ $log->housingUnit?->full_label ?? $log->location?->name }}</td>
                                <td class="px-4 py-3 text-slate-400 whitespace-nowrap">{{ $log->entry_time->format('d/m H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">Aún no hay actividad en tu unidad.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="rounded-xl border border-slate-800 overflow-hidden">
                <div class="px-4 py-3 bg-slate-950/60 border-b border-slate-800 flex justify-between items-center">
                    <h3 class="text-sm font-semibold text-white">Notificaciones</h3>
                    <a href="{{ route('resident.notifications.index') }}" class="text-xs text-teal-400 hover:text-teal-300">Ver todas</a>
                </div>
                <div class="divide-y divide-slate-800">
                    @forelse ($myNotifications as $n)
                        @php $data = $n->data; @endphp
                        <div class="px-4 py-3 flex items-start gap-3 {{ $n->read_at ? 'opacity-60' : '' }}">
                            <span class="mt-0.5 w-2 h-2 rounded-full flex-shrink-0 {{ $n->read_at ? 'bg-slate-700' : 'bg-teal-400' }}"></span>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-white">{{ $data['title'] ?? 'Notificación' }}</p>
                                <p class="text-xs text-slate-400">{{ $data['message'] ?? '' }}</p>
                                <p class="mt-1 text-[10px] text-slate-500">{{ $n->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-sm text-slate-500">No tienes notificaciones.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        new Chart(document.getElementById('residentChart'), {
            type: 'bar',
            data: {
                labels: @json($dailyLabels),
                datasets: [{
                    label: 'Ingresos',
                    data: @json($dailyData),
                    backgroundColor: 'rgba(45, 212, 191, 0.3)',
                    borderColor: 'rgb(45, 212, 191)',
                    borderWidth: 1,
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(148, 163, 184, 0.1)' }, ticks: { color: '#94a3b8' } },
                    x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
                }
            }
        });
    </script>
    @endpush
</x-resident-layout>