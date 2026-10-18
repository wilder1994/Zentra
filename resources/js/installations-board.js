import { createClusterer } from './map-cluster';
import { dropIcon, dropSvg, GLYPHS, MAP_STYLES } from './map-pins';
import { shareButton } from './map-share';

const fold = (text) => String(text || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();

const PIN = 28;

/** Pin suelto: gota con un edificio (sin número; el número es solo para grupos). */
function pinIcon(posts, selected = false) {
    const color = posts > 0 ? '#0d9488' : '#475569';

    return dropIcon(dropSvg(color, GLYPHS.building, { ring: selected }), selected ? PIN + 8 : PIN);
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

/** Tablero de Instalaciones (empresa): KPIs, mapa agrupado, instalaciones con sus servicios, novedades y revistas. */
export function installationsBoard() {
    let map = null;
    let pins = [];
    let pinById = {};
    let clusterer = null;
    let highlighted = null;
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
        card: null,

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

        shareHtml(m) {
            if (!m || m.lat == null || m.lng == null) {
                return '';
            }

            return shareButton({ title: m.name, subtitle: m.client || '', address: m.address || '', lat: m.lat, lng: m.lng }, { label: true });
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
                clickableIcons: false,
                styles: MAP_STYLES,
            });
            map.addListener('click', () => this.closeCard());
            clusterer = createClusterer(map, {
                onSamePlace: (markers) => this.openList(markers.map((marker) => marker.zentraData)),
            });
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
            clusterer?.clearMarkers();
            pins = [];
            pinById = {};
            highlighted = null;
            const bounds = new google.maps.LatLngBounds();
            const markers = (this.board.markers || []).filter((m) => m.kind === 'installation');
            markers.forEach((m) => {
                const position = { lat: m.lat, lng: m.lng };
                const posts = Number(m.posts || 0);
                const pin = new google.maps.Marker({ position, icon: pinIcon(posts), title: m.name });
                pin.zentraTone = posts > 0 ? 'teal' : 'slate';
                pin.zentraData = m;
                pin.addListener('click', () => this.openCard(m));
                pins.push(pin);
                pinById[m.id] = pin;
                bounds.extend(position);
            });
            clusterer?.addMarkers(pins);
            if (this.card?.kind === 'one' && !pinById[this.card.row.id]) {
                this.card = null;
            }
            if (!fitted && markers.length) {
                if (markers.length === 1) {
                    map.setCenter(bounds.getCenter());
                    map.setZoom(16);
                } else {
                    map.fitBounds(bounds, 24);
                }
                fitted = true;
            }
        },

        highlight(id) {
            if (highlighted && pinById[highlighted]) {
                const prev = pinById[highlighted];
                prev.setIcon(pinIcon(Number(prev.zentraData.posts || 0)));
                prev.setZIndex(undefined);
            }
            highlighted = id;
            const pin = id !== null ? pinById[id] : null;
            if (pin) {
                pin.setIcon(pinIcon(Number(pin.zentraData.posts || 0), true));
                pin.setZIndex(Number(google.maps.Marker.MAX_ZINDEX) + 1000);
            }
        },

        openCard(m) {
            this.card = { kind: 'one', row: m };
            this.highlight(m.id);
        },

        openList(list) {
            this.card = { kind: 'list', rows: (list || []).filter(Boolean) };
            this.highlight(null);
        },

        closeCard() {
            this.card = null;
            this.highlight(null);
        },

        focusInstallation(row) {
            const marker = (this.board.markers || []).find((m) => m.kind === 'installation' && m.id === row.id);
            if (!map || !marker) {
                return;
            }
            map.panTo({ lat: marker.lat, lng: marker.lng });
            if ((map.getZoom() || 0) < 17) {
                map.setZoom(17);
            }
            this.openCard(marker);
        },
    };
}
