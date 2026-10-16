import { planPicker } from './plan-picker';

/**
 * Cotización comercial Super Admin: planPicker + total comercial editable.
 */
export function commercialQuoteForm(config) {
    const picker = planPicker(config);
    const initialCommercial = config.commercialAmount;
    const form = {
        commercialAmount: initialCommercial === null || initialCommercial === undefined || initialCommercial === ''
            ? null
            : Number(initialCommercial),
        commercialTouched: initialCommercial !== null && initialCommercial !== undefined && initialCommercial !== '',
        syncCommercial() {
            if (! this.commercialTouched) {
                this.commercialAmount = this.total > 0 ? Math.round(this.total) : null;
            }
        },
        useCatalog() {
            this.commercialAmount = this.total > 0 ? Math.round(this.total) : null;
            this.commercialTouched = false;
        },
    };

    return Object.defineProperties(form, Object.getOwnPropertyDescriptors(picker));
}
