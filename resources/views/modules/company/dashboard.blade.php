@php
    $endsAt = $metrics['package_ends_at'] ?? null;
    $daysLeft = $metrics['days_until_renewal'];
    $statusColor = ($metrics['is_expired'] ?? false)
        ? 'text-red-400'
        : (($metrics['is_renewal_soon'] ?? false) ? 'text-amber-400' : 'text-emerald-400');
    $ops = $ops ?? [];
    $k = $ops['kpis'] ?? [];
    $workforce = $ops['workforce'] ?? [];
    $mapMarkers = $ops['map_markers'] ?? [];
    $portfolio = $ops['portfolio'] ?? [];
    $revistaMonthly = $ops['revista_monthly'] ?? ['labels' => [], 'done' => [], 'expected' => [], 'pending' => []];
    $revistaWeek = $ops['revista_week'] ?? ['labels' => [], 'done' => [], 'expected' => [], 'pending' => []];
    $accessByClient = $ops['access_by_client'] ?? [];
    $openShiftsTable = $ops['open_shifts_table'] ?? [];
    $mapMarkersJson = json_encode($mapMarkers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $googleMapsJson = json_encode($ops['google_maps'] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $revistaMonthlyJson = json_encode($revistaMonthly, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $revistaWeekJson = json_encode($revistaWeek, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $accessChartJson = json_encode($accessByClient, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $fieldSupervision = $fieldSupervision ?? null;

    $panicOpen = (int) ($k['panics_open'] ?? 0);
    $novedades = (int) ($k['novedades_today'] ?? 0);
    $corrPending = (int) ($k['pending_correspondence'] ?? 0);
    $blockTotal = (int) ($k['blocklist_total'] ?? 0);
    $sinAsign = (int) ($workforce['without_assignment'] ?? 0);
    $shiftsCount = count($openShiftsTable);
    $dashboardLiveUrl = route('company.dashboard.live');
    $dashboardLivePayload = [
        'metrics' => [
            'clients_remaining' => $metrics['clients_remaining'] ?? ($portfolio['available'] ?? 0),
            'supervision_remaining' => $metrics['supervision_remaining'] ?? 0,
            'supervision_remaining_label' => $metrics['supervision_remaining_label'] ?? ($metrics['supervision_remaining'] ?? 0),
            'package_label' => $metrics['package_label'] ?? '—',
        ],
        'ops' => [
            'kpis' => $k,
            'workforce' => $workforce,
            'portfolio' => $portfolio,
            'revista_monthly' => $revistaMonthly,
            'revista_week' => $revistaWeek,
            'access_by_client' => $accessByClient,
            'open_shifts_table' => $openShiftsTable,
        ],
        'field' => $fieldSupervision,
    ];
@endphp

<x-company-layout title="Mi empresa">
    @push('styles')
    <style>
        .company-cc {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            width: 100%;
            max-width: 100%;
            min-width: 0;
        }
        .company-cc-row {
            display: grid;
            gap: 0.75rem;
            width: 100%;
            min-width: 0;
        }
        .company-cc-row-1 {
            grid-template-columns: minmax(0, 1fr);
            height: auto;
        }
        .company-cc-row-2 {
            grid-template-columns: minmax(0, 1fr);
            height: auto;
        }
        .company-cc-row-3 {
            grid-template-columns: minmax(0, 1fr);
            height: auto;
        }
        /* Tablet: mapa full; side en 2 columnas */
        @media (min-width: 768px) and (max-width: 1023px) {
            .company-cc { gap: 0.875rem; }
            .company-cc-row { gap: 0.875rem; }
            .company-cc-row-1 .company-cc-card:first-child { height: 380px; }
            .company-cc-side {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                grid-template-rows: auto;
                height: auto;
            }
            .company-cc-side .company-cc-card { height: 200px; }
            .company-cc-row-2 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .company-cc-row-2 .company-cc-card { height: 240px; }
            .company-cc-row-2 .company-cc-card:last-child {
                grid-column: 1 / -1;
                height: 220px;
            }
            .company-cc-row-3 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .company-cc-row-3 .company-cc-card { height: 240px; }
        }
        /* Desktop */
        @media (min-width: 1024px) {
            .company-cc { gap: 0.875rem; }
            .company-cc-row { gap: 0.875rem; }
            .company-cc-row-1 {
                grid-template-columns: minmax(0, 1.75fr) minmax(280px, 1fr);
                height: 440px;
            }
            .company-cc-row-2 {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                height: 270px;
            }
            .company-cc-row-3 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                height: 280px;
            }
            .company-cc-side {
                grid-template-columns: minmax(0, 1fr);
                grid-template-rows: minmax(0, 1fr) minmax(0, 1.15fr);
                height: 100%;
            }
        }
        /* XL */
        @media (min-width: 1280px) {
            .company-cc { gap: 1rem; }
            .company-cc-row { gap: 1rem; }
            .company-cc-row-1 {
                grid-template-columns: minmax(0, 2.05fr) minmax(300px, 0.95fr);
                height: 460px;
            }
            .company-cc-row-2 { height: 280px; }
            .company-cc-row-3 { height: 290px; }
            .company-map-search-input { width: min(240px, 28vw); }
        }
        /* 2XL */
        @media (min-width: 1536px) {
            .company-cc-row-1 {
                grid-template-columns: minmax(0, 2.25fr) minmax(320px, 0.85fr);
                height: 500px;
            }
            .company-cc-row-2 { height: 300px; }
            .company-cc-row-3 { height: 310px; }
            .company-map-search-input { width: min(280px, 22vw); }
        }
        .company-cc-card {
            display: flex;
            flex-direction: column;
            min-width: 0;
            min-height: 0;
            height: 100%;
            border-radius: 0.5rem;
            border: 1px solid rgb(30 41 59);
            background: rgba(15, 23, 42, 0.85);
            overflow: hidden;
        }
        .company-cc-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            flex-shrink: 0;
            flex-wrap: wrap;
            padding: 0.625rem 0.75rem;
            border-bottom: 1px solid rgb(30 41 59);
        }
        .company-cc-card-body {
            flex: 1 1 auto;
            min-height: 0;
            overflow: hidden;
            padding: 0.75rem;
        }
        .company-cc-card-body-flush {
            flex: 1 1 auto;
            min-height: 0;
            overflow: auto;
            padding: 0;
        }
        .company-cc-side {
            display: grid;
            grid-template-rows: minmax(0, 1fr) minmax(0, 1.15fr);
            gap: inherit;
            min-height: 0;
            height: 100%;
            min-width: 0;
        }
        .company-map-shell {
            position: relative;
            width: 100%;
            height: 100%;
            background: #020617;
        }
        .company-map-shell #company-map,
        .company-map-shell #company-map-svg {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }
        .company-map-head-tools {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 0;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .company-map-search-input {
            width: min(200px, 42vw);
            min-width: 8rem;
        }
        .company-map-bubble {
            position: absolute;
            z-index: 30;
            width: min(252px, calc(100% - 1.25rem));
            transform: translate(-50%, calc(-100% - 16px));
            border-radius: 0.65rem;
            border: 1px solid rgba(148, 163, 184, 0.22);
            background: rgba(2, 6, 23, 0.96);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.5);
            padding: 0.65rem 0.7rem 0.7rem;
            pointer-events: auto;
            color: #e2e8f0;
            font-family: ui-sans-serif, system-ui, sans-serif;
        }
        .company-map-bubble::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: -6px;
            width: 11px;
            height: 11px;
            transform: translateX(-50%) rotate(45deg);
            background: rgba(2, 6, 23, 0.96);
            border-right: 1px solid rgba(148, 163, 184, 0.22);
            border-bottom: 1px solid rgba(148, 163, 184, 0.22);
        }
        .company-map-bubble-close {
            position: absolute;
            top: 0.4rem;
            right: 0.4rem;
            z-index: 2;
            width: 1.35rem;
            height: 1.35rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
            color: #f1f5f9;
            font-size: 0.85rem;
            line-height: 1;
            background: #334155;
            border: 1px solid #64748b;
            cursor: pointer;
        }
        .company-map-bubble-close:hover {
            color: #fff;
            background: #475569;
        }
        .company-iw-title {
            margin: 0 1.6rem 0.2rem 0;
            font-size: 0.8rem;
            font-weight: 650;
            color: #fff;
            line-height: 1.2;
        }
        .company-iw-badge {
            display: inline-flex;
            border-radius: 9999px;
            padding: 0.1rem 0.45rem;
            font-size: 0.62rem;
            font-weight: 600;
            background: rgba(251, 191, 36, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(251, 191, 36, 0.25);
        }
        .company-iw-badge[data-tone="ok"] {
            background: rgba(52, 211, 153, 0.14);
            color: #34d399;
            border-color: rgba(52, 211, 153, 0.25);
        }
        .company-iw-badge[data-tone="danger"] {
            background: rgba(248, 113, 113, 0.14);
            color: #f87171;
            border-color: rgba(248, 113, 113, 0.25);
        }
        .company-iw-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.35rem 0.55rem;
            margin-top: 0.55rem;
            padding-top: 0.5rem;
            border-top: 1px solid rgba(51, 65, 85, 0.8);
        }
        .company-iw-metric {
            min-width: 0;
        }
        .company-iw-metric span {
            display: block;
            font-size: 0.6rem;
            color: #94a3b8;
            line-height: 1.2;
        }
        .company-iw-metric strong {
            display: block;
            margin-top: 0.1rem;
            font-size: 0.72rem;
            color: #f8fafc;
            font-weight: 650;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .company-iw-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.4rem;
            margin-top: 0.55rem;
        }
        .company-iw-actions a,
        .company-iw-actions button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 1.85rem;
            border-radius: 0.4rem;
            padding: 0.3rem 0.4rem;
            font-size: 0.68rem;
            font-weight: 650;
            text-decoration: none;
            border: 0;
            cursor: pointer;
        }
        .company-iw-ver { background: rgba(99, 102, 241, 0.22); color: #c7d2fe; }
        .company-iw-ver:hover { background: rgba(99, 102, 241, 0.34); }
        .company-iw-operar { background: rgba(16, 185, 129, 0.22); color: #a7f3d0; width: 100%; }
        .company-iw-operar:hover { background: rgba(16, 185, 129, 0.34); }
        .company-svg-label {
            fill: #e2e8f0;
            font-size: 3.2px;
            font-family: ui-sans-serif, system-ui, sans-serif;
            pointer-events: none;
        }
        .company-cc-chart {
            position: relative;
            width: 100%;
            height: 100%;
            min-height: 0;
        }
        .company-alert-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.5rem;
            height: 100%;
        }
        .company-alert-tile {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 0;
            border-radius: 0.5rem;
            border: 1px solid rgb(30 41 59);
            padding: 0.625rem 0.75rem;
        }
        .company-portfolio-list {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.5rem;
            height: 100%;
            align-content: space-evenly;
        }
        .company-workforce-list {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.5rem;
            height: 100%;
            align-content: space-evenly;
        }
        /* Móvil */
        @media (max-width: 767px) {
            .company-cc-row-1,
            .company-cc-row-2,
            .company-cc-row-3 {
                height: auto;
            }
            .company-cc-row-1 .company-cc-card:first-child {
                height: 320px;
            }
            .company-cc-side {
                height: auto;
                grid-template-columns: minmax(0, 1fr);
                grid-template-rows: auto auto;
            }
            .company-cc-side .company-cc-card {
                height: 180px;
            }
            .company-cc-row-2 .company-cc-card,
            .company-cc-row-3 .company-cc-card {
                height: 220px;
            }
            .company-map-head-tools {
                width: 100%;
                justify-content: stretch;
            }
            .company-map-search-input {
                flex: 1 1 auto;
                width: auto;
                min-width: 0;
            }
        }
    </style>
    @endpush

    <div class="company-cc"
         x-data="companyDashboardLive"
         data-live-url="{{ $dashboardLiveUrl }}"
         data-payload='@json($dashboardLivePayload)'>
        {{-- Fila 1: Mapa + Cartera / Alertas --}}
        <div class="company-cc-row company-cc-row-1">
            <div class="company-cc-card">
                <div class="company-cc-card-head">
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-white">Mapa de conjuntos</h3>
                        <p class="text-xs text-slate-500">
                            <span x-text="portfolio('with_geo')">{{ $portfolio['with_geo'] ?? 0 }}</span>/<span x-text="portfolio('active_total')">{{ $portfolio['active_total'] ?? 0 }}</span> con ubicación
                        </p>
                    </div>
                    <div class="company-map-head-tools">
                        <div class="inline-flex rounded-md border border-slate-700 bg-slate-950/80 p-0.5 text-xs" role="group" aria-label="Tipo de mapa">
                            <button type="button" id="company-map-type-satellite" class="company-map-type-btn rounded px-2 py-1 font-medium text-white bg-indigo-600/80" data-map-type="satellite">
                                Satélite
                            </button>
                            <button type="button" id="company-map-type-terrain" class="company-map-type-btn rounded px-2 py-1 font-medium text-slate-400 hover:text-slate-200" data-map-type="terrain">
                                Terreno
                            </button>
                        </div>
                        <input
                            id="company-map-search"
                            type="search"
                            placeholder="Buscar conjunto..."
                            class="company-map-search-input rounded-md border border-slate-700 bg-slate-950/80 px-2.5 py-1.5 text-xs text-slate-200 placeholder:text-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                    </div>
                </div>
                <div class="company-cc-card-body-flush relative">
                    <div class="company-map-shell" data-csrf="{{ csrf_token() }}">
                        <div id="company-map" class="hidden"></div>
                        <svg id="company-map-svg" viewBox="0 0 100 100" preserveAspectRatio="none" class="opacity-90"></svg>
                        <div id="company-map-empty" class="absolute inset-0 flex items-center justify-center text-center p-4 text-xs text-slate-500 hidden">
                            Sin clientes ni instalaciones con coordenadas
                        </div>
                        <div id="company-map-bubble" class="company-map-bubble hidden" role="dialog" aria-label="Detalle del conjunto"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-x-3 gap-y-1 px-3 py-2 text-[11px] text-slate-400 leading-tight">
                        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-indigo-500 ring-1 ring-slate-900"></span>Cliente</span>
                        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-cyan-400 ring-1 ring-slate-900"></span>Instalación</span>
                    </div>
                </div>
            </div>

            <div class="company-cc-side">
                <div class="company-cc-card">
                    <div class="company-cc-card-head">
                        <h3 class="text-sm font-semibold text-white">Cartera de clientes</h3>
                        @can('company.billing.manage')
                            <a href="{{ route('company.billing.index') }}" class="ui-chip-link">Facturación</a>
                        @endcan
                    </div>
                    <div class="company-cc-card-body">
                        <div class="company-portfolio-list text-sm">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-slate-500">Plan activo</span>
                                <span class="font-medium {{ $statusColor }} truncate max-w-[60%] text-right">{{ $metrics['package_label'] ?? '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-slate-500">Activos</span>
                                <span class="font-semibold text-white tabular-nums" x-text="portfolio('active_total')">{{ $portfolio['active_total'] ?? 0 }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-slate-500">Archivados</span>
                                <span class="font-semibold text-slate-300 tabular-nums" x-text="portfolio('archived')">{{ $portfolio['archived'] ?? 0 }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-slate-500">Disponibles Accesos</span>
                                <span class="font-semibold text-indigo-300 tabular-nums" x-text="metrics.clients_remaining ?? portfolio('available')">{{ $metrics['clients_remaining'] ?? $portfolio['available'] ?? 0 }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-slate-500">Disponibles Supervisión</span>
                                <span class="font-semibold text-amber-300 tabular-nums" x-text="metrics.supervision_remaining_label ?? metrics.supervision_remaining ?? 0">{{ $metrics['supervision_remaining_label'] ?? $metrics['supervision_remaining'] ?? 0 }}</span>
                            </div>
                            @if ($endsAt)
                                <p class="text-xs text-slate-600 pt-1 border-t border-slate-800">
                                    Renueva {{ $endsAt->format('d M Y') }}
                                    @if ($daysLeft !== null && $daysLeft >= 0)
                                        · {{ $daysLeft }} d
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="company-cc-card">
                    <div class="company-cc-card-head">
                        <h3 class="text-sm font-semibold text-white">Resumen de alertas y registros (hoy)</h3>
                    </div>
                    <div class="company-cc-card-body">
                        <div class="company-alert-grid">
                            <div class="company-alert-tile bg-indigo-500/10 border-indigo-500/30">
                                <p class="text-xs text-indigo-300">Novedades</p>
                                <p class="text-2xl font-semibold text-white tabular-nums leading-none" x-text="kpi('novedades_today')">{{ $novedades }}</p>
                                <p class="text-[10px] text-slate-500">Minuta</p>
                            </div>
                            <div class="company-alert-tile bg-amber-500/10 border-amber-500/30">
                                <p class="text-xs text-amber-300">Correspondencia</p>
                                <p class="text-2xl font-semibold text-white tabular-nums leading-none" x-text="kpi('pending_correspondence')">{{ $corrPending }}</p>
                                <p class="text-[10px] text-slate-500">Pendiente</p>
                            </div>
                            <div class="company-alert-tile bg-red-500/10 border-red-500/30">
                                <p class="text-xs text-red-300">Pánico</p>
                                <p class="text-2xl font-semibold text-white tabular-nums leading-none" x-text="kpi('panics_open')">{{ $panicOpen }}</p>
                                <p class="text-[10px] text-slate-500">Abiertos</p>
                            </div>
                            <div class="company-alert-tile bg-teal-500/10 border-teal-500/30">
                                <p class="text-xs text-teal-300">Bloqueos</p>
                                <p class="text-2xl font-semibold text-white tabular-nums leading-none" x-text="kpi('blocklist_total')">{{ $blockTotal }}</p>
                                <p class="text-[10px] text-slate-500">Activos</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($fieldSupervision)
        <div class="company-cc-card" style="height: auto;">
            <div class="company-cc-card-head">
                <h3 class="text-sm font-semibold text-white">Supervisión de campo (hoy)</h3>
                @can('company.supervision.view')
                    <a href="{{ route('company.supervision.index', ['tab' => 'summary']) }}" class="ui-chip-link">Resumen e informe</a>
                @endcan
            </div>
            <div class="company-cc-card-body">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 text-sm">
                    <div class="rounded-md border border-slate-800 bg-slate-950/40 px-3 py-2">
                        <p class="text-[10px] text-slate-500 uppercase">En ruta</p>
                        <p class="text-lg font-semibold text-white tabular-nums" x-text="fieldVal('open_shifts')">{{ $fieldSupervision['open_shifts'] }}</p>
                    </div>
                    <div class="rounded-md border border-slate-800 bg-slate-950/40 px-3 py-2">
                        <p class="text-[10px] text-slate-500 uppercase">Revistas app</p>
                        <p class="text-lg font-semibold text-amber-300 tabular-nums" x-text="fieldVal('reviews_today')">{{ $fieldSupervision['reviews_today'] }}</p>
                    </div>
                    <div class="rounded-md border border-slate-800 bg-slate-950/40 px-3 py-2">
                        <p class="text-[10px] text-slate-500 uppercase">Cobertura</p>
                        <p class="text-lg font-semibold text-white tabular-nums">
                            <span x-text="field && field.coverage_pct !== null ? field.coverage_pct + '%' : '—'">{{ $fieldSupervision['coverage_pct'] !== null ? $fieldSupervision['coverage_pct'].'%' : '—' }}</span>
                        </p>
                        <p class="text-[10px] text-slate-600"><span x-text="fieldVal('sites_visited')">{{ $fieldSupervision['sites_visited'] }}</span>/<span x-text="fieldVal('sites_contracted')">{{ $fieldSupervision['sites_contracted'] }}</span></p>
                    </div>
                    <div class="rounded-md border border-slate-800 bg-slate-950/40 px-3 py-2">
                        <p class="text-[10px] text-slate-500 uppercase">Recomendaciones</p>
                        <p class="text-lg font-semibold tabular-nums" :class="fieldVal('recommendations_today') > 0 ? 'text-amber-300' : 'text-white'" x-text="fieldVal('recommendations_today')">{{ $fieldSupervision['recommendations_today'] }}</p>
                    </div>
                    <div class="rounded-md border border-slate-800 bg-slate-950/40 px-3 py-2">
                        <p class="text-[10px] text-slate-500 uppercase">Atenciones</p>
                        <p class="text-lg font-semibold tabular-nums" :class="fieldVal('attention_today') > 0 ? 'text-red-300' : 'text-white'" x-text="fieldVal('attention_today')">{{ $fieldSupervision['attention_today'] }}</p>
                    </div>
                    <div class="rounded-md border border-slate-800 bg-slate-950/40 px-3 py-2">
                        <p class="text-[10px] text-slate-500 uppercase">Km hoy</p>
                        <p class="text-lg font-semibold text-white tabular-nums" x-text="fieldVal('km_today')">{{ $fieldSupervision['km_today'] }}</p>
                    </div>
                </div>
                <p class="text-[11px] text-slate-600 mt-2">
                    Distinto de las revistas de portería (gráficas de abajo). Aquí solo cuenta la app de Supervisión.
                    @if (($fieldSupervision['posts_count'] ?? 0) > 0)
                        · {{ $fieldSupervision['posts_count'] }} puesto{{ $fieldSupervision['posts_count'] === 1 ? '' : 's' }} activos.
                    @endif
                </p>
            </div>
        </div>
        @endif

        {{-- Fila 2: Fuerza laboral | Accesos | Turnos --}}
        <div class="company-cc-row company-cc-row-2">
            <div class="company-cc-card">
                <div class="company-cc-card-head">
                    <h3 class="text-sm font-semibold text-white">Fuerza laboral actual</h3>
                </div>
                <div class="company-cc-card-body">
                    <div class="company-workforce-list text-sm">
                        <div class="flex items-center justify-between gap-2 rounded-md border border-slate-800 bg-slate-950/40 px-3 py-2">
                            <span class="text-slate-400">Vigilantes</span>
                            <span class="font-semibold text-white tabular-nums" x-text="workforce('vigilantes_active')">{{ $workforce['vigilantes_active'] ?? 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-2 rounded-md border border-slate-800 bg-slate-950/40 px-3 py-2">
                            <span class="text-slate-400">En turno</span>
                            <span class="font-semibold text-indigo-300 tabular-nums" x-text="workforce('vigilantes_on_shift')">{{ $workforce['vigilantes_on_shift'] ?? 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-2 rounded-md border border-slate-800 bg-slate-950/40 px-3 py-2">
                            <span class="text-slate-400">Supervisores</span>
                            <span class="font-semibold text-white tabular-nums" x-text="workforce('supervisors_active')">{{ $workforce['supervisors_active'] ?? 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-2 rounded-md border border-slate-800 bg-slate-950/40 px-3 py-2">
                            <span class="text-slate-400">Sin asignación</span>
                            <span class="font-semibold tabular-nums" :class="workforce('without_assignment') > 0 ? 'text-amber-400' : 'text-white'" x-text="workforce('without_assignment')">{{ $sinAsign }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="company-cc-card">
                <div class="company-cc-card-head">
                    <h3 class="text-sm font-semibold text-white">Accesos por conjunto (hoy)</h3>
                </div>
                <div class="company-cc-card-body">
                    <div class="company-cc-chart">
                        <canvas id="companyAccessChart" aria-label="Accesos por conjunto"></canvas>
                    </div>
                </div>
            </div>

            <div class="company-cc-card">
                <div class="company-cc-card-head">
                    <h3 class="text-sm font-semibold text-white">Turnos abiertos · <span x-text="shifts().length">{{ $shiftsCount }}</span></h3>
                    <a href="{{ route('company.clients.index', ['modo' => 'operar']) }}" class="ui-chip-link">Ver detalle</a>
                </div>
                <div class="company-cc-card-body-flush">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-950/60 text-xs text-slate-500 sticky top-0">
                            <tr>
                                <th class="px-3 py-1.5 text-left font-medium">Puesto</th>
                                <th class="px-3 py-1.5 text-left font-medium">Vigilante</th>
                                <th class="px-3 py-1.5 text-left font-medium">Inicio</th>
                                <th class="px-3 py-1.5 text-left font-medium">Últ. revista</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <template x-for="(row, index) in shifts()" :key="index">
                                <tr class="hover:bg-slate-800/30">
                                    <td class="px-3 py-2 text-xs text-slate-300" x-text="row.puesto"></td>
                                    <td class="px-3 py-2 text-xs text-slate-400" x-text="row.vigilante"></td>
                                    <td class="px-3 py-2 text-xs text-slate-400" x-text="row.inicio"></td>
                                    <td class="px-3 py-2 text-xs" :class="row.tone === 'danger' ? 'text-red-300' : 'text-emerald-300'" x-text="row.ultima_revista"></td>
                                </tr>
                            </template>
                            <tr x-show="!shifts().length">
                                <td colspan="4" class="px-3 py-8 text-center text-xs text-slate-500">Sin turnos abiertos.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Fila 3: Revistas mes | Revistas semana --}}
        <div class="company-cc-row company-cc-row-3">
            <div class="company-cc-card">
                <div class="company-cc-card-head">
                    <h3 class="text-sm font-semibold text-white">Revistas mensuales</h3>
                </div>
                <div class="company-cc-card-body">
                    <div class="company-cc-chart">
                        <canvas id="companyRevistaMonthlyChart" aria-label="Revistas mensuales"></canvas>
                    </div>
                </div>
            </div>

            <div class="company-cc-card">
                <div class="company-cc-card-head">
                    <h3 class="text-sm font-semibold text-white">Revistas de supervisión (7 días)</h3>
                </div>
                <div class="company-cc-card-body">
                    <div class="company-cc-chart">
                        <canvas id="companyRevistaWeekChart" aria-label="Revistas de supervisión"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            const chartDefaults = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#94a3b8', boxWidth: 10, font: { size: 10 }, padding: 12 },
                    },
                },
                scales: {
                    x: {
                        ticks: { color: '#64748b', font: { size: 9 }, maxRotation: 0 },
                        grid: { color: '#1e293b' },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#64748b', precision: 0, font: { size: 9 } },
                        grid: { color: '#1e293b' },
                    },
                },
            };

            function stackedRevistaChart(el, payload) {
                if (!el) return;
                const labels = payload.labels?.length ? payload.labels : ['—'];
                new Chart(el, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: 'Meta',
                                data: payload.expected?.length ? payload.expected : [0],
                                backgroundColor: '#14b8a6',
                                borderRadius: 2,
                            },
                            {
                                label: 'Realizados',
                                data: payload.done?.length ? payload.done : [0],
                                backgroundColor: '#d6b07c',
                                borderRadius: 2,
                            },
                            {
                                label: 'Pendientes',
                                data: payload.pending?.length ? payload.pending : [0],
                                backgroundColor: '#64748b',
                                borderRadius: 2,
                            },
                        ],
                    },
                    options: {
                        ...chartDefaults,
                        scales: {
                            ...chartDefaults.scales,
                            x: { ...chartDefaults.scales.x, stacked: false },
                            y: { ...chartDefaults.scales.y, stacked: false },
                        },
                    },
                });
            }

            stackedRevistaChart(document.getElementById('companyRevistaMonthlyChart'), {!! $revistaMonthlyJson !!});
            stackedRevistaChart(document.getElementById('companyRevistaWeekChart'), {!! $revistaWeekJson !!});

            const accessRows = {!! $accessChartJson !!};
            const accessEl = document.getElementById('companyAccessChart');
            if (accessEl) {
                const accessLabels = accessRows.length
                    ? accessRows.map(r => r.label.length > 12 ? r.label.slice(0, 12) + '…' : r.label)
                    : ['—'];
                new Chart(accessEl, {
                    type: 'bar',
                    data: {
                        labels: accessLabels,
                        datasets: accessRows.length
                            ? [
                                { label: 'Vehículos', data: accessRows.map(r => r.vehicles), backgroundColor: '#8b5cf6', borderRadius: 2 },
                                { label: 'Visitantes', data: accessRows.map(r => r.visitors), backgroundColor: '#64748b', borderRadius: 2 },
                            ]
                            : [{ label: 'Accesos', data: [0], backgroundColor: '#334155' }],
                    },
                    options: chartDefaults,
                });
            }

            const mapMarkers = {!! $mapMarkersJson !!};
            const googleMaps = {!! $googleMapsJson !!};
            const mapEl = document.getElementById('company-map');
            const svgEl = document.getElementById('company-map-svg');
            const emptyEl = document.getElementById('company-map-empty');
            const bubbleEl = document.getElementById('company-map-bubble');
            const mapShell = document.querySelector('.company-map-shell');
            const searchEl = document.getElementById('company-map-search');
            const csrfToken = mapShell?.dataset.csrf || '';
            const toneColor = { ok: '#34d399', warn: '#fbbf24', danger: '#f87171', site: '#22d3ee' };
            let googleMap = null;
            let googleMarkers = [];
            let bubbleOverlay = null;
            let activePinId = null;
            let activePinLatLng = null;
            let ignoreMapClickUntil = 0;

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function shortLabel(title) {
                const t = String(title || '');
                return t.length > 18 ? t.slice(0, 17) + '…' : t;
            }

            function badgeTone(pin) {
                if (pin.tone === 'ok') return 'ok';
                if (pin.tone === 'danger') return 'danger';
                return 'warn';
            }

            function pinFill(pin) {
                if (pin.kind === 'installation') return '#22d3ee';
                return toneColor[pin.tone] || '#6366f1';
            }

            function bubbleHtml(pin) {
                if (pin.kind === 'installation') {
                    return `
                    <button type="button" class="company-map-bubble-close" data-bubble-close aria-label="Cerrar">×</button>
                    <p class="company-iw-title">${escapeHtml(pin.title)}</p>
                    <span class="company-iw-badge" data-tone="warn">${escapeHtml(pin.client_name || 'Instalación')}</span>
                    <div class="company-iw-actions">
                        <a class="company-iw-ver" href="${escapeHtml(pin.url)}">Ver ficha</a>
                    </div>
                    `;
                }
                return `
                    <button type="button" class="company-map-bubble-close" data-bubble-close aria-label="Cerrar">×</button>
                    <p class="company-iw-title">${escapeHtml(pin.title)}</p>
                    <span class="company-iw-badge" data-tone="${escapeHtml(badgeTone(pin))}">${escapeHtml(pin.tone_label || '—')}</span>
                    <div class="company-iw-grid">
                        <div class="company-iw-metric"><span>Revistas hoy</span><strong>${escapeHtml(pin.revistas_hoy)}</strong></div>
                        <div class="company-iw-metric"><span>Turno abierto</span><strong>${pin.turno_abierto ? 'Sí' : 'No'}</strong></div>
                        <div class="company-iw-metric"><span>Vehículos hoy</span><strong>${escapeHtml(pin.vehiculos_hoy)}</strong></div>
                        <div class="company-iw-metric"><span>Visitantes hoy</span><strong>${escapeHtml(pin.visitantes_hoy)}</strong></div>
                        <div class="company-iw-metric"><span>Inicio servicio</span><strong>${escapeHtml(pin.service_started || '—')}</strong></div>
                        <div class="company-iw-metric"><span>Últ. novedad</span><strong>${escapeHtml(pin.ultima_novedad || '—')}</strong></div>
                    </div>
                    <div class="company-iw-actions">
                        <a class="company-iw-ver" href="${escapeHtml(pin.url)}">Ver</a>
                        <form method="POST" action="${escapeHtml(pin.operate_url)}" style="margin:0">
                            <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                            <button type="submit" class="company-iw-operar">Operar</button>
                        </form>
                    </div>
                `;
            }

            function closeBubble() {
                if (!bubbleEl) return;
                bubbleEl.classList.add('hidden');
                bubbleEl.innerHTML = '';
                activePinId = null;
                activePinLatLng = null;
            }

            function placeBubbleAt(left, top) {
                if (!bubbleEl || !mapShell) return;
                const half = Math.min(126, mapShell.clientWidth / 2);
                const clampedLeft = Math.max(half + 8, Math.min(left, mapShell.clientWidth - half - 8));
                const clampedTop = Math.max(24, Math.min(top, mapShell.clientHeight - 8));
                bubbleEl.style.left = `${clampedLeft}px`;
                bubbleEl.style.top = `${clampedTop}px`;
            }

            function renderBubble(pin) {
                bubbleEl.innerHTML = bubbleHtml(pin);
                bubbleEl.classList.remove('hidden');
                bubbleEl.querySelector('[data-bubble-close]')?.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    closeBubble();
                });
            }

            function openBubble(pin, left, top, latLng) {
                if (!bubbleEl) return;
                if (activePinId === pin.id) {
                    closeBubble();
                    return;
                }
                ignoreMapClickUntil = Date.now() + 400;
                activePinId = pin.id;
                activePinLatLng = latLng || null;
                renderBubble(pin);
                placeBubbleAt(left, top);
            }

            function ensureBubbleOverlay() {
                if (bubbleOverlay || !google.maps?.OverlayView) return bubbleOverlay;
                class PinBubbleOverlay extends google.maps.OverlayView {
                    draw() {
                        if (!activePinLatLng || !bubbleEl || bubbleEl.classList.contains('hidden')) return;
                        const projection = this.getProjection();
                        if (!projection) return;
                        const point = projection.fromLatLngToContainerPixel(activePinLatLng);
                        if (!point) return;
                        placeBubbleAt(point.x, point.y);
                    }
                }
                bubbleOverlay = new PinBubbleOverlay();
                return bubbleOverlay;
            }

            function openGoogleBubble(pin) {
                if (!googleMap || !bubbleEl) return;
                if (activePinId === pin.id) {
                    closeBubble();
                    return;
                }

                ignoreMapClickUntil = Date.now() + 400;
                activePinId = pin.id;
                activePinLatLng = new google.maps.LatLng(pin.lat, pin.lng);
                renderBubble(pin);

                const overlay = ensureBubbleOverlay();
                if (overlay) {
                    overlay.setMap(googleMap);
                    // Proyección lista en el siguiente ciclo del mapa
                    google.maps.event.addListenerOnce(googleMap, 'idle', () => overlay.draw());
                    requestAnimationFrame(() => overlay.draw());
                    setTimeout(() => overlay.draw(), 50);
                } else {
                    placeBubbleAt(mapShell.clientWidth / 2, mapShell.clientHeight / 2);
                }
            }

            function normalizePins(pins) {
                if (!pins.length) return [];
                const lats = pins.map(p => p.lat);
                const lngs = pins.map(p => p.lng);
                const minLat = Math.min(...lats), maxLat = Math.max(...lats);
                const minLng = Math.min(...lngs), maxLng = Math.max(...lngs);
                const pad = 10;
                return pins.map(p => {
                    const x = maxLng === minLng ? 50 : pad + ((p.lng - minLng) / (maxLng - minLng)) * (100 - 2 * pad);
                    const y = maxLat === minLat ? 50 : pad + ((maxLat - p.lat) / (maxLat - minLat)) * (100 - 2 * pad);
                    return { ...p, nx: x, ny: y };
                });
            }

            function drawSvgMap(pins) {
                if (!svgEl) return;
                closeBubble();
                const norm = normalizePins(pins);
                let html = '<rect width="100" height="100" fill="#020617"/>';
                html += '<path d="M8 70 Q25 40 40 55 T70 35 T95 50" fill="none" stroke="#334155" stroke-width="0.4"/>';
                norm.forEach(pin => {
                    const c = toneColor[pin.tone] || '#6366f1';
                    const fill = pin.kind === 'installation' ? '#22d3ee' : c;
                    const key = pin.key || String(pin.id);
                    html += `<circle cx="${pin.nx}" cy="${pin.ny}" r="3.2" fill="${fill}" stroke="#0f172a" stroke-width="0.6" data-pin-key="${escapeHtml(key)}" style="cursor:pointer"/>`;
                    html += `<text class="company-svg-label" x="${pin.nx}" y="${pin.ny - 4.5}" text-anchor="middle">${escapeHtml(shortLabel(pin.title))}</text>`;
                });
                svgEl.innerHTML = html;
                svgEl.querySelectorAll('circle[data-pin-key]').forEach(node => {
                    const key = node.getAttribute('data-pin-key');
                    const pin = pins.find(p => (p.key || String(p.id)) === key);
                    const point = norm.find(p => (p.key || String(p.id)) === key);
                    if (pin && point) {
                        node.addEventListener('click', (e) => {
                            e.stopPropagation();
                            const left = (point.nx / 100) * svgEl.clientWidth;
                            const top = (point.ny / 100) * svgEl.clientHeight;
                            openBubble(pin, left, top, null);
                        });
                    }
                });
            }

            function filterMarkers(query) {
                const q = (query || '').trim().toLowerCase();
                return !q
                    ? mapMarkers
                    : mapMarkers.filter(p => (p.title || '').toLowerCase().includes(q)
                        || (p.client_name || '').toLowerCase().includes(q));
            }

            function clearGoogleOverlays() {
                googleMarkers.forEach(m => m.setMap(null));
                googleMarkers = [];
                closeBubble();
            }

            function applyFilter(query) {
                const filtered = filterMarkers(query);
                if (!googleMaps.api_key || !googleMap) {
                    drawSvgMap(filtered);
                    return;
                }
                clearGoogleOverlays();
                const bounds = new google.maps.LatLngBounds();
                filtered.forEach((pin) => {
                    const position = { lat: pin.lat, lng: pin.lng };
                    const marker = new google.maps.Marker({
                        position,
                        map: googleMap,
                        title: pin.title,
                        zIndex: 2,
                        optimized: false,
                        icon: {
                            path: google.maps.SymbolPath.CIRCLE,
                            scale: 8,
                            fillColor: pinFill(pin),
                            fillOpacity: 1,
                            strokeColor: '#0f172a',
                            strokeWeight: 2,
                        },
                    });
                    marker.addListener('click', (event) => {
                        if (event?.domEvent) {
                            event.domEvent.stopPropagation();
                            event.domEvent.preventDefault();
                        }
                        openGoogleBubble(pin);
                    });
                    googleMarkers.push(marker);
                    bounds.extend(position);
                });
                if (filtered.length === 1) {
                    googleMap.setCenter({ lat: filtered[0].lat, lng: filtered[0].lng });
                    googleMap.setZoom(14);
                } else if (filtered.length > 1) {
                    googleMap.fitBounds(bounds, 56);
                }
            }

            function initGoogleMap() {
                if (!googleMaps.api_key || !mapMarkers.length) return false;
                mapEl.classList.remove('hidden');
                svgEl.style.display = 'none';
                googleMap = new google.maps.Map(mapEl, {
                    center: googleMaps.center,
                    zoom: googleMaps.zoom,
                    mapTypeId: google.maps.MapTypeId.SATELLITE,
                    disableDefaultUI: true,
                    zoomControl: true,
                    mapTypeControl: false,
                    scrollwheel: true,
                    gestureHandling: 'greedy',
                });
                googleMap.addListener('click', () => {
                    if (Date.now() < ignoreMapClickUntil) return;
                    closeBubble();
                });
                googleMap.addListener('bounds_changed', () => bubbleOverlay?.draw());
                googleMap.addListener('zoom_changed', () => bubbleOverlay?.draw());
                applyFilter('');
                syncMapTypeButtons('satellite');
                return true;
            }

            function syncMapTypeButtons(type) {
                document.querySelectorAll('.company-map-type-btn').forEach((btn) => {
                    const active = btn.dataset.mapType === type;
                    btn.classList.toggle('bg-indigo-600/80', active);
                    btn.classList.toggle('text-white', active);
                    btn.classList.toggle('text-slate-400', !active);
                });
            }

            function setMapType(type) {
                if (!googleMap) return;
                const id = type === 'terrain'
                    ? google.maps.MapTypeId.TERRAIN
                    : google.maps.MapTypeId.SATELLITE;
                googleMap.setMapTypeId(id);
                syncMapTypeButtons(type === 'terrain' ? 'terrain' : 'satellite');
            }

            document.querySelectorAll('.company-map-type-btn').forEach((btn) => {
                btn.addEventListener('click', () => setMapType(btn.dataset.mapType || 'satellite'));
            });

            if (searchEl) {
                searchEl.addEventListener('input', () => applyFilter(searchEl.value));
            }
            svgEl?.addEventListener('click', closeBubble);
            bubbleEl?.addEventListener('click', (e) => e.stopPropagation());

            if (!mapMarkers.length) {
                emptyEl.classList.remove('hidden');
                svgEl.style.display = 'none';
            } else if (googleMaps.api_key) {
                window.initCompanyMap = () => initGoogleMap();
                const script = document.createElement('script');
                script.src = `https://maps.googleapis.com/maps/api/js?key=${googleMaps.api_key}&callback=initCompanyMap`;
                script.async = true;
                script.defer = true;
                document.head.appendChild(script);
            } else {
                drawSvgMap(mapMarkers);
            }
        })();
    </script>
    @endpush
</x-company-layout>
