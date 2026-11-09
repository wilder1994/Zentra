<x-company-layout :title="$client->name">
    @include('modules.company.clients.partials.nav-slots', [
        'client' => $client,
        'clientNavActive' => 'cliente',
        'vista' => $vista ?? 'cliente',
        'canOperate' => $canOperate,
        'canOperateClientPanel' => $canOperateClientPanel,
        'canUpdate' => $canUpdate,
        'companyContext' => $companyContext ?? ['is_quota_full' => true],
    ])

    @if (($vista ?? 'cliente') === 'cliente')
        <div class="max-w-3xl space-y-4">
            <section class="rounded-lg border border-slate-800 bg-slate-900/80 p-4 space-y-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h3 class="text-sm font-semibold text-white">Ficha comercial</h3>
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($canUpdate ?? false)
                            <x-ui.button variant="secondary" :href="route('company.clients.edit', $client)" size="sm">Editar</x-ui.button>
                        @endif
                        @php
                            $canOperatePorteria = (bool) ($canOperate ?? false);
                            $canOperatePanel = (bool) ($canOperateClientPanel ?? false);
                            $operateAllSites = $installations->values();
                            $operateDoorSites = $operateAllSites
                                ->filter(fn ($site) => ($site->locations ?? collect())->where('is_active', true)->isNotEmpty())
                                ->values();
                            $operateDoorSitesJs = $operateDoorSites->map(fn ($site) => [
                                'id' => (int) $site->id,
                                'name' => $site->name,
                                'doors' => $site->locations->where('is_active', true)->values()->map(fn ($door) => [
                                    'id' => (int) $door->id,
                                    'name' => $door->name,
                                    'code' => $door->code,
                                ])->all(),
                            ])->values()->all();
                            $operateAllSitesJs = $operateAllSites->map(fn ($site) => [
                                'id' => (int) $site->id,
                                'name' => $site->name,
                            ])->values()->all();
                            $showOperateMenu = $canOperatePanel || ($canOperatePorteria && $operateDoorSites->isNotEmpty());
                        @endphp
                        @if ($showOperateMenu)
                            <div
                                class="relative"
                                x-data="{
                                    menu: false,
                                    modal: null,
                                    sites: @js($operateDoorSitesJs),
                                    allSites: @js($operateAllSitesJs),
                                    siteId: @js($operateDoorSites->count() === 1 ? (string) $operateDoorSites->first()->id : ''),
                                    doorId: '',
                                    installId: @js($operateAllSites->count() === 1 ? (string) $operateAllSites->first()->id : ''),
                                    get doors() {
                                        const site = this.sites.find((row) => String(row.id) === String(this.siteId));
                                        return site ? site.doors : [];
                                    },
                                    openMenu() { this.menu = ! this.menu },
                                    closeMenu() { this.menu = false },
                                    openModal(kind) {
                                        this.closeMenu();
                                        this.modal = kind;
                                        if (kind === 'porteria') {
                                            if (this.sites.length === 1) this.siteId = String(this.sites[0].id);
                                            this.doorId = this.doors.length === 1 ? String(this.doors[0].id) : '';
                                        }
                                        if (kind === 'instalacion') {
                                            this.installId = this.allSites.length === 1 ? String(this.allSites[0].id) : '';
                                        }
                                    },
                                    closeModal() { this.modal = null },
                                    onSiteChange() {
                                        this.doorId = this.doors.length === 1 ? String(this.doors[0].id) : '';
                                    },
                                }"
                                @keydown.escape.window="closeModal()"
                            >
                                <button
                                    type="button"
                                    @click="openMenu()"
                                    class="inline-flex h-9 items-center gap-1 rounded-lg bg-indigo-600 px-3 text-sm font-semibold text-white hover:bg-indigo-500"
                                >
                                    Operar
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M5.25 7.5L10 12.25 14.75 7.5"/></svg>
                                </button>
                                <div
                                    x-show="menu"
                                    x-cloak
                                    @click.outside="closeMenu()"
                                    class="absolute right-0 z-30 mt-1 min-w-[12rem] overflow-hidden rounded-lg border border-slate-700 bg-slate-950 shadow-xl"
                                >
                                    @if ($canOperatePanel)
                                        <form method="POST" action="{{ route('company.clients.operate-client', $client) }}">
                                            @csrf
                                            <button type="submit" class="block w-full px-3 py-2 text-left text-xs font-semibold text-white hover:bg-indigo-900/60">Operar cliente</button>
                                        </form>
                                        @if ($operateAllSites->isNotEmpty())
                                            @if ($operateAllSites->count() === 1)
                                                <form method="POST" action="{{ route('company.clients.operate-installation', $client) }}">
                                                    @csrf
                                                    <input type="hidden" name="installation_id" value="{{ $operateAllSites->first()->id }}">
                                                    <button type="submit" class="block w-full px-3 py-2 text-left text-xs font-semibold text-white hover:bg-indigo-900/60">Operar instalación</button>
                                                </form>
                                            @else
                                                <button type="button" @click="openModal('instalacion')" class="block w-full px-3 py-2 text-left text-xs font-semibold text-white hover:bg-indigo-900/60">Operar instalación</button>
                                            @endif
                                        @endif
                                    @endif
                                    @if ($canOperatePorteria && $operateDoorSites->isNotEmpty())
                                        @if ($operateDoorSites->count() === 1 && $operateDoorSites->first()->locations->where('is_active', true)->count() === 1)
                                            @php
                                                $onlySite = $operateDoorSites->first();
                                                $onlyDoor = $onlySite->locations->where('is_active', true)->first();
                                            @endphp
                                            <form method="POST" action="{{ route('company.clients.activate', $client) }}">
                                                @csrf
                                                <input type="hidden" name="installation_id" value="{{ $onlySite->id }}">
                                                <input type="hidden" name="location_id" value="{{ $onlyDoor->id }}">
                                                <button type="submit" class="block w-full px-3 py-2 text-left text-xs font-semibold text-white hover:bg-indigo-900/60">Operar portería</button>
                                            </form>
                                        @else
                                            <button type="button" @click="openModal('porteria')" class="block w-full px-3 py-2 text-left text-xs font-semibold text-white hover:bg-indigo-900/60">Operar portería</button>
                                        @endif
                                    @endif
                                </div>

                                {{-- Modal: Operar instalación --}}
                                <div
                                    x-show="modal === 'instalacion'"
                                    x-cloak
                                    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4"
                                    role="dialog"
                                    aria-modal="true"
                                >
                                    <div class="w-full max-w-md rounded-xl border border-slate-700 bg-slate-900 p-5 shadow-xl" @click.outside="closeModal()">
                                        <div class="mb-4 flex items-start justify-between gap-3">
                                            <div>
                                                <h4 class="text-sm font-semibold text-white">Operar instalación</h4>
                                                <p class="mt-1 text-xs text-slate-500">Elige la instalación del panel cliente.</p>
                                            </div>
                                            <button type="button" @click="closeModal()" class="text-xs text-slate-400 hover:text-white">Cerrar</button>
                                        </div>
                                        <form method="POST" action="{{ route('company.clients.operate-installation', $client) }}" class="space-y-4">
                                            @csrf
                                            <div class="max-h-64 space-y-1.5 overflow-y-auto">
                                                <template x-for="site in allSites" :key="site.id">
                                                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border px-3 py-2.5"
                                                           :class="String(installId) === String(site.id) ? 'border-indigo-500 bg-indigo-950/40' : 'border-slate-800 bg-slate-950/50 hover:border-slate-700'">
                                                        <input type="radio" name="installation_id" :value="site.id" x-model="installId" class="border-slate-600 text-indigo-600" required>
                                                        <span class="text-sm text-white" x-text="site.name"></span>
                                                    </label>
                                                </template>
                                            </div>
                                            <button type="submit" :disabled="!installId"
                                                    class="inline-flex h-9 w-full items-center justify-center rounded-lg bg-indigo-600 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-40">
                                                Operar
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                {{-- Modal: Operar portería --}}
                                <div
                                    x-show="modal === 'porteria'"
                                    x-cloak
                                    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4"
                                    role="dialog"
                                    aria-modal="true"
                                >
                                    <div class="w-full max-w-md rounded-xl border border-slate-700 bg-slate-900 p-5 shadow-xl" @click.outside="closeModal()">
                                        <div class="mb-4 flex items-start justify-between gap-3">
                                            <div>
                                                <h4 class="text-sm font-semibold text-white">Operar portería</h4>
                                                <p class="mt-1 text-xs text-slate-500">Elige instalación y puerta.</p>
                                            </div>
                                            <button type="button" @click="closeModal()" class="text-xs text-slate-400 hover:text-white">Cerrar</button>
                                        </div>
                                        <form method="POST" action="{{ route('company.clients.activate', $client) }}" class="space-y-4">
                                            @csrf
                                            @if ($operateDoorSites->count() > 1)
                                                <div>
                                                    <label class="mb-1 block text-[11px] text-slate-500">Instalación</label>
                                                    <select name="installation_id" x-model="siteId" @change="onSiteChange()" required
                                                            class="h-10 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 text-sm text-white">
                                                        <option value="">Seleccione…</option>
                                                        @foreach ($operateDoorSites as $site)
                                                            <option value="{{ $site->id }}">{{ $site->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            @else
                                                <input type="hidden" name="installation_id" value="{{ $operateDoorSites->first()->id }}">
                                            @endif

                                            <div>
                                                <p class="mb-1 text-[11px] text-slate-500">Puerta</p>
                                                <div class="max-h-64 space-y-1.5 overflow-y-auto" x-show="doors.length">
                                                    <template x-for="door in doors" :key="door.id">
                                                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border px-3 py-2.5"
                                                               :class="String(doorId) === String(door.id) ? 'border-indigo-500 bg-indigo-950/40' : 'border-slate-800 bg-slate-950/50 hover:border-slate-700'">
                                                            <input type="radio" name="location_id" :value="door.id" x-model="doorId" class="border-slate-600 text-indigo-600" required>
                                                            <span class="min-w-0">
                                                                <span class="block text-sm text-white" x-text="door.name"></span>
                                                                <span class="block font-mono text-[10px] text-slate-500" x-show="door.code" x-text="door.code"></span>
                                                            </span>
                                                        </label>
                                                    </template>
                                                </div>
                                                <p class="py-4 text-center text-xs text-slate-500" x-show="!doors.length">Selecciona una instalación para ver sus puertas.</p>
                                            </div>

                                            <button type="submit" :disabled="!doorId"
                                                    class="inline-flex h-9 w-full items-center justify-center rounded-lg bg-indigo-600 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-40">
                                                Operar
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endif
                        @if ($client->has_access || $client->has_supervision)
                            <x-ui.button :href="route('company.clients.show', [$client, 'vista' => 'sitio'])" size="sm">Instalaciones</x-ui.button>
                        @endif
                    </div>
                </div>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-slate-500">Nombre comercial</dt>
                        <dd class="text-white font-medium">{{ $client->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Razón social</dt>
                        <dd class="text-slate-200">{{ $client->legal_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Documento</dt>
                        <dd class="text-slate-200">{{ $client->document_type }} {{ $client->tax_id }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Contacto</dt>
                        <dd class="text-slate-200">{{ $client->email ?: '—' }} · {{ $client->phone ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Inicio de servicio</dt>
                        <dd class="text-slate-200">
                            @if ($canUpdate ?? false)
                                <form method="POST" action="{{ route('company.clients.service-start', $client) }}" class="mt-1 flex flex-wrap items-center gap-2">
                                    @csrf
                                    @method('PUT')
                                    <input type="date" name="service_started_at" value="{{ old('service_started_at', $client->service_started_at?->format('Y-m-d')) }}"
                                           class="h-9 rounded-lg border border-slate-700 bg-slate-950 px-2 text-sm text-white">
                                    <button type="submit" class="h-9 rounded-lg border border-slate-700 px-3 text-xs font-semibold text-slate-200 hover:bg-slate-800">Guardar fecha</button>
                                </form>
                            @else
                                {{ $client->service_started_at?->format('d/m/Y') ?: '—' }}
                            @endif
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-slate-500">Dirección</dt>
                        <dd class="text-slate-200">{{ $client->address ?: '—' }}{{ $client->city ? ' · '.$client->city : '' }}</dd>
                    </div>
                </dl>
            </section>

            @if (($canUpdate ?? false) && $client->has_access)
                @include('modules.company.clients.partials.modules-card', ['client' => $client])
            @endif

            <section class="rounded-lg border border-slate-800 bg-slate-900/80 p-4 space-y-2">
                <h3 class="text-sm font-semibold text-white">Líneas de servicio</h3>
                <p class="text-sm text-slate-400">
                    @if ($client->has_access)
                        Accesos activo (portería y censo).
                    @else
                        Sin Accesos: no se opera portería.
                    @endif
                    @if ($client->has_supervision)
                        Supervisión activa: la revista se firma en la app de campo, en los puestos de este cliente.
                    @else
                        Sin Supervisión.
                    @endif
                    @if ($client->has_observatory)
                        Observatorio activo.
                    @endif
                </p>
                @if ($client->has_supervision)
                    @php
                        $zoneName = $client->supervisorZone?->name;
                        $sharedNames = $client->sharedSupervisorZones->pluck('name')->implode(', ');
                    @endphp
                    <p class="text-sm {{ $zoneName ? 'text-slate-300' : 'text-rose-300' }}">
                        @if ($zoneName)
                            Zona: <span class="font-semibold text-white">{{ $zoneName }}</span>@if ($sharedNames !== '') · compartido con {{ $sharedNames }}@endif
                        @else
                            Sin zona de Supervisión: ningún supervisor lo ve.
                        @endif
                        @can('company.settings.manage')
                            <a href="{{ route('company.supervision-zones.index') }}" class="ml-1 text-xs font-semibold text-indigo-300 hover:text-indigo-200">Zonas</a>
                        @endcan
                    </p>
                @endif
                @if ($client->isCatalogOnly())
                    <p class="text-xs text-slate-500">Esta ficha no consume cupo. Activa Accesos o Supervisión en Editar cuando tengas asientos.</p>
                @endif
            </section>
        </div>
    @elseif (($vista ?? '') === 'sitio')
        <div class="w-full space-y-4">
            @include('modules.company.clients.partials.sitio-tree', [
                'client' => $client,
                'installations' => $installations ?? collect(),
                'canManageTree' => $canManageTree ?? false,
                'postModalities' => $postModalities ?? [],
                'siteAdmins' => $siteAdmins ?? [],
                'maps' => $maps ?? ['api_key' => config('google-maps.api_key')],
            ])
        </div>
    @elseif (($vista ?? '') === 'resumen' && $expediente)
        <div class="space-y-4">
            @include('modules.company.clients.partials.expediente-resumen', [
                'client' => $client,
                'expediente' => $expediente,
                'installationsCount' => $installationsCount ?? 0,
            ])
        </div>
    @endif

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
        <script>
            (function () {
                const expediente = @json($expediente);
                if (! expediente) {
                    return;
                }
                const chart = expediente.chart;
                const presence = expediente.presence_chart;
                const defaults = {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: '#94a3b8', boxWidth: 10, font: { size: 10 } } } },
                    scales: {
                        x: { ticks: { color: '#64748b', font: { size: 10 } }, grid: { color: 'rgba(51,65,85,0.35)' } },
                        y: { ticks: { color: '#64748b', font: { size: 10 } }, grid: { color: 'rgba(51,65,85,0.35)' }, beginAtZero: true },
                    },
                };

                const trafficEl = document.getElementById('clientTrafficChart');
                if (trafficEl) {
                    new Chart(trafficEl, {
                        type: 'line',
                        data: {
                            labels: chart.labels,
                            datasets: [
                                {
                                    label: 'Ingresos',
                                    data: chart.entries,
                                    borderColor: '#34d399',
                                    backgroundColor: 'rgba(52,211,153,0.12)',
                                    fill: true,
                                    tension: 0.35,
                                },
                                {
                                    label: 'Salidas',
                                    data: chart.exits,
                                    borderColor: '#fbbf24',
                                    backgroundColor: 'transparent',
                                    tension: 0.35,
                                },
                            ],
                        },
                        options: defaults,
                    });
                }

                const presenceEl = document.getElementById('clientPresenceChart');
                if (presenceEl) {
                    new Chart(presenceEl, {
                        type: 'doughnut',
                        data: {
                            labels: presence.labels,
                            datasets: [{
                                data: presence.values,
                                backgroundColor: ['#6366f1', '#fbbf24', '#f87171'],
                                borderWidth: 0,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '58%',
                            plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8', boxWidth: 8, font: { size: 10 } } } },
                        },
                    });
                }
            })();
        </script>
    @endpush
</x-company-layout>

