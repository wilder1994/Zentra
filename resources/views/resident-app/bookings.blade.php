<x-layouts.resident-app title="Reservas">
    <div class="space-y-3 pb-2">
        <div class="flex items-center justify-between gap-2">
            <p class="text-xs text-slate-500">Zonas comunes de tu instalación</p>
            <a href="{{ route('resident-app.bookings.create') }}"
               class="rounded-lg bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white">Nueva</a>
        </div>

        @forelse ($bookings as $booking)
            <article class="rounded-xl border border-slate-800 bg-slate-900 px-3 py-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-medium text-white">{{ $booking->zone?->name ?? 'Zona' }}</p>
                        <p class="text-[11px] text-slate-500">
                            {{ $booking->date?->format('d/m/Y') }}
                            @if ($booking->date_until)
                                → {{ $booking->date_until->format('d/m/Y') }}
                            @endif
                            · {{ $booking->full_day ? 'Todo el día' : ($booking->start_time.'–'.$booking->end_time) }}
                        </p>
                        <p class="mt-1 text-[11px] text-slate-400">
                            {{ $booking->status->label() }} · {{ $booking->people_count }} pers.
                        </p>
                    </div>
                    @if (in_array($booking->status->value, \App\Enums\BookingStatus::open(), true))
                        <form method="POST" action="{{ route('resident-app.bookings.destroy', $booking) }}"
                              onsubmit="return confirm('¿Cancelar esta reserva?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-[11px] text-amber-400">Cancelar</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <p class="rounded-xl border border-dashed border-slate-800 px-3 py-6 text-center text-xs text-slate-500">
                Aún no tienes reservas. Crea la primera.
            </p>
        @endforelse
    </div>
</x-layouts.resident-app>
