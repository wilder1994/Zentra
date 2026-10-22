const fold = (text) => String(text || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();

/**
 * Panel «Filtros» del Observatorio: borrador en el modal, buscador de cliente/instalación
 * y al aplicar escribe los valores en el formulario del encabezado y lo envía.
 */
export function observatoryFilters(config) {
    const keys = ['place', 'status', 'type', 'comuna', 'q'];
    const initial = Object.fromEntries(keys.map((key) => [key, String(config.values?.[key] ?? '')]));
    const places = (config.places || []).map((o) => ({
        value: String(o.value),
        label: String(o.label),
        hint: o.hint ? String(o.hint) : '',
        key: fold(`${o.label} ${o.hint || ''}`),
    }));

    return {
        open: false,
        values: { ...initial },
        draft: { ...initial },
        placeQuery: '',
        placeOpen: false,
        placeActive: 0,
        form: null,

        init() {
            this.form = this.$el.closest('form');
        },

        get count() {
            return keys.filter((key) => this.values[key] !== '').length;
        },

        get clientId() {
            return this.values.place.startsWith('client:') ? this.values.place.slice(7) : '';
        },

        get installationId() {
            return this.values.place.startsWith('site:') ? this.values.place.slice(5) : '';
        },

        get placeLabel() {
            return places.find((o) => o.value === this.draft.place)?.label || '';
        },

        get placeMatches() {
            const q = fold(this.placeQuery.trim());
            const list = q === '' ? places : places.filter((o) => o.key.includes(q));

            return list.slice(0, 60);
        },

        show() {
            this.draft = { ...this.values };
            this.placeQuery = '';
            this.placeOpen = false;
            this.open = true;
        },

        close() {
            this.open = false;
            this.placeOpen = false;
        },

        openPlaces() {
            this.placeOpen = true;
            this.placeActive = 0;
            this.$nextTick(() => this.$refs.placeSearch?.focus());
        },

        movePlace(step) {
            const n = this.placeMatches.length;
            if (!this.placeOpen) {
                this.openPlaces();

                return;
            }
            if (n > 0) {
                this.placeActive = (this.placeActive + step + n) % n;
                this.$nextTick(() => this.$refs.placeList?.children[this.placeActive]?.scrollIntoView({ block: 'nearest' }));
            }
        },

        pickPlace(option) {
            this.draft.place = option ? option.value : '';
            this.placeQuery = '';
            this.placeOpen = false;
        },

        enterPlace() {
            const option = this.placeMatches[this.placeActive];
            if (option) {
                this.pickPlace(option);
            }
        },

        clear() {
            keys.forEach((key) => {
                this.draft[key] = '';
            });
            this.placeQuery = '';
        },

        apply() {
            this.values = { ...this.draft };
            this.open = false;
            this.$nextTick(() => this.form?.submit());
        },
    };
}
