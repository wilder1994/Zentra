const DISMISSED_KEY = 'zentra-geo-dismissed';

export function geoRequestAlerts() {
    return {
        pollUrl: '',
        onReviewPage: false,
        requests: [],
        dismissed: new Set(),
        seen: new Set(),
        timer: null,
        ctx: null,
        get visible() {
            return this.onReviewPage ? [] : this.requests.filter((row) => !this.dismissed.has(row.id)).slice(0, 3);
        },
        init() {
            if (!this.pollUrl || this.timer) return;
            try {
                JSON.parse(sessionStorage.getItem(DISMISSED_KEY) || '[]').forEach((id) => this.dismissed.add(id));
            } catch (_) {}
            this.tick();
            this.timer = setInterval(() => this.tick(), 15000);
        },
        async tick() {
            try {
                const res = await fetch(this.pollUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!res.ok) return;
                const data = await res.json();
                const rows = Array.isArray(data.requests) ? data.requests : [];
                let fresh = false;
                rows.forEach((row) => {
                    if (!this.seen.has(row.id)) {
                        this.seen.add(row.id);
                        if (!this.dismissed.has(row.id)) fresh = true;
                    }
                });
                this.requests = rows;
                if (fresh && !this.onReviewPage) this.beep();
            } catch (_) {}
        },
        dismiss(row) {
            this.dismissed.add(row.id);
            try {
                sessionStorage.setItem(DISMISSED_KEY, JSON.stringify([...this.dismissed]));
            } catch (_) {}
        },
        distance(row) {
            const m = Number(row.distance_m) || 0;
            return m >= 1000 ? `${(m / 1000).toFixed(1).replace('.', ',')} km` : `${m} m`;
        },
        beep() {
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return;
                this.ctx = this.ctx || new Ctx();
                if (this.ctx.state === 'suspended') this.ctx.resume();
                const osc = this.ctx.createOscillator();
                const gain = this.ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = 660;
                gain.gain.value = 0.06;
                osc.connect(gain);
                gain.connect(this.ctx.destination);
                osc.start();
                setTimeout(() => osc.stop(), 220);
            } catch (_) {}
        },
    };
}
