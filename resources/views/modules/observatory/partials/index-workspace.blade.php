@php
    /** @var \App\Support\Observatory\ObservatoryFilters $filters */
    $vista = in_array($filters->vista, ['tablero', 'eventos'], true) ? $filters->vista : 'tablero';
    $status = $filters->status;
    $grain = $filters->grain();
    $options = $filterOptions ?? ['places' => [], 'types' => [], 'comunas' => []];
    $board = $board ?? [
        'total' => 0, 'nuevo' => 0, 'en_atencion' => 0, 'cerrado' => 0, 'closed_rate' => 0, 'load_rate' => 0,
        'top' => [], 'trend' => ['labels' => ['—'], 'series' => []],
        'peaks' => ['labels' => ['—'], 'values' => [0]],
        'kinds' => ['labels' => [], 'values' => []], 'sources' => ['labels' => [], 'values' => []],
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
        'top' => $board['top'] ?? [],
        'trend' => $board['trend'] ?? ['labels' => ['—'], 'series' => []],
        'peaks' => $board['peaks'] ?? ['labels' => ['—'], 'values' => [0]],
        'sources' => $board['sources'] ?? ['labels' => [], 'values' => []],
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
</style>
@endpush

<div class="obs-board space-y-3"
     @if ($observatoryLiveUrl)
         x-data="opsLivePage"
         data-live-url="{{ $observatoryLiveUrl }}"
         data-show-client="{{ $showClientColumn ? '1' : '0' }}"
         data-events='@json($liveEventRows)'
     @endif>
    <div class="space-y-3" @if ($vista === 'tablero') x-data="observatoryBoard(@js($charts))" @endif>
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
            $chips[] = ['Tipo: '.(collect($options['types'])->firstWhere('value', $filters->type)['label'] ?? $filters->type), $drop(['type'])];
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
                       @if ($observatoryLiveUrl) x-text="payload.{{ $value === null ? 'total' : $value }} ?? {{ (int) $count }}" @endif>{{ $count }}</p>
                </a>
            @endforeach
        </div>

        <div class="grid gap-2 lg:grid-cols-[minmax(0,7fr)_minmax(13rem,3fr)] lg:items-stretch">
            @include('modules.observatory.partials.map', [
                'map' => $map,
                'mapCanvasClass' => 'h-80 lg:h-full lg:min-h-[26rem]',
            ])
            @include('modules.observatory.partials.pin-legend')
        </div>

        <div class="grid gap-2 lg:grid-cols-[minmax(14rem,1fr)_minmax(0,2fr)] lg:items-stretch">
            <section class="obs-card p-2.5 flex flex-col min-h-0">
                <p class="text-[10px] uppercase tracking-wide text-slate-500 shrink-0">Instalaciones por riesgo</p>
                <div class="mt-1.5 space-y-1.5 overflow-y-auto max-h-48 lg:max-h-none lg:flex-1 sidebar-scroll">
                    @if ($observatoryLiveUrl)
                        <template x-for="row in (payload.top || [])" :key="row.name + '-' + (row.count ?? 0)">
                            <div class="flex items-start justify-between gap-2 text-sm">
                                <div class="min-w-0">
                                    <p class="text-slate-200 truncate text-[13px]" x-text="row.name"></p>
                                    @if ($showClientColumn)
                                        <p class="text-[10px] text-slate-500 truncate" x-show="row.client" x-text="row.client"></p>
                                    @endif
                                    <p class="text-[10px] text-slate-500 truncate" x-show="row.comuna_name" x-text="row.comuna_name"></p>
                                </div>
                                <p class="font-mono text-[11px] text-slate-300 shrink-0">
                                    <span x-text="row.score ?? row.count"></span>
                                    <span class="text-slate-600" x-text="row.count"></span>
                                </p>
                            </div>
                        </template>
                        <p class="text-sm text-slate-500" x-show="!(payload.top || []).length">Aún no hay eventos en el periodo.</p>
                    @else
                        @forelse ($board['top'] as $row)
                            <div class="flex items-start justify-between gap-2 text-sm">
                                <div class="min-w-0">
                                    <p class="text-slate-200 truncate text-[13px]">{{ $row['name'] }}</p>
                                    @if ($showClientColumn && filled($row['client']))
                                        <p class="text-[10px] text-slate-500 truncate">{{ $row['client'] }}</p>
                                    @endif
                                    @if (filled($row['comuna_name'] ?? null))
                                        <p class="text-[10px] text-slate-500 truncate">{{ $row['comuna_name'] }}</p>
                                    @endif
                                </div>
                                <p class="font-mono text-[11px] text-slate-300 shrink-0">{{ $row['score'] ?? $row['count'] }} <span class="text-slate-600">{{ $row['count'] }}</span></p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">Aún no hay eventos en el periodo.</p>
                        @endforelse
                    @endif
                </div>
            </section>
            <section class="obs-card">
                <div class="obs-card-h">
                    <p>Tendencia por tipo</p>
                    <h3>{{ $grain === 'year' ? 'Mes a mes' : 'Día a día' }}</h3>
                </div>
                <div class="obs-chart obs-chart-lg"><canvas x-ref="trend" aria-label="Tendencia por tipo"></canvas></div>
            </section>
        </div>

        <div class="grid gap-2 md:grid-cols-2 lg:grid-cols-[1.15fr_1.15fr_0.85fr] lg:items-stretch">
            <section class="obs-card">
                <div class="obs-card-h">
                    <p>Picos</p>
                    <h3>Días con más reportes</h3>
                </div>
                <div class="obs-chart"><canvas x-ref="peaks" aria-label="Días con más reportes"></canvas></div>
            </section>
            <section class="obs-card">
                <div class="obs-card-h">
                    <p>Canal</p>
                    <h3>De dónde llega</h3>
                </div>
                <div class="obs-chart"><canvas x-ref="sources" aria-label="Reportes por canal"></canvas></div>
            </section>
            <section class="obs-card">
                <div class="obs-card-h">
                    <p>Atención</p>
                    <h3>Atención de folios</h3>
                </div>
                <div class="obs-chart obs-gauge">
                    <div class="obs-gauge-plot">
                        <canvas x-ref="gauge" aria-label="Atención de folios"></canvas>
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
                        <th class="px-4 py-2.5 text-left font-medium hidden sm:table-cell">Tipo</th>
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
