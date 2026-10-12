/**
 * Cotizador del plan. Misma regla que App\Services\Pricing\PlanQuoter:
 * 1. cada producto = unidad × cantidad × (1 − descuento del plan de su cantidad); Indexación cuenta empleados;
 * 2. el de mayor valor va completo; cada producto adicional lleva el descuento por combinar;
 * 3. anual = mensual ya descontado × 12 × (1 − descuento anual).
 */
export const money = (n) => '$' + Math.round(Number(n) || 0).toLocaleString('es-CO');

export const PRODUCTS = [
    { key: 'access', label: 'Accesos' },
    { key: 'supervision', label: 'Supervisión' },
    { key: 'indexing', label: 'Indexación' },
    { key: 'observatory', label: 'Observatorio' },
];

/** Indexación se cobra por empleado; los demás por instalación. */
export const countsEmployees = (product) => product === 'indexing';

export function unitLabel(product, n) {
    if (countsEmployees(product)) {
        return Number(n) === 1 ? 'empleado' : 'empleados';
    }

    return Number(n) === 1 ? 'instalación' : 'instalaciones';
}

/** Rango [min, max] de un plan para el producto; Platino no tiene tope. */
export function metalRange(catalog, metal, product) {
    const employees = countsEmployees(product);
    const min = Number(employees ? metal.emp_min : metal.min) || 1;
    if (metal.key === 'platino') {
        return [min, Number(employees ? catalog.max_employees : 10000) || min];
    }

    return [min, Number(employees ? metal.emp_max : metal.max) || min];
}

const round2 = (n) => Math.round(n * 100) / 100;

export function metalFor(catalog, seats, product = 'access') {
    if (!seats || seats < 1) {
        return null;
    }
    const metals = catalog.metals || [];

    return metals.find((m) => {
        const [min, max] = metalRange(catalog, m, product);

        return seats >= min && (seats <= max || m.key === 'platino');
    }) || metals[metals.length - 1] || null;
}

/**
 * @param {object} catalog  CatalogSettings::forClient()
 * @param {{manual?: number, hardware?: number, supervision?: number, indexing?: number, observatory?: number}} seats
 * @param {'monthly'|'annual'} cycle
 */
export function quotePlan(catalog, seats, cycle) {
    const units = catalog.units || {};
    const annualPct = Number(catalog.annual_discount || 0);
    const comboPct = Number(catalog.combo_discount || 0);
    const qty = (v) => Math.max(0, Math.floor(Number(v) || 0));
    const manual = qty(seats.manual);
    const hardware = qty(seats.hardware);
    const counts = {
        access: manual + hardware,
        supervision: qty(seats.supervision),
        indexing: qty(seats.indexing),
        observatory: qty(seats.observatory),
    };

    const lines = [];
    for (const product of PRODUCTS) {
        const n = counts[product.key];
        if (n < 1) {
            continue;
        }
        const metal = metalFor(catalog, n, product.key);
        const discount = Number(metal?.discounts?.[product.key] || 0);
        const list = product.key === 'access'
            ? manual * Number(units.access || 0) + hardware * Number(units.access_hardware || 0)
            : n * Number(units[product.key] || 0);
        lines.push({
            key: product.key,
            label: product.label,
            seats: n,
            unitLabel: unitLabel(product.key, n),
            metal,
            unit: round2(list / n),
            discount,
            list: round2(list),
            plan: round2(list * (1 - discount)),
            combo: false,
            monthly: 0,
            annual: 0,
        });
    }

    [...lines].sort((a, b) => b.plan - a.plan).forEach((line, i) => {
        line.combo = i > 0 && comboPct > 0;
        line.monthly = i === 0 ? line.plan : round2(line.plan * (1 - comboPct));
        line.annual = round2(line.monthly * 12 * (1 - annualPct));
    });

    const sum = (field) => round2(lines.reduce((acc, l) => acc + l[field], 0));
    const months = cycle === 'annual' ? 12 : 1;
    const listMonthly = sum('list');
    const planMonthly = sum('plan');
    const monthly = sum('monthly');
    const amount = cycle === 'annual' ? sum('annual') : monthly;
    const listAmount = round2(listMonthly * months);

    return {
        lines,
        listMonthly,
        monthly,
        listAmount,
        planSavings: round2((listMonthly - planMonthly) * months),
        comboSavings: round2((planMonthly - monthly) * months),
        annualSavings: cycle === 'annual' ? round2(monthly * 12 - amount) : 0,
        amount,
        savings: round2(listAmount - amount),
        comboPct,
        annualPct,
    };
}

export function planPicker(config) {
    const catalog = config.catalog || {};
    const num = (v) => Number(v ?? 0);

    return {
        manual: num(config.manual),
        hardware: num(config.hardware),
        supervision: num(config.supervision),
        indexing: num(config.indexing),
        observatory: num(config.observatory),
        cycle: config.cycle || 'monthly',
        initial: {
            manual: num(config.manual),
            hardware: num(config.hardware),
            supervision: num(config.supervision),
            indexing: num(config.indexing),
            observatory: num(config.observatory),
        },

        money,

        get hardwareEnabled() {
            return Boolean(catalog.hardware);
        },

        get accessSeats() {
            return Math.max(0, Number(this.manual) || 0) + Math.max(0, Number(this.hardware) || 0);
        },

        metalFor(seats, product = 'access') {
            return metalFor(catalog, seats, product);
        },

        get quote() {
            return quotePlan(catalog, {
                manual: this.manual,
                hardware: this.hardware,
                supervision: this.supervision,
                indexing: this.indexing,
                observatory: this.observatory,
            }, this.cycle);
        },

        get total() {
            return this.quote.amount;
        },

        get unavailable() {
            const issues = [];
            if (config.enforceAvailability === false) {
                return issues;
            }
            for (const line of this.quote.lines) {
                if (line.metal && !line.metal.available) {
                    issues.push(`${line.label} ${line.metal.label}`);
                }
            }
            if ((Number(this.hardware) || 0) > 0 && !this.hardwareEnabled) {
                issues.push('Hardware');
            }

            return issues;
        },

        get isEmpty() {
            return this.quote.lines.length === 0;
        },

        get isUnchanged() {
            return ['manual', 'hardware', 'supervision', 'indexing', 'observatory']
                .every((k) => Number(this[k]) === this.initial[k])
                && (config.initialCycle ? this.cycle === config.initialCycle : true);
        },

        get canSubmit() {
            return !this.isEmpty && this.unavailable.length === 0 && !(config.blockUnchanged && this.isUnchanged);
        },

        nextPlanHint(seats, product) {
            const m = this.metalFor(seats, product);
            if (!m || m.key === 'platino') {
                return '';
            }
            const metals = catalog.metals || [];
            const next = metals[metals.findIndex((x) => x.key === m.key) + 1];
            if (!next || !next.available) {
                return '';
            }
            const missing = next.min - seats;

            return `Con ${missing} instalaci${missing === 1 ? 'ón' : 'ones'} más pasas a ${next.label} (−${Math.round((next.discounts?.[product] || 0) * 100)}% en todas).`;
        },
    };
}
