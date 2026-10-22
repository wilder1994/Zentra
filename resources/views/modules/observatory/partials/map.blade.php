@php
    $map = $map ?? ['google_maps' => ['api_key' => null], 'sites' => []];
    $maps = $map['google_maps'] ?? [];
    $sites = $map['sites'] ?? [];
    $points = $map['points'] ?? [];
    $showLegend = $showLegend ?? false;
    $mapCanvasClass = $mapCanvasClass ?? 'h-80 xl:h-full xl:min-h-[26rem]';
    $types = $map['types'] ?? [];
@endphp
<div class="obs-card overflow-hidden h-full min-h-80 flex flex-col">
    @if (! empty($maps['api_key']))
        <div
            x-data="observatoryMap(@js([
                'sites' => $sites,
                'points' => $points,
                'center' => $maps['center'] ?? ['lat' => 3.4372, 'lng' => -76.5225],
                'zoom' => $maps['zoom'] ?? 12,
                'comuna' => $map['comuna'] ?? '',
                'comunas' => $map['comunas'] ?? [],
                'layerUrl' => $map['layer_url'] ?? '/geo/cali-comunas.geojson',
            ]))"
            class="relative flex-1 min-h-80 flex flex-col"
        >
            <div class="shrink-0 flex flex-wrap items-center gap-2 px-2.5 py-1.5 border-b border-slate-800 bg-slate-950/80">
                <div class="flex flex-wrap gap-1">
                    <button type="button" class="h-7 px-2 rounded-md text-[11px] font-semibold"
                            :class="mode === 'pins' ? 'bg-white text-slate-900' : 'bg-slate-900 text-slate-200 border border-slate-700'"
                            @click="setMode('pins')">Pines</button>
                    <button type="button" class="h-7 px-2 rounded-md text-[11px] font-semibold"
                            :class="mode === 'heat_installation' ? 'bg-white text-slate-900' : 'bg-slate-900 text-slate-200 border border-slate-700'"
                            @click="setMode('heat_installation')">Calor instalación</button>
                    <button type="button" class="h-7 px-2 rounded-md text-[11px] font-semibold"
                            :class="mode === 'heat_risk' ? 'bg-white text-slate-900' : 'bg-slate-900 text-slate-200 border border-slate-700'"
                            @click="setMode('heat_risk')">Calor riesgo</button>
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
            @if ($types !== [])
                <div class="shrink-0 flex flex-wrap gap-1 px-2 py-1.5 border-b border-slate-800">
                    @foreach ($types as $type)
                        <button type="button"
                                class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px]"
                                :class="typeFilter === @js($type['slug']) ? 'border-white text-white' : 'border-slate-700 text-slate-300'"
                                @click="setTypeFilter(@js($type['slug']))">
                            <span class="h-1.5 w-1.5 rounded-full" style="background: {{ $type['color'] }}"></span>
                            {{ $type['name'] }}
                        </button>
                    @endforeach
                </div>
            @endif
            <div x-ref="map" class="{{ $mapCanvasClass }} w-full flex-1"></div>
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
            <script src="https://maps.googleapis.com/maps/api/js?key={{ $maps['api_key'] }}&callback=initObservatoryMap&loading=async" async defer></script>
        @endpush
    @elseif (count($sites) > 0 || count($points) > 0)
        <p class="p-4 text-xs text-slate-500">
            {{ count($sites) + count($points) }} puntos. Configura <code class="text-indigo-300">GOOGLE_MAPS_API_KEY</code> para ver el mapa.
        </p>
    @else
        <p class="p-4 text-sm text-slate-500">No hay instalaciones con coordenadas.</p>
    @endif
</div>
