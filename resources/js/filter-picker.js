const fold = (text) => String(text || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();

/**
 * Selector con búsqueda: al abrir muestra toda la lista, filtra mientras se escribe
 * (sin tildes) y al elegir envía el formulario que lo contiene.
 */
export function filterPicker(config) {
    const options = (config.options || []).map((o) => ({
        value: String(o.value),
        label: String(o.label),
        hint: o.hint ? String(o.hint) : '',
        key: fold(o.label + ' ' + (o.hint || '')),
    }));
    const allLabel = config.allLabel || 'Todos';

    return {
        options,
        value: String(config.value ?? ''),
        query: '',
        open: false,
        active: 0,
        form: null,

        init() {
            this.form = this.$el.closest('form');
        },

        get current() {
            return this.options.find((o) => o.value === this.value)?.label || allLabel;
        },

        get matches() {
            const q = fold(this.query.trim());
            const all = [{ value: '', label: allLabel, hint: '', key: fold(allLabel) }, ...this.options];
            const list = q === '' ? all : all.filter((o) => o.value !== '' && o.key.includes(q));

            return list.slice(0, 80);
        },

        show() {
            this.query = '';
            this.open = true;
            const index = this.matches.findIndex((o) => o.value === this.value);
            this.active = index >= 0 ? index : 0;
            this.$nextTick(() => this.$refs.search?.focus());
        },

        close() {
            this.open = false;
            this.query = '';
        },

        type() {
            this.open = true;
            this.active = 0;
        },

        move(step) {
            if (!this.open) {
                this.show();

                return;
            }
            const n = this.matches.length;
            if (n > 0) {
                this.active = (this.active + step + n) % n;
                this.$nextTick(() => this.$refs.list?.children[this.active]?.scrollIntoView({ block: 'nearest' }));
            }
        },

        enter() {
            const option = this.matches[this.active];
            if (this.open && option) {
                this.pick(option);
            }
        },

        pick(option) {
            this.close();
            if (option.value === this.value) {
                return;
            }
            this.value = option.value;
            this.$nextTick(() => this.form?.submit());
        },
    };
}
