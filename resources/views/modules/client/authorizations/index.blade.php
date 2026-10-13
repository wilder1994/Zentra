<x-client-layout title="Autorizaciones" :wide="true">
    <x-slot:actions>
        <a href="{{ route('client.authorizations.import') }}" class="rounded-lg bg-slate-800 px-3 py-2 text-sm text-white hover:bg-slate-700">Importar Excel</a>
        <a href="{{ route('client.authorizations.create') }}" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-500">Nueva autorización</a>
    </x-slot:actions>

    <div class="space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <nav class="flex gap-1 rounded-xl bg-slate-900 p-1 ring-1 ring-slate-800">
                @foreach (['hoy' => 'Hoy', 'proximas' => 'Próximas', 'historial' => 'Historial'] as $key => $label)
                    <a href="{{ route('client.authorizations.index', ['tab' => $key]) }}"
                       class="rounded-lg px-4 py-2 text-sm font-medium {{ $tab === $key ? 'bg-teal-600 text-white' : 'text-slate-400 hover:text-white' }}">
                        {{ $label }}@isset($counts[$key]) <span class="ml-1 text-xs opacity-80">({{ $counts[$key] }})</span>@endisset
                    </a>
                @endforeach
            </nav>

            <form method="GET" class="flex flex-wrap items-end gap-2">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Nombre, documento, unidad o placa"
                       class="w-64 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white">
                <select name="type" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white">
                    <option value="">Todos los tipos</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-600">Filtrar</button>
            </form>
        </div>

        @if ($authorizations->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/50 px-6 py-14 text-center">
                <p class="text-slate-300 font-medium">
                    {{ match ($tab) { 'proximas' => 'No hay autorizaciones programadas.', 'historial' => 'Aún no hay historial.', default => 'No hay autorizaciones vigentes hoy.' } }}
                </p>
                <a href="{{ route('client.authorizations.create') }}" class="mt-4 inline-flex rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-500">Crear autorización</a>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                @foreach ($authorizations as $auth)
                    @include('modules.client.authorizations.partials.card', ['auth' => $auth, 'href' => route('client.authorizations.show', $auth)])
                @endforeach
            </div>
            {{ $authorizations->links() }}
        @endif
    </div>
</x-client-layout>
