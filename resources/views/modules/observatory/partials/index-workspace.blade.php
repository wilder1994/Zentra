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
        'kinds' => ['labels' => [], 'values' => []], 'sources' => ['labels' => [], 'values' => []],
        'origins' => ['labels' => [], 'values' => []],
    ];
    $observatoryLiveUrl = $observatoryLiveUrl ?? null;
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
        'sources' => $board['sources'] ?? ['labels' => [], 'values' => []],
        'origins' => $board['origins'] ?? ['labels' => [], 'values' => []],
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
    .obs-kpi {
        position: relative;
        overflow: hidden;
        border-radius: 0.7rem;
        border: 1px solid rgb(30 41 59);
        background: linear-gradient(180deg, rgb(15 23 42 / 0.95), rgb(2 6 23 / 0.8));
        padding: 0.65rem 0.8rem 0.6rem;
        transition: border-color .15s ease;
    }
    .obs-kpi.is-on { border-color: rgb(245 158 11 / 0.45); }
    .obs-kpi::before {
        content: "";
        position: absolute; inset: 0 auto 0 0; width: 3px;
        background: var(--obs-tone, #64748b);
    }
    .obs-card {
        border-radius: 0.75rem;
        border: 1px solid rgb(30 41 59);
        background: linear-gradient(180deg, rgb(15 23 42 / 0.92), rgb(2 6 23 / 0.88));
        min-width: 0;
    }
    .obs-card-h { padding: 0.55rem 0.75rem 0; }
    .obs-card-h p { font-size: 10px; letter-spacing: .08em; text-transform: uppercase; color: #64748b; margin: 0; }
    .obs-card-h h3 { margin: .1rem 0 0; font-size: .85rem; font-weight: 600; color: #e2e8f0; }
    .obs-chart { height: 9.5rem; padding: .35rem .75rem .65rem; }
    .obs-chart-lg { height: 13.5rem; }
    .obs-gauge { height: auto; min-height: 10.5rem; display: flex; flex-direction: column; overflow: visible; }
    .obs-gauge-plot { height: 7.25rem; position: relative; overflow: visible; }
    html[data-theme="light"] .obs-kpi,
    html[data-theme="light"] .obs-card {
        background: var(--ui-surface);
        border-color: var(--ui-border);
        box-shadow: 0 1px 2px rgb(15 23 42 / 0.06);
    }
    html[data-theme="light"] .obs-card-h p { color: var(--ui-muted); }
    html[data-theme="light"] .obs-card-h h3 { color: var(--ui-text); }
    html[data-theme="light"] .obs-chip { color: #92400e; border-color: rgb(217 119 6 / 0.45); background: rgb(254 243 199 / 0.7); }
    .obs-risk-rank { max-height: 22.5rem; overflow: auto; }
    .obs-risk-rank thead th { position: sticky; top: 0; z-index: 1; background: rgb(15 23 42); }
    html[data-theme="light"] .obs-risk-rank thead th { background: var(--ui-surface); }
    .obs-risk-cyl { width: 16.5rem; height: 2.6rem; }
    .obs-trend-row { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
    .obs-origins, .obs-plot { width: 100%; height: 100%; }
    .obs-top { display: grid; gap: .5rem; align-items: stretch; }
    .obs-top-map, .obs-top-side, .obs-top-catalogs { min-width: 0; }
    @media (min-width: 768px) {
        .obs-top { grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); }
        .obs-top-map, .obs-top-side { height: 28rem; }
        .obs-top-catalogs { grid-column: 1 / -1; display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; align-items: stretch; }
        .obs-top-catalogs > section { height: 16rem; max-height: 16rem; overflow: hidden; display: flex; flex-direction: column; }
        .obs-top-catalogs > section > div { min-height: 0; flex: 1; overflow: auto; }
    }
    @media (min-width: 1024px) {
        .obs-top { grid-template-columns: minmax(0, 5fr) minmax(0, 3fr) minmax(0, 2fr); }
        .obs-top-catalogs {
            grid-column: auto;
            display: flex;
            flex-direction: column;
            height: 28rem;
            max-height: 28rem;
            overflow: hidden;
        }
        .obs-top-catalogs > section { flex: 1 1 0; height: auto; max-height: none; min-height: 0; }
    }
</style>
@endpush

<div class="obs-board space-y-3"
     @if ($observatoryLiveUrl)
         x-data="opsLivePage"
         data-live-url="{{ $observatoryLiveUrl }}"
         data-trail-url="{{ $observatoryTrailUrl ?? '' }}"
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
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
            @foreach ([
                [null, 'Eventos', $board['total'], $status === '', '#94a3b8'],
                ['nuevo', 'Nuevos', $board['nuevo'], $status === 'nuevo', '#f59e0b'],
                ['en_atencion', 'En atención', $board['en_atencion'], $status === 'en_atencion', '#818cf8'],
                ['cerrado', 'Cerrados', $board['cerrado'], $status === 'cerrado', '#34d399'],
            ] as [$value, $label, $count, $on, $tone])
                <a href="{{ $kpiUrl($value) }}" class="obs-kpi {{ $on ? 'is-on' : '' }}" style="--obs-tone: {{ $tone }}">
                    <p class="text-[10px] uppercase tracking-wide text-slate-500">{{ $label }}</p>
                    <p class="mt-0.5 text-2xl font-semibold tabular-nums text-white"
                       @if ($observatoryLiveUrl) x-text="board.{{ $value === null ? 'total' : $value }} ?? {{ (int) $count }}" @endif>{{ $count }}</p>
                </a>
            @endforeach
        </div>

        <div class="obs-top">
            <div class="obs-top-map flex min-h-80 flex-col">
            @include('modules.observatory.partials.map', [
                'map' => $map,
                'mapCanvasClass' => 'h-80 md:h-full md:min-h-0 md:flex-1',
                'trailUrl' => $observatoryTrailUrl ?? '',
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
                            <table class="w-full text-sm" x-show="shifts.length">
                                <thead>
                                    <tr class="text-[10px] uppercase tracking-wide text-slate-500 border-b border-slate-800">
                                        <th class="text-left py-1.5 pr-2 font-medium">Supervisor</th>
                                        <th class="text-left py-1.5 pr-2 font-medium">Estado</th>
                                        <th class="text-right py-1.5 font-medium">Km</th>
                                        <th class="text-right py-1.5 font-medium">Rev.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="row in shifts" :key="row.shift_id">
                                        <tr class="border-b border-slate-800/70 align-top cursor-pointer hover:bg-slate-800/60"
                                            :class="Number(selectedShiftId) === Number(row.shift_id) ? 'bg-indigo-950/80 ring-1 ring-inset ring-indigo-400' : ''"
                                            @click="pickShift(row.shift_id)">
                                            <td class="py-1.5 pr-2">
                                                <p class="text-[13px] font-medium text-slate-100 leading-tight" x-text="row.user || 'Supervisor'"></p>
                                                <p class="text-[11px] mt-0.5 font-semibold" :class="signalClass(row)" x-text="row.online_label || 'Sin señal'"></p>
                                                <p class="text-[10px] text-sky-500 mt-0.5 truncate" x-text="row.zone_label || 'Sin zona'"></p>
                                            </td>
                                            <td class="py-1.5 pr-2 text-[11px] text-slate-300 leading-snug" x-text="row.status_line || '—'"></td>
                                            <td class="py-1.5 text-right text-[11px] text-slate-300 tabular-nums" x-text="Number(row.km || 0).toFixed(1)"></td>
                                            <td class="py-1.5 text-right text-[11px] text-slate-300 tabular-nums" x-text="row.reviews_count || 0"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-sm text-slate-500">Nadie en turno ahora.</p>
                    @endif
                </div>
            </section>
            </div>
            <div class="obs-top-catalogs">
                @include('modules.observatory.partials.risk-board-catalogs', ['risk' => $risk ?? []])
            </div>
        </div>

        <div class="grid gap-2">
            <section class="obs-card p-3 flex flex-col min-h-0">
                <div class="shrink-0">
                    <h3 class="text-sm font-semibold text-white">Reportes</h3>
                    <p class="text-[11px] text-slate-500">Revista, alarma, apoyo y observatorio. Lo más nuevo arriba.</p>
                </div>
                <div class="mt-2 overflow-y-auto max-h-80 lg:max-h-[28rem] sidebar-scroll">
                    @if ($observatoryLiveUrl)
                        <div class="flex flex-col gap-1.5">
                            <template x-for="row in bubbles" :key="row.id">
                                <article class="rounded-xl border px-3 py-1.5" :class="bubbleClass(row)">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-300" x-text="row.kind_label"></p>
                                        <p class="text-[10px] text-slate-500 tabular-nums" x-text="row.at"></p>
                                    </div>
                                    <template x-for="line in row.lines" :key="line.label">
                                        <p class="text-[12px] text-slate-300 leading-snug"><span class="text-slate-500" x-text="line.label"></span> <span class="text-slate-100" x-text="line.value"></span></p>
                                    </template>
                                </article>
                            </template>
                            <p class="py-6 text-center text-sm text-slate-500" x-show="!bubbles.length">Sin reportes todavía.</p>
                        </div>
                    @endif
                </div>
            </section>
        </div>

        @if (! empty($risk))
            @include('modules.observatory.partials.risk-board', [
                'risk' => $risk,
                'showClientColumn' => $showClientColumn ?? false,
            ])
        @endif

        <div class="space-y-3" x-data="observatoryBoard(@js($charts))">
            <div class="obs-trend-row">
                <section class="obs-card">
                    <div class="obs-card-h">
                        <p>Tendencia endógena</p>
                        <h3>{{ $grain === 'year' ? 'Mes a mes' : 'Día a día' }}</h3>
                    </div>
                    <div class="obs-chart obs-chart-lg"><div class="obs-plot" x-ref="trend" role="img" aria-label="Tendencia endógena"></div></div>
                </section>
                <section class="obs-card">
                    <div class="obs-card-h">
                        <p>Picos</p>
                        <h3>Días con más reportes</h3>
                    </div>
                    <div class="obs-chart obs-chart-lg"><div class="obs-plot" x-ref="peaks" role="img" aria-label="Días con más reportes"></div></div>
                </section>
            </div>

        <div class="grid gap-2 md:grid-cols-2 lg:grid-cols-[1.15fr_1.15fr_0.85fr] lg:items-stretch">
            <section class="obs-card">
                <div class="obs-card-h">
                    <h3>Orígenes</h3>
                </div>
                <div class="obs-chart"><div class="obs-origins" data-origins='@json($charts['origins'])'></div></div>
            </section>
            <section class="obs-card">
                <div class="obs-card-h">
                    <h3>Fuentes</h3>
                </div>
                <div class="obs-chart"><div class="obs-plot" x-ref="sources" role="img" aria-label="Fuentes"></div></div>
            </section>
            <section class="obs-card">
                <div class="obs-card-h">
                    <p>Atención</p>
                    <h3>Atención de folios</h3>
                </div>
                <div class="obs-chart obs-gauge">
                    <div class="obs-gauge-plot">
                        <div class="obs-plot" x-ref="gauge" role="img" aria-label="Atención de folios"></div>
                        <p class="obs-gauge-value pointer-events-none absolute inset-x-0 top-[42%] text-center text-xl font-semibold tabular-nums text-white leading-none">{{ (int) ($board['load_rate'] ?? 0) }}%</p>
                    </div>
                    <div class="obs-gauge-legend shrink-0 grid grid-cols-3 gap-1 px-1 pt-1 text-[10px] leading-tight">
                        <p class="text-emerald-400">Cerrados <span class="font-semibold tabular-nums text-emerald-300" @if ($observatoryLiveUrl) x-text="payload.cerrado ?? {{ (int) ($board['cerrado'] ?? 0) }}" @endif>{{ (int) ($board['cerrado'] ?? 0) }}</span></p>
                        <p class="text-center text-amber-400">En trámite <span class="font-semibold tabular-nums text-amber-300" @if ($observatoryLiveUrl) x-text="payload.en_atencion ?? {{ (int) ($board['en_atencion'] ?? 0) }}" @endif>{{ (int) ($board['en_atencion'] ?? 0) }}</span></p>
                        <p class="text-right text-red-400">Total <span class="font-semibold tabular-nums text-red-300" @if ($observatoryLiveUrl) x-text="payload.total ?? {{ (int) ($board['total'] ?? 0) }}" @endif>{{ (int) ($board['total'] ?? 0) }}</span></p>
                    </div>
                </div>
            </section>
        </div>
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
