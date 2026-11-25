const COMMUNE_CITIES = new Set([
    'armenia',
    'barranquilla',
    'bucaramanga',
    'cali',
    'cartagena',
    'cartagena de indias',
    'cucuta',
    'ibague',
    'manizales',
    'medellin',
    'monteria',
    'neiva',
    'pasto',
    'pereira',
    'popayan',
    'santa marta',
    'santiago de cali',
    'sincelejo',
    'valledupar',
    'villavicencio',
]);

const LABELS = {
    comuna: 'Comuna',
    localidad: 'Localidad',
    vereda: 'Vereda',
    corregimiento: 'Corregimiento',
    none: 'Área',
};

export function normalizePlace(value) {
    return String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .trim();
}

export function classifyArea(name, city) {
    const n = normalizePlace(name);
    const c = normalizePlace(city);

    if (!n) {
        return 'none';
    }
    if (n.includes('vereda')) {
        return 'vereda';
    }
    if (n.includes('corregimiento')) {
        return 'corregimiento';
    }
    if (n.includes('localidad')) {
        return 'localidad';
    }
    if (n.includes('comuna')) {
        return 'comuna';
    }
    if (c.startsWith('bogota') || c === 'distrito capital') {
        return 'localidad';
    }
    if (COMMUNE_CITIES.has(c)) {
        return 'comuna';
    }

    return 'none';
}

export function areaKindLabel(kind) {
    return LABELS[kind] || LABELS.none;
}

export function pickArea(components, city) {
    const get = (type) => {
        const row = (components || []).find((item) => item.types.includes(type));
        return row ? row.long_name : '';
    };
    const cityNorm = normalizePlace(city);
    const ranked = [
        get('administrative_area_level_3'),
        get('sublocality_level_1'),
        get('sublocality'),
    ].filter((name) => name && normalizePlace(name) !== cityNorm);

    for (const name of ranked) {
        const kind = classifyArea(name, city);
        if (kind !== 'none') {
            return { name, kind };
        }
    }

    return { name: '', kind: 'none' };
}

export function installationAreaFields(config) {
    const veredas = Array.isArray(config.veredas) ? config.veredas : [];
    const initial = String(config.commune || '');
    const locked = Boolean(config.idescLocked);
    const known = veredas.find((row) => row.label === initial || row.name === initial);

    return {
        commune: locked ? initial : (known ? known.label : ''),
        city: config.city || '',
        veredas,
        idescLocked: locked,
        areaKind: locked ? 'comuna' : 'vereda',
        get areaLabel() {
            return this.idescLocked ? 'Comuna' : 'Zona rural';
        },
        get areaHint() {
            if (this.idescLocked) {
                return 'La pone el pin (comuna IDESC de Cali). No se edita.';
            }

            return 'El pin no cae en una comuna de Cali. Elige la vereda.';
        },
        applyPlace(detail) {
            if (detail.city) {
                this.city = detail.city;
            }
            if (detail.idesc) {
                this.idescLocked = true;
                this.commune = detail.area || this.commune;
                this.areaKind = 'comuna';
                return;
            }
            this.idescLocked = false;
            this.areaKind = 'vereda';
            const kept = this.veredas.find((row) => row.label === this.commune);
            if (kept) {
                return;
            }
            const fromMap = this.veredas.find((row) => row.label === detail.area || row.name === detail.area);
            this.commune = fromMap ? fromMap.label : '';
        },
        refreshKind() {
            this.areaKind = this.idescLocked ? 'comuna' : 'vereda';
        },
    };
}
