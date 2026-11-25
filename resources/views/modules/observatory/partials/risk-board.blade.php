@php
    $rows = $risk['rows'] ?? [];
    $parameters = $risk['parameters'] ?? null;
    $cuts = [
        (int) ($parameters->threshold_low ?? 0),
        (int) ($parameters->threshold_medium ?? 30),
        (int) ($parameters->threshold_high ?? 60),
        (int) ($parameters->threshold_critical ?? 80),
    ];
    $stations = [
        ['label' => 'Bajo', 'from' => $cuts[0], 'to' => $cuts[1]],
        ['label' => 'Medio', 'from' => $cuts[1], 'to' => $cuts[2]],
        ['label' => 'Alto', 'from' => $cuts[2], 'to' => $cuts[3]],
        ['label' => 'Crítico', 'from' => $cuts[3], 'to' => 100],
    ];
@endphp
<section class="obs-card p-3 space-y-3">
    <div>
        <h3 class="text-sm font-semibold text-white">Riesgo por instalación</h3>
        <p class="text-[11px] text-slate-500">Comuna {{ $risk['year'] ?? now()->year }} y reportes de la sede con decaimiento. Escala 0–100. Las de mayor riesgo final arriba.</p>
    </div>
    <div class="obs-risk-rank sidebar-scroll">
        <table class="min-w-full text-xs">
            <thead class="text-slate-500">
                <tr>
                    <th class="px-2 py-1 text-left">Instalación</th>
                    <th class="px-2 py-1 text-left">Comuna</th>
                    <th class="px-2 py-1 text-right">Reportes</th>
                    <th class="px-2 py-1 text-right">Máx.</th>
                    <th class="px-2 py-1 text-right">Suma</th>
                    <th class="px-2 py-1 text-right">R comuna</th>
                    <th class="px-2 py-1 text-right">R sede</th>
                    <th class="px-2 py-1 text-right">R final</th>
                    <th class="px-2 py-1 text-left">Semáforo</th>
                </tr>
            </thead>
            <tbody data-obs-risk-body data-show-client="{{ ! empty($showClientColumn) ? '1' : '0' }}">
                @forelse ($rows as $row)
                    <tr class="border-t border-slate-800">
                        <td class="px-2 py-1.5 text-slate-200">
                            {{ $row['name'] }}
                            @if (! empty($showClientColumn) && ($row['client'] ?? '') !== '')
                                <span class="block text-[10px] text-slate-500">{{ $row['client'] }}</span>
                            @endif
                        </td>
                        <td class="px-2 py-1.5 text-slate-400">{{ $row['comuna'] }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums text-slate-300">{{ $row['reports'] }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums text-slate-300">{{ number_format((float) $row['max_severity'], 1) }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums text-slate-300">{{ number_format((float) $row['weighted_sum'], 2) }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums text-slate-200">{{ number_format((float) $row['r_commune'], 1) }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums text-slate-200">{{ number_format((float) $row['r_installation'], 1) }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums font-semibold text-white">{{ number_format((float) $row['r_final'], 1) }}</td>
                        <td class="px-2 py-1.5">
                            @php
                                $fill = max(0, min(100, (float) $row['r_final']));
                                $ink = $parameters?->colorFor((string) $row['band']) ?? '#14532d';
                                $cap = $parameters?->colorFor('CRITICO') ?? '#9f1239';
                            @endphp
                            <div
                                class="obs-risk-cyl"
                                data-fill="{{ $fill }}"
                                data-ink="{{ $ink }}"
                                data-cap="{{ $cap }}"
                                title="{{ number_format($fill, 1) }} · {{ $row['band'] === 'CRITICO' ? 'CRÍTICO' : $row['band'] }}"
                            ></div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-2 py-6 text-center text-slate-500">Sin instalaciones en este filtro.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
<script type="application/json" id="obs-risk-cylinder-meta">@json(['stations' => $stations, 'cuts' => $cuts])</script>
<script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js" defer></script>
<script defer>
document.addEventListener('DOMContentLoaded', () => {
    if (!window.echarts || window.__obsRiskCylinders) return;
    window.__obsRiskCylinders = true;
    const meta = JSON.parse(document.getElementById('obs-risk-cylinder-meta')?.textContent || '{}');
    const stations = meta.stations || [];
    const cuts = (meta.cuts || []).filter((cut) => cut > 0 && cut < 100);
    const width = 22;
    const tip = 9;
    const mix = (hex, toward, amount) => {
        const parse = (value) => [1, 3, 5].map((index) => parseInt(value.slice(index, index + 2), 16));
        const [red, green, blue] = parse(hex);
        const [toRed, toGreen, toBlue] = parse(toward);
        const channel = (from, to) => Math.round(from + (to - from) * amount).toString(16).padStart(2, '0');
        return '#' + channel(red, toRed) + channel(green, toGreen) + channel(blue, toBlue);
    };
    const shaft = (ink) => new echarts.graphic.LinearGradient(0, 0, 0, 1, [
        { offset: 0, color: mix(ink, '#ffffff', 0.55) },
        { offset: 0.42, color: ink },
        { offset: 1, color: mix(ink, '#020617', 0.4) },
    ]);
    const lyingCylinder = (fill, ink, cap) => {
        const series = [{
            type: 'bar',
            barWidth: width,
            barGap: '-100%',
            silent: true,
            z: 1,
            data: [{ value: 100, itemStyle: { color: '#e2e8f0', borderRadius: 0 } }],
            markLine: {
                silent: true,
                symbol: 'none',
                label: { show: false },
                lineStyle: { color: '#334155', width: 1 },
                data: cuts.map((cut) => ({ xAxis: cut })),
            },
        }];
        if (fill > 0) {
            series.push({
                type: 'bar',
                barWidth: width,
                barGap: '-100%',
                silent: true,
                z: 2,
                data: [{ value: fill, itemStyle: { color: shaft(ink), borderRadius: 0 } }],
            });
            series.push({
                type: 'pictorialBar',
                symbol: 'circle',
                symbolSize: [tip, width],
                symbolPosition: 'end',
                symbolOffset: [tip / 2, 0],
                silent: true,
                barGap: '-100%',
                z: 3,
                data: [{ value: fill, itemStyle: { color: ink } }],
            });
        }
        series.push({
            type: 'pictorialBar',
            symbol: 'circle',
            symbolSize: [tip, width],
            symbolPosition: 'end',
            symbolOffset: [tip / 2, 0],
            silent: true,
            barGap: '-100%',
            z: 4,
            data: [{ value: 100, itemStyle: { color: cap } }],
        });
        series.push({
            type: 'scatter',
            symbolSize: 0,
            silent: true,
            z: 5,
            data: stations.filter((station) => station.to > station.from).map((station) => ({
                value: [(station.from + station.to) / 2, 0],
                name: station.label,
            })),
            label: {
                show: true,
                formatter: '{b}',
                color: '#0f172a',
                fontSize: 8,
                fontWeight: 700,
            },
        });
        return series;
    };
    window.obsPaintRiskCylinders = () => {
        document.querySelectorAll('.obs-risk-cyl').forEach((node) => {
            const previous = echarts.getInstanceByDom(node);
            if (previous) previous.dispose();
            const chart = echarts.init(node);
            chart.setOption({
                animation: false,
                grid: { left: 1, right: 8, top: 4, bottom: 4 },
                xAxis: { type: 'value', min: 0, max: 100, show: false },
                yAxis: { type: 'category', data: [''], show: false },
                series: lyingCylinder(Number(node.dataset.fill || 0), node.dataset.ink || '#14532d', node.dataset.cap || '#9f1239'),
            });
        });
    };
    window.obsPaintRiskCylinders();
});
</script>
