export function deviceChangeAlerts() {
    return {
        pollUrl: '',
        csrf: '',
        requests: [],
        seen: new Set(),
        timer: null,
        clock: null,
        ctx: null,
        init() {
            if (!this.pollUrl || this.timer) return;
            this.tick();
            this.timer = setInterval(() => this.tick(), 8000);
            this.clock = setInterval(() => {
                this.requests.forEach((row) => { row.expires_in = Math.max(0, row.expires_in - 1); });
                this.requests = this.requests.filter((row) => row.expires_in > 0);
            }, 1000);
        },
        async tick() {
            try {
                const res = await fetch(this.pollUrl, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!res.ok) return;
                const data = await res.json();
                const rows = Array.isArray(data.requests) ? data.requests : [];
                const busy = new Map(this.requests.map((row) => [row.id, row]));
                let fresh = false;
                this.requests = rows.map((row) => {
                    if (!this.seen.has(row.id)) {
                        this.seen.add(row.id);
                        fresh = true;
                    }
                    const prev = busy.get(row.id);
                    return Object.assign({ busy: false, error: '' }, row, prev ? { busy: prev.busy, error: prev.error } : {});
                });
                if (fresh) this.beep();
            } catch (_) {}
        },
        left(row) {
            const s = Math.max(0, Number(row.expires_in) || 0);
            return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
        },
        async resolve(row, approve) {
            row.busy = true;
            row.error = '';
            const body = new FormData();
            body.append('_token', this.csrf);
            try {
                const res = await fetch(approve ? row.approve_url : row.reject_url, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body,
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok && res.status !== 409) {
                    row.error = data.message || 'No se pudo completar.';
                    row.busy = false;
                    return;
                }
                this.requests = this.requests.filter((item) => item.id !== row.id);
            } catch (_) {
                row.error = 'Sin conexión. Intenta de nuevo.';
                row.busy = false;
            }
        },
        beep() {
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return;
                this.ctx = this.ctx || new Ctx();
                if (this.ctx.state === 'suspended') this.ctx.resume();
                [0, 350].forEach((delay) => {
                    setTimeout(() => {
                        const osc = this.ctx.createOscillator();
                        const gain = this.ctx.createGain();
                        osc.type = 'sine';
                        osc.frequency.value = 740;
                        gain.gain.value = 0.08;
                        osc.connect(gain);
                        gain.connect(this.ctx.destination);
                        osc.start();
                        setTimeout(() => osc.stop(), 250);
                    }, delay);
                });
            } catch (_) {}
        },
    };
}
