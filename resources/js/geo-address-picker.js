import { pickArea, classifyArea } from './colombian-area';
import { fetchCaliComunas, locateCaliComuna, paintCaliLayer, caliComunasUrl } from './cali-comunas';

window.__geoMapsLoader = window.__geoMapsLoader || null;

function loadGoogleMaps(apiKey) {
    if (window.google?.maps?.importLibrary) {
        return Promise.resolve();
    }
    if (window.__geoMapsLoader) {
        return window.__geoMapsLoader;
    }
    window.__geoMapsLoader = new Promise((resolve, reject) => {
        const cb = `__geoMapsReady_${Date.now()}`;
        window[cb] = () => {
            resolve();
            delete window[cb];
        };
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&v=weekly&loading=async&callback=${cb}`;
        script.async = true;
        script.onerror = () => reject(new Error('No se pudo cargar Google Maps'));
        document.head.appendChild(script);
    });
    return window.__geoMapsLoader;
}

function componentText(component) {
    if (!component) {
        return '';
    }
    return component.longText || component.long_name || component.shortText || component.short_name || '';
}

function parsePlaceComponents(components) {
    const get = (type) => {
        const c = (components || []).find((x) => (x.types || []).includes(type));
        return componentText(c);
    };
    const route = get('route');
    const number = get('street_number');
    const address = [route, number].filter(Boolean).join(' ') || '';
    const locality = get('locality') || get('administrative_area_level_2') || '';
    const sublocality = get('sublocality') || get('sublocality_level_1') || '';
    const city = locality || (classifyArea(sublocality, '') === 'none' ? sublocality : '') || '';
    const department = get('administrative_area_level_1') || '';
    const area = pickArea(components, city);

    return { address, city, department, area: area.name, areaKind: area.kind };
}

function latLngParts(location) {
    if (!location) {
        return null;
    }
    const lat = typeof location.lat === 'function' ? location.lat() : location.lat;
    const lng = typeof location.lng === 'function' ? location.lng() : location.lng;
    if (lat == null || lng == null) {
        return null;
    }
    return { lat: Number(lat), lng: Number(lng) };
}

function placeDisplayName(place) {
    if (!place) {
        return '';
    }
    if (typeof place.displayName === 'string') {
        return place.displayName;
    }
    return place.displayName?.text || '';
}

window.geoAddressPicker = function geoAddressPicker(config) {
    return {
        address: config.address ?? '',
        city: config.city ?? '',
        department: config.department ?? '',
        latitude: config.latitude !== null && config.latitude !== '' ? Number(config.latitude) : null,
        longitude: config.longitude !== null && config.longitude !== '' ? Number(config.longitude) : null,
        maps: config.maps || {},
        open: false,
        map: null,
        marker: null,
        searchQuery: '',
        suggestions: [],
        searchTimer: null,
        searching: false,
        sessionToken: null,
        draftLat: null,
        draftLng: null,
        draftAddress: '',
        draftCity: '',
        draftDepartment: '',
        draftArea: '',
        draftAreaKind: 'none',
        draftLabel: '',
        draftComuna: null,
        caliGeo: null,

        async openMap() {
            this.open = true;
            this.draftLat = this.latitude;
            this.draftLng = this.longitude;
            this.draftAddress = this.address || '';
            this.draftCity = this.city || '';
            this.draftDepartment = this.department || '';
            this.draftArea = '';
            this.draftAreaKind = 'none';
            this.draftComuna = null;
            this.draftLabel = this.address || '';
            this.searchQuery = '';
            this.suggestions = [];
            await this.$nextTick();
            if (!this.maps.apiKey) {
                return;
            }
            try {
                await loadGoogleMaps(this.maps.apiKey);
                await google.maps.importLibrary('maps');
                await google.maps.importLibrary('places');
                this.sessionToken = new google.maps.places.AutocompleteSessionToken();
                this.initMap();
            } catch (e) {
                console.error(e);
            }
        },

        closeMap() {
            this.open = false;
            this.suggestions = [];
            if (this.searchTimer) {
                clearTimeout(this.searchTimer);
                this.searchTimer = null;
            }
        },

        initMap() {
            const el = this.$refs.map;
            if (!el || !window.google?.maps) {
                return;
            }

            const hasCoords = this.draftLat !== null && this.draftLng !== null;
            const center = hasCoords
                ? { lat: Number(this.draftLat), lng: Number(this.draftLng) }
                : { lat: Number(this.maps.center.lat), lng: Number(this.maps.center.lng) };

            this.map = new google.maps.Map(el, {
                center,
                zoom: hasCoords ? 16 : Number(this.maps.zoom || 6),
                mapTypeId: google.maps.MapTypeId.HYBRID,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false,
            });

            this.marker = new google.maps.Marker({
                map: this.map,
                position: center,
                draggable: true,
            });

            this.marker.addListener('dragend', () => {
                const pos = this.marker.getPosition();
                this.setDraftFromLatLng(pos.lat(), pos.lng());
            });

            this.map.addListener('click', (e) => {
                this.marker.setPosition(e.latLng);
                this.setDraftFromLatLng(e.latLng.lat(), e.latLng.lng());
            });

            if (hasCoords) {
                this.draftLabel = this.address || `${this.draftLat}, ${this.draftLng}`;
            }

            this.loadCaliLayer();
        },

        onSearchInput() {
            if (this.searchTimer) {
                clearTimeout(this.searchTimer);
            }
            const q = (this.searchQuery || '').trim();
            if (q.length < 2) {
                this.suggestions = [];
                return;
            }
            this.searchTimer = setTimeout(() => this.fetchSuggestions(q), 250);
        },

        async fetchSuggestions(query) {
            if (!window.google?.maps?.places?.AutocompleteSuggestion) {
                this.suggestions = [];
                return;
            }
            this.searching = true;
            try {
                if (!this.sessionToken) {
                    this.sessionToken = new google.maps.places.AutocompleteSessionToken();
                }
                const request = {
                    input: query,
                    includedRegionCodes: ['co'],
                    language: 'es',
                    sessionToken: this.sessionToken,
                };
                const { suggestions } = await google.maps.places.AutocompleteSuggestion.fetchAutocompleteSuggestions(request);
                this.suggestions = (suggestions || [])
                    .map((row) => row.placePrediction)
                    .filter(Boolean)
                    .slice(0, 6)
                    .map((prediction) => ({
                        prediction,
                        label: prediction.text?.text
                            || prediction.mainText?.text
                            || prediction.toString?.()
                            || 'Lugar',
                        secondary: prediction.secondaryText?.text || '',
                    }));
            } catch (e) {
                console.error(e);
                this.suggestions = [];
            } finally {
                this.searching = false;
            }
        },

        async selectSuggestion(item) {
            if (!item?.prediction) {
                return;
            }
            this.suggestions = [];
            try {
                const place = item.prediction.toPlace();
                await place.fetchFields({
                    fields: ['displayName', 'formattedAddress', 'location', 'addressComponents'],
                });
                this.sessionToken = new google.maps.places.AutocompleteSessionToken();
                const coords = latLngParts(place.location);
                if (!coords) {
                    return;
                }
                this.map.setCenter(coords);
                this.map.setZoom(16);
                this.marker.setPosition(coords);
                const parsed = parsePlaceComponents(place.addressComponents || []);
                const name = placeDisplayName(place);
                this.draftLat = coords.lat;
                this.draftLng = coords.lng;
                this.draftAddress = parsed.address || place.formattedAddress || name || item.label || '';
                this.draftCity = parsed.city;
                this.draftDepartment = parsed.department;
                this.draftArea = parsed.area;
                this.draftAreaKind = parsed.areaKind;
                this.draftLabel = place.formattedAddress || name || this.draftAddress;
                this.searchQuery = this.draftLabel;
                this.applyCaliComuna(coords.lat, coords.lng);
            } catch (e) {
                console.error(e);
            }
        },

        async loadCaliLayer() {
            try {
                this.caliGeo = await fetchCaliComunas(caliComunasUrl());
                if (this.caliGeo && this.map) {
                    this.applyCaliComuna(this.draftLat, this.draftLng);
                }
            } catch (e) {
                console.error(e);
            }
        },

        applyCaliComuna(lat, lng) {
            if (!this.caliGeo || lat == null || lng == null) {
                return;
            }
            const hit = locateCaliComuna(Number(lat), Number(lng), this.caliGeo);
            this.draftComuna = hit;
            if (hit) {
                this.draftArea = hit.name;
                this.draftAreaKind = 'comuna';
            }
            if (this.map) {
                paintCaliLayer(this.map, this.caliGeo, hit?.code || '');
            }
        },

        setDraftFromLatLng(lat, lng) {
            this.draftLat = lat;
            this.draftLng = lng;
            this.draftLabel = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
            this.applyCaliComuna(lat, lng);
            const geocoder = new google.maps.Geocoder();
            geocoder.geocode({ location: { lat, lng } }, (results, status) => {
                if (status !== 'OK' || !results?.[0]) {
                    return;
                }
                const parsed = parsePlaceComponents(results[0].address_components);
                this.draftAddress = parsed.address || results[0].formatted_address || this.draftAddress;
                this.draftCity = parsed.city || this.draftCity;
                this.draftDepartment = parsed.department || this.draftDepartment;
                this.draftArea = parsed.area;
                this.draftAreaKind = parsed.areaKind;
                this.draftLabel = results[0].formatted_address || this.draftLabel;
                this.searchQuery = this.draftLabel;
                this.applyCaliComuna(lat, lng);
            });
        },

        confirmPlace() {
            if (this.draftLat === null || this.draftLng === null) {
                return;
            }
            this.latitude = this.draftLat;
            this.longitude = this.draftLng;
            if (this.draftAddress) {
                this.address = this.draftAddress;
            }
            if (this.draftCity) {
                this.city = this.draftCity;
            }
            if (this.draftDepartment) {
                this.department = this.draftDepartment;
            }
            this.$dispatch('geo-place', {
                city: this.city,
                department: this.department,
                address: this.address,
                area: this.draftArea,
                areaKind: this.draftAreaKind,
                idesc: Boolean(this.draftComuna),
            });
            this.closeMap();
        },
    };
};
