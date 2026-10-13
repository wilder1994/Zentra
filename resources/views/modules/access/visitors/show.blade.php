<x-access-layout :title="$visitor->full_name">
    <div class="bg-slate-900 rounded-xl border border-slate-800 p-6">
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div><dt class="text-sm font-medium text-slate-500">Documento</dt><dd class="mt-1 text-sm text-white">{{ $visitor->displayedDocument() }}</dd></div>
            <div><dt class="text-sm font-medium text-slate-500">Teléfono</dt><dd class="mt-1 text-sm text-white">{{ $visitor->phone ?? '-' }}</dd></div>
            <div><dt class="text-sm font-medium text-slate-500">Email</dt><dd class="mt-1 text-sm text-white">{{ $visitor->email ?? '-' }}</dd></div>
            <div><dt class="text-sm font-medium text-slate-500">Empresa</dt><dd class="mt-1 text-sm text-white">{{ $visitor->company ?? '-' }}</dd></div>
            <div><dt class="text-sm font-medium text-slate-500">Tipo</dt><dd class="mt-1 text-sm text-white">{{ ucfirst($visitor->visitor_type) }}</dd></div>
            <div><dt class="text-sm font-medium text-slate-500">Nacionalidad</dt><dd class="mt-1 text-sm text-white">{{ $visitor->nationality ?? '-' }}</dd></div>
        </dl>
    </div>

    @if($visitor->vehicles->count())
    <div class="mt-6 bg-slate-900 rounded-xl border border-slate-800 p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Vehículos</h3>
        <ul class="list-disc list-inside text-sm text-slate-400">
            @foreach($visitor->vehicles as $v)
            <li>{{ $v->plate }} - {{ $v->brand }} {{ $v->model }} ({{ $v->color }})</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="mt-6 bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-800">
            <h3 class="text-lg font-semibold text-white">Historial de Accesos</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800">
                <thead class="bg-slate-950/60">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Fecha</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Ubicación</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Ingreso</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Salida</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($visitor->accessLogs as $log)
                    <tr class="hover:bg-slate-800/40">
                        <td class="px-4 py-2 text-sm text-slate-400">{{ $log->entry_time->format('d/m/Y') }}</td>
                        <td class="px-4 py-2 text-sm text-slate-400">{{ $log->location->name }}</td>
                        <td class="px-4 py-2 text-sm text-slate-400">{{ $log->entry_time->format('H:i') }}</td>
                        <td class="px-4 py-2 text-sm text-slate-400">{{ $log->exit_time?->format('H:i') ?? '-' }}</td>
                        <td class="px-4 py-2">
                            @if($log->status == 'active')
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-900/30 text-emerald-300 ring-1 ring-emerald-700">
                                    <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full"></span>
                                    Dentro
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-800 text-slate-400 ring-1 ring-slate-700">
                                    <span class="w-1.5 h-1.5 bg-slate-500 rounded-full"></span>
                                    Salió
                                </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-2 text-sm text-slate-500 text-center">Sin accesos registrados</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-access-layout>
