@php
    $action = $action ?? '';
    $search = $search ?? '';
    $from = $from ?? '';
    $to = $to ?? '';
    $status = $status ?? '';
    $vista = in_array($vista ?? 'tablero', ['tablero', 'eventos'], true) ? ($vista ?? 'tablero') : 'tablero';
    $grain = in_array($grain ?? 'day', ['day', 'month', 'year'], true) ? ($grain ?? 'day') : 'day';
    $filterClientId = $filterClientId ?? '';
    $filterClients = $filterClients ?? collect();
    $comuna = $comuna ?? '';
    $comunas = $comunas ?? ($map['comunas'] ?? []);
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
    $searchPlaceholder = $showClientColumn
        ? 'Buscar por instalación, DANE, cliente o texto…'
        : 'Buscar por instalación, DANE o texto…';
    $docsUrl = route('observatory.docs');
    $exportRoute = $exportRoute ?? null;
    $exportUrl = $exportRoute ? ($exportRoute.'?'.http_build_query(array_filter([
        'from' => $from ?: null,
        'to' => $to ?: null,
        'grain' => $grain,
        'comuna' => $comuna ?: null,
        'client_id' => $filterClientId ?: null,
    ], static fn ($value) => $value !== null && $value !== ''))) : null;
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
    <form method="GET" action="{{ $action }}"
          x-data="obsDateRange({ from: @js($from), to: @js($to) })"
          class="rounded-xl border border-slate-800 bg-slate-900/70 p-2.5 flex flex-col lg:flex-row lg:flex-wrap xl:flex-nowrap xl:items-end gap-2">
        <input type="hidden" name="vista" value="{{ $vista }}">
        <input type="hidden" name="from" :value="from">
        <input type="hidden" name="to" :value="to">
        <input type="hidden" name="comuna" value="{{ $comuna }}">
        @if ($showClientColumn && $filterClients->isNotEmpty())
            <div class="w-full lg:w-36 shrink-0">
                <label for="client_id" class="block text-[11px] text-slate-500 mb-1">Cliente</label>
                <select id="client_id" name="client_id" class="w-full h-9 px-2 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
                    <option value="">Todos</option>
                    @foreach ($filterClients as $row)
                        <option value="{{ $row->id }}" @selected((string) $filterClientId === (string) $row->id)>{{ $row->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="shrink-0">
            <p class="block text-[11px] text-slate-500 mb-1">Desde — Hasta</p>
            <button type="button"
                    class="h-9 px-2.5 inline-flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-950 text-sm text-slate-200 hover:bg-slate-800"
                    @click="show()">
                <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <rect x="3" y="5" width="18" height="16" rx="2"></rect>
                    <path d="M3 10h18M8 3v4M16 3v4"></path>
                </svg>
                <span class="whitespace-nowrap" x-text="label"></span>
            </button>
            <template x-teleport="body">
                <div x-show="open" x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center p-4"
                     @keydown.escape.window="close()">
                    <div class="absolute inset-0 bg-slate-950/75" @click="close()"></div>
                    <div class="relative w-full max-w-md rounded-xl border border-slate-700 bg-slate-900 p-4 shadow-2xl" @click.stop>
                        <p class="text-[11px] uppercase tracking-wide text-slate-500">Rango</p>
                        <h3 class="text-sm font-semibold text-white">Desde y hasta</h3>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div>
                                <label for="obs-from" class="block text-[11px] text-slate-500 mb-1">Desde</label>
                                <input type="date" id="obs-from" x-model="draftFrom"
                                       class="w-full h-9 px-2 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
                            </div>
                            <div>
                                <label for="obs-to" class="block text-[11px] text-slate-500 mb-1">Hasta</label>
                                <input type="date" id="obs-to" x-model="draftTo"
                                       class="w-full h-9 px-2 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
                            </div>
                        </div>
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" class="h-9 px-3 rounded-lg border border-slate-700 text-sm text-slate-300" @click="close()">Cerrar</button>
                            <button type="button" class="h-9 px-3 rounded-lg bg-slate-100 text-sm font-medium text-slate-900" @click="apply()">Aceptar</button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
        <div class="shrink-0">
            <label for="grain" class="block text-[11px] text-slate-500 mb-1">Líneas</label>
            <select id="grain" name="grain" class="h-9 px-2 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
                <option value="day" @selected($grain === 'day')>Por día</option>
                <option value="month" @selected($grain === 'month')>Por mes</option>
                <option value="year" @selected($grain === 'year')>Por año</option>
            </select>
        </div>
        <div class="flex-1 min-w-[10rem]">
            <label for="q" class="block text-[11px] text-slate-500 mb-1">Buscar</label>
            <input type="search" id="q" name="q" value="{{ $search }}" placeholder="{{ $searchPlaceholder }}"
                   class="w-full h-9 px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white placeholder:text-slate-600">
        </div>
        @if ($status !== '')
            <input type="hidden" name="status" value="{{ $status }}">
        @endif
        <button type="submit" class="h-9 px-4 shrink-0 rounded-lg border border-slate-700 text-sm text-slate-200 hover:bg-slate-800">Filtrar</button>
        @if ($vista === 'tablero')
            @include('modules.observatory.partials.share-modal')
        @endif
        @if ($vista === 'tablero' && $exportUrl)
            <a href="{{ $exportUrl }}"
               class="h-9 px-4 inline-flex items-center rounded-lg border border-slate-700 text-sm text-slate-300 hover:bg-slate-800">PPTX</a>
        @endif
        <a href="{{ $docsUrl }}" target="_blank" rel="noopener"
           class="h-9 px-4 inline-flex items-center rounded-lg border border-slate-700 text-sm text-slate-300 hover:bg-slate-800">API</a>
    </form>

    @if ($vista === 'tablero')
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-2">
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

        <div class="grid gap-2 xl:grid-cols-[minmax(0,7fr)_minmax(13rem,3fr)] xl:items-stretch">
            @include('modules.observatory.partials.map', [
                'map' => $map,
                'mapCanvasClass' => 'h-80 xl:h-full xl:min-h-[26rem]',
            ])
            @include('modules.observatory.partials.pin-legend')
        </div>

        <div class="grid gap-2 xl:grid-cols-[minmax(14rem,1fr)_minmax(0,2fr)] xl:items-stretch">
            <section class="obs-card p-2.5 flex flex-col min-h-0">
                <p class="text-[10px] uppercase tracking-wide text-slate-500 shrink-0">Instalaciones por riesgo</p>
                <div class="mt-1.5 space-y-1.5 overflow-y-auto max-h-48 xl:max-h-none xl:flex-1 sidebar-scroll">
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
                    <h3>{{ $grain === 'year' ? 'Meses del periodo' : ($grain === 'month' ? 'Días del mes' : 'Día a día') }}</h3>
                </div>
                <div class="obs-chart obs-chart-lg"><canvas x-ref="trend" aria-label="Tendencia por tipo"></canvas></div>
            </section>
        </div>

        <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-[1.15fr_1.15fr_0.85fr] xl:items-stretch">
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
