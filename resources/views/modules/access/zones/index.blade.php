<x-access-layout title="Reservas">
    <div class="space-y-5">
        @if ($errors->any())
            <div class="rounded-lg border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-200">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            @include('modules.access.zones.partials.tabs')
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Unidad, persona o zona"
                       class="h-10 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 text-sm text-white sm:w-72">
                <select name="zone" class="h-10 rounded-lg border border-slate-700 bg-slate-950 pl-3 pr-8 text-sm text-white">
                    <option value="">Todas las zonas</option>
                    @foreach ($zones as $z)
                        <option value="{{ $z->id }}" @selected((string) request('zone') === (string) $z->id)>{{ $z->name }}</option>
                    @endforeach
                </select>
                <button class="inline-flex h-10 items-center rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-500">Filtrar</button>
            </form>
        </div>

        @if ($bookings->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/50 px-6 py-14 text-center text-slate-400">
                {{ match ($tab) { 'proximas' => 'No hay reservas programadas.', 'historial' => 'Aún no hay historial.', default => 'No hay reservas para hoy.' } }}
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                @foreach ($bookings as $booking)
                    @include('modules.client.bookings.partials.card', ['booking' => $booking, 'porteria' => $tab === 'hoy'])
                @endforeach
            </div>
            {{ $bookings->links() }}
        @endif
    </div>
</x-access-layout>
