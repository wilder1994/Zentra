<nav class="inline-flex h-10 items-center gap-1 rounded-lg bg-slate-900 p-1 ring-1 ring-slate-800">
    @foreach (['hoy' => 'Hoy', 'proximas' => 'Próximas', 'historial' => 'Historial'] as $key => $label)
        <a href="{{ route('access.zones.index', ['tab' => $key]) }}"
           class="inline-flex h-8 items-center whitespace-nowrap rounded-md px-3 text-sm font-semibold {{ $tab === $key ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white' }}">
            {{ $label }}@isset($counts[$key])<span class="ml-1 text-xs opacity-80">({{ $counts[$key] }})</span>@endisset
        </a>
    @endforeach
    <a href="{{ route('access.zones.calendar') }}"
       class="inline-flex h-8 items-center whitespace-nowrap rounded-md px-3 text-sm font-semibold {{ $tab === 'calendario' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white' }}">
        Calendario
    </a>
</nav>
