<x-access-layout title="Abrir turno / puerta">
    <x-slot:actions>
        <x-ui.button variant="secondary" href="{{ route('access.turnos.index') }}">← Mis turnos</x-ui.button>
    </x-slot:actions>

    <div class="max-w-xl">
        <div class="bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800">
                <h3 class="text-sm font-semibold text-slate-200">Puerta que vas a operar</h3>
                <p class="text-xs text-slate-500 mt-1">
                    @if($locations->count() === 1)
                        Hay una sola puerta: el sistema la asigna a tu usuario. El pánico sale con tu nombre y esa puerta.
                    @else
                        Hay varias puertas. Elige una; el pánico y el turno quedan ligados a esa puerta y a tu usuario.
                    @endif
                </p>
            </div>
            <form method="POST" action="{{ route('access.turnos.store') }}" class="p-6 space-y-5" novalidate>
                @csrf

                @if($locations->isEmpty())
                    <p class="text-sm text-amber-200">Este cliente no tiene puertas activas. Sin puerta no hay portería ni pánico.</p>
                @elseif($locations->count() === 1)
                    <input type="hidden" name="location_id" value="{{ $locations->first()->id }}">
                    <p class="text-sm text-slate-200">{{ $locations->first()->name }}</p>
                @else
                    <div>
                        <x-ui.label for="location_id">Puerta</x-ui.label>
                        <select id="location_id" name="location_id" required class="mt-1 block w-full h-9 rounded-lg bg-slate-950 border border-slate-700 text-white text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30">
                            <option value="">Selecciona…</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}" @selected((int) ($selectedId ?? 0) === (int) $loc->id)>{{ $loc->name }}</option>
                            @endforeach
                        </select>
                        <x-ui.field-error :messages="$errors->get('location_id')" />
                    </div>
                @endif

                <div>
                    <x-ui.label for="start_notes">Nota de apertura (opcional)</x-ui.label>
                    <textarea id="start_notes" name="start_notes" rows="3" class="mt-1 block w-full rounded-lg bg-slate-950 border border-slate-700 text-white text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30" placeholder="Estado del turno, pendientes…"></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <x-ui.button variant="secondary" :href="route('access.turnos.index')">Cancelar</x-ui.button>
                    @if($locations->isNotEmpty())
                    <x-ui.button type="submit" size="md">{{ $locations->count() === 1 ? 'Iniciar turno' : 'Operar esta puerta' }}</x-ui.button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</x-access-layout>
