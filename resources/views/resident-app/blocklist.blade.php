<x-layouts.resident-app title="Bloqueos">
    <div class="space-y-3 pb-2">
        <p class="text-xs text-slate-500">Personas y vehículos bloqueados en tu conjunto (solo consulta).</p>

        @forelse ($entries as $entry)
            <article class="rounded-xl border border-slate-800 bg-slate-900 px-3 py-3">
                <p class="text-sm font-medium text-white">
                    {{ $entry->display_name ?: ($entry->plate ?: ($entry->document_number ?: 'Registro')) }}
                </p>
                <p class="text-[11px] text-slate-500">
                    @if ($entry->isVehicle())
                        Vehículo
                        @if ($entry->plate) · {{ strtoupper($entry->plate) }} @endif
                    @else
                        Persona
                        @if ($entry->document_number) · {{ $entry->document_number }} @endif
                    @endif
                </p>
                @if ($entry->reason)
                    <p class="mt-1 text-[11px] text-slate-400">{{ $entry->reason }}</p>
                @endif
            </article>
        @empty
            <p class="rounded-xl border border-dashed border-slate-800 px-3 py-6 text-center text-xs text-slate-500">
                No hay bloqueos activos.
            </p>
        @endforelse
    </div>
</x-layouts.resident-app>
