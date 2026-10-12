@php
    $a = $analytics;
    $kpis = $a['kpis'];
    $fmtMoney = fn (float $n) => '$'.number_format($n, 0, ',', '.');
    $fmtMrr = fn (float $n) => $n >= 1_000_000
        ? '$'.number_format($n / 1_000_000, 1, ',', '.').'M'
        : $fmtMoney($n);

    $portfolioColors = ['#34d399', '#fbbf24', '#f87171', '#f59e0b', '#64748b', '#334155'];
    $portfolioTotal = max(1, collect($a['portfolio_status'])->sum('value'));
    $mapMarkersJson = json_encode($a['map_markers'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $googleMapsJson = json_encode($a['google_maps'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
@endphp

<x-admin-layout title="Resumen plataforma">
    <div class="flex flex-col gap-4" x-data="{ mapLayer: 'empresa' }" x-effect="window.platformMapSetLayer?.(mapLayer)">
        {{-- Fila superior: mapa + KPIs + cartera --}}
        <div class="grid grid-cols-1 xl:grid-cols-[1.2fr_1fr] gap-4 items-stretch">
            <section class="rounded-lg border border-slate-800 bg-slate-900/80 overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-800 flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-white">Distribución geográfica</h3>
                    <span class="text-[10px] text-slate-500">Google Maps API</span>
                </div>
                <div class="p-4 space-y-3">
                    <div class="flex items-center gap-5">
                        @foreach (['empresa' => 'Empresa', 'clientes' => 'Clientes'] as $layer => $label)
                            <button type="button"
                                    class="inline-flex items-center gap-2 text-sm"
                                    @click="mapLayer = '{{ $layer }}'">
                                <span class="w-4 h-4 rounded-full border-2 flex items-center justify-center transition"
                                      :class="mapLayer === '{{ $layer }}' ? 'border-violet-500 bg-violet-500' : 'border-slate-600 bg-slate-900'">
                                    <span class="w-1.5 h-1.5 rounded-full bg-white" x-show="mapLayer === '{{ $layer }}'"></span>
                                </span>
                                <span :class="mapLayer === '{{ $layer }}' ? 'text-white font-semibold' : 'text-slate-400'">{{ $label }}</span>
                            </button>
                        @endforeach
                    </div>
                    <div id="platform-map" class="w-full h-[300px] rounded-lg border border-slate-800 bg-slate-950/60 overflow-hidden relative">
                        <div id="platform-map-fallback" class="absolute inset-0 flex items-center justify-center text-center p-6 text-sm text-slate-500 hidden">
                            <div>
                                <p class="text-slate-300 font-medium mb-1">Mapa no disponible</p>
                                <p>Configura <code class="text-violet-300">GOOGLE_MAPS_API_KEY</code> en tu archivo <code class="text-slate-400">.env</code>.</p>
                            </div>
                        </div>
                    </div>
                    <p class="text-[10px] text-slate-600">Fuente: empresas de seguridad y conjuntos · Colombia</p>
                </div>
            </section>

            <div class="flex flex-col gap-4 h-full">
                <div class="grid grid-cols-2 gap-3 shrink-0">
                    <div class="rounded-lg border border-slate-800 bg-slate-900/80 p-4">
                        <p class="text-[10px] uppercase tracking-wide text-slate-500">Empresas activas</p>
                        <p class="mt-1 text-2xl font-bold text-white tabular-nums">{{ $kpis['active_companies'] }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-800 bg-slate-900/80 p-4">
                        <p class="text-[10px] uppercase tracking-wide text-slate-500">Conjuntos operativos</p>
                        <p class="mt-1 text-2xl font-bold text-emerald-400 tabular-nums">{{ $kpis['operational_clients'] }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-800 bg-slate-900/80 p-4">
                        <p class="text-[10px] uppercase tracking-wide text-slate-500">MRR estimado</p>
                        <p class="mt-1 text-2xl font-bold text-white tabular-nums">{{ $fmtMrr($kpis['mrr']) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-800 bg-slate-900/80 p-4">
                        <p class="text-[10px] uppercase tracking-wide text-slate-500">Tasa retención</p>
                        <p class="mt-1 text-2xl font-bold text-emerald-400 tabular-nums">{{ $kpis['retention_rate'] }}%</p>
                    </div>
                </div>

                <section class="rounded-lg border border-slate-800 bg-slate-900/80 flex-1 flex flex-col min-h-0">
                    <div class="px-4 py-3 border-b border-slate-800 shrink-0">
                        <h3 class="text-sm font-semibold text-white">Estado de cartera</h3>
                    </div>
                    <div class="flex-1 flex items-center justify-between gap-3 px-3 py-2 min-h-0">
                        <div class="flex flex-col justify-center gap-1.5 pl-1 min-w-0">
                            @foreach ($a['portfolio_status'] as $index => $item)
                                @php $pct = round(($item['value'] / $portfolioTotal) * 100); @endphp
                                <div class="flex items-center gap-2 text-xs sm:text-sm">
                                    <span class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $portfolioColors[$index] ?? '#64748b' }}"></span>
                                    <span class="text-slate-300 truncate">
                                        {{ $item['label'] }} · <strong class="text-white tabular-nums">{{ $pct }}%</strong>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                        <div class="relative w-[156px] h-[156px] shrink-0 pr-1">
                            <canvas id="portfolioChart" aria-label="Estado de cartera"></canvas>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        {{-- Planes y ciclo --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <section class="rounded-lg border border-slate-800 bg-slate-900/80 md:col-span-2">
                <div class="px-4 py-3 border-b border-slate-800 flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-white">Empresas por plan</h3>
                    <a href="{{ route('admin.pricing.edit') }}" class="text-[11px] text-violet-300 hover:text-violet-200">Planes</a>
                </div>
                <div class="p-4">
                    <div class="relative h-[100px] w-full min-w-0">
                        <canvas id="plansChart"></canvas>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-slate-800 bg-slate-900/80">
                <div class="px-4 py-3 border-b border-slate-800">
                    <h3 class="text-sm font-semibold text-white">Ciclo de facturación</h3>
                </div>
                <div class="p-4">
                    <div class="relative h-[100px] w-full min-w-0">
                        <canvas id="billingCycleChart"></canvas>
                    </div>
                </div>
            </section>
        </div>

        {{-- Grid facturación 2×2 --}}
        <div class="grid grid-cols-1 lg:grid-cols-[1.1fr_1fr] gap-4 items-stretch">
            <section class="rounded-lg border border-slate-800 bg-slate-900/80 lg:row-span-2 flex flex-col min-h-0 overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-800 flex items-center justify-between shrink-0">
                    <h3 class="text-sm font-semibold text-white">Empresas por facturación</h3>
                    <span class="text-[10px] text-slate-500">TOP 5</span>
                </div>
                <div class="flex-1 min-h-0 overflow-y-auto overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-slate-900/95 backdrop-blur-sm text-[10px] uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium">#</th>
                                <th class="px-4 py-2 text-left font-medium">Empresa</th>
                                <th class="px-4 py-2 text-right font-medium">MRR</th>
                                <th class="px-4 py-2 text-right font-medium">%</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @forelse ($a['top_billing'] as $index => $row)
                                <tr class="hover:bg-slate-800/30">
                                    <td class="px-4 py-2.5 text-slate-500 tabular-nums">{{ $index + 1 }}</td>
                                    <td class="px-4 py-2.5">
                                        <a href="{{ route('admin.companies.show', $row['id']) }}" class="text-slate-200 hover:text-violet-300 font-medium">
                                            {{ $row['name'] }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-slate-300 tabular-nums">{{ $fmtMrr($row['mrr']) }}</td>
                                    <td class="px-4 py-2.5 text-right text-slate-400 tabular-nums">{{ $row['share'] }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-slate-500">Sin datos de facturación.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-lg border border-slate-800 bg-slate-900/80">
                <div class="px-4 py-3 border-b border-slate-800">
                    <h3 class="text-sm font-semibold text-white">KPIs comerciales</h3>
                </div>
                <div class="p-4 flex justify-between gap-2">
                    @php $commercial = $a['commercial_kpis']; @endphp
                    @foreach ([
                        ['label' => 'Cobertura nacional', 'value' => $commercial['coverage'].'%', 'ring' => 'border-t-violet-500 border-r-violet-500'],
                        ['label' => 'Crecimiento MRR', 'value' => ($commercial['mrr_growth'] >= 0 ? '+' : '').$commercial['mrr_growth'].'%', 'ring' => 'border-t-emerald-500 border-r-emerald-500'],
                        ['label' => 'Churn mensual', 'value' => $commercial['churn'].'%', 'ring' => 'border-t-sky-400 border-r-sky-400'],
                    ] as $gauge)
                        <div class="flex-1 text-center">
                            <div class="mx-auto w-[68px] h-[68px] rounded-full border-[5px] border-slate-700 {{ $gauge['ring'] }} flex items-center justify-center">
                                <span class="text-sm font-semibold text-white tabular-nums">{{ $gauge['value'] }}</span>
                            </div>
                            <p class="mt-2 text-[10px] text-slate-500 leading-tight">{{ $gauge['label'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-lg border border-slate-800 bg-slate-900/80">
                <div class="px-4 py-3 border-b border-slate-800">
                    <h3 class="text-sm font-semibold text-white">Nuevos vs retenidos</h3>
                </div>
                <div class="p-4">
                    <div class="relative h-[125px] w-full min-w-0">
                        <canvas id="growthChart"></canvas>
                    </div>
                </div>
            </section>
        </div>

        {{-- Tendencia MRR --}}
        <section class="rounded-lg border border-slate-800 bg-slate-900/80 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-800 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-white">Tendencia de ingresos plataforma</h3>
                <span class="text-[10px] text-slate-500">Últimos 12 meses</span>
            </div>
            <div class="p-4">
                <div class="relative h-[220px] w-full min-w-0">
                    <canvas id="mrrTrendChart"></canvas>
                </div>
                <p class="mt-2 text-[10px] text-slate-600">Eje izquierdo: MRR (millones COP) · Eje derecho: conjuntos activos</p>
            </div>
        </section>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            const chartDefaults = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#94a3b8', boxWidth: 10, font: { size: 10 } } } },
                scales: {
                    x: { ticks: { color: '#64748b', font: { size: 10 } }, grid: { color: 'rgba(51,65,85,0.4)' } },
                    y: { ticks: { color: '#64748b', font: { size: 10 } }, grid: { color: 'rgba(51,65,85,0.4)' } },
                },
            };

            const portfolio = @json($a['portfolio_status']);
            new Chart(document.getElementById('portfolioChart'), {
                type: 'doughnut',
                data: {
                    labels: portfolio.map(p => p.label),
                    datasets: [{
                        data: portfolio.map(p => p.value),
                        backgroundColor: @json($portfolioColors),
                        borderWidth: 0,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '55%',
                    plugins: { legend: { display: false } },
                },
            });

            const plansAccess = @json($a['plans_access']);
            const plansSupervision = @json($a['plans_supervision']);
            new Chart(document.getElementById('plansChart'), {
                type: 'bar',
                data: {
                    labels: plansAccess.map(s => s.label),
                    datasets: [
                        { label: 'Accesos', data: plansAccess.map(s => s.value), backgroundColor: '#6366f1', borderRadius: 4 },
                        { label: 'Supervisión', data: plansSupervision.map(s => s.value), backgroundColor: '#f59e0b', borderRadius: 4 },
                    ],
                },
                options: { ...chartDefaults, scales: { ...chartDefaults.scales, y: { ...chartDefaults.scales.y, ticks: { ...chartDefaults.scales.y.ticks, precision: 0 } } } },
            });

            const cycles = @json($a['billing_cycles']);
            new Chart(document.getElementById('billingCycleChart'), {
                type: 'bar',
                data: {
                    labels: cycles.map(c => c.label),
                    datasets: [{
                        label: 'Empresas',
                        data: cycles.map(c => c.value),
                        backgroundColor: '#34d399',
                        borderRadius: 4,
                    }],
                },
                options: {
                    ...chartDefaults,
                    indexAxis: 'y',
                    plugins: { legend: { display: false } },
                },
            });

            const growth = @json($a['growth_monthly']);
            new Chart(document.getElementById('growthChart'), {
                type: 'bar',
                data: {
                    labels: growth.labels,
                    datasets: [
                        { label: 'Nuevos', data: growth.nuevos, backgroundColor: '#6366f1', borderRadius: 3 },
                        { label: 'Retenidos', data: growth.retenidos, backgroundColor: '#34d399', borderRadius: 3 },
                    ],
                },
                options: chartDefaults,
            });

            const mrrTrend = @json($a['mrr_trend']);
            const clientsTrend = @json($a['clients_trend']);
            new Chart(document.getElementById('mrrTrendChart'), {
                type: 'line',
                data: {
                    labels: mrrTrend.map(p => p.label),
                    datasets: [
                        {
                            label: 'MRR (millones COP)',
                            data: mrrTrend.map(p => p.value),
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99,102,241,0.15)',
                            fill: true,
                            tension: 0.3,
                            yAxisID: 'y',
                        },
                        {
                            label: 'Conjuntos activos',
                            data: clientsTrend.map(p => p.value),
                            borderColor: '#34d399',
                            backgroundColor: 'transparent',
                            tension: 0.3,
                            yAxisID: 'y1',
                        },
                    ],
                },
                options: {
                    ...chartDefaults,
                    scales: {
                        x: chartDefaults.scales.x,
                        y: { ...chartDefaults.scales.y, position: 'left' },
                        y1: { ...chartDefaults.scales.y, position: 'right', grid: { drawOnChartArea: false } },
                    },
                },
            });

            const mapMarkers = {!! $mapMarkersJson !!};
            const googleMaps = {!! $googleMapsJson !!};
            const mapEl = document.getElementById('platform-map');
            const fallbackEl = document.getElementById('platform-map-fallback');
            let mapInstance = null;
            let markerLayers = { empresa: [], clientes: [] };

            function renderMarkers(layer) {
                if (!mapInstance || !window.google?.maps) return;
                markerLayers.empresa.forEach(m => m.setMap(null));
                markerLayers.clientes.forEach(m => m.setMap(null));
                const pins = mapMarkers[layer] || [];
                const color = layer === 'empresa' ? '#8b5cf6' : '#38bdf8';
                markerLayers[layer] = pins.map(pin => new google.maps.Marker({
                    position: { lat: pin.lat, lng: pin.lng },
                    map: mapInstance,
                    title: pin.title,
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: 7,
                        fillColor: color,
                        fillOpacity: 1,
                        strokeColor: '#0f172a',
                        strokeWeight: 1.5,
                    },
                }));
            }

            window.platformMapSetLayer = renderMarkers;

            function initMap() {
                if (!googleMaps.api_key) {
                    fallbackEl.classList.remove('hidden');
                    return;
                }
                mapInstance = new google.maps.Map(mapEl, {
                    center: googleMaps.center,
                    zoom: googleMaps.zoom,
                    mapTypeId: google.maps.MapTypeId.HYBRID,
                    disableDefaultUI: true,
                    zoomControl: true,
                });
                renderMarkers('empresa');
            }

            if (googleMaps.api_key) {
                const script = document.createElement('script');
                script.src = `https://maps.googleapis.com/maps/api/js?key=${googleMaps.api_key}&callback=initPlatformMap`;
                script.async = true;
                script.defer = true;
                window.initPlatformMap = initMap;
                document.head.appendChild(script);
            } else {
                fallbackEl.classList.remove('hidden');
            }
        })();
    </script>
    @endpush
</x-admin-layout>
