export function settingsHub(config) {
    return {
        groups: config.groups || {},
        panelUrlTemplate: config.panelUrl || '',
        layer: 'hub',
        group: null,
        catalog: null,
        panelHtml: '',
        loading: false,
        error: '',
        origin: null,
        animating: false,

        init() {
            const g = config.openGroup || '';
            const c = config.openCatalog || '';
            if (g && this.groups[g]) {
                this.group = g;
                this.layer = 'group';
                if (c && this.groups[g].items[c]) {
                    this.$nextTick(() => this.openCatalog(c, null, true));
                }
            }
        },

        openGroup(key, event) {
            if (!this.groups[key]) return;
            this.captureOrigin(event);
            this.group = key;
            this.catalog = null;
            this.panelHtml = '';
            this.error = '';
            this.layer = 'group';
            this.pulse();
            this.syncUrl(key, null);
        },

        async openCatalog(key, event, skipOrigin = false) {
            if (!this.group || !this.groups[this.group]?.items[key]) return;
            if (!skipOrigin) this.captureOrigin(event);
            this.catalog = key;
            this.layer = 'catalog';
            this.pulse();
            this.syncUrl(this.group, key);
            await this.loadPanel(key);
        },

        back() {
            if (this.layer === 'catalog') {
                this.catalog = null;
                this.panelHtml = '';
                this.error = '';
                this.layer = 'group';
                this.pulse();
                this.syncUrl(this.group, null);
                return;
            }
            if (this.layer === 'group') {
                this.group = null;
                this.layer = 'hub';
                this.origin = null;
                this.syncUrl(null, null);
            }
        },

        closeAll() {
            this.layer = 'hub';
            this.group = null;
            this.catalog = null;
            this.panelHtml = '';
            this.error = '';
            this.origin = null;
            this.syncUrl(null, null);
        },

        captureOrigin(event) {
            const el = event?.currentTarget;
            if (!(el instanceof HTMLElement)) {
                this.origin = null;
                return;
            }
            const r = el.getBoundingClientRect();
            this.origin = {
                top: `${r.top}px`,
                left: `${r.left}px`,
                width: `${r.width}px`,
                height: `${r.height}px`,
            };
        },

        pulse() {
            this.animating = true;
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    this.animating = false;
                });
            });
        },

        stageStyle() {
            if (!this.origin || this.layer === 'hub') return {};
            if (this.animating) {
                return {
                    top: this.origin.top,
                    left: this.origin.left,
                    width: this.origin.width,
                    height: this.origin.height,
                    borderRadius: '1rem',
                    transform: 'none',
                };
            }
            return {};
        },

        async loadPanel(key) {
            this.loading = true;
            this.error = '';
            this.panelHtml = '';
            try {
                const url = this.panelUrlTemplate.replace('__KEY__', encodeURIComponent(key));
                const res = await fetch(url, {
                    headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!res.ok) throw new Error('No se pudo cargar el catálogo.');
                this.panelHtml = await res.text();
                this.$nextTick(() => {
                    if (window.Alpine?.initTree && this.$refs.panel) {
                        window.Alpine.initTree(this.$refs.panel);
                    }
                });
            } catch (e) {
                this.error = e?.message || 'Error al cargar.';
            } finally {
                this.loading = false;
            }
        },

        syncUrl(group, catalog) {
            const url = new URL(window.location.href);
            if (group) url.searchParams.set('group', group);
            else url.searchParams.delete('group');
            if (catalog) url.searchParams.set('catalog', catalog);
            else url.searchParams.delete('catalog');
            window.history.replaceState({}, '', url);
        },

        groupLabel() {
            return this.group ? this.groups[this.group]?.label : '';
        },

        catalogLabel() {
            if (!this.group || !this.catalog) return '';
            return this.groups[this.group]?.items?.[this.catalog]?.label || '';
        },

        items() {
            if (!this.group) return [];
            return Object.entries(this.groups[this.group].items || {}).map(([key, item]) => ({
                key,
                ...item,
            }));
        },
    };
}
