<x-access-layout title="Reservas">
    <div class="space-y-5">
        @if ($errors->any())
            <div class="rounded-lg border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-200">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <nav class="inline-flex h-10 items-center gap-1 rounded-lg bg-slate-900 p-1 ring-1 ring-slate-800">
                @foreach (['hoy' => 'Hoy', 'proximas' => 'Próximas', 'historial' => 'Historial'] as $key => $label)
                    <a href="{{ route('access.zones.index', ['tab' => $key]) }}"
                       class="inline-flex h-8 items-center whitespace-nowrap rounded-md px-3 text-sm font-semibold {{ $tab === $key ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white' }}">
                        {{ $label }}@isset($counts[$key])<span class="ml-1 text-xs opacity-80">({{ $counts[$key] }})</span>@endisset
                    </a>
                @endforeach
            </nav>
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
