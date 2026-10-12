<x-company-layout title="Revisar carga">
    <x-slot:actions>
        <form method="POST" action="{{ route('company.clients.import.cancel') }}">
            @csrf
            <x-ui.button type="submit" variant="secondary" size="sm">Cancelar</x-ui.button>
        </form>
        @if (($preview['error'] ?? 0) === 0)
            <form method="POST" action="{{ route('company.clients.import.commit') }}">
                @csrf
                <x-ui.button type="submit" size="sm">Aceptar y cargar</x-ui.button>
            </form>
        @endif
    </x-slot:actions>

    <div class="space-y-4" x-data="{ filter: 'all' }">
        <p class="text-sm text-slate-400">Revisión temporal. La ficha no consume cupo; Accesos, Supervisión y Observatorio sí.</p>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="rounded-lg border border-slate-800 bg-slate-900/80 px-3 py-3">
                <p class="text-[10px] uppercase tracking-wide text-slate-500">Filas</p>
                <p class="mt-1 text-lg font-semibold text-white tabular-nums">{{ count($preview['rows']) }}</p>
            </div>
            <div class="rounded-lg border border-emerald-900/50 bg-emerald-950/20 px-3 py-3">
                <p class="text-[10px] uppercase tracking-wide text-emerald-500">Válidas</p>
                <p class="mt-1 text-lg font-semibold text-emerald-300 tabular-nums">{{ $preview['ok'] }}</p>
            </div>
            <div class="rounded-lg border border-amber-900/50 bg-amber-950/20 px-3 py-3">
                <p class="text-[10px] uppercase tracking-wide text-amber-500">Avisos</p>
                <p class="mt-1 text-lg font-semibold text-amber-300 tabular-nums">{{ $preview['warning'] }}</p>
            </div>
            <div class="rounded-lg border border-rose-900/50 bg-rose-950/20 px-3 py-3">
                <p class="text-[10px] uppercase tracking-wide text-rose-500">Errores</p>
                <p class="mt-1 text-lg font-semibold text-rose-300 tabular-nums">{{ $preview['error'] }}</p>
            </div>
        </div>

        @if (($preview['error'] ?? 0) > 0)
            <p class="text-xs text-rose-300">Aceptar está bloqueado hasta que no queden errores.</p>
        @endif

        <div class="rounded-lg border border-slate-800 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-900/80 text-slate-400 text-xs uppercase">
                    <tr>
                        <th class="px-3 py-3 text-left">Fila</th>
                        <th class="px-3 py-3 text-left">Estado</th>
                        <th class="px-3 py-3 text-left">Nombre</th>
                        <th class="px-3 py-3 text-left">Documento</th>
                        <th class="px-3 py-3 text-left">Detalle</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @foreach ($preview['rows'] as $row)
                        <tr
                            class="hover:bg-slate-900/40"
                            x-show="filter === 'all' || (filter === 'ok' && '{{ $row['status'] }}' !== 'error') || (filter === 'error' && '{{ $row['status'] }}' === 'error')"
                        >
                            <td class="px-3 py-3 text-slate-500 tabular-nums">{{ $row['line'] }}</td>
                            <td class="px-3 py-3">
                                @if ($row['status'] === 'error')
                                    <span class="text-xs text-rose-300">Error</span>
                                @elseif ($row['status'] === 'warning')
                                    <span class="text-xs text-amber-300">Aviso</span>
                                @else
                                    <span class="text-xs text-emerald-300">OK</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-white">{{ $row['name'] ?: '—' }}</td>
                            <td class="px-3 py-3 text-slate-300">{{ $row['document'] ?: '—' }}</td>
                            <td class="px-3 py-3 text-xs text-slate-400">
                                @foreach ($row['messages'] as $message)
                                    <p class="{{ $row['status'] === 'error' ? 'text-rose-300' : 'text-amber-300' }}">{{ $message }}</p>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-company-layout>
