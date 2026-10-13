<x-access-layout>
    <div class="-mt-6 -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 pt-6 pb-8 bg-gradient-to-r from-slate-800 to-indigo-900 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-indigo-300">Control de Acceso</p>
                <h2 class="text-xl font-bold text-white">Reservas</h2>
            </div>
            @can('access.manage.locations')
            <a href="{{ route('access.zones.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition-colors shadow-sm">
                <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Nueva Zona
            </a>
            @endcan
        </div>
    </div>

    @if($errors->any())
        <div class="rounded-lg bg-red-900/40 border border-red-700 text-red-200 px-4 py-3 text-sm mb-6">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if($todayBookings->isNotEmpty())
        <div class="bg-slate-900 rounded-xl border border-slate-800 overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-200">Reservas de hoy ({{ $todayBookings->count() }})</h3>
                <span class="text-xs text-slate-500">Escaneá el QR de la reserva para habilitar el uso</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wider text-slate-500 border-b border-slate-800">
                            <th class="px-6 py-3">Zona</th>
                            <th class="px-6 py-3">Reservada por</th>
                            <th class="px-6 py-3">Horario</th>
                            <th class="px-6 py-3">Personas</th>
                            <th class="px-6 py-3">Estado</th>
                            <th class="px-6 py-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/70">
                        @foreach($todayBookings as $booking)
                            <tr class="hover:bg-slate-800/40 transition-colors">
                                <td class="px-6 py-3 text-white font-medium">{{ $booking->zone?->name }}</td>
                                <td class="px-6 py-3 text-slate-300">{{ $booking->user?->name }}</td>
                                <td class="px-6 py-3 text-slate-300">{{ $booking->start_time->format('H:i') }} – {{ $booking->end_time->format('H:i') }}</td>
                                <td class="px-6 py-3 text-slate-400">{{ $booking->people_count }}</td>
                                <td class="px-6 py-3">
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium
                                        {{ $booking->status === 'pending' ? 'bg-amber-900/50 text-amber-300' : ($booking->status === 'confirmed' ? 'bg-sky-900/50 text-sky-300' : ($booking->status === 'checked_in' ? 'bg-emerald-900/50 text-emerald-300' : 'bg-slate-800 text-slate-400')) }}">
                                        {{ $booking->statusLabel() }}
                                    </span>
                                </td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        @if(in_array($booking->status, ['pending', 'confirmed'], true))
                                            <form method="POST" action="{{ route('access.zones.checkin') }}">
                                                @csrf
                                                <input type="hidden" name="qr_code" value="{{ $booking->qr_code }}">
                                                <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-xs font-semibold text-white transition-colors">Dar uso</button>
                                            </form>
                                        @endif
                                        @if($booking->status === 'checked_in')
                                            <form method="POST" action="{{ route('access.zones.complete', $booking) }}">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-slate-700 hover:bg-slate-600 rounded-lg text-xs font-semibold text-white transition-colors">Finalizar</button>
                                            </form>
                                        @endif
                                        @if(! in_array($booking->status, ['completed', 'cancelled'], true))
                                            <form method="POST" action="{{ route('access.zones.cancel', $booking) }}" onsubmit="return confirm('¿Cancelar esta reserva?');">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-red-900/60 hover:bg-red-800/70 rounded-lg text-xs font-semibold text-red-200 transition-colors">Cancelar</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-200">Zonas configuradas</h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 p-6">
            @forelse($zones as $zone)
                <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 {{ $zone->is_active ? '' : 'opacity-60' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-white">{{ $zone->name }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">{{ $zone->typeLabel() }} · Capacidad {{ $zone->capacity }}</p>
                        </div>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase {{ $zone->is_active ? 'bg-emerald-900/50 text-emerald-300' : 'bg-red-900/50 text-red-300' }}">
                            {{ $zone->is_active ? 'Activa' : 'Inactiva' }}
                        </span>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2 text-[11px] text-slate-400">
                        <span>🕐 {{ $zone->open_time?->format('H:i') }} – {{ $zone->close_time?->format('H:i') }}</span>
                        @if($zone->requires_approval)
                            <span class="text-amber-300">Requiere aprobación</span>
                        @endif
                    </div>
                    <div class="mt-3 text-xs text-slate-500">
                        {{ $zone->bookings_count }} reserva(s) de hoy
                    </div>
                    @if($zone->is_active && auth()->user()->can('access.manage.locations'))
                        <form method="POST" action="{{ route('access.zones.destroy', $zone) }}" class="mt-4" onsubmit="return confirm('¿Desactivar esta zona?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs font-medium text-red-400 hover:text-red-300">Desactivar</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="md:col-span-2 xl:col-span-3 text-center py-10 text-sm text-slate-500">No hay zonas comunes configuradas todavía.</div>
            @endforelse
        </div>
        <div class="px-6 py-4 border-t border-slate-800">
            {{ $zones->links() }}
        </div>
    </div>
</x-access-layout>