export function opsLiveAlerts() {
    return {
        pollUrl: '',
        panicUrl: '',
        claimUrl: '',
        csrf: '',
        after: 0,
        open: false,
        panicBusy: false,
        title: '',
        body: '',
        type: '',
        alertId: 0,
        canAttend: false,
        note: '',
        toasts: [],
        lat: '',
        lng: '',
        timer: null,
        alarmTimer: null,
        reportAlarm: null,
        ctx: null,
        booted: false,
        inflight: false,
        again: false,
        init() {
            const last = Number(localStorage.getItem('ops_alert_after') || '0');
            this.after = Number.isFinite(last) ? last : 0;
            this.locate();
            this.tick();
            window.addEventListener('ops-surface-changed', (event) => {
                if (event.detail?.kind === 'supervision-ping') {
                    return;
                }
                this.tick();
            });
            window.addEventListener('observatory-pin-clicked', () => this.stopReportAlarm());
            this.timer = setInterval(() => {
                const state = window.Echo?.connector?.pusher?.connection?.state;
                if (state !== 'connected') {
                    this.tick();
                }
            }, 12000);
        },
        locate() {
            if (!navigator.geolocation) return;
            navigator.geolocation.getCurrentPosition((p) => {
                this.lat = p.coords.latitude.toFixed(7);
                this.lng = p.coords.longitude.toFixed(7);
            }, () => {}, { timeout: 4000 });
        },
        async tick() {
            if (!this.pollUrl) return;
            if (this.inflight) {
                this.again = true;
                return;
            }
            this.inflight = true;
            try {
                do {
                    this.again = false;
                    await this.pull();
                } while (this.again);
            } finally {
                this.inflight = false;
            }
        },
        async pull() {
            try {
            const res = await fetch(`${this.pollUrl}?after=0`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!res.ok) return;
                const data = await res.json();
                const alerts = Array.isArray(data.alerts) ? data.alerts : [];
                if (window.Alpine?.store('opsNotices')) {
                    window.Alpine.store('opsNotices').items = alerts;
                }
                this.syncReports(alerts);
                this.booted = true;
                const panics = alerts.filter((row) => row.type === 'panic' && Number(row.id) > this.after);
                if (this.open) {
                    const current = alerts.find((row) => Number(row.id) === this.alertId);
                    if (!current) {
                        this.ack();
                        return;
                    }
                    this.canAttend = Boolean(current.can_attend);
                    return;
                }
                if (!panics.length) return;
                this.stopReportAlarm();
                const first = panics[0];
                this.alertId = Number(first.id) || 0;
                this.type = first.type || '';
                this.title = first.title;
                this.body = first.body;
                this.canAttend = Boolean(first.can_attend);
                this.open = true;
                this.startAlarm();
            } catch (_) {}
        },
        startAlarm() {
            this.stopAlarm();
            this.beep();
            this.alarmTimer = setInterval(() => this.beep(), this.type === 'panic' ? 1200 : 900);
        },
        stopAlarm() {
            if (this.alarmTimer) {
                clearInterval(this.alarmTimer);
                this.alarmTimer = null;
            }
        },
        startReportAlarm() {
            if (this.open) {
                return;
            }
            this.stopReportAlarm();
            this.beepAt(620);
            this.reportAlarm = setInterval(() => this.beepAt(620), 900);
        },
        stopReportAlarm() {
            if (this.reportAlarm) {
                clearInterval(this.reportAlarm);
                this.reportAlarm = null;
            }
        },
        beep() {
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return;
                this.ctx = this.ctx || new Ctx();
                if (this.ctx.state === 'suspended') {
                    this.ctx.resume();
                }
                const osc = this.ctx.createOscillator();
                const gain = this.ctx.createGain();
                osc.type = 'sawtooth';
                osc.frequency.value = this.type === 'panic' ? 880 : 620;
                this.play(osc, gain);
            } catch (_) {}
        },
        beepAt(frequency) {
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return;
                this.ctx = this.ctx || new Ctx();
                if (this.ctx.state === 'suspended') {
                    this.ctx.resume();
                }
                const osc = this.ctx.createOscillator();
                const gain = this.ctx.createGain();
                osc.type = 'sawtooth';
                osc.frequency.value = frequency;
                this.play(osc, gain);
            } catch (_) {}
        },
        play(osc, gain) {
            gain.gain.value = 0.08;
            osc.connect(gain);
            gain.connect(this.ctx.destination);
            osc.start();
            setTimeout(() => osc.stop(), 700);
        },
        toastSeen() {
            try {
                return new Set(JSON.parse(localStorage.getItem('ops_obs_toast') || '[]').map(Number));
            } catch {
                return new Set();
            }
        },
        rememberToast(id) {
            const seen = this.toastSeen();
            seen.add(Number(id));
            localStorage.setItem('ops_obs_toast', JSON.stringify([...seen].slice(-40)));
        },
        syncReports(alerts) {
            const onMap = Boolean(document.querySelector('[data-obs-map]'));
            const seen = this.toastSeen();
            let hear = false;
            alerts.filter((row) => row.type === 'observatory').forEach((row) => {
                const id = Number(row.id);
                if (!id || seen.has(id) || this.toasts.some((item) => Number(item.id) === id)) {
                    return;
                }
                this.rememberToast(id);
                if (!this.booted) {
                    return;
                }
                if (!onMap) {
                    this.toasts.push(row);
                }
                if (row.can_attend) {
                    hear = true;
                }
            });
            if (hear) {
                this.startReportAlarm();
            }
        },
        dismissToast(id) {
            this.toasts = this.toasts.filter((row) => Number(row.id) !== Number(id));
            this.stopReportAlarm();
        },
        ack() {
            this.stopAlarm();
            if (this.alertId) {
                this.after = Math.max(this.after, this.alertId);
                localStorage.setItem('ops_alert_after', String(this.after));
            }
            this.open = false;
            this.title = '';
            this.body = '';
            this.canAttend = false;
            this.alertId = 0;
            this.type = '';
        },
        async attend() {
            if (!this.claimUrl || !this.alertId) return;
            const body = new FormData();
            body.append('_token', this.csrf);
            body.append('alert_id', String(this.alertId));
            try {
                const res = await fetch(this.claimUrl, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body,
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));
                this.ack();
                if (res.ok && data.url) {
                    window.location.href = data.url;
                    return;
                }
            } catch (_) {
                this.ack();
            }
        },
        async sendPanic() {
            if (this.panicBusy || !this.panicUrl) return;
            this.panicBusy = true;
            this.locate();
            const body = new FormData();
            body.append('_token', this.csrf);
            if (this.lat) body.append('latitude', this.lat);
            if (this.lng) body.append('longitude', this.lng);
            try {
                await fetch(this.panicUrl, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body,
                    credentials: 'same-origin',
                });
            } finally {
                this.panicBusy = false;
                this.note = '';
            }
        },
    };
}
