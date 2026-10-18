/** Panel «Filtros» de Fichas: edita un borrador y escribe los valores en el formulario al aplicar. */
export function supervisionSheetFilters(config) {
    const keys = ['zone_id', 'supervisor_id', 'kind', 'client_id', 'novelty'];
    const initial = Object.fromEntries(keys.map((key) => [key, String(config.values?.[key] ?? '')]));

    return {
        open: false,
        values: { ...initial },
        draft: { ...initial },
        form: null,

        init() {
            this.form = this.$el.closest('form');
        },

        get count() {
            return keys.filter((key) => this.values[key] !== '').length;
        },

        show() {
            this.draft = { ...this.values };
            this.open = true;
        },

        close() {
            this.open = false;
        },

        clear() {
            keys.forEach((key) => {
                this.draft[key] = '';
            });
        },

        apply() {
            this.values = { ...this.draft };
            this.open = false;
            this.$nextTick(() => this.form?.submit());
        },
    };
}
