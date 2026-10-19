/**
 * Fichas de supervisión en modal (sin pestañas nuevas): Descargar PDF, Imprimir y Cerrar.
 * Intercepta cualquier enlace a /supervision/fichas/… del panel empresa.
 */
const SHEET_PATH = /\/company\/supervision\/fichas\/[a-z]+\/\d+/;

const STYLE = `
.zsm-overlay{position:fixed;inset:0;z-index:90;background:rgba(2,6,23,.72);display:flex;align-items:center;justify-content:center;padding:2.5vh 2vw;}
.zsm-box{width:min(960px,100%);height:95vh;background:#0f172a;border:1px solid #1e293b;border-radius:14px;box-shadow:0 25px 70px rgba(0,0,0,.55);display:flex;flex-direction:column;overflow:hidden;}
.zsm-bar{display:flex;align-items:center;gap:.5rem;padding:.6rem .75rem .6rem 1rem;border-bottom:1px solid #1e293b;}
.zsm-title{flex:1;min-width:0;color:#e2e8f0;font-size:.85rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.zsm-btn{display:inline-flex;align-items:center;gap:.35rem;height:2.1rem;padding:0 .85rem;border-radius:.55rem;font-size:.78rem;font-weight:700;border:1px solid #334155;background:#1e293b;color:#e2e8f0;cursor:pointer;text-decoration:none;}
.zsm-btn:hover{background:#273449;}
.zsm-btn.primary{background:#0f766e;border-color:#0f766e;color:#fff;}
.zsm-btn.primary:hover{background:#0d6b63;}
.zsm-close{width:2.1rem;justify-content:center;padding:0;font-size:1.15rem;}
.zsm-frame{flex:1;width:100%;border:0;background:#eef2f6;}
.zsm-loading{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:.85rem;pointer-events:none;}
.zsm-body{position:relative;flex:1;display:flex;}
`;

let overlay = null;
let lastFocus = null;

function ensureStyle() {
    if (document.getElementById('zsm-style')) return;
    const style = document.createElement('style');
    style.id = 'zsm-style';
    style.textContent = STYLE;
    document.head.appendChild(style);
}

function withParam(href, key, value) {
    const url = new URL(href, window.location.origin);
    url.searchParams.set(key, value);
    return url.toString();
}

function close() {
    if (!overlay) return;
    overlay.remove();
    overlay = null;
    document.documentElement.style.overflow = '';
    document.removeEventListener('keydown', onKey, true);
    lastFocus?.focus?.();
}

function onKey(ev) {
    if (ev.key === 'Escape') {
        ev.preventDefault();
        close();
    }
}

export function openSheetModal(href) {
    ensureStyle();
    close();
    lastFocus = document.activeElement;

    overlay = document.createElement('div');
    overlay.className = 'zsm-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.innerHTML = `
        <div class="zsm-box">
            <div class="zsm-bar">
                <span class="zsm-title">Ficha de campo</span>
                <a class="zsm-btn primary" data-zsm-pdf href="${withParam(href, 'format', 'pdf')}">Descargar PDF</a>
                <button type="button" class="zsm-btn" data-zsm-print>Imprimir</button>
                <button type="button" class="zsm-btn zsm-close" data-zsm-close aria-label="Cerrar">×</button>
            </div>
            <div class="zsm-body">
                <p class="zsm-loading">Cargando ficha…</p>
                <iframe class="zsm-frame" title="Ficha de campo" src="${withParam(href, 'embed', '1')}"></iframe>
            </div>
        </div>`;
    document.body.appendChild(overlay);
    document.documentElement.style.overflow = 'hidden';

    const frame = overlay.querySelector('iframe');
    frame.addEventListener('load', () => {
        overlay?.querySelector('.zsm-loading')?.remove();
        try {
            const title = frame.contentDocument?.title;
            if (title) overlay.querySelector('.zsm-title').textContent = title;
            frame.contentDocument?.addEventListener('keydown', (ev) => {
                if (ev.key === 'Escape' && !frame.contentDocument.querySelector('.lightbox.open')) close();
            });
        } catch (e) { /* otro origen */ }
    });
    overlay.querySelector('[data-zsm-print]').addEventListener('click', () => frame.contentWindow?.print());
    overlay.querySelector('[data-zsm-close]').addEventListener('click', close);
    overlay.addEventListener('click', (ev) => {
        if (ev.target === overlay) close();
    });
    document.addEventListener('keydown', onKey, true);
    overlay.querySelector('[data-zsm-close]').focus();
}

document.addEventListener('click', (ev) => {
    const link = ev.target.closest?.('a[href]');
    if (!link || ev.defaultPrevented || ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey) return;
    const url = new URL(link.href, window.location.origin);
    if (url.origin !== window.location.origin || !SHEET_PATH.test(url.pathname) || url.searchParams.get('format') === 'pdf') return;
    ev.preventDefault();
    ev.stopPropagation();
    openSheetModal(url.toString());
}, true);
