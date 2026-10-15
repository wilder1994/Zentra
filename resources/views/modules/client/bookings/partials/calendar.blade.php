{{-- $mode: 'admin' (aprueba y confirma) | 'porteria' (consulta y solicita) --}}
    @php
        $admin = $mode === 'admin';
        $input = 'h-10 w-full rounded-lg bg-slate-950 border border-slate-700 px-3 text-sm text-white';
        $label = 'mb-1 block text-xs text-slate-400';

        $present = fn ($b) => [
            'id' => $b->id,
            'zone_id' => $b->common_zone_id,
            'zone' => $b->zone?->name,
            'status' => $b->status->value,
            'status_label' => $b->statusLabel(),
            'badge' => $b->status->badge(),
            'unit' => $b->unitLabel(),
            'requester' => $b->requesterLabel(),
            'dates' => $b->datesLabel(),
            'hours' => $b->hoursLabel(),
            'people' => $b->people_count,
            'notes' => $b->notes,
            'origin' => $b->origin,
            'structure_id' => (string) $b->structure_id,
            'structure_member_id' => (string) ($b->structure_member_id ?? ''),
            'date' => $b->date->toDateString(),
            'date_until' => $b->date_until?->toDateString() ?? '',
            'full_day' => (bool) $b->full_day,
            'start_time' => $b->startTime(),
            'end_time' => $b->endTime(),
            'guests' => array_values($b->guests ?? []),
        ];

        $zoneConfig = $zones->mapWithKeys(fn ($z) => [$z->id => [
            'id' => $z->id,
            'name' => $z->name,
            'installation_id' => (string) $z->installation_id,
            'allow_full_day' => $z->allow_full_day,
            'open' => $z->openTime(),
            'close' => $z->closeTime(),
            'min_minutes' => $z->min_minutes,
            'shared' => $z->isShared(),
            'capacity' => $z->capacity,
        ]]);

        $byDay = [];
        foreach ($monthBookings as $b) {
            for ($d = $b->date->copy(); $d->lte($b->lastDate()); $d->addDay()) {
                if ($d->isSameMonth($month)) {
                    $byDay[$d->toDateString()][] = $present($b);
                }
            }
        }

        $gridStart = $month->copy()->startOfMonth()->startOfWeek(\Carbon\CarbonInterface::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(\Carbon\CarbonInterface::SUNDAY);
        $allowedDays = $zone?->allowedWeekdays() ?? [];
        $lastBookable = $zone ? today()->addDays($zone->max_advance_days) : today();

        $old = $errors->any() && old('date') !== null ? [
            'booking_id' => old('booking_id'),
            'common_zone_id' => (int) old('common_zone_id'),
            'structure_id' => (string) old('structure_id', ''),
            'structure_member_id' => (string) old('structure_member_id', ''),
            'date' => old('date'),
            'date_until' => old('date_until', ''),
            'full_day' => (bool) old('full_day'),
            'start_time' => old('start_time', ''),
            'end_time' => old('end_time', ''),
            'people_count' => old('people_count', 1),
            'guests' => array_values(old('guests', [])),
            'notes' => old('notes', ''),
        ] : null;

        $config = [
            'zones' => $zoneConfig,
            'zoneId' => $zone?->id,
            'nodes' => $nodeOptions,
            'byDay' => $byDay,
            'pending' => $pending->map($present)->values(),
            'old' => $old,
            'membersUrl' => $urls['members'],
            'visitorUrl' => $urls['visitor'],
            'storeUrl' => $urls['store'],
            'bookingUrl' => $urls['booking'] ?? '',
        ];
        $nav = fn (array $params = []) => route($urls['index'], $params);
    @endphp

    <div class="mx-auto max-w-7xl space-y-5" x-data="bookingCalendar(@js($config))">
        @if ($errors->any())
            <div class="rounded-lg border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-200">{{ $errors->first() }}</div>
        @endif

        @if ($zones->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/50 px-6 py-16 text-center">
                @if ($admin)
                    <p class="text-lg font-semibold text-white">Crea las zonas comunes que se pueden reservar</p>
                    <p class="mt-1 text-sm text-slate-500">Cada zona lleva su nombre y su configuración: cupo, horario, días y reglas.</p>
                    <a href="{{ $urls['zones'] }}" class="mt-5 inline-flex h-10 items-center rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-500">Crear zonas</a>
                @else
                    <p class="text-lg font-semibold text-white">Aún no hay zonas reservables</p>
                    <p class="mt-1 text-sm text-slate-500">La administración debe crearlas desde su panel.</p>
                @endif
            </div>
        @else
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <form method="GET" class="flex items-center gap-2">
                        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                        <select name="zone" onchange="this.form.submit()" class="h-10 min-w-[14rem] rounded-lg border border-slate-700 bg-slate-950 pl-3 pr-8 text-sm font-semibold text-white">
                            @foreach ($zones as $z)
                                <option value="{{ $z->id }}" @selected($z->id === $zone->id)>{{ $z->name }}</option>
                            @endforeach
                        </select>
                    </form>
                    <div class="inline-flex h-10 items-center rounded-lg bg-slate-900 ring-1 ring-slate-800">
                        <a href="{{ $nav(['zone' => $zone->id, 'month' => $month->copy()->subMonth()->format('Y-m')]) }}" class="flex h-10 w-10 items-center justify-center text-slate-400 hover:text-white" aria-label="Mes anterior">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        </a>
                        <span class="min-w-[9.5rem] text-center text-sm font-semibold capitalize text-white">{{ $month->translatedFormat('F Y') }}</span>
                        <a href="{{ $nav(['zone' => $zone->id, 'month' => $month->copy()->addMonth()->format('Y-m')]) }}" class="flex h-10 w-10 items-center justify-center text-slate-400 hover:text-white" aria-label="Mes siguiente">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                    @unless ($month->isSameMonth(today()))
                        <a href="{{ $nav(['zone' => $zone->id]) }}" class="text-sm text-slate-400 hover:text-white">Hoy</a>
                    @endunless
                </div>
                <div class="flex items-center gap-2">
                    @if ($admin)
                        <a href="{{ $urls['zones'] }}" class="inline-flex h-10 items-center rounded-lg border border-slate-700 px-4 text-sm font-semibold text-slate-200 hover:opacity-80">Zonas</a>
                    @endif
                    <button type="button" @click="openForm({ date: @js(today()->toDateString()) })" class="inline-flex h-10 items-center rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-500">{{ $admin ? '+ Nueva reserva' : '+ Solicitar reserva' }}</button>
                </div>
            </div>

            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <section class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
                    <div class="grid grid-cols-7 border-b border-slate-800 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">
                        @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $wd)
                            <div class="py-2.5">{{ $wd }}</div>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-7">
                        @for ($day = $gridStart->copy(); $day->lte($gridEnd); $day->addDay())
                            @php
                                $iso = $day->toDateString();
                                $inMonth = $day->isSameMonth($month);
                                $past = $day->lt(today());
                                $closed = ! in_array($day->dayOfWeekIso, $allowedDays, true) || $day->gt($lastBookable);
                                $items = $byDay[$iso] ?? [];
                            @endphp
                            <button type="button" @click="openDay(@js($iso), @js($day->translatedFormat('l j \d\e F')), @js(! $past && ! $closed))"
                                    @class([
                                        'group relative flex min-h-[6.5rem] flex-col gap-1 border-b border-r border-slate-800 p-1.5 text-left transition',
                                        'hover:bg-slate-800/60' => $inMonth && ! $past,
                                        'opacity-40' => ! $inMonth || $past,
                                        'bg-slate-950/40' => $inMonth && $closed && ! $past,
                                    ])>
                                <span class="flex items-center justify-between">
                                    <span @class([
                                        'inline-flex h-6 min-w-[1.5rem] items-center justify-center rounded-md px-1 text-xs font-semibold tabular-nums',
                                        'bg-indigo-600 text-white' => $day->isToday(),
                                        'text-slate-300' => ! $day->isToday(),
                                    ])>{{ $day->day }}</span>
                                    @if ($inMonth && $closed && ! $past)
                                        <span class="text-[10px] text-slate-500">No disponible</span>
                                    @elseif ($inMonth && ! $past)
                                        <span class="text-base leading-none text-slate-500 opacity-0 group-hover:opacity-100">+</span>
                                    @endif
                                </span>
                                @foreach (array_slice($items, 0, 3) as $item)
                                    <span @class([
                                        'block truncate rounded px-1.5 py-0.5 text-[11px] font-medium',
                                        'border border-dashed border-amber-500/60 text-amber-300' => $item['status'] === 'pending',
                                        'bg-indigo-600 text-white' => in_array($item['status'], ['confirmed', 'checked_in'], true),
                                        'bg-slate-800 text-slate-400' => $item['status'] === 'completed',
                                    ])>{{ $item['full_day'] ? 'Día' : $item['start_time'] }} · {{ $item['unit'] }}</span>
                                @endforeach
                                @if (count($items) > 3)
                                    <span class="px-1 text-[11px] text-slate-400">+{{ count($items) - 3 }} más</span>
                                @endif
                            </button>
                        @endfor
                    </div>
                    <div class="flex flex-wrap items-center gap-4 border-t border-slate-800 px-4 py-2.5 text-xs text-slate-400">
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-4 rounded bg-indigo-600"></span>Confirmada</span>
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-4 rounded border border-dashed border-amber-500/70"></span>Por aprobar (no ocupa)</span>
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-4 rounded bg-slate-800"></span>Finalizada</span>
                    </div>
                </section>

                <aside class="space-y-4">
                    <section class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
                        <h3 class="text-sm font-semibold text-white">{{ $admin ? 'Solicitudes por aprobar' : 'Pendientes de aprobación' }} <span class="text-slate-500" x-text="'(' + pending.length + ')'"></span></h3>
                        <p class="text-xs text-slate-500">{{ $admin ? 'Solo quedan reservadas cuando las apruebas.' : 'La administración las aprueba, modifica o rechaza.' }}</p>
                        <p x-show="pending.length === 0" class="mt-4 rounded-lg border border-dashed border-slate-700 px-3 py-5 text-center text-sm text-slate-500">No hay solicitudes pendientes.</p>
                        <div class="mt-3 space-y-2">
                            <template x-for="b in pending" :key="b.id">
                                <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="truncate text-sm font-semibold text-white" x-text="b.unit"></p>
                                        <span class="shrink-0 text-[11px] text-slate-500" x-text="b.zone"></span>
                                    </div>
                                    <p class="text-xs text-slate-400" x-text="b.dates + ' · ' + b.hours + ' · ' + b.people + ' pers.'"></p>
                                    <p class="truncate text-xs text-slate-500" x-text="b.requester"></p>
                                    @if ($admin)
                                        <div class="mt-2 flex gap-1.5">
                                            <form method="POST" :action="url(b.id, 'approve')" class="flex-1">
                                                @csrf @method('PATCH')
                                                <button class="h-8 w-full rounded-lg bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-500">Aprobar</button>
                                            </form>
                                            <button type="button" @click="openForm(b)" class="h-8 flex-1 rounded-lg bg-slate-800 text-xs font-semibold text-slate-200 hover:opacity-80">Modificar</button>
                                            <button type="button" @click="openReview(b, 'reject')" class="h-8 flex-1 rounded-lg text-xs font-semibold text-red-400 hover:text-red-300">Rechazar</button>
                                        </div>
                                    @endif
                                </div>
                            </template>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-slate-800 bg-slate-900 p-4 text-sm">
                        <h3 class="font-semibold text-white">{{ $zone->name }}</h3>
                        <dl class="mt-2 space-y-1.5">
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Uso</dt><dd class="text-right text-slate-200">{{ $zone->isShared() ? "Por cupos · {$zone->capacity}" : 'Exclusiva' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Horario</dt><dd class="text-right text-slate-200">{{ $zone->scheduleLabel() }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Días</dt><dd class="text-right text-slate-200">{{ $zone->weekdaysLabel() }}</dd></div>
                            @if ($zone->buffer_minutes)
                                <div class="flex justify-between gap-3"><dt class="text-slate-500">Limpieza</dt><dd class="text-right text-slate-200">{{ $zone->buffer_minutes }} min entre reservas</dd></div>
                            @endif
                            @if ($zone->max_per_unit_month)
                                <div class="flex justify-between gap-3"><dt class="text-slate-500">Límite</dt><dd class="text-right text-slate-200">{{ $zone->max_per_unit_month }} por unidad al mes</dd></div>
                            @endif
                        </dl>
                        @if ($zone->rules)
                            <p class="mt-3 whitespace-pre-line rounded-lg bg-slate-950/60 px-3 py-2 text-xs text-slate-300">{{ $zone->rules }}</p>
                        @endif
                    </section>
                </aside>
            </div>
        @endif

        {{-- Día: reservas del día y acciones --}}
        <template x-teleport="body">
            <div x-show="day.open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-16 sm:items-center sm:pt-4" @keydown.escape.window="day.open = false">
                <div class="absolute inset-0 bg-black/50" @click="day.open = false"></div>
                <div class="relative w-full max-w-lg rounded-2xl border border-slate-700 bg-slate-900 p-5 shadow-2xl">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold capitalize text-white" x-text="day.label"></h3>
                            <p class="text-xs text-slate-500" x-text="zone()?.name"></p>
                        </div>
                        <button type="button" @click="day.open = false" class="text-slate-400 hover:text-white" aria-label="Cerrar">✕</button>
                    </div>
                    <p x-show="(byDay[day.iso] || []).length === 0" class="mt-4 rounded-lg border border-dashed border-slate-700 px-3 py-6 text-center text-sm text-slate-400"
                       x-text="day.bookable ? 'Día libre.' : 'Este día no se puede reservar.'"></p>
                    <div class="mt-4 space-y-2">
                        <template x-for="b in byDay[day.iso] || []" :key="b.id">
                            <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-white" x-text="b.unit"></p>
                                        <p class="truncate text-xs text-slate-400" x-text="b.requester"></p>
                                    </div>
                                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1" :class="b.badge" x-text="b.status_label"></span>
                                </div>
                                <p class="mt-1 text-xs text-slate-300" x-text="b.dates + ' · ' + b.hours + ' · ' + b.people + ' personas' + (b.guests.length ? ' · ' + b.guests.length + ' invitados' : '')"></p>
                                <p x-show="b.notes" class="mt-1 text-xs text-slate-500" x-text="b.notes"></p>
                                @if ($admin)
                                <div class="mt-2 flex flex-wrap justify-end gap-1.5">
                                    <template x-if="b.status === 'pending'">
                                        <div class="flex gap-1.5">
                                            <form method="POST" :action="url(b.id, 'approve')">
                                                @csrf @method('PATCH')
                                                <button class="h-8 rounded-lg bg-indigo-600 px-3 text-xs font-semibold text-white hover:bg-indigo-500">Aprobar</button>
                                            </form>
                                            <button type="button" @click="openForm(b)" class="h-8 rounded-lg bg-slate-800 px-3 text-xs font-semibold text-slate-200 hover:opacity-80">Modificar</button>
                                            <button type="button" @click="openReview(b, 'reject')" class="h-8 rounded-lg px-3 text-xs font-semibold text-red-400 hover:text-red-300">Rechazar</button>
                                        </div>
                                    </template>
                                    <template x-if="b.status === 'confirmed'">
                                        <button type="button" @click="openReview(b, 'cancel')" class="h-8 rounded-lg px-3 text-xs font-semibold text-red-400 hover:text-red-300">Cancelar reserva</button>
                                    </template>
                                </div>
                                @endif
                            </div>
                        </template>
                    </div>
                    <div class="mt-4 flex justify-end" x-show="day.bookable">
                        <button type="button" @click="openForm({ date: day.iso })" class="h-10 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-500">{{ $admin ? '+ Reservar este día' : '+ Solicitar este día' }}</button>
                    </div>
                </div>
            </div>
        </template>

        {{-- Formulario de reserva (nueva o aprobar con cambios) --}}
        <template x-teleport="body">
            <div x-show="form.open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-10" @keydown.escape.window="if (!guestModal.open) form.open = false">
                <div class="absolute inset-0 bg-black/50" @click="form.open = false"></div>
                <form method="POST" :action="form.bookingId ? url(form.bookingId, 'approve') : storeUrl" class="relative w-full max-w-2xl space-y-4 rounded-2xl border border-slate-700 bg-slate-900 p-5 shadow-2xl">
                    @csrf
                    <template x-if="form.bookingId"><input type="hidden" name="_method" value="PUT"></template>
                    <input type="hidden" name="booking_id" :value="form.bookingId">
                    <input type="hidden" name="common_zone_id" :value="form.zoneId">
                    <input type="hidden" name="full_day" :value="form.full_day ? 1 : 0">

                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-white" @if ($admin) x-text="form.bookingId ? 'Modificar y aprobar' : 'Nueva reserva'" @endif>{{ $admin ? '' : 'Solicitar reserva' }}</h3>
                            <p class="text-xs text-slate-500" x-text="zone(form.zoneId)?.name + ' · horario ' + zone(form.zoneId)?.open + ' – ' + zone(form.zoneId)?.close"></p>
                            @unless ($admin)
                                <p class="mt-1 text-xs text-amber-300">Queda por aprobar: la administración la confirma, modifica o rechaza.</p>
                            @endunless
                        </div>
                        <button type="button" @click="form.open = false" class="text-slate-400 hover:text-white" aria-label="Cerrar">✕</button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="{{ $label }}">Unidad que reserva</label>
                            <select name="structure_id" x-model="form.structure_id" @change="loadMembers()" required class="{{ $input }}">
                                <option value="">Seleccione unidad</option>
                                <template x-for="node in nodes[zone(form.zoneId)?.installation_id] ?? []" :key="node.id">
                                    <option :value="String(node.id)" :selected="String(node.id) === form.structure_id" x-text="'\u00A0'.repeat(node.depth * 3) + node.name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="{{ $label }}">Quién solicita</label>
                            <select name="structure_member_id" x-model="form.structure_member_id" class="{{ $input }}">
                                <option value="" x-text="form.structure_id ? (loadingMembers ? 'Cargando…' : @js($admin ? 'Administración' : 'Sin residente (portería)')) : 'Elige primero la unidad'"></option>
                                <template x-for="m in members" :key="m.id">
                                    <option :value="String(m.id)" :selected="String(m.id) === form.structure_member_id" x-text="m.name"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-800 bg-slate-950/40 p-3">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="{{ $label }}">Día</label>
                                <input type="date" name="date" x-model="form.date" :min="today" required class="{{ $input }}">
                            </div>
                            <div>
                                <label class="{{ $label }}">
                                    <span class="inline-flex items-center gap-2">
                                        <input type="checkbox" x-model="form.multi" @change="if (!form.multi) form.date_until = ''" class="h-4 w-4 rounded border-slate-400 text-[var(--ui-accent)] focus:ring-[var(--ui-accent)]">
                                        Varios días · hasta
                                    </span>
                                </label>
                                <input type="date" name="date_until" x-model="form.date_until" :min="form.date" :disabled="!form.multi" class="{{ $input }} disabled:opacity-40">
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap items-end gap-3">
                            <label x-show="zone(form.zoneId)?.allow_full_day" class="inline-flex h-10 cursor-pointer items-center gap-2 text-sm text-slate-200">
                                <input type="checkbox" x-model="form.full_day" class="h-4 w-4 rounded border-slate-400 text-[var(--ui-accent)] focus:ring-[var(--ui-accent)]">
                                Todo el día
                            </label>
                            <div x-show="!form.full_day" class="grid flex-1 grid-cols-2 gap-3">
                                <label class="text-xs text-slate-400">Desde
                                    <input type="time" name="start_time" x-model="form.start_time" :min="zone(form.zoneId)?.open" :max="zone(form.zoneId)?.close" :disabled="form.full_day" class="mt-1 {{ $input }}">
                                </label>
                                <label class="text-xs text-slate-400">Hasta
                                    <input type="time" name="end_time" x-model="form.end_time" :min="zone(form.zoneId)?.open" :max="zone(form.zoneId)?.close" :disabled="form.full_day" class="mt-1 {{ $input }}">
                                </label>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-slate-500" x-text="form.multi && form.date_until ? 'El horario aplica a cada día del rango.' : ''"></p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-[10rem_1fr]">
                        <div>
                            <label class="{{ $label }}">Personas</label>
                            <input type="number" name="people_count" x-model="form.people_count" min="1" :max="zone(form.zoneId)?.capacity" required class="{{ $input }}">
                        </div>
                        <div>
                            <label class="{{ $label }}">Notas (opcional)</label>
                            <input name="notes" x-model="form.notes" maxlength="500" placeholder="Ej. Cumpleaños, llevan sonido" class="{{ $input }}">
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-800 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold text-white">Invitados <span class="font-normal text-slate-500" x-text="'(' + form.guests.length + ')'"></span></p>
                                <p class="text-xs text-slate-500">Opcional. {{ $admin ? 'Al confirmar' : 'Cuando se apruebe' }} se crea una autorización tipo Evento y quedan en visitantes.</p>
                            </div>
                            <button type="button" @click="openGuest()" class="h-9 rounded-lg bg-slate-800 px-3 text-sm font-semibold text-slate-200 hover:opacity-80">+ Agregar invitado</button>
                        </div>
                        <template x-for="(g, i) in form.guests" :key="g.key">
                            <div>
                                <input type="hidden" :name="`guests[${i}][document_type]`" :value="g.document_type">
                                <input type="hidden" :name="`guests[${i}][document_number]`" :value="g.document_number">
                                <input type="hidden" :name="`guests[${i}][first_name]`" :value="g.first_name">
                                <input type="hidden" :name="`guests[${i}][last_name]`" :value="g.last_name">
                                <input type="hidden" :name="`guests[${i}][phone]`" :value="g.phone">
                            </div>
                        </template>
                        <div x-show="form.guests.length" class="mt-3 grid gap-2 sm:grid-cols-2">
                            <template x-for="(g, i) in form.guests" :key="'c' + g.key">
                                <div class="flex items-center gap-2 rounded-lg bg-slate-950/60 px-3 py-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm text-white" x-text="g.first_name + ' ' + g.last_name"></p>
                                        <p class="truncate text-xs text-slate-500" x-text="g.document_type + ' ' + g.document_number"></p>
                                    </div>
                                    <button type="button" @click="openGuest(i)" class="text-xs text-slate-400 hover:text-white">Editar</button>
                                    <button type="button" @click="form.guests.splice(i, 1)" class="text-xs text-red-400 hover:text-red-300">Quitar</button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div x-show="form.bookingId">
                        <label class="{{ $label }}">Mensaje para el residente (opcional)</label>
                        <input name="review_note" maxlength="500" placeholder="Ej. Se movió a las 3 p. m. por otra reserva" class="{{ $input }}">
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="form.open = false" class="h-10 rounded-lg px-4 text-sm text-slate-400 hover:text-white">Cancelar</button>
                        <button class="h-10 rounded-lg bg-indigo-600 px-5 text-sm font-semibold text-white hover:bg-indigo-500" @if ($admin) x-text="form.bookingId ? 'Aprobar con cambios' : 'Confirmar reserva'" @endif>{{ $admin ? '' : 'Enviar solicitud' }}</button>
                    </div>
                </form>
            </div>
        </template>

        {{-- Invitado --}}
        <template x-teleport="body">
            <div x-show="guestModal.open" x-cloak class="fixed inset-0 z-[60] flex items-start justify-center p-4 pt-16 sm:items-center sm:pt-4" @keydown.escape.window="guestModal.open = false">
                <div class="absolute inset-0 bg-black/50" @click="guestModal.open = false"></div>
                <div class="relative w-full max-w-md space-y-4 rounded-2xl border border-slate-700 bg-slate-900 p-5 shadow-2xl">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-white" x-text="guestModal.index === null ? 'Agregar invitado' : 'Editar invitado'"></h3>
                        <button type="button" @click="guestModal.open = false" class="text-slate-400 hover:text-white" aria-label="Cerrar">✕</button>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <label class="text-xs text-slate-400">Tipo
                            <select x-model="guestModal.data.document_type" class="mt-1 {{ $input }}">
                                <template x-for="t in ['CC','CE','TI','PAS','PPT']" :key="t"><option :value="t" :selected="t === guestModal.data.document_type" x-text="t"></option></template>
                            </select>
                        </label>
                        <label class="col-span-2 text-xs text-slate-400">Documento
                            <input x-model="guestModal.data.document_number" x-ref="guestDocument" @change="lookupVisitor()" inputmode="numeric" class="mt-1 {{ $input }}">
                        </label>
                    </div>
                    <p class="-mt-2 text-xs text-emerald-300" x-show="guestModal.found">Ya está en visitantes: datos cargados.</p>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-xs text-slate-400">Nombres<input x-model="guestModal.data.first_name" class="mt-1 {{ $input }}"></label>
                        <label class="text-xs text-slate-400">Apellidos<input x-model="guestModal.data.last_name" class="mt-1 {{ $input }}"></label>
                    </div>
                    <label class="block text-xs text-slate-400">Teléfono (opcional)<input x-model="guestModal.data.phone" inputmode="tel" class="mt-1 {{ $input }}"></label>
                    <p class="text-xs text-red-400" x-show="guestModal.error" x-text="guestModal.error"></p>
                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" @click="guestModal.open = false" class="h-9 rounded-lg px-3 text-sm text-slate-300 hover:text-white">Cancelar</button>
                        <button type="button" x-show="guestModal.index === null" @click="saveGuest(true)" class="h-9 rounded-lg bg-slate-800 px-3 text-sm font-semibold text-slate-200 hover:opacity-80">Agregar y otro</button>
                        <button type="button" @click="saveGuest(false)" class="h-9 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-500" x-text="guestModal.index === null ? 'Agregar' : 'Guardar'"></button>
                    </div>
                </div>
            </div>
        </template>

        {{-- Rechazar / cancelar --}}
        @if ($admin)
        <template x-teleport="body">
            <div x-show="review.open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4" @keydown.escape.window="review.open = false">
                <div class="absolute inset-0 bg-black/50" @click="review.open = false"></div>
                <form method="POST" :action="url(review.id, review.action)" class="relative w-full max-w-md space-y-4 rounded-2xl border border-slate-700 bg-slate-900 p-5 shadow-2xl">
                    @csrf @method('PATCH')
                    <h3 class="text-base font-semibold text-white" x-text="review.action === 'reject' ? 'Rechazar solicitud' : 'Cancelar reserva'"></h3>
                    <p class="text-sm text-slate-400" x-text="review.summary"></p>
                    <label class="block text-xs text-slate-400">Motivo (opcional)
                        <textarea name="review_note" rows="2" maxlength="500" class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white"></textarea>
                    </label>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="review.open = false" class="h-10 rounded-lg px-4 text-sm text-slate-400 hover:text-white">Volver</button>
                        <button class="h-10 rounded-lg bg-red-600 px-4 text-sm font-semibold text-white hover:bg-red-500" x-text="review.action === 'reject' ? 'Rechazar' : 'Cancelar reserva'"></button>
                    </div>
                </form>
            </div>
        </template>
        @endif
    </div>

    @push('scripts')
        <script>
            function bookingCalendar(cfg) {
                let seq = 0;
                const person = (g = {}) => ({
                    key: ++seq,
                    document_type: g.document_type || 'CC',
                    document_number: (g.document_number || '').trim(),
                    first_name: (g.first_name || '').trim(),
                    last_name: (g.last_name || '').trim(),
                    phone: (g.phone || '').trim(),
                });
                const pad = (n) => String(n).padStart(2, '0');
                const local = new Date();
                const addMinutes = (time, minutes) => {
                    const [h, m] = time.split(':').map(Number);
                    const total = Math.min(h * 60 + m + minutes, 23 * 60 + 59);
                    return pad(Math.floor(total / 60)) + ':' + pad(total % 60);
                };
                return {
                    ...cfg,
                    today: `${local.getFullYear()}-${pad(local.getMonth() + 1)}-${pad(local.getDate())}`,
                    members: [],
                    loadingMembers: false,
                    day: { open: false, iso: '', label: '', bookable: false },
                    form: { open: false, guests: [] },
                    review: { open: false, id: null, action: 'reject', summary: '' },
                    guestModal: { open: false, index: null, data: person(), error: '', found: false },
                    init() {
                        if (this.old) {
                            this.openForm({ ...this.old, id: this.old.booking_id || null, zone_id: this.old.common_zone_id });
                        }
                    },
                    zone(id = null) {
                        return this.zones[id ?? this.zoneId] ?? null;
                    },
                    url(id, action) {
                        return this.bookingUrl.replace('__ID__', id) + '/' + action;
                    },
                    openDay(iso, label, bookable) {
                        this.day = { open: true, iso, label, bookable };
                    },
                    openForm(b = {}) {
                        const zoneId = b.zone_id || this.zoneId;
                        const z = this.zone(zoneId);
                        const start = b.start_time || z.open;
                        this.day.open = false;
                        this.form = {
                            open: true,
                            bookingId: b.id || null,
                            zoneId,
                            structure_id: b.structure_id || '',
                            structure_member_id: b.structure_member_id || '',
                            date: b.date || this.today,
                            date_until: b.date_until || '',
                            multi: Boolean(b.date_until),
                            full_day: Boolean(b.full_day),
                            start_time: start,
                            end_time: b.end_time || addMinutes(start, Math.max(z.min_minutes, 60)),
                            people_count: b.people_count || b.people || 1,
                            notes: b.notes || '',
                            guests: (b.guests || []).map(person),
                        };
                        this.loadMembers(this.form.structure_member_id);
                    },
                    openReview(b, action) {
                        this.day.open = false;
                        this.review = { open: true, id: b.id, action, summary: `${b.unit} · ${b.zone || this.zone()?.name} · ${b.dates} · ${b.hours}` };
                    },
                    async loadMembers(keep = '') {
                        this.form.structure_member_id = keep;
                        this.members = [];
                        if (!this.form.structure_id) return;
                        this.loadingMembers = true;
                        try {
                            const res = await fetch(`${this.membersUrl}?structure_id=${this.form.structure_id}`, { headers: { Accept: 'application/json' } });
                            this.members = (await res.json()).members || [];
                        } finally {
                            this.loadingMembers = false;
                        }
                    },
                    openGuest(index = null) {
                        this.guestModal = { open: true, index, data: index === null ? person() : { ...this.form.guests[index] }, error: '', found: false };
                        this.$nextTick(() => this.$refs.guestDocument?.focus());
                    },
                    async lookupVisitor() {
                        const doc = this.guestModal.data.document_number.replace(/[\s.]/g, '');
                        this.guestModal.data.document_number = doc;
                        this.guestModal.found = false;
                        if (doc.length < 5) return;
                        const res = await fetch(`${this.visitorUrl}?document=${encodeURIComponent(doc)}`, { headers: { Accept: 'application/json' } });
                        const v = (await res.json()).visitor;
                        if (!v || this.guestModal.data.document_number !== doc) return;
                        Object.assign(this.guestModal.data, {
                            document_type: v.document_type || this.guestModal.data.document_type,
                            first_name: v.first_name || '',
                            last_name: v.last_name || '',
                            phone: this.guestModal.data.phone || v.phone || '',
                        });
                        this.guestModal.found = true;
                    },
                    saveGuest(another) {
                        const g = person(this.guestModal.data);
                        if (!g.document_number || !g.first_name || !g.last_name) {
                            this.guestModal.error = 'Documento, nombres y apellidos son obligatorios.';
                            return;
                        }
                        const dup = this.form.guests.findIndex((x) => x.document_number === g.document_number);
                        if (dup !== -1 && dup !== this.guestModal.index) {
                            this.guestModal.error = 'Ese invitado ya está en la lista.';
                            return;
                        }
                        if (this.guestModal.index === null) this.form.guests.push(g);
                        else this.form.guests.splice(this.guestModal.index, 1, g);
                        if (another) this.openGuest();
                        else this.guestModal.open = false;
                    },
                };
            }
        </script>
    @endpush
