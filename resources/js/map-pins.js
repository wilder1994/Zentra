/**
 * Pines de Zentra en forma de gota (viewBox 32×42, punta abajo en (16, 41)).
 * La cabeza es un círculo de radio 13 centrado en (16, 16): ahí va el ícono o el número.
 */
/** Sin íconos de lugares de Google: se confunden con nuestras gotas. */
export const MAP_STYLES = [
    { featureType: 'poi', stylers: [{ visibility: 'off' }] },
    { featureType: 'transit', elementType: 'labels.icon', stylers: [{ visibility: 'off' }] },
];

export const DROP_W = 32;
export const DROP_H = 42;
export const DROP_PATH = 'M16 41C16 41 29 27.6 29 16A13 13 0 0 0 3 16C3 27.6 16 41 16 41Z';

/** Íconos blancos para la cabeza de la gota (dibujados sobre la rejilla 32×32). */
export const GLYPHS = {
    building: '<path d="M10 22v-8.6l6-3.9 6 3.9V22z" fill="#ffffff"/><path d="M14.3 22v-4.2h3.4V22z" fill="__COLOR__"/>',
    client: '<circle cx="16" cy="12.6" r="3.4" fill="#ffffff"/><path d="M9.6 22.4c.6-3.6 3.2-5.6 6.4-5.6s5.8 2 6.4 5.6z" fill="#ffffff"/>',
    company: '<path d="M10 22.5V9.5h7v13z" fill="#ffffff"/><path d="M17 22.5v-8h5v8z" fill="#ffffff" fill-opacity=".8"/><path d="M12 12h3M12 15h3M12 18h3" stroke="__COLOR__" stroke-width="1.2"/>',
    dot: '<circle cx="16" cy="16" r="5" fill="#ffffff"/>',
    alert: '<rect x="14.6" y="9.6" width="2.8" height="8" rx="1.4" fill="#ffffff"/><circle cx="16" cy="21.4" r="1.7" fill="#ffffff"/>',
};

/** SVG completo de una gota. `inner` va dentro de la cabeza (escala 0.86 para dejar borde). */
export function dropSvg(color, inner = '', { ring = false, stroke = '#0b1220' } = {}) {
    const glyph = String(inner).split('__COLOR__').join(color);
    const halo = ring ? '<circle cx="16" cy="16" r="14.6" fill="none" stroke="#fbbf24" stroke-width="2.6"/>' : '';

    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 42">'
        + '<path d="' + DROP_PATH + '" fill="' + color + '" stroke="' + stroke + '" stroke-width="1.8" stroke-linejoin="round"/>'
        + halo
        + (glyph ? '<g transform="translate(16 16) scale(.86) translate(-16 -16)">' + glyph + '</g>' : '')
        + '</svg>';
}

/** Ícono de Google Maps a partir de una gota: punta abajo, anclada en el punto. */
export function dropIcon(svg, width = 26) {
    const height = width * DROP_H / DROP_W;

    return {
        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
        scaledSize: new google.maps.Size(width, height),
        anchor: new google.maps.Point(width / 2, height - 1),
    };
}

/** Gota de grupo: el número de elementos en la cabeza, un poco más grande según el total. */
export function numberDropIcon(color, count) {
    const text = count > 999 ? '999+' : String(count);
    const font = text.length > 3 ? 8.5 : (text.length > 2 ? 10.5 : (text.length > 1 ? 12.5 : 14));
    const inner = '<circle cx="16" cy="16" r="10.2" fill="none" stroke="#ffffff" stroke-opacity=".45" stroke-width="1.2"/>'
        + '<text x="16" y="' + (16 + font * 0.36).toFixed(2) + '" text-anchor="middle" font-family="system-ui,sans-serif" font-size="' + font + '" font-weight="800" fill="#ffffff">' + text + '</text>';
    const width = count < 10 ? 32 : (count < 50 ? 36 : 42);

    return dropIcon(dropSvg(color, inner), width);
}
