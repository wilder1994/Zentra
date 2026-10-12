/**
 * Selector de plan por cantidad. Misma regla que App\Services\Pricing\PriceCalculator:
 * cada instalación = unidad × (1 − descuento del plan del total); al cruzar un rango todas cambian de plan.
 */
const money = (n) => '$' + Math.round(Number(n) || 0).toLocaleString('es-CO');

export function planPicker(config) {
    const catalog = config.catalog || {};

    return {
        manual: Number(config.manual ?? 0),
        hardware: Number(config.hardware ?? 0),
        supervision: Number(config.supervision ?? 0),
        cycle: config.cycle || 'monthly',
        initial: {
            manual: Number(config.manual ?? 0),
            hardware: Number(config.hardware ?? 0),
            supervision: Number(config.supervision ?? 0),
        },

        money,

        get hardwareEnabled() {
            return Boolean(catalog.hardware);
        },

        get accessSeats() {
            return Math.max(0, Number(this.manual) || 0) + Math.max(0, Number(this.hardware) || 0);
        },

        metalFor(seats) {
            if (!seats || seats < 1) {
                return null;
            }
            const metals = catalog.metals || [];

            return metals.find((m) => seats >= m.min && (seats <= m.max || m.key === 'platino')) || metals[metals.length - 1] || null;
        },

        get accessMetal() {
            return this.metalFor(this.accessSeats);
        },

        get supervisionMetal() {
            return this.metalFor(Number(this.supervision) || 0);
        },

        cycleAmount(monthly) {
            if (this.cycle !== 'annual') {
                return monthly;
            }

            return monthly * 12 * (1 - (catalog.annual_discount || 0));
        },

        get accessUnit() {
            const m = this.accessMetal;

            return m ? (catalog.units?.access || 0) * (1 - (m.discounts?.access || 0)) : 0;
        },

        get hardwareUnit() {
            const m = this.accessMetal;

            return m ? (catalog.units?.access_hardware || 0) * (1 - (m.discounts?.access || 0)) : 0;
        },

        get supervisionUnit() {
            const m = this.supervisionMetal;

            return m ? (catalog.units?.supervision || 0) * (1 - (m.discounts?.supervision || 0)) : 0;
        },

        get accessMonthly() {
            return (Math.max(0, Number(this.manual) || 0) * this.accessUnit)
                + (Math.max(0, Number(this.hardware) || 0) * this.hardwareUnit);
        },

        get supervisionMonthly() {
            return (Number(this.supervision) || 0) * this.supervisionUnit;
        },

        get totalMonthly() {
            return this.accessMonthly + this.supervisionMonthly;
        },

        get total() {
            return this.cycleAmount(this.totalMonthly);
        },

        get unavailable() {
            const issues = [];
            if (config.enforceAvailability === false) {
                return issues;
            }
            if (this.accessMetal && !this.accessMetal.available) {
                issues.push(`Accesos ${this.accessMetal.label}`);
            }
            if (this.supervisionMetal && !this.supervisionMetal.available) {
                issues.push(`Supervisión ${this.supervisionMetal.label}`);
            }
            if ((Number(this.hardware) || 0) > 0 && !this.hardwareEnabled) {
                issues.push('Hardware');
            }

            return issues;
        },

        get isEmpty() {
            return this.accessSeats < 1 && (Number(this.supervision) || 0) < 1;
        },

        get isUnchanged() {
            return Number(this.manual) === this.initial.manual
                && Number(this.hardware) === this.initial.hardware
                && Number(this.supervision) === this.initial.supervision
                && (config.initialCycle ? this.cycle === config.initialCycle : true);
        },

        get canSubmit() {
            return !this.isEmpty && this.unavailable.length === 0 && !(config.blockUnchanged && this.isUnchanged);
        },

        nextPlanHint(seats, product) {
            const m = this.metalFor(seats);
            if (!m || m.key === 'platino') {
                return '';
            }
            const metals = catalog.metals || [];
            const next = metals[metals.findIndex((x) => x.key === m.key) + 1];
            if (!next || !next.available) {
                return '';
            }
            const missing = next.min - seats;
            const label = product === 'supervision' ? 'cliente' : 'instalación';

            return `Con ${missing} ${label}${missing === 1 ? '' : (product === 'supervision' ? 's' : 'es')} más pasas a ${next.label} (−${Math.round((next.discounts?.[product] || 0) * 100)}% en todas).`;
        },

        signupUrl(base) {
            const params = new URLSearchParams({ cycle: this.cycle });
            if (Number(this.manual) > 0) params.set('access', String(Number(this.manual)));
            if (Number(this.hardware) > 0) params.set('hardware', String(Number(this.hardware)));
            if (Number(this.supervision) > 0) params.set('supervision', String(Number(this.supervision)));

            return `${base}?${params.toString()}`;
        },
    };
}
