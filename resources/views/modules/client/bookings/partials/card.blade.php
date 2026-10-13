@php
    /** @var \App\Models\CommonZoneBooking $booking */
    $isToday = $booking->covers(today());
@endphp
<div class="flex flex-col rounded-2xl border border-slate-800 bg-slate-900 p-4">
    <div class="flex items-start justify-between gap-2">
        <span class="rounded-md bg-slate-800 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-300">{{ $booking->zone?->name }}</span>
        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $booking->status->badge() }}">{{ $booking->statusLabel() }}</span>
    </div>

    <p class="mt-3 truncate text-base font-semibold text-white">{{ $booking->unitLabel() }}</p>
    <p class="truncate text-sm text-slate-400">{{ $booking->requesterLabel() }}</p>

    <dl class="mt-3 space-y-1.5 text-sm">
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Fecha</dt><dd class="text-right text-slate-200">{{ $booking->datesLabel() }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Horario</dt><dd class="text-right text-slate-200">{{ $booking->hoursLabel() }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Personas</dt><dd class="text-right text-slate-200">{{ $booking->people_count }}</dd></div>
        @if ($booking->guestsCount() > 0)
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Invitados</dt><dd class="text-right text-slate-200">{{ $booking->guestsCount() }} en autorización</dd></div>
        @endif
    </dl>

    @if ($booking->notes)
        <p class="mt-3 rounded-lg bg-slate-950/60 px-3 py-2 text-xs text-slate-300">{{ $booking->notes }}</p>
    @endif

    @if (! empty($porteria))
        <div class="mt-auto flex gap-2 pt-4">
            @if ($booking->status === \App\Enums\BookingStatus::Confirmed && $isToday)
                <form method="POST" action="{{ route('access.zones.checkin', $booking) }}" class="flex-1">
                    @csrf @method('PATCH')
                    <button class="w-full rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Dar uso</button>
                </form>
            @elseif ($booking->status === \App\Enums\BookingStatus::CheckedIn)
                <form method="POST" action="{{ route('access.zones.complete', $booking) }}" class="flex-1">
                    @csrf @method('PATCH')
                    <button class="w-full rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Finalizar</button>
                </form>
            @endif
            @if ($booking->authorization && $isToday)
                <a href="{{ route('access.logs.index', ['tab' => 'movimiento', 'q' => $booking->authorization->qr_auth_token]) }}"
                   class="flex-1 rounded-lg border border-slate-700 px-3 py-2 text-center text-sm font-semibold text-slate-200 hover:opacity-80">Invitados</a>
            @endif
        </div>
    @endif
</div>
