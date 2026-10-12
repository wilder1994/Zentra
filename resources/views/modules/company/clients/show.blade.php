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
                <h3 class="text-sm font-semibold text-white">Ficha comercial</h3>
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
            @php
                $showActionCards = ($canOperate ?? false)
                    || ($canOperateClientPanel ?? false)
                    || ($canUpdate ?? false)
                    || $client->has_access
                    || $client->has_supervision;
                $actionCard = 'flex h-full min-h-[7.5rem] w-full flex-col items-start rounded-lg border border-slate-700 bg-slate-900/80 p-4 text-left text-inherit no-underline shadow-none appearance-none box-border hover:bg-slate-800/80 transition-colors';
            @endphp
            @if ($showActionCards)
                <section class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-stretch">
                    @if ($canOperate ?? false)
                        <form method="POST" action="{{ route('company.clients.activate', $client) }}" class="flex h-full min-h-[7.5rem]">
                            @csrf
                            <button type="submit" class="{{ $actionCard }}">
                                <p class="text-sm font-semibold text-white">Operar portería</p>
                                <p class="mt-1 text-xs text-slate-400 leading-relaxed">Entra al módulo de accesos de este cliente.</p>
                            </button>
                        </form>
                    @endif
                    @if ($canOperateClientPanel ?? false)
                        <form method="POST" action="{{ route('company.clients.operate-client', $client) }}" class="flex h-full min-h-[7.5rem]">
                            @csrf
                            <button type="submit" class="{{ $actionCard }}">
                                <p class="text-sm font-semibold text-white">Operar cliente</p>
                                <p class="mt-1 text-xs text-slate-400 leading-relaxed">Abre el panel de censo y estructura.</p>
                            </button>
                        </form>
                        @if ($installations->isNotEmpty())
                            <form method="POST" action="{{ route('company.clients.operate-installation', $client) }}"
                                  class="flex h-full min-h-[7.5rem] w-full flex-col items-start rounded-lg border border-slate-700 bg-slate-900/80 p-4">
                                @csrf
                                <p class="text-sm font-semibold text-white">Operar instalación</p>
                                @if ($installations->count() === 1)
                                    <p class="mt-1 text-xs text-slate-400 leading-relaxed">Entra a {{ $installations->first()->name }}.</p>
                                    <input type="hidden" name="installation_id" value="{{ $installations->first()->id }}">
                                @else
                                    <p class="mt-1 text-xs text-slate-400 leading-relaxed">Elige la instalación y entra a su panel.</p>
                                    <select name="installation_id" required class="mt-2 w-full h-9 rounded-lg border border-slate-700 bg-slate-950 px-2 text-xs text-white">
                                        <option value="">Selecciona…</option>
                                        @foreach ($installations as $site)
                                            <option value="{{ $site->id }}">{{ $site->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                <button type="submit" class="mt-auto pt-3 text-xs font-semibold text-indigo-300 hover:text-indigo-200">Entrar</button>
                            </form>
                        @endif
                    @endif
                    @if ($canUpdate ?? false)
                        <a href="{{ route('company.clients.edit', $client) }}" class="{{ $actionCard }}">
                            <p class="text-sm font-semibold text-white">Editar</p>
                            <p class="mt-1 text-xs text-slate-400 leading-relaxed">Cambia la ficha comercial y las líneas de servicio.</p>
                        </a>
                    @endif
                    @if ($client->has_access || $client->has_supervision)
                        <a href="{{ route('company.clients.show', [$client, 'vista' => 'sitio']) }}" class="{{ $actionCard }}">
                            <p class="text-sm font-semibold text-white">Instalaciones y puestos</p>
                            <p class="mt-1 text-xs text-slate-400 leading-relaxed">Sitio, modalidad y vigilantes.</p>
                        </a>
                    @endif
                    @if ($client->has_access)
                        <a href="{{ route('company.clients.show', [$client, 'vista' => 'puertas']) }}" class="{{ $actionCard }}">
                            <p class="text-sm font-semibold text-white">Puertas</p>
                            <p class="mt-1 text-xs text-slate-400 leading-relaxed">Quién entra y sale en cada instalación.</p>
                        </a>
                    @endif
                </section>
            @endif
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
                </p>
                @if ($client->isCatalogOnly())
                    <p class="text-xs text-slate-500">Esta ficha no consume cupo. Activa Accesos o Supervisión en Editar cuando tengas asientos.</p>
                @endif
            </section>
        </div>
    @elseif (($vista ?? '') === 'sitio')
        <div class="max-w-3xl space-y-4">
            <x-ui.button variant="secondary" :href="route('company.clients.show', [$client, 'vista' => 'cliente'])" size="sm">← Cliente</x-ui.button>
            @include('modules.company.clients.partials.sitio-tree', [
                'client' => $client,
                'installations' => $installations ?? collect(),
                'canManageTree' => $canManageTree ?? false,
                'postModalities' => $postModalities ?? [],
                'siteAdmins' => $siteAdmins ?? [],
            ])
        </div>
    @elseif (($vista ?? '') === 'puertas')
        <div class="max-w-3xl space-y-4">
            <x-ui.button variant="secondary" :href="route('company.clients.show', [$client, 'vista' => 'cliente'])" size="sm">← Cliente</x-ui.button>
            @include('modules.company.clients.partials.puertas-tree', [
                'client' => $client,
                'installations' => $installations ?? collect(),
                'canManageTree' => $canManageTree ?? false,
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
