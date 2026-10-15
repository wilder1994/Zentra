@php
    $liveJson = json_encode($map['live'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $historyJson = json_encode($map['history'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $reviewsJson = json_encode($map['reviews'] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $eventsJson = json_encode($map['events'] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $clientsJson = json_encode($map['clients'] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $installationsJson = json_encode($map['installations'] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $coverageJson = json_encode($map['coverage'] ?? null, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $googleMapsJson = json_encode($map['google_maps'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $activeTab = in_array($tab ?? '', ['live', 'history', 'summary', 'sheets'], true) ? $tab : 'live';
    $tabQuery = array_filter([
        'from' => $summary->from,
        'to' => $summary->to,
        'zone_id' => $filter->zoneId ?? null,
        'supervisor_id' => $filter->supervisorId ?? null,
        'kind' => $filter->sheetKind ?? null,
        'client_id' => $filter->clientId ?? null,
        'novelty' => $filter->hasNovelty === null ? null : ($filter->hasNovelty ? '1' : '0'),
    ], fn ($value) => $value !== null && $value !== '');
    $semaphoreClass = match ($summary->semaphore) {
        'green' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40',
        'yellow' => 'bg-amber-500/20 text-amber-300 border-amber-500/40',
        'red' => 'bg-red-500/20 text-red-300 border-red-500/40',
        default => 'bg-slate-800 text-slate-300 border-slate-700',
    };
@endphp

<x-company-layout title="Supervisión">
    <x-slot:actions>
        @if ($activeTab !== 'live')
        <form method="GET" action="{{ route('company.supervision.index') }}" class="flex flex-wrap items-end gap-2" id="supervision-filter">
            <input type="hidden" name="tab" value="{{ $activeTab }}">
            @include('modules.company.supervision.partials.period-picker', ['from' => $summary->from, 'to' => $summary->to])
            @if (in_array($activeTab, ['summary', 'sheets'], true))
            <div>
                <label for="zone_id" class="text-xs text-slate-500">Zona</label>
                <select id="zone_id" name="zone_id"
                        class="mt-1 block h-9 min-w-[9.5rem] px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
                    <option value="">Todas</option>
                    @foreach ($zones ?? [] as $zone)
                        <option value="{{ $zone->id }}" @selected($filter->zoneId === (int) $zone->id)>
                            {{ $zone->name }}{{ $zone->is_active ? '' : ' (inactiva)' }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif
            <div>
                <label for="supervisor_id" class="text-xs text-slate-500">Supervisor</label>
                <select id="supervisor_id" name="supervisor_id"
                        class="mt-1 block h-9 min-w-[9.5rem] px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
                    <option value="">Todos</option>
                    @foreach ($supervisors ?? [] as $supervisor)
                        <option value="{{ $supervisor->id }}" @selected($filter->supervisorId === (int) $supervisor->id)>
                            {{ $supervisor->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @if ($activeTab === 'sheets')
                <div>
                    <label for="kind" class="text-xs text-slate-500">Tipo</label>
                    <select id="kind" name="kind"
                            class="mt-1 block h-9 min-w-[8.5rem] px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
                        <option value="">Todas</option>
                        <option value="review" @selected($filter->sheetKind === 'review')>Revista</option>
                        <option value="alarm" @selected($filter->sheetKind === 'alarm')>Alarma</option>
                        <option value="support" @selected($filter->sheetKind === 'support')>Apoyo</option>
                        <option value="document" @selected($filter->sheetKind === 'document')>Documentos</option>
                    </select>
                </div>
                <div>
                    <label for="client_id" class="text-xs text-slate-500">Cliente</label>
                    <select id="client_id" name="client_id"
                            class="mt-1 block h-9 min-w-[9.5rem] px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
                        <option value="">Todos</option>
                        @foreach ($clients ?? [] as $client)
                            <option value="{{ $client->id }}" @selected($filter->clientId === (int) $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="novelty" class="text-xs text-slate-500">Novedad</label>
                    <select id="novelty" name="novelty"
                            class="mt-1 block h-9 min-w-[7.5rem] px-3 text-sm rounded-lg border border-slate-700 bg-slate-950 text-white">
                        <option value="">Todas</option>
                        <option value="1" @selected($filter->hasNovelty === true)>Con novedad</option>
                        <option value="0" @selected($filter->hasNovelty === false)>Sin novedad</option>
                    </select>
                </div>
            @endif
            <x-ui.button type="submit" size="sm" variant="secondary">Filtrar</x-ui.button>
            @if ($activeTab !== 'history')
            <a href="{{ route('company.supervision.report', $tabQuery) }}"
               class="inline-flex items-center h-9 px-3 text-sm rounded-lg border border-amber-500/40 text-amber-200 hover:bg-amber-500/10">
                Descargar PPTX
            </a>
            @endif
        </form>
        @endif
    </x-slot:actions>

    <x-slot:headerTabs>
        <a
            href="{{ route('company.supervision.index', $tabQuery + ['tab' => 'live']) }}"
            @class(['admin-header-tab', 'is-active' => $activeTab === 'live'])
        >En vivo</a>
        <a
            href="{{ route('company.supervision.index', $tabQuery + ['tab' => 'history']) }}"
            @class(['admin-header-tab', 'is-active' => $activeTab === 'history'])
        >Historial</a>
        <a
            href="{{ route('company.supervision.index', $tabQuery + ['tab' => 'summary']) }}"
            @class(['admin-header-tab', 'is-active' => $activeTab === 'summary'])
        >Resumen</a>
        <a
            href="{{ route('company.supervision.index', $tabQuery + ['tab' => 'sheets']) }}"
            @class(['admin-header-tab', 'is-active' => $activeTab === 'sheets'])
        >Fichas</a>
        @if (app(\App\Services\Supervision\InstallationGeoRequestService::class)->canApprove(auth()->user()))
            <a href="{{ route('company.supervision.geo.index') }}" class="admin-header-tab">Georreferenciaciones</a>
        @endif
    </x-slot:headerTabs>

    <div class="space-y-4">
        @if ($activeTab !== 'summary' && $activeTab !== 'sheets')
            <div @class(['grid gap-4 lg:grid-cols-12 lg:items-stretch' => in_array($activeTab, ['live', 'history'], true)])>
                <div @class(['relative' => true, 'lg:col-span-7 xl:col-span-8' => in_array($activeTab, ['live', 'history'], true)])>
                    <div id="supervision-map" @class([
                        'w-full rounded-lg border border-slate-800 bg-slate-950/60 overflow-hidden relative',
                        'h-[min(78vh,740px)] min-h-[420px]' => in_array($activeTab, ['live', 'history'], true),
                        'h-[420px]' => ! in_array($activeTab, ['live', 'history'], true),
                    ])></div>
                        <div class="absolute top-3 left-3 right-16 z-10 flex flex-wrap items-center gap-2 pointer-events-none">
                            <div class="pointer-events-auto inline-flex rounded-md border border-slate-700 bg-slate-950/90 p-0.5 text-xs">
                                <button type="button" class="supervision-map-type-btn rounded px-2 py-1 font-medium text-white bg-indigo-600/80" data-map-type="satellite">Satélite</button>
                                <button type="button" class="supervision-map-type-btn rounded px-2 py-1 font-medium text-slate-400 hover:text-slate-200" data-map-type="terrain">Terreno</button>
                            </div>
                            @if ($activeTab === 'live')
                                <button type="button" id="supervision-show-all" class="pointer-events-auto hidden rounded-md border border-indigo-400/60 bg-indigo-600/90 px-2.5 py-1 text-xs font-semibold text-white shadow hover:bg-indigo-500">← Ver todos</button>
                                <div class="pointer-events-auto inline-flex rounded-md border border-slate-700 bg-slate-950/90 p-0.5 text-xs">
                                    <button type="button" class="supervision-points-btn rounded px-2 py-1 font-medium" data-points="client">Clientes</button>
                                    <button type="button" class="supervision-points-btn rounded px-2 py-1 font-medium" data-points="installation">Instalaciones</button>
                                </div>
                                <label class="pointer-events-auto inline-flex cursor-pointer items-center gap-2 rounded-md border border-slate-700 bg-slate-950/90 px-2 py-1 text-xs text-slate-200">
                                    <input type="checkbox" id="supervision-pending-toggle" class="peer sr-only">
                                    <span class="relative inline-block h-4 w-7 rounded-full bg-slate-700 transition peer-checked:bg-red-500 after:absolute after:left-0.5 after:top-0.5 after:h-3 after:w-3 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-3"></span>
                                    Pendientes
                                </label>
                                <span id="supervision-coverage-chip" class="pointer-events-auto rounded-md border border-slate-700 bg-slate-950/90 px-2 py-1 text-[11px] text-slate-300"></span>
                            @endif
                        </div>
                        @if ($activeTab === 'live')
                            <div id="supervision-missing-location" class="absolute bottom-3 left-3 z-10 hidden max-w-[22rem] rounded-md border border-amber-500/50 bg-slate-950/95 px-3 py-2 text-[11px] text-amber-200 shadow-lg"></div>
                        @endif
                        <div id="supervision-map-fallback" class="absolute inset-0 flex items-center justify-center text-center p-6 text-sm text-slate-500 hidden">
                            <div>
                                <p class="text-slate-300 font-medium mb-1">Mapa no disponible</p>
                                <p>Configura <code class="text-indigo-300">GOOGLE_MAPS_API_KEY</code>.</p>
                            </div>
                        </div>
                </div>

                @if ($activeTab === 'live')
                    <section class="relative lg:col-span-5 xl:col-span-4 rounded-lg border border-slate-800 bg-slate-900/80 p-4 min-h-[420px] lg:min-h-0 lg:h-[min(78vh,740px)] flex flex-col"
                             x-data="{ help: false }"
                             x-init="if (! localStorage.getItem('zentra-sup-help-live')) { localStorage.setItem('zentra-sup-help-live', '1'); help = true; setTimeout(() => help = false, 7000); }">
                        @include('modules.company.supervision.partials.pin-legend', ['live' => true])
                        <div class="mt-3 pt-3 border-t border-slate-800 flex items-center justify-between gap-2 shrink-0">
                            <h3 class="text-sm font-semibold text-white">Supervisores en turno <span id="supervision-live-count" class="font-normal text-slate-500">· {{ count($map['live']) }}</span></h3>
                            <button type="button" x-ref="helpBtn" @click="help = ! help" title="Ayuda"
                                    class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-600 text-[11px] font-bold text-slate-300 hover:bg-slate-800 hover:text-white">?</button>
                        </div>
                        @include('modules.company.supervision.partials.panel-help', ['tips' => [
                            'Clic en un supervisor: solo su recorrido en vivo. Otro clic o «Ver todos» vuelve a la vista general.',
                            'Pasa el cursor por la moto para ver quién es y su señal. Clic en un pin o grupo: sus eventos.',
                            'Ver: ficha del turno en curso.',
                            'La lista y el mapa se actualizan solos cada 10 s.',
                            'Revistas por día operativo: de 06:00 a 06:00.',
                        ]])
                        <div class="mt-2 overflow-auto flex-1" id="supervision-live-list">
                            @include('modules.company.supervision.partials.live-roster', ['rows' => $map['live']])
                        </div>
                    </section>
                @endif

                @if ($activeTab === 'history')
                    <section class="relative lg:col-span-5 xl:col-span-4 rounded-lg border border-slate-800 bg-slate-900/80 p-4 min-h-[420px] lg:min-h-0 lg:h-[min(78vh,740px)] flex flex-col"
                             x-data="{ help: false }"
                             x-init="if (! localStorage.getItem('zentra-sup-help-history')) { localStorage.setItem('zentra-sup-help-history', '1'); help = true; setTimeout(() => help = false, 7000); }">
                        @include('modules.company.supervision.partials.pin-legend')
                        <div class="mt-3 pt-3 border-t border-slate-800 flex items-center justify-between gap-2 shrink-0">
                            <h3 class="text-sm font-semibold text-white">Turnos del periodo <span class="font-normal text-slate-500">· {{ count($map['history']) }}</span></h3>
                            <button type="button" x-ref="helpBtn" @click="help = ! help" title="Ayuda"
                                    class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-600 text-[11px] font-bold text-slate-300 hover:bg-slate-800 hover:text-white">?</button>
                        </div>
                        @include('modules.company.supervision.partials.panel-help', ['tips' => [
                            'Solo turnos cerrados del periodo filtrado.',
                            'Clic en un turno: su ruta en calle, paradas y eventos (una ruta a la vez).',
                            'Clic en un pin o grupo: sus eventos.',
                            'Ver: ficha guardada del turno.',
                        ]])
                        <div class="mt-2 overflow-auto flex-1 space-y-1">
                            @forelse ($map['history'] as $row)
                                <button type="button"
                                        class="supervision-trail-pick w-full text-left rounded-md px-2 py-2 hover:bg-slate-800 border border-transparent"
                                        data-shift-id="{{ $row['shift_id'] }}">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="text-sm font-medium text-slate-100">{{ $row['user'] ?? 'Supervisor' }}</p>
                                        @if (! empty($row['sheet_url']))
                                            <a href="{{ $row['sheet_url'] }}" target="_blank" rel="noopener"
                                               class="shrink-0 rounded-md border border-slate-700 px-2 py-0.5 text-[11px] font-semibold text-indigo-300 hover:bg-slate-800"
                                               onclick="event.stopPropagation()">Ver</a>
                                        @endif
                                    </div>
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $row['status_label'] ?? $row['status'] }}
                                        @if (! empty($row['schedule_label']))
                                            · {{ $row['schedule_label'] }}
                                        @endif
                                    </p>
                                    <p class="text-xs text-slate-500 mt-0.5">Inicio {{ $row['started_at_label'] ?? '—' }}
                                        @if (! empty($row['ended_at_label']))
                                            · fin {{ $row['ended_at_label'] }}
                                        @endif
                                        · {{ number_format((float) ($row['km_traveled'] ?? 0), 1) }} km
                                    </p>
                                </button>
                            @empty
                                <p class="text-sm text-slate-500">Sin turnos en el rango.</p>
                            @endforelse
                        </div>
                    </section>
                @endif
            </div>
        @endif

        @if ($activeTab === 'sheets')
            <section class="rounded-lg border border-slate-800 bg-slate-900/80 overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-800">
                    <h3 class="text-sm font-semibold text-white">Fichas de campo</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Carta imprimible. No es el rastro GPS ni el PPTX.</p>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs font-medium text-slate-500 border-b border-slate-800">
                            <th class="text-left px-4 py-2">Folio</th>
                            <th class="text-left px-4 py-2">Tipo</th>
                            <th class="text-left px-4 py-2">Supervisor</th>
                            <th class="text-left px-4 py-2">Cliente</th>
                            <th class="text-left px-4 py-2">Fecha</th>
                            <th class="text-left px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sheets ?? [] as $row)
                            <tr class="border-b border-slate-800/80">
                                <td class="px-4 py-2 font-mono text-xs text-indigo-300/90">{{ $row->folio }}</td>
                                <td class="px-4 py-2 font-medium text-slate-200">
                                    {{ $row->typeLabel }}
                                    @if ($row->hasNovelty)
                                        <span class="ml-1 text-xs text-red-300">novedad</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-slate-300">{{ $row->supervisorName }}</td>
                                <td class="px-4 py-2 text-slate-400">{{ $row->clientName ?? '—' }}</td>
                                <td class="px-4 py-2 text-slate-400">{{ $row->recordedAt->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('company.supervision.sheets.show', ['kind' => $row->kind->value, 'id' => $row->id]) }}"
                                       target="_blank"
                                       class="text-indigo-300 hover:text-indigo-200">Ver / imprimir</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-sm text-slate-500">No hay fichas en este recorte.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                @if ($sheets)
                    <div class="px-4 py-3">{{ $sheets->links() }}</div>
                @endif
            </section>
        @endif

        @if ($activeTab === 'summary')
            @include('modules.company.supervision.partials.summary-dashboard')
        @endif
    </div>

    @push('scripts')
        <script>
            (function () {
                let live = {!! $liveJson !!};
                const history = {!! $historyJson !!};
                let reviews = {!! $reviewsJson !!};
                let events = {!! $eventsJson !!};
                const clients = {!! $clientsJson !!};
                const installations = {!! $installationsJson !!};
                let coverage = {!! $coverageJson !!};
                let pointsMode = 'client';
                let pendingOnly = false;
                const googleMaps = {!! $googleMapsJson !!};
                const activeTab = @json($activeTab);
                const liveFeedUrl = @json(route('company.supervision.live-feed'));
                const snappedRouteUrl = (id) => @json(url('/company/supervision/turnos')).replace(/\/$/, '') + '/' + id + '/ruta';
                const mapEl = document.getElementById('supervision-map');
                const fallback = document.getElementById('supervision-map-fallback');

                if (! mapEl) {
                    return;
                }

                const svgIcon = (svg, size, anchor) => ({
                    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
                    scaledSize: new google.maps.Size(size, size),
                    anchor: new google.maps.Point(anchor, anchor),
                });

                const PIN_SIZE = 24;
                @php
                    $pinSvgs = [];
                    foreach (['start', 'flag', 'review', 'novelty', 'alarm', 'stop', 'support', 'coverage'] as $pinKind) {
                        $pinSvgs[$pinKind] = trim(view('modules.company.supervision.partials.pin-icon', ['kind' => $pinKind])->render());
                    }
                @endphp
                const pinSvgs = @json($pinSvgs);
                const pinIcon = (key, count) => {
                    let svg = pinSvgs[key];
                    if (count > 1) {
                        const text = count > 99 ? '99+' : String(count);
                        svg = svg.replace('viewBox="0 0 32 32"', 'viewBox="0 0 36 36"').replace(/<svg([^>]*)>/, '<svg$1><g transform="translate(0 4)">')
                            .replace(/<\/svg>$/, '</g><circle cx="29" cy="7" r="6.5" fill="#0b1220" stroke="#f8fafc" stroke-width="1.2"/>'
                                + '<text x="29" y="9.8" text-anchor="middle" font-family="system-ui,sans-serif" font-size="' + (text.length > 2 ? 6 : 8) + '" font-weight="700" fill="#ffffff">' + text + '</text></svg>');
                        const box = PIN_SIZE * 36 / 32;
                        return {
                            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
                            scaledSize: new google.maps.Size(box, box),
                            anchor: new google.maps.Point(box * 16 / 36, box * 20 / 36),
                        };
                    }
                    return svgIcon(svg, PIN_SIZE, PIN_SIZE / 2);
                };
                const iconMoto = () => ({
                    url: @json(asset('images/ui/supervisor-moto.png').'?v='.(string) filemtime(public_path('images/ui/supervisor-moto.png'))),
                    scaledSize: new google.maps.Size(50, 36),
                    anchor: new google.maps.Point(25, 34),
                    labelOrigin: new google.maps.Point(25, -6),
                });
                function circleIcon(color, scale) {
                    return {
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: scale || 10.5,
                        fillColor: color,
                        fillOpacity: 1,
                        strokeColor: '#0b1220',
                        strokeWeight: 1,
                    };
                }

                let googleMap = null;
                let overlays = [];
                let selectedLiveShiftId = null;
                let livePollTimer = null;
                let liveFitted = false;
                let pinCard = null;
                let motoTip = null;

                function esc(value) {
                    return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#39;',
                    }[ch]));
                }

                function signalOf(row) {
                    return row.signal || (row.online ? 'online' : 'no_signal');
                }

                function signalTextClass(row) {
                    const s = signalOf(row);
                    if (s === 'screen_off') return 'text-amber-400';
                    if (s === 'online') return 'text-emerald-400';
                    return 'text-red-400';
                }

                function signalColor(row) {
                    const s = signalOf(row);
                    if (s === 'screen_off') return '#fbbf24';
                    if (s === 'online') return '#86efac';
                    return '#fca5a5';
                }

                function liveStatus(row) {
                    if (row.parked && row.parked.minutes) return 'parado ' + row.parked.minutes + ' min';
                    if (row.lat) return 'en ruta';
                    return 'sin GPS aún';
                }

                function renderLiveList(rows) {
                    const list = document.getElementById('supervision-live-list');
                    if (!list) return;
                    const count = document.getElementById('supervision-live-count');
                    if (count) count.textContent = '· ' + rows.length;
                    if (!rows.length) {
                        list.innerHTML = '<p class="text-sm text-slate-500">Nadie en turno ahora.</p>';
                        return;
                    }
                    list.innerHTML = '<table class="w-full text-sm"><thead><tr class="text-[11px] uppercase tracking-wide text-slate-500 border-b border-slate-800">'
                        + '<th class="text-left py-2 pr-2 font-medium">Supervisor</th>'
                        + '<th class="text-left py-2 pr-2 font-medium">Estado</th>'
                        + '<th class="text-right py-2 font-medium">Km</th>'
                        + '<th class="text-right py-2 font-medium">Rev.</th>'
                        + '<th class="text-right py-2 font-medium"></th>'
                        + '</tr></thead><tbody>'
                        + rows.map((row) => {
                            const selected = Number(selectedLiveShiftId) === Number(row.shift_id);
                            const sheet = row.sheet_url
                                ? '<a href="' + esc(row.sheet_url) + '" target="_blank" rel="noopener" class="js-sheet-link inline-flex rounded-md border border-slate-700 px-2 py-0.5 text-[11px] font-semibold text-indigo-300 hover:bg-slate-800">Ver</a>'
                                : '';
                            return '<tr class="supervision-live-pick border-b border-slate-800/70 align-top cursor-pointer hover:bg-slate-800/60' + (selected ? ' bg-slate-800 ring-1 ring-inset ring-indigo-500/60' : '') + '" data-shift-id="' + esc(String(row.shift_id)) + '">'
                                + '<td class="py-2 pr-2"><p class="font-medium text-slate-100">' + esc(row.user || 'Supervisor') + '</p>'
                                + '<p class="text-xs mt-0.5 ' + signalTextClass(row) + '">' + esc(row.online_label || (row.online ? 'En línea' : 'Sin señal')) + '</p>'
                                + '<p class="text-xs text-slate-500 mt-0.5">Inicio ' + esc(row.started_at_label || '—') + '</p></td>'
                                + '<td class="py-2 pr-2 text-xs text-slate-300 leading-snug">' + esc(row.status_line || liveStatus(row)) + '</td>'
                                + '<td class="py-2 text-right text-slate-300 tabular-nums">' + Number(row.km || 0).toFixed(1) + '</td>'
                                + '<td class="py-2 text-right text-slate-300 tabular-nums">' + String(row.reviews_count || 0) + '</td>'
                                + '<td class="py-2 pl-2 text-right">' + sheet + '</td>'
                                + '</tr>';
                        }).join('')
                        + '</tbody></table>';
                }

                function clearOverlays() {
                    closePinPreview(true);
                    overlays.forEach((item) => item.setMap(null));
                    overlays = [];
                }

                function addOverlay(item) {
                    overlays.push(item);
                    return item;
                }

                function extend(bounds, pos) {
                    if (pos?.lat == null || pos?.lng == null) return false;
                    bounds.extend(pos);
                    return true;
                }

                function pinMeters(a, b) {
                    if (a?.lat == null || a?.lng == null || b?.lat == null || b?.lng == null) return Number.POSITIVE_INFINITY;
                    const toRad = (d) => d * Math.PI / 180;
                    const dLat = toRad(b.lat - a.lat);
                    const dLng = toRad(b.lng - a.lng);
                    const s = Math.sin(dLat / 2) ** 2
                        + Math.cos(toRad(a.lat)) * Math.cos(toRad(b.lat)) * Math.sin(dLng / 2) ** 2;
                    return 6371000 * 2 * Math.atan2(Math.sqrt(s), Math.sqrt(1 - s));
                }

                const PIN_PRIORITY = ['flag', 'start', 'alarm', 'support', 'review', 'stop', 'client', 'installation', 'moto'];

                function listPins(items) {
                    return (items || []).filter((pin) => pin.kind !== 'moto');
                }

                function clusterPins(pins, radiusM) {
                    const n = pins.length;
                    if (n < 2) return pins.slice();
                    const parent = pins.map((_, i) => i);
                    const find = (x) => (parent[x] === x ? x : (parent[x] = find(parent[x])));
                    const uni = (a, b) => { parent[find(a)] = find(b); };
                    for (let i = 0; i < n; i++) {
                        for (let j = i + 1; j < n; j++) {
                            if (pinMeters(pins[i], pins[j]) <= radiusM) uni(i, j);
                        }
                    }
                    const groups = {};
                    pins.forEach((_, i) => {
                        const root = find(i);
                        (groups[root] ||= []).push(i);
                    });
                    const out = [];
                    Object.values(groups).forEach((idx) => {
                        const items = idx.map((i) => pins[i]);
                        if (items.length === 1) {
                            out.push(items[0]);
                            return;
                        }
                        const moto = items.find((p) => p.kind === 'moto');
                        const listed = listPins(items);
                        if (moto && listed.length) {
                            out.push(Object.assign({}, moto, { nearby: listed }));
                            if (listed.length === 1) {
                                out.push(listed[0]);
                            } else {
                                const anchor = listed.find((p) => p.kind === 'client') || listed[0];
                                out.push({
                                    kind: 'cluster',
                                    lat: anchor.lat,
                                    lng: anchor.lng,
                                    items: listed,
                                    title: listed.length + ' eventos en este punto',
                                });
                            }
                            return;
                        }
                        const anchor = items.find((p) => p.kind === 'client') || items[0];
                        out.push({
                            kind: 'cluster',
                            lat: anchor.lat,
                            lng: anchor.lng,
                            items,
                            title: items.length + ' eventos en este punto',
                        });
                    });
                    return out;
                }

                function coverageColor(row) {
                    if (row.done >= row.posts) return '#16a34a';
                    if (row.done > 0) return '#f59e0b';
                    return '#dc2626';
                }

                function coverageIcon(row, count) {
                    const text = String(count);
                    const ring = pointsMode === 'installation' ? '<circle cx="16" cy="16" r="11.5" fill="none" stroke="#ffffff" stroke-width="1" stroke-dasharray="3 2"/>' : '';
                    const svg = pinSvgs.coverage
                        .replace('__COLOR__', coverageColor(row))
                        .replace('__RING__', ring)
                        .replace('__FONT__', text.length > 2 ? '9.5' : (text.length > 1 ? '11.5' : '13'))
                        .replace('__TEXT__', esc(text));
                    return svgIcon(svg, PIN_SIZE, PIN_SIZE / 2);
                }

                function collectCoveragePins() {
                    if (!coverage) return [];
                    const source = pointsMode === 'installation' ? (coverage.installations || []) : (coverage.clients || []);
                    return source
                        .filter((row) => row.lat != null && row.lng != null)
                        .filter((row) => (pendingOnly ? row.posts - row.done > 0 : row.done > 0))
                        .map((row) => ({
                            kind: 'coverage',
                            scope: pointsMode,
                            lat: row.lat,
                            lng: row.lng,
                            title: row.name,
                            count: pendingOnly ? row.posts - row.done : row.done,
                            row,
                        }));
                }

                function drawCoveragePins(map, bounds, pins) {
                    let any = false;
                    pins.forEach((pin) => {
                        const marker = addOverlay(new google.maps.Marker({
                            map,
                            position: { lat: Number(pin.lat), lng: Number(pin.lng) },
                            zIndex: 3,
                            icon: coverageIcon(pin.row, pin.count),
                        }));
                        bindPinOpen(marker, pin);
                        any = extend(bounds, pin) || any;
                    });
                    return any;
                }

                function coverageCardHtml(pin) {
                    const row = pin.row;
                    const pending = row.posts - row.done;
                    const kind = pin.scope === 'installation' ? 'Instalación' : 'Cliente';
                    const meta = (pin.scope === 'installation'
                        ? (row.client ? row.client + ' · ' : '')
                        : row.installations + (row.installations === 1 ? ' instalación · ' : ' instalaciones · '))
                        + row.posts + (row.posts === 1 ? ' puesto' : ' puestos');
                    const list = (row.pending || []).slice(0, 8).map((name) => '<li style="margin:2px 0">' + esc(name) + '</li>').join('');
                    const more = (row.pending || []).length > 8 ? '<li class="zmap-card-faint">y ' + ((row.pending || []).length - 8) + ' más</li>' : '';
                    return '<div class="zmap-card" style="width:250px">'
                        + '<div style="height:3px;background:' + coverageColor(row) + '"></div>'
                        + '<div style="padding:8px 10px 10px">'
                        + '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px">'
                        + '<span class="zmap-card-kind">' + kind + '</span>'
                        + '<button type="button" class="sup-card-close zmap-card-close">×</button>'
                        + '</div>'
                        + '<p class="zmap-card-title" style="margin:6px 0 0">' + esc(row.name) + '</p>'
                        + '<p class="zmap-card-meta" style="margin:3px 0 0">' + esc(meta) + '</p>'
                        + '<p style="margin:6px 0 0"><span class="zmap-card-ok">' + row.done + ' revisados</span>'
                        + '<span class="zmap-card-faint"> · </span><span class="' + (pending > 0 ? 'zmap-card-bad' : 'zmap-card-ok') + '">' + pending + ' pendientes</span></p>'
                        + (list ? '<p class="zmap-card-meta" style="margin:8px 0 2px;font-size:11px">Puestos sin revista hoy:</p><ul style="margin:0;padding-left:16px">' + list + more + '</ul>' : '')
                        + '<a href="' + esc(row.url) + '" target="_blank" rel="noopener" class="zmap-card-link">Abrir ' + kind.toLowerCase() + '</a>'
                        + '</div></div>';
                }

                function renderCoverageChrome() {
                    const chip = document.getElementById('supervision-coverage-chip');
                    if (chip) {
                        chip.textContent = coverage
                            ? 'Puestos revisados hoy: ' + coverage.done + ' de ' + coverage.posts
                            : '';
                        chip.title = coverage ? 'Día operativo ' + coverage.window_label : '';
                    }
                    document.querySelectorAll('.supervision-points-btn').forEach((btn) => {
                        const active = btn.dataset.points === pointsMode;
                        btn.classList.toggle('bg-indigo-600/80', active);
                        btn.classList.toggle('text-white', active);
                        btn.classList.toggle('text-slate-400', !active);
                    });
                    document.getElementById('supervision-show-all')?.classList.toggle('hidden', selectedLiveShiftId === null);
                    const box = document.getElementById('supervision-missing-location');
                    if (!box) return;
                    const missing = (coverage && coverage.missing_location) || [];
                    if (!missing.length) {
                        box.classList.add('hidden');
                        box.innerHTML = '';
                        return;
                    }
                    const n = missing.length;
                    box.innerHTML = '<details><summary style="cursor:pointer;font-weight:600">⚠ ' + n + (n === 1 ? ' instalación sin ubicación' : ' instalaciones sin ubicación') + '</summary>'
                        + '<p style="margin:4px 0;color:#fcd34d">Registra la ubicación (coordenadas) de la instalación para poder pasarle revista.</p>'
                        + '<ul style="margin:0;padding-left:14px;max-height:9rem;overflow:auto">'
                        + missing.map((row) => '<li><a href="' + esc(row.url) + '" target="_blank" rel="noopener" style="color:#93c5fd">' + esc(row.name) + '</a> <span style="color:#94a3b8">· ' + esc(row.client) + '</span></li>').join('')
                        + '</ul></details>';
                    box.classList.remove('hidden');
                }

                function pinKindLabel(kind) {
                    return ({
                        start: 'Inicio',
                        moto: 'Supervisor',
                        flag: 'Cierre',
                        review: 'Revista',
                        stop: 'Parada',
                        client: 'Cliente',
                        installation: 'Instalación',
                        alarm: 'Alarma',
                        support: 'Apoyo',
                    })[kind] || kind;
                }

                function clusterFace(items) {
                    let best = items[0];
                    let bestRank = 99;
                    items.forEach((pin) => {
                        const rank = PIN_PRIORITY.indexOf(pin.kind);
                        const value = rank === -1 ? 50 : rank;
                        if (value < bestRank) {
                            best = pin;
                            bestRank = value;
                        }
                    });
                    return best;
                }

                function pinAccent(pin) {
                    if (pin.kind === 'review') return pin.novelty ? '#ea580c' : '#16a34a';
                    if (pin.kind === 'alarm') return '#dc2626';
                    if (pin.kind === 'support') return '#2563eb';
                    if (pin.kind === 'stop') return '#991b1b';
                    if (pin.kind === 'start') return '#16a34a';
                    if (pin.kind === 'flag') return '#f8fafc';
                    if (pin.kind === 'moto') return '#e2e8f0';
                    if (pin.kind === 'installation') return '#22d3ee';
                    return '#818cf8';
                }

                function pinCardHtml(pin) {
                    if (pin.kind === 'coverage') return coverageCardHtml(pin);
                    const row = pin.row || pin;
                    const kind = pinKindLabel(pin.kind);
                    let headline = row.client || pin.title || kind;
                    let meta = row.at_label || '';
                    if (row.user) meta = (meta ? meta + ' · ' : '') + row.user;
                    let extra = '';
                    if (pin.kind === 'review') {
                        extra = row.novelty
                            ? '<span class="zmap-card-bad">Con novedad</span>'
                            : '<span class="zmap-card-ok">Sin novedad</span>';
                        if (row.post) extra += '<span class="zmap-card-faint"> · ' + esc(row.post) + '</span>';
                    }
                    if (pin.kind === 'alarm' || pin.kind === 'support') {
                        extra = esc(row.subtitle || row.outcome_label || '');
                    }
                    if (pin.kind === 'moto') {
                        headline = pin.user || 'Supervisor';
                        meta = pin.online_label || 'En turno';
                    }
                    if (pin.kind === 'start' || pin.kind === 'flag') {
                        headline = pin.user || kind;
                        meta = pin.title || '';
                    }
                    if (pin.kind === 'installation') {
                        headline = pin.title || kind;
                        meta = row.client || '';
                    }
                    const notes = row.notes ? '<p class="zmap-card-meta" style="margin:6px 0 0">' + esc(row.notes) + '</p>' : '';
                    const link = row.sheet_url
                        ? '<a href="' + esc(row.sheet_url) + '" target="_blank" rel="noopener" class="zmap-card-link">Ver ficha' + (row.folio ? ' ' + esc(row.folio) : '') + '</a>'
                        : '';
                    return '<div class="zmap-card" style="width:236px">'
                        + '<div style="height:3px;background:' + pinAccent(pin) + '"></div>'
                        + '<div style="padding:8px 10px 10px">'
                        + '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px">'
                        + '<span class="zmap-card-kind">' + esc(kind) + '</span>'
                        + '<button type="button" class="sup-card-close zmap-card-close">×</button>'
                        + '</div>'
                        + '<p class="zmap-card-title" style="margin:6px 0 0">' + esc(headline) + '</p>'
                        + (meta ? '<p class="zmap-card-meta" style="margin:3px 0 0">' + esc(meta) + '</p>' : '')
                        + (extra ? '<p style="margin:6px 0 0">' + extra + '</p>' : '')
                        + notes
                        + link
                        + '</div></div>';
                }

                let ignoreMapClick = false;
                let pinSticky = false;
                let listSticky = false;
                let listAnchor = null;
                let pinHoverTimer = null;
                let pinStack = null;
                let PinStackOverlay = null;

                function ensurePinStackClass() {
                    if (PinStackOverlay) return;
                    PinStackOverlay = class extends google.maps.OverlayView {
                        constructor(position, html, onKeep, onLeave, onReady, opts = {}) {
                            super();
                            this.position = position;
                            this.html = html;
                            this.onKeep = onKeep;
                            this.onLeave = onLeave;
                            this.onReady = onReady;
                            this.place = opts.place || 'right';
                            this.pointer = opts.pointer !== false;
                            this.div = null;
                        }

                        onAdd() {
                            const div = document.createElement('div');
                            div.style.position = 'absolute';
                            div.style.zIndex = '1000';
                            div.style.pointerEvents = this.pointer ? 'auto' : 'none';
                            div.innerHTML = this.html;
                            if (this.pointer) {
                                div.onmouseenter = this.onKeep;
                                div.onmouseleave = this.onLeave;
                            }
                            this.div = div;
                            this.getPanes().floatPane.appendChild(div);
                            this.onReady?.(div);
                            this.draw();
                        }

                        draw() {
                            if (!this.div) return;
                            const point = this.getProjection().fromLatLngToDivPixel(this.position);
                            if (!point) return;
                            const w = this.div.offsetWidth;
                            const h = this.div.offsetHeight;
                            if (this.place === 'above') {
                                this.div.style.left = (point.x - w / 2) + 'px';
                                this.div.style.top = (point.y - h - 14) + 'px';
                                return;
                            }
                            this.div.style.left = (point.x + 16) + 'px';
                            this.div.style.top = (point.y - h / 2) + 'px';
                        }

                        onRemove() {
                            this.div?.remove();
                            this.div = null;
                        }
                    };
                }

                function hideOverlay(which) {
                    if (which) which.setMap(null);
                }

                function hidePinStack() {
                    hideOverlay(pinStack);
                    pinStack = null;
                }

                function hidePinCard() {
                    hideOverlay(pinCard);
                    pinCard = null;
                }

                function hideMotoTip() {
                    hideOverlay(motoTip);
                    motoTip = null;
                }

                function closePinPreview(force) {
                    if (force) {
                        pinSticky = false;
                        listSticky = false;
                        listAnchor = null;
                    }
                    if (force || !pinSticky) hidePinCard();
                    if (force || !listSticky) hidePinStack();
                    hideMotoTip();
                }

                function schedulePinHide() {
                    clearTimeout(pinHoverTimer);
                    pinHoverTimer = setTimeout(() => closePinPreview(false), 280);
                }

                function pinChipColor(pin) {
                    return pinAccent(pin);
                }

                function mountFloat(html, marker, opts) {
                    ensurePinStackClass();
                    return new PinStackOverlay(
                        marker.getPosition(),
                        html,
                        opts.onKeep,
                        opts.onLeave,
                        opts.onReady,
                        opts,
                    );
                }

                function showMotoTip(marker, pin) {
                    ensurePinStackClass();
                    hideMotoTip();
                    const name = pin.user || 'Supervisor';
                    const status = pin.online_label || 'En turno';
                    const html = '<div class="zmap-tip">'
                        + '<span class="zmap-card-title">' + esc(name) + '</span>'
                        + '<span class="zmap-card-meta"> · ' + esc(status) + '</span></div>';
                    motoTip = mountFloat(html, marker, { place: 'above', pointer: false });
                    motoTip.setMap(googleMap);
                }

                function showPinList(items, marker) {
                    hidePinStack();
                    hideMotoTip();
                    const rows = items.map((pin, idx) => (
                        '<button type="button" data-pin-idx="' + idx + '" class="sup-pin-pick" style="display:flex;align-items:center;gap:8px;margin:0;padding:2px 0;border:0;background:transparent;cursor:pointer;color:#fff;text-shadow:0 1px 3px #000;font:12px/1.2 system-ui,sans-serif;white-space:nowrap">'
                        + '<span style="width:12px;height:12px;border-radius:50%;background:' + pinChipColor(pin) + ';border:2px solid #fff;box-shadow:0 1px 2px rgba(0,0,0,.6);flex:0 0 auto"></span>'
                        + esc(pinKindLabel(pin.kind))
                        + '</button>'
                    )).join('');
                    const html = '<div style="display:flex;flex-direction:column;gap:6px;padding:4px 0 4px 2px">' + rows + '</div>';
                    pinStack = mountFloat(html, marker, {
                        place: 'right',
                        onKeep: () => clearTimeout(pinHoverTimer),
                        onLeave: () => { if (!listSticky) schedulePinHide(); },
                        onReady: (div) => {
                            div.querySelectorAll('.sup-pin-pick').forEach((btn) => {
                                btn.onclick = (ev) => {
                                    ev.preventDefault();
                                    ev.stopPropagation();
                                    ignoreMapClick = true;
                                    listSticky = true;
                                    pinSticky = true;
                                    openPinDetail(items[Number(btn.dataset.pinIdx)], marker);
                                };
                            });
                        },
                    });
                    pinStack.setMap(googleMap);
                }

                function openPinDetail(pin, marker) {
                    hidePinCard();
                    hideMotoTip();
                    pinCard = mountFloat(pinCardHtml(pin), marker, {
                        place: 'above',
                        onKeep: () => clearTimeout(pinHoverTimer),
                        onLeave: () => { if (!pinSticky) schedulePinHide(); },
                        onReady: (div) => {
                            const close = div.querySelector('.sup-card-close');
                            if (!close) return;
                            close.onclick = (ev) => {
                                ev.preventDefault();
                                ev.stopPropagation();
                                ignoreMapClick = true;
                                pinSticky = false;
                                hidePinCard();
                            };
                        },
                    });
                    pinCard.setMap(googleMap);
                }

                function bindPinOpen(marker, pin) {
                    const isMoto = pin.kind === 'moto';
                    const items = listPins(pin.items || pin.nearby || [pin]);
                    const showList = items.length > 1;
                    marker.addListener('mouseover', () => {
                        clearTimeout(pinHoverTimer);
                        if (isMoto) {
                            showMotoTip(marker, pin);
                            return;
                        }
                        if (showList) {
                            if (!(listSticky && listAnchor === marker)) showPinList(items, marker);
                            return;
                        }
                        if (!pinSticky) openPinDetail(items[0] || pin, marker);
                    });
                    marker.addListener('mouseout', () => schedulePinHide());
                    marker.addListener('click', () => {
                        ignoreMapClick = true;
                        clearTimeout(pinHoverTimer);
                        hideMotoTip();
                        if (showList) {
                            if (listSticky && listAnchor === marker) {
                                closePinPreview(true);
                                return;
                            }
                            pinSticky = false;
                            hidePinCard();
                            listSticky = true;
                            listAnchor = marker;
                            showPinList(items, marker);
                            return;
                        }
                        listSticky = false;
                        listAnchor = null;
                        hidePinStack();
                        pinSticky = true;
                        openPinDetail(items[0] || pin, marker);
                    });
                }

                function markerIconFor(pin, count) {
                    if (pin.kind === 'review') return pinIcon(pin.novelty ? 'novelty' : 'review', count);
                    if (pin.kind === 'moto') return iconMoto();
                    if (pinSvgs[pin.kind]) return pinIcon(pin.kind, count);
                    return circleIcon('#6366f1');
                }

                function placePin(map, pin) {
                    if (pin?.lat == null || pin?.lng == null) return;
                    const pos = { lat: Number(pin.lat), lng: Number(pin.lng) };
                    if (pin.kind === 'cluster') {
                        const listed = listPins(pin.items);
                        const face = clusterFace(pin.items);
                        const marker = addOverlay(new google.maps.Marker({
                            map,
                            position: pos,
                            zIndex: 22,
                            title: pin.title,
                            icon: markerIconFor(face, listed.length),
                            opacity: 1,
                        }));
                        bindPinOpen(marker, pin);
                        return;
                    }
                    if (pin.kind === 'client' || pin.kind === 'installation') {
                        const color = pin.kind === 'installation' ? '#22d3ee' : '#6366f1';
                        const marker = addOverlay(new google.maps.Marker({
                            map, position: pos, title: pin.title, zIndex: 1, icon: circleIcon(color),
                        }));
                        bindPinOpen(marker, pin);
                        return;
                    }
                    const extras = {};
                    if (pin.kind === 'moto') {
                        extras.opacity = pin.opacity;
                        extras.label = pin.label;
                    }
                    const marker = addOverlay(new google.maps.Marker({
                        map,
                        position: pos,
                        zIndex: pin.kind === 'review' || pin.kind === 'alarm' || pin.kind === 'support' ? 14
                            : (pin.kind === 'moto' ? 5 : 6),
                        title: pin.kind === 'moto' ? '' : (pin.title || ''),
                        icon: markerIconFor(pin),
                        ...extras,
                    }));
                    bindPinOpen(marker, pin);
                }

                function collectClientPins() {
                    const clientPins = clients.filter((row) => row.lat != null && row.lng != null).map((row) => ({
                        kind: 'client',
                        lat: row.lat,
                        lng: row.lng,
                        title: row.name,
                        row: { client: row.name },
                    }));
                    const sitePins = installations.filter((row) => row.lat != null && row.lng != null).map((row) => ({
                        kind: 'installation',
                        lat: row.lat,
                        lng: row.lng,
                        title: row.name,
                        row: { client: row.client || row.name, sheet_url: row.url || '' },
                    }));

                    return clientPins.concat(sitePins);
                }

                function collectReviewPins(shiftId) {
                    return reviews.filter((row) => row.lat != null && row.lng != null && (!shiftId || Number(row.shift_id) === Number(shiftId))).map((row) => ({
                        kind: 'review',
                        lat: row.lat,
                        lng: row.lng,
                        title: (row.client || 'Revista') + (row.novelty ? ' · novedad' : ''),
                        novelty: row.novelty,
                        row,
                    }));
                }

                function collectEventPins(shiftId) {
                    return events.filter((row) => row.lat != null && row.lng != null && (!shiftId || Number(row.shift_id) === Number(shiftId))).map((row) => ({
                        kind: row.kind,
                        lat: row.lat,
                        lng: row.lng,
                        title: (row.title || pinKindLabel(row.kind)) + (row.client ? ' · ' + row.client : ''),
                        row,
                    }));
                }

                function collectTrailPins(row, opts) {
                    const pins = [];
                    if (row.start) {
                        pins.push({
                            kind: 'start',
                            lat: row.start.lat,
                            lng: row.start.lng,
                            user: row.user,
                            title: (row.user || 'Inicio') + ' · inicio',
                        });
                    }
                    (row.stops || []).forEach((stop) => {
                        pins.push({
                            kind: 'stop',
                            lat: stop.lat,
                            lng: stop.lng,
                            title: stop.label || ('Parado ' + stop.minutes + ' min'),
                        });
                    });
                    if (opts.current && row.end) {
                        const label = row.online_label || (row.online ? 'En línea' : 'Sin señal');
                        pins.push({
                            kind: 'moto',
                            lat: row.end.lat,
                            lng: row.end.lng,
                            user: row.user,
                            signal: row.signal,
                            online: row.online,
                            online_label: label,
                            title: '',
                            opacity: signalOf(row) === 'no_signal' ? 0.7 : 1,
                            label: {
                                text: label,
                                color: signalColor(row),
                                fontSize: '11px',
                                fontWeight: '700',
                            },
                        });
                    }
                    if (opts.flag && row.end) {
                        pins.push({
                            kind: 'flag',
                            lat: row.end.lat,
                            lng: row.end.lng,
                            title: (row.user || 'Supervisor') + ' · cierre',
                        });
                    }
                    return pins;
                }

                function drawPath(map, bounds, row, opts) {
                    let any = false;
                    const path = (row.path || []).map((p) => ({ lat: p.lat, lng: p.lng }));
                    if (path.length > 1) {
                        addOverlay(new google.maps.Polyline({
                            map,
                            path,
                            strokeColor: opts.street ? '#3b82f6' : '#f59e0b',
                            strokeOpacity: 0.92,
                            strokeWeight: opts.street ? 5 : 4,
                        }));
                        path.forEach((p) => { any = extend(bounds, p) || any; });
                    }
                    return any;
                }

                function drawPins(map, bounds, pins) {
                    let any = false;
                    clusterPins(pins, 50).forEach((pin) => {
                        placePin(map, pin);
                        any = extend(bounds, pin) || any;
                    });
                    return any;
                }

                function paintLive(fit) {
                    clearOverlays();
                    const bounds = new google.maps.LatLngBounds();
                    let hasPoint = false;
                    const pins = [];
                    if (selectedLiveShiftId !== null && !live.some((row) => Number(row.shift_id) === Number(selectedLiveShiftId))) {
                        selectedLiveShiftId = null;
                    }
                    const selected = selectedLiveShiftId !== null
                        ? live.find((row) => Number(row.shift_id) === Number(selectedLiveShiftId))
                        : null;
                    if (selected) {
                        hasPoint = drawPath(googleMap, bounds, selected, { street: false });
                        pins.push(...collectTrailPins(selected, { current: true, flag: false }));
                        pins.push(...collectReviewPins(selected.shift_id));
                        pins.push(...collectEventPins(selected.shift_id));
                    } else {
                        if (!pendingOnly) {
                            live.forEach((row) => {
                                pins.push(...collectTrailPins(row, { current: true, flag: false }).filter((pin) => pin.kind === 'moto'));
                            });
                            pins.push(...collectReviewPins(null));
                            pins.push(...collectEventPins(null));
                        }
                        hasPoint = drawCoveragePins(googleMap, bounds, collectCoveragePins()) || hasPoint;
                    }
                    hasPoint = drawPins(googleMap, bounds, pins) || hasPoint;
                    if (hasPoint && fit) {
                        googleMap.fitBounds(bounds, 48);
                        google.maps.event.addListenerOnce(googleMap, 'idle', () => {
                            if (googleMap.getZoom() > 17) googleMap.setZoom(17);
                        });
                    }
                    renderLiveList(live);
                    renderCoverageChrome();
                }

                async function paintHistory(shiftId) {
                    clearOverlays();
                    const bounds = new google.maps.LatLngBounds();
                    let hasPoint = false;
                    const row = shiftId
                        ? history.find((item) => Number(item.shift_id) === Number(shiftId))
                        : null;
                    if (row) {
                        let trailRow = row;
                        let street = false;
                        try {
                            const res = await fetch(snappedRouteUrl(row.shift_id), { headers: { Accept: 'application/json' } });
                            if (res.ok) {
                                const data = await res.json();
                                if (Array.isArray(data.path) && data.path.length) {
                                    trailRow = Object.assign({}, row, {
                                        path: data.path,
                                        start: data.start || null,
                                        end: data.end || null,
                                        stops: Array.isArray(data.stops) ? data.stops : [],
                                    });
                                    street = Boolean(data.snapped);
                                }
                            }
                        } catch (e) {}
                        const path = trailRow.path || [];
                        if (!trailRow.start && path.length) trailRow.start = path[0];
                        if (!trailRow.end && path.length) trailRow.end = path[path.length - 1];
                        hasPoint = drawPath(googleMap, bounds, trailRow, { street });
                        const pins = collectTrailPins(trailRow, { current: false, flag: true })
                            .concat(collectReviewPins(row.shift_id))
                            .concat(collectEventPins(row.shift_id));
                        hasPoint = drawPins(googleMap, bounds, pins) || hasPoint;
                    } else {
                        hasPoint = drawPins(googleMap, bounds, collectClientPins());
                    }
                    if (hasPoint) googleMap.fitBounds(bounds, 48);
                    document.querySelectorAll('.supervision-trail-pick').forEach((btn) => {
                        btn.classList.toggle('bg-slate-800', Number(btn.dataset.shiftId) === Number(row?.shift_id));
                        btn.classList.toggle('text-white', Number(btn.dataset.shiftId) === Number(row?.shift_id));
                    });
                }

                function syncMapTypeButtons(type) {
                    document.querySelectorAll('.supervision-map-type-btn').forEach((btn) => {
                        const active = btn.dataset.mapType === type;
                        btn.classList.toggle('bg-indigo-600/80', active);
                        btn.classList.toggle('text-white', active);
                        btn.classList.toggle('text-slate-400', !active);
                    });
                }

                window.initSupervisionMap = function () {
                    try {
                    if (!mapEl || !window.google?.maps) {
                        fallback?.classList.remove('hidden');
                        return;
                    }
                    const center = googleMaps.center || { lat: 4.57, lng: -74.29 };
                    googleMap = new google.maps.Map(mapEl, {
                        center,
                        zoom: googleMaps.zoom || 12,
                        mapTypeId: google.maps.MapTypeId.HYBRID,
                        mapTypeControl: false,
                        streetViewControl: false,
                        clickableIcons: false,
                        styles: [
                            { featureType: 'poi', stylers: [{ visibility: 'off' }] },
                            { featureType: 'transit', elementType: 'labels.icon', stylers: [{ visibility: 'off' }] },
                        ],
                    });
                    googleMap.addListener('click', () => {
                        if (ignoreMapClick) {
                            ignoreMapClick = false;
                            return;
                        }
                        closePinPreview(true);
                    });
                    syncMapTypeButtons('satellite');
                    document.querySelectorAll('.supervision-map-type-btn').forEach((btn) => {
                        btn.addEventListener('click', () => {
                            const type = btn.dataset.mapType || 'satellite';
                            googleMap.setMapTypeId(type === 'terrain'
                                ? google.maps.MapTypeId.TERRAIN
                                : google.maps.MapTypeId.HYBRID);
                            syncMapTypeButtons(type);
                        });
                    });

                    if (activeTab === 'history') {
                        paintHistory(null);
                        document.querySelectorAll('.supervision-trail-pick').forEach((btn) => {
                            btn.addEventListener('click', () => paintHistory(btn.dataset.shiftId));
                        });
                        return;
                    }

                    document.getElementById('supervision-live-list')?.addEventListener('click', (ev) => {
                        const link = ev.target.closest('.js-sheet-link');
                        if (link) return;
                        const row = ev.target.closest('.supervision-live-pick');
                        if (!row || !googleMap) return;
                        const id = Number(row.dataset.shiftId);
                        selectedLiveShiftId = selectedLiveShiftId === id ? null : id;
                        paintLive(true);
                    });

                    document.getElementById('supervision-show-all')?.addEventListener('click', () => {
                        selectedLiveShiftId = null;
                        paintLive(true);
                    });
                    document.querySelectorAll('.supervision-points-btn').forEach((btn) => {
                        btn.addEventListener('click', () => {
                            pointsMode = btn.dataset.points === 'installation' ? 'installation' : 'client';
                            selectedLiveShiftId = null;
                            paintLive(true);
                        });
                    });
                    document.getElementById('supervision-pending-toggle')?.addEventListener('change', (ev) => {
                        pendingOnly = Boolean(ev.target.checked);
                        selectedLiveShiftId = null;
                        paintLive(true);
                    });

                    paintLive(true);
                    liveFitted = true;
                    clearInterval(livePollTimer);
                    livePollTimer = setInterval(async () => {
                        if (pinSticky || listSticky || pinCard) return;
                        try {
                            const res = await fetch(liveFeedUrl, { headers: { Accept: 'application/json' } });
                            if (!res.ok) return;
                            const data = await res.json();
                            live = data.live || [];
                            reviews = data.reviews || [];
                            events = data.events || [];
                            coverage = data.coverage || coverage;
                            paintLive(!liveFitted);
                            liveFitted = true;
                        } catch (e) {}
                    }, 10000);
                    } catch (e) {
                        fallback?.classList.remove('hidden');
                    }
                };

                if (!googleMaps.api_key) {
                    fallback?.classList.remove('hidden');
                    return;
                }
                const script = document.createElement('script');
                script.src = `https://maps.googleapis.com/maps/api/js?key=${googleMaps.api_key}&callback=initSupervisionMap`;
                script.async = true;
                script.defer = true;
                script.onerror = () => fallback?.classList.remove('hidden');
                document.head.appendChild(script);
            })();
        </script>
    @endpush
</x-company-layout>
