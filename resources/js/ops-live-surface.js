function echoIsConnected() {
    const state = window.Echo?.connector?.pusher?.connection?.state;

    return state === 'connected';
}

function bindOpsLiveRefresh(refresh) {
    window.addEventListener('ops-surface-changed', (event) => {
        if (event.detail?.kind === 'supervision-ping') {
            return;
        }
        refresh();
    });
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
                if (payload?.kind !== 'supervision-ping') {
                    this.toast = actor ? `${actor} · ${summary}` : summary;
                }
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
        shifts: [],
        bubbles: [],
        board: {},
        selectedShiftId: null,
        refreshing: false,
        queued: false,
        shiftsKey: '',
        init() {
            this.url = this.$el.dataset.liveUrl || '';
            this.showClient = this.$el.dataset.showClient === '1';
            try {
                this.events = JSON.parse(this.$el.dataset.events || '[]');
            } catch {
                this.events = [];
            }
            try {
                this.shifts = JSON.parse(this.$el.dataset.shifts || '[]');
            } catch {
                this.shifts = [];
            }
            this.shiftsKey = JSON.stringify(this.shifts);
            try {
                this.bubbles = JSON.parse(this.$el.dataset.bubbles || '[]');
            } catch {
                this.bubbles = [];
            }
            try {
                this.board = JSON.parse(this.$el.dataset.board || '{}');
            } catch {
                this.board = {};
            }
            this.trailUrl = this.$el.dataset.trailUrl || '';
            this.positionsUrl = this.$el.dataset.positionsUrl || '';
            window.addEventListener('ops-surface-changed', (event) => {
                if (event.detail?.kind === 'supervision-ping') {
                    if (!this.applyPoint(event.detail.point)) {
                        this.refreshPositions();
                    }
                    return;
                }
                this.refresh();
            });
            window.dispatchEvent(new CustomEvent('observatory-shifts', { detail: this.shifts }));
        },
        async pickShift(id) {
            const numeric = Number(id);
            const next = Number(this.selectedShiftId) === numeric ? null : numeric;
            this.selectedShiftId = next;
            let row = next == null
                ? null
                : this.shifts.find((item) => Number(item.shift_id) === next);
            if (row && (!Array.isArray(row.path) || row.path.length === 0) && this.trailUrl) {
                try {
                    const res = await fetch(this.trailUrl.replace('__SHIFT__', String(next)), {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (res.ok) {
                        const trail = await res.json();
                        row = {
                            ...row,
                            path: Array.isArray(trail.path) ? trail.path : [],
                            lat: trail.lat ?? row.lat,
                            lng: trail.lng ?? row.lng,
                        };
                        this.shifts = this.shifts.map((item) => (
                            Number(item.shift_id) === Number(next) ? row : item
                        ));
                    }
                } catch {
                    // el clic siguiente vuelve a pedir el recorrido
                }
            }
            window.dispatchEvent(new CustomEvent('observatory-shift-pick', {
                detail: {
                    id: next,
                    path: Array.isArray(row?.path) ? row.path : [],
                    lat: row?.lat,
                    lng: row?.lng,
                },
            }));
        },
        applyPoint(point) {
            if (!point || point.lat == null || point.lng == null || !point.shift_id) {
                return false;
            }
            const id = Number(point.shift_id);
            const current = this.shifts.find((row) => Number(row.shift_id) === id);
            if (!current) {
                return false;
            }
            this.shifts = this.shifts.map((row) => {
                if (Number(row.shift_id) !== id) {
                    return row;
                }
                const path = Number(this.selectedShiftId) === id && Array.isArray(row.path)
                    ? row.path.concat([{ lat: Number(point.lat), lng: Number(point.lng) }])
                    : (row.path || []);

                return {
                    ...row,
                    lat: Number(point.lat),
                    lng: Number(point.lng),
                    recorded_at: point.at || row.recorded_at,
                    end: { lat: Number(point.lat), lng: Number(point.lng), at: point.at || null },
                    path,
                };
            });
            window.dispatchEvent(new CustomEvent('observatory-shifts', { detail: this.shifts }));

            return true;
        },
        async refreshPositions() {
            if (!this.positionsUrl) {
                return;
            }
            try {
                const res = await fetch(this.positionsUrl, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!res.ok) {
                    return;
                }
                const data = await res.json();
                const incoming = Array.isArray(data.shifts) ? data.shifts : [];
                const selected = this.selectedShiftId;
                this.shifts = incoming.map((row) => {
                    const prev = this.shifts.find((item) => Number(item.shift_id) === Number(row.shift_id));
                    const keepPath = Number(selected) === Number(row.shift_id) && Array.isArray(prev?.path);

                    return { ...row, path: keepPath ? prev.path : [] };
                });
                if (selected != null && !this.shifts.some((row) => Number(row.shift_id) === Number(selected))) {
                    this.selectedShiftId = null;
                }
                window.dispatchEvent(new CustomEvent('observatory-shifts', { detail: this.shifts }));
            } catch {
                // el siguiente punto vuelve a pedir las motos
            }
        },
        signalClass(row) {
            if (row?.signal === 'screen_off') {
                return 'text-amber-400';
            }
            if (row?.signal === 'online' || row?.online) {
                return 'text-emerald-400';
            }

            return 'text-red-400';
        },
        signalDot(row) {
            if (row?.signal === 'screen_off') {
                return 'bg-amber-400';
            }
            if (row?.signal === 'online' || row?.online) {
                return 'bg-emerald-400';
            }

            return 'bg-red-400';
        },
        zoneName(row) {
            const label = String(row?.zone_label || '').replace(/^Zona\s+/i, '').trim();

            return label || 'Sin zona';
        },
        shiftStatus(row) {
            const parked = row?.parked;
            const minutes = Number(parked?.minutes || 0);
            if (parked && minutes > 0) {
                const gps = parked.last_gps_label || '';

                return 'Detenido ' + minutes + ' min' + (gps ? ' · GPS ' + gps : '');
            }
            const line = String(row?.status_line || '');
            const clock = line.match(/(\d{1,2}:\d{2})\s*$/);
            if (line.startsWith('En ruta')) {
                return clock ? 'En ruta · GPS ' + clock[1] : 'En ruta';
            }
            if (clock && line.startsWith('Último GPS')) {
                return 'GPS ' + clock[1];
            }

            return 'Sin GPS';
        },
        bubbleClass(row) {
            if (row?.tone === 'red') {
                return 'border-red-500/40 bg-red-950/20';
            }
            if (row?.tone === 'sky') {
                return 'border-sky-500/40 bg-sky-950/20';
            }
            if (row?.tone === 'amber') {
                return 'border-amber-500/40 bg-amber-950/20';
            }

            return 'border-indigo-500/40 bg-slate-950/70';
        },
        async refresh() {
            if (!this.url) {
                return;
            }
            if (this.refreshing) {
                this.queued = true;
                return;
            }
            this.refreshing = true;
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
                if (data.board && typeof data.board === 'object') {
                    this.board = { ...this.board, ...data.board };
                }
                const shifts = Array.isArray(data.shifts) ? data.shifts : data.map?.shifts;
                if (Array.isArray(shifts)) {
                    const nextKey = JSON.stringify(shifts);
                    if (nextKey !== this.shiftsKey) {
                        this.shiftsKey = nextKey;
                        const selected = this.selectedShiftId;
                        this.shifts = shifts.map((row) => ({
                            ...row,
                            path: Array.isArray(row.path) ? row.path : [],
                        }));
                        if (selected != null && !this.shifts.some((row) => Number(row.shift_id) === Number(selected))) {
                            this.selectedShiftId = null;
                        }
                        window.dispatchEvent(new CustomEvent('observatory-shifts', { detail: this.shifts }));
                    }
                }
                if (Array.isArray(data.bubbles)) {
                    this.bubbles = data.bubbles;
                }
                window.dispatchEvent(new CustomEvent('ops-live-data', { detail: data }));
            } catch {
                // el siguiente aviso vuelve a pedir el tablero
            } finally {
                this.refreshing = false;
                if (this.queued) {
                    this.queued = false;
                    this.refresh();
                }
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
        serviceFeed() {
            return this.ops.service_feed || [];
        },
        supervisorReviews() {
            return this.ops.supervisor_reviews || [];
        },
        activityBubbles() {
            return this.ops.activity_bubbles || [];
        },
        bubbleClass(row) {
            if (row?.tone === 'red') {
                return 'border-red-500/40 bg-red-950/20';
            }
            if (row?.tone === 'sky') {
                return 'border-sky-500/40 bg-sky-950/20';
            }
            if (row?.tone === 'amber') {
                return 'border-amber-500/40 bg-amber-950/20';
            }

            return 'border-indigo-500/40 bg-slate-950/70';
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
