const fold = (text) => String(text || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();

/** Ajustes → Zonas: modal para asignar zona principal y zonas compartidas a cada cliente. */
export function zoneAssigner(config = {}) {
    const zones = (config.zones || []).map((z) => ({ id: Number(z.id), name: String(z.name), active: Boolean(z.active) }));
    const original = new Map((config.rows || []).map((r) => [Number(r.id), {
        zone_id: r.zone_id === null ? null : Number(r.zone_id),
        shared: (r.shared || []).map(Number).sort((a, b) => a - b),
    }]));

    return {
        open: false,
        zones,
        rows: (config.rows || []).map((r) => ({
            id: Number(r.id),
            name: String(r.name),
            zone_id: r.zone_id === null ? null : Number(r.zone_id),
            shared: (r.shared || []).map(Number),
        })),
        query: '',
        filter: 'all',
        contextZone: null,
        selected: [],
        openShared: null,
        bulkZone: '',
        bulkShared: [],
        bulkSharedOpen: false,
        saving: false,
        error: '',
        unassigned: Number(config.unassigned || 0),

        init() {
            window.addEventListener('zone-assigner:open', (event) => this.show(event.detail || {}));
        },

        show({ zoneId = null, filter = null } = {}) {
            this.contextZone = zoneId ? Number(zoneId) : null;
            this.filter = filter || (this.contextZone ? 'zone:' + this.contextZone : 'all');
            this.bulkZone = this.contextZone ? String(this.contextZone) : '';
            this.query = '';
            this.selected = [];
            this.error = '';
            this.open = true;
            this.$nextTick(() => this.$refs.search?.focus());
        },

        close() {
            if (this.dirtyCount > 0 && !window.confirm('Hay cambios sin guardar. ¿Cerrar de todas formas?')) {
                return;
            }
            this.reset();
            this.open = false;
        },

        reset() {
            this.rows.forEach((row) => {
                const base = original.get(row.id);
                row.zone_id = base.zone_id;
                row.shared = [...base.shared];
            });
            this.selected = [];
            this.openShared = null;
        },

        zoneName(id) {
            return this.zones.find((z) => z.id === Number(id))?.name || '';
        },

        countFor(key) {
            if (key === 'all') return this.rows.length;
            if (key === 'none') return this.rows.filter((r) => r.zone_id === null).length;
            const id = Number(String(key).replace('zone:', ''));

            return this.rows.filter((r) => r.zone_id === id || r.shared.includes(id)).length;
        },

        get filtered() {
            const q = fold(this.query.trim());
            let list = this.rows;
            if (this.filter === 'none') {
                list = list.filter((r) => r.zone_id === null);
            } else if (this.filter.startsWith('zone:')) {
                const id = Number(this.filter.slice(5));
                list = list.filter((r) => r.zone_id === id || r.shared.includes(id) || (this.contextZone === id && r.zone_id === null));
            }
            if (q) list = list.filter((r) => fold(r.name).includes(q));

            return list;
        },

        status(row) {
            if (row.zone_id === null) return { label: 'Sin zona', tone: 'none' };
            if (row.shared.length) return { label: 'Compartido', tone: 'shared' };

            return { label: 'Solo ' + this.zoneName(row.zone_id), tone: 'own' };
        },

        isDirty(row) {
            const base = original.get(row.id);
            const shared = [...row.shared].sort((a, b) => a - b);

            return base.zone_id !== row.zone_id || base.shared.join(',') !== shared.join(',');
        },

        get dirtyRows() {
            return this.rows.filter((r) => this.isDirty(r));
        },

        get dirtyCount() {
            return this.dirtyRows.length;
        },

        setZone(row, value) {
            row.zone_id = value === '' ? null : Number(value);
            row.shared = row.zone_id === null ? [] : row.shared.filter((id) => id !== row.zone_id);
        },

        toggleShared(row, zoneId) {
            const id = Number(zoneId);
            row.shared = row.shared.includes(id) ? row.shared.filter((x) => x !== id) : [...row.shared, id];
        },

        sharedLabel(row) {
            return row.shared.length ? row.shared.map((id) => this.zoneName(id)).join(', ') : '—';
        },

        toggleSelect(id) {
            this.selected = this.selected.includes(id) ? this.selected.filter((x) => x !== id) : [...this.selected, id];
        },

        get allVisibleSelected() {
            return this.filtered.length > 0 && this.filtered.every((r) => this.selected.includes(r.id));
        },

        toggleAllVisible() {
            const ids = this.filtered.map((r) => r.id);
            this.selected = this.allVisibleSelected
                ? this.selected.filter((id) => !ids.includes(id))
                : [...new Set([...this.selected, ...ids])];
        },

        applyBulkZone() {
            if (this.bulkZone === '') return;
            this.rows.filter((r) => this.selected.includes(r.id)).forEach((r) => this.setZone(r, this.bulkZone));
        },

        toggleBulkShared(zoneId) {
            const id = Number(zoneId);
            this.bulkShared = this.bulkShared.includes(id) ? this.bulkShared.filter((x) => x !== id) : [...this.bulkShared, id];
        },

        applyBulkShared() {
            const missing = [];
            this.rows.filter((r) => this.selected.includes(r.id)).forEach((r) => {
                if (r.zone_id === null) {
                    missing.push(r.name);
                    return;
                }
                r.shared = [...new Set([...r.shared, ...this.bulkShared])].filter((id) => id !== r.zone_id);
            });
            this.bulkSharedOpen = false;
            this.error = missing.length ? 'Sin zona principal, no se compartieron: ' + missing.slice(0, 3).join(', ') + (missing.length > 3 ? '…' : '') : '';
        },

        async save() {
            if (!this.dirtyCount || this.saving) return;
            this.saving = true;
            this.error = '';
            try {
                const res = await fetch(config.assignUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    body: JSON.stringify({
                        changes: this.dirtyRows.map((r) => ({ client_id: r.id, zone_id: r.zone_id, shared: r.shared })),
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'No se pudo guardar.');
                }
                window.location.reload();
            } catch (e) {
                this.error = e.message;
                this.saving = false;
            }
        },
    };
}
