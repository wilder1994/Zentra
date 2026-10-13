<x-client-layout title="Zonas reservables">
    @php
        $input = 'h-10 w-full rounded-lg bg-slate-950 border border-slate-700 px-3 text-sm text-white';
        $label = 'mb-1 block text-xs text-slate-400';
        $blank = [
            'id' => null,
            'installation_id' => $installations->count() === 1 ? (string) $installations->first()->id : '',
            'name' => '', 'type' => 'salon', 'booking_mode' => 'exclusive', 'capacity' => 30,
            'open_time' => '08:00', 'close_time' => '22:00', 'allow_full_day' => true,
            'weekdays' => array_keys($weekdays), 'min_hours' => 1, 'max_hours' => '', 'buffer_minutes' => 0,
            'max_advance_days' => 90, 'max_per_unit_month' => '', 'rules' => '', 'is_active' => true,
        ];
        $rows = $zones->map(fn ($z) => [
            'id' => $z->id,
            'installation_id' => (string) $z->installation_id,
            'name' => $z->name, 'type' => $z->type, 'booking_mode' => $z->booking_mode, 'capacity' => $z->capacity,
            'open_time' => $z->openTime(), 'close_time' => $z->closeTime(), 'allow_full_day' => $z->allow_full_day,
            'weekdays' => $z->allowedWeekdays(), 'min_hours' => $z->min_minutes / 60,
            'max_hours' => $z->max_minutes !== null ? $z->max_minutes / 60 : '', 'buffer_minutes' => $z->buffer_minutes,
            'max_advance_days' => $z->max_advance_days, 'max_per_unit_month' => $z->max_per_unit_month ?? '',
            'rules' => $z->rules ?? '', 'is_active' => $z->is_active,
        ])->keyBy('id');
        $reopen = $errors->any() && old('name') !== null
            ? array_merge($blank, old(), [
                'id' => old('zone_id') ? (int) old('zone_id') : null,
                'weekdays' => array_map('intval', (array) old('weekdays', [])),
                'allow_full_day' => (bool) old('allow_full_day'),
                'is_active' => (bool) old('is_active'),
            ])
            : null;
    @endphp

    <div class="mx-auto max-w-6xl space-y-5" x-data="zoneManager(@js(['blank' => $blank, 'rows' => $rows, 'reopen' => $reopen]))">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('client.bookings.index') }}" class="text-sm text-slate-400 hover:text-white">← Calendario de reservas</a>
                <p class="mt-1 text-sm text-slate-500">Define dónde, cuándo y cómo se puede reservar cada zona común.</p>
            </div>
            <button type="button" @click="edit()" class="inline-flex h-10 items-center rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-500">+ Nueva zona</button>
        </div>

        @if ($zones->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/50 px-6 py-14 text-center">
                <p class="text-slate-300">Aún no hay zonas reservables.</p>
                <p class="mt-1 text-sm text-slate-500">Crea el salón social, la zona BBQ, la piscina… con su horario y reglas.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($zones as $z)
                    <div @class(['flex flex-col rounded-2xl border border-slate-800 bg-slate-900 p-4', 'opacity-60' => ! $z->is_active])>
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-base font-semibold text-white">{{ $z->name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ $z->typeLabel() }}@if ($installations->count() > 1) · {{ $z->installation?->name }}@endif</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $z->is_active ? 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/40' : 'bg-slate-700/40 text-slate-300 ring-slate-600' }}">{{ $z->is_active ? 'Activa' : 'Inactiva' }}</span>
                        </div>
                        <dl class="mt-3 space-y-1.5 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Uso</dt><dd class="text-right text-slate-200">{{ $z->isShared() ? "Por cupos · {$z->capacity} personas" : "Exclusiva · hasta {$z->capacity} personas" }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Horario</dt><dd class="text-right text-slate-200">{{ $z->scheduleLabel() }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Días</dt><dd class="text-right text-slate-200">{{ $z->weekdaysLabel() }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Reglas</dt><dd class="text-right text-slate-200">
                                {{ $z->allow_full_day ? 'Día completo o por horas' : 'Solo por horas' }}{{ $z->max_per_unit_month ? " · {$z->max_per_unit_month}/mes por unidad" : '' }}
                            </dd></div>
                        </dl>
                        <div class="mt-auto flex justify-end gap-2 pt-4">
                            <a href="{{ route('client.bookings.index', ['zone' => $z->id]) }}" class="rounded-lg px-3 py-1.5 text-sm text-slate-400 hover:text-white">Ver calendario</a>
                            <button type="button" @click="edit({{ $z->id }})" class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm font-semibold text-slate-200 hover:opacity-80">Editar</button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <template x-teleport="body">
            <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-10" @keydown.escape.window="open = false">
                <div class="absolute inset-0 bg-black/50" @click="open = false"></div>
                <form method="POST" :action="form.id ? @js(url('client/bookings/zones')) + '/' + form.id : @js(route('client.bookings.zones.store'))"
                      class="relative w-full max-w-2xl space-y-4 rounded-2xl border border-slate-700 bg-slate-900 p-5 shadow-2xl">
                    @csrf
                    <template x-if="form.id"><input type="hidden" name="_method" value="PUT"></template>
                    <input type="hidden" name="zone_id" :value="form.id">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-white" x-text="form.id ? 'Editar zona' : 'Nueva zona'"></h3>
                        <button type="button" @click="open = false" class="text-slate-400 hover:text-white" aria-label="Cerrar">✕</button>
                    </div>

                    @if ($errors->any())
                        <div class="rounded-lg border border-red-800 bg-red-950/40 px-3 py-2 text-sm text-red-200">{{ $errors->first() }}</div>
                    @endif

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2" @if ($installations->count() === 1) hidden @endif>
                            <label class="{{ $label }}">Instalación</label>
                            <select name="installation_id" x-model="form.installation_id" class="{{ $input }}">
                                <option value="">Seleccione instalación</option>
                                @foreach ($installations as $installation)
                                    <option value="{{ $installation->id }}">{{ $installation->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="{{ $label }}">Nombre</label>
                            <input name="name" x-model="form.name" required maxlength="100" placeholder="Ej. Salón social torre 2" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="{{ $label }}">Tipo</label>
                            <select name="type" x-model="form.type" class="{{ $input }}">
                                @foreach ($types as $value => $typeLabel)
                                    <option value="{{ $value }}">{{ $typeLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="{{ $label }}">¿Cómo se usa?</label>
                        <input type="hidden" name="booking_mode" :value="form.booking_mode">
                        <div class="grid gap-2 sm:grid-cols-2">
                            <button type="button" @click="form.booking_mode = 'exclusive'" :class="form.booking_mode === 'exclusive' ? 'ring-2 ring-indigo-500' : 'ring-1 ring-slate-700'" class="rounded-lg bg-slate-950 px-3 py-2 text-left">
                                <span class="block text-sm font-semibold text-white">Exclusiva</span>
                                <span class="block text-xs text-slate-500">Una unidad a la vez (salón, BBQ).</span>
                            </button>
                            <button type="button" @click="form.booking_mode = 'shared'" :class="form.booking_mode === 'shared' ? 'ring-2 ring-indigo-500' : 'ring-1 ring-slate-700'" class="rounded-lg bg-slate-950 px-3 py-2 text-left">
                                <span class="block text-sm font-semibold text-white">Por cupos</span>
                                <span class="block text-xs text-slate-500">Varias unidades hasta la capacidad (piscina, gimnasio).</span>
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="{{ $label }}">Capacidad (personas)</label>
                            <input type="number" name="capacity" x-model="form.capacity" min="1" max="1000" required class="{{ $input }}">
                        </div>
                        <div>
                            <label class="{{ $label }}">Abre</label>
                            <input type="time" name="open_time" x-model="form.open_time" required class="{{ $input }}">
                        </div>
                        <div>
                            <label class="{{ $label }}">Cierra</label>
                            <input type="time" name="close_time" x-model="form.close_time" required class="{{ $input }}">
                        </div>
                    </div>

                    <div>
                        <label class="{{ $label }}">Días en que se puede reservar</label>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($weekdays as $value => $short)
                                <label class="cursor-pointer">
                                    <input type="checkbox" name="weekdays[]" value="{{ $value }}" x-model.number="form.weekdays" class="peer sr-only">
                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-slate-950 text-sm font-semibold text-slate-400 ring-1 ring-slate-700 peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:ring-0">{{ $short }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="{{ $label }}">Mínimo por reserva (horas)</label>
                            <input type="number" name="min_hours" x-model="form.min_hours" step="0.5" min="0.5" max="24" required class="{{ $input }}">
                        </div>
                        <div>
                            <label class="{{ $label }}">Máximo por reserva (horas)</label>
                            <input type="number" name="max_hours" x-model="form.max_hours" step="0.5" min="0.5" max="24" placeholder="Sin límite" class="{{ $input }}">
                        </div>
                        <div>
                            <label class="{{ $label }}">Limpieza entre reservas (min)</label>
                            <input type="number" name="buffer_minutes" x-model="form.buffer_minutes" min="0" max="240" step="15" required class="{{ $input }}">
                        </div>
                        <div>
                            <label class="{{ $label }}">Anticipación máxima (días)</label>
                            <input type="number" name="max_advance_days" x-model="form.max_advance_days" min="1" max="365" required class="{{ $input }}">
                        </div>
                        <div>
                            <label class="{{ $label }}">Reservas por unidad al mes</label>
                            <input type="number" name="max_per_unit_month" x-model="form.max_per_unit_month" min="1" max="100" placeholder="Sin límite" class="{{ $input }}">
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-x-6 gap-y-2">
                        <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-200">
                            <input type="checkbox" name="allow_full_day" value="1" x-model="form.allow_full_day" class="h-4 w-4 rounded border-slate-400 text-[var(--ui-accent)] focus:ring-[var(--ui-accent)]">
                            Se puede reservar el día completo
                        </label>
                        <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-200">
                            <input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="h-4 w-4 rounded border-slate-400 text-[var(--ui-accent)] focus:ring-[var(--ui-accent)]">
                            Zona activa
                        </label>
                    </div>

                    <div>
                        <label class="{{ $label }}">Reglas de uso (opcional)</label>
                        <textarea name="rules" x-model="form.rules" rows="3" maxlength="2000" placeholder="Ej. Entregar aseado. Música hasta las 10 p. m." class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white"></textarea>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="h-10 rounded-lg px-4 text-sm text-slate-400 hover:text-white">Cancelar</button>
                        <button class="h-10 rounded-lg bg-indigo-600 px-5 text-sm font-semibold text-white hover:bg-indigo-500" x-text="form.id ? 'Guardar cambios' : 'Crear zona'"></button>
                    </div>
                </form>
            </div>
        </template>
    </div>

    @push('scripts')
        <script>
            function zoneManager(cfg) {
                return {
                    open: false,
                    form: { ...cfg.blank },
                    init() {
                        if (cfg.reopen) {
                            this.form = { ...cfg.reopen };
                            this.open = true;
                        }
                    },
                    edit(id = null) {
                        const row = id ? cfg.rows[id] : cfg.blank;
                        this.form = { ...row, weekdays: [...row.weekdays] };
                        this.open = true;
                    },
                };
            }
        </script>
    @endpush
</x-client-layout>
