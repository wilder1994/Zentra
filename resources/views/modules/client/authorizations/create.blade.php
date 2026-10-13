<x-client-layout title="Nueva autorización">
    @php
        $firstInstallation = $installations->count() === 1 ? (string) $installations->first()->id : '';
        $initialType = old('type', $preset['type'] ?? 'visit');
        $config = [
            'type' => $initialType,
            'mode' => old('destination_mode', 'unit'),
            'installationId' => (string) old('installation_id', $preset['installation_id'] ?? $firstInstallation),
            'structureId' => (string) old('structure_id', $preset['structure_id'] ?? ''),
            'destinationText' => old('destination_text', ''),
            'memberId' => (string) old('member_id', ''),
            'authorizedText' => old('authorized_text', ''),
            'notes' => old('notes', ''),
            'guests' => array_values(old('guests', [])),
            'nodes' => $nodeOptions,
            'multiDefaults' => $multiDefaults,
            'membersUrl' => route('client.authorizations.members'),
            'visitorUrl' => route('client.authorizations.visitor'),
        ];
        $validity = [
            'from' => old('valid_from', now()->toDateString()),
            'to' => old('valid_until', old('valid_from', now()->toDateString())),
            'timeFrom' => old('entry_time_from', ''),
            'timeUntil' => old('entry_time_until', ''),
            'multi' => (bool) old('multi_entry', $multiDefaults[$initialType] ?? false),
            'multiTouched' => old('multi_entry') !== null,
        ];
        $input = 'h-10 w-full rounded-lg bg-slate-950 border border-slate-700 px-3 text-sm text-white';
        $label = 'mb-1 block text-xs text-slate-400';
        $notesChips = ['Ingresa con vehículo', 'Trae herramientas', 'Usar parqueadero de visitantes', 'Avisar a la unidad al llegar', 'Solo hasta la portería'];
    @endphp

    <form action="{{ route('client.authorizations.store') }}" method="POST" x-data="authorizationForm(@js($config))" class="mx-auto max-w-4xl space-y-5">
        @csrf
        @if ($errors->any())
            <div class="rounded-lg border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-200">
                <p class="font-semibold">No se pudo guardar. Revisa:</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                    @foreach (collect($errors->all())->unique() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <h3 class="text-sm font-semibold text-white">1. Tipo de autorización</h3>
            <div class="mt-3 max-w-xs">
                <select name="type" x-model="type" @change="$dispatch('auth-type', multiDefaults[type])" class="{{ $input }}">
                    @foreach ($types as $value => $typeLabel)
                        <option value="{{ $value }}">{{ $typeLabel }}</option>
                    @endforeach
                </select>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-white">2. Destino y quién autoriza</h3>
                <div class="inline-flex h-9 items-center gap-1 rounded-lg bg-slate-950 p-1 ring-1 ring-slate-800">
                    <input type="hidden" name="destination_mode" :value="mode">
                    <button type="button" @click="mode = 'unit'" :class="mode === 'unit' ? 'bg-indigo-600 text-white' : 'text-slate-400'" class="h-7 rounded-md px-3 text-xs font-semibold">Unidad</button>
                    <button type="button" @click="mode = 'zone'" :class="mode === 'zone' ? 'bg-indigo-600 text-white' : 'text-slate-400'" class="h-7 rounded-md px-3 text-xs font-semibold">Zona común</button>
                </div>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div @class(['hidden' => $installations->count() === 1])>
                    <label class="{{ $label }}">Instalación</label>
                    <select name="installation_id" x-model="installationId" @change="structureId = ''; members = []" class="{{ $input }}">
                        <option value="">Seleccione instalación</option>
                        @foreach ($installations as $installation)
                            <option value="{{ $installation->id }}">{{ $installation->name }}</option>
                        @endforeach
                    </select>
                </div>

                <template x-if="mode === 'unit'">
                    <div>
                        <label class="{{ $label }}">Unidad</label>
                        <select name="structure_id" x-model="structureId" @change="loadMembers()" required class="{{ $input }}">
                            <option value="">Seleccione unidad</option>
                            <template x-for="node in nodes[installationId] ?? []" :key="node.id">
                                <option :value="String(node.id)" :selected="String(node.id) === structureId" x-text="'\u00A0'.repeat(node.depth * 3) + node.name"></option>
                            </template>
                        </select>
                    </div>
                </template>

                <template x-if="mode === 'zone'">
                    <div>
                        <label class="{{ $label }}">Zona común</label>
                        <input type="text" name="destination_text" x-model="destinationText" required maxlength="150" placeholder="Ej. Salón social" class="{{ $input }}">
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($zones as $zone)
                                <button type="button" @click="destinationText = @js($zone)" class="rounded-full bg-slate-800 px-2.5 py-1 text-xs text-slate-300 hover:opacity-80">{{ $zone }}</button>
                            @endforeach
                        </div>
                    </div>
                </template>

                <div class="grid gap-4 sm:col-span-2 sm:grid-cols-2">
                    <div x-show="mode === 'unit'">
                        <label class="{{ $label }}">Autoriza (residente)</label>
                        <select name="member_id" x-model="memberId" :disabled="mode !== 'unit'" class="{{ $input }}">
                            <option value="" x-text="structureId ? (loadingMembers ? 'Cargando…' : 'Otra persona / administración') : 'Elige primero la unidad'"></option>
                            <template x-for="m in members" :key="m.id">
                                <option :value="String(m.id)" :selected="String(m.id) === memberId" x-text="m.name"></option>
                            </template>
                        </select>
                    </div>
                    <div x-show="mode === 'zone' || !memberId">
                        <label class="{{ $label }}">Nombre de quien autoriza</label>
                        <input type="text" name="authorized_text" x-model="authorizedText" maxlength="150" placeholder="Administración" class="{{ $input }}">
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5"
                 x-data="authorizationValidityPicker(@js($validity))" x-on:auth-type.window="typeChanged($event.detail)">
            <h3 class="text-sm font-semibold text-white">3. Vigencia</h3>
            <input type="hidden" name="valid_from" :value="from">
            <input type="hidden" name="valid_until" :value="to">
            <input type="hidden" name="entry_time_from" :value="timeFrom">
            <input type="hidden" name="entry_time_until" :value="timeUntil">
            <input type="hidden" name="multi_entry" :value="multi ? 1 : 0">

            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="{{ $label }}">Fecha, horario y entradas</label>
                    <button type="button" @click="show()"
                            class="flex h-10 w-full items-center gap-2 rounded-lg border border-slate-700 bg-slate-950 px-3 text-left text-sm text-white hover:border-slate-500">
                        <svg class="h-4 w-4 shrink-0 text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="16" rx="2"></rect>
                            <path d="M3 10h18M8 3v4M16 3v4"></path>
                        </svg>
                        <span class="truncate" x-text="dayLabel + ' · ' + hoursLabel + ' · ' + (multi ? 'Entrada múltiple' : 'Una entrada')"></span>
                    </button>
                </div>
                <div>
                    <label class="{{ $label }}">Placa del vehículo (opcional)</label>
                    <input type="text" name="vehicle_plate" value="{{ old('vehicle_plate') }}" maxlength="15" placeholder="ABC123" class="{{ $input }} uppercase">
                </div>
            </div>

            <template x-teleport="body">
                <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 pt-16 sm:items-center sm:pt-4" @keydown.escape.window="close()">
                    <div class="absolute inset-0 bg-black/50" @click="close()"></div>
                    <div class="relative w-full rounded-2xl border border-slate-700 bg-slate-900 p-5 shadow-2xl" :class="draft.range ? 'max-w-[40rem]' : 'max-w-[22rem]'">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-semibold text-white">Vigencia de la autorización</h3>
                            <button type="button" @click="close()" class="text-slate-400 hover:text-white" aria-label="Cerrar">✕</button>
                        </div>

                        <label class="mt-3 inline-flex cursor-pointer items-center gap-2 text-sm text-slate-200">
                            <input type="checkbox" class="h-4 w-4 rounded border-slate-400 text-[var(--ui-accent)] focus:ring-[var(--ui-accent)]" :checked="draft.range" @change="toggleRange()">
                            Varios días (rango)
                            <span class="text-xs text-slate-500" x-show="draft.range" x-text="picking ? 'Elige el día final' : 'Elige desde y hasta'"></span>
                        </label>

                        <div class="mt-3 flex items-center justify-between">
                            <button type="button" @click="shift(-1)" class="rounded-lg p-1.5 text-slate-400 hover:text-white" aria-label="Mes anterior">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <button type="button" @click="shift(1)" class="rounded-lg p-1.5 text-slate-400 hover:text-white" aria-label="Mes siguiente">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                        <div class="-mt-7 grid gap-5" :class="draft.range ? 'sm:grid-cols-2' : ''">
                            <template x-for="(m, index) in months" :key="m.year + '-' + m.month">
                                <div :class="index > 0 ? 'hidden sm:block' : ''">
                                    <p class="text-center text-sm font-semibold text-white" x-text="title(m)"></p>
                                    <div class="mt-3 grid grid-cols-7 gap-0.5 text-center text-[11px] text-slate-500">
                                        <template x-for="(w, wi) in weekdays" :key="wi"><span x-text="w"></span></template>
                                    </div>
                                    <div class="mt-1 grid grid-cols-7 gap-0.5 text-center text-sm">
                                        <template x-for="cell in cells(m)" :key="cell.key">
                                            <button type="button" class="h-8 rounded-md tabular-nums" :disabled="!cell.day"
                                                    :class="dayClass(cell.iso)" @click="pick(cell.iso)" x-text="cell.day || ''"></button>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="mt-4 space-y-3 border-t border-slate-800 pt-4">
                            <div>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-medium text-white">Horario de ingreso</span>
                                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-300">
                                        <input type="checkbox" x-model="draft.allDay" class="h-4 w-4 rounded border-slate-400 text-[var(--ui-accent)] focus:ring-[var(--ui-accent)]">
                                        Todo el día
                                    </label>
                                </div>
                                <div x-show="!draft.allDay" class="mt-2 grid grid-cols-2 gap-3">
                                    <label class="text-xs text-slate-400">Desde
                                        <input type="time" x-model="draft.timeFrom" class="mt-1 {{ $input }}">
                                    </label>
                                    <label class="text-xs text-slate-400">Hasta
                                        <input type="time" x-model="draft.timeUntil" class="mt-1 {{ $input }}">
                                    </label>
                                </div>
                                <p class="mt-1 text-xs text-slate-500" x-show="!draft.allDay" x-text="draft.range ? 'Aplica a cada día del rango.' : 'Aplica para ese día.'"></p>
                            </div>
                            <label class="flex cursor-pointer items-start gap-3">
                                <input type="checkbox" x-model="draft.multi" class="mt-0.5 h-4 w-4 rounded border-slate-400 text-[var(--ui-accent)] focus:ring-[var(--ui-accent)]">
                                <span>
                                    <span class="block text-sm font-medium text-white">Entrada múltiple</span>
                                    <span class="block text-xs text-slate-500">Puede entrar y salir varias veces durante la vigencia. Si no, cada persona entra una sola vez.</span>
                                </span>
                            </label>
                        </div>

                        <p class="mt-3 text-xs text-red-400" x-show="error" x-text="error"></p>
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" @click="close()" class="h-9 rounded-lg px-3 text-sm text-slate-300 hover:text-white">Cancelar</button>
                            <button type="button" @click="apply()" class="h-9 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-500">Aplicar</button>
                        </div>
                    </div>
                </div>
            </template>
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-white">4. Personas autorizadas <span class="text-slate-500" x-text="'(' + guests.length + ')'"></span></h3>
                    <p class="text-xs text-slate-500">Cada persona queda en la ficha de visitantes. En portería solo se toma la foto.</p>
                </div>
                <button type="button" @click="openGuest()" class="inline-flex h-9 items-center rounded-lg bg-indigo-600 px-3 text-sm font-semibold text-white hover:bg-indigo-500">+ Agregar persona</button>
            </div>

            <template x-for="(g, i) in guests" :key="g.key">
                <div>
                    <input type="hidden" :name="`guests[${i}][document_type]`" :value="g.document_type">
                    <input type="hidden" :name="`guests[${i}][document_number]`" :value="g.document_number">
                    <input type="hidden" :name="`guests[${i}][first_name]`" :value="g.first_name">
                    <input type="hidden" :name="`guests[${i}][last_name]`" :value="g.last_name">
                    <input type="hidden" :name="`guests[${i}][phone]`" :value="g.phone">
                </div>
            </template>

            <div x-show="guests.length === 0" class="mt-4 rounded-xl border border-dashed border-slate-700 px-4 py-6 text-center text-sm text-slate-400">
                Aún no hay personas. Agrega una por una a quienes van a ingresar.
            </div>
            <div x-show="guests.length" class="mt-4 grid gap-2 sm:grid-cols-2">
                <template x-for="(g, i) in guests" :key="'card-' + g.key">
                    <div class="flex items-center gap-3 rounded-xl border border-slate-800 bg-slate-950/60 p-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-sm font-bold text-white" x-text="initials(g)"></div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-white" x-text="g.first_name + ' ' + g.last_name"></p>
                            <p class="truncate text-xs text-slate-500" x-text="g.document_type + ' ' + g.document_number + (g.phone ? ' · ' + g.phone : '')"></p>
                        </div>
                        <button type="button" @click="openGuest(i)" class="rounded-md px-2 py-1 text-xs text-slate-400 hover:text-white">Editar</button>
                        <button type="button" @click="guests.splice(i, 1)" class="rounded-md px-2 py-1 text-xs text-red-400 hover:text-red-300">Quitar</button>
                    </div>
                </template>
            </div>

            <template x-teleport="body">
                <div x-show="guestModal.open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-16 sm:items-center sm:pt-4" @keydown.escape.window="guestModal.open = false">
                    <div class="absolute inset-0 bg-black/50" @click="guestModal.open = false"></div>
                    <div class="relative w-full max-w-md space-y-4 rounded-2xl border border-slate-700 bg-slate-900 p-5 shadow-2xl">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-semibold text-white" x-text="guestModal.index === null ? 'Agregar persona' : 'Editar persona'"></h3>
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
                            <label class="text-xs text-slate-400">Nombres
                                <input x-model="guestModal.data.first_name" class="mt-1 {{ $input }}">
                            </label>
                            <label class="text-xs text-slate-400">Apellidos
                                <input x-model="guestModal.data.last_name" class="mt-1 {{ $input }}">
                            </label>
                        </div>
                        <label class="block text-xs text-slate-400">Teléfono (opcional)
                            <input x-model="guestModal.data.phone" inputmode="tel" class="mt-1 {{ $input }}">
                        </label>
                        <p class="text-xs text-red-400" x-show="guestModal.error" x-text="guestModal.error"></p>
                        <div class="flex flex-wrap justify-end gap-2">
                            <button type="button" @click="guestModal.open = false" class="h-9 rounded-lg px-3 text-sm text-slate-300 hover:text-white">Cancelar</button>
                            <button type="button" x-show="guestModal.index === null" @click="saveGuest(true)" class="h-9 rounded-lg bg-slate-800 px-3 text-sm font-semibold text-slate-200 hover:opacity-80">Agregar y otra</button>
                            <button type="button" @click="saveGuest(false)" class="h-9 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-500"
                                    x-text="guestModal.index === null ? 'Agregar' : 'Guardar'"></button>
                        </div>
                    </div>
                </div>
            </template>
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-white">5. Indicaciones para portería <span class="font-normal text-slate-500">(opcional)</span></h3>
                <span class="text-xs text-slate-500" x-text="notes.length + '/500'"></span>
            </div>
            <p class="mt-1 text-xs text-slate-500">El vigilante las verá al validar el ingreso.</p>
            <div class="mt-3 flex flex-wrap gap-1.5">
                @foreach ($notesChips as $chip)
                    <button type="button" @click="addNote(@js($chip))" class="rounded-full bg-slate-800 px-2.5 py-1 text-xs text-slate-300 hover:opacity-80">+ {{ $chip }}</button>
                @endforeach
            </div>
            <textarea name="notes" x-model="notes" rows="3" maxlength="500" class="mt-3 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white"
                      placeholder="Ej. Llega en camión de mudanza; usar la entrada de servicio."></textarea>
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ route('client.authorizations.index') }}" class="inline-flex h-10 items-center rounded-lg px-4 text-sm text-slate-400 hover:text-white">Cancelar</a>
            <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-indigo-600 px-5 text-sm font-semibold text-white hover:bg-indigo-500">Crear autorización</button>
        </div>
    </form>

    @push('scripts')
        <script>
            function authorizationForm(cfg) {
                let seq = 0;
                const person = (g = {}) => ({
                    key: ++seq,
                    document_type: g.document_type || 'CC',
                    document_number: (g.document_number || '').trim(),
                    first_name: (g.first_name || '').trim(),
                    last_name: (g.last_name || '').trim(),
                    phone: (g.phone || '').trim(),
                });
                return {
                    ...cfg,
                    members: [],
                    loadingMembers: false,
                    guests: cfg.guests.filter((g) => g && (g.document_number || g.first_name)).map(person),
                    guestModal: { open: false, index: null, data: person(), error: '', found: false },
                    init() {
                        if (this.structureId) this.loadMembers(this.memberId);
                    },
                    async loadMembers(keep = '') {
                        this.memberId = keep;
                        this.members = [];
                        if (!this.structureId) return;
                        this.loadingMembers = true;
                        try {
                            const res = await fetch(`${this.membersUrl}?structure_id=${this.structureId}`, { headers: { Accept: 'application/json' } });
                            this.members = (await res.json()).members || [];
                        } finally {
                            this.loadingMembers = false;
                        }
                    },
                    initials(g) {
                        return ((g.first_name[0] || '') + (g.last_name[0] || '')).toUpperCase();
                    },
                    openGuest(index = null) {
                        this.guestModal = {
                            open: true,
                            index,
                            data: index === null ? person() : { ...this.guests[index] },
                            error: '',
                            found: false,
                        };
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
                        const dup = this.guests.findIndex((x) => x.document_number === g.document_number);
                        if (dup !== -1 && dup !== this.guestModal.index) {
                            this.guestModal.error = 'Esa persona ya está en la lista.';
                            return;
                        }
                        if (this.guestModal.index === null) {
                            this.guests.push(g);
                        } else {
                            this.guests.splice(this.guestModal.index, 1, g);
                        }
                        if (another) {
                            this.openGuest();
                        } else {
                            this.guestModal.open = false;
                        }
                    },
                    addNote(text) {
                        const current = this.notes.trim();
                        if (current.includes(text)) return;
                        this.notes = (current ? current.replace(/\.?$/, '. ') : '') + text + '.';
                    },
                };
            }
        </script>
    @endpush
</x-client-layout>
