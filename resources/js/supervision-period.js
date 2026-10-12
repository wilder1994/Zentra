const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
const SHORT = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

const pad = (n) => String(n).padStart(2, '0');
const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const parse = (value) => {
    const [y, m, d] = String(value || '').split('-').map(Number);
    return y && m && d ? new Date(y, m - 1, d) : null;
};
const lastDay = (y, m) => new Date(y, m + 1, 0).getDate();

/** Un solo control de periodo: un día o, con «Rango», desde–hasta en dos meses. Escribe from/to en el formulario. */
export function supervisionPeriodPicker(config) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const start = parse(config.from) || today;

    return {
        open: false,
        from: config.from || iso(today),
        to: config.to || config.from || iso(today),
        range: (config.from || '') !== (config.to || config.from || ''),
        draftFrom: '',
        draftTo: '',
        picking: false,
        viewYear: start.getFullYear(),
        viewMonth: start.getMonth(),
        weekdays: ['L', 'M', 'M', 'J', 'V', 'S', 'D'],
        form: null,

        init() {
            this.form = this.$el.closest('form');
        },

        get label() {
            const a = parse(this.from);
            const b = parse(this.to);
            if (!a || !b) {
                return 'Periodo';
            }
            const sameYear = a.getFullYear() === b.getFullYear();
            if (iso(a) === iso(b)) {
                return `${a.getDate()} ${SHORT[a.getMonth()]} ${a.getFullYear()}`;
            }
            if (sameYear && a.getMonth() === 0 && a.getDate() === 1 && b.getMonth() === 11 && b.getDate() === 31) {
                return `Año ${a.getFullYear()}`;
            }
            if (sameYear && a.getMonth() === b.getMonth() && a.getDate() === 1 && b.getDate() === lastDay(b.getFullYear(), b.getMonth())) {
                return `${MONTHS[a.getMonth()].replace(/^./, (c) => c.toUpperCase())} ${a.getFullYear()}`;
            }
            const left = `${a.getDate()} ${SHORT[a.getMonth()]}${sameYear ? '' : ` ${a.getFullYear()}`}`;

            return `${left} – ${b.getDate()} ${SHORT[b.getMonth()]} ${b.getFullYear()}`;
        },

        get months() {
            const list = [{ year: this.viewYear, month: this.viewMonth }];
            if (this.range) {
                const next = new Date(this.viewYear, this.viewMonth + 1, 1);
                list.push({ year: next.getFullYear(), month: next.getMonth() });
            }

            return list;
        },

        show() {
            this.draftFrom = this.from;
            this.draftTo = this.to;
            this.picking = false;
            const d = parse(this.from) || today;
            this.viewYear = d.getFullYear();
            this.viewMonth = d.getMonth();
            this.open = true;
        },

        close() {
            this.open = false;
        },

        title(m) {
            return `${MONTHS[m.month].replace(/^./, (c) => c.toUpperCase())} ${m.year}`;
        },

        shift(delta) {
            const d = new Date(this.viewYear, this.viewMonth + delta, 1);
            this.viewYear = d.getFullYear();
            this.viewMonth = d.getMonth();
        },

        cells(m) {
            const first = new Date(m.year, m.month, 1);
            const lead = (first.getDay() + 6) % 7;
            const out = [];
            for (let i = 0; i < lead; i++) {
                out.push({ key: `b${i}`, day: null, iso: '' });
            }
            for (let day = 1; day <= lastDay(m.year, m.month); day++) {
                out.push({ key: `d${day}`, day, iso: iso(new Date(m.year, m.month, day)) });
            }

            return out;
        },

        toggleRange() {
            this.range = !this.range;
            this.picking = false;
            if (!this.range) {
                this.draftTo = this.draftFrom;
            }
        },

        pick(value) {
            if (!value) {
                return;
            }
            if (!this.range) {
                this.draftFrom = value;
                this.draftTo = value;

                return;
            }
            if (!this.picking) {
                this.draftFrom = value;
                this.draftTo = '';
                this.picking = true;

                return;
            }
            if (value < this.draftFrom) {
                this.draftTo = this.draftFrom;
                this.draftFrom = value;
            } else {
                this.draftTo = value;
            }
            this.picking = false;
        },

        dayClass(value) {
            if (!value) {
                return '';
            }
            const to = this.draftTo || this.draftFrom;
            if (value === this.draftFrom || value === to) {
                return 'bg-amber-400 text-slate-950 font-semibold';
            }
            if (this.range && this.draftFrom && to && value > this.draftFrom && value < to) {
                return 'bg-amber-400/20 text-amber-100';
            }
            if (value === iso(today)) {
                return 'ring-1 ring-amber-400/60 text-white';
            }

            return 'text-slate-200 hover:bg-slate-800';
        },

        preset(kind) {
            const y = today.getFullYear();
            const m = today.getMonth();
            const map = {
                today: [today, today],
                yesterday: [new Date(y, m, today.getDate() - 1), new Date(y, m, today.getDate() - 1)],
                month: [new Date(y, m, 1), new Date(y, m, lastDay(y, m))],
                lastMonth: [new Date(y, m - 1, 1), new Date(y, m - 1, lastDay(y, m - 1))],
                year: [new Date(y, 0, 1), new Date(y, 11, 31)],
            };
            const [a, b] = map[kind];
            this.draftFrom = iso(a);
            this.draftTo = iso(b);
            this.apply();
        },

        apply() {
            if (!this.draftFrom) {
                return;
            }
            this.from = this.draftFrom;
            this.to = this.draftTo || this.draftFrom;
            this.range = this.from !== this.to;
            this.open = false;
            this.$nextTick(() => this.form?.submit());
        },
    };
}
