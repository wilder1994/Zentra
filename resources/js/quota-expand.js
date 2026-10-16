import { metalFor, money, quotePlan, unitLabel } from './plan-picker';

/**
 * Ampliar un producto contratado. Precio sugerido = lo que paga hoy + diferencia de catálogo
 * entre el plan actual y el ampliado (respeta descuentos pactados previos).
 */
export function quotaExpand(config) {
    const seatsKey = (key) => (key === 'access' ? 'manual' : key);

    return {
        catalog: config.catalog,
        cycle: config.cycle,
        seats: config.seats,
        today: Number(config.today) || 0,
        products: config.products,
        product: config.product || config.products[0]?.key || '',
        quantity: Number(config.quantity) || 1,
        agreed: config.agreed ?? '',
        touched: config.agreed !== null && config.agreed !== '',
        money,

        init() {
            if (!this.touched) {
                this.agreed = this.suggested;
            }
            this.$watch('product', () => this.resync());
            this.$watch('quantity', () => this.resync());
        },

        resync() {
            if (!this.touched) {
                this.agreed = this.suggested;
            }
        },

        resetSuggested() {
            this.touched = false;
            this.agreed = this.suggested;
        },

        get line() {
            return this.products.find((p) => p.key === this.product) || null;
        },

        get add() {
            return Math.max(0, Math.floor(Number(this.quantity) || 0));
        },

        get after() {
            const next = { ...this.seats };
            if (this.line) {
                next[seatsKey(this.product)] = (Number(next[seatsKey(this.product)]) || 0) + this.add;
            }

            return next;
        },

        get countNow() {
            return this.line ? this.line.seats : 0;
        },

        get countAfter() {
            return this.countNow + this.add;
        },

        get metalNow() {
            return metalFor(this.catalog, this.countNow, this.product);
        },

        get metalAfter() {
            return metalFor(this.catalog, this.countAfter, this.product);
        },

        get jumps() {
            return this.metalNow && this.metalAfter && this.metalNow.key !== this.metalAfter.key;
        },

        get catalogDiff() {
            return quotePlan(this.catalog, this.after, this.cycle).amount - quotePlan(this.catalog, this.seats, this.cycle).amount;
        },

        get suggested() {
            return Math.max(0, Math.round(this.today + this.catalogDiff));
        },

        get delta() {
            return (Number(this.agreed) || 0) - this.today;
        },

        unit(n) {
            return unitLabel(this.product, n);
        },

        get per() {
            return this.cycle === 'annual' ? '/año' : '/mes';
        },
    };
}
