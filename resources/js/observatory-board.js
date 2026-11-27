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

function floatTip() {
    return { ...tip(), appendToBody: true, confine: false };
}

function escapeTip(value) {
    return String(value ?? '').replace(/[&<>]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[char]));
}

function barCard(title, total, bar, parts = []) {
    return [
        `<strong>${escapeTip(title)}</strong>`,
        `Total de reportes: ${total}`,
        `Esta barra: ${bar}`,
        ...parts,
    ].join('<br>');
}

const RISK_INKS = ['#14532d', '#854d0e', '#9a3412', '#9f1239'];

function hexChannels(hex) {
    const value = String(hex || '').replace('#', '');
    if (value.length !== 6) {
        return [20, 83, 45];
    }

    return [0, 2, 4].map((index) => parseInt(value.slice(index, index + 2), 16));
}

function rgbToHsl(red, green, blue) {
    const r = red / 255;
    const g = green / 255;
    const b = blue / 255;
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const light = (max + min) / 2;
    if (max === min) {
        return [0, 0, light * 100];
    }
    const delta = max - min;
    const sat = light > 0.5 ? delta / (2 - max - min) : delta / (max + min);
    let hue = 0;
    if (max === r) {
        hue = ((g - b) / delta) % 6;
    } else if (max === g) {
        hue = (b - r) / delta + 2;
    } else {
        hue = (r - g) / delta + 4;
    }

    return [hue * 60, sat * 100, light * 100];
}

function hslToHex(hue, sat, light) {
    const s = sat / 100;
    const l = light / 100;
    const c = (1 - Math.abs(2 * l - 1)) * s;
    const h = ((hue % 360) + 360) % 360;
    const x = c * (1 - Math.abs((h / 60) % 2 - 1));
    const m = l - c / 2;
    const sector = Math.floor(h / 60);
    const rgb = [
        [c, x, 0],
        [x, c, 0],
        [0, c, x],
        [0, x, c],
        [x, 0, c],
        [c, 0, x],
    ][sector];

    return `#${rgb.map((channel) => Math.round((channel + m) * 255).toString(16).padStart(2, '0')).join('')}`;
}

function shiftInk(hex, step) {
    const [hue, sat, light] = rgbToHsl(...hexChannels(hex));
    const nextHue = hue + step * 18;
    const nextLight = Math.min(64, Math.max(28, light + (step % 2 === 0 ? 14 : -10)));

    return hslToHex(nextHue, Math.min(72, sat + 8), nextLight);
}

function familyInks(bases, count) {
    const seeds = Array.isArray(bases) && bases.length ? bases : RISK_INKS;
    return Array.from({ length: Math.max(count, 0) }, (_, index) => {
        const lap = Math.floor(index / seeds.length);
        const seed = seeds[index % seeds.length];

        return lap === 0 ? seed : shiftInk(seed, lap);
    });
}

function spreadInks(colors) {
    const seen = new Map();

    return colors.map((color) => {
        const key = String(color || '').toLowerCase();
        const used = seen.get(key) || 0;
        seen.set(key, used + 1);

        return used === 0 ? color : shiftInk(color, used);
    });
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

function tubeInk(ink) {
    return new window.echarts.graphic.RadialGradient(0.5, 0.5, 1, [
        { offset: 0.46, color: mixHex(ink, '#020617', 0.45) },
        { offset: 0.72, color: ink },
        { offset: 0.9, color: mixHex(ink, '#ffffff', 0.5) },
        { offset: 1, color: mixHex(ink, '#020617', 0.3) },
    ]);
}

export function observatoryBoard(payload) {
    return {
        payload,
        siteQuery: '',
        riskQuery: '',
        charts: [],
        canPaint: Boolean(payload?.can_paint),
        paintUrl: payload?.paint_url || '',
        brush: null,
        brushRows: [],
        async init() {
            await (window.whenEcharts ?? import('./echarts'));
            this.draw();
            window.addEventListener('resize', () => {
                this.charts.forEach((chart) => chart.resize());
                this.pie(this.$refs.sources, this.payload?.sources ?? { labels: [], values: [] });
                this.gauge(this.$refs.gauge);
            });
            window.addEventListener('ops-live-data', (event) => {
                const board = event.detail?.board;
                if (!board) {
                    return;
                }
                this.replacePayload(board);
                ['total', 'nuevo', 'en_atencion', 'cerrado'].forEach((key) => {
                    if (board[key] === undefined) {
                        return;
                    }
                    document.querySelectorAll(`[data-obs-kpi="${key}"]`).forEach((node) => {
                        node.textContent = String(board[key]);
                    });
                });
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
                crimes: source.crimes ?? null,
                sites: source.sites ?? null,
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
                this.charts = this.charts.filter((chart) => chart !== current);
                current.dispose();
            }
            const chart = window.echarts.init(el);
            this.charts.push(chart);

            return chart;
        },
        draw() {
            const data = this.payload ?? {};
            const trend = data.trend ?? { labels: ['—'], series: [] };
            const sources = data.sources ?? { labels: [], values: [] };

            this.lines(this.$refs.trend, trend);
            this.paintCrimes(data.crimes);
            this.paintSites();
            this.pie(this.$refs.sources, sources);
            this.gauge(this.$refs.gauge);
            this.paintOrigins(data.origins);
            this.paintRisk(data.risk);
        },
        normalize(value) {
            return String(value || '')
                .toLowerCase()
                .normalize('NFD')
                .replace(/\p{M}/gu, '')
                .replace(/\s+/g, ' ')
                .trim();
        },
        barSize(el, count) {
            const rows = Math.max(count, 1);
            const thick = 22;
            const pitch = thick + 8;
            const visible = el.parentElement?.clientHeight || 0;
            el.style.height = visible > 0 && rows * pitch > visible ? `${rows * pitch}px` : '100%';

            return thick;
        },
        openBrush(chart) {
            if (this.brush === chart) {
                this.brush = null;

                return;
            }
            this.brushRows = (this.payload?.paints?.[chart] || []).map((row) => ({ ...row }));
            this.brush = chart;
        },
        previewBrush(key, color) {
            const row = this.brushRows.find((item) => item.key === key);
            if (!row || !this.brush) {
                return;
            }
            row.color = color;
            this.applyBrush(this.brush, this.brushRows);
        },
        async saveBrush() {
            if (!this.paintUrl || !this.brush) {
                return;
            }
            const colors = {};
            this.brushRows.forEach((row) => {
                colors[row.key] = row.color;
            });
            const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
            await fetch(this.paintUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({ chart: this.brush, colors }),
            });
        },
        applyBrush(chart, rows) {
            const paints = { ...(this.payload?.paints || {}), [chart]: rows.map((row) => ({ ...row })) };
            const next = { ...this.payload, paints };
            const colors = rows.map((row) => row.color);
            if (chart === 'sources' || chart === 'origins') {
                next[chart] = { ...(next[chart] || {}), colors };
            }
            if (chart === 'trend') {
                const byKey = Object.fromEntries(rows.map((row) => [row.key, row.color]));
                next.trend = {
                    ...(next.trend || {}),
                    series: (next.trend?.series || []).map((row) => ({ ...row, color: byKey[row.key] || row.color })),
                };
            }
            this.payload = next;
            this.draw();
        },
        filterRisk() {
            const body = document.querySelector('[data-obs-risk-body]');
            if (!body) {
                return;
            }
            const query = this.normalize(this.riskQuery);
            let shown = 0;
            body.querySelectorAll('tr[data-site]').forEach((row) => {
                const hit = query === '' || this.normalize(row.dataset.site).includes(query);
                row.hidden = !hit;
                if (hit) {
                    shown += 1;
                }
            });
            let empty = body.querySelector('tr[data-risk-empty]');
            const needed = query !== '' && shown === 0 && body.querySelector('tr[data-site]');
            if (needed && !empty) {
                empty = document.createElement('tr');
                empty.dataset.riskEmpty = '1';
                const cell = document.createElement('td');
                cell.colSpan = 9;
                cell.className = 'px-2 py-6 text-center text-slate-500';
                cell.textContent = 'Sin instalaciones';
                empty.append(cell);
                body.append(empty);
            }
            if (empty) {
                empty.hidden = !needed;
            }
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
                series: rows.map((row, index) => ({
                    type: 'line',
                    name: row.label,
                    data: row.values?.length ? row.values : labels.map(() => 0),
                    smooth: 0.35,
                    symbol: 'circle',
                    symbolSize: 6,
                    lineStyle: { width: 2, color: row.color || '#94a3b8' },
                    itemStyle: { color: row.color || '#94a3b8' },
                })),
            }, true);
        },
        pie(el, series) {
            const chart = this.mount(el);
            if (!chart) {
                return;
            }
            const labels = series.labels?.length ? series.labels : ['Link público', 'WhatsApp', 'App de patrulla', 'API', 'Panel'];
            const values = series.values?.length ? series.values : labels.map(() => 0);
            const colors = series.colors?.length ? series.colors : labels.map((_, index) => familyInks(this.payload?.inks, labels.length)[index]);
            const empty = values.every((value) => Number(value) === 0);
            const legendH = 34;
            const width = el.clientWidth || 240;
            const height = el.clientHeight || 160;
            const availH = Math.max(90, height - legendH);
            const band = 22;
            const outer = Math.max(52, Math.min(width / 2 - 8, availH / 2 - 4));
            const inner = Math.max(16, outer - band);
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
                    radius: [inner, outer],
                    center: ['50%', Math.round(availH / 2)],
                    label: {
                        show: !empty,
                        position: 'inside',
                        formatter: (item) => (Number(item.value) > 0 ? String(item.value) : ''),
                        color: '#fff',
                        fontSize: 11,
                        fontWeight: 700,
                        textBorderColor: '#0f172a',
                        textBorderWidth: 2,
                    },
                    data: labels.map((label, index) => {
                        const ink = colors[index % colors.length];
                        return {
                            name: label,
                            value: empty ? 1 : Number(values[index] || 0),
                            itemStyle: {
                                color: empty ? GRID : new window.echarts.graphic.RadialGradient(0.5, 0.5, 1, [
                                    { offset: 0.46, color: mixHex(ink, '#020617', 0.45) },
                                    { offset: 0.72, color: ink },
                                    { offset: 0.9, color: mixHex(ink, '#ffffff', 0.5) },
                                    { offset: 1, color: mixHex(ink, '#020617', 0.3) },
                                ]),
                                borderColor: SURFACE,
                                borderWidth: 2,
                                shadowBlur: 8,
                                shadowColor: 'rgba(2, 6, 23, 0.35)',
                            },
                        };
                    }),
                }],
            }, true);
        },
        gauge(el) {
            const chart = this.mount(el);
            if (!chart) {
                return;
            }
            const board = this.payload ?? {};
            const parts = [
                { name: 'Cerrados', value: Number(board.cerrado) || 0, ink: '#10b981' },
                { name: 'En trámite', value: Number(board.en_atencion) || 0, ink: '#f59e0b' },
                { name: 'Nuevos', value: Number(board.nuevo) || 0, ink: '#ef4444' },
            ].filter((part) => part.value > 0);
            const empty = parts.length === 0;
            const shown = empty
                ? [{ name: '', value: 1, ink: isLight() ? '#cbd5e1' : '#334155' }]
                : parts;
            const span = shown.reduce((sum, part) => sum + part.value, 0);
            const width = el.clientWidth || 240;
            const height = el.clientHeight || 150;
            const band = 22;
            const outer = Math.max(72, Math.min(width / 2 - 8, height - 4));
            const inner = Math.max(16, outer - band);
            chart.setOption({
                animation: false,
                tooltip: {
                    ...tip(),
                    formatter(item) {
                        return item.name ? `${item.name}: ${item.value}` : '';
                    },
                },
                series: [{
                    type: 'pie',
                    startAngle: 180,
                    clockwise: true,
                    radius: [inner, outer],
                    center: ['50%', '100%'],
                    label: { show: false },
                    emphasis: { disabled: true },
                    data: [
                        ...shown.map((part) => ({
                            name: part.name,
                            value: part.value,
                            itemStyle: { color: tubeInk(part.ink), borderWidth: 0 },
                        })),
                        {
                            name: '',
                            value: span,
                            itemStyle: { color: 'transparent' },
                            tooltip: { show: false },
                        },
                    ],
                }],
            }, true);
        },
        paintCrimes(series) {
            const el = this.$refs.crimes;
            if (!el) {
                return;
            }
            const labels = series?.labels?.length ? series.labels : [];
            const values = (series?.values || []).map((value) => Number(value) || 0);
            const colors = series?.colors || [];
            const thick = this.barSize(el, labels.length || 1);
            const inks = spreadInks(colors);
            const chart = this.mount(el);
            if (!chart) {
                return;
            }
            if (labels.length === 0) {
                chart.setOption({
                    animation: false,
                    graphic: [{ type: 'text', left: 'center', top: 'middle', style: { text: 'Sin reportes', fill: MUTED, fontSize: 12 } }],
                    xAxis: { show: false },
                    yAxis: { show: false },
                    series: [],
                }, true);
                chart.resize();
                return;
            }
            const cap = thick;
            const max = Math.max(1, ...values);
            const total = values.reduce((sum, value) => sum + value, 0);
            const track = isLight() ? '#e2e8f0' : '#1e293b';
            chart.setOption({
                animation: false,
                grid: { left: 8, right: 36, top: 4, bottom: 4, containLabel: true },
                tooltip: {
                    trigger: 'axis',
                    ...floatTip(),
                    formatter(items) {
                        const list = Array.isArray(items) ? items : [items];
                        const row = list.find((item) => item.seriesName === 'barra') || list[0];
                        return barCard(row?.axisValueLabel || row?.name || '', total, Number(row?.value) || 0);
                    },
                },
                xAxis: { type: 'value', min: 0, max, show: false },
                yAxis: {
                    type: 'category',
                    inverse: true,
                    data: labels,
                    axisLine: { show: false },
                    axisTick: { show: false },
                    axisLabel: { color: '#94a3b8', fontSize: 10, width: 96, overflow: 'truncate' },
                },
                series: [
                    {
                        type: 'bar',
                        barWidth: thick,
                        barGap: '-100%',
                        silent: true,
                        z: 1,
                        data: values.map(() => max),
                        itemStyle: { color: track, borderRadius: 0 },
                    },
                    {
                        name: 'barra',
                        type: 'bar',
                        barWidth: thick,
                        barGap: '-100%',
                        z: 2,
                        label: {
                            show: true,
                            position: 'right',
                            distance: 8,
                            color: SLATE,
                            fontSize: 11,
                            fontWeight: 600,
                            formatter: (item) => (item.value > 0 ? String(item.value) : ''),
                        },
                        data: values.map((value, index) => ({
                            value: value > 0 ? value : null,
                            itemStyle: { color: cylinderGradient(inks[index] || '#14532d', false), borderRadius: 0 },
                        })),
                    },
                    {
                        type: 'pictorialBar',
                        symbol: 'circle',
                        symbolSize: [cap, thick],
                        symbolPosition: 'end',
                        symbolOffset: [cap / 2, 0],
                        silent: true,
                        barGap: '-100%',
                        z: 3,
                        data: values.map((value, index) => ({
                            value: value > 0 ? value : null,
                            itemStyle: { color: inks[index] || '#14532d' },
                        })),
                    },
                ],
            }, true);
            chart.resize();
        },
        paintSites() {
            const el = this.$refs.sites;
            if (!el) {
                return;
            }
            const query = this.normalize(this.siteQuery);
            const rows = (this.payload?.sites?.rows || []).filter((row) => query === '' || this.normalize(row.name).includes(query));
            rows.forEach((row) => {
                const parts = row.parts || [];
                const inks = spreadInks(parts.map((part) => part.color || '#14532d'));
                row.parts = parts.map((part, index) => ({ ...part, color: inks[index] }));
            });
            const thick = this.barSize(el, rows.length || 1);
            const chart = this.mount(el);
            if (!chart) {
                return;
            }
            if (rows.length === 0) {
                chart.setOption({
                    animation: false,
                    graphic: [{ type: 'text', left: 'center', top: 'middle', style: { text: query ? 'Sin instalaciones' : 'Sin reportes', fill: MUTED, fontSize: 12 } }],
                    xAxis: { show: false },
                    yAxis: { show: false },
                    series: [],
                }, true);
                chart.resize();
                return;
            }
            const names = [];
            rows.forEach((row) => (row.parts || []).forEach((part) => {
                if (!names.includes(part.name)) {
                    names.push(part.name);
                }
            }));
            const totals = rows.map((row) => Number(row.total) || 0);
            const grand = totals.reduce((sum, value) => sum + value, 0);
            const max = Math.max(1, ...totals);
            const cap = thick;
            const track = isLight() ? '#e2e8f0' : '#1e293b';
            const stacked = names.map((name) => ({
                name,
                type: 'bar',
                stack: 'site',
                barWidth: thick,
                barGap: '-100%',
                z: 2,
                data: rows.map((row) => {
                    const part = (row.parts || []).find((item) => item.name === name);
                    return {
                        value: part ? Number(part.count) || 0 : 0,
                        itemStyle: { color: cylinderGradient(part?.color || '#94a3b8', false), borderRadius: 0 },
                    };
                }),
            }));
            chart.setOption({
                animation: false,
                grid: { left: 8, right: 36, top: 4, bottom: 4, containLabel: true },
                tooltip: {
                    trigger: 'axis',
                    ...floatTip(),
                    formatter(items) {
                        const list = Array.isArray(items) ? items : [items];
                        const title = list[0]?.axisValueLabel || list[0]?.name || '';
                        const index = list[0]?.dataIndex ?? 0;
                        const parts = list
                            .filter((item) => item.seriesName && Number(item.value) > 0)
                            .map((item) => `${escapeTip(item.seriesName)}: ${item.value}`);
                        return barCard(title, grand, totals[index] ?? 0, parts);
                    },
                },
                xAxis: { type: 'value', min: 0, max, show: false },
                yAxis: {
                    type: 'category',
                    inverse: true,
                    data: rows.map((row) => row.name),
                    axisLine: { show: false },
                    axisTick: { show: false },
                    axisLabel: { color: '#94a3b8', fontSize: 10, width: 96, overflow: 'truncate' },
                },
                series: [
                    {
                        type: 'bar',
                        barWidth: thick,
                        barGap: '-100%',
                        silent: true,
                        z: 1,
                        data: totals.map(() => max),
                        itemStyle: { color: track, borderRadius: 0 },
                    },
                    ...stacked,
                    {
                        type: 'pictorialBar',
                        symbol: 'circle',
                        symbolSize: [cap, thick],
                        symbolPosition: 'end',
                        symbolOffset: [cap / 2, 0],
                        silent: true,
                        barGap: '-100%',
                        z: 4,
                        data: rows.map((row) => {
                            const last = (row.parts || [])[(row.parts || []).length - 1];
                            return {
                                value: Number(row.total) > 0 ? Number(row.total) : null,
                                itemStyle: { color: last?.color || '#94a3b8' },
                            };
                        }),
                    },
                    {
                        type: 'bar',
                        barWidth: thick,
                        barGap: '-100%',
                        silent: true,
                        z: 5,
                        label: {
                            show: true,
                            position: 'right',
                            distance: 8,
                            color: SLATE,
                            fontSize: 11,
                            fontWeight: 600,
                            formatter: (item) => (item.value > 0 ? String(item.value) : ''),
                        },
                        data: totals.map((total) => ({
                            value: total > 0 ? total : null,
                            itemStyle: { color: 'transparent' },
                        })),
                    },
                ],
            }, true);
            chart.resize();
        },
        paintOrigins(series) {
            const node = this.$refs.origins || document.querySelector('.obs-origins');
            if (!node || !series) {
                return;
            }
            const labels = series.labels?.length ? series.labels : ['Sin origen'];
            const values = (series.values?.length ? series.values : [0]).map((value) => Number(value) || 0);
            const inks = series.colors?.length ? series.colors : familyInks(this.payload?.inks, labels.length);
            const thick = this.barSize(node, labels.length || 1);
            const cap = thick;
            const max = Math.max(1, ...values);
            const total = values.reduce((sum, value) => sum + value, 0);
            const track = isLight() ? '#e2e8f0' : '#1e293b';
            const chart = this.mount(node);
            chart.setOption({
                animation: false,
                grid: { left: 8, right: 36, top: 4, bottom: 4, containLabel: true },
                tooltip: {
                    trigger: 'axis',
                    ...floatTip(),
                    formatter(items) {
                        const list = Array.isArray(items) ? items : [items];
                        const row = list.find((item) => item.seriesName === 'barra') || list[0];
                        return barCard(row?.axisValueLabel || row?.name || '', total, Number(row?.value) || 0);
                    },
                },
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
                        barWidth: thick,
                        barGap: '-100%',
                        silent: true,
                        z: 1,
                        data: values.map(() => max),
                        itemStyle: { color: track, borderRadius: 0 },
                    },
                    {
                        name: 'barra',
                        type: 'bar',
                        barWidth: thick,
                        barGap: '-100%',
                        z: 2,
                        data: values.map((value, index) => ({
                            value: value > 0 ? value : null,
                            itemStyle: { color: cylinderGradient(inks[index] || '#14532d', false), borderRadius: 0 },
                        })),
                    },
                    {
                        type: 'pictorialBar',
                        symbol: 'circle',
                        symbolSize: [cap, thick],
                        symbolPosition: 'end',
                        symbolOffset: [cap / 2, 0],
                        silent: true,
                        barGap: '-100%',
                        z: 3,
                        data: values.map((value, index) => ({
                            value: value > 0 ? value : null,
                            itemStyle: { color: inks[index] || '#14532d' },
                        })),
                    },
                ],
            }, true);
            chart.resize();
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
                tr.dataset.site = row.name || '';
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
            this.filterRisk();
        },
    };
}
