<x-client-layout title="Nueva autorización">
    @php
        $firstInstallation = $installations->count() === 1 ? (string) $installations->first()->id : '';
        $config = [
            'type' => old('type', $preset['type'] ?? 'visit'),
            'mode' => old('destination_mode', 'unit'),
            'installationId' => (string) old('installation_id', $preset['installation_id'] ?? $firstInstallation),
            'structureId' => (string) old('structure_id', $preset['structure_id'] ?? ''),
            'destinationText' => old('destination_text', ''),
            'memberId' => (string) old('member_id', ''),
            'authorizedText' => old('authorized_text', ''),
            'validFrom' => old('valid_from', now()->toDateString()),
            'validUntil' => old('valid_until', now()->toDateString()),
            'multi' => (bool) old('multi_entry', $multiDefaults[old('type', $preset['type'] ?? 'visit')] ?? false),
            'guests' => array_values(old('guests', [])),
            'nodes' => $nodeOptions,
            'multiDefaults' => $multiDefaults,
            'membersUrl' => route('client.authorizations.members'),
        ];
        $input = 'w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white';
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
            <input type="hidden" name="type" :value="type">
            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ($types as $value => $label)
                    <button type="button" @click="setType('{{ $value }}')"
                            :class="type === '{{ $value }}' ? 'border-teal-500 bg-teal-500/15 text-teal-200' : 'border-slate-700 bg-slate-950 text-slate-300 hover:border-slate-500'"
                            class="rounded-xl border px-3 py-3 text-sm font-medium">{{ $label }}</button>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-white">2. Destino y quién autoriza</h3>
                <div class="flex gap-1 rounded-lg bg-slate-950 p-1 ring-1 ring-slate-800">
                    <input type="hidden" name="destination_mode" :value="mode">
                    <button type="button" @click="mode = 'unit'" :class="mode === 'unit' ? 'bg-slate-700 text-white' : 'text-slate-400'" class="rounded-md px-3 py-1.5 text-xs font-semibold">Unidad</button>
                    <button type="button" @click="mode = 'zone'" :class="mode === 'zone' ? 'bg-slate-700 text-white' : 'text-slate-400'" class="rounded-md px-3 py-1.5 text-xs font-semibold">Zona común</button>
                </div>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div @class(['hidden' => $installations->count() === 1])>
                    <label class="mb-1 block text-xs text-slate-400">Instalación</label>
                    <select name="installation_id" x-model="installationId" @change="structureId = ''; members = []" class="{{ $input }}">
                        <option value="">Seleccione instalación</option>
                        @foreach ($installations as $installation)
                            <option value="{{ $installation->id }}">{{ $installation->name }}</option>
                        @endforeach
                    </select>
                </div>

                <template x-if="mode === 'unit'">
                    <div>
                        <label class="mb-1 block text-xs text-slate-400">Unidad</label>
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
                        <label class="mb-1 block text-xs text-slate-400">Zona común</label>
                        <input type="text" name="destination_text" x-model="destinationText" required maxlength="150" placeholder="Ej. Salón social" class="{{ $input }}">
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($zones as $zone)
                                <button type="button" @click="destinationText = @js($zone)" class="rounded-full bg-slate-800 px-2.5 py-1 text-xs text-slate-300 hover:bg-slate-700">{{ $zone }}</button>
                            @endforeach
                        </div>
                    </div>
                </template>

                <div class="sm:col-span-2 grid gap-4 sm:grid-cols-2">
                    <div x-show="mode === 'unit'">
                        <label class="mb-1 block text-xs text-slate-400">Autoriza (residente)</label>
                        <select name="member_id" x-model="memberId" :disabled="mode !== 'unit'" class="{{ $input }}">
                            <option value="" x-text="structureId ? (loadingMembers ? 'Cargando…' : 'Otra persona / administración') : 'Elige primero la unidad'"></option>
                            <template x-for="m in members" :key="m.id">
                                <option :value="String(m.id)" :selected="String(m.id) === memberId" x-text="m.name"></option>
                            </template>
                        </select>
                    </div>
                    <div x-show="mode === 'zone' || !memberId">
                        <label class="mb-1 block text-xs text-slate-400">Nombre de quien autoriza</label>
                        <input type="text" name="authorized_text" x-model="authorizedText" maxlength="150" placeholder="Administración" class="{{ $input }}">
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <h3 class="text-sm font-semibold text-white">3. Vigencia</h3>
            <div class="mt-4 grid gap-4 sm:grid-cols-4">
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Desde</label>
                    <input type="date" name="valid_from" x-model="validFrom" min="{{ now()->toDateString() }}" @change="if (validUntil < validFrom) validUntil = validFrom" required class="{{ $input }}">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Hasta</label>
                    <input type="date" name="valid_until" x-model="validUntil" :min="validFrom" required class="{{ $input }}">
                </div>
                <div class="sm:col-span-2 flex flex-wrap items-end gap-2">
                    <button type="button" @click="range(0, 0)" class="rounded-lg bg-slate-800 px-3 py-2 text-xs text-slate-200 hover:bg-slate-700">Hoy</button>
                    <button type="button" @click="range(1, 1)" class="rounded-lg bg-slate-800 px-3 py-2 text-xs text-slate-200 hover:bg-slate-700">Mañana</button>
                    <button type="button" @click="range(0, 6)" class="rounded-lg bg-slate-800 px-3 py-2 text-xs text-slate-200 hover:bg-slate-700">7 días</button>
                    <button type="button" @click="range(0, 29)" class="rounded-lg bg-slate-800 px-3 py-2 text-xs text-slate-200 hover:bg-slate-700">30 días</button>
                </div>
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label class="flex items-start gap-3 rounded-xl border border-slate-800 bg-slate-950 p-3">
                    <input type="checkbox" name="multi_entry" value="1" x-model="multi" @change="multiTouched = true" class="mt-0.5 rounded border-slate-600 bg-slate-900 text-teal-500">
                    <span>
                        <span class="block text-sm font-medium text-white">Entrada múltiple</span>
                        <span class="block text-xs text-slate-500">Pueden entrar y salir varias veces durante la vigencia (trasteos, eventos, obras).</span>
                    </span>
                </label>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Placa del vehículo (opcional)</label>
                    <input type="text" name="vehicle_plate" value="{{ old('vehicle_plate') }}" maxlength="15" placeholder="ABC123" class="{{ $input }} uppercase">
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-white">4. Personas autorizadas <span class="text-slate-500" x-text="'(' + filled() + ')'"></span></h3>
                    <p class="text-xs text-slate-500">Cada persona queda registrada como visitante. En portería solo se toma la foto.</p>
                </div>
                <div class="flex gap-2">
                    <button type="button" @click="pasteOpen = !pasteOpen" class="rounded-lg bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-700">Pegar lista</button>
                    <button type="button" @click="addGuest()" class="rounded-lg bg-teal-600 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-500">+ Agregar persona</button>
                </div>
            </div>

            <div x-show="pasteOpen" x-cloak class="mt-4 rounded-xl border border-slate-700 bg-slate-950 p-3">
                <p class="text-xs text-slate-400">Una persona por línea: <span class="font-mono text-slate-300">documento, nombres, apellidos, teléfono</span> (separado por coma o tabulador, sirve copiar desde Excel).</p>
                <textarea x-model="pasteText" rows="5" class="mt-2 {{ $input }} font-mono" placeholder="1012345678, Juan, Pérez, 3001234567"></textarea>
                <div class="mt-2 flex justify-end gap-2">
                    <button type="button" @click="pasteOpen = false" class="rounded-lg px-3 py-1.5 text-xs text-slate-400 hover:text-white">Cancelar</button>
                    <button type="button" @click="importPaste()" class="rounded-lg bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-500">Agregar a la lista</button>
                </div>
            </div>

            <div class="mt-4 space-y-2">
                <template x-for="(g, i) in guests" :key="g.key">
                    <div class="grid grid-cols-12 gap-2 rounded-xl border border-slate-800 bg-slate-950/60 p-2">
                        <select :name="`guests[${i}][document_type]`" x-model="g.document_type" class="col-span-3 sm:col-span-1 rounded-lg border border-slate-700 bg-slate-950 px-2 py-2 text-xs text-white">
                            <template x-for="t in ['CC','CE','TI','PAS','PPT']" :key="t"><option :value="t" :selected="t === g.document_type" x-text="t"></option></template>
                        </select>
                        <input :name="`guests[${i}][document_number]`" x-model="g.document_number" required placeholder="Documento" inputmode="numeric" class="col-span-9 sm:col-span-2 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                        <input :name="`guests[${i}][first_name]`" x-model="g.first_name" required placeholder="Nombres" class="col-span-6 sm:col-span-3 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                        <input :name="`guests[${i}][last_name]`" x-model="g.last_name" placeholder="Apellidos" class="col-span-6 sm:col-span-3 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                        <input :name="`guests[${i}][phone]`" x-model="g.phone" placeholder="Teléfono" inputmode="tel" class="col-span-10 sm:col-span-2 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                        <button type="button" @click="removeGuest(i)" :disabled="guests.length === 1" class="col-span-2 sm:col-span-1 rounded-lg text-slate-500 hover:bg-red-500/10 hover:text-red-300 disabled:opacity-30" title="Quitar">✕</button>
                    </div>
                </template>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <label class="mb-1 block text-sm font-semibold text-white">Notas para portería (opcional)</label>
            <textarea name="notes" rows="2" maxlength="1000" class="{{ $input }}" placeholder="Ej. Trasteo con camión, usar parqueadero de visitantes.">{{ old('notes') }}</textarea>
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ route('client.authorizations.index') }}" class="rounded-lg px-4 py-2 text-sm text-slate-400 hover:text-white">Cancelar</a>
            <button type="submit" class="rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-500">Crear autorización</button>
        </div>
    </form>

    @push('scripts')
        <script>
            function authorizationForm(cfg) {
                let seq = 0;
                const blank = (g = {}) => ({
                    key: ++seq,
                    document_type: g.document_type || 'CC',
                    document_number: g.document_number || '',
                    first_name: g.first_name || '',
                    last_name: g.last_name || '',
                    phone: g.phone || '',
                });
                return {
                    ...cfg,
                    multiTouched: false,
                    members: [],
                    loadingMembers: false,
                    pasteOpen: false,
                    pasteText: '',
                    guests: (cfg.guests.length ? cfg.guests : [{}]).map(blank),
                    init() {
                        if (this.structureId) this.loadMembers(this.memberId);
                    },
                    setType(value) {
                        this.type = value;
                        if (!this.multiTouched) this.multi = !!this.multiDefaults[value];
                    },
                    range(startOffset, endOffset) {
                        const day = (n) => { const d = new Date(); d.setDate(d.getDate() + n); return d.toLocaleDateString('en-CA'); };
                        this.validFrom = day(startOffset);
                        this.validUntil = day(endOffset);
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
                    filled() {
                        return this.guests.filter((g) => g.document_number.trim() || g.first_name.trim()).length;
                    },
                    addGuest() {
                        this.guests.push(blank());
                    },
                    removeGuest(i) {
                        if (this.guests.length > 1) this.guests.splice(i, 1);
                    },
                    importPaste() {
                        const rows = this.pasteText.split(/\r?\n/).map((l) => l.trim()).filter(Boolean);
                        const parsed = rows.map((line) => {
                            const [doc, first, last, phone] = line.split(/\t|,|;/).map((p) => (p || '').trim());
                            return blank({ document_number: doc.replace(/[\s.]/g, ''), first_name: first, last_name: last, phone });
                        }).filter((g) => g.document_number || g.first_name);
                        this.guests = [...this.guests.filter((g) => g.document_number.trim() || g.first_name.trim()), ...parsed];
                        if (!this.guests.length) this.guests = [blank()];
                        this.pasteText = '';
                        this.pasteOpen = false;
                    },
                };
            }
        </script>
    @endpush
</x-client-layout>
