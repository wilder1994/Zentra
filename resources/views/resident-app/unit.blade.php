<x-layouts.resident-app title="Mi unidad">
    <div class="space-y-4 pb-2">
        <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
            <p class="text-[11px] uppercase tracking-wide text-slate-500">Estructura</p>
            <p class="mt-1 text-sm font-medium text-white">{{ $appUser->member?->structure?->full_path ?? '—' }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $appUser->client?->name }}</p>
            <p class="mt-2 text-[11px] text-slate-600">Solo consulta. Los cambios los hace administración del conjunto.</p>
        </section>

        <section class="space-y-2">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Personas</h2>
            @forelse ($members as $member)
                <div class="rounded-xl border border-slate-800 bg-slate-900 px-3 py-3">
                    <p class="text-sm text-white">{{ $member->full_name }}</p>
                    <p class="text-[11px] text-slate-500">
                        {{ $member->memberType?->name ?? 'Persona' }}
                        @if ($member->document_number)
                            · {{ $member->document_type }} {{ $member->document_number }}
                        @endif
                    </p>
                </div>
            @empty
                <p class="rounded-xl border border-dashed border-slate-800 px-3 py-4 text-xs text-slate-500">Sin personas registradas en la unidad.</p>
            @endforelse
        </section>

        <section class="space-y-2">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Vehículos</h2>
            @forelse ($vehicles as $vehicle)
                <div class="rounded-xl border border-slate-800 bg-slate-900 px-3 py-3">
                    <p class="text-sm font-medium text-white uppercase">{{ $vehicle->plate }}</p>
                    <p class="text-[11px] text-slate-500">
                        {{ trim(implode(' · ', array_filter([$vehicle->brand, $vehicle->model, $vehicle->color, $vehicle->type]))) ?: 'Sin detalle' }}
                    </p>
                </div>
            @empty
                <p class="rounded-xl border border-dashed border-slate-800 px-3 py-4 text-xs text-slate-500">Sin vehículos de la unidad.</p>
            @endforelse
        </section>
    </div>
</x-layouts.resident-app>
