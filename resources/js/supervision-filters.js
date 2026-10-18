/** Panel «Filtros» de Supervisión: edita un borrador y escribe los valores en el formulario al aplicar. */
export function supervisionFilters(config) {
    const keys = Object.keys(config.values || {});
    const initial = Object.fromEntries(keys.map((key) => [key, String(config.values[key] ?? '')]));

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
