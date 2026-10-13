<x-access-layout title="Auditoría de Seguridad">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-900 rounded-xl border border-slate-800 p-4">
            <p class="text-xs text-slate-500">Eventos totales</p>
            <p class="mt-1 text-2xl font-bold text-white">{{ number_format($summary['total']) }}</p>
        </div>
        <div class="bg-slate-900 rounded-xl border border-slate-800 p-4">
            <p class="text-xs text-slate-500">Hoy</p>
            <p class="mt-1 text-2xl font-bold text-indigo-400">{{ $summary['today'] }}</p>
        </div>
        <div class="bg-slate-900 rounded-xl border border-slate-800 p-4">
            <p class="text-xs text-slate-500">Ingresos/salidas auditados</p>
            <p class="mt-1 text-2xl font-bold text-emerald-400">{{ number_format($summary['entries']) }}</p>
        </div>
        <div class="bg-slate-900 rounded-xl border border-slate-800 p-4">
            <p class="text-xs text-slate-500">Minutas/supervisiones</p>
            <p class="mt-1 text-2xl font-bold text-amber-400">{{ number_format($summary['guard_logs']) }}</p>
        </div>
    </div>

    <div class="bg-slate-900 rounded-xl border border-slate-800 p-4 mb-6">
        <form method="GET" action="{{ route('access.audit.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-slate-400 mb-1">Acción</label>
                <select name="action" class="w-full rounded-lg bg-slate-950 border-slate-700 text-sm text-white focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todas</option>
                    @foreach($actions as $a)
                        <option value="{{ $a }}" @selected(request('action') === $a)>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1">Modelo</label>
                <input type="text" name="auditable_type" value="{{ request('auditable_type') }}" placeholder="ej. AccessLog" class="w-full rounded-lg bg-slate-950 border-slate-700 text-sm text-white focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1">Desde</label>
                <input type="date" name="from" value="{{ request('from') }}" class="w-full rounded-lg bg-slate-950 border-slate-700 text-sm text-white focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1">Hasta</label>
                <input type="date" name="to" value="{{ request('to') }}" class="w-full rounded-lg bg-slate-950 border-slate-700 text-sm text-white focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="md:col-span-5 flex justify-end gap-3">
                <a href="{{ route('access.audit.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg font-semibold text-xs text-slate-300 hover:bg-slate-700 transition-colors">Limpiar</a>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition-colors shadow-sm">Filtrar</button>
            </div>
        </form>
    </div>

    <div class="bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wider text-slate-500 border-b border-slate-800">
                        <th class="px-6 py-3">Fecha</th>
                        <th class="px-6 py-3">Usuario</th>
                        <th class="px-6 py-3">Acción</th>
                        <th class="px-6 py-3">Entidad</th>
                        <th class="px-6 py-3">IP</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/70">
                    @forelse($logs as $log)
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="px-6 py-3 text-slate-300 whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                            <td class="px-6 py-3 text-white">{{ $log->actorName() }}</td>
                            <td class="px-6 py-3">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium {{ str_starts_with($log->action, 'access.') ? 'bg-emerald-900/50 text-emerald-300' : (str_contains($log->action, 'delete') || $log->action === 'panic' ? 'bg-red-900/50 text-red-300' : 'bg-indigo-900/50 text-indigo-300') }}">{{ $log->action }}</span>
                            </td>
                            <td class="px-6 py-3 text-slate-400">{{ $log->auditable_type ? class_basename($log->auditable_type) . ' #' . $log->auditable_id : '—' }}</td>
                            <td class="px-6 py-3 text-slate-500 font-mono">{{ $log->ip_address ?? '—' }}</td>
                            <td class="px-6 py-3">
                                <details class="group">
                                    <summary class="cursor-pointer text-xs text-indigo-400 hover:text-indigo-300 font-medium list-none">Ver cambios</summary>
                                    <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
                                        @if($log->old_values)
                                            <div>
                                                <p class="text-xs font-semibold text-slate-500 mb-1 uppercase tracking-wider">Antes</p>
                                                <pre class="text-xs text-slate-400 bg-slate-950/60 border border-slate-800 rounded-lg p-3 overflow-x-auto">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </div>
                                        @endif
                                        @if($log->new_values)
                                            <div>
                                                <p class="text-xs font-semibold text-slate-500 mb-1 uppercase tracking-wider">Después</p>
                                                <pre class="text-xs text-slate-300 bg-slate-950/60 border border-slate-800 rounded-lg p-3 overflow-x-auto">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </div>
                                        @endif
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">Sin eventos de auditoría para los filtros seleccionados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-slate-800">
            {{ $logs->links() }}
        </div>
    </div>
</x-access-layout>