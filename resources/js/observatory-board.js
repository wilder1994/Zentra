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

function chartFont() {
    return { family: 'Figtree, ui-sans-serif, system-ui', size: 10 };
}

function lineOptions(showLegend = false) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                display: showLegend,
                position: 'bottom',
                labels: { color: SLATE, boxWidth: 8, font: { ...chartFont(), size: 10 }, padding: 8 },
            },
            tooltip: {
                backgroundColor: '#0f172a',
                borderColor: '#334155',
                borderWidth: 1,
                titleColor: '#e2e8f0',
                bodyColor: '#cbd5e1',
                padding: 8,
            },
        },
        scales: {
            x: {
                ticks: { color: MUTED, font: chartFont(), maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
                grid: { color: GRID, drawBorder: false },
            },
            y: {
                beginAtZero: true,
                suggestedMax: 4,
                ticks: { color: MUTED, precision: 0, font: chartFont() },
                grid: { color: GRID, drawBorder: false },
            },
        },
    };
}

function doughnutOptions(empty) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { color: SLATE, boxWidth: 8, font: { ...chartFont(), size: 10 }, padding: 8 },
            },
            tooltip: {
                callbacks: {
                    label(ctx) {
                        if (empty) {
                            return ` ${ctx.label}: 0`;
                        }
                        return ` ${ctx.label}: ${ctx.parsed}`;
                    },
                },
            },
        },
    };
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

function drawLoadGauge(canvas, value) {
    const parent = canvas.parentElement;
    const width = Math.max(1, parent?.clientWidth ?? canvas.clientWidth);
    const height = Math.max(1, parent?.clientHeight ?? canvas.clientHeight);
    const dpr = window.devicePixelRatio || 1;
    canvas.width = Math.round(width * dpr);
    canvas.height = Math.round(height * dpr);
    canvas.style.width = `${width}px`;
    canvas.style.height = `${height}px`;

    const ctx = canvas.getContext('2d');
    if (!ctx) {
        return;
    }
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, width, height);

    const pad = 8;
    const thickness = Math.max(10, Math.min(width, height) * 0.12);
    const cx = width / 2;
    const cy = height - pad - 4;
    const radius = Math.max(12, Math.min((width / 2) - pad - thickness / 2, cy - pad - thickness / 2));
    const start = Math.PI;
    const sweep = Math.PI;
    const steps = 96;

    ctx.lineCap = 'butt';
    ctx.lineWidth = thickness;
    for (let i = 0; i < steps; i += 1) {
        const t0 = i / steps;
        const a0 = start + sweep * t0;
        const a1 = start + sweep * ((i + 1) / steps);
        ctx.beginPath();
        ctx.strokeStyle = loadColor(t0);
        ctx.arc(cx, cy, radius, a0, a1);
        ctx.stroke();
    }

    ctx.beginPath();
    ctx.lineWidth = 3;
    ctx.strokeStyle = SURFACE;
    ctx.arc(cx, cy, Math.max(4, radius - thickness / 2 - 1.5), start, start + sweep);
    ctx.stroke();

    const load = Math.max(0, Math.min(100, Number(value) || 0)) / 100;
    const angle = start + sweep * load;
    const needle = Math.max(8, radius - thickness * 0.55);
    ctx.save();
    ctx.translate(cx, cy);
    ctx.rotate(angle);
    ctx.beginPath();
    ctx.moveTo(-6, 4);
    ctx.lineTo(needle, 0);
    ctx.lineTo(-6, -4);
    ctx.closePath();
    ctx.fillStyle = loadColor(load);
    ctx.fill();
    ctx.beginPath();
    ctx.arc(0, 0, 6, 0, Math.PI * 2);
    ctx.fillStyle = load >= 0.7 ? '#fecaca' : '#e2e8f0';
    ctx.fill();
    ctx.beginPath();
    ctx.arc(0, 0, 3, 0, Math.PI * 2);
    ctx.fillStyle = loadColor(load);
    ctx.fill();
    ctx.restore();
}

export function observatoryBoard(payload) {
    return {
        payload,
        charts: [],
        init() {
            this.draw();
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
            const paint = () => this.paintOrigins(this.payload?.origins);
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', paint);
            } else {
                paint();
            }
        },
        replacePayload(board) {
            const next = { ...this.payload, ...board };
            if (JSON.stringify(this.liveSlice(this.payload)) === JSON.stringify(this.liveSlice(next))) {
                this.payload = next;
                return;
            }
            this.charts.forEach((chart) => chart.destroy());
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
        track(chart) {
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
            if (!el) {
                return;
            }
            const labels = series.labels?.length ? series.labels : ['—'];
            const rows = (series.series || []).length
                ? series.series
                : [{ label: 'Reportes', color: '#94a3b8', values: [0] }];
            this.track(new Chart(el, {
                type: 'line',
                data: {
                    labels,
                    datasets: rows.map((row) => ({
                        label: row.label,
                        data: row.values?.length ? row.values : labels.map(() => 0),
                        borderColor: row.color,
                        backgroundColor: 'transparent',
                        fill: false,
                        tension: 0.35,
                        pointRadius: 2,
                        pointHoverRadius: 4,
                        pointBackgroundColor: row.color,
                        borderWidth: 2,
                    })),
                },
                options: lineOptions(rows.length > 1),
            }));
        },
        bars(el, series) {
            if (!el) {
                return;
            }
            const labels = series.labels?.length ? series.labels : ['—'];
            const values = series.values?.length ? series.values : [0];
            this.track(new Chart(el, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        data: values,
                        backgroundColor: '#f59e0b',
                        borderRadius: 5,
                        maxBarThickness: 22,
                    }],
                },
                options: {
                    ...lineOptions(),
                    plugins: { ...lineOptions().plugins, legend: { display: false } },
                },
            }));
        },
        pie(el, series) {
            if (!el) {
                return;
            }
            const labels = series.labels?.length ? series.labels : ['Link público', 'WhatsApp', 'App de patrulla', 'API', 'Panel'];
            const values = series.values?.length ? series.values : labels.map(() => 0);
            const colors = ['#14b8a6', '#22c55e', '#f59e0b', '#a78bfa', '#818cf8'];
            const empty = values.every((n) => Number(n) === 0);
            this.track(new Chart(el, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data: empty ? values.map(() => 1) : values,
                        backgroundColor: empty ? values.map(() => GRID) : colors.slice(0, values.length),
                        borderColor: SURFACE,
                        borderWidth: 2,
                    }],
                },
                options: {
                    ...doughnutOptions(empty),
                    cutout: '62%',
                },
            }));
        },
        gauge(el, value) {
            if (!el) {
                return;
            }
            const paint = () => drawLoadGauge(el, value);
            paint();
            if (typeof ResizeObserver !== 'undefined') {
                const observer = new ResizeObserver(paint);
                observer.observe(el.parentElement ?? el);
            }
        },
        paintOrigins(series) {
            const node = document.querySelector('.obs-origins');
            if (!node || !window.echarts || !series) {
                return;
            }
            const labels = series.labels?.length ? series.labels : ['Sin origen'];
            const values = series.values?.length ? series.values : [0];
            const palette = [
                ['#d1fae5', '#22c55e', '#14532d'],
                ['#fef3c7', '#eab308', '#854d0e'],
                ['#ffedd5', '#f97316', '#9a3412'],
                ['#ffe4e6', '#f43f5e', '#9f1239'],
                ['#e0e7ff', '#818cf8', '#3730a3'],
                ['#ccfbf1', '#14b8a6', '#115e59'],
                ['#f3e8ff', '#a78bfa', '#6b21a8'],
                ['#e2e8f0', '#94a3b8', '#334155'],
            ];
            const width = 12;
            const tip = 5;
            const colorsOf = (index) => palette[index % palette.length];
            const shaft = (colors) => new window.echarts.graphic.LinearGradient(0, 0, 0, 1, [
                { offset: 0, color: colors[0] },
                { offset: 0.42, color: colors[1] },
                { offset: 1, color: colors[2] },
            ]);
            const chart = window.echarts.getInstanceByDom(node) || window.echarts.init(node);
            chart.setOption({
                animation: false,
                grid: { left: 16, right: 14, top: 6, bottom: 4, containLabel: true },
                xAxis: { type: 'value', min: 0, show: false },
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
                        z: 2,
                        data: values.map((value, index) => ({
                            value: Number(value) > 0 ? Number(value) : null,
                            itemStyle: { borderRadius: 0, color: shaft(colorsOf(index)) },
                        })),
                    },
                    {
                        type: 'pictorialBar',
                        symbol: 'circle',
                        symbolSize: [tip, width],
                        symbolPosition: 'end',
                        symbolOffset: [tip / 2, 0],
                        barGap: '-100%',
                        z: 3,
                        silent: true,
                        data: values.map((value, index) => ({
                            value: Number(value) > 0 ? Number(value) : null,
                            itemStyle: { color: colorsOf(index)[0] },
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
