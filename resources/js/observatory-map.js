export function observatoryMap(cfg) {
    const layerUrl = '/geo/cali-comunas.geojson';

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
                zoomControl: true,
                zoomControlOptions: {
                    position: google.maps.ControlPosition.RIGHT_BOTTOM,
                },
            });

            const info = new google.maps.InfoWindow();

            sites.forEach((site) => {
                const pos = { lat: Number(site.lat), lng: Number(site.lng) };
                const pinIcon = {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: site.open_count > 0 ? 8 : 6,
                    fillColor: site.color || '#94a3b8',
                    fillOpacity: 0.9,
                    strokeColor: '#0f172a',
                    strokeWeight: 1,
                };
                const marker = new google.maps.Marker({
                    position: pos,
                    map: this.map,
                    title: site.name,
                    zIndex: 1,
                    icon: pinIcon,
                    kindSlugs: site.type_slugs || (site.kind_slug ? [site.kind_slug] : []),
                    pinIcon,
                    heatWeight: site.open_count > 0 ? Number(site.heat_weight || 1) : 0,
                    riskWeight: site.open_count > 0 ? Number(site.risk_weight || 1) : 0,
                });
                marker.addListener('click', () => {
                    const mix = (site.summary || [])
                        .map((row) => `${this.esc(row.name)} (${row.count})`)
                        .join(' · ');
                    const link = site.show_url
                        ? `<a href="${this.esc(site.show_url)}">Ver evento</a>`
                        : '<span>Sin eventos</span>';
                    info.setContent(
                        `<div style="color:#0f172a;font:13px/1.4 sans-serif;max-width:240px">`
                        + `<strong>${this.esc(site.name)}</strong><br>`
                        + `${site.client ? this.esc(site.client) + '<br>' : ''}`
                        + `${mix || this.esc(site.status_label)}`
                        + `<br>${link}</div>`,
                    );
                    info.open(this.map, marker);
                });
                this.markers.push(marker);
            });

            points.forEach((point) => {
                const pos = { lat: Number(point.lat), lng: Number(point.lng) };
                const pinIcon = {
                    path: google.maps.SymbolPath.BACKWARD_CLOSED_ARROW,
                    scale: 5,
                    fillColor: point.color || '#94a3b8',
                    fillOpacity: 1,
                    strokeColor: '#ffffff',
                    strokeWeight: 1,
                };
                const marker = new google.maps.Marker({
                    position: pos,
                    map: this.map,
                    title: point.kind || point.title,
                    zIndex: 2,
                    icon: pinIcon,
                    kindSlugs: point.kind_slug ? [point.kind_slug] : [],
                    pinIcon,
                    heatWeight: point.open ? 1 : 0,
                    riskWeight: point.open ? Number(point.level || 1) : 0,
                });
                marker.addListener('click', () => {
                    const link = point.show_url
                        ? `<a href="${this.esc(point.show_url)}">Ver evento</a>`
                        : '';
                    info.setContent(
                        `<div style="color:#0f172a;font:13px/1.4 sans-serif;max-width:220px">`
                        + `<strong>${this.esc(point.title)}</strong><br>`
                        + `<span style="color:${this.esc(point.color || '#334155')}">${this.esc(point.kind)}</span>`
                        + ` · ${this.esc(point.status_label)}`
                        + (link ? `<br>${link}` : '')
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
            this.markers.forEach((marker) => {
                const slugs = marker.kindSlugs || [];
                const ok = !this.typeFilter || slugs.length === 0 || slugs.includes(this.typeFilter);
                const weight = risk ? marker.riskWeight : marker.heatWeight;
                const show = ok && (pins || weight > 0);
                marker.setMap(show ? this.map : null);
                if (!show) {
                    return;
                }
                marker.setIcon(pins ? marker.pinIcon : this.heatIcon(weight, risk));
            });
        },
    };
}
