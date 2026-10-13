<x-access-layout title="Mis Turnos">
    <x-slot:actions>
        @if($currentShift)
            <form method="POST" action="{{ route('access.turnos.close') }}" onsubmit="return confirm('¿Cerrar el turno actual?');">
                @csrf
                <input type="hidden" name="end_notes" value="">
                <x-ui.button type="submit" variant="secondary">Cerrar Turno</x-ui.button>
            </form>
        @else
            <x-ui.button href="{{ route('access.turnos.open') }}">Abrir Turno</x-ui.button>
        @endif
    </x-slot:actions>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        @if($currentShift)
            <div class="bg-emerald-900/40 border border-emerald-700 rounded-xl p-5 md:col-span-3">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-emerald-400 font-semibold">Turno en curso</p>
                        <p class="mt-1 text-2xl font-bold text-white">Desde {{ $currentShift->started_at->format('H:i') }} h</p>
                        <div class="mt-2 flex flex-wrap gap-3 text-xs text-slate-300">
                            <span class="inline-flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Activo</span>
                            @if($currentShift->location)
                                <span class="inline-flex items-center gap-1">📍 {{ $currentShift->location->name }}</span>
                            @endif
                            <span>🗓 {{ $currentShift->started_at->format('d/m/Y') }}</span>
                        </div>
                    </div>
                    <div class="text-right text-xs text-slate-400">
                        <p>Duración actual</p>
                        <p class="text-lg font-bold text-emerald-300">{{ $currentShift->started_at->diffForHumans(now(), ['parts' => 2, 'short' => true]) }}</p>
                    </div>
                </div>
            </div>
        @else
            <div class="bg-amber-900/30 border border-amber-700/70 rounded-xl p-5 md:col-span-3">
                <p class="text-sm text-amber-200">No tienes un turno abierto. <a href="{{ route('access.turnos.open') }}" class="font-semibold underline">Abrir turno</a> para habilitar las operaciones de portería.</p>
            </div>
        @endif
    </div>

    <div class="bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-200">Historial de turnos</h3>
            <span class="text-xs text-slate-500">{{ $history->total() }} registros</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wider text-slate-500 border-b border-slate-800">
                        <th class="px-6 py-3">Inicio</th>
                        <th class="px-6 py-3">Fin</th>
                        <th class="px-6 py-3">Duración</th>
                        <th class="px-6 py-3">Ubicación</th>
                        <th class="px-6 py-3">Nota de cierre</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/70">
                    @forelse($history as $shift)
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="px-6 py-3 text-white">{{ $shift->started_at->format('d/m/Y H:i') }}</td>
                            <td class="px-6 py-3 text-slate-300">{{ $shift->ended_at ? $shift->ended_at->format('d/m/Y H:i') : '—' }}</td>
                            <td class="px-6 py-3 text-slate-400">
                                @if($shift->ended_at)
                                    {{ (int)$shift->started_at->diffInMinutes($shift->ended_at) / 60 >= 1 ? number_format($shift->started_at->diffInHours($shift->ended_at), 1) . ' h' : $shift->started_at->diffInMinutes($shift->ended_at) . ' min' }}
                                @else
                                    <span class="text-emerald-400 font-medium">En curso</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-slate-300">{{ $shift->location?->name ?? 'No especificada' }}</td>
                            <td class="px-6 py-3 text-slate-400 max-w-xs truncate">{{ $shift->end_notes ?? ($shift->start_notes ?? '—') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-slate-500">Sin turnos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-slate-800">
            {{ $history->links() }}
        </div>
    </div>
</x-access-layout>