import { MONTHS, SHORT, iso, lastDay, parse } from './supervision-period';

/** Vigencia de una autorización: día o rango, horario de ingreso y entrada múltiple, en un solo modal. */
export function authorizationValidityPicker(config) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const todayIso = iso(today);
    const start = parse(config.from) || today;

    return {
        open: false,
        from: config.from || todayIso,
        to: config.to || config.from || todayIso,
        timeFrom: config.timeFrom || '',
        timeUntil: config.timeUntil || '',
        multi: !!config.multi,
        multiTouched: !!config.multiTouched,
        draft: {},
        picking: false,
        error: '',
        viewYear: start.getFullYear(),
        viewMonth: start.getMonth(),
        weekdays: ['L', 'M', 'M', 'J', 'V', 'S', 'D'],

        get dayLabel() {
            const a = parse(this.from);
            const b = parse(this.to);
            if (!a || !b) return 'Elegir fecha';
            const fmt = (d, year) => `${d.getDate()} ${SHORT[d.getMonth()]}${year ? ` ${d.getFullYear()}` : ''}`;
            if (this.from === this.to) return fmt(a, true);

            return `${fmt(a, a.getFullYear() !== b.getFullYear())} – ${fmt(b, true)}`;
        },

        get hoursLabel() {
            return this.timeFrom && this.timeUntil ? `${this.timeFrom} – ${this.timeUntil}` : 'Todo el día';
        },

        get months() {
            const list = [{ year: this.viewYear, month: this.viewMonth }];
            if (this.draft.range) {
                const next = new Date(this.viewYear, this.viewMonth + 1, 1);
                list.push({ year: next.getFullYear(), month: next.getMonth() });
            }

            return list;
        },

        typeChanged(defaultMulti) {
            if (!this.multiTouched) this.multi = !!defaultMulti;
        },

        show() {
            this.draft = {
                from: this.from,
                to: this.to,
                range: this.from !== this.to,
                allDay: !(this.timeFrom && this.timeUntil),
                timeFrom: this.timeFrom || '08:00',
                timeUntil: this.timeUntil || '18:00',
                multi: this.multi,
            };
            this.picking = false;
            this.error = '';
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
            if (d < new Date(today.getFullYear(), today.getMonth(), 1)) return;
            this.viewYear = d.getFullYear();
            this.viewMonth = d.getMonth();
        },

        cells(m) {
            const first = new Date(m.year, m.month, 1);
            const lead = (first.getDay() + 6) % 7;
            const out = [];
            for (let i = 0; i < lead; i++) out.push({ key: `b${i}`, day: null, iso: '' });
            for (let day = 1; day <= lastDay(m.year, m.month); day++) {
                out.push({ key: `d${day}`, day, iso: iso(new Date(m.year, m.month, day)) });
            }

            return out;
        },

        toggleRange() {
            this.draft.range = !this.draft.range;
            this.picking = false;
            if (!this.draft.range) this.draft.to = this.draft.from;
        },

        pick(value) {
            if (!value || value < todayIso) return;
            if (!this.draft.range) {
                this.draft.from = value;
                this.draft.to = value;

                return;
            }
            if (!this.picking) {
                this.draft.from = value;
                this.draft.to = '';
                this.picking = true;

                return;
            }
            if (value < this.draft.from) {
                this.draft.to = this.draft.from;
                this.draft.from = value;
            } else {
                this.draft.to = value;
            }
            this.picking = false;
        },

        dayClass(value) {
            if (!value) return '';
            if (value < todayIso) return 'text-slate-600 cursor-not-allowed';
            const to = this.draft.to || this.draft.from;
            if (value === this.draft.from || value === to) return 'bg-indigo-600 text-white font-semibold';
            if (this.draft.range && this.draft.from && to && value > this.draft.from && value < to) return 'bg-indigo-500/20 text-indigo-300';
            if (value === todayIso) return 'ring-1 ring-indigo-400 text-white';

            return 'text-slate-200 hover:bg-slate-800';
        },

        apply() {
            if (!this.draft.from) {
                this.error = 'Elige el día.';

                return;
            }
            if (this.draft.range && this.picking) {
                this.error = 'Elige el día final del rango.';

                return;
            }
            if (!this.draft.allDay && (!this.draft.timeFrom || !this.draft.timeUntil || this.draft.timeUntil <= this.draft.timeFrom)) {
                this.error = 'La hora final debe ser posterior a la inicial.';

                return;
            }
            this.from = this.draft.from;
            this.to = this.draft.to || this.draft.from;
            this.timeFrom = this.draft.allDay ? '' : this.draft.timeFrom;
            this.timeUntil = this.draft.allDay ? '' : this.draft.timeUntil;
            if (this.draft.multi !== this.multi) this.multiTouched = true;
            this.multi = this.draft.multi;
            this.open = false;
        },
    };
}
