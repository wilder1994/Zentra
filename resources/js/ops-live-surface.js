function echoIsConnected() {
    const state = window.Echo?.connector?.pusher?.connection?.state;

    return state === 'connected';
}

function bindOpsLiveRefresh(refresh) {
    window.addEventListener('ops-surface-changed', () => refresh());
    window.setInterval(() => {
        if (!echoIsConnected()) {
            refresh();
        }
    }, 12000);
}

export function opsLiveSurface() {
    return {
        toast: '',
        init() {
            const company = this.$el.dataset.company || '';
            const client = this.$el.dataset.client || '';
            const echo = window.Echo;
            const onChanged = (payload) => {
                const actor = payload?.actor || '';
                const summary = payload?.summary || 'Tablero actualizado';
                this.toast = actor ? `${actor} · ${summary}` : summary;
                window.clearTimeout(this.hideTimer);
                this.hideTimer = window.setTimeout(() => {
                    this.toast = '';
                }, 7000);
                window.dispatchEvent(new CustomEvent('ops-surface-changed', { detail: payload || {} }));
            };

            if (echo && company) {
                echo.private(`ops.company.${company}`).listen('.changed', onChanged);
            }
            if (echo && client) {
                echo.private(`ops.client.${client}`).listen('.changed', onChanged);
            }
        },
    };
}

export function opsLivePage() {
    return {
        url: '',
        showClient: false,
        events: [],
        init() {
            this.url = this.$el.dataset.liveUrl || '';
            this.showClient = this.$el.dataset.showClient === '1';
            try {
                this.events = JSON.parse(this.$el.dataset.events || '[]');
            } catch {
                this.events = [];
            }
            bindOpsLiveRefresh(() => this.refresh());
        },
        async refresh() {
            if (!this.url) {
                return;
            }
            try {
                const res = await fetch(this.url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!res.ok) {
                    return;
                }
                const data = await res.json();
                this.events = Array.isArray(data.events) ? data.events : this.events;
                window.dispatchEvent(new CustomEvent('ops-live-data', { detail: data }));
            } catch {
                // red caída: el siguiente tick reintenta
            }
        },
    };
}

export function sigBoardLive() {
    return {
        url: '',
        board: {},
        chart: null,
        init() {
            this.url = this.$el.dataset.liveUrl || '';
            this.board = JSON.parse(this.$el.dataset.board || '{}');
            this.drawChart();
            bindOpsLiveRefresh(() => this.refresh());
        },
        async refresh() {
            if (!this.url) {
                return;
            }
            try {
                const res = await fetch(this.url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!res.ok) {
                    return;
                }
                this.board = await res.json();
                this.drawChart();
            } catch {
                //
            }
        },
        drawChart() {
            const el = this.$refs.chart;
            if (!el || typeof window.Chart === 'undefined') {
                return;
            }
            const ChartLib = window.Chart;
            if (!ChartLib) {
                return;
            }
            if (this.chart) {
                this.chart.destroy();
            }
            const chart = this.board.chart || { labels: [], values: [] };
            this.chart = new ChartLib(el, {
                type: 'bar',
                data: {
                    labels: chart.labels || [],
                    datasets: [{ label: 'Servicios activos', data: chart.values || [], backgroundColor: '#2dd4bf' }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: '#94a3b8' } },
                        y: { ticks: { color: '#94a3b8' }, beginAtZero: true },
                    },
                },
            });
        },
        chartSum() {
            return (this.board.chart?.values || []).reduce((sum, n) => sum + Number(n || 0), 0);
        },
    };
}

export function companyDashboardLive() {
    return {
        url: '',
        metrics: {},
        ops: { kpis: {}, workforce: {}, portfolio: {}, open_shifts_table: [], personnel: {} },
        field: null,
        init() {
            this.url = this.$el.dataset.liveUrl || '';
            try {
                const payload = JSON.parse(this.$el.dataset.payload || '{}');
                this.apply(payload);
            } catch {
                //
            }
            bindOpsLiveRefresh(() => this.refresh());
        },
        apply(payload) {
            this.metrics = payload.metrics || this.metrics;
            this.ops = payload.ops || this.ops;
            this.field = payload.field ?? this.field;
        },
        async refresh() {
            if (!this.url) {
                return;
            }
            try {
                const res = await fetch(this.url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!res.ok) {
                    return;
                }
                this.apply(await res.json());
                this.drawCharts();
            } catch {
                //
            }
        },
        kpi(key) {
            return this.ops.kpis?.[key] ?? 0;
        },
        portfolio(key) {
            return this.ops.portfolio?.[key] ?? 0;
        },
        workforce(key) {
            return this.ops.workforce?.[key] ?? 0;
        },
        personnel(key) {
            return this.ops.personnel?.[key] ?? 0;
        },
        personnelPath(path) {
            const parts = String(path || '').split('.');
            let cur = this.ops.personnel;
            for (const part of parts) {
                if (cur == null || typeof cur !== 'object') {
                    return null;
                }
                cur = cur[part];
            }
            return cur ?? null;
        },
        shifts() {
            return this.ops.open_shifts_table || [];
        },
        fieldVal(key) {
            return this.field?.[key] ?? 0;
        },
        drawCharts() {
            const ChartLib = window.Chart;
            if (!ChartLib) {
                return;
            }
            const defaults = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#94a3b8', boxWidth: 10, font: { size: 10 }, padding: 12 },
                    },
                },
                scales: {
                    x: {
                        ticks: { color: '#64748b', font: { size: 9 }, maxRotation: 0 },
                        grid: { color: '#1e293b' },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#64748b', precision: 0, font: { size: 9 } },
                        grid: { color: '#1e293b' },
                    },
                },
            };
            this.stackedBar('companyRevistaMonthlyChart', this.ops.revista_monthly, ChartLib, defaults);
            this.stackedBar('companyRevistaWeekChart', this.ops.revista_week, ChartLib, defaults);
            this.accessBar('companyAccessChart', this.ops.access_by_client || [], ChartLib, defaults);
            const charts = this.ops.personnel?.charts || {};
            this.donut('companyParafiscalDonut', charts.parafiscal_donut, ['#34d399', '#334155'], ChartLib);
            this.donut('companyDocsDonut', charts.docs_donut, ['#818cf8', '#334155'], ChartLib);
            this.line('companyParafiscalTrend', charts.parafiscal_trend, ChartLib, defaults);
            this.folderBars('companyDocsFolders', charts.folder_bars, ChartLib, defaults);
        },
        donut(id, payload, colors, ChartLib) {
            const el = document.getElementById(id);
            if (!el) {
                return;
            }
            ChartLib.getChart(el)?.destroy();
            const data = payload || {};
            new ChartLib(el, {
                type: 'doughnut',
                data: {
                    labels: data.labels?.length ? data.labels : ['—'],
                    datasets: [{
                        data: data.values?.length ? data.values : [0],
                        backgroundColor: colors,
                        borderWidth: 0,
                        hoverOffset: 4,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { color: '#94a3b8', boxWidth: 10, font: { size: 10 }, padding: 10 },
                        },
                    },
                },
            });
        },
        line(id, payload, ChartLib, defaults) {
            const el = document.getElementById(id);
            if (!el) {
                return;
            }
            ChartLib.getChart(el)?.destroy();
            const data = payload || {};
            new ChartLib(el, {
                type: 'line',
                data: {
                    labels: data.labels?.length ? data.labels : ['—'],
                    datasets: [{
                        label: 'Con planilla',
                        data: data.values?.length ? data.values : [0],
                        borderColor: '#34d399',
                        backgroundColor: 'rgba(52, 211, 153, 0.12)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointBackgroundColor: '#34d399',
                    }],
                },
                options: {
                    ...defaults,
                    plugins: { legend: { display: false } },
                },
            });
        },
        folderBars(id, payload, ChartLib, defaults) {
            const el = document.getElementById(id);
            if (!el) {
                return;
            }
            ChartLib.getChart(el)?.destroy();
            const data = payload || {};
            const labels = data.labels?.length
                ? data.labels.map((l) => (l && l.length > 10 ? l.slice(0, 9) + '…' : (l || '—')))
                : ['—'];
            new ChartLib(el, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Con soporte',
                        data: data.values?.length ? data.values : [0],
                        backgroundColor: ['#818cf8', '#22d3ee', '#fbbf24'],
                        borderRadius: 3,
                    }],
                },
                options: {
                    ...defaults,
                    indexAxis: 'y',
                    plugins: { legend: { display: false } },
                },
            });
        },
        stackedBar(id, payload, ChartLib, defaults) {
            const el = document.getElementById(id);
            if (!el) {
                return;
            }
            ChartLib.getChart(el)?.destroy();
            const data = payload || {};
            const labels = data.labels?.length ? data.labels : ['—'];
            new ChartLib(el, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        { label: 'Meta', data: data.expected?.length ? data.expected : [0], backgroundColor: '#14b8a6', borderRadius: 2 },
                        { label: 'Realizados', data: data.done?.length ? data.done : [0], backgroundColor: '#d6b07c', borderRadius: 2 },
                        { label: 'Pendientes', data: data.pending?.length ? data.pending : [0], backgroundColor: '#64748b', borderRadius: 2 },
                    ],
                },
                options: {
                    ...defaults,
                    scales: {
                        ...defaults.scales,
                        x: { ...defaults.scales.x, stacked: false },
                        y: { ...defaults.scales.y, stacked: false },
                    },
                },
            });
        },
        accessBar(id, rows, ChartLib, defaults) {
            const el = document.getElementById(id);
            if (!el) {
                return;
            }
            ChartLib.getChart(el)?.destroy();
            const accessRows = Array.isArray(rows) ? rows : [];
            const labels = accessRows.length
                ? accessRows.map((r) => (r.label && r.label.length > 12 ? r.label.slice(0, 12) + '…' : (r.label || '—')))
                : ['—'];
            new ChartLib(el, {
                type: 'bar',
                data: {
                    labels,
                    datasets: accessRows.length
                        ? [
                            { label: 'Vehículos', data: accessRows.map((r) => r.vehicles), backgroundColor: '#8b5cf6', borderRadius: 2 },
                            { label: 'Visitantes', data: accessRows.map((r) => r.visitors), backgroundColor: '#64748b', borderRadius: 2 },
                        ]
                        : [{ label: 'Accesos', data: [0], backgroundColor: '#334155' }],
                },
                options: defaults,
            });
        },
    };
}
