import { createClusterer } from './map-cluster';
import { dropIcon, dropSvg, GLYPHS, MAP_STYLES } from './map-pins';
import { shareButton } from './map-share';

/** Tono del grupo según el riesgo abierto (los colores del catálogo los define cada cliente). */
function donutIcon(slices, total, neon = '') {
    const list = (slices || []).filter((row) => Number(row.count) > 0);
    const sum = list.reduce((acc, row) => acc + Number(row.count), 0);
    const cx = 36;
    const cy = 34;
    const r = 24;
    const inner = 14;
    let angle = -Math.PI / 2;
    const paths = (sum === 0 ? [{ color: '#334155', count: 1 }] : list).map((row) => {
        const sweep = ((Number(row.count) || 1) / (sum || 1)) * Math.PI * 2;
        if (list.length === 1 || sweep > Math.PI * 1.999) {
            return `<circle cx="${cx}" cy="${cy}" r="${(r + inner) / 2}" fill="none" stroke="${row.color}" stroke-width="${r - inner}"/>`;
        }
        const large = sweep > Math.PI ? 1 : 0;
        const x1 = cx + r * Math.cos(angle);
        const y1 = cy + r * Math.sin(angle);
        angle += sweep;
        const x2 = cx + r * Math.cos(angle);
        const y2 = cy + r * Math.sin(angle);
        const ix = cx + inner * Math.cos(angle);
        const iy = cy + inner * Math.sin(angle);
        const back = angle - sweep;
        const ix0 = cx + inner * Math.cos(back);
        const iy0 = cy + inner * Math.sin(back);

        return `<path d="M ${x1.toFixed(2)} ${y1.toFixed(2)} A ${r} ${r} 0 ${large} 1 ${x2.toFixed(2)} ${y2.toFixed(2)} L ${ix.toFixed(2)} ${iy.toFixed(2)} A ${inner} ${inner} 0 ${large} 0 ${ix0.toFixed(2)} ${iy0.toFixed(2)} Z" fill="${row.color}"/>`;
    }).join('');
    const label = new Intl.NumberFormat('es-CO').format(Number(total) || 0);
    const size = label.length > 5 ? 9 : 12;
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="72" height="88" viewBox="0 0 72 88">
        <path d="M36 86 L16 48 A28 28 0 1 1 56 48 Z" fill="#0f172a" ${neon ? `stroke="${neon}" stroke-width="3.5"` : ''}/>
        <circle cx="36" cy="34" r="28" fill="#e5e7eb"/>
        <circle cx="36" cy="34" r="25" fill="#fff"/>
        ${paths}
        <circle cx="36" cy="34" r="13" fill="#fff"/>
        <text x="36" y="38" text-anchor="middle" font-family="Arial,sans-serif" font-size="${size}" font-weight="700" fill="#0f172a">${label}</text>
    </svg>`;

    return {
        url: `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`,
        scaledSize: new google.maps.Size(48, 58),
        anchor: new google.maps.Point(24, 56),
    };
}

function heatColor(ratio) {
    const t = Math.max(0, Math.min(1, Number(ratio) || 0));
    const from = t < 0.5 ? [37, 99, 235] : [250, 204, 21];
    const to = t < 0.5 ? [250, 204, 21] : [220, 38, 38];
    const p = t < 0.5 ? t / 0.5 : (t - 0.5) / 0.5;
    const mix = from.map((channel, index) => Math.round(channel + (to[index] - channel) * p));

    return `rgb(${mix[0]}, ${mix[1]}, ${mix[2]})`;
}

function heatPoint(ratio, neon = '') {
    const t = Math.max(0, Math.min(1, Number(ratio) || 0));

    return {
        path: google.maps.SymbolPath.CIRCLE,
        scale: 7 + Math.round(t * 6),
        fillColor: heatColor(t),
        fillOpacity: 0.92,
        strokeColor: neon || '#ffffff',
        strokeWeight: neon ? 3 : 1,
    };
}

function countIcon(total, neon = '') {
    const label = new Intl.NumberFormat('es-CO').format(Number(total) || 0);
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="54" height="54" viewBox="0 0 54 54">
        <circle cx="27" cy="27" r="24" fill="#fff" stroke="${neon || '#0f172a'}" stroke-width="${neon ? 4 : 3}"/>
        <text x="27" y="32" text-anchor="middle" font-family="Arial,sans-serif" font-size="12" font-weight="700" fill="#0f172a">${label}</text>
    </svg>`;

    return {
        url: `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`,
        scaledSize: new google.maps.Size(42, 42),
        anchor: new google.maps.Point(21, 21),
    };
}

function riskTone(open, score) {
    if (!open) {
        return 'slate';
    }
    const w = Number(score) || 0;
    if (w >= 80) {
        return 'red';
    }
    if (w >= 60) {
        return 'orange';
    }
    if (w >= 30) {
        return 'amber';
    }

    return 'emerald';
}

let siteCard = null;
let openSiteId = 0;
const knownPending = new Set();
let pendingSeeded = false;
function pinCard(position, html, onClose) {
    const overlay = new google.maps.OverlayView();
    overlay.onAdd = function onAdd() {
        const div = document.createElement('div');
        div.className = 'obs-pin-cards';
        div.innerHTML = html;
        div.addEventListener('click', (event) => event.stopPropagation());
        div.querySelector('[data-obs-close]')?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            onClose();
        });
        this.node = div;
        this.getPanes().floatPane.appendChild(div);
    };
    overlay.draw = function draw() {
        const point = this.getProjection()?.fromLatLngToDivPixel(position);
        if (!point || !this.node) {
            return;
        }
        this.node.style.left = `${point.x}px`;
        this.node.style.top = `${point.y}px`;
    };
    overlay.onRemove = function onRemove() {
        this.node?.remove();
        this.node = null;
    };
    overlay.drop = function drop() {
        this.node?.remove();
        this.node = null;
        this.setMap(null);
    };

    return overlay;
}

export function observatoryMap(cfg) {
    const layerUrl = '/geo/cali-comunas.geojson';
    let clusterer = null;
    // Fuera de Alpine, igual que `overlays` en Supervisión. Si el arreglo es reactivo,
    // setMap(null) no suelta el canvas y el trazo reaparece al hacer zoom.
    let overlays = [];
    let crimeMarkers = [];
    let placeMarkers = [];
    let crimeToken = 0;

    return {
        layer: 'reportes',
        mode: 'grouped',
        mapType: 'satellite',
        catalogOpen: false,
        typeFilter: '',
        comuna: cfg.comuna || '',
        comunaQ: '',
        comunaOpen: false,
        comunas: cfg.comunas || [],
        map: null,
        shifts: cfg.shifts || [],
        selectedShiftId: null,
        motoMarkers: {},
        motoAnims: {},
        neonOn: false,
        neonTimer: null,

        init() {
            window.initObservatoryMap = () => this.draw();
            if (window.google?.maps || window.__observatoryMapReady) {
                this.draw();
            }
        },

        metersBetween(a, b) {
            const toRad = (deg) => deg * Math.PI / 180;
            const dLat = toRad(b.lat - a.lat);
            const dLng = toRad(b.lng - a.lng);
            const lat1 = toRad(a.lat);
            const lat2 = toRad(b.lat);
            const h = Math.sin(dLat / 2) ** 2
                + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;

            return 6371000 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
        },

        signalOf(row) {
            return row?.signal || (row?.online ? 'online' : 'no_signal');
        },

        signalColor(row) {
            const signal = this.signalOf(row);
            if (signal === 'screen_off') {
                return '#fbbf24';
            }
            if (signal === 'online') {
                return '#86efac';
            }

            return '#fca5a5';
        },

        motoIcon(selected) {
            const moto = cfg.moto || '';
            if (!moto) {
                return undefined;
            }

            return {
                url: moto,
                scaledSize: new google.maps.Size(selected ? 56 : 50, selected ? 40 : 36),
                anchor: new google.maps.Point(selected ? 28 : 25, selected ? 38 : 34),
            };
        },

        motoLabel(row) {
            const text = row?.online_label || (row?.online ? 'En línea' : 'Sin señal');

            return {
                text,
                color: this.signalColor(row),
                fontSize: '11px',
                fontWeight: '700',
                className: 'obs-moto-label',
            };
        },

        esc(value) {
            return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            }[char]));
        },

        draw() {
            const el = this.$refs.map;
            if (!el || !window.google?.maps || this.map) {
                return;
            }

            const sites = cfg.sites || [];
            const points = cfg.points || [];
            this.map = new google.maps.Map(el, {
                center: { lat: 3.4372, lng: -76.5225 },
                zoom: 12,
                mapTypeId: google.maps.MapTypeId.HYBRID,
                streetViewControl: false,
                fullscreenControl: false,
                mapTypeControl: false,
                clickableIcons: false,
                styles: MAP_STYLES,
                zoomControl: true,
                zoomControlOptions: {
                    position: google.maps.ControlPosition.RIGHT_BOTTOM,
                },
            });

            const info = new google.maps.InfoWindow({ pixelOffset: new google.maps.Size(0, -4) });
            this.infoWindow = info;
            this.seedPending();
            window.addEventListener('ops-live-data', (event) => {
                const sites = event.detail?.map?.sites;
                if (Array.isArray(sites)) {
                    this.applyLiveSites(sites);
                }
            });
            this.map.addListener('click', () => this.closeSiteCards());
            clusterer = createClusterer(this.map, {
                clusterZIndex: 4,
                iconForCluster: (cluster) => this.clusterIcon(cluster),
                onSamePlace: (markers) => {
                    if (markers[0]) {
                        google.maps.event.trigger(markers[0], 'click');
                    }
                },
            });

            sites.forEach((site) => {
                const pos = { lat: Number(site.lat), lng: Number(site.lng) };
                const open = site.open_count > 0;
                const pinIcon = dropIcon(dropSvg(site.color || '#94a3b8', GLYPHS.building), open ? 28 : 24);
                const marker = new google.maps.Marker({
                    position: pos,
                    map: null,
                    optimized: true,
                    title: site.name,
                    zIndex: 1,
                    icon: pinIcon,
                    kindSlugs: site.type_slugs || (site.kind_slug ? [site.kind_slug] : []),
                    role: 'site',
                    site,
                    pinIcon,
                    heatWeight: open ? Number(site.heat_weight || 1) : 0,
                    riskWeight: open ? Number(site.risk_weight || 1) : 0,
                });
                marker.zentraTone = riskTone(open, site.risk_weight);
                marker.addListener('click', (event) => {
                    event?.stop?.();
                    this.openSiteCards(marker);
                });
                placeMarkers.push(marker);
            });

            points.forEach((point) => {
                const pos = { lat: Number(point.lat), lng: Number(point.lng) };
                const pinIcon = dropIcon(dropSvg(point.color || '#94a3b8', GLYPHS.alert, { stroke: '#ffffff' }), 22);
                const marker = new google.maps.Marker({
                    position: pos,
                    map: null,
                    optimized: true,
                    title: point.kind || point.title,
                    zIndex: 2,
                    icon: pinIcon,
                    kindSlugs: point.kind_slug ? [point.kind_slug] : [],
                    role: 'point',
                    pinIcon,
                    heatWeight: point.open ? 1 : 0,
                    riskWeight: point.open ? Number(point.score || 0) : 0,
                });
                marker.zentraTone = riskTone(point.open, point.score);
                marker.addListener('click', () => {
                    const link = point.show_url
                        ? `<a href="${this.esc(point.show_url)}">Ver evento</a>`
                        : '';
                    const share = shareButton({ title: (point.kind ? point.kind + ' · ' : '') + point.title, subtitle: point.client || '', address: point.address || '', lat: pos.lat, lng: pos.lng }, { label: true });
                    info.setContent(
                        `<div class="zmap-share-light" style="color:#0f172a;font:13px/1.4 sans-serif;max-width:220px">`
                        + `<strong>${this.esc(point.title)}</strong><br>`
                        + `<span style="color:${this.esc(point.color || '#334155')}">${this.esc(point.kind)}</span>`
                        + ` · ${this.esc(point.status_label)}`
                        + `<div style="margin-top:6px;display:flex;align-items:center;justify-content:space-between;gap:8px">${link}${share}</div>`
                        + `</div>`,
                    );
                    info.open(this.map, marker);
                });
                placeMarkers.push(marker);
            });

            this.applyMode();
            window.addEventListener('observatory-shifts', (event) => {
                this.shifts = Array.isArray(event.detail) ? event.detail : [];
                if (this.selectedShiftId != null && !this.shifts.some((row) => Number(row.shift_id) === Number(this.selectedShiftId))) {
                    this.selectedShiftId = null;
                }
                this.drawShifts(false);
            });
            window.addEventListener('observatory-shift-pick', (event) => {
                const detail = event.detail;
                const id = detail && typeof detail === 'object' ? detail.id : detail;
                this.selectedShiftId = id == null || id === '' ? null : Number(id);
                if (this.selectedShiftId != null && detail && typeof detail === 'object') {
                    const path = Array.isArray(detail.path) ? detail.path : [];
                    this.shifts = (this.shifts || []).map((row) => (
                        Number(row.shift_id) === Number(this.selectedShiftId)
                            ? {
                                ...row,
                                path,
                                lat: detail.lat ?? row.lat,
                                lng: detail.lng ?? row.lng,
                                end: detail.lat != null && detail.lng != null
                                    ? { lat: detail.lat, lng: detail.lng, at: row.recorded_at }
                                    : row.end,
                            }
                            : row
                    ));
                }
                this.drawShifts(this.selectedShiftId != null ? 'route' : 'all');
            });
            this.loadComunas();
            google.maps.event.addListenerOnce(this.map, 'idle', () => {
                google.maps.event.trigger(this.map, 'resize');
            });
        },

        loadComunas() {
            if (!this.map) {
                return;
            }
            fetch(layerUrl)
                .then((res) => (res.ok ? res.json() : Promise.reject(new Error(String(res.status)))))
                .then((geo) => {
                    if (!geo || !this.map?.data) {
                        this.fitSitesFallback();
                        return;
                    }
                    this.map.data.addGeoJson(geo);
                    this.styleComunas();
                    this.map.data.addListener('click', (event) => {
                        const code = event.feature?.getProperty('code');
                        if (!code) {
                            return;
                        }
                        this.goComuna(String(code));
                    });
                    this.fitLayer();
                })
                .catch(() => this.fitSitesFallback());
        },

        styleComunas() {
            if (!this.map?.data) {
                return;
            }
            const heat = this.layer === 'riesgos' && this.mode === 'heat';
            const totals = {};
            let max = 1;
            if (heat) {
                this.crimeRows().forEach((row) => {
                    const total = this.totalOf(row.counts);
                    totals[row.code] = total;
                    if (total > max) {
                        max = total;
                    }
                });
            }
            this.map.data.setStyle((feature) => {
                const code = String(feature.getProperty('code') || '');
                const selected = this.comuna !== '' && this.comuna !== 'fuera' && code === this.comuna;
                const visible = this.comuna !== 'fuera' && (!this.comuna || selected);
                const total = totals[code] || 0;
                if (heat && total > 0) {
                    return {
                        strokeColor: '#f8fafc',
                        strokeOpacity: 0.95,
                        strokeWeight: selected ? 2.4 : 1.2,
                        fillColor: heatColor(total / max),
                        fillOpacity: 0.72,
                        clickable: true,
                        visible,
                    };
                }

                return {
                    strokeColor: selected ? '#f8fafc' : '#7dd3fc',
                    strokeOpacity: selected ? 1 : 0.85,
                    strokeWeight: selected ? 2.8 : 1.4,
                    fillColor: '#38bdf8',
                    fillOpacity: heat ? 0 : (selected ? 0.4 : 0.16),
                    clickable: true,
                    visible,
                };
            });
        },

        fitLayer() {
            if (!this.map?.data || this.comuna === 'fuera') {
                this.fitSitesFallback();
                return;
            }
            const bounds = new google.maps.LatLngBounds();
            let any = false;
            this.map.data.forEach((feature) => {
                const code = String(feature.getProperty('code') || '');
                if (this.comuna && code !== this.comuna) {
                    return;
                }
                feature.getGeometry()?.forEachLatLng((ll) => {
                    bounds.extend(ll);
                    any = true;
                });
            });
            if (any) {
                this.map.fitBounds(bounds, 36);
                return;
            }
            this.fitSitesFallback();
        },

        fitMotosAndSites() {
            if (!this.map) {
                return;
            }
            const bounds = new google.maps.LatLngBounds();
            let any = false;
            const extend = (lat, lng) => {
                const point = { lat: Number(lat), lng: Number(lng) };
                if (!Number.isFinite(point.lat) || !Number.isFinite(point.lng)) {
                    return;
                }
                bounds.extend(point);
                any = true;
            };
            (this.shifts || []).forEach((row) => {
                const end = row.end && row.end.lat != null
                    ? row.end
                    : (row.lat != null ? { lat: row.lat, lng: row.lng } : null);
                if (end) {
                    extend(end.lat, end.lng);
                }
            });
            (cfg.sites || []).forEach((site) => extend(site.lat, site.lng));
            if (!any) {
                this.fitSitesFallback();
                return;
            }
            this.map.fitBounds(bounds, 48);
            google.maps.event.addListenerOnce(this.map, 'idle', () => {
                if ((this.map.getZoom() || 0) > 16) {
                    this.map.setZoom(16);
                }
            });
        },

        fitSitesFallback() {
            const bounds = new google.maps.LatLngBounds();
            let any = false;
            (cfg.sites || []).forEach((site) => {
                bounds.extend({ lat: Number(site.lat), lng: Number(site.lng) });
                any = true;
            });
            (cfg.points || []).forEach((point) => {
                bounds.extend({ lat: Number(point.lat), lng: Number(point.lng) });
                any = true;
            });
            if (any && !this.comuna) {
                this.map.fitBounds(bounds, 48);
                return;
            }
            this.map.setCenter({ lat: 3.4372, lng: -76.5225 });
            this.map.setZoom(this.comuna ? 13 : 12);
        },

        comunaRows() {
            return [...this.comunas, { code: 'fuera', name: 'Fuera de Cali' }];
        },

        comunaLabel() {
            if (!this.comuna) {
                return 'Todas';
            }
            return this.comunaRows().find((row) => row.code === this.comuna)?.name || this.comuna;
        },

        filteredComunas() {
            const q = this.comunaQ.trim().toLowerCase();
            const rows = this.comunaRows();
            if (!q) {
                return rows;
            }

            return rows.filter((row) => (
                row.code.toLowerCase().includes(q)
                || row.name.toLowerCase().replace(/^comuna\s+0?/, '').includes(q)
                || row.name.toLowerCase().includes(q)
            ));
        },

        goComuna(code) {
            const url = new URL(window.location.href);
            if (!code || this.comuna === code) {
                url.searchParams.delete('comuna');
            } else {
                url.searchParams.set('comuna', code);
            }
            window.location.assign(url.toString());
        },

        setLayer(layer) {
            this.layer = layer;
            this.typeFilter = '';
            this.applyMode();
        },

        setMode(mode) {
            this.mode = mode;
            this.applyMode();
        },

        legend() {
            if (this.layer === 'riesgos') {
                return (cfg.crimes && cfg.crimes.types) || [];
            }

            return cfg.reportTypes || [];
        },

        crimeRows() {
            let rows = (cfg.crimes && cfg.crimes.rows) || [];
            if (this.comuna && this.comuna !== 'fuera') {
                rows = rows.filter((row) => row.code === this.comuna);
            }

            return rows;
        },

        clearCrimes() {
            crimeMarkers.forEach((marker) => marker.setMap(null));
            crimeMarkers = [];
        },

        slicesOf(counts) {
            return ((cfg.crimes && cfg.crimes.types) || [])
                .filter((type) => !this.typeFilter || type.slug === this.typeFilter)
                .map((type) => ({ color: type.color, name: type.name, count: Number((counts || {})[type.slug] || 0) }));
        },

        totalOf(counts) {
            return this.slicesOf(counts).reduce((sum, slice) => sum + slice.count, 0);
        },

        drawCrimes() {
            const token = (crimeToken += 1);
            this.clearCrimes();
            this.styleComunas();
            if (!this.map || this.layer !== 'riesgos' || this.mode === 'heat') {
                return;
            }
            this.crimeRows().forEach((row) => {
                if (token !== crimeToken) {
                    return;
                }
                const slices = this.slicesOf(row.counts);
                const total = slices.reduce((sum, slice) => sum + slice.count, 0);
                if (total < 1) {
                    return;
                }
                const marker = new google.maps.Marker({
                    position: { lat: Number(row.lat), lng: Number(row.lng) },
                    map: null,
                    optimized: true,
                    zIndex: 6,
                    title: row.name,
                    icon: this.mode === 'places' ? countIcon(total) : donutIcon(slices, total),
                });
                marker.addListener('click', () => {
                    const lines = slices.filter((slice) => slice.count > 0)
                        .map((slice) => `<span style="color:${this.esc(slice.color)}">${this.esc(slice.name)}</span> ${new Intl.NumberFormat('es-CO').format(slice.count)}`)
                        .join('<br>');
                    const info = new google.maps.InfoWindow({
                        content: `<div style="color:#0f172a;font:13px/1.45 sans-serif"><strong>${this.esc(row.name)}</strong><br>${lines || 'Sin cifras'}</div>`,
                    });
                    info.open(this.map, marker);
                });
                if (token !== crimeToken) {
                    marker.setMap(null);
                    return;
                }
                marker.setMap(this.map);
                crimeMarkers.push(marker);
            });
        },

        setTypeFilter(slug) {
            this.typeFilter = this.typeFilter === slug ? '' : slug;
            this.applyMode();
        },

        setMapType(type) {
            this.mapType = type;
            if (this.map) {
                this.map.setMapTypeId(type === 'roadmap' ? google.maps.MapTypeId.ROADMAP : google.maps.MapTypeId.HYBRID);
            }
        },

        applyMode() {
            clusterer?.clearMarkers();
            if (this.layer === 'riesgos') {
                this.drawCrimes();
            } else {
                this.clearCrimes();
                this.styleComunas();
            }
            this.showSites();
            this.drawShifts(false);
        },
        seedPending() {
            (cfg.sites || []).forEach((site) => {
                const id = Number(site.pending?.event_id || 0);
                if (id) {
                    knownPending.add(id);
                }
            });
            pendingSeeded = true;
        },
        applyLiveSites(sites) {
            const byId = new Map(sites.map((site) => [Number(site.id), site]));
            placeMarkers.forEach((marker) => {
                if (marker.role !== 'site') {
                    return;
                }
                const next = byId.get(Number(marker.site?.id));
                if (next) {
                    marker.site = { ...marker.site, ...next };
                }
            });
            const fresh = [];
            sites.forEach((site) => {
                const id = Number(site.pending?.event_id || 0);
                if (!id || knownPending.has(id)) {
                    return;
                }
                knownPending.add(id);
                if (pendingSeeded) {
                    fresh.push(site);
                }
            });
            if (fresh[0] && this.layer === 'reportes') {
                this.focusSite(fresh[0]);
            }
            this.showSites();
            if (openSiteId) {
                const marker = placeMarkers.find((item) => item.role === 'site' && Number(item.site?.id) === openSiteId);
                if (marker) {
                    this.openSiteCards(marker, true);
                }
            }
        },
        focusSite(site) {
            if (!this.map || site.lat == null || site.lng == null) {
                return;
            }
            this.map.panTo({ lat: Number(site.lat), lng: Number(site.lng) });
            this.map.setZoom(17);
        },
        closeSiteCards() {
            siteCard?.drop?.();
            siteCard = null;
            openSiteId = 0;
            document.querySelectorAll('.obs-pin-cards').forEach((node) => node.remove());
        },
        openSiteCards(marker, refresh = false) {
            window.dispatchEvent(new CustomEvent('observatory-pin-clicked'));
            const site = marker.site || {};
            const id = Number(site.id) || 0;
            if (!refresh && openSiteId === id && siteCard) {
                this.closeSiteCards();
                return;
            }
            openSiteId = id;
            siteCard?.drop?.();
            const dane = site.dane_code ? this.esc(site.dane_code) : 'Sin código DANE';
            const comuna = site.comuna_name ? this.esc(site.comuna_name) : 'Sin comuna';
            const share = shareButton({
                title: site.name,
                subtitle: site.client || '',
                address: site.address || '',
                lat: site.lat,
                lng: site.lng,
            }, { label: true });
            const pending = site.pending;
            const report = pending
                ? `<article class="zmap-card obs-pin-card obs-pin-card-alert">`
                    + `<p class="zmap-card-kind">Reporte pendiente</p>`
                    + `<p class="zmap-card-title">${this.esc(pending.folio)} · ${this.esc(pending.kind || '')}</p>`
                    + (pending.text ? `<p class="zmap-card-meta">${this.esc(pending.text)}</p>` : '')
                    + (pending.show_url ? `<a class="zmap-card-link" href="${this.esc(pending.show_url)}">Abrir folio</a>` : '')
                    + `</article>`
                : '';
            const html = `<button type="button" class="obs-pin-x" data-obs-close aria-label="Cerrar">×</button>`
                + report
                + `<article class="zmap-card obs-pin-card">`
                + `<p class="zmap-card-kind">Instalación</p>`
                + `<p class="zmap-card-title">${this.esc(site.name)}</p>`
                + (site.client ? `<p class="zmap-card-meta">${this.esc(site.client)}</p>` : '')
                + (site.address ? `<p class="zmap-card-faint">${this.esc(site.address)}</p>` : '')
                + `<p class="zmap-card-meta">${comuna}</p>`
                + `<p class="zmap-card-meta">DANE ${dane}</p>`
                + `<p class="zmap-card-meta">${Number(site.posts_count || 0)} puestos · ${Number(site.guards_count || 0)} guardas</p>`
                + `<div class="obs-pin-share">${share}</div>`
                + `</article>`;
            siteCard = pinCard(marker.getPosition(), html, () => this.closeSiteCards());
            siteCard.setMap(this.map);
        },
        paintNeon() {
            const ink = this.neonOn ? '#ffe4e6' : '#fb7185';
            let any = false;
            if (this.map && this.layer === 'reportes') {
                placeMarkers.forEach((marker) => {
                    if (marker.role !== 'site' || !marker.site?.pending) {
                        return;
                    }
                    any = true;
                    marker.setIcon(this.neonIcon(marker, ink));
                });
            }
            if (any && !this.neonTimer) {
                this.neonTimer = setInterval(() => {
                    this.neonOn = !this.neonOn;
                    this.paintNeon();
                }, 700);
            }
            if (!any && this.neonTimer) {
                clearInterval(this.neonTimer);
                this.neonTimer = null;
            }
        },
        neonIcon(marker, ink) {
            const summary = this.siteSummary(marker);
            const total = summary.reduce((sum, row) => sum + Number(row.count || 0), 0);
            if (this.mode === 'heat') {
                const max = Math.max(1, ...placeMarkers.filter((item) => item.role === 'site').map((item) => this.siteTotal(item)));

                return heatPoint(total / max, ink);
            }
            if (this.mode === 'places') {
                return countIcon(total, ink);
            }

            return donutIcon(summary, total, ink);
        },
        clusterIcon(cluster) {
            const markers = cluster.markers || [];
            if (this.mode === 'heat') {
                const max = Math.max(1, ...placeMarkers.filter((marker) => marker.role === 'site').map((marker) => this.siteTotal(marker)));
                const total = markers.reduce((sum, marker) => sum + this.siteTotal(marker), 0);
                const ratio = markers.length ? (total / markers.length) / max : 0;

                return heatPoint(ratio);
            }
            const slices = new Map();
            let total = 0;
            markers.forEach((marker) => {
                this.siteSummary(marker).forEach((row) => {
                    const key = row.slug || row.name;
                    const prev = slices.get(key) || { slug: row.slug, name: row.name, color: row.color, count: 0 };
                    prev.count += Number(row.count || 0);
                    slices.set(key, prev);
                    total += Number(row.count || 0);
                });
            });
            if (this.mode === 'grouped') {
                return donutIcon([...slices.values()], total);
            }

            return countIcon(total);
        },
        showSites() {
            clusterer?.clearMarkers();
            placeMarkers.forEach((marker) => marker.setMap(null));
            if (this.layer === 'riesgos') {
                return;
            }
            const sites = placeMarkers.filter((marker) => marker.role === 'site');
            const max = Math.max(1, ...sites.map((marker) => this.siteTotal(marker)));
            sites.forEach((marker) => {
                const summary = this.siteSummary(marker);
                const total = summary.reduce((sum, row) => sum + Number(row.count || 0), 0);
                if (this.mode === 'heat') {
                    marker.setIcon(heatPoint(total / max));
                } else if (this.mode === 'places') {
                    marker.setIcon(countIcon(total));
                } else {
                    marker.setIcon(donutIcon(summary, total));
                }
            });
            clusterer?.addMarkers(sites);
            this.paintNeon();
        },

        siteSummary(marker) {
            return (marker.site?.report_summary || [])
                .filter((row) => !this.typeFilter || row.slug === this.typeFilter);
        },

        siteTotal(marker) {
            return this.siteSummary(marker).reduce((sum, row) => sum + Number(row.count || 0), 0);
        },

        clearOverlays() {
            overlays.forEach((item) => item.setMap(null));
            overlays = [];
        },

        addOverlay(item) {
            overlays.push(item);

            return item;
        },

        drawPath(path) {
            if (path.length > 1) {
                this.addOverlay(new google.maps.Polyline({
                    map: this.map,
                    path,
                    strokeColor: '#f59e0b',
                    strokeOpacity: 0.92,
                    strokeWeight: 4,
                }));
            }
        },

        drawShifts(camera) {
            if (!this.map) {
                return;
            }
            this.clearOverlays();
            const follow = camera === 'route';
            const bounds = follow ? new google.maps.LatLngBounds() : null;
            let points = 0;
            const seen = {};
            (this.shifts || []).forEach((row) => {
                const id = Number(row.shift_id);
                const selected = this.selectedShiftId != null && id === Number(this.selectedShiftId);
                const path = selected
                    ? (row.path || [])
                        .map((point) => ({ lat: Number(point.lat), lng: Number(point.lng) }))
                        .filter((point) => Number.isFinite(point.lat) && Number.isFinite(point.lng))
                    : [];
                if (path.length > 1) {
                    this.drawPath(path);
                    path.forEach((point) => {
                        bounds?.extend(point);
                        points += 1;
                    });
                }
                const end = row.end && row.end.lat != null
                    ? row.end
                    : (row.lat != null ? { lat: row.lat, lng: row.lng } : null);
                if (!end || end.lat == null || end.lng == null || !id) {
                    return;
                }
                const next = { lat: Number(end.lat), lng: Number(end.lng) };
                seen[id] = true;
                let marker = this.motoMarkers[id];
                const opacity = this.signalOf(row) === 'no_signal' ? 0.72 : 1;
                if (!marker) {
                    marker = new google.maps.Marker({
                        map: this.map,
                        position: next,
                        optimized: false,
                        zIndex: selected ? 20 : 18,
                        title: [row.user, row.online_label].filter(Boolean).join(' · '),
                        icon: this.motoIcon(selected),
                        label: this.motoLabel(row),
                        opacity,
                    });
                    this.motoMarkers[id] = marker;
                } else {
                    marker.setMap(this.map);
                    marker.setZIndex(selected ? 20 : 18);
                    marker.setTitle([row.user, row.online_label].filter(Boolean).join(' · '));
                    marker.setIcon(this.motoIcon(selected));
                    marker.setLabel(this.motoLabel(row));
                    marker.setOpacity(opacity);
                    const current = marker.getPosition();
                    const from = current ? { lat: current.lat(), lng: current.lng() } : next;
                    const meters = this.metersBetween(from, next);
                    if (this.motoAnims[id]) {
                        cancelAnimationFrame(this.motoAnims[id]);
                        delete this.motoAnims[id];
                    }
                    if (meters < 3 || meters > 250) {
                        marker.setPosition(next);
                    } else {
                        const started = performance.now();
                        const step = (now) => {
                            const t = Math.min(1, (now - started) / 5000);
                            marker.setPosition({
                                lat: from.lat + (next.lat - from.lat) * t,
                                lng: from.lng + (next.lng - from.lng) * t,
                            });
                            if (t < 1) {
                                this.motoAnims[id] = requestAnimationFrame(step);
                                return;
                            }
                            delete this.motoAnims[id];
                        };
                        this.motoAnims[id] = requestAnimationFrame(step);
                    }
                }
                if (follow && selected) {
                    bounds?.extend(next);
                    points += 1;
                }
            });
            Object.keys(this.motoMarkers).forEach((key) => {
                const id = Number(key);
                if (seen[id]) {
                    return;
                }
                if (this.motoAnims[id]) {
                    cancelAnimationFrame(this.motoAnims[id]);
                    delete this.motoAnims[id];
                }
                this.motoMarkers[id].setMap(null);
                delete this.motoMarkers[id];
            });
            if (follow && bounds && points > 0) {
                this.map.fitBounds(bounds, 48);
                google.maps.event.addListenerOnce(this.map, 'idle', () => {
                    if ((this.map.getZoom() || 0) > 16) {
                        this.map.setZoom(16);
                    }
                });
                return;
            }
            if (camera === 'all') {
                this.fitMotosAndSites();
            }
        },
    };
}
