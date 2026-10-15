<x-access-layout title="Minuta">
    @php
        $isToday = $date->isToday();
        $installation = $door?->installation;
        $client = $installation?->client;
        $subjects = \App\Models\GuardLog::SUBJECTS;
        $tone = [
            'novedad' => 'bg-[#fef3c7] text-[#92400e]',
            'incidente' => 'bg-[#fee2e2] text-[#991b1b]',
            'turno' => 'bg-[#dbeafe] text-[#1e40af]',
            'revista' => 'bg-[#ccfbf1] text-[#115e59]',
            'correccion' => 'bg-[#ede9fe] text-[#5b21b6]',
        ];
        $reopenRevista = old('type') === 'revista' && $errors->any();
        $correcting = old('type') === 'correccion' ? $entries->firstWhere('id', (int) old('corrects_guard_log_id')) : null;
        $nav = fn ($d) => route('access.guard_logs.index', array_filter(['door' => $door?->id, 'date' => $d?->toDateString()]));
        $rule = 'border-b border-[#c7dbef]';
    @endphp

    <div class="mx-auto max-w-6xl space-y-4" x-data="minutaBook(@js([
        'revista' => $reopenRevista,
        'type' => old('type') && old('type') !== 'revista' ? old('type') : 'general',
        'correcting' => $correcting ? ['id' => $correcting->id, 'time' => $correcting->log_time->format('H:i')] : null,
    ]))">
        @if ($door === null)
            <div class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/50 px-6 py-16 text-center">
                <p class="text-lg font-semibold text-white">No hay una puerta activa</p>
                <p class="mt-1 text-sm text-slate-500">El libro de minuta se lleva por puerta. Pide a la administración que active una.</p>
            </div>
        @else
            <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($doors->count() > 1)
                        <form method="GET">
                            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                            <select name="door" onchange="this.form.submit()" class="h-10 min-w-[12rem] rounded-lg border border-slate-700 bg-slate-950 pl-3 pr-8 text-sm font-semibold text-white">
                                @foreach ($doors as $d)
                                    <option value="{{ $d->id }}" @selected($d->id === $door->id)>{{ $d->name }}</option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                    <div class="inline-flex h-10 items-center rounded-lg bg-slate-900 ring-1 ring-slate-800">
                        <a href="{{ $nav($date->copy()->subDay()) }}" class="flex h-10 w-10 items-center justify-center text-slate-400 hover:text-white" aria-label="Día anterior">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        </a>
                        <form method="GET" class="flex items-center">
                            <input type="hidden" name="door" value="{{ $door->id }}">
                            <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" onchange="this.form.submit()" class="h-8 w-[9.5rem] border-0 bg-transparent text-center text-sm font-semibold text-white focus:ring-0" aria-label="Día del libro">
                        </form>
                        @if ($isToday)
                            <span class="flex h-10 w-10 items-center justify-center text-slate-700">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </span>
                        @else
                            <a href="{{ $nav($date->copy()->addDay()) }}" class="flex h-10 w-10 items-center justify-center text-slate-400 hover:text-white" aria-label="Día siguiente">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        @endif
                    </div>
                    @unless ($isToday)
                        <a href="{{ $nav(null) }}" class="inline-flex h-10 items-center rounded-lg px-3 text-sm font-semibold text-indigo-300 hover:text-indigo-200">Ir a hoy</a>
                    @endunless
                </div>
                <button type="button" onclick="window.print()" class="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-700 bg-slate-900 px-3 text-sm font-semibold text-slate-300 hover:text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/></svg>
                    Imprimir
                </button>
            </div>

            @if ($errors->any() && ! $reopenRevista)
                <div class="rounded-lg border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-200 print:hidden">{{ $errors->first() }}</div>
            @endif

            <article id="minuta-book" class="overflow-hidden rounded-2xl border border-[#d6cfbf] bg-[#fdfbf4] text-[#1f2937] shadow-xl">
                <header class="flex flex-wrap items-start justify-between gap-4 border-b-2 border-[#1f2937] px-6 py-5">
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold uppercase tracking-[0.25em] text-[#0f766e]">Minuta de servicio</p>
                        <h3 class="mt-1 truncate text-2xl font-bold text-[#111827]">{{ $client?->name ?? 'Cliente' }}</h3>
                        <p class="mt-0.5 text-sm text-[#4b5563]">
                            {{ $installation?->name ?? 'Instalación' }} · <span class="font-semibold text-[#111827]">{{ $door->name }}</span>
                        </p>
                        <p class="mt-2 text-xs text-[#6b7280]">
                            {{ ucfirst($date->translatedFormat('l j \d\e F \d\e Y')) }}
                            · Vigilante: {{ auth()->user()->name }}
                        </p>
                    </div>
                    @if ($isToday)
                        <div class="flex flex-wrap gap-2 print:hidden">
                            <button type="button" @click="focusLine()" class="inline-flex h-10 items-center gap-1.5 rounded-lg bg-[#0f766e] px-4 text-sm font-semibold text-[#ffffff] hover:bg-[#115e59]">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                                Nueva anotación
                            </button>
                            <button type="button" @click="revista = true" class="inline-flex h-10 items-center gap-1.5 rounded-lg border border-[#0f766e] px-4 text-sm font-semibold text-[#0f766e] hover:bg-[#ecfdf5]">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                Revista del supervisor
                            </button>
                        </div>
                    @endif
                </header>

                <div class="overflow-x-auto">
                    <div class="min-w-[44rem]">
                        <div class="grid grid-cols-[6.5rem_4.5rem_9.5rem_1fr] border-b-2 border-[#1f2937] bg-[#f3efe3] text-[11px] font-bold uppercase tracking-wider text-[#374151]">
                            <div class="px-3 py-2">Fecha</div>
                            <div class="px-3 py-2">Hora</div>
                            <div class="px-3 py-2">Asunto</div>
                            <div class="border-l-2 border-[#e8a3a3] px-4 py-2">Anotación</div>
                        </div>

                        @foreach ($entries as $entry)
                            <div class="group grid grid-cols-[6.5rem_4.5rem_9.5rem_1fr] {{ $rule }} text-sm">
                                <div class="px-3 py-2.5 tabular-nums text-[#4b5563]">{{ $entry->log_time->format('d/m/Y') }}</div>
                                <div class="px-3 py-2.5 font-semibold tabular-nums">{{ $entry->log_time->format('H:i') }}</div>
                                <div class="px-3 py-2">
                                    <span class="inline-flex rounded px-1.5 py-0.5 text-xs font-semibold {{ $entry->is_panic ? 'bg-[#fee2e2] text-[#991b1b]' : ($tone[$entry->type] ?? 'bg-[#f1f5f9] text-[#334155]') }}">{{ $entry->subjectLabel() }}</span>
                                </div>
                                <div class="border-l-2 border-[#e8a3a3] px-4 py-2.5">
                                    @if ($entry->corrects)
                                        <p class="mb-0.5 text-xs font-semibold text-[#5b21b6]">Corrige la anotación de las {{ $entry->corrects->log_time->format('H:i') }}</p>
                                    @endif
                                    <p class="whitespace-pre-line leading-relaxed">{{ $entry->description }}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-[#6b7280]">
                                        <span>— {{ $entry->user?->name }}</span>
                                        @if ($entry->supervisor_name)
                                            <span class="font-semibold text-[#115e59]">Firma supervisor: {{ $entry->supervisor_name }}</span>
                                        @endif
                                        @foreach ($entry->corrections as $fix)
                                            <span class="font-semibold text-[#5b21b6]">Corregida a las {{ $fix->log_time->format('H:i') }}</span>
                                        @endforeach
                                        @if ($isToday && $entry->type !== 'correccion' && ! $entry->is_panic)
                                            <button type="button" @click="correct({{ $entry->id }}, '{{ $entry->log_time->format('H:i') }}')" class="font-semibold text-[#9ca3af] hover:text-[#0f766e] group-hover:text-[#0f766e] print:hidden">Corregir</button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        @if ($isToday)
                            <form method="POST" action="{{ route('access.guard_logs.store') }}" id="minuta-line" class="grid grid-cols-[6.5rem_4.5rem_9.5rem_1fr] {{ $rule }} bg-[#fffef0] text-sm print:hidden">
                                @csrf
                                <input type="hidden" name="location_id" value="{{ $door->id }}">
                                <input type="hidden" name="latitude" :value="lat">
                                <input type="hidden" name="longitude" :value="lng">
                                <template x-if="correcting">
                                    <input type="hidden" name="corrects_guard_log_id" :value="correcting.id">
                                </template>
                                <div class="px-3 py-3 tabular-nums text-[#4b5563]">{{ now()->format('d/m/Y') }}</div>
                                <div class="px-3 py-3 font-semibold tabular-nums" x-text="clock">{{ now()->format('H:i') }}</div>
                                <div class="px-2 py-2">
                                    <template x-if="correcting">
                                        <div>
                                            <input type="hidden" name="type" value="correccion">
                                            <span class="inline-flex rounded bg-[#ede9fe] px-1.5 py-0.5 text-xs font-semibold text-[#5b21b6]">Corrección</span>
                                        </div>
                                    </template>
                                    <select x-show="!correcting" name="type" x-model="type" :disabled="!!correcting" class="h-9 w-full rounded-md border border-[#cbd5e1] bg-[#ffffff] px-2 text-sm text-[#111827] focus:border-[#0f766e] focus:ring-[#0f766e]">
                                        @foreach ($subjects as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="border-l-2 border-[#e8a3a3] px-3 py-2">
                                    <div x-show="correcting" x-cloak class="mb-1.5 flex items-center justify-between gap-2 text-xs font-semibold text-[#5b21b6]">
                                        <span x-text="correcting ? 'Corrigiendo la anotación de las ' + correcting.time : ''"></span>
                                        <button type="button" @click="correcting = null" class="text-[#6b7280] hover:text-[#111827]">Cancelar</button>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <textarea id="minuta-text" name="description" rows="2" maxlength="2000" required placeholder="Escribe la anotación…" class="min-h-[2.5rem] flex-1 resize-y rounded-md border border-[#cbd5e1] bg-[#ffffff] px-3 py-2 text-sm text-[#111827] placeholder:text-[#9ca3af] focus:border-[#0f766e] focus:ring-[#0f766e]">{{ old('type') !== 'revista' ? old('description') : '' }}</textarea>
                                        <button type="submit" class="h-10 shrink-0 rounded-lg bg-[#0f766e] px-4 text-sm font-semibold text-[#ffffff] hover:bg-[#115e59]">Registrar</button>
                                    </div>
                                    @if ($observatoryKinds !== [])
                                        <div x-show="type === 'novedad' && !correcting" x-cloak class="mt-2 flex flex-wrap items-center gap-3 text-xs text-[#374151]">
                                            <label class="inline-flex items-center gap-1.5">
                                                <input type="checkbox" name="to_observatory" value="1" x-model="observatory" class="rounded border-[#94a3b8] text-[#0f766e] focus:ring-[#0f766e]">
                                                También al Observatorio
                                            </label>
                                            <template x-if="observatory">
                                                <span class="inline-flex flex-wrap items-center gap-3">
                                                    <select name="observatory_kind" required class="h-8 rounded-md border border-[#cbd5e1] bg-[#ffffff] px-2 text-xs text-[#111827]">
                                                        <option value="">Tipo…</option>
                                                        @foreach ($observatoryKinds as $value => $label)
                                                            <option value="{{ $value }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    <label class="inline-flex items-center gap-1.5">
                                                        <input type="checkbox" name="observatory_anonymous" value="1" class="rounded border-[#94a3b8] text-[#0f766e] focus:ring-[#0f766e]">
                                                        Anónimo
                                                    </label>
                                                </span>
                                            </template>
                                        </div>
                                    @endif
                                </div>
                            </form>
                        @endif

                        @if ($entries->isEmpty() && ! $isToday)
                            <div class="grid grid-cols-[6.5rem_4.5rem_9.5rem_1fr] {{ $rule }} text-sm">
                                <div class="col-span-3"></div>
                                <div class="border-l-2 border-[#e8a3a3] px-4 py-2.5 italic text-[#9ca3af]">Sin anotaciones este día.</div>
                            </div>
                        @endif

                        @for ($i = 0; $i < max(3, 8 - $entries->count()); $i++)
                            <div class="grid h-10 grid-cols-[6.5rem_4.5rem_9.5rem_1fr] {{ $rule }}" aria-hidden="true">
                                <div class="col-span-3"></div>
                                <div class="border-l-2 border-[#e8a3a3]"></div>
                            </div>
                        @endfor
                    </div>
                </div>

                <footer class="flex flex-wrap justify-between gap-2 px-6 py-3 text-xs text-[#6b7280]">
                    <span>{{ $entries->count() }} {{ $entries->count() === 1 ? 'anotación' : 'anotaciones' }}</span>
                    <span>Las anotaciones no se borran: los errores se corrigen con una línea nueva.</span>
                </footer>
            </article>

            @if ($isToday)
                <template x-teleport="body">
                    <div x-show="revista" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="revista = false">
                        <div class="absolute inset-0 bg-black/50" @click="revista = false"></div>
                        <form method="POST" action="{{ route('access.guard_logs.store') }}" class="relative w-full max-w-md space-y-4 rounded-2xl border border-slate-700 bg-slate-900 p-5 shadow-2xl">
                            @csrf
                            <input type="hidden" name="type" value="revista">
                            <input type="hidden" name="location_id" value="{{ $door->id }}">
                            <input type="hidden" name="latitude" :value="lat">
                            <input type="hidden" name="longitude" :value="lng">
                            <div>
                                <h3 class="text-base font-semibold text-white">Revista del supervisor</h3>
                                <p class="mt-0.5 text-sm text-slate-500">{{ $door->name }} · queda en el libro con la hora del sistema.</p>
                            </div>
                            @if ($reopenRevista)
                                <div class="rounded-lg border border-red-800 bg-red-950/40 px-3 py-2 text-sm text-red-200">{{ $errors->first() }}</div>
                            @endif
                            <div>
                                <label for="revista-code" class="mb-1 block text-xs text-slate-400">Código del supervisor</label>
                                <input id="revista-code" type="password" name="supervision_code" inputmode="numeric" autocomplete="off" required class="h-10 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 text-sm tracking-widest text-white">
                                <p class="mt-1 text-xs text-slate-500">Lo digita el supervisor: su código de 6 dígitos o uno del catálogo de portería.</p>
                            </div>
                            <div>
                                <label for="revista-text" class="mb-1 block text-xs text-slate-400">Anotación</label>
                                <textarea id="revista-text" name="description" rows="3" maxlength="2000" required class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">{{ $reopenRevista ? old('description') : 'Revista de puesto. Sin novedad.' }}</textarea>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="revista = false" class="h-10 rounded-lg px-4 text-sm font-semibold text-slate-300 hover:text-white">Cancelar</button>
                                <button type="submit" class="h-10 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-500">Firmar revista</button>
                            </div>
                        </form>
                    </div>
                </template>
            @endif
        @endif
    </div>

    @push('scripts')
        <style>
            @media print {
                html, body, main, .h-screen { height: auto !important; overflow: visible !important; }
                body * { visibility: hidden; }
                #minuta-book, #minuta-book * { visibility: visible; }
                #minuta-book { position: absolute; inset: 0 auto auto 0; width: 100%; box-shadow: none; border: 0; }
            }
        </style>
        <script>
            function minutaBook(cfg) {
                return {
                    revista: Boolean(cfg.revista),
                    type: cfg.type || 'general',
                    correcting: cfg.correcting,
                    observatory: false,
                    lat: '',
                    lng: '',
                    clock: '',
                    init() {
                        const tick = () => {
                            const d = new Date();
                            this.clock = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
                        };
                        tick();
                        setInterval(tick, 15000);
                        if (navigator.geolocation) {
                            navigator.geolocation.getCurrentPosition(
                                (pos) => {
                                    this.lat = pos.coords.latitude.toFixed(7);
                                    this.lng = pos.coords.longitude.toFixed(7);
                                },
                                () => {},
                                { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 },
                            );
                        }
                        if (this.revista) {
                            this.$nextTick(() => document.getElementById('revista-code')?.focus());
                        }
                        this.$watch('revista', (open) => open && this.$nextTick(() => document.getElementById('revista-code')?.focus()));
                    },
                    focusLine() {
                        const text = document.getElementById('minuta-text');
                        text?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        text?.focus();
                    },
                    correct(id, time) {
                        this.correcting = { id, time };
                        this.focusLine();
                    },
                };
            }
        </script>
    @endpush
</x-access-layout>
