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
                        @if ($canOperate ?? false)
                            <form method="POST" action="{{ route('company.clients.activate', $client) }}" class="inline">
                                @csrf
                                <x-ui.button type="submit" size="sm">Operar portería</x-ui.button>
                            </form>
                        @endif
                        @if ($canOperateClientPanel ?? false)
                            <form method="POST" action="{{ route('company.clients.operate-client', $client) }}" class="inline">
                                @csrf
                                <x-ui.button type="submit" variant="secondary" size="sm">Operar cliente</x-ui.button>
                            </form>
                        @endif
                        @if (($canOperateClientPanel ?? false) && $installations->isNotEmpty())
                            <form method="POST" action="{{ route('company.clients.operate-installation', $client) }}" class="inline-flex flex-wrap items-center gap-2">
                                @csrf
                                @if ($installations->count() === 1)
                                    <input type="hidden" name="installation_id" value="{{ $installations->first()->id }}">
                                @else
                                    <select name="installation_id" required class="h-9 rounded-lg border border-slate-700 bg-slate-950 px-2 text-xs text-white">
                                        <option value="">Instalación…</option>
                                        @foreach ($installations as $site)
                                            <option value="{{ $site->id }}">{{ $site->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                <x-ui.button type="submit" variant="secondary" size="sm">Operar instalación</x-ui.button>
                            </form>
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

