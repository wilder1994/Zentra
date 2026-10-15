@php
    $exceptionRows = $exceptions->map(fn ($e) => [
        'id' => $e->id,
        'company_id' => $e->security_company_id,
        'client_id' => $e->client_id,
        'installation_id' => $e->installation_id,
        'radius_m' => $e->radius_m,
        'reason' => $e->reason,
    ])->values();
    $openRadius = session('open_radius') || $errors->hasAny(['default_radius_m', 'max_radius_m', 'security_company_id', 'client_id', 'installation_id', 'radius_m', 'reason']);
@endphp

<x-admin-layout title="Ajustes">
    <div class="space-y-6"
         x-data="reviewRadius(@js([
            'companies' => $companies,
            'clients' => $clients,
            'installations' => $installations,
            'exceptions' => $exceptionRows,
            'defaultRadius' => $settings->default_radius_m,
            'maxRadius' => $settings->max_radius_m,
            'open' => $openRadius,
            'old' => [
                'company' => old('security_company_id'),
                'client' => old('client_id'),
                'installation' => old('installation_id'),
                'radius' => old('radius_m'),
                'reason' => old('reason'),
            ],
            'destroyUrl' => url('admin/settings/review-radius').'/__ID__',
         ]))">
        @include('modules.admin.settings.partials.subnav')

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <button type="button" @click="openModal()"
                    class="text-left rounded-xl border border-slate-800 bg-slate-900 p-5 hover:border-[var(--ui-accent)] transition">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-white">Radio de revistas</h3>
                        <p class="mt-1 text-xs text-slate-400">Distancia máxima al puesto para que el supervisor registre la revista.</p>
                    </div>
                    <span class="shrink-0 rounded-lg bg-violet-600 px-2.5 py-1 text-sm font-bold text-white">{{ $settings->default_radius_m }} m</span>
                </div>
                <p class="mt-4 text-xs text-slate-400">
                    Tope {{ $settings->max_radius_m }} m ·
                    {{ $exceptions->count() === 0 ? 'Sin excepciones' : $exceptions->count().' '.($exceptions->count() === 1 ? 'excepción activa' : 'excepciones activas') }}
                </p>
            </button>

            <a href="{{ route('admin.settings.document-types.index') }}"
               class="rounded-xl border border-slate-800 bg-slate-900 p-5 hover:border-[var(--ui-accent)] transition">
                <h3 class="text-sm font-semibold text-white">Tipos de documento</h3>
                <p class="mt-1 text-xs text-slate-400">CC, CE, NIT… usados en contratación y formularios de personas.</p>
            </a>
        </div>

        <template x-teleport="body">
            <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/60 p-4 sm:p-8" @keydown.escape.window="open = false">
                <div class="w-full max-w-3xl rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl" @click.outside="open = false">
                    <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
                        <div>
                            <h3 class="text-base font-semibold text-white">Radio de revistas</h3>
                            <p class="text-xs text-slate-400">Solo la plataforma lo modifica. Se aplica: instalación → cliente → empresa → global.</p>
                        </div>
                        <button type="button" @click="open = false" class="text-slate-400 hover:text-white" aria-label="Cerrar">✕</button>
                    </div>

                    <div class="space-y-6 p-5">
                        <form method="POST" action="{{ route('admin.settings.review-radius.settings') }}" class="rounded-xl border border-slate-800 p-4">
                            @csrf
                            @method('PUT')
                            <h4 class="text-sm font-semibold text-white">Radio global</h4>
                            <div class="mt-3 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                                <label class="block">
                                    <span class="block text-xs text-slate-400 mb-1">Radio por defecto (m)</span>
                                    <input type="number" name="default_radius_m" min="10" max="5000" required value="{{ old('default_radius_m', $settings->default_radius_m) }}"
                                           class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
                                </label>
                                <label class="block">
                                    <span class="block text-xs text-slate-400 mb-1">Tope máximo (m)</span>
                                    <input type="number" name="max_radius_m" min="10" max="5000" required value="{{ old('max_radius_m', $settings->max_radius_m) }}"
                                           class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
                                </label>
                                <button type="submit" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">Guardar</button>
                            </div>
                            @error('default_radius_m') <p class="mt-2 text-xs text-rose-400">{{ $message }}</p> @enderror
                            @error('max_radius_m') <p class="mt-2 text-xs text-rose-400">{{ $message }}</p> @enderror
                            <p class="mt-2 text-[11px] text-slate-500">Ninguna excepción puede pasar del tope.</p>
                        </form>

                        <form method="POST" action="{{ route('admin.settings.review-radius.store') }}" class="rounded-xl border border-slate-800 p-4" id="review-radius-exception">
                            @csrf
                            <h4 class="text-sm font-semibold text-white" x-text="exact() ? 'Editar excepción' : 'Nueva excepción'"></h4>
                            <input type="hidden" name="security_company_id" :value="sel.company ?? ''">
                            <input type="hidden" name="client_id" :value="sel.client ?? ''">
                            <input type="hidden" name="installation_id" :value="sel.installation ?? ''">

                            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                @foreach ([
                                    ['key' => 'company', 'label' => 'Empresa', 'placeholder' => 'Buscar empresa…'],
                                    ['key' => 'client', 'label' => 'Cliente (opcional)', 'placeholder' => 'Buscar cliente…'],
                                    ['key' => 'installation', 'label' => 'Instalación (opcional)', 'placeholder' => 'Buscar instalación…'],
                                ] as $combo)
                                    <div class="relative" @click.outside="focus === '{{ $combo['key'] }}' && (focus = null)">
                                        <span class="block text-xs text-slate-400 mb-1">{{ $combo['label'] }}</span>
                                        <template x-if="sel.{{ $combo['key'] }}">
                                            <div class="flex items-center justify-between gap-2 rounded-lg border border-[var(--ui-accent)] bg-slate-950 px-3 py-2 text-sm text-white">
                                                <span class="truncate" x-text="nameOf('{{ $combo['key'] }}', sel.{{ $combo['key'] }})"></span>
                                                <button type="button" @click="clear('{{ $combo['key'] }}')" class="shrink-0 text-slate-400 hover:text-white" aria-label="Quitar">✕</button>
                                            </div>
                                        </template>
                                        <template x-if="!sel.{{ $combo['key'] }}">
                                            <div>
                                                <input type="text" x-model="q.{{ $combo['key'] }}" @focus="focus = '{{ $combo['key'] }}'" @input="focus = '{{ $combo['key'] }}'"
                                                       :disabled="!enabled('{{ $combo['key'] }}')" placeholder="{{ $combo['placeholder'] }}" autocomplete="off"
                                                       class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white disabled:opacity-40">
                                                <div x-show="focus === '{{ $combo['key'] }}'" x-cloak
                                                     class="absolute z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-slate-700 bg-slate-900 shadow-xl">
                                                    <template x-for="item in options('{{ $combo['key'] }}')" :key="item.id">
                                                        <button type="button" @click="pick('{{ $combo['key'] }}', item.id)"
                                                                class="block w-full px-3 py-2 text-left text-sm text-slate-200 hover:bg-slate-800" x-text="item.name"></button>
                                                    </template>
                                                    <p x-show="options('{{ $combo['key'] }}').length === 0" class="px-3 py-2 text-xs text-slate-500">Sin resultados</p>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                @endforeach
                            </div>
                            @foreach (['security_company_id', 'client_id', 'installation_id'] as $field)
                                @error($field) <p class="mt-2 text-xs text-rose-400">{{ $message }}</p> @enderror
                            @endforeach

                            <div x-show="sel.company" x-cloak class="mt-4 rounded-lg border border-slate-800 bg-slate-950/40 p-4">
                                <p class="text-xs text-slate-400">
                                    Radio actual: <span class="text-base font-bold text-white" x-text="current().meters + ' m'"></span>
                                    <span class="ml-1" x-text="'(' + current().source + ')'"></span>
                                </p>
                                <div class="mt-3 grid gap-3 sm:grid-cols-[10rem_1fr]">
                                    <label class="block">
                                        <span class="block text-xs text-slate-400 mb-1">Nuevo radio (m)</span>
                                        <input type="number" name="radius_m" min="10" :max="maxRadius" x-model.number="radius" required
                                               class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
                                    </label>
                                    <label class="block">
                                        <span class="block text-xs text-slate-400 mb-1">Motivo</span>
                                        <input type="text" name="reason" maxlength="255" x-model="reason" required placeholder="Ej. Instalación de 40 ha, puestos dispersos"
                                               class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-white">
                                    </label>
                                </div>
                                <p class="mt-1 text-[11px] text-slate-500" x-text="'Máximo ' + maxRadius + ' m.'"></p>
                                @error('radius_m') <p class="mt-2 text-xs text-rose-400">{{ $message }}</p> @enderror
                                @error('reason') <p class="mt-2 text-xs text-rose-400">{{ $message }}</p> @enderror
                                <div class="mt-4 flex flex-wrap justify-end gap-2">
                                    <button type="button" x-show="exact()" @click="remove(exact().id)"
                                            class="rounded-lg border border-rose-500/50 px-4 py-2 text-sm font-semibold text-rose-400 hover:bg-rose-500/10">Quitar excepción</button>
                                    <button type="submit" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">Guardar</button>
                                </div>
                            </div>
                        </form>

                        <div>
                            <h4 class="text-sm font-semibold text-white">Excepciones activas</h4>
                            @if ($exceptions->isEmpty())
                                <p class="mt-2 text-xs text-slate-500">Todas las instalaciones usan el radio global.</p>
                            @else
                                <div class="mt-2 divide-y divide-slate-800 rounded-xl border border-slate-800">
                                    @foreach ($exceptions as $e)
                                        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                                            <div class="min-w-0">
                                                <p class="text-sm text-white">
                                                    {{ $e->company?->displayName() ?? '—' }}
                                                    @if ($e->client) <span class="text-slate-500">·</span> {{ $e->client->name }} @endif
                                                    @if ($e->installation) <span class="text-slate-500">·</span> {{ $e->installation->name }} @endif
                                                </p>
                                                <p class="text-xs text-slate-400">
                                                    {{ $e->levelLabel() }} · {{ $e->reason }} ·
                                                    {{ $e->updatedByUser?->name ?? 'Sistema' }}, {{ $e->updated_at?->format('d/m/Y H:i') }}
                                                </p>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <span class="rounded-lg bg-violet-600 px-2.5 py-1 text-sm font-bold text-white">{{ min($e->radius_m, $settings->max_radius_m) }} m</span>
                                                <button type="button" @click="edit({{ $e->id }})" class="text-xs text-violet-300 hover:text-violet-200">Editar</button>
                                                <button type="button" @click="remove({{ $e->id }})" class="text-xs text-rose-400 hover:text-rose-300">Quitar</button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <form method="POST" id="review-radius-delete" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            </div>
        </template>
    </div>

    @push('scripts')
        <script>
            function reviewRadius(cfg) {
                const norm = (s) => String(s ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
                const toId = (v) => (v === null || v === undefined || v === '') ? null : Number(v);

                return {
                    ...cfg,
                    focus: null,
                    q: { company: '', client: '', installation: '' },
                    sel: { company: toId(cfg.old.company), client: toId(cfg.old.client), installation: toId(cfg.old.installation) },
                    radius: cfg.old.radius ? Number(cfg.old.radius) : null,
                    reason: cfg.old.reason ?? '',

                    init() {
                        if (this.sel.company && this.radius === null) this.prefill();
                    },
                    openModal() {
                        this.open = true;
                    },
                    list(key) {
                        if (key === 'company') return this.companies;
                        if (key === 'client') return this.clients.filter(c => c.company_id === this.sel.company);
                        return this.installations.filter(i => i.client_id === this.sel.client);
                    },
                    enabled(key) {
                        if (key === 'company') return true;
                        if (key === 'client') return !!this.sel.company;
                        return !!this.sel.client;
                    },
                    options(key) {
                        const term = norm(this.q[key]);
                        return this.list(key).filter(i => !term || norm(i.name).includes(term)).slice(0, 50);
                    },
                    nameOf(key, id) {
                        const src = key === 'company' ? this.companies : (key === 'client' ? this.clients : this.installations);
                        return src.find(i => i.id === id)?.name ?? '—';
                    },
                    pick(key, id) {
                        this.sel[key] = id;
                        this.q[key] = '';
                        this.focus = null;
                        if (key === 'company') { this.sel.client = null; this.sel.installation = null; }
                        if (key === 'client') { this.sel.installation = null; }
                        this.prefill();
                    },
                    clear(key) {
                        this.sel[key] = null;
                        if (key === 'company') { this.sel.client = null; this.sel.installation = null; }
                        if (key === 'client') { this.sel.installation = null; }
                        this.prefill();
                    },
                    find(company, client, installation) {
                        return this.exceptions.find(e => e.company_id === company
                            && (e.client_id ?? null) === client
                            && (e.installation_id ?? null) === installation) ?? null;
                    },
                    exact() {
                        if (!this.sel.company) return null;
                        return this.find(this.sel.company, this.sel.client, this.sel.installation);
                    },
                    current() {
                        const s = this.sel;
                        const chain = [];
                        if (s.installation) chain.push([this.find(s.company, s.client, s.installation), 'instalación']);
                        if (s.client) chain.push([this.find(s.company, s.client, null), 'cliente']);
                        chain.push([this.find(s.company, null, null), 'empresa']);
                        for (const [exc, label] of chain) {
                            if (exc) return { meters: Math.min(exc.radius_m, this.maxRadius), source: 'excepción de ' + label };
                        }
                        return { meters: Math.min(this.defaultRadius, this.maxRadius), source: 'global' };
                    },
                    prefill() {
                        const exc = this.exact();
                        this.radius = exc ? exc.radius_m : (this.sel.company ? this.current().meters : null);
                        this.reason = exc ? exc.reason : '';
                    },
                    edit(id) {
                        const exc = this.exceptions.find(e => e.id === id);
                        if (!exc) return;
                        this.sel = { company: exc.company_id, client: exc.client_id ?? null, installation: exc.installation_id ?? null };
                        this.prefill();
                        document.getElementById('review-radius-exception').scrollIntoView({ behavior: 'smooth', block: 'start' });
                    },
                    remove(id) {
                        if (!confirm('¿Quitar esta excepción? Volverá al radio heredado.')) return;
                        const form = document.getElementById('review-radius-delete');
                        form.action = this.destroyUrl.replace('__ID__', id);
                        form.submit();
                    },
                };
            }
        </script>
    @endpush
</x-admin-layout>
