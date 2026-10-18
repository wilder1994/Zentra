import { createClusterer } from './map-cluster';
import { dropIcon, dropSvg, GLYPHS, MAP_STYLES } from './map-pins';
import { shareButton } from './map-share';

/** Tono del grupo según el riesgo abierto (los colores del catálogo los define cada cliente). */
function riskTone(open, weight) {
    if (!open) {
        return 'slate';
    }
    const w = Number(weight) || 1;

    return w >= 3 ? 'red' : (w >= 2 ? 'orange' : 'amber');
}

export function observatoryMap(cfg) {
    const layerUrl = '/geo/cali-comunas.geojson';
    let clusterer = null;

    return {
        mode: 'pins',
        mapType: 'satellite',
        typeFilter: '',
        comuna: cfg.comuna || '',
        comunaQ: '',
        comunaOpen: false,
        comunas: cfg.comunas || [],
        map: null,
        markers: [],

        init() {
            window.initObservatoryMap = () => this.draw();
            if (window.google?.maps || window.__observatoryMapReady) {
                this.draw();
            }
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
            clusterer = createClusterer(this.map, {
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
                    title: site.name,
                    zIndex: 1,
                    icon: pinIcon,
                    kindSlugs: site.type_slugs || (site.kind_slug ? [site.kind_slug] : []),
                    pinIcon,
                    heatWeight: open ? Number(site.heat_weight || 1) : 0,
                    riskWeight: open ? Number(site.risk_weight || 1) : 0,
                });
                marker.zentraTone = riskTone(open, site.risk_weight);
                marker.addListener('click', () => {
                    const mix = (site.summary || [])
                        .map((row) => `${this.esc(row.name)} (${row.count})`)
                        .join(' · ');
                    const link = site.show_url
                        ? `<a href="${this.esc(site.show_url)}">Ver evento</a>`
                        : '<span>Sin eventos</span>';
                    const share = shareButton({ title: site.name, subtitle: site.client || '', address: site.address || '', lat: pos.lat, lng: pos.lng }, { label: true });
                    info.setContent(
                        `<div class="zmap-share-light" style="color:#0f172a;font:13px/1.4 sans-serif;max-width:240px">`
                        + `<strong>${this.esc(site.name)}</strong><br>`
                        + `${site.client ? this.esc(site.client) + '<br>' : ''}`
                        + `${site.address ? '<span style="color:#475569">' + this.esc(site.address) + '</span><br>' : ''}`
                        + `${mix || this.esc(site.status_label)}`
                        + `<div style="margin-top:6px;display:flex;align-items:center;justify-content:space-between;gap:8px">${link}${share}</div></div>`,
                    );
                    info.open(this.map, marker);
                });
                this.markers.push(marker);
            });

            points.forEach((point) => {
                const pos = { lat: Number(point.lat), lng: Number(point.lng) };
                const pinIcon = dropIcon(dropSvg(point.color || '#94a3b8', GLYPHS.alert, { stroke: '#ffffff' }), 22);
                const marker = new google.maps.Marker({
                    position: pos,
                    map: null,
                    title: point.kind || point.title,
                    zIndex: 2,
                    icon: pinIcon,
                    kindSlugs: point.kind_slug ? [point.kind_slug] : [],
                    pinIcon,
                    heatWeight: point.open ? 1 : 0,
                    riskWeight: point.open ? Number(point.level || 1) : 0,
                });
                marker.zentraTone = riskTone(point.open, point.level);
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
                this.markers.push(marker);
            });

            this.applyMode();
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
            this.map.data.setStyle((feature) => {
                const code = String(feature.getProperty('code') || '');
                const selected = this.comuna !== '' && this.comuna !== 'fuera' && code === this.comuna;
                const visible = this.comuna !== 'fuera' && (!this.comuna || selected);

                return {
                    strokeColor: selected ? '#f8fafc' : '#7dd3fc',
                    strokeOpacity: selected ? 1 : 0.85,
                    strokeWeight: selected ? 2.8 : 1.4,
                    fillColor: '#38bdf8',
                    fillOpacity: selected ? 0.4 : 0.16,
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

        setMode(mode) {
            this.mode = mode;
            this.applyMode();
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

        heatIcon(weight, risk) {
            const w = Math.max(1, Number(weight) || 1);
            return {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 10 + Math.min(20, w * 4),
                fillColor: risk ? '#f97316' : '#38bdf8',
                fillOpacity: 0.38,
                strokeColor: risk ? '#fdba74' : '#e0f2fe',
                strokeWeight: 1,
            };
        },

        applyMode() {
            const pins = this.mode === 'pins';
            const risk = this.mode === 'heat_risk';
            const grouped = [];
            clusterer?.clearMarkers();
            this.markers.forEach((marker) => {
                const slugs = marker.kindSlugs || [];
                const ok = !this.typeFilter || slugs.length === 0 || slugs.includes(this.typeFilter);
                const weight = risk ? marker.riskWeight : marker.heatWeight;
                const show = ok && (pins || weight > 0);
                if (!show) {
                    marker.setMap(null);
                    return;
                }
                marker.setIcon(pins ? marker.pinIcon : this.heatIcon(weight, risk));
                // Pines: agrupados por zoom. Calor: círculos sueltos (el tamaño es el dato).
                if (pins && clusterer) {
                    grouped.push(marker);
                } else {
                    marker.setMap(this.map);
                }
            });
            clusterer?.addMarkers(grouped);
        },
    };
}
