window.__observatoryIntakeMapsLoader = window.__observatoryIntakeMapsLoader || null;

function loadGoogleMaps(apiKey) {
    if (window.google?.maps) {
        return Promise.resolve();
    }
    if (window.__observatoryIntakeMapsLoader) {
        return window.__observatoryIntakeMapsLoader;
    }
    window.__observatoryIntakeMapsLoader = new Promise((resolve, reject) => {
        const cb = `__observatoryIntakeMapsReady_${Date.now()}`;
        window[cb] = () => {
            resolve();
            delete window[cb];
        };
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&callback=${cb}`;
        script.async = true;
        script.onerror = () => reject(new Error('No se pudo cargar Google Maps'));
        document.head.appendChild(script);
    });
    return window.__observatoryIntakeMapsLoader;
}

export function observatoryPhotos(cfg = {}) {
    return {
        anonymous: Boolean(cfg.anonymous),
        previews: [null, null, null],
        setPhoto(event, index) {
            const file = event.target.files?.[0];
            if (this.previews[index]) {
                URL.revokeObjectURL(this.previews[index]);
            }
            const next = [...this.previews];
            next[index] = file ? URL.createObjectURL(file) : null;
            this.previews = next;
        },
    };
}

export function observatoryIntake(cfg) {
    return {
        step: cfg.installationId ? 2 : 1,
        query: '',
        installations: [],
        searched: false,
        installationId: cfg.installationId || '',
        installationName: cfg.installationName || '',
        kind: cfg.kind || '',
        anonymous: Boolean(cfg.anonymous),
        role: cfg.role || '',
        reporterName: cfg.reporterName || '',
        reporterPhone: cfg.reporterPhone || '',
        remember: false,
        remembered: false,
        storageKey: cfg.storageKey || '',
        latitude: cfg.latitude || '',
        longitude: cfg.longitude || '',
        siteLat: cfg.siteLat ?? null,
        siteLng: cfg.siteLng ?? null,
        hasPin: false,
        mapsKey: cfg.mapsKey || '',
        map: null,
        marker: null,
        previews: [null, null, null],

        init() {
            this.restoreIdentity();
            if (this.step === 2) {
                this.goStep(2);
            }
        },

        restoreIdentity() {
            if (!this.storageKey || typeof localStorage === 'undefined') {
                return;
            }
            try {
                const raw = localStorage.getItem(this.storageKey);
                if (!raw) {
                    return;
                }
                const data = JSON.parse(raw);
                if (!data?.role) {
                    return;
                }
                if (!this.role) {
                    this.role = String(data.role);
                }
                if (data.role !== 'alumno') {
                    if (!this.reporterName && data.name) {
                        this.reporterName = String(data.name);
                    }
                    if (!this.reporterPhone && data.phone) {
                        this.reporterPhone = String(data.phone);
                    }
                }
                this.remembered = true;
                this.remember = true;
            } catch {
                // storage corrupto o privado
            }
        },

        persistIdentity() {
            if (!this.storageKey || typeof localStorage === 'undefined') {
                return;
            }
            if (!this.remember || !this.role) {
                return;
            }
            const payload = { role: this.role };
            if (this.role !== 'alumno') {
                payload.name = this.reporterName || '';
                payload.phone = this.reporterPhone || '';
            }
            try {
                localStorage.setItem(this.storageKey, JSON.stringify(payload));
            } catch {
                // storage lleno o privado
            }
        },

        forget() {
            this.remembered = false;
            this.remember = false;
            if (this.storageKey && typeof localStorage !== 'undefined') {
                try {
                    localStorage.removeItem(this.storageKey);
                } catch {
                    // ignore
                }
            }
        },

        async search() {
            const q = this.query.trim();
            if (q.length < 2) {
                this.installations = [];
                this.searched = false;
                return;
            }
            const res = await fetch(cfg.installationsUrl + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
            const data = await res.json();
            this.installations = data.installations || [];
            this.searched = true;
        },

        pick(row) {
            this.installationId = String(row.id);
            this.installationName = row.name;
            this.siteLat = row.lat ?? null;
            this.siteLng = row.lng ?? null;
            this.latitude = row.lat != null ? String(row.lat) : '';
            this.longitude = row.lng != null ? String(row.lng) : '';
            this.installations = [];
            this.query = row.name;
        },

        setPhoto(event, index) {
            const file = event.target.files?.[0];
            if (this.previews[index]) {
                URL.revokeObjectURL(this.previews[index]);
            }
            const next = [...this.previews];
            next[index] = file ? URL.createObjectURL(file) : null;
            this.previews = next;
        },

        goStep(n) {
            this.step = n;
            if (n === 2) {
                this.$nextTick(() => {
                    setTimeout(() => this.initMap(), 50);
                });
            }
        },

        savePos(ll) {
            this.latitude = String(ll.lat());
            this.longitude = String(ll.lng());
        },

        async initMap() {
            const lat = Number(this.latitude || this.siteLat);
            const lng = Number(this.longitude || this.siteLng);
            this.hasPin = Number.isFinite(lat) && Number.isFinite(lng);
            if (!this.hasPin || !this.mapsKey) {
                return;
            }

            try {
                await loadGoogleMaps(this.mapsKey);
            } catch {
                this.hasPin = false;
                return;
            }

            await this.$nextTick();
            const el = this.$refs.pinMap;
            if (!el || !window.google?.maps) {
                return;
            }

            const pos = { lat, lng };
            if (!this.map) {
                this.map = new google.maps.Map(el, {
                    center: pos,
                    zoom: 18,
                    mapTypeId: google.maps.MapTypeId.HYBRID,
                    streetViewControl: false,
                    mapTypeControl: false,
                });
                this.marker = new google.maps.Marker({
                    position: pos,
                    map: this.map,
                    draggable: true,
                    title: 'Lugar de la novedad',
                });
                this.marker.addListener('dragend', () => this.savePos(this.marker.getPosition()));
                this.map.addListener('click', (e) => {
                    this.marker.setPosition(e.latLng);
                    this.savePos(e.latLng);
                });
            } else {
                this.marker.setPosition(pos);
                this.map.setCenter(pos);
            }

            google.maps.event.trigger(this.map, 'resize');
            this.map.setCenter(pos);
            this.savePos(this.marker.getPosition());
        },
    };
}
