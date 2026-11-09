<x-layouts.resident-app title="Nueva reserva">
    <form method="POST" action="{{ route('resident-app.bookings.store') }}" class="space-y-3 pb-4"
          x-data="{ fullDay: @js((bool) old('full_day', false)) }">
        @csrf
        <a href="{{ route('resident-app.bookings') }}" class="text-xs text-slate-400">← Mis reservas</a>

        @if ($zones->isEmpty())
            <p class="rounded-xl border border-amber-800/50 bg-amber-950/30 px-3 py-3 text-xs text-amber-100">
                No hay zonas activas en tu instalación. Pide a administración que las configure.
            </p>
        @else
            <div>
                <label class="block text-xs text-slate-400 mb-1">Zona</label>
                <select name="common_zone_id" required class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-3 text-sm text-white">
                    @foreach ($zones as $zone)
                        <option value="{{ $zone->id }}" @selected((int) old('common_zone_id') === (int) $zone->id)>{{ $zone->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Desde</label>
                    <input type="date" name="date" value="{{ old('date', now()->toDateString()) }}" required
                           class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-3 text-sm text-white">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Hasta</label>
                    <input type="date" name="date_until" value="{{ old('date_until') }}"
                           class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-3 text-sm text-white">
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input type="checkbox" name="full_day" value="1" x-model="fullDay" class="rounded border-slate-600 bg-slate-900 text-teal-600">
                Todo el día
            </label>

            <div class="grid grid-cols-2 gap-2" x-show="!fullDay">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Hora inicio</label>
                    <input type="time" name="start_time" value="{{ old('start_time') }}"
                           :required="!fullDay"
                           class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-3 text-sm text-white">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Hora fin</label>
                    <input type="time" name="end_time" value="{{ old('end_time') }}"
                           :required="!fullDay"
                           class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-3 text-sm text-white">
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1">Personas</label>
                <input type="number" name="people_count" min="1" max="200" value="{{ old('people_count', 1) }}" required
                       class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-3 text-sm text-white">
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1">Notas</label>
                <textarea name="notes" rows="2" class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-3 text-sm text-white">{{ old('notes') }}</textarea>
            </div>

            @if ($errors->any())
                <ul class="rounded-lg border border-red-800 bg-red-950/40 px-3 py-2 text-xs text-red-200 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            <button type="submit" class="w-full rounded-xl bg-teal-600 py-3 text-sm font-semibold text-white">Solicitar reserva</button>
        @endif
    </form>
</x-layouts.resident-app>
