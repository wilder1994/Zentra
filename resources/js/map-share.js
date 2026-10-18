/**
 * Botón «Compartir ubicación» para las tarjetas de los mapas.
 * Solo comparte nombre, contexto (cliente/empresa), dirección y enlace a Google Maps; nada interno.
 */
const esc = (text) => String(text ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[c]));

const ICON = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
    + '<circle cx="18" cy="5" r="2.6"/><circle cx="6" cy="12" r="2.6"/><circle cx="18" cy="19" r="2.6"/>'
    + '<path d="M8.3 10.8l7.4-4.3M8.3 13.2l7.4 4.3"/></svg>';

export function mapsUrl(lat, lng) {
    return 'https://www.google.com/maps/search/?api=1&query=' + Number(lat).toFixed(6) + ',' + Number(lng).toFixed(6);
}

export function shareText(place) {
    const lines = [place.title, place.subtitle, place.address]
        .map((line) => String(line ?? '').trim())
        .filter(Boolean);
    if (place.lat != null && place.lng != null) {
        lines.push('Ubicación: ' + mapsUrl(place.lat, place.lng));
    }

    return [...new Set(lines)].join('\n');
}

/** HTML del botón; `place` = { title, subtitle?, address?, lat, lng }. */
export function shareButton(place, { label = false } = {}) {
    const data = esc(JSON.stringify({
        title: place.title || '',
        subtitle: place.subtitle || '',
        address: place.address || '',
        lat: place.lat,
        lng: place.lng,
    }));

    return '<button type="button" class="zmap-share" data-zshare="' + data + '" title="Compartir ubicación" aria-label="Compartir ubicación">'
        + ICON + (label ? '<span>Compartir</span>' : '') + '</button>';
}

/**
 * Tarjeta fija en la esquina superior derecha del mapa (no tapa el zoom).
 * `holder` es el contenedor con `position: relative` que envuelve el mapa.
 */
export function createCornerCard(holder) {
    let box = null;

    const close = () => {
        box?.remove();
        box = null;
    };

    return {
        open(innerHtml) {
            close();
            box = document.createElement('div');
            box.className = 'zmap-card zmap-corner-card';
            box.innerHTML = '<button type="button" class="zmap-card-close zmap-corner-close" aria-label="Cerrar">×</button>' + innerHtml;
            box.querySelector('.zmap-corner-close')?.addEventListener('click', (event) => {
                event.stopPropagation();
                close();
            });
            box.addEventListener('click', (event) => event.stopPropagation());
            holder.appendChild(box);
        },
        close,
    };
}

let menu = null;
let toastTimer = null;

function toast(message) {
    let el = document.getElementById('zmap-share-toast');
    if (!el) {
        el = document.createElement('div');
        el.id = 'zmap-share-toast';
        el.className = 'zmap-share-toast';
        document.body.appendChild(el);
    }
    el.textContent = message;
    el.classList.add('is-on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove('is-on'), 2200);
}

async function copy(text) {
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        const area = document.createElement('textarea');
        area.value = text;
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        area.remove();
    }
    toast('Ubicación copiada');
}

function closeMenu() {
    menu?.remove();
    menu = null;
}

function openMenu(button, place) {
    closeMenu();
    const text = shareText(place);
    const items = [];
    if (navigator.share) {
        items.push(['native', 'Compartir…']);
    }
    items.push(['copy', 'Copiar ubicación'], ['whatsapp', 'Enviar por WhatsApp']);
    if (place.lat != null && place.lng != null) {
        items.push(['maps', 'Abrir en Google Maps']);
    }

    menu = document.createElement('div');
    menu.className = 'zmap-card zmap-share-menu';
    menu.setAttribute('role', 'menu');
    menu.innerHTML = items.map(([action, label]) => '<button type="button" role="menuitem" data-zshare-action="' + action + '">' + esc(label) + '</button>').join('');
    document.body.appendChild(menu);

    const rect = button.getBoundingClientRect();
    const width = menu.offsetWidth;
    const left = Math.min(window.innerWidth - width - 8, Math.max(8, rect.right - width));
    const below = rect.bottom + 6;
    const top = below + menu.offsetHeight > window.innerHeight - 8 ? rect.top - menu.offsetHeight - 6 : below;
    menu.style.left = left + 'px';
    menu.style.top = Math.max(8, top) + 'px';

    menu.addEventListener('click', async (event) => {
        event.stopPropagation();
        const action = event.target.closest('[data-zshare-action]')?.dataset.zshareAction;
        if (!action) {
            return;
        }
        closeMenu();
        if (action === 'native') {
            try {
                await navigator.share({ title: place.title, text });
            } catch {
                // cancelado por el usuario
            }
        } else if (action === 'copy') {
            copy(text);
        } else if (action === 'whatsapp') {
            window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank', 'noopener');
        } else if (action === 'maps') {
            window.open(mapsUrl(place.lat, place.lng), '_blank', 'noopener');
        }
    });
}

let installed = false;

/** Un solo listener global (fase de captura: funciona dentro de tarjetas de Google Maps). */
export function installMapShare() {
    if (installed) {
        return;
    }
    installed = true;
    document.addEventListener('click', (event) => {
        const button = event.target.closest?.('[data-zshare]');
        if (button) {
            event.preventDefault();
            event.stopPropagation();
            let place = {};
            try {
                place = JSON.parse(button.dataset.zshare || '{}');
            } catch {
                place = {};
            }
            openMenu(button, place);

            return;
        }
        if (menu && !menu.contains(event.target)) {
            closeMenu();
        }
    }, true);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMenu();
        }
    });
    window.addEventListener('scroll', closeMenu, true);
}
