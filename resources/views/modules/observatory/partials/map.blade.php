@php
    $map = $map ?? ['google_maps' => ['api_key' => null], 'sites' => []];
    $maps = $map['google_maps'] ?? [];
    $sites = $map['sites'] ?? [];
    $points = $map['points'] ?? [];
    $showLegend = $showLegend ?? false;
    $mapCanvasClass = $mapCanvasClass ?? 'h-80 xl:h-full xl:min-h-[26rem]';
    $types = $map['types'] ?? [];
    $boardMap = $boardMap ?? false;
    $kpis = $kpis ?? [];
    $kpiLive = $kpiLive ?? false;
@endphp
<div class="obs-card overflow-hidden h-full {{ ($folioFocus ?? false) ? 'min-h-0' : 'min-h-80' }} flex flex-col">
    @if (! empty($maps['api_key']))
        <div
            x-data="observatoryMap(@js([
                'sites' => $sites,
                'points' => $points,
                'shifts' => $map['shifts'] ?? [],
                'moto' => asset('images/ui/supervisor-moto.png').'?v='.(string) (@filemtime(public_path('images/ui/supervisor-moto.png')) ?: '1'),
                'trailUrl' => $trailUrl ?? '',
                'center' => $maps['center'] ?? ['lat' => 3.4372, 'lng' => -76.5225],
                'zoom' => $maps['zoom'] ?? 12,
                'comuna' => $map['comuna'] ?? '',
                'folio' => (bool) ($folioFocus ?? false),
                'comunas' => $map['comunas'] ?? [],
                'layerUrl' => $map['layer_url'] ?? '/geo/cali-comunas.geojson',
                'crimes' => $map['crimes'] ?? ['year' => (int) now()->year, 'years' => [], 'types' => [], 'byYear' => []],
                'reportTypes' => $types,
            ]))"
            data-obs-map
            class="relative flex-1 {{ ($folioFocus ?? false) ? 'min-h-0' : 'min-h-80' }} flex flex-col"
        >
            @if ($boardMap)
                <div class="shrink-0 flex flex-wrap items-center gap-x-3 gap-y-1 px-2.5 py-1.5 border-b border-slate-800 bg-slate-950/80">
                    @foreach ($kpis as $kpi)
                        <a href="{{ $kpi['url'] }}" class="inline-flex items-baseline gap-1 {{ $kpi['on'] ? 'text-white' : 'text-slate-400 hover:text-slate-200' }}">
                            <span class="text-[10px] uppercase tracking-wide">{{ $kpi['label'] }}</span>
                            <span class="text-sm font-semibold tabular-nums" style="color: {{ $kpi['tone'] }}" data-obs-kpi="{{ $kpi['key'] }}">{{ $kpi['count'] }}</span>
                        </a>
                    @endforeach
                    <div class="ml-auto flex gap-1">
                        <button type="button" class="h-7 px-2.5 rounded-md text-[11px] font-semibold"
                                :class="mapType === 'roadmap' ? 'bg-white text-slate-900' : 'bg-slate-900 text-slate-200 border border-slate-700'"
                                @click="setMapType('roadmap')">Mapa</button>
                        <button type="button" class="h-7 px-2.5 rounded-md text-[11px] font-semibold"
                                :class="mapType === 'satellite' ? 'bg-white text-slate-900' : 'bg-slate-900 text-slate-200 border border-slate-700'"
                                @click="setMapType('satellite')">Satélite</button>
                    </div>
                </div>
                <div class="relative min-h-0 flex-1">
                    <div x-ref="map" class="{{ $mapCanvasClass }} w-full h-full"></div>
                    <div class="pointer-events-none absolute inset-x-2 top-2 z-10 flex items-start justify-between gap-2">
                        <div class="pointer-events-auto flex flex-wrap items-center gap-1.5">
                            <span class="text-[11px] font-semibold text-white drop-shadow">Riesgos</span>
                            <div class="flex overflow-hidden rounded-md border border-slate-700">
                                <button type="button" class="h-7 px-2 text-[11px] font-semibold"
                                        :class="layer === 'riesgos' ? 'bg-white text-slate-900' : 'bg-slate-950/80 text-slate-200'"
                                        @click="setLayer('riesgos')">Exógeno</button>
                                <button type="button" class="h-7 px-2 text-[11px] font-semibold"
                                        :class="layer === 'reportes' ? 'bg-white text-slate-900' : 'bg-slate-950/80 text-slate-200'"
                                        @click="setLayer('reportes')">Endógeno</button>
                            </div>
                            <button type="button" class="h-7 px-2 rounded-md text-[11px] font-semibold"
                                    :class="mode === 'grouped' ? 'bg-white text-slate-900' : 'bg-slate-950/80 text-slate-200 border border-slate-700'"
                                    @click="setMode('grouped')">Agrupado</button>
                            <button type="button" class="h-7 px-2 rounded-md text-[11px] font-semibold"
                                    :class="mode === 'heat' ? 'bg-white text-slate-900' : 'bg-slate-950/80 text-slate-200 border border-slate-700'"
                                    @click="setMode('heat')">Mapa de calor</button>
                            <button type="button" class="h-7 px-2 rounded-md text-[11px] font-semibold"
                                    :class="mode === 'places' ? 'bg-white text-slate-900' : 'bg-slate-950/80 text-slate-200 border border-slate-700'"
                                    @click="setMode('places')"
                                    x-text="layer === 'riesgos' ? 'Comuna' : 'Instalaciones'"></button>
                        </div>
                        <button type="button" class="pointer-events-auto h-7 shrink-0 rounded-md border border-white/20 bg-slate-950/45 px-2.5 text-[11px] font-semibold text-white backdrop-blur-sm"
                                :class="catalogOpen ? 'border-white text-white' : ''"
                                @click="catalogOpen = !catalogOpen">Catálogo</button>
                    </div>
                    <div x-show="catalogOpen" x-cloak
                         class="absolute bottom-12 right-2 top-12 z-10 w-52 overflow-auto rounded-lg border border-white/25 p-2 sidebar-scroll"
                         style="background: rgb(15 23 42 / 0.28)">
                        <p class="px-1 pb-1 text-[10px] uppercase tracking-wide text-slate-300" x-text="layer === 'riesgos' ? 'Exógeno' : 'Endógeno'"></p>
                        <template x-for="type in legend()" :key="type.slug">
                            <button type="button"
                                    class="flex w-full items-center gap-1.5 rounded-md px-1.5 py-1 text-left text-[11px]"
                                    :class="typeFilter === type.slug ? 'bg-white/20 text-white' : 'text-slate-100 hover:bg-white/10'"
                                    @click="setTypeFilter(type.slug)">
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full" :style="'background:' + type.color"></span>
                                <span x-text="type.name"></span>
                            </button>
                        </template>
                        <p class="px-1 py-2 text-[11px] text-slate-300" x-show="!legend().length">Sin registros.</p>
                    </div>
                </div>
            @else
            <div class="shrink-0 flex flex-wrap items-center gap-2 px-2.5 py-1.5 border-b border-slate-800 bg-slate-950/80">
                <span class="text-[11px] font-semibold text-slate-400">Riesgos</span>
                <div class="flex rounded-md border border-slate-700 overflow-hidden">
                    <button type="button" class="h-7 px-2 text-[11px] font-semibold"
                            :class="layer === 'riesgos' ? 'bg-white text-slate-900' : 'bg-slate-900 text-slate-300'"
                            @click="setLayer('riesgos')">Exógeno</button>
                    <button type="button" class="h-7 px-2 text-[11px] font-semibold"
                            :class="layer === 'reportes' ? 'bg-white text-slate-900' : 'bg-slate-900 text-slate-300'"
                            @click="setLayer('reportes')">Endógeno</button>
                </div>
                <div class="flex flex-wrap gap-1">
                    <button type="button" class="h-7 px-2 rounded-md text-[11px] font-semibold"
                            :class="mode === 'grouped' ? 'bg-white text-slate-900' : 'bg-slate-900 text-slate-200 border border-slate-700'"
                            @click="setMode('grouped')">Agrupado</button>
                    <button type="button" class="h-7 px-2 rounded-md text-[11px] font-semibold"
                            :class="mode === 'heat' ? 'bg-white text-slate-900' : 'bg-slate-900 text-slate-200 border border-slate-700'"
                            @click="setMode('heat')">Mapa de calor</button>
                    <button type="button" class="h-7 px-2 rounded-md text-[11px] font-semibold"
                            :class="mode === 'places' ? 'bg-white text-slate-900' : 'bg-slate-900 text-slate-200 border border-slate-700'"
                            @click="setMode('places')"
                            x-text="layer === 'riesgos' ? 'Comuna' : 'Instalaciones'"></button>
                </div>
                <div class="ml-auto flex gap-1">
                    <button type="button" class="h-7 px-2.5 rounded-md text-[11px] font-semibold"
                            :class="mapType === 'roadmap' ? 'bg-white text-slate-900' : 'bg-slate-900 text-slate-200 border border-slate-700'"
                            @click="setMapType('roadmap')">Mapa</button>
                    <button type="button" class="h-7 px-2.5 rounded-md text-[11px] font-semibold"
                            :class="mapType === 'satellite' ? 'bg-white text-slate-900' : 'bg-slate-900 text-slate-200 border border-slate-700'"
                            @click="setMapType('satellite')">Satélite</button>
                </div>
            </div>
            @unless ($folioFocus ?? false)
            <div class="shrink-0 flex flex-wrap gap-1 px-2 py-1.5 border-b border-slate-800" x-show="legend().length">
                <template x-for="type in legend()" :key="type.slug">
                    <button type="button"
                            class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px]"
                            :class="typeFilter === type.slug ? 'border-white text-white' : 'border-slate-700 text-slate-300'"
                            @click="setTypeFilter(type.slug)">
                        <span class="h-1.5 w-1.5 rounded-full" :style="'background:' + type.color"></span>
                        <span x-text="type.name"></span>
                    </button>
                </template>
            </div>
            @endunless
            <div x-ref="map" class="{{ $mapCanvasClass }} w-full flex-1"></div>
            @endif
            @if ($showLegend)
                <p class="px-3 py-2 text-[11px] text-slate-500 border-t border-slate-800">
                    Ámbar nuevo · índigo en atención · gris cerrado. Calor = ubicación de cada reporte abierto.
                </p>
            @endif
        </div>
        @push('scripts')
            <script>
                window.initObservatoryMap = window.initObservatoryMap || function () {
                    window.__observatoryMapReady = true;
                };
            </script>
            <script>
                (function () {
                    const script = document.createElement('script');
                    script.src = 'https://maps.googleapis.com/maps/api/js?key={{ $maps['api_key'] }}&callback=initObservatoryMap';
                    script.async = true;
                    script.defer = true;
                    document.head.appendChild(script);
                })();
            </script>
        @endpush
    @elseif (count($sites) > 0 || count($points) > 0)
        <p class="p-4 text-xs text-slate-500">
            {{ count($sites) + count($points) }} puntos. Configura <code class="text-indigo-300">GOOGLE_MAPS_API_KEY</code> para ver el mapa.
        </p>
    @else
        <p class="p-4 text-sm text-slate-500">No hay instalaciones con coordenadas.</p>
    @endif
</div>
