const DURATION = 2800;

const KINDS = {
    error: 'ui-toast-error',
    warning: 'ui-toast-warning',
    caution: 'ui-toast-caution',
    success: 'ui-toast-success',
};

function host() {
    return document.querySelector('[data-zentra-feedback]');
}

function msgEl() {
    return host()?.querySelector('[data-zentra-feedback-msg]') ?? null;
}

function fieldOf(form, name) {
    if (!name) return null;
    const escaped = typeof CSS !== 'undefined' && CSS.escape ? CSS.escape(name) : name;
    return form.querySelector(`[name="${escaped}"]`)
        || form.querySelector(`[name="${escaped}[]"]`)
        || document.getElementById(name)
        || document.querySelector(`[name="${escaped}"]`);
}

function focusField(field) {
    if (!field || typeof field.focus !== 'function') return;
    field.scrollIntoView({ behavior: 'smooth', block: 'center' });
    try {
        field.focus({ preventScroll: true });
    } catch {
        field.focus();
    }
    field.classList.add('ring-2', 'ring-red-400', 'ring-offset-2', 'ring-offset-slate-950');
    setTimeout(() => {
        field.classList.remove('ring-2', 'ring-red-400', 'ring-offset-2', 'ring-offset-slate-950');
    }, DURATION + 400);
}

function spanishValidity(field) {
    if (!field.validity || field.validity.valid) {
        return 'Revise este dato.';
    }
    if (field.validity.valueMissing) {
        return 'Completa este campo.';
    }
    if (field.validity.typeMismatch && field.type === 'email') {
        return 'Escribe un correo válido.';
    }
    if (field.validity.typeMismatch) {
        return 'El formato no es válido.';
    }
    if (field.validity.tooShort) {
        return `Debe tener al menos ${field.minLength} caracteres.`;
    }
    if (field.validity.tooLong) {
        return `No puede superar ${field.maxLength} caracteres.`;
    }
    if (field.validity.rangeUnderflow) {
        return `Debe ser al menos ${field.min}.`;
    }
    if (field.validity.rangeOverflow) {
        return `No puede ser mayor que ${field.max}.`;
    }
    if (field.validity.stepMismatch) {
        return 'El valor no es válido.';
    }
    if (field.validity.patternMismatch) {
        return 'El formato no es válido.';
    }
    if (field.validity.badInput) {
        return 'El valor no es válido.';
    }

    return field.validationMessage || 'Revise este dato.';
}

let hideTimer = null;

export function showFeedback(text, kind = 'error', field = null) {
    const box = msgEl();
    if (!box || !text) return;

    box.textContent = String(text);
    box.className = `max-w-md rounded-xl border px-5 py-3 text-center text-sm font-semibold leading-snug shadow-2xl ${KINDS[kind] ?? KINDS.error}`;
    box.hidden = false;

    if (hideTimer) clearTimeout(hideTimer);
    hideTimer = setTimeout(() => {
        box.hidden = true;
    }, DURATION);

    if (field) focusField(field);
}

function bindForms() {
    document.querySelectorAll('form').forEach((form) => {
        if (form.dataset.skipFeedback === '1' || form.getAttribute('method') === 'dialog') {
            return;
        }
        form.setAttribute('novalidate', 'novalidate');
        form.addEventListener('submit', (event) => {
            if (form.checkValidity()) return;
            event.preventDefault();
            const first = form.querySelector(':invalid');
            showFeedback(spanishValidity(first || form), 'error', first);
        });
    });
}

export function initFormFeedback() {
    bindForms();
    const root = host();
    if (!root) return;

    const text = (root.dataset.initialText || '').trim();
    const kind = root.dataset.initialKind || 'error';
    const fieldName = root.dataset.initialField || '';
    if (!text) return;

    const field = fieldOf(document, fieldName);
    showFeedback(text, kind, field);
}
