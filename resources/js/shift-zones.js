/** Supervisión → En vivo: habilitar zonas extra a un supervisor durante su turno. */
export function shiftZones() {
    return {
        open: false,
        loading: false,
        saving: false,
        error: '',
        notice: '',
        url: '',
        user: '',
        base: null,
        extra: [],
        zones: [],
        picked: [],

        init() {
            window.addEventListener('shift-zones:open', (event) => this.show(event.detail || {}));
        },

        async show({ url, user }) {
            this.url = url;
            this.user = user || 'Supervisor';
            this.error = '';
            this.notice = '';
            this.open = true;
            this.loading = true;
            try {
                const res = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!res.ok) throw new Error('No se pudieron cargar las zonas del turno.');
                this.apply(await res.json());
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        },

        apply(data) {
            this.base = data.base || null;
            this.extra = data.extra || [];
            this.zones = data.zones || [];
            this.picked = this.extra.map((z) => Number(z.id));
        },

        get options() {
            return this.zones.filter((z) => !this.base || Number(z.id) !== Number(this.base.id));
        },

        addedInfo(zoneId) {
            const row = this.extra.find((z) => Number(z.id) === Number(zoneId));

            return row ? 'Habilitada ' + (row.added_at || '') + (row.added_by ? ' por ' + row.added_by : '') : '';
        },

        toggle(zoneId) {
            const id = Number(zoneId);
            this.picked = this.picked.includes(id) ? this.picked.filter((x) => x !== id) : [...this.picked, id];
        },

        async save() {
            this.saving = true;
            this.error = '';
            try {
                const res = await fetch(this.url, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    body: JSON.stringify({ zones: this.picked }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'No se pudo guardar.');
                this.apply(data);
                this.notice = data.message || 'Guardado.';
                window.dispatchEvent(new CustomEvent('supervision:refresh-live'));
            } catch (e) {
                this.error = e.message;
            } finally {
                this.saving = false;
            }
        },
    };
}
