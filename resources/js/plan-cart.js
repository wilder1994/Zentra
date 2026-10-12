/**
 * Tarjetas de plan (/planes), barra de resumen y carrito (/planes/carrito). El carrito vive en localStorage
 * y es la única selección: cada producto está una sola vez y cae en la tarjeta del plan de su cantidad.
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

function normalizeItems(raw) {
    const items = {};
    for (const key of KEYS) {
        const n = Math.floor(Number(raw?.[key]) || 0);
        if (n > 0) {
            items[key] = Math.min(maxFor(key), n);
        }
    }

    return items;
}

export function readCart() {
    try {
        const raw = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '{}');

        return { cycle: raw.cycle === 'annual' ? 'annual' : 'monthly', items: normalizeItems(raw.items) };
    } catch {
        return { cycle: 'monthly', items: {} };
    }
}

export function writeCart(cart) {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(cart));
    window.dispatchEvent(new CustomEvent('plan-cart-updated', { detail: cart }));
}

/** Selección de las tarjetas: el carrito público o, con `scope`, un formulario en memoria. */
const cartStore = { read: readCart, write: writeCart, event: 'plan-cart-updated', allowUnavailable: false };
const formStores = {};

function createFormStore(scope, items, cycle) {
    let state = { cycle: cycle === 'annual' ? 'annual' : 'monthly', items: normalizeItems(items) };
    const event = `plan-form-updated:${scope}`;
    formStores[scope] = {
        event,
        allowUnavailable: true,
        read: () => ({ cycle: state.cycle, items: { ...state.items } }),
        write: (next) => {
            state = { cycle: next.cycle, items: { ...next.items } };
            window.dispatchEvent(new CustomEvent(event, { detail: formStores[scope].read() }));
        },
    };

    return formStores[scope];
}

const storeFor = (scope) => (scope ? formStores[scope] : cartStore);

const cartCount = (cart) => Object.keys(cart.items).length;

/** Accesos y hardware comparten el plan del total de Accesos. */
const pricingKey = (key) => (key === 'hardware' ? 'access' : key);

/** Cantidad que define el plan de un producto. */
const planCount = (items, key) => (pricingKey(key) === 'access'
    ? (items.access || 0) + (items.hardware || 0)
    : items[key] || 0);

const seatsOf = (items) => ({
    manual: items.access || 0,
    hardware: items.hardware || 0,
    supervision: items.supervision || 0,
    indexing: items.indexing || 0,
    observatory: items.observatory || 0,
});

/** «Plan Plata» si todos los productos comparten metal; «Plan personalizado» si mezclan. */
export function planIdentity(quote) {
    const metals = [...new Map(quote.lines.filter((l) => l.metal).map((l) => [l.metal.key, l.metal])).values()];
    if (metals.length === 0) {
        return { key: null, name: '' };
    }
    if (metals.length > 1) {
        return { key: 'personalizado', name: 'Plan personalizado' };
    }

    return { key: metals[0].key, name: `Plan ${metals[0].label}` };
}

/**
 * Cambia la cantidad de un producto en la selección. El carrito público no deja entrar a un plan no disponible.
 *
 * @returns {{ok: boolean, from: ?object, to: ?object}}
 */
function commitQty(catalog, key, value, store = cartStore) {
    const n = Math.min(maxFor(key), Math.max(0, Math.floor(Number(value) || 0)));
    const cart = store.read();
    const from = cart.items[key] ? metalFor(catalog, planCount(cart.items, key), pricingKey(key)) : null;
    const items = { ...cart.items };
    if (n === 0) {
        delete items[key];
    } else {
        items[key] = n;
    }
    const to = n > 0 ? metalFor(catalog, planCount(items, key), pricingKey(key)) : null;
    if (to && !to.available && !store.allowUnavailable) {
        return { ok: false, from, to };
    }
    store.write({ ...cart, items });

    return { ok: true, from, to };
}

export function planCartBadge() {
    return {
        count: 0,
        bump: false,
        init() {
            this.count = cartCount(readCart());
            window.addEventListener('plan-cart-updated', (e) => this.update(cartCount(e.detail)));
            window.addEventListener('storage', () => this.update(cartCount(readCart())));
        },
        update(count) {
            if (count !== this.count) {
                this.bump = true;
                setTimeout(() => { this.bump = false; }, 450);
            }
            this.count = count;
        },
    };
}

/**
 * Tarjeta de un plan (metal). Muestra los productos del carrito cuya cantidad cae en este plan; al cruzar
 * el rango con las flechas o escribiendo, el producto pasa solo a la tarjeta del plan siguiente.
 */
export function planCard(config) {
    const catalog = config.catalog || {};
    const metal = (catalog.metals || []).find((m) => m.key === config.metal) || {};
    const rows = config.rows || [];
    const modules = config.modules || {};
    const comboPct = Number(catalog.combo_discount || 0);
    const ranges = {};
    for (const row of rows) {
        ranges[row] = metalRange(catalog, metal, pricingKey(row));
    }
    const metalOf = (items, key) => metalFor(catalog, planCount(items, key), pricingKey(key));
    const scope = config.scope || null;
    const store = storeFor(scope);

    return {
        money,
        metal,
        rows,
        cart: {},
        comboPctLabel: Math.round(comboPct * 100),
        notice: '',
        noticeTimer: null,
        glow: false,

        init() {
            this.cart = store.read().items;
            window.addEventListener(store.event, (e) => { this.cart = { ...e.detail.items }; });
            if (!scope) {
                window.addEventListener('storage', () => { this.cart = readCart().items; });
            }
            window.addEventListener('plan-moved', (e) => {
                if (e.detail.metal !== metal.key || e.detail.scope !== scope) {
                    return;
                }
                this.flash(e.detail.message);
                this.glow = true;
                setTimeout(() => { this.glow = false; }, 1600);
                this.$root.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
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

        /** El producto está en el carrito y su cantidad cae en este plan. */
        isOn(row) {
            return (this.cart[row] || 0) > 0 && metalOf(this.cart, row)?.key === metal.key;
        },

        /** Plan en el que está el producto si vive en otra tarjeta. */
        elsewhere(row) {
            if (!(this.cart[row] > 0) || this.isOn(row)) {
                return '';
            }

            return metalOf(this.cart, row)?.label || '';
        },

        qty(row) {
            return this.isOn(row) ? this.cart[row] : this.startQty(row);
        },

        /** Cantidad con la que entra a esta tarjeta (Accesos descuenta el hardware ya elegido). */
        startQty(row) {
            const other = planCount(this.cart, row) - (this.cart[row] || 0);

            return Math.max(1, this.min(row) - other);
        },

        toggle(row, input) {
            this.commit(row, input.checked ? this.startQty(row) : 0);
            input.checked = this.isOn(row);
        },

        step(row, direction) {
            this.commit(row, this.qty(row) + direction * STEP(row));
        },

        commit(row, value, input = null) {
            const result = commitQty(catalog, row, value, store);
            if (!result.ok) {
                this.flash(`El plan ${result.to.label} no está a la venta por ahora.`);
                if (input) {
                    input.value = this.qty(row);
                }

                return;
            }
            const to = result.to;
            if (to && to.key !== metal.key) {
                const pct = Math.round(Number(to.discounts?.[pricingKey(row)] || 0) * 100);
                window.dispatchEvent(new CustomEvent('plan-moved', {
                    detail: { scope, metal: to.key, message: `${CART_LABELS[row]} pasó a ${to.label} (−${pct}% por ${this.unitName(row)}).` },
                }));
            }
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

        /** Carrito actual; `extra` simula traer esa fila a esta tarjeta. */
        itemsWith(extra = null) {
            const items = { ...this.cart };
            if (extra) {
                items[extra] = this.startQty(extra);
            }

            return items;
        },

        /** 'discount' = esta fila lleva el −%; 'top' = va completa y los demás lo llevan; null = sin combinación. */
        comboState(row) {
            if (comboPct <= 0) {
                return null;
            }
            const self = pricingKey(row);
            const items = this.itemsWith(this.isOn(row) ? null : row);
            if (!Object.keys(items).some((k) => pricingKey(k) !== self && items[k] > 0)) {
                return null;
            }
            const line = quotePlan(catalog, seatsOf(items), 'monthly').lines.find((l) => l.key === self);

            return line?.combo ? 'discount' : 'top';
        },

        comboUnit(row) {
            return this.unit(row) * (1 - comboPct);
        },

        rowTotal(row) {
            const base = this.unit(row) * this.qty(row);

            return this.comboState(row) === 'discount' ? base * (1 - comboPct) : base;
        },

        get selected() {
            return this.rows.filter((row) => this.isOn(row));
        },

        get monthly() {
            return this.selected.reduce((acc, row) => acc + this.unit(row) * this.qty(row), 0);
        },

        get combined() {
            return this.selected.reduce((acc, row) => acc + this.rowTotal(row), 0);
        },

        modulesOf(key) {
            return modules[pricingKey(key)] || [];
        },

        /** Módulos que ya da el resto del carrito (si la fila está marcada, solo los productos anteriores a ella). */
        ownedModules(row) {
            const self = pricingKey(row);
            const idx = KEYS.indexOf(row);
            const on = this.isOn(row);
            const products = new Set(
                Object.keys(this.cart)
                    .filter((k) => pricingKey(k) !== self && (!on || KEYS.indexOf(k) < idx))
                    .map(pricingKey),
            );

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

        get gets() {
            const picked = this.selected;
            if (picked.length === 0) {
                return 'Marca un producto para sumarlo a tu plan.';
            }
            const fmt = (v) => Number(v).toLocaleString('es-CO');
            const installs = picked.filter((row) => !countsEmployees(row));
            const n = Math.max(0, ...installs.map((row) => planCount(this.cart, row)));
            const parts = [];
            if (n > 0) {
                const names = installs.map((row) => `${CART_LABELS[row]} ${this.qty(row)}`).join(', ');
                parts.push(`${n} instalaci${n === 1 ? 'ón' : 'ones'} (${names}), ${metal.posts} puestos por instalación, asigna hasta ${metal.per_post} empleados a cada puesto`);
            }
            const indexing = picked.includes('indexing') ? this.qty('indexing') : 0;
            if (indexing > 0) {
                parts.push(`Indexación para ${fmt(indexing)} empleados`);
            }
            const employees = Math.max(Number(metal.employees) || 0, indexing);

            return `Obtienes ${parts.join(', ')} y hasta ${fmt(employees)} empleados.`;
        },

        flash(message) {
            this.notice = message;
            clearTimeout(this.noticeTimer);
            this.noticeTimer = setTimeout(() => { this.notice = ''; }, 4500);
        },
    };
}

const HELP_SEEN_KEY = 'zentra.plan-help.seen';

/** Guía de /planes: se abre sola la primera visita y luego con el botón. */
export function planHelp() {
    return {
        open: false,
        init() {
            let seen = false;
            try {
                seen = localStorage.getItem(HELP_SEEN_KEY) === '1';
            } catch {
                seen = true;
            }
            if (!seen) {
                this.show();
            }
        },
        show() {
            this.open = true;
            document.documentElement.classList.add('overflow-hidden');
        },
        close() {
            this.open = false;
            document.documentElement.classList.remove('overflow-hidden');
            try {
                localStorage.setItem(HELP_SEEN_KEY, '1');
            } catch {
                // Sin almacenamiento la guía vuelve a abrirse en la próxima visita.
            }
        },
    };
}

/**
 * Formulario con tarjetas de metal (cambio de plan del superadmin). La selección vive en memoria bajo `scope`;
 * las tarjetas del mismo scope la comparten y el formulario envía las cantidades en campos ocultos.
 */
export function planForm(config) {
    const catalog = config.catalog || {};
    const store = createFormStore(config.scope, config.items, config.cycle);
    const initial = store.read();

    return {
        money,
        labels: CART_LABELS,
        items: initial.items,
        cycle: initial.cycle,

        init() {
            window.addEventListener(store.event, (e) => {
                this.items = { ...e.detail.items };
                this.cycle = e.detail.cycle;
            });
        },

        setCycle(cycle) {
            store.write({ cycle, items: this.items });
        },

        reset() {
            store.write(initial);
        },

        get keys() {
            return KEYS.filter((k) => this.items[k] > 0);
        },

        get quote() {
            return quotePlan(catalog, seatsOf(this.items), this.cycle);
        },

        get identity() {
            return planIdentity(this.quote);
        },

        get changed() {
            return this.cycle !== initial.cycle || KEYS.some((k) => (this.items[k] || 0) !== (initial.items[k] || 0));
        },

        metalOf(key) {
            return metalFor(catalog, planCount(this.items, key), pricingKey(key));
        },

        unitName(key, n = 1) {
            return unitLabel(pricingKey(key), n);
        },
    };
}

/** Barra fija de /planes: nombre del plan, total combinado y acceso al carrito. */
export function planSummary(config) {
    const catalog = config.catalog || {};

    return {
        money,
        items: {},

        init() {
            this.items = readCart().items;
            window.addEventListener('plan-cart-updated', (e) => { this.items = { ...e.detail.items }; });
            window.addEventListener('storage', () => { this.items = readCart().items; });
        },

        get count() {
            return Object.keys(this.items).length;
        },

        get quote() {
            return quotePlan(catalog, seatsOf(this.items), 'monthly');
        },

        get identity() {
            return planIdentity(this.quote);
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
        notice: '',
        noticeTimer: null,

        init() {
            const cart = readCart();
            this.items = cart.items;
            this.cycle = cart.cycle;
            window.addEventListener('plan-cart-updated', (e) => { this.items = { ...e.detail.items }; });
        },

        save() {
            writeCart({ cycle: this.cycle, items: this.items });
        },

        get keys() {
            return KEYS.filter((k) => this.items[k] > 0);
        },

        get quote() {
            return quotePlan(catalog, seatsOf(this.items), this.cycle);
        },

        get identity() {
            return planIdentity(this.quote);
        },

        metalOf(key) {
            return metalFor(catalog, planCount(this.items, key), pricingKey(key));
        },

        unitName(key, n = 1) {
            return unitLabel(pricingKey(key), n);
        },

        line(key) {
            return this.quote.lines.find((l) => l.key === pricingKey(key)) || null;
        },

        /** Precio por unidad con el descuento del plan y, si aplica, el de combinar. */
        netUnit(key, withCombo = false) {
            const line = this.line(key);
            if (!line) {
                return 0;
            }
            const unit = key === 'hardware' ? catalog.units?.access_hardware : catalog.units?.[pricingKey(key)];
            const net = Number(unit || 0) * (1 - (line.discount || 0));

            return withCombo && line.combo ? net * (1 - this.quote.comboPct) : net;
        },

        listUnit(key) {
            return Number((key === 'hardware' ? catalog.units?.access_hardware : catalog.units?.[pricingKey(key)]) || 0);
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
            const share = line.list > 0 ? (this.listUnit(key) * (this.items[key] || 0)) / line.list : 0;

            return value * share;
        },

        setQty(key, value, input = null) {
            const before = this.metalOf(key);
            const result = commitQty(catalog, key, value);
            if (!result.ok) {
                this.flash(`El plan ${result.to.label} no está a la venta por ahora.`);
                if (input) {
                    input.value = this.items[key] || 0;
                }

                return;
            }
            const cart = readCart();
            this.items = cart.items;
            if (result.to && before && result.to.key !== before.key) {
                this.flash(`${CART_LABELS[key]} pasó a ${result.to.label}.`);
            }
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

        flash(message) {
            this.notice = message;
            clearTimeout(this.noticeTimer);
            this.noticeTimer = setTimeout(() => { this.notice = ''; }, 4000);
        },
    };
}
