@php
    /** @var \App\Support\Observatory\ObservatoryFilters $filters */
    $vista = in_array($filters->vista, ['tablero', 'eventos'], true) ? $filters->vista : 'tablero';
    $status = $filters->status;
    $grain = $filters->grain();
    $options = $filterOptions ?? ['places' => [], 'types' => [], 'comunas' => []];
    $board = $board ?? [
        'total' => 0, 'nuevo' => 0, 'en_atencion' => 0, 'cerrado' => 0, 'closed_rate' => 0, 'load_rate' => 0,
        'trend' => ['labels' => ['—'], 'series' => []],
        'peaks' => ['labels' => ['—'], 'values' => [0]],
        'crimes' => ['labels' => [], 'values' => [], 'colors' => []],
        'sites' => ['rows' => []],
        'kinds' => ['labels' => [], 'values' => []], 'sources' => ['labels' => [], 'values' => []],
        'origins' => ['labels' => [], 'values' => []],
    ];
    $observatoryLiveUrl = $observatoryLiveUrl ?? null;
    $canPaintCharts = $canPaintCharts ?? false;
    $showClientColumn = $showClientColumn ?? false;
    $eventShowRoute = $eventShowRoute ?? 'client.observatory.events.show';
    $accent = $accent ?? 'teal';
    $linkClass = $accent === 'indigo' ? 'text-indigo-400 hover:text-indigo-300' : 'text-teal-400 hover:text-teal-300';
    $folioClass = $accent === 'indigo' ? 'text-indigo-300' : 'text-teal-300';
    $tabUrl = static function (string $tab, ?string $statusValue = null) use ($status): string {
        return request()->fullUrlWithQuery([
            'vista' => $tab,
            'status' => $tab === 'eventos' ? ($statusValue ?? $status) : ($statusValue ?? $status ?: null),
        ]);
    };
    $kpiUrl = static function (?string $value) use ($tabUrl): string {
        return $tabUrl('eventos', $value);
    };
    $charts = [
        'accent' => $accent,
        'total' => (int) ($board['total'] ?? 0),
        'nuevo' => (int) ($board['nuevo'] ?? 0),
        'en_atencion' => (int) ($board['en_atencion'] ?? 0),
        'cerrado' => (int) ($board['cerrado'] ?? 0),
        'closed_rate' => (int) ($board['closed_rate'] ?? 0),
        'load_rate' => (int) ($board['load_rate'] ?? 0),
        'trend' => $board['trend'] ?? ['labels' => ['—'], 'series' => []],
        'peaks' => $board['peaks'] ?? ['labels' => ['—'], 'values' => [0]],
        'crimes' => $board['crimes'] ?? ['labels' => [], 'values' => [], 'colors' => []],
        'sites' => $board['sites'] ?? ['rows' => []],
        'sources' => $board['sources'] ?? ['labels' => [], 'values' => []],
        'origins' => $board['origins'] ?? ['labels' => [], 'values' => []],
        'paints' => $board['paints'] ?? ['sources' => [], 'origins' => [], 'trend' => []],
        'can_paint' => $canPaintCharts,
        'paint_url' => $canPaintCharts ? route('company.observatory.charts.paint') : '',
    ];
    $liveEventRows = collect($events->items() ?? [])->map(static function ($event) use ($eventShowRoute, $showClientColumn): array {
        return [
            'id' => $event->id,
            'folio' => $event->folio(),
            'site' => $event->installation?->name,
            'client' => $showClientColumn ? $event->client?->name : null,
            'type' => $event->kindLabel(),
            'status' => $event->statusLabel(),
            'opened' => $event->opened_at?->format('d/m/Y H:i'),
            'url' => route($eventShowRoute, $event),
        ];
    })->values()->all();
@endphp

@push('styles')
<style>
    [x-cloak] { display: none !important; }
    .obs-board { --obs-line: {{ $accent === 'indigo' ? '#818cf8' : '#2dd4bf' }}; }
    .obs-card {
        border-radius: 0.75rem;
        border: 1px solid color-mix(in srgb, var(--ui-accent) 42%, var(--ui-border));
        background: color-mix(in srgb, var(--ui-accent) 9%, var(--ui-surface));
        min-width: 0;
    }
    .obs-card-h { padding: 0.55rem 0.75rem 0; }
    .obs-card-h p { font-size: 10px; letter-spacing: .08em; text-transform: uppercase; color: var(--ui-accent); margin: 0; }
    .obs-card-h h3 { margin: .1rem 0 0; font-size: .85rem; font-weight: 600; color: var(--ui-accent-hover); }
    .obs-title-row { display: flex; align-items: baseline; gap: .45rem; flex-wrap: wrap; }
    .obs-title-row h3 { margin: 0; color: var(--ui-text); }
    .obs-title-row span { font-size: 11px; font-weight: 600; color: var(--ui-accent); }
    .obs-card-tools { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
    .obs-title-row .obs-brush { margin-left: auto; }
    .obs-brush {
        width: 1.65rem; height: 1.65rem; border-radius: .4rem; display: grid; place-items: center;
        border: 1px solid color-mix(in srgb, var(--ui-accent) 45%, var(--ui-border));
        color: var(--ui-accent); background: color-mix(in srgb, var(--ui-accent) 10%, transparent);
    }
    .obs-brush svg { width: .95rem; height: .95rem; }
    .obs-brush-pop {
        position: absolute; right: .65rem; top: 2.15rem; z-index: 30; width: 14rem;
        padding: .55rem .65rem .7rem; border-radius: .65rem;
        border: 1px solid color-mix(in srgb, var(--ui-accent) 42%, var(--ui-border));
        background: var(--ui-surface); box-shadow: 0 12px 28px rgb(2 6 23 / .35);
    }
    .obs-brush-pop p { margin: 0 0 .4rem; font-size: 11px; font-weight: 700; color: var(--ui-accent); }
    .obs-brush-row { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .2rem 0; font-size: 12px; color: var(--ui-text); }
    .obs-brush-row input { width: 1.7rem; height: 1.35rem; padding: 0; border: 0; background: transparent; }
    .obs-chart-fill { flex: 1; min-height: 9rem; height: auto; display: flex; flex-direction: column; padding-bottom: .35rem; }
    .obs-chart-fill > .obs-rank-scroll { flex: 1; min-height: 0; }
    .obs-chart { height: 9.5rem; padding: .35rem .75rem .65rem; }
    .obs-chart-lg { height: 13.5rem; }
    .obs-gauge { height: auto; min-height: 10.5rem; display: flex; flex-direction: column; overflow: hidden; }
    .obs-gauge-plot { height: 7.25rem; position: relative; overflow: hidden; }
    .obs-card-fill { display: flex; flex-direction: column; }
    .obs-card-fill > .obs-chart { flex: 1; height: auto; min-height: 12rem; padding-bottom: .35rem; }
    .obs-card-fill > .obs-donut { display: flex; flex-direction: column; }
    .obs-card-fill .obs-gauge-plot,
    .obs-card-fill .obs-donut-plot { flex: 1; height: auto; min-height: 10rem; }
    .obs-card-fill .obs-gauge-legend { margin-top: auto; padding-top: .15rem; padding-bottom: .4rem; }
    .obs-odo {
        position: absolute; left: 50%; bottom: 1cm; transform: translateX(-50%);
        pointer-events: none; padding: .18rem; border-radius: .35rem;
        background: linear-gradient(180deg, #1e293b, #020617 55%, #0f172a);
        box-shadow: 0 2px 0 #334155, 0 6px 10px rgb(2 6 23 / 0.55), inset 0 1px 0 rgb(148 163 184 / 0.35);
    }
    .obs-odo-screen {
        display: flex; align-items: center; gap: .4rem; padding: .38rem .7rem; border-radius: .2rem;
        border: 1px solid #0284c7;
        background: radial-gradient(120% 140% at 50% 0%, rgb(14 165 233 / 0.28), rgb(2 6 23) 62%);
        box-shadow: inset 0 8px 12px rgb(2 6 23 / 0.85), inset 0 1px 0 rgb(125 211 252 / 0.55), 0 0 8px rgb(56 189 248 / 0.35);
        font-size: 1rem; font-weight: 700; line-height: 1; white-space: nowrap;
    }
    .obs-odo-kicker { color: #7dd3fc; text-shadow: 0 0 6px rgb(125 211 252 / 0.65); }
    .obs-odo-value { color: #f0f9ff; font-variant-numeric: tabular-nums; text-shadow: 0 0 8px rgb(224 242 254 / 0.8); }
    html[data-theme="light"] .obs-card {
        background: color-mix(in srgb, var(--ui-accent) 8%, var(--ui-surface));
        border-color: color-mix(in srgb, var(--ui-accent) 35%, var(--ui-border));
        box-shadow: 0 1px 2px rgb(15 23 42 / 0.06);
    }
    html[data-theme="light"] .obs-card-h p { color: var(--ui-muted); }
    html[data-theme="light"] .obs-card-h h3 { color: var(--ui-text); }
    html[data-theme="light"] .obs-chip { color: #92400e; border-color: rgb(217 119 6 / 0.45); background: rgb(254 243 199 / 0.7); }
    .obs-risk-rank { max-height: 22.5rem; overflow: auto; }
    .obs-risk-rank thead th { position: sticky; top: 0; z-index: 1; background: rgb(15 23 42); }
    html[data-theme="light"] .obs-risk-rank thead th { background: var(--ui-surface); }
    .obs-risk-cyl { width: 16.5rem; height: 2.6rem; }
    .obs-chart-row { display: grid; gap: .5rem; }
    .obs-rank-scroll { height: 100%; overflow-x: hidden; overflow-y: auto; }
    .obs-site-search {
        height: 1.75rem; width: 9.5rem; border-radius: .375rem;
        border: 1px solid color-mix(in srgb, var(--ui-accent) 45%, var(--ui-border));
        background: var(--ui-surface); color: var(--ui-text); font-size: 11px; padding: 0 .5rem;
    }
    html[data-theme="light"] .obs-site-search { background: #fff; color: var(--ui-text); border-color: var(--ui-border); }
    .obs-origins, .obs-plot { width: 100%; height: 100%; }
    .obs-top { display: grid; gap: .5rem; align-items: stretch; }
    .obs-top-map, .obs-top-side, .obs-top-feed { min-width: 0; }
    @media (min-width: 768px) {
        .obs-top { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
        .obs-top-map { grid-column: 1 / -1; height: 32.2rem; }
        .obs-top-side, .obs-top-feed { height: 25.3rem; }
    }
    @media (min-width: 1024px) {
        .obs-chart-row { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .obs-top { grid-template-columns: minmax(0, 3fr) minmax(0, 1fr) minmax(0, 1fr); }
        .obs-top-map { grid-column: auto; }
        .obs-top-map, .obs-top-side, .obs-top-feed { height: 32.2rem; }
    }
</style>
@endpush

<div class="obs-board space-y-3"
     @if ($observatoryLiveUrl)
         x-data="opsLivePage"
         data-live-url="{{ $observatoryLiveUrl }}"
         data-trail-url="{{ $observatoryTrailUrl ?? '' }}"
         data-positions-url="{{ $observatoryPositionsUrl ?? '' }}"
         data-show-client="{{ $showClientColumn ? '1' : '0' }}"
         data-events='@json($liveEventRows)'
         data-shifts='@json($map['shifts'] ?? [])'
         data-bubbles='@json($map['bubbles'] ?? [])'
         data-board='@json($charts)'
     @endif>
    <div class="space-y-3">
    @php
        $chips = [];
        $drop = static fn (array $keys): string => request()->fullUrlWithoutQuery(array_merge($keys, ['page']));
        if ($filters->installationId !== null) {
            $site = collect($options['places'])->firstWhere('value', 'site:'.$filters->installationId);
            $chips[] = ['Instalación: '.($site['label'] ?? '#'.$filters->installationId), $drop(['installation_id', 'client_id'])];
        } elseif ($filters->clientId !== null && $showClientColumn) {
            $client = collect($options['places'])->firstWhere('value', 'client:'.$filters->clientId);
            $chips[] = ['Cliente: '.($client['label'] ?? '#'.$filters->clientId), $drop(['client_id'])];
        }
        if ($filters->status !== '') {
            $chips[] = ['Estado: '.(\App\Enums\ObservatoryEventStatus::tryFrom($filters->status)?->label() ?? $filters->status), $drop(['status'])];
        }
        if ($filters->type !== '') {
            $chips[] = ['Endógeno: '.(collect($options['types'])->firstWhere('value', $filters->type)['label'] ?? $filters->type), $drop(['type'])];
        }
        if ($filters->comuna !== '') {
            $chips[] = ['Comuna: '.(collect($options['comunas'])->firstWhere('code', $filters->comuna)['name'] ?? $filters->comuna), $drop(['comuna'])];
        }
        if ($filters->search !== '' && $vista === 'eventos') {
            $chips[] = ['Texto: '.$filters->search, $drop(['q'])];
        }
    @endphp
    @if ($chips !== [])
        <div class="flex flex-wrap items-center gap-1.5 text-xs">
            @foreach ($chips as [$label, $url])
                <a href="{{ $url }}" class="obs-chip inline-flex items-center gap-1.5 rounded-full border border-amber-500/40 bg-amber-500/10 px-2.5 py-1 text-amber-200 hover:bg-amber-500/20">
                    {{ $label }} <span aria-hidden="true">×</span>
                </a>
            @endforeach
            <a href="{{ request()->fullUrlWithoutQuery(['client_id', 'installation_id', 'status', 'type', 'comuna', 'q', 'page']) }}"
               class="rounded-full px-2 py-1 text-slate-400 hover:text-white">Limpiar todo</a>
        </div>
    @endif
    @if ($vista === 'tablero')
        @php
            $kpis = [
                ['key' => 'total', 'label' => 'Eventos', 'count' => (int) $board['total'], 'on' => $status === '', 'tone' => '#94a3b8', 'url' => $kpiUrl(null)],
                ['key' => 'nuevo', 'label' => 'Nuevos', 'count' => (int) $board['nuevo'], 'on' => $status === 'nuevo', 'tone' => '#f59e0b', 'url' => $kpiUrl('nuevo')],
                ['key' => 'en_atencion', 'label' => 'En atención', 'count' => (int) $board['en_atencion'], 'on' => $status === 'en_atencion', 'tone' => '#818cf8', 'url' => $kpiUrl('en_atencion')],
                ['key' => 'cerrado', 'label' => 'Cerrados', 'count' => (int) $board['cerrado'], 'on' => $status === 'cerrado', 'tone' => '#34d399', 'url' => $kpiUrl('cerrado')],
            ];
        @endphp
        <div class="obs-top">
            <div class="obs-top-map flex min-h-80 flex-col">
            @include('modules.observatory.partials.map', [
                'map' => $map,
                'mapCanvasClass' => 'h-full min-h-0',
                'trailUrl' => $observatoryTrailUrl ?? '',
                'boardMap' => true,
                'kpis' => $kpis,
                'kpiLive' => (bool) $observatoryLiveUrl,
            ])
            </div>
            <div class="obs-top-side flex min-h-0 flex-col">
            <section class="obs-card p-2.5 flex h-full min-h-0 flex-1 flex-col overflow-hidden">
                <div class="shrink-0 max-h-[10.5rem] overflow-y-auto sidebar-scroll">
                    @include('modules.company.supervision.partials.pin-legend')
                </div>
                <div class="mt-2 border-t border-slate-800 pt-2 min-h-0 flex-1 flex flex-col overflow-hidden">
                    <h3 class="text-sm font-semibold text-white shrink-0">Supervisores en turno</h3>
                    <p class="text-[11px] text-slate-500 mb-1.5 shrink-0">Clic pinta el recorrido. Otro clic lo quita.</p>
                    @if ($observatoryLiveUrl)
                        <div class="overflow-y-auto min-h-0 flex-1 sidebar-scroll">
                            <p class="text-sm text-slate-500" x-show="!shifts.length">Nadie en turno ahora.</p>
                            <div class="flex flex-col" x-show="shifts.length">
                                <template x-for="row in shifts" :key="row.shift_id">
                                    <button type="button"
                                            class="border-b border-slate-800/70 px-1 py-1.5 text-left hover:bg-slate-800/60"
                                            :class="Number(selectedShiftId) === Number(row.shift_id) ? 'bg-indigo-950/80 ring-1 ring-inset ring-indigo-400' : ''"
                                            @click="pickShift(row.shift_id)">
                                        <span class="flex items-baseline justify-between gap-2">
                                            <span class="min-w-0 truncate text-[13px] font-medium leading-tight text-slate-100" x-text="row.user || 'Supervisor'"></span>
                                            <span class="shrink-0 text-[10px] tabular-nums text-slate-400">
                                                <span x-text="Number(row.km || 0).toFixed(1)"></span> km · <span x-text="row.reviews_count || 0"></span>
                                            </span>
                                        </span>
                                        <span class="mt-0.5 flex min-w-0 items-center gap-1 text-[11px] leading-tight">
                                            <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="signalDot(row)"></span>
                                            <span class="shrink-0 font-semibold" :class="signalClass(row)" x-text="row.online_label || 'Sin señal'"></span>
                                            <span class="text-slate-600">·</span>
                                            <span class="min-w-0 truncate text-sky-500" x-text="zoneName(row)"></span>
                                        </span>
                                        <span class="mt-0.5 block text-[11px] leading-tight text-slate-300" x-text="shiftStatus(row)"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-slate-500">Nadie en turno ahora.</p>
                    @endif
                </div>
            </section>
            </div>
            <div class="obs-top-feed flex min-h-0 flex-col">
            <section class="obs-card p-3 flex h-full min-h-0 flex-col">
                <div class="shrink-0">
                    <h3 class="text-sm font-semibold text-white">Reportes</h3>
                    <p class="text-[11px] text-slate-500">Revista, alarma, apoyo y observatorio. Lo más nuevo arriba.</p>
                </div>
                <div class="mt-2 min-h-0 flex-1 overflow-y-auto sidebar-scroll">
                    @if ($observatoryLiveUrl)
                        <div class="flex flex-col gap-1.5">
                            <template x-for="row in bubbles" :key="row.id">
                                <article class="rounded-xl border px-3 py-1.5" :class="bubbleClass(row)">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-300" x-text="row.kind_label"></p>
                                        <p class="text-[10px] text-slate-500 tabular-nums" x-text="row.at"></p>
                                    </div>
                                    <p class="text-[12px] leading-snug text-slate-200" x-text="row.text"></p>
                                </article>
                            </template>
                            <p class="py-6 text-center text-sm text-slate-500" x-show="!bubbles.length">Sin reportes todavía.</p>
                        </div>
                    @endif
                </div>
            </section>
            </div>
        </div>

        <div class="space-y-3" x-data="observatoryBoard(@js($charts))">
            <div class="obs-chart-row">
                <section class="obs-card obs-card-fill">
                    <div class="obs-card-h">
                        <h3>Atención de folios <span class="tabular-nums" @if ($observatoryLiveUrl) x-text="payload.total ?? {{ (int) ($board['total'] ?? 0) }}" @endif>{{ (int) ($board['total'] ?? 0) }}</span></h3>
                    </div>
                    <div class="obs-chart obs-gauge">
                        <div class="obs-gauge-plot">
                            <div class="obs-plot" x-ref="gauge" role="img" aria-label="Atención de folios"></div>
                            <div class="obs-odo">
                                <div class="obs-odo-screen">
                                    <span class="obs-odo-kicker">Gestión</span>
                                    <span class="obs-odo-value" @if ($observatoryLiveUrl) x-text="(payload.load_rate ?? {{ (int) ($board['load_rate'] ?? 0) }}) + '%'" @endif>{{ (int) ($board['load_rate'] ?? 0) }}%</span>
                                </div>
                            </div>
                        </div>
                        <div class="obs-gauge-legend shrink-0 grid grid-cols-3 gap-1 px-1 pt-1 text-[10px] leading-tight">
                            <p class="text-emerald-400">Cerrados <span class="font-semibold tabular-nums text-emerald-300" @if ($observatoryLiveUrl) x-text="payload.cerrado ?? {{ (int) ($board['cerrado'] ?? 0) }}" @endif>{{ (int) ($board['cerrado'] ?? 0) }}</span></p>
                            <p class="text-center text-amber-400">En trámite <span class="font-semibold tabular-nums text-amber-300" @if ($observatoryLiveUrl) x-text="payload.en_atencion ?? {{ (int) ($board['en_atencion'] ?? 0) }}" @endif>{{ (int) ($board['en_atencion'] ?? 0) }}</span></p>
                            <p class="text-right text-red-400">Nuevos <span class="font-semibold tabular-nums text-red-300" @if ($observatoryLiveUrl) x-text="payload.nuevo ?? {{ (int) ($board['nuevo'] ?? 0) }}" @endif>{{ (int) ($board['nuevo'] ?? 0) }}</span></p>
                        </div>
                    </div>
                </section>
                <section class="obs-card obs-card-fill">
                    <div class="obs-card-h obs-card-tools">
                        <h3>Fuentes</h3>
                        @include('modules.observatory.partials.chart-brush', ['chart' => 'sources', 'title' => 'Fuentes'])
                    </div>
                    <div class="obs-chart obs-donut"><div class="obs-plot obs-donut-plot" x-ref="sources" role="img" aria-label="Fuentes"></div></div>
                </section>
                <section class="obs-card obs-card-fill">
                    <div class="obs-card-h obs-card-tools">
                        <h3>Orígenes</h3>
                        @include('modules.observatory.partials.chart-brush', ['chart' => 'origins', 'title' => 'Orígenes'])
                    </div>
                    <div class="obs-chart obs-chart-fill">
                        <div class="obs-rank-scroll"><div class="obs-origins" x-ref="origins" role="img" aria-label="Orígenes"></div></div>
                    </div>
                </section>
            </div>
            <div class="obs-chart-row">
                <section class="obs-card obs-card-fill">
                    <div class="obs-card-h obs-title-row">
                        <h3>Tendencia</h3>
                        <span>{{ $grain === 'year' ? 'Por mes' : 'Por día' }}</span>
                        @include('modules.observatory.partials.chart-brush', ['chart' => 'trend', 'title' => 'Tendencia'])
                    </div>
                    <div class="obs-chart obs-chart-lg"><div class="obs-plot" x-ref="trend" role="img" aria-label="Tendencia"></div></div>
                </section>
                <section class="obs-card obs-card-fill">
                    <div class="obs-card-h obs-title-row">
                        <h3>Delitos</h3>
                        <span>los más reportados</span>
                    </div>
                    <div class="obs-chart obs-chart-fill">
                        <div class="obs-rank-scroll"><div class="obs-plot" x-ref="crimes" role="img" aria-label="Delitos, los más reportados"></div></div>
                    </div>
                </section>
                <section class="obs-card obs-card-fill">
                    <div class="obs-card-h obs-title-row">
                        <h3>Reportes por instalación</h3>
                        <input type="search" class="obs-site-search ml-auto" placeholder="Buscar" autocomplete="off"
                               x-model="siteQuery" @input="paintSites()" @keydown.enter.prevent>
                    </div>
                    <div class="obs-chart obs-chart-fill">
                        <div class="obs-rank-scroll"><div class="obs-plot" x-ref="sites" role="img" aria-label="Reportes por instalación"></div></div>
                    </div>
                </section>
            </div>

            @if (! empty($risk))
                @include('modules.observatory.partials.risk-board', [
                    'risk' => $risk,
                    'showClientColumn' => $showClientColumn ?? false,
                ])
            @endif
        </div>
    @else
        <div class="rounded-xl border border-slate-800 overflow-hidden bg-slate-900/80">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-950/60 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-medium">Folio</th>
                        <th class="px-4 py-2.5 text-left font-medium">Instalación</th>
                        @if ($showClientColumn)
                            <th class="px-4 py-2.5 text-left font-medium hidden md:table-cell">Cliente</th>
                        @endif
                        <th class="px-4 py-2.5 text-left font-medium hidden sm:table-cell">Endógeno</th>
                        <th class="px-4 py-2.5 text-left font-medium">Estado</th>
                        <th class="px-4 py-2.5 text-left font-medium hidden lg:table-cell">Abierto</th>
                        <th class="px-4 py-2.5 text-right font-medium">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @if ($observatoryLiveUrl)
                        <template x-for="row in events" :key="row.id">
                            <tr class="hover:bg-slate-800/30">
                                <td class="px-4 py-3 font-mono text-xs {{ $folioClass }}" x-text="row.folio"></td>
                                <td class="px-4 py-3 text-slate-200" x-text="row.site"></td>
                                @if ($showClientColumn)
                                    <td class="px-4 py-3 hidden md:table-cell text-slate-400" x-text="row.client"></td>
                                @endif
                                <td class="px-4 py-3 hidden sm:table-cell text-slate-300" x-text="row.type"></td>
                                <td class="px-4 py-3 text-slate-300" x-text="row.status"></td>
                                <td class="px-4 py-3 hidden lg:table-cell text-slate-400 tabular-nums" x-text="row.opened"></td>
                                <td class="px-4 py-3 text-right">
                                    <a :href="row.url" class="text-xs {{ $linkClass }}">Ver</a>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!events.length">
                            <td colspan="{{ $showClientColumn ? 7 : 6 }}" class="px-4 py-10 text-center text-sm text-slate-500">Aún no hay reportes.</td>
                        </tr>
                    @else
                        @forelse ($events as $event)
                            <tr class="hover:bg-slate-800/30">
                                <td class="px-4 py-3 font-mono text-xs {{ $folioClass }}">{{ $event->folio() }}</td>
                                <td class="px-4 py-3 text-slate-200">{{ $event->installation?->name }}</td>
                                @if ($showClientColumn)
                                    <td class="px-4 py-3 hidden md:table-cell text-slate-400">{{ $event->client?->name }}</td>
                                @endif
                                <td class="px-4 py-3 hidden sm:table-cell text-slate-300">{{ $event->kindLabel() }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $event->statusLabel() }}</td>
                                <td class="px-4 py-3 hidden lg:table-cell text-slate-400 tabular-nums">{{ $event->opened_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route($eventShowRoute, $event) }}" class="text-xs {{ $linkClass }}">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $showClientColumn ? 7 : 6 }}" class="px-4 py-10 text-center text-sm text-slate-500">Aún no hay reportes.</td>
                            </tr>
                        @endforelse
                    @endif
                </tbody>
            </table>
        </div>
        @if ($events->hasPages())
            <div>{{ $events->links() }}</div>
        @endif
    @endif
    </div>
</div>
