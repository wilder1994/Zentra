import Chart from 'chart.js/auto';

window.Chart = Chart;

export function obsDateRange(config) {
    const fmt = (value) => {
        if (!value) {
            return '';
        }
        const [y, m, d] = String(value).split('-');
        return `${d}/${m}/${y}`;
    };

    return {
        open: false,
        from: config.from || '',
        to: config.to || '',
        draftFrom: config.from || '',
        draftTo: config.to || '',
        get label() {
            if (this.from && this.to) {
                return `${fmt(this.from)} – ${fmt(this.to)}`;
            }
            if (this.from) {
                return `Desde ${fmt(this.from)}`;
            }
            if (this.to) {
                return `Hasta ${fmt(this.to)}`;
            }

            return 'Fechas';
        },
        show() {
            this.draftFrom = this.from;
            this.draftTo = this.to;
            this.open = true;
        },
        apply() {
            this.from = this.draftFrom;
            this.to = this.draftTo;
            this.open = false;
            this.$nextTick(() => {
                if (this.$el instanceof HTMLFormElement) {
                    this.$el.submit();
                }
            });
        },
        close() {
            this.open = false;
        },
    };
}

const isLight = () => document.documentElement.dataset.theme === 'light';
const SLATE = isLight() ? '#4d675f' : '#94a3b8';
const GRID = isLight() ? '#d5e3de' : '#1e293b';
const MUTED = isLight() ? '#6b827a' : '#64748b';
const SURFACE = isLight() ? '#f7fbf9' : '#0f172a';

function tip() {
    return {
        backgroundColor: '#0f172a',
        borderColor: '#334155',
        borderWidth: 1,
        textStyle: { color: '#e2e8f0', fontSize: 11 },
    };
}

function mixHex(hex, toward, amount) {
    const parse = (value) => [1, 3, 5].map((index) => parseInt(value.slice(index, index + 2), 16));
    const [red, green, blue] = parse(hex);
    const [toRed, toGreen, toBlue] = parse(toward);
    const channel = (from, to) => Math.round(from + (to - from) * amount).toString(16).padStart(2, '0');

    return `#${channel(red, toRed)}${channel(green, toGreen)}${channel(blue, toBlue)}`;
}

function cylinderGradient(ink, vertical) {
    return new window.echarts.graphic.LinearGradient(0, 0, vertical ? 1 : 0, vertical ? 0 : 1, [
        { offset: 0, color: mixHex(ink, '#ffffff', 0.55) },
        { offset: 0.42, color: ink },
        { offset: 1, color: mixHex(ink, '#020617', 0.4) },
    ]);
}

function mixRgb(from, to, t) {
    return from.map((c, i) => Math.round(c + (to[i] - c) * t));
}

function loadColor(t) {
    const clamped = Math.max(0, Math.min(1, t));
    const green = [16, 185, 129];
    const amber = [245, 158, 11];
    const red = [239, 68, 68];
    const rgb = clamped < 0.5
        ? mixRgb(green, amber, clamped / 0.5)
        : mixRgb(amber, red, (clamped - 0.5) / 0.5);

    return `rgb(${rgb[0]}, ${rgb[1]}, ${rgb[2]})`;
}

export function observatoryBoard(payload) {
    return {
        payload,
        charts: [],
        async init() {
            await (window.whenEcharts ?? import('./echarts'));
            this.draw();
            window.addEventListener('resize', () => {
                this.charts.forEach((chart) => chart.resize());
            });
            window.addEventListener('ops-live-data', (event) => {
                const board = event.detail?.board;
                if (!board) {
                    return;
                }
                this.replacePayload(board);
                const loadEl = document.querySelector('.obs-gauge-value');
                if (loadEl && board.load_rate !== undefined) {
                    loadEl.textContent = `${board.load_rate}%`;
                }
            });
        },
        replacePayload(board) {
            const next = { ...this.payload, ...board };
            if (JSON.stringify(this.liveSlice(this.payload)) === JSON.stringify(this.liveSlice(next))) {
                this.payload = next;
                return;
            }
            this.charts.forEach((chart) => chart.dispose());
            this.charts = [];
            this.payload = next;
            this.draw();
        },
        liveSlice(data) {
            const source = data ?? {};
            return {
                trend: source.trend ?? null,
                peaks: source.peaks ?? null,
                sources: source.sources ?? null,
                origins: source.origins ?? null,
                risk: source.risk ?? null,
                load_rate: source.load_rate ?? null,
            };
        },
        mount(el) {
            if (!el) {
                return null;
            }
            const current = window.echarts.getInstanceByDom(el);
            if (current) {
                current.dispose();
            }
            const chart = window.echarts.init(el);
            this.charts.push(chart);

            return chart;
        },
        draw() {
            const data = this.payload ?? {};
            const trend = data.trend ?? { labels: ['—'], series: [] };
            const peaks = data.peaks ?? { labels: ['—'], values: [0] };
            const sources = data.sources ?? { labels: [], values: [] };
            const load = Number(data.load_rate ?? 0);

            this.lines(this.$refs.trend, trend);
            this.bars(this.$refs.peaks, peaks);
            this.pie(this.$refs.sources, sources);
            this.gauge(this.$refs.gauge, load);
            this.paintOrigins(data.origins);
            this.paintRisk(data.risk);
        },
        lines(el, series) {
            const chart = this.mount(el);
            if (!chart) {
                return;
            }
            const labels = series.labels?.length ? series.labels : ['—'];
            const rows = (series.series || []).length
                ? series.series
                : [{ label: 'Reportes', color: '#94a3b8', values: [0] }];
            chart.setOption({
                animation: false,
                grid: { left: 8, right: 12, top: 16, bottom: rows.length > 1 ? 36 : 8, containLabel: true },
                tooltip: { trigger: 'axis', ...tip() },
                legend: {
                    show: rows.length > 1,
                    bottom: 0,
                    icon: 'circle',
                    itemWidth: 8,
                    itemHeight: 8,
                    textStyle: { color: SLATE, fontSize: 10 },
                },
                xAxis: {
                    type: 'category',
                    data: labels,
                    axisTick: { show: false },
                    axisLine: { lineStyle: { color: GRID } },
                    axisLabel: { color: MUTED, fontSize: 10 },
                },
                yAxis: {
                    type: 'value',
                    min: 0,
                    minInterval: 1,
                    axisLabel: { color: MUTED, fontSize: 10 },
                    splitLine: { lineStyle: { color: GRID } },
                },
                series: rows.map((row) => ({
                    type: 'line',
                    name: row.label,
                    data: row.values?.length ? row.values : labels.map(() => 0),
                    smooth: 0.35,
                    symbol: 'circle',
                    symbolSize: 6,
                    lineStyle: { width: 2, color: row.color },
                    itemStyle: { color: row.color },
                })),
            }, true);
        },
        bars(el, series) {
            const chart = this.mount(el);
            if (!chart) {
                return;
            }
            const labels = series.labels?.length ? series.labels : ['—'];
            const values = (series.values?.length ? series.values : [0]).map((value) => Number(value) || 0);
            const ink = '#f59e0b';
            const width = 18;
            const cap = 7;
            const max = Math.max(1, ...values);
            const track = isLight() ? '#e2e8f0' : '#1e293b';
            chart.setOption({
                animation: false,
                grid: { left: 8, right: 12, top: 16, bottom: 8, containLabel: true },
                tooltip: { trigger: 'axis', ...tip() },
                xAxis: {
                    type: 'category',
                    data: labels,
                    axisTick: { show: false },
                    axisLine: { show: false },
                    axisLabel: { color: MUTED, fontSize: 10 },
                },
                yAxis: {
                    type: 'value',
                    min: 0,
                    max,
                    show: false,
                },
                series: [
                    {
                        type: 'bar',
                        barWidth: width,
                        barGap: '-100%',
                        silent: true,
                        z: 1,
                        data: values.map(() => max),
                        itemStyle: { color: track, borderRadius: 0 },
                    },
                    {
                        type: 'bar',
                        barWidth: width,
                        barGap: '-100%',
                        z: 2,
                        data: values.map((value) => ({
                            value: value > 0 ? value : null,
                            itemStyle: { color: cylinderGradient(ink, true), borderRadius: 0 },
                        })),
                    },
                    {
                        type: 'pictorialBar',
                        symbol: 'circle',
                        symbolSize: [width, cap],
                        symbolPosition: 'end',
                        symbolOffset: [0, -cap / 2],
                        silent: true,
                        barGap: '-100%',
                        z: 3,
                        data: values.map((value) => ({
                            value: value > 0 ? value : null,
                            itemStyle: { color: ink },
                        })),
                    },
                ],
            }, true);
        },
        pie(el, series) {
            const chart = this.mount(el);
            if (!chart) {
                return;
            }
            const labels = series.labels?.length ? series.labels : ['Link público', 'WhatsApp', 'App de patrulla', 'API', 'Panel'];
            const values = series.values?.length ? series.values : labels.map(() => 0);
            const colors = ['#14b8a6', '#22c55e', '#f59e0b', '#a78bfa', '#818cf8'];
            const empty = values.every((value) => Number(value) === 0);
            chart.setOption({
                animation: false,
                tooltip: {
                    ...tip(),
                    formatter(item) {
                        return `${item.name}: ${empty ? 0 : item.value}`;
                    },
                },
                legend: {
                    bottom: 0,
                    icon: 'circle',
                    itemWidth: 8,
                    itemHeight: 8,
                    textStyle: { color: SLATE, fontSize: 10 },
                },
                series: [{
                    type: 'pie',
                    radius: ['48%', '72%'],
                    center: ['50%', '42%'],
                    label: { show: false },
                    data: labels.map((label, index) => ({
                        name: label,
                        value: empty ? 1 : Number(values[index] || 0),
                        itemStyle: {
                            color: empty ? GRID : colors[index % colors.length],
                            borderColor: SURFACE,
                            borderWidth: 2,
                        },
                    })),
                }],
            }, true);
        },
        gauge(el, value) {
            const chart = this.mount(el);
            if (!chart) {
                return;
            }
            const load = Math.max(0, Math.min(100, Number(value) || 0));
            chart.setOption({
                animation: false,
                series: [{
                    type: 'gauge',
                    startAngle: 180,
                    endAngle: 0,
                    min: 0,
                    max: 100,
                    radius: '140%',
                    center: ['50%', '78%'],
                    pointer: {
                        show: true,
                        length: '62%',
                        width: 4,
                        itemStyle: { color: loadColor(load / 100) },
                    },
                    progress: { show: false },
                    axisLine: {
                        lineStyle: {
                            width: 12,
                            color: [[0.5, '#10b981'], [0.75, '#f59e0b'], [1, '#ef4444']],
                        },
                    },
                    axisTick: { show: false },
                    splitLine: { show: false },
                    axisLabel: { show: false },
                    detail: { show: false },
                    data: [{ value: load }],
                }],
            }, true);
        },
        paintOrigins(series) {
            const node = document.querySelector('.obs-origins');
            if (!node || !series) {
                return;
            }
            const labels = series.labels?.length ? series.labels : ['Sin origen'];
            const values = (series.values?.length ? series.values : [0]).map((value) => Number(value) || 0);
            const palette = ['#22c55e', '#eab308', '#f97316', '#f43f5e', '#818cf8', '#14b8a6', '#a78bfa', '#94a3b8'];
            const width = 12;
            const cap = 5;
            const max = Math.max(1, ...values);
            const track = isLight() ? '#e2e8f0' : '#1e293b';
            const chart = this.mount(node);
            chart.setOption({
                animation: false,
                grid: { left: 8, right: 16, top: 4, bottom: 4, containLabel: true },
                tooltip: { trigger: 'axis', ...tip() },
                xAxis: { type: 'value', min: 0, max, show: false },
                yAxis: {
                    type: 'category',
                    inverse: true,
                    data: labels,
                    axisLine: { show: false },
                    axisTick: { show: false },
                    axisLabel: { color: '#94a3b8', fontSize: 10 },
                },
                series: [
                    {
                        type: 'bar',
                        barWidth: width,
                        barGap: '-100%',
                        silent: true,
                        z: 1,
                        data: values.map(() => max),
                        itemStyle: { color: track, borderRadius: 0 },
                    },
                    {
                        type: 'bar',
                        barWidth: width,
                        barGap: '-100%',
                        z: 2,
                        data: values.map((value, index) => ({
                            value: value > 0 ? value : null,
                            itemStyle: { color: cylinderGradient(palette[index % palette.length], false), borderRadius: 0 },
                        })),
                    },
                    {
                        type: 'pictorialBar',
                        symbol: 'circle',
                        symbolSize: [cap, width],
                        symbolPosition: 'end',
                        symbolOffset: [cap / 2, 0],
                        silent: true,
                        barGap: '-100%',
                        z: 3,
                        data: values.map((value, index) => ({
                            value: value > 0 ? value : null,
                            itemStyle: { color: palette[index % palette.length] },
                        })),
                    },
                ],
            }, true);
        },
        paintRisk(risk) {
            const body = document.querySelector('[data-obs-risk-body]');
            if (!body || !risk || !Array.isArray(risk.rows)) {
                return;
            }
            document.querySelectorAll('.obs-risk-cyl').forEach((node) => {
                window.echarts?.getInstanceByDom(node)?.dispose();
            });
            body.replaceChildren();
            const showClient = body.dataset.showClient === '1';
            const num = (value, digits) => Number(value || 0).toFixed(digits);
            if (risk.rows.length === 0) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 9;
                td.className = 'px-2 py-6 text-center text-slate-500';
                td.textContent = 'Sin instalaciones en este filtro.';
                tr.append(td);
                body.append(tr);
                return;
            }
            risk.rows.forEach((row) => {
                const tr = document.createElement('tr');
                tr.className = 'border-t border-slate-800';
                const name = document.createElement('td');
                name.className = 'px-2 py-1.5 text-slate-200';
                name.textContent = row.name || '';
                if (showClient && row.client) {
                    const sub = document.createElement('span');
                    sub.className = 'block text-[10px] text-slate-500';
                    sub.textContent = row.client;
                    name.append(sub);
                }
                tr.append(name);
                const text = [
                    [row.comuna || '', 'px-2 py-1.5 text-slate-400'],
                    [String(row.reports ?? 0), 'px-2 py-1.5 text-right tabular-nums text-slate-300'],
                    [num(row.max_severity, 1), 'px-2 py-1.5 text-right tabular-nums text-slate-300'],
                    [num(row.weighted_sum, 2), 'px-2 py-1.5 text-right tabular-nums text-slate-300'],
                    [num(row.r_commune, 1), 'px-2 py-1.5 text-right tabular-nums text-slate-200'],
                    [num(row.r_installation, 1), 'px-2 py-1.5 text-right tabular-nums text-slate-200'],
                    [num(row.r_final, 1), 'px-2 py-1.5 text-right tabular-nums font-semibold text-white'],
                ];
                text.forEach(([value, className]) => {
                    const td = document.createElement('td');
                    td.className = className;
                    td.textContent = value;
                    tr.append(td);
                });
                const cylCell = document.createElement('td');
                cylCell.className = 'px-2 py-1.5';
                const cyl = document.createElement('div');
                cyl.className = 'obs-risk-cyl';
                cyl.dataset.fill = String(row.fill ?? 0);
                cyl.dataset.ink = row.ink || '#14532d';
                cyl.dataset.cap = row.cap || '#9f1239';
                const band = row.band === 'CRITICO' ? 'CRÍTICO' : (row.band || '');
                cyl.title = `${num(row.fill, 1)} · ${band}`;
                cylCell.append(cyl);
                tr.append(cylCell);
                body.append(tr);
            });
            window.obsPaintRiskCylinders?.();
        },
    };
}
