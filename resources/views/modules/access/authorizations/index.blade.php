<x-access-layout title="Autorizaciones">
    <div class="space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <nav class="flex gap-1 rounded-xl bg-slate-900 p-1 ring-1 ring-slate-800">
                @foreach (['hoy' => 'Hoy', 'proximas' => 'Próximas', 'historial' => 'Historial'] as $key => $label)
                    <a href="{{ route('access.authorizations.index', ['tab' => $key]) }}"
                       class="rounded-lg px-4 py-2 text-sm font-semibold {{ $tab === $key ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white' }}">
                        {{ $label }}@isset($counts[$key]) <span class="ml-1 text-xs opacity-80">({{ $counts[$key] }})</span>@endisset
                    </a>
                @endforeach
            </nav>
            <form method="GET" class="flex flex-wrap items-end gap-2">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Nombre, documento, unidad o placa"
                       class="w-64 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                <select name="type" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                    <option value="">Todos los tipos</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-600">Filtrar</button>
            </form>
        </div>

        @if ($authorizations->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/50 px-6 py-14 text-center text-slate-400">
                {{ match ($tab) { 'proximas' => 'No hay autorizaciones programadas.', 'historial' => 'Aún no hay historial.', default => 'No hay autorizaciones vigentes hoy.' } }}
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                @foreach ($authorizations as $auth)
                    @php $movimiento = route('access.logs.index', ['tab' => 'movimiento', 'q' => $auth->qr_auth_token]); @endphp
                    @include('modules.client.authorizations.partials.card', [
                        'auth' => $auth,
                        'href' => $movimiento,
                        'ingresarUrl' => $tab === 'hoy' ? $movimiento : null,
                    ])
                @endforeach
            </div>
            {{ $authorizations->links() }}
        @endif
    </div>
</x-access-layout>
