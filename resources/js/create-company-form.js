import { planPicker } from './plan-picker';

const fieldLabels = [
    ['party_type', 'tipo de suscriptor'],
    ['legal_name', 'razón social'],
    ['trade_name', 'nombre comercial'],
    ['tax_id', 'NIT'],
    ['email', 'email comercial'],
    ['phone', 'teléfono'],
    ['address', 'dirección'],
    ['city', 'ciudad'],
    ['department', 'departamento'],
    ['latitude', 'coordenadas (mapa)'],
    ['longitude', 'coordenadas (mapa)'],
    ['billing_cycle', 'ciclo'],
];

export function createCompanyForm(config) {
    const form = {
        toast: '',
        toastTimer: null,

        showToast(message) {
            this.toast = message;
            if (this.toastTimer) {
                clearTimeout(this.toastTimer);
            }
            this.toastTimer = setTimeout(() => {
                this.toast = '';
            }, 2800);
        },

        validate(event) {
            const el = event.target;
            for (const [name, label] of fieldLabels) {
                const field = el.elements.namedItem(name);
                const value = field ? String(field.value ?? '').trim() : '';
                if (value === '') {
                    event.preventDefault();
                    this.showToast(`Falta ${label} por llenar`);
                    if (field && typeof field.focus === 'function') {
                        field.focus();
                    }

                    return;
                }
            }
            if (this.isEmpty) {
                event.preventDefault();
                this.showToast('Indica instalaciones de Accesos o clientes de Supervisión');
            }
        },
    };

    return Object.defineProperties(form, Object.getOwnPropertyDescriptors(planPicker(config)));
}
