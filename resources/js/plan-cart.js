/**
 * Tarjetas de plan (/planes) y carrito (/planes/carrito). El carrito vive en localStorage:
 * una línea por producto; agregar el mismo producto reemplaza la cantidad.
 */
import { countsEmployees, metalFor, metalRange, money, quotePlan, unitLabel } from './plan-picker';

const STORAGE_KEY = 'zentra.plan-cart.v1';
const MAX_SEATS = 10000;
const MAX_EMPLOYEES = 100000;
const KEYS = ['access', 'hardware', 'supervision', 'indexing', 'observatory'];

/** Las flechas de Indexación suben de 50 en 50 empleados; las demás de 1 en 1. */
const STEP = (key) => (countsEmployees(key) ? 50 : 1);
const maxFor = (key) => (countsEmployees(key) ? MAX_EMPLOYEES : MAX_SEATS);

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
                items[key] = Math.min(maxFor(key), n);
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

const seatsOf = (items) => ({
    manual: items.access || 0,
    hardware: items.hardware || 0,
    supervision: items.supervision || 0,
    indexing: items.indexing || 0,
    observatory: items.observatory || 0,
});

/**
 * Tarjeta de un plan (metal). Cada fila: casilla, precio por unidad y cantidad dentro del rango del plan
 * (instalaciones; empleados en Indexación). El descuento por combinar y los módulos nuevos se calculan
 * con lo marcado en la tarjeta más lo que ya está en el carrito.
 */
export function planCard(config) {
    const catalog = config.catalog || {};
    const metal = (catalog.metals || []).find((m) => m.key === config.metal) || {};
    const rows = config.rows || [];
    const modules = config.modules || {};
    const comboPct = Number(catalog.combo_discount || 0);
    const ranges = {};
    const state = {};
    for (const row of rows) {
        ranges[row] = metalRange(catalog, metal, pricingKey(row));
        state[row] = { on: row === 'access', qty: ranges[row][0] };
    }

    return {
        money,
        metal,
        rows: state,
        cart: {},
        comboPctLabel: Math.round(comboPct * 100),
        notice: '',
        noticeTimer: null,

        init() {
            this.cart = readCart().items;
            window.addEventListener('plan-cart-updated', (e) => { this.cart = { ...e.detail.items }; });
            window.addEventListener('storage', () => { this.cart = readCart().items; });
        },

        /** Carrito con lo marcado en la tarjeta encima; `extra` simula marcar una fila más. */
        mergedItems(extra = null) {
            const items = { ...this.cart };
            for (const [key, r] of this.selected) {
                items[key] = Math.floor(Number(r.qty));
            }
            if (extra) {
                items[extra] = Math.floor(Number(this.rows[extra].qty)) || this.min(extra);
            }

            return items;
        },

        get mergedQuote() {
            return quotePlan(catalog, seatsOf(this.mergedItems()), 'monthly');
        },

        /** 'discount' = esta fila lleva el −%; 'top' = va completa y los demás lo llevan; null = sin combinación. */
        comboState(row) {
            if (comboPct <= 0) {
                return null;
            }
            const self = pricingKey(row);
            const items = this.mergedItems(this.rows[row].on ? null : row);
            if (!Object.keys(items).some((k) => pricingKey(k) !== self && items[k] > 0)) {
                return null;
            }
            const quote = this.rows[row].on ? this.mergedQuote : quotePlan(catalog, seatsOf(items), 'monthly');
            const line = quote.lines.find((l) => l.key === self);

            return line?.combo ? 'discount' : 'top';
        },

        comboUnit(row) {
            return this.unit(row) * (1 - comboPct);
        },

        rowTotal(row) {
            const base = this.unit(row) * Number(this.rows[row].qty);

            return this.comboState(row) === 'discount' ? base * (1 - comboPct) : base;
        },

        get combined() {
            return this.selected.reduce((acc, [key]) => acc + this.rowTotal(key), 0);
        },

        get comboSavings() {
            return this.selected.length > 0 ? this.mergedQuote.comboSavings : 0;
        },

        get cartAdds() {
            const picked = new Set(this.selected.map(([key]) => pricingKey(key)));

            return Object.keys(this.cart).some((k) => !picked.has(pricingKey(k)));
        },

        modulesOf(key) {
            return modules[pricingKey(key)] || [];
        },

        /** Módulos que ya tendría por el carrito o por las filas marcadas antes que esta. */
        ownedModules(row) {
            const self = pricingKey(row);
            const products = new Set(Object.keys(this.cart).map(pricingKey).filter((k) => k !== self));
            const order = Object.keys(this.rows);
            const idx = order.indexOf(row);
            const on = this.rows[row].on;
            order.forEach((k, i) => {
                if (this.rows[k].on && pricingKey(k) !== self && (!on || i < idx)) {
                    products.add(pricingKey(k));
                }
            });

            return new Set([...products].flatMap((k) => this.modulesOf(k)));
        },

        moduleInfo(row) {
            const all = this.modulesOf(row);
            const owned = this.ownedModules(row);
            if (owned.size === 0) {
                return { mode: 'all', text: all.join(', ') };
            }
            const fresh = all.filter((m) => !owned.has(m));

            return fresh.length
                ? { mode: 'new', text: `Agrega: ${fresh.join(', ')}` }
                : { mode: 'none', text: 'Sin módulos nuevos: ya los tienes.' };
        },

        min(row) {
            return ranges[row][0];
        },

        max(row) {
            return ranges[row][1];
        },

        unitName(row, n = 1) {
            return unitLabel(pricingKey(row), n);
        },

        clamp(row) {
            const n = Math.floor(Number(this.rows[row].qty) || 0);
            this.rows[row].qty = Math.min(this.max(row), Math.max(this.min(row), n));
        },

        step(row, direction) {
            this.rows[row].qty = (Math.floor(Number(this.rows[row].qty) || this.min(row))) + direction * STEP(row);
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
                const k = pricingKey(key);
                if (!countsEmployees(k)) {
                    acc[k] = (acc[k] || 0) + Number(r.qty);
                }

                return acc;
            }, {});
        },

        get capacity() {
            return Math.max(0, ...Object.values(this.installations));
        },

        get gets() {
            if (this.selected.length === 0) {
                return 'Marca al menos un producto.';
            }
            const n = this.capacity;
            const fmt = (v) => Number(v).toLocaleString('es-CO');
            const names = this.selected
                .filter(([key]) => !countsEmployees(key))
                .map(([key, r]) => `${CART_LABELS[key]} ${r.qty}`)
                .join(', ');
            const parts = [];
            if (n > 0) {
                parts.push(`${n} instalaci${n === 1 ? 'ón' : 'ones'} (${names}), ${metal.posts} puestos por instalación, asigna hasta ${metal.per_post} empleados a cada puesto`);
            }
            const indexing = this.rows.indexing;
            if (indexing?.on && Number(indexing.qty) > 0) {
                parts.push(`Indexación para ${fmt(indexing.qty)} empleados`);
            }

            const employees = Math.max(Number(metal.employees) || 0, indexing?.on ? Number(indexing.qty) || 0 : 0);

            return `Obtienes ${parts.join(', ')} y hasta ${fmt(employees)} empleados.`;
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

            return metalFor(catalog, seats, pricingKey(key));
        },

        unitName(key, n = 1) {
            return unitLabel(pricingKey(key), n);
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
            const n = Math.min(maxFor(key), Math.max(0, Math.floor(Number(value) || 0)));
            if (n === 0) {
                delete this.items[key];
            } else {
                this.items[key] = n;
            }
            this.items = { ...this.items };
            this.save();
        },

        step(key, direction) {
            this.setQty(key, (this.items[key] || 0) + direction * STEP(key));
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
            return Math.max(0, (this.items.access || 0) + (this.items.hardware || 0), this.items.supervision || 0, this.items.observatory || 0);
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
