@php
    $charts = $summary->charts;
    $grainLabel = ($charts['grain'] ?? 'day') === 'month' ? 'por mes' : 'por día';
    $chartsJson = json_encode($charts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
@endphp

<section class="space-y-4">
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
        <div class="rounded-lg border p-3 {{ $semaphoreClass }}">
            <p class="text-[10px] uppercase tracking-wide">Cumplimiento por puesto</p>
            <p class="text-2xl font-semibold tabular-nums">{{ $summary->coveragePercent !== null ? number_format($summary->coveragePercent, 1).'%' : '—' }}</p>
            <p class="text-[11px] opacity-80">{{ $summary->sitesVisited }} de {{ $summary->sitesContracted }} revistas esperadas</p>
            <p class="mt-0.5 text-[10px] opacity-70 leading-snug">Revistas contratadas por puesto en cada día de servicio (06:00 a 06:00). Por defecto 1.</p>
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-950/60 p-3">
            <p class="text-[10px] uppercase tracking-wide text-slate-500">Puestos cumplidos</p>
            <p class="text-2xl font-semibold text-white tabular-nums">{{ $summary->postsDone }} <span class="text-sm font-normal text-slate-500">de {{ $summary->postsTotal }}</span></p>
            <p class="text-[11px] text-slate-500">{{ $summary->postsTotal - $summary->postsDone }} con pendientes</p>
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-950/60 p-3">
            <p class="text-[10px] uppercase tracking-wide text-slate-500">Revistas</p>
            <p class="text-2xl font-semibold text-white tabular-nums">{{ $summary->reviews }}</p>
            <p class="text-[11px] {{ $summary->reviewsWithNovelty > 0 ? 'text-orange-300' : 'text-slate-500' }}">{{ $summary->reviewsWithNovelty }} con novedad</p>
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-950/60 p-3">
            <p class="text-[10px] uppercase tracking-wide text-slate-500">Km recorridos</p>
            <p class="text-2xl font-semibold text-amber-300 tabular-nums">{{ $summary->kmTraveled }}</p>
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-950/60 p-3">
            <p class="text-[10px] uppercase tracking-wide text-slate-500">Recomendaciones</p>
            <p class="text-2xl font-semibold text-white tabular-nums">{{ $summary->recommendations['total'] }}</p>
            <p class="text-[11px] text-slate-500">B {{ $summary->recommendations['low'] }} · M {{ $summary->recommendations['medium'] }} · A {{ $summary->recommendations['high'] }} · E {{ $summary->recommendations['extreme'] }}</p>
        </div>
    </div>

    @if ($summary->alerts !== [])
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Atención</span>
            @foreach ($summary->alerts as $alert)
                <span @class([
                    'rounded-full border px-2.5 py-0.5 text-[11px] font-medium',
                    'border-red-500/40 bg-red-500/15 text-red-300' => $alert['level'] === 'red',
                    'border-amber-500/40 bg-amber-500/15 text-amber-300' => $alert['level'] !== 'red',
                ])>{{ $alert['text'] }}</span>
            @endforeach
        </div>
    @endif

    <div class="grid lg:grid-cols-3 gap-3">
        <div class="rounded-lg border border-slate-800 bg-slate-900/80 p-3">
            <h3 class="text-sm font-semibold text-white">Revistas {{ $grainLabel }}</h3>
            <p class="text-[11px] text-slate-500">Visitas de supervisión guardadas.</p>
            <div class="h-40 mt-2"><canvas id="chart-reviews"></canvas></div>
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-900/80 p-3">
            <h3 class="text-sm font-semibold text-white">Km {{ $grainLabel }}</h3>
            <p class="text-[11px] text-slate-500">Odómetro de turnos del periodo.</p>
            <div class="h-40 mt-2"><canvas id="chart-km"></canvas></div>
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-900/80 p-3">
            <h3 class="text-sm font-semibold text-white">Novedad de revista</h3>
            <p class="text-[11px] text-slate-500">Con / sin novedad en la visita.</p>
            <div class="h-40 mt-2"><canvas id="chart-review-novelty"></canvas></div>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-3">
        <div class="rounded-lg border border-slate-800 bg-slate-900/80 p-3">
            <h3 class="text-sm font-semibold text-white">Inventario</h3>
            <p class="text-[11px] text-slate-500">Elementos: bueno / regular / malo.</p>
            <div class="h-40 mt-2"><canvas id="chart-inventory"></canvas></div>
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-900/80 p-3">
            <h3 class="text-sm font-semibold text-white">Libros de control</h3>
            <p class="text-[11px] text-slate-500">Ítems con y sin novedad.</p>
            <div class="h-40 mt-2"><canvas id="chart-books"></canvas></div>
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-900/80 p-3">
            <h3 class="text-sm font-semibold text-white">Carpetas</h3>
            <p class="text-[11px] text-slate-500">Completa vs con faltantes.</p>
            <div class="h-40 mt-2"><canvas id="chart-folders"></canvas></div>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-3">
        <div class="rounded-lg border border-slate-800 bg-slate-900/80 p-3">
            <h3 class="text-sm font-semibold text-white">Armamento</h3>
            <p class="text-[11px] text-slate-500">Inspecciones: solo revista, con aseo, novedad, permiso vencido.</p>
            <div class="h-40 mt-2"><canvas id="chart-weapons"></canvas></div>
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-900/80 p-3">
            <h3 class="text-sm font-semibold text-white">Recs por nivel</h3>
            <p class="text-[11px] text-slate-500">Matriz P×I. Extremo = score ≥ 17.</p>
            <div class="h-40 mt-2"><canvas id="chart-recs-level"></canvas></div>
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-900/80 p-3">
            <h3 class="text-sm font-semibold text-white">Recs por tipo de riesgo</h3>
            <p class="text-[11px] text-slate-500">Catálogo de la empresa.</p>
            <div class="h-40 mt-2"><canvas id="chart-recs-type"></canvas></div>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-3">
        <div class="rounded-lg border border-slate-800 bg-slate-900/80 p-3">
            <h3 class="text-sm font-semibold text-white">Alarmas por tipo</h3>
            <p class="text-[11px] text-slate-500">Prueba vs atención.</p>
            <div class="h-40 mt-2"><canvas id="chart-alarms-type"></canvas></div>
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-900/80 p-3">
            <h3 class="text-sm font-semibold text-white">Resultado de alarmas</h3>
            <p class="text-[11px] text-slate-500">Prueba OK/Falla · Atención real/falsa/no ubicada.</p>
            <div class="h-40 mt-2"><canvas id="chart-alarms-result"></canvas></div>
        </div>
        <div class="rounded-lg border border-slate-800 bg-slate-900/80 p-3">
            <h3 class="text-sm font-semibold text-white">Apoyos por tipo</h3>
            <p class="text-[11px] text-slate-500">
                Catálogo de la empresa.
                {{ (int) ($charts['supports_place']['site'] ?? 0) }} en sitio ·
                {{ (int) ($charts['supports_place']['road'] ?? 0) }} en vía.
            </p>
            <div class="h-40 mt-2"><canvas id="chart-supports"></canvas></div>
        </div>
    </div>

    @php
        $statusRow = [
            'red' => 'bg-red-500/10 hover:bg-red-500/15',
            'amber' => 'bg-amber-500/10 hover:bg-amber-500/15',
            'green' => 'bg-emerald-500/10 hover:bg-emerald-500/15',
        ];
        $statusDot = ['red' => 'bg-red-500', 'amber' => 'bg-amber-400', 'green' => 'bg-emerald-400'];
        $statusCount = collect($summary->byClient)->countBy('status');
    @endphp
    <div class="rounded-lg border border-slate-800 bg-slate-900/80 p-4">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h3 class="text-sm font-semibold text-white">Clientes · {{ count($summary->byClient) }}</h3>
            <p class="flex flex-wrap items-center gap-3 text-[11px] text-slate-400">
                <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-red-500"></span>Sin revista ({{ $statusCount['red'] ?? 0 }})</span>
                <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-400"></span>Con pendientes ({{ $statusCount['amber'] ?? 0 }})</span>
                <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-400"></span>Cumplido ({{ $statusCount['green'] ?? 0 }})</span>
                <span class="text-slate-500">Clic en un cliente: puestos pendientes.</span>
            </p>
        </div>
        <div class="mt-3 max-h-[26rem] overflow-y-auto">
            <table class="w-full text-xs">
                <thead class="text-[10px] uppercase text-slate-500 sticky top-0 z-10 bg-slate-900">
                    <tr>
                        <th class="text-left font-medium py-1.5 pl-2">Cliente</th>
                        <th class="text-right font-medium py-1.5">Instalaciones</th>
                        <th class="text-right font-medium py-1.5">Puestos</th>
                        <th class="text-right font-medium py-1.5">Cumplidos</th>
                        <th class="text-right font-medium py-1.5">Revistas</th>
                        <th class="text-right font-medium py-1.5">Novedad</th>
                        <th class="text-right font-medium py-1.5 pr-2">%</th>
                    </tr>
                </thead>
                @forelse ($summary->byClient as $row)
                    <tbody x-data="{ open: false }" class="text-slate-300">
                        <tr class="border-t border-slate-800/80 {{ $statusRow[$row['status']] }} {{ $row['pending'] !== [] ? 'cursor-pointer' : '' }}"
                            @if ($row['pending'] !== []) @click="open = !open" @endif>
                            <td class="py-1.5 pl-2 pr-2">
                                <span class="inline-flex items-center gap-2">
                                    <span class="h-2 w-2 shrink-0 rounded-full {{ $statusDot[$row['status']] }}"></span>
                                    <span class="text-slate-100">{{ $row['name'] }}</span>
                                    @if ($row['pending'] !== [])
                                        <svg class="h-3 w-3 text-slate-500 transition-transform" :class="open && 'rotate-90'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                    @endif
                                </span>
                            </td>
                            <td class="py-1.5 text-right tabular-nums">{{ $row['installations'] }}</td>
                            <td class="py-1.5 text-right tabular-nums">{{ $row['posts'] }}</td>
                            <td class="py-1.5 text-right tabular-nums">{{ $row['posts_done'] }}</td>
                            <td class="py-1.5 text-right tabular-nums">{{ $row['reviews'] }}</td>
                            <td class="py-1.5 text-right tabular-nums {{ $row['novelty'] > 0 ? 'text-orange-300 font-medium' : 'text-slate-500' }}">{{ $row['novelty'] }}</td>
                            <td class="py-1.5 pr-2 text-right tabular-nums">{{ $row['percent'] !== null ? number_format($row['percent'], 0).'%' : '—' }}</td>
                        </tr>
                        @if ($row['pending'] !== [])
                            <tr x-show="open" x-cloak>
                                <td colspan="7" class="bg-slate-950/60 px-4 py-2">
                                    <ul class="grid gap-x-6 gap-y-0.5 sm:grid-cols-2 lg:grid-cols-3 text-[11px] text-slate-400">
                                        @foreach ($row['pending'] as $pending)
                                            <li>
                                                <span class="text-slate-500">{{ $pending['installation'] }} ·</span>
                                                <span class="text-slate-200">{{ $pending['post'] }}</span>
                                                @if ($pending['expected'] > 1)
                                                    <span class="text-amber-300/90">· faltan {{ $pending['missing'] }} de {{ $pending['expected'] }} revistas</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                @empty
                    <tbody>
                        <tr><td colspan="7" class="py-4 text-slate-500">Sin puestos con servicio en el periodo.</td></tr>
                    </tbody>
                @endforelse
            </table>
        </div>
    </div>

    <div class="rounded-lg border border-slate-800 bg-slate-900/80 p-4">
        <h3 class="text-sm font-semibold text-white">Por supervisor · {{ count($summary->bySupervisor) }}</h3>
        <div class="mt-3 max-h-72 overflow-y-auto">
            <table class="w-full text-xs">
                <thead class="text-[10px] uppercase text-slate-500 sticky top-0 bg-slate-900">
                    <tr>
                        <th class="text-left font-medium py-1.5">Supervisor</th>
                        <th class="text-right font-medium py-1.5">Turnos</th>
                        <th class="text-right font-medium py-1.5">Revistas</th>
                        <th class="text-right font-medium py-1.5">Km</th>
                        <th class="text-right font-medium py-1.5">Alarmas</th>
                        <th class="text-right font-medium py-1.5">Apoyos</th>
                        <th class="text-right font-medium py-1.5">Registros</th>
                    </tr>
                </thead>
                <tbody class="text-slate-300">
                    @forelse ($summary->bySupervisor as $row)
                        <tr class="border-t border-slate-800">
                            <td class="py-1.5 pr-2 text-slate-100">{{ $row['name'] }}</td>
                            <td class="py-1.5 text-right tabular-nums">{{ $row['shifts'] }}</td>
                            <td class="py-1.5 text-right tabular-nums">{{ $row['reviews'] }}</td>
                            <td class="py-1.5 text-right tabular-nums">{{ $row['km'] }}</td>
                            <td class="py-1.5 text-right tabular-nums">{{ $row['alarms'] }}</td>
                            <td class="py-1.5 text-right tabular-nums">{{ $row['supports'] }}</td>
                            <td class="py-1.5 text-right tabular-nums text-slate-500">{{ $row['logs'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-4 text-slate-500">Sin actividad en el periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            const data = {!! $chartsJson !!};
            const labels = data.labels || [];
            const neon = {
                id: 'neonHairline',
                afterDatasetsDraw(chart) {
                    const { ctx } = chart;
                    chart.data.datasets.forEach((dataset, i) => {
                        const meta = chart.getDatasetMeta(i);
                        if (meta.type !== 'line' || !meta.dataset) return;
                        ctx.save();
                        ctx.shadowColor = dataset.borderColor;
                        ctx.shadowBlur = 12;
                        meta.dataset.draw(ctx);
                        ctx.restore();
                    });
                },
            };
            const axis = {
                ticks: { color: '#64748b', font: { size: 9 }, maxRotation: 0 },
                grid: { color: 'rgba(148,163,184,0.08)', drawBorder: false },
            };
            const legend = { position: 'bottom', labels: { color: '#94a3b8', boxWidth: 8, font: { size: 10 }, padding: 10 } };
            const lineOpts = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend, tooltip: { mode: 'index', intersect: false } },
                scales: { x: axis, y: { ...axis, beginAtZero: true, ticks: { ...axis.ticks, precision: 0 } } },
            };
            const barOpts = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend },
                scales: { x: axis, y: { ...axis, beginAtZero: true, ticks: { ...axis.ticks, precision: 0 } } },
            };
            function lineSet(label, values, color) {
                return {
                    label,
                    data: values,
                    borderColor: color,
                    backgroundColor: color + '1a',
                    borderWidth: 1.25,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    tension: 0.4,
                    fill: true,
                };
            }
            function bar(id, chartLabels, datasets, stacked) {
                const el = document.getElementById(id);
                if (!el) return;
                new Chart(el, {
                    type: 'bar',
                    data: { labels: chartLabels.length ? chartLabels : ['—'], datasets },
                    options: {
                        ...barOpts,
                        scales: {
                            x: { ...axis, stacked: !!stacked },
                            y: { ...axis, stacked: !!stacked, beginAtZero: true, ticks: { ...axis.ticks, precision: 0 } },
                        },
                    },
                });
            }
            function donut(id, chartLabels, values, colors) {
                const el = document.getElementById(id);
                if (!el) return;
                new Chart(el, {
                    type: 'doughnut',
                    data: {
                        labels: chartLabels,
                        datasets: [{ data: values, backgroundColor: colors, borderWidth: 0 }],
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend }, cutout: '62%' },
                });
            }

            const reviewsEl = document.getElementById('chart-reviews');
            if (reviewsEl) {
                new Chart(reviewsEl, {
                    type: 'line',
                    data: { labels, datasets: [lineSet('Revistas', data.reviews || [], '#fbbf24')] },
                    options: lineOpts,
                    plugins: [neon],
                });
            }
            const kmEl = document.getElementById('chart-km');
            if (kmEl) {
                new Chart(kmEl, {
                    type: 'line',
                    data: { labels, datasets: [lineSet('Km', data.km || [], '#38bdf8')] },
                    options: lineOpts,
                    plugins: [neon],
                });
            }
            const novEl = document.getElementById('chart-review-novelty');
            if (novEl) {
                new Chart(novEl, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [
                            lineSet('Con novedad', data.novelty_yes || [], '#f87171'),
                            lineSet('Sin novedad', data.novelty_no || [], '#34d399'),
                        ],
                    },
                    options: lineOpts,
                    plugins: [neon],
                });
            }

            const inv = data.inventory || {};
            bar('chart-inventory', ['Bueno', 'Regular', 'Malo'], [
                { label: 'Elementos', data: [inv.good || 0, inv.regular || 0, inv.bad || 0], backgroundColor: ['#34d399', '#fbbf24', '#f87171'] },
            ]);
            const books = data.books || {};
            bar('chart-books', ['Sin novedad', 'Con novedad'], [
                { label: 'Libros', data: [books.no || 0, books.yes || 0], backgroundColor: ['#34d399', '#fbbf24'] },
            ]);
            const folders = data.folders || {};
            bar('chart-folders', ['Completa', 'Faltantes'], [
                { label: 'Carpetas', data: [folders.complete || 0, folders.missing || 0], backgroundColor: ['#34d399', '#fbbf24'] },
            ]);

            const w = data.weapons || {};
            bar('chart-weapons', ['Solo revista', 'Con aseo', 'Novedad', 'Permiso vencido'], [
                { label: 'Armas', data: [w.inspection_only || 0, w.cleaned || 0, w.novelty || 0, w.expired || 0], backgroundColor: ['#38bdf8', '#34d399', '#fbbf24', '#f87171'] },
            ]);
            const recLevel = data.recs_by_level || {};
            donut('chart-recs-level', ['Bajo', 'Medio', 'Alto', 'Extremo'], [
                recLevel.low || 0, recLevel.medium || 0, recLevel.high || 0, recLevel.extreme || 0,
            ], ['#34d399', '#38bdf8', '#fbbf24', '#f87171']);
            const recTypes = data.recs_by_type || [];
            bar('chart-recs-type', recTypes.length ? recTypes.map((r) => r.name) : ['Sin datos'], [
                { label: 'Recs', data: recTypes.length ? recTypes.map((r) => r.total) : [0], backgroundColor: '#38bdf8' },
            ]);

            const alarmTypes = data.alarms_by_type || [];
            bar('chart-alarms-type', alarmTypes.length ? alarmTypes.map((r) => r.name) : ['Sin datos'], [
                { label: 'Prueba', data: alarmTypes.length ? alarmTypes.map((r) => r.test) : [0], backgroundColor: '#38bdf8' },
                { label: 'Atención', data: alarmTypes.length ? alarmTypes.map((r) => r.response) : [0], backgroundColor: '#fbbf24' },
            ], true);
            const ar = data.alarms_result || {};
            bar('chart-alarms-result', ['OK', 'Falla', 'Real', 'Falsa', 'No ubicada'], [
                { label: 'Alarmas', data: [ar.ok || 0, ar.fail || 0, ar.real || 0, ar.false_alarm || 0, ar.not_found || 0], backgroundColor: ['#34d399', '#f87171', '#f43f5e', '#fbbf24', '#94a3b8'] },
            ]);
            const supports = data.supports_by_type || [];
            bar('chart-supports', supports.length ? supports.map((r) => r.name) : ['Sin datos'], [
                { label: 'Apoyos', data: supports.length ? supports.map((r) => r.total) : [0], backgroundColor: '#a78bfa' },
            ]);
        })();
    </script>
@endpush
