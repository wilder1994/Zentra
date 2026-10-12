/**
 * Tarjetas de plan (/planes) y carrito (/planes/carrito). El carrito vive en localStorage:
 * una línea por producto; agregar el mismo producto reemplaza la cantidad.
 */
import { money, metalFor, quotePlan } from './plan-picker';

const STORAGE_KEY = 'zentra.plan-cart.v1';
const MAX_SEATS = 10000;
const KEYS = ['access', 'hardware', 'supervision', 'indexing', 'observatory'];

export const CART_LABELS = {
    access: 'Accesos',
    hardware: 'Accesos con hardware',
    supervision: 'Supervisión',
    indexing: 'Indexación',
    observatory: 'Observatorio',
};

export function readCart() {
    try {
        const raw = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '{}');
        const items = {};
        for (const key of KEYS) {
            const n = Math.floor(Number(raw.items?.[key]) || 0);
            if (n > 0) {
                items[key] = Math.min(MAX_SEATS, n);
            }
        }

        return { cycle: raw.cycle === 'annual' ? 'annual' : 'monthly', items };
    } catch {
        return { cycle: 'monthly', items: {} };
    }
}

export function writeCart(cart) {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(cart));
    window.dispatchEvent(new CustomEvent('plan-cart-updated', { detail: cart }));
}

const cartCount = (cart) => Object.keys(cart.items).length;

/** Precio de un producto en el carrito: Accesos y hardware comparten el plan del total de Accesos. */
const pricingKey = (key) => (key === 'hardware' ? 'access' : key);

export function planCartBadge() {
    return {
        count: 0,
        init() {
            this.count = cartCount(readCart());
            window.addEventListener('plan-cart-updated', (e) => { this.count = cartCount(e.detail); });
            window.addEventListener('storage', () => { this.count = cartCount(readCart()); });
        },
    };
}

/**
 * Tarjeta de un plan (metal). Cada fila: casilla, precio por instalación, cantidad dentro del rango del plan.
 */
export function planCard(config) {
    const catalog = config.catalog || {};
    const metal = (catalog.metals || []).find((m) => m.key === config.metal) || {};
    const min = Number(metal.min || 1);
    const max = metal.key === 'platino' ? MAX_SEATS : Number(metal.max || min);
    const rows = config.rows || [];
    const state = {};
    for (const row of rows) {
        state[row] = { on: row === 'access', qty: min };
    }

    return {
        money,
        metal,
        min,
        max,
        rows: state,
        notice: '',
        noticeTimer: null,

        clamp(row) {
            const n = Math.floor(Number(this.rows[row].qty) || 0);
            this.rows[row].qty = Math.min(this.max, Math.max(this.min, n));
        },

        step(row, delta) {
            this.rows[row].qty = (Math.floor(Number(this.rows[row].qty) || this.min)) + delta;
            this.clamp(row);
            this.rows[row].on = true;
        },

        unit(row) {
            const key = pricingKey(row);
            const unit = row === 'hardware' ? catalog.units?.access_hardware : catalog.units?.[key];

            return Number(unit || 0) * (1 - Number(metal.discounts?.[key] || 0));
        },

        listUnit(row) {
            return Number((row === 'hardware' ? catalog.units?.access_hardware : catalog.units?.[pricingKey(row)]) || 0);
        },

        discountPct(row) {
            return Math.round(Number(metal.discounts?.[pricingKey(row)] || 0) * 100);
        },

        get selected() {
            return Object.entries(this.rows).filter(([, r]) => r.on && Number(r.qty) > 0);
        },

        get installations() {
            return this.selected.reduce((acc, [key, r]) => {
                const k = key === 'hardware' ? 'access' : key;
                acc[k] = (acc[k] || 0) + Number(r.qty);

                return acc;
            }, {});
        },

        get capacity() {
            return Math.max(0, ...Object.values(this.installations));
        },

        get gets() {
            const n = this.capacity;
            if (n < 1) {
                return 'Marca al menos un producto.';
            }
            const names = this.selected.map(([key, r]) => `${CART_LABELS[key]} ${r.qty}`).join(', ');

            return `Obtienes ${n} instalaci${n === 1 ? 'ón' : 'ones'} (${names}), ${metal.posts} puestos por instalación, asigna hasta ${metal.per_post} empleados a cada puesto y hasta ${Number(metal.employees).toLocaleString('es-CO')} empleados.`;
        },

        get monthly() {
            return this.selected.reduce((acc, [key, r]) => acc + this.unit(key) * Number(r.qty), 0);
        },

        addToCart() {
            Object.keys(this.rows).forEach((row) => this.clamp(row));
            const picked = this.selected;
            if (picked.length === 0) {
                this.flash('Marca al menos un producto.');

                return;
            }
            const cart = readCart();
            const replaced = [];
            for (const [key, r] of picked) {
                if (cart.items[key]) {
                    replaced.push(CART_LABELS[key]);
                }
                cart.items[key] = Math.floor(Number(r.qty));
            }
            writeCart(cart);
            this.flash(replaced.length
                ? `Agregado. Reemplazamos ${replaced.join(', ')} que ya estaba en el carrito.`
                : 'Agregado al carrito.');
        },

        flash(message) {
            this.notice = message;
            clearTimeout(this.noticeTimer);
            this.noticeTimer = setTimeout(() => { this.notice = ''; }, 4000);
        },
    };
}

export function planCart(config) {
    const catalog = config.catalog || {};

    return {
        money,
        labels: CART_LABELS,
        items: {},
        cycle: 'monthly',

        init() {
            const cart = readCart();
            this.items = cart.items;
            this.cycle = cart.cycle;
        },

        save() {
            writeCart({ cycle: this.cycle, items: this.items });
        },

        get keys() {
            return KEYS.filter((k) => this.items[k] > 0);
        },

        get quote() {
            return quotePlan(catalog, {
                manual: this.items.access || 0,
                hardware: this.items.hardware || 0,
                supervision: this.items.supervision || 0,
                indexing: this.items.indexing || 0,
                observatory: this.items.observatory || 0,
            }, this.cycle);
        },

        metalOf(key) {
            const seats = key === 'access' || key === 'hardware'
                ? (this.items.access || 0) + (this.items.hardware || 0)
                : this.items[key] || 0;

            return metalFor(catalog, seats);
        },

        line(key) {
            return this.quote.lines.find((l) => l.key === pricingKey(key)) || null;
        },

        lineAmount(key) {
            const line = this.line(key);
            if (!line) {
                return 0;
            }
            const value = this.cycle === 'annual' ? line.annual : line.monthly;
            if (key !== 'access' && key !== 'hardware') {
                return value;
            }
            const unit = key === 'hardware' ? catalog.units?.access_hardware : catalog.units?.access;
            const share = line.list > 0 ? (Number(unit || 0) * (this.items[key] || 0)) / line.list : 0;

            return value * share;
        },

        setQty(key, value) {
            const n = Math.min(MAX_SEATS, Math.max(0, Math.floor(Number(value) || 0)));
            if (n === 0) {
                delete this.items[key];
            } else {
                this.items[key] = n;
            }
            this.items = { ...this.items };
            this.save();
        },

        step(key, delta) {
            this.setQty(key, (this.items[key] || 0) + delta);
        },

        remove(key) {
            this.setQty(key, 0);
        },

        setCycle(cycle) {
            this.cycle = cycle;
            this.save();
        },

        get unavailable() {
            return this.quote.lines.filter((l) => l.metal && !l.metal.available).map((l) => `${l.label} ${l.metal.label}`);
        },

        get capacity() {
            return Math.max(0, (this.items.access || 0) + (this.items.hardware || 0), this.items.supervision || 0, this.items.indexing || 0, this.items.observatory || 0);
        },

        get topMetal() {
            const metals = this.quote.lines.map((l) => l.metal).filter(Boolean);

            return metals.reduce((best, m) => (!best || m.max > best.max ? m : best), null);
        },

        get canCheckout() {
            return this.keys.length > 0 && this.unavailable.length === 0;
        },

        checkoutUrl(base) {
            const params = new URLSearchParams({ cycle: this.cycle });
            if (this.items.access) params.set('access', String(this.items.access));
            if (this.items.hardware) params.set('hardware', String(this.items.hardware));
            if (this.items.supervision) params.set('supervision', String(this.items.supervision));
            if (this.items.indexing) params.set('indexing', String(this.items.indexing));
            if (this.items.observatory) params.set('observatory', String(this.items.observatory));

            return `${base}?${params.toString()}`;
        },
    };
}
