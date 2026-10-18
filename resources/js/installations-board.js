const fold = (text) => String(text || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();

const esc = (text) => String(text ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[c]));

const PIN = 34;

function pinIcon(posts) {
    const text = posts > 99 ? '99+' : String(posts);
    const color = posts > 0 ? '#0d9488' : '#475569';
    const font = text.length > 2 ? 9 : 12;
    const svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
        + '<circle cx="16" cy="16" r="14" fill="' + color + '" stroke="#0b1220" stroke-width="2"/>'
        + '<circle cx="16" cy="16" r="10.5" fill="none" stroke="#ffffff" stroke-opacity=".35" stroke-width="1.2"/>'
        + '<text x="16" y="20.4" text-anchor="middle" font-family="system-ui,sans-serif" font-size="' + font + '" font-weight="700" fill="#ffffff">' + text + '</text></svg>';

    return {
        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
        scaledSize: new google.maps.Size(PIN, PIN),
        anchor: new google.maps.Point(PIN / 2, PIN / 2),
    };
}

let mapsLoader = null;
function loadGoogleMaps(key) {
    if (window.google?.maps) {
        return Promise.resolve();
    }
    if (!key) {
        return Promise.reject(new Error('sin clave'));
    }
    mapsLoader ??= new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(key);
        script.async = true;
        script.onload = () => resolve();
        script.onerror = reject;
        document.head.appendChild(script);
    });

    return mapsLoader;
}

/** Tablero de Instalaciones (empresa): KPIs, mapa, novedades, revistas e instalaciones con sus servicios. */
export function installationsBoard() {
    let map = null;
    let pins = [];
    let card = null;
    let CardOverlay = null;
    let fitted = false;

    return {
        url: '',
        showUrl: '',
        board: {},
        query: '',
        expanded: {},
        mapType: 'satellite',
        mapReady: false,
        mapError: false,

        init() {
            this.url = this.$el.dataset.liveUrl || '';
            this.showUrl = this.$el.dataset.showUrl || '';
            try {
                this.board = JSON.parse(this.$el.dataset.board || '{}');
            } catch {
                this.board = {};
            }
            this.bootMap();
            window.addEventListener('ops-surface-changed', () => this.refresh());
            window.setInterval(() => {
                if (window.Echo?.connector?.pusher?.connection?.state !== 'connected') {
                    this.refresh();
                }
            }, 12000);
        },

        async refresh() {
            if (!this.url) {
                return;
            }
            try {
                const res = await fetch(this.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (res.ok) {
                    this.board = await res.json();
                    this.drawPins();
                }
            } catch {
                // red caída: el siguiente tick reintenta
            }
        },

        get installations() {
            const list = this.board.installations || [];
            const q = fold(this.query.trim());
            if (q === '') {
                return list;
            }

            return list.filter((row) => fold([row.name, row.code, row.dane, row.client, row.city, row.kind].join(' ')).includes(q));
        },

        toggle(id) {
            this.expanded[id] = !this.expanded[id];
        },

        installationUrl(id) {
            return this.showUrl.replace('__ID__', String(id));
        },

        get reviewsPct() {
            const expected = Number(this.board.reviews_expected_today || 0);
            if (expected === 0) {
                return null;
            }

            return Math.min(100, Math.round((Number(this.board.reviews_done_today || 0) / expected) * 100));
        },

        get reviewsTone() {
            const pct = this.reviewsPct;
            if (pct === null) {
                return 'slate';
            }

            return pct >= 90 ? 'emerald' : (pct >= 60 ? 'amber' : 'red');
        },

        async bootMap() {
            const maps = this.board.maps || {};
            try {
                await loadGoogleMaps(maps.api_key || '');
            } catch {
                this.mapError = true;

                return;
            }
            const el = this.$refs.map;
            if (!el) {
                return;
            }
            map = new google.maps.Map(el, {
                center: maps.center || { lat: 3.4516, lng: -76.532 },
                zoom: 11,
                mapTypeId: google.maps.MapTypeId.HYBRID,
                disableDefaultUI: true,
                zoomControl: true,
                gestureHandling: 'greedy',
            });
            map.addListener('click', () => this.closeCard());
            this.mapReady = true;
            this.drawPins();
        },

        setMapType(type) {
            this.mapType = type;
            map?.setMapTypeId(type === 'terrain' ? google.maps.MapTypeId.TERRAIN : google.maps.MapTypeId.HYBRID);
        },

        drawPins() {
            if (!map) {
                return;
            }
            pins.forEach((pin) => pin.setMap(null));
            pins = [];
            this.closeCard();
            const bounds = new google.maps.LatLngBounds();
            const markers = (this.board.markers || []).filter((m) => m.kind === 'installation');
            markers.forEach((m) => {
                const position = { lat: m.lat, lng: m.lng };
                const pin = new google.maps.Marker({ map, position, icon: pinIcon(Number(m.posts || 0)), title: m.name });
                pin.addListener('click', () => this.openCard(m, position));
                pins.push(pin);
                bounds.extend(position);
            });
            if (!fitted && markers.length) {
                if (markers.length === 1) {
                    map.setCenter(bounds.getCenter());
                    map.setZoom(16);
                } else {
                    map.fitBounds(bounds, 48);
                }
                fitted = true;
            }
        },

        openCard(m, position) {
            this.closeCard();
            CardOverlay ??= class extends google.maps.OverlayView {
                constructor(pos, html) {
                    super();
                    this.pos = pos;
                    this.html = html;
                    this.div = null;
                }

                onAdd() {
                    this.div = document.createElement('div');
                    this.div.style.position = 'absolute';
                    this.div.style.zIndex = '1000';
                    this.div.innerHTML = this.html;
                    this.div.addEventListener('click', (e) => e.stopPropagation());
                    this.div.querySelector('[data-close]')?.addEventListener('click', () => this.setMap(null));
                    this.getPanes().floatPane.appendChild(this.div);
                }

                draw() {
                    const point = this.getProjection()?.fromLatLngToDivPixel(new google.maps.LatLng(this.pos));
                    if (!point || !this.div) {
                        return;
                    }
                    this.div.style.left = (point.x - this.div.offsetWidth / 2) + 'px';
                    this.div.style.top = (point.y - this.div.offsetHeight - PIN / 2 - 8) + 'px';
                }

                onRemove() {
                    this.div?.remove();
                    this.div = null;
                }
            };
            const posts = Number(m.posts || 0);
            const guards = Number(m.guards || 0);
            const html = '<div class="zmap-card" style="width:240px">'
                + '<div style="padding:10px 12px">'
                + '<div style="display:flex;justify-content:space-between;align-items:center;gap:8px">'
                + '<span class="zmap-card-kind">Instalación</span>'
                + '<button type="button" class="zmap-card-close" data-close aria-label="Cerrar">×</button></div>'
                + '<p class="zmap-card-title" style="margin:6px 0 0">' + esc(m.name) + '</p>'
                + '<p class="zmap-card-meta" style="margin:3px 0 0">' + esc(m.client || '') + (m.code ? ' · ' + esc(m.code) : '') + '</p>'
                + '<p style="margin:6px 0 0"><span class="' + (posts > 0 ? 'zmap-card-ok' : 'zmap-card-faint') + '">'
                + posts + ' ' + (posts === 1 ? 'servicio' : 'servicios') + '</span>'
                + '<span class="zmap-card-faint"> · </span><span class="zmap-card-meta">' + guards + ' ' + (guards === 1 ? 'vigilante' : 'vigilantes') + '</span></p>'
                + '<a href="' + esc(this.installationUrl(m.id)) + '" class="zmap-card-link">Ver instalación →</a>'
                + '</div></div>';
            card = new CardOverlay(position, html);
            card.setMap(map);
        },

        closeCard() {
            card?.setMap(null);
            card = null;
        },

        focusInstallation(row) {
            const marker = (this.board.markers || []).find((m) => m.kind === 'installation' && m.id === row.id);
            if (!map || !marker) {
                return;
            }
            const position = { lat: marker.lat, lng: marker.lng };
            map.panTo(position);
            if ((map.getZoom() || 0) < 15) {
                map.setZoom(15);
            }
            this.openCard(marker, position);
        },
    };
}
