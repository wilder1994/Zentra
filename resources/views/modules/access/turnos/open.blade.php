@php
    $templateOptions = $templates->map(fn ($t) => [
        'id' => (int) $t->id,
        'name' => $t->name,
        'schedule' => $t->scheduleLabel(),
    ])->values();
@endphp
<x-access-layout title="Iniciar turno">
    <x-slot:actions>
        @unless($requiresShift ?? false)
            <x-ui.button variant="secondary" href="{{ route('access.turnos.index') }}">← Mis turnos</x-ui.button>
        @endunless
    </x-slot:actions>

    <div class="max-w-lg mx-auto">
        <div class="rounded-xl border border-slate-800 bg-slate-900"
             x-data="turnoOpenForm(@js($templateOptions), @js(old('shift_template_id') ? (int) old('shift_template_id') : null))">
            <div class="border-b border-slate-800 px-5 py-4">
                <h3 class="text-base font-semibold text-white">Iniciar turno</h3>
                <p class="mt-1 text-sm text-slate-500">Hora del servidor · elige turno · nota opcional en la minuta.</p>
            </div>

            <form method="POST" action="{{ route('access.turnos.store') }}" class="space-y-5 px-5 py-5" novalidate>
                @csrf

                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-lg border border-slate-800 bg-slate-950/80 px-3 py-2.5">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Hora</p>
                        <p class="mt-0.5 text-xl font-semibold tabular-nums text-white">{{ $serverNow->format('H:i') }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-800 bg-slate-950/80 px-3 py-2.5">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Fecha</p>
                        <p class="mt-0.5 text-xl font-semibold tabular-nums text-white">{{ $serverNow->format('d/m/Y') }}</p>
                    </div>
                </div>

                <div>
                    <x-ui.label for="shift_template_id">Turno</x-ui.label>
                    @if($templates->isEmpty())
                        <p class="mt-2 text-sm text-amber-200">No hay turnos activos en Ajustes de la empresa. Activa Día, Noche o Turno A/B/C.</p>
                    @else
                        <select id="shift_template_id"
                                name="shift_template_id"
                                required
                                x-model.number="templateId"
                                class="mt-1.5 block h-10 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 text-sm text-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500/40">
                            <option value="" disabled @selected(! old('shift_template_id'))>Selecciona…</option>
                            @foreach($templates as $template)
                                <option value="{{ $template->id }}" @selected((int) old('shift_template_id') === (int) $template->id)>
                                    {{ $template->name }}
                                </option>
                            @endforeach
                        </select>
                        <p x-show="selected" x-cloak class="mt-2 text-sm text-slate-300">
                            Horario:
                            <span class="font-semibold tabular-nums text-white" x-text="selected?.schedule"></span>
                        </p>
                    @endif
                    <x-ui.field-error :messages="$errors->get('shift_template_id')" />
                </div>

                @if($locations->isEmpty())
                    <p class="text-sm text-amber-200">Este cliente no tiene puertas activas.</p>
                @elseif($locations->count() === 1)
                    <input type="hidden" name="location_id" value="{{ $locations->first()->id }}">
                    <div>
                        <x-ui.label>Puerta</x-ui.label>
                        <p class="mt-1.5 text-sm text-slate-200">{{ $locations->first()->name }}</p>
                    </div>
                @else
                    <div>
                        <x-ui.label for="location_id">Puerta</x-ui.label>
                        <select id="location_id" name="location_id" required
                                class="mt-1.5 block h-10 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 text-sm text-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500/40">
                            <option value="">Selecciona…</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}" @selected((int) old('location_id', $selectedId ?? 0) === (int) $loc->id)>{{ $loc->name }}</option>
                            @endforeach
                        </select>
                        <x-ui.field-error :messages="$errors->get('location_id')" />
                    </div>
                @endif

                <div>
                    <x-ui.label for="start_notes">Nota de apertura <span class="font-normal text-slate-500">(opcional)</span></x-ui.label>
                    <textarea id="start_notes" name="start_notes" rows="2"
                              class="mt-1.5 block w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white placeholder:text-slate-600 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500/40"
                              placeholder="Pendientes del puesto…">{{ old('start_notes') }}</textarea>
                </div>

                @if($locations->isNotEmpty() && $templates->isNotEmpty())
                    <div class="flex justify-end border-t border-slate-800 pt-4">
                        <x-ui.button type="submit" size="md">Iniciar turno</x-ui.button>
                    </div>
                @endif
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function turnoOpenForm(templates, initialId) {
            return {
                templates: templates || [],
                templateId: initialId || '',
                get selected() {
                    const id = Number(this.templateId);
                    if (! id) return null;
                    return this.templates.find(t => t.id === id) || null;
                }
            }
        }
    </script>
    @endpush
</x-access-layout>
