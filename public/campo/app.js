document.querySelectorAll('[data-pw-toggle]').forEach((btn) => {
    const input = btn.parentElement?.querySelector('input');
    if (!input) return;
    btn.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        btn.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
        btn.querySelector('.pw-eye')?.classList.toggle('hidden', show);
        btn.querySelector('.pw-eye-off')?.classList.toggle('hidden', !show);
    });
});

const statusEl = document.getElementById('status');
let pingTimer = null;
let pingWatchId = null;
let pingBusy = false;
let nativePing = false;
let lastPingSentAt = 0;
let catalog = [];
let intake = null;
let currentModule = null;
let currentReview = null;
let activity = null;
let lastGeo = null;
let sites = [];
let reviewClient = null;
let reviewPost = null;
let reviewGuard = null;
let blobs = { odo: null, self: null, odoEnd: null, selfEnd: null, guard: null };
let searchTimers = {};
let moduleReturn = 'home';
let reviewDraftLogs = [];
let modulePhotos = {};
let cropSession = null;
let postsCache = [];
let guardsCache = [];
let syncing = false;
let closeQueued = false;
/** Hasta recuperar lo guardado en el teléfono no se autoguarda (evita pisarlo al arrancar). */
let draftsReady = false;
let reviewEventId = null;
let closeEventId = null;
let logEventId = null;
let obsSite = null;
let obsKinds = {};
let obsKindsByClient = {};
let obsSitesCache = [];
let obsSearchTimer = null;
let obsMapsKey = '';
let obsMap = null;
let obsMarker = null;
let obsPin = null;
let obsMapsPromise = null;
const APP_BUILD = 23;
let trainingsMonth = [];
let topicPick = null;
let trainingTimer = null;
let trainingDup = '';
let trainingTick = null;
let trainingWarned = false;
let sessionSaveTimer = null;

async function withBusy(btn, busyLabel, fn) {
    if (!btn || btn.dataset.busy === '1') return;
    btn.dataset.busy = '1';
    btn.disabled = true;
    const original = btn.textContent;
    btn.textContent = busyLabel;
    setStatus(busyLabel);
    try {
        await fn();
    } catch (e) {
        setStatus(e.message, false);
    } finally {
        btn.dataset.busy = '';
        btn.disabled = false;
        btn.textContent = original;
    }
}

function isNativeApp() {
    return Boolean(window.Capacitor?.isNativePlatform?.());
}

function deviceId() {
    let id = localStorage.getItem('zentra_device_id');
    if (!id || id.length < 8) {
        id = window.crypto?.randomUUID?.()
            || `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
        localStorage.setItem('zentra_device_id', id);
    }
    return id;
}

function showDeviceWait(message) {
    const box = document.getElementById('device-wait');
    const msg = document.getElementById('device-wait-msg');
    if (msg) msg.textContent = message || 'La central debe aprobar el cambio de celular.';
    box?.classList.remove('hidden');
    setStatus(message || 'Cambio de celular pendiente de aprobación.', false);
}

function hideDeviceWait() {
    document.getElementById('device-wait')?.classList.add('hidden');
}

async function deviceLabel() {
    let model = '';
    try {
        const info = await navigator.userAgentData?.getHighEntropyValues?.(['model']);
        model = info?.model || '';
    } catch (_) {}
    if (!model) {
        const m = (navigator.userAgent || '').match(/Android [\d.]+; ([^;)]+)/);
        if (m && m[1] !== 'K') model = m[1].replace(/ Build.*/, '');
    }
    return `${model || 'Celular'} · ${isNativeApp() ? 'APK' : 'PWA'}`.slice(0, 150);
}

function inferApi() {
    if (isNativeApp()) {
        return 'https://zentra.wcodex.cloud/api';
    }
    const host = location.hostname;
    const port = location.port;
    if (port === '8085') {
        return `${location.protocol}//${host}:8084/api`;
    }
    if (/\.ts\.net$/i.test(host)) {
        return `${location.origin.replace(/\/$/, '')}/api`;
    }
    if (/zentra_supervision/i.test(host)) {
        return `${location.protocol}//${host.replace(/zentra_supervision/i, 'zentra')}/api`;
    }
    return `${location.origin.replace(/\/$/, '')}/api`;
}

function apiBase() {
    return inferApi().replace(/\/$/, '');
}

function token() {
    return localStorage.getItem('token');
}

function offlineReady() {
    return Boolean(window.ZentraOffline && typeof ZentraOffline.setUser === 'function');
}

function setStatus(text, ok = true) {
    statusEl.textContent = text;
    statusEl.className = ok ? 'ok' : 'err';
    const wrap = document.getElementById('feedback');
    const box = wrap?.querySelector('p');
    if (!wrap || !box || !text) return;
    box.textContent = String(text);
    wrap.className = ok ? 'ok show' : 'err show';
    clearTimeout(setStatus.timer);
    setStatus.timer = setTimeout(() => wrap.classList.remove('show'), 2800);
}

function show(id) {
    ['boot', 'login', 'change-password', 'open-shift', 'ops', 'close-shift'].forEach((key) => {
        document.getElementById(key)?.classList.toggle('hidden', key !== id);
    });
    if (id === 'close-shift') rememberScreen('close-shift');
}

/** Pantalla en la que quedó el supervisor; al volver a la app se reabre ahí. */
function rememberScreen(screen) {
    if (!draftsReady || !offlineReady() || !ZentraOffline.currentUserId?.()) return;
    ZentraOffline.metaSet('uiScreen', screen).catch(() => {});
}

async function api(path, options = {}) {
    const authToken = options.authToken || token();
    const headers = Object.assign({ Accept: 'application/json' }, options.headers || {});
    if (!(options.body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
    }
    if (authToken) headers.Authorization = `Bearer ${authToken}`;
    const fetchOpts = Object.assign({}, options);
    delete fetchOpts.authToken;
    let res;
    try {
        res = await fetch(`${apiBase()}${path}`, Object.assign({}, fetchOpts, { headers }));
    } catch (err) {
        const offline = new Error('Sin conexión. El registro queda en el teléfono.');
        offline.offline = true;
        throw offline;
    }
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const parts = data.errors
            ? Object.values(data.errors).flat().filter(Boolean)
            : [];
        const httpHint = {
            401: 'Usuario o contraseña no coinciden.',
            403: 'No tienes permiso para esta acción.',
            419: 'La sesión expiró. Recarga e intenta de nuevo.',
            422: 'Revise los datos e inténtelo de nuevo.',
            429: 'Demasiados intentos. Espere un momento.',
            500: 'Error del servidor. Intenta de nuevo.',
        };
        const text = parts.join(' ')
            || data.message
            || data.login?.[0]
            || data.email?.[0]
            || httpHint[res.status]
            || 'No se pudo completar. Intenta de nuevo.';
        const error = new Error(String(text).replace(/\bvalidation\.\w+\b/g, 'Revise los datos e inténtelo de nuevo.'));
        error.status = res.status;
        throw error;
    }
    return data;
}

function applyPack(pack) {
    if (!pack) return;
    sites = pack.sites || sites;
    postsCache = pack.posts || postsCache;
    guardsCache = pack.guards || guardsCache;
    if (pack.modules?.length) catalog = pack.modules;
    if (Array.isArray(pack.trainings_month)) trainingsMonth = pack.trainings_month;
    if (pack.observatory_sites) obsSitesCache = pack.observatory_sites;
    if (pack.observatory_kinds_by_client) obsKindsByClient = pack.observatory_kinds_by_client;
    if (pack.observatory_kinds) {
        obsKinds = pack.observatory_kinds;
        fillObsKinds(obsKinds);
    }
    const select = document.getElementById('mod-client');
    if (select) {
        const current = select.value;
        select.innerHTML = (sites || []).length
            ? '<option value="">Seleccione…</option>' + sites.map((s) => `<option value="${s.id}">${s.name}</option>`).join('')
            : '<option value="">Sin clientes con Supervisión</option>';
        select.value = current;
    }
    renderHub();
}

let packRefreshedAt = 0;

async function refreshOfflinePack() {
    const pack = await api(`/supervision/offline-pack?app_build=${APP_BUILD}`);
    packRefreshedAt = Date.now();
    await ZentraOffline.metaSet('pack', pack);
    applyPack(pack);
    return pack;
}

/** La central puede habilitar otra zona en pleno turno: al abrir revista o módulo se piden los clientes de nuevo. */
async function refreshSitesIfStale() {
    if (!navigator.onLine || !token() || Date.now() - packRefreshedAt < 20000) return false;
    try {
        await refreshOfflinePack();
        return true;
    } catch (e) {
        return false;
    }
}

async function restorePack() {
    const pack = await ZentraOffline.metaGet('pack');
    applyPack(pack);
    closeQueued = Boolean(await ZentraOffline.metaGet('closeQueued'));
    return pack;
}

async function clearOwnCloseLock() {
    closeQueued = false;
    try {
        await ZentraOffline.metaSet('closeQueued', false);
    } catch (e) {
        // sin usuario
    }
}

const OUTBOX_LABELS = { review: 'Revista', log: 'Registro', observatory: 'Observatorio', close: 'Cierre de turno' };

function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

async function renderRejected() {
    const box = document.getElementById('sync-rejected');
    if (!box) return;
    const jobs = await ZentraOffline.rejectedJobs();
    if (!jobs.length) {
        box.classList.add('hidden');
        box.innerHTML = '';
        return;
    }
    box.classList.remove('hidden');
    const items = jobs.map((job) => {
        const label = OUTBOX_LABELS[job.row.type] || 'Registro';
        return `<li><strong>${esc(label)}</strong>: ${esc(job.row.rejected.message)}</li>`;
    }).join('');
    box.innerHTML = `<p><strong>${jobs.length} registro(s) no aceptado(s) por el servidor.</strong> Siguen guardados en el teléfono.</p>`
        + `<ul>${items}</ul>`
        + '<div class="row"><button type="button" class="secondary" id="btn-rejected-retry">Reintentar</button>'
        + '<button type="button" class="ghost" id="btn-rejected-discard">Descartar</button></div>';
    document.getElementById('btn-rejected-retry').onclick = async () => {
        await ZentraOffline.retryRejected();
        await updateSyncBar();
        flushOutbox();
    };
    document.getElementById('btn-rejected-discard').onclick = async () => {
        if (!window.confirm('¿Descartar estos registros? Se borran del teléfono y no se podrán recuperar.')) return;
        const hadClose = jobs.some((job) => job.row.type === 'close');
        await ZentraOffline.discardRejected();
        if (hadClose) await clearOwnCloseLock();
        await updateSyncBar();
    };
}

async function updateSyncBar() {
    await renderRejected();
    const bar = document.getElementById('sync-bar');
    if (!bar) return;
    const stats = await ZentraOffline.outboxStats();
    const n = stats.total;
    if (n < 1 && !closeQueued) {
        bar.classList.add('hidden');
        bar.textContent = '';
        return;
    }
    bar.classList.remove('hidden');
    const net = navigator.onLine ? 'Subiendo…' : 'Sin cobertura';
    if (stats.others && !stats.mine && !closeQueued) {
        bar.textContent = `${stats.others} registro(s) de otro supervisor. ${net}`;
        return;
    }
    if (stats.others) {
        bar.textContent = `${n} registro(s) en el teléfono (${stats.others} de otro supervisor). ${net}. No borre datos del sitio.`;
        return;
    }
    bar.textContent = n
        ? `${n} registro(s) en el teléfono. ${net}. No borre datos del sitio.`
        : 'Cierre de turno pendiente de envío.';
}

async function enqueueOrThrow(item, okMessage) {
    await ZentraOffline.enqueue(Object.assign({}, item, { authToken: token() || '' }));
    await updateSyncBar();
    setStatus(okMessage);
}

async function flushOutbox() {
    if (syncing || !navigator.onLine) return;
    syncing = true;
    const sessionUser = ZentraOffline.currentUserId();
    let closedMine = false;
    try {
        const jobs = await ZentraOffline.allPendingJobs();
        for (const job of jobs) {
            if (job.row?.type === 'close-meta' || job.row?.rejected) continue;
            const uid = Number(job.userId || job.row?.userId || 0) || 0;
            const sessionTok = token();
            let auth = job.row?.authToken || job.authToken || (uid ? ZentraOffline.tokenForUser(uid) : '');
            if (job.row?.type === 'close') {
                if (!uid || (sessionUser && uid !== Number(sessionUser) && auth && sessionTok && auth === sessionTok)) {
                    auth = uid ? ZentraOffline.tokenForUser(uid) : '';
                }
                if (!auth || (sessionUser && uid !== Number(sessionUser) && auth === sessionTok)) {
                    continue;
                }
                if (sessionUser && !uid) {
                    continue;
                }
            } else if (!auth) {
                if (sessionUser && uid === Number(sessionUser) && sessionTok) auth = sessionTok;
                else continue;
            } else if (sessionUser && uid && uid !== Number(sessionUser) && auth === sessionTok) {
                auth = ZentraOffline.tokenForUser(uid) || '';
                if (!auth) continue;
            }
            try {
                await sendOutboxItem(job.row, auth);
                if (job.row.id != null) {
                    await ZentraOffline.removeOutboxIn(job.dbName, job.row.id);
                }
                if (job.row.type === 'close') {
                    await ZentraOffline.metaSetIn(job.dbName, 'closeQueued', false);
                    await ZentraOffline.metaSetIn(job.dbName, 'shift', null);
                    if (sessionUser && uid === Number(sessionUser)) {
                        closedMine = true;
                    }
                }
            } catch (e) {
                if (ZentraOffline.isOfflineError(e)) break;
                if (job.row.id == null || e.status === 401 || !(e.status >= 400 && e.status < 500)) continue;
                if (job.row.type === 'ping') {
                    await ZentraOffline.removeOutboxIn(job.dbName, job.row.id);
                } else {
                    await ZentraOffline.markRejectedIn(job.dbName, job.row, e.message);
                }
                continue;
            }
        }
        if (closedMine) {
            closeQueued = false;
            stopPing();
            setStatus('Turno cerrado. Datos enviados.');
            syncing = false;
            logout({ skipFlush: true });
            return;
        }
    } catch (e) {
        if (!ZentraOffline.isOfflineError(e)) setStatus(e.message, false);
    } finally {
        syncing = false;
        await updateSyncBar();
    }
}

async function sendOutboxItem(row, authToken) {
    const auth = { authToken };
    if (row.type === 'ping') {
        await api('/supervision/shifts/ping', { method: 'POST', body: JSON.stringify(row.body), ...auth });
        return;
    }
    if (row.type === 'log') {
        await api('/supervision/logs', { method: 'POST', body: JSON.stringify(row.body), ...auth });
        return;
    }
    if (row.type === 'review') {
        const fd = reviewFormFromQueue(row);
        const data = await api('/supervision/reviews', { method: 'POST', body: fd, ...auth });
        announceDiscarded(data);
        return;
    }
    if (row.type === 'close') {
        const fd = new FormData();
        fd.append('km_end', String(row.body.km_end));
        fd.append('client_event_id', row.clientEventId);
        if (row.body.shift_id) fd.append('shift_id', String(row.body.shift_id));
        fd.append('odometer_photo', row.files.odometer, 'odometer-end.jpg');
        fd.append('selfie_photo', row.files.selfie, 'selfie-end.jpg');
        await api('/supervision/shifts/close', { method: 'POST', body: fd, ...auth });
        return;
    }
    if (row.type === 'observatory') {
        const fd = observatoryFormFromQueue(row);
        await api('/supervision/observatory/reports', { method: 'POST', body: fd, ...auth });
    }
}

function observatoryFormFromQueue(row) {
    const fd = new FormData();
    Object.entries(row.body || {}).forEach(([key, value]) => {
        if (value == null || value === '') return;
        fd.append(key, typeof value === 'string' ? value : String(value));
    });
    const photos = Array.isArray(row.files?.photos) ? row.files.photos : [];
    photos.forEach((blob, i) => {
        if (blob) fd.append('photos[]', blob, `observatory-${i + 1}.jpg`);
    });
    if (!photos.length && row.files?.photo) {
        fd.append('photos[]', row.files.photo, 'observatory.jpg');
    }
    return fd;
}

function reviewFormFromQueue(row) {
    const fd = new FormData();
    Object.entries(row.body).forEach(([key, value]) => {
        if (value == null) return;
        fd.append(key, typeof value === 'string' ? value : String(value));
    });
    fd.append('client_event_id', row.clientEventId);
    fd.append('guard_photo', row.files.guard, 'guard.jpg');
    Object.entries(row.files.logPhotos || {}).forEach(([slot, blob]) => {
        const [index, name] = slot.split('::');
        fd.append(`log_photos[${index}][${name}]`, blob, `${name}.jpg`);
    });
    return fd;
}

async function apiHtml(path) {
    const headers = { Accept: 'text/html' };
    if (token()) headers.Authorization = `Bearer ${token()}`;
    const res = await fetch(`${apiBase()}${path}`, { headers });
    const text = await res.text();
    if (!res.ok) {
        throw new Error('No se pudo abrir la ficha.');
    }
    return text;
}

function siteId() {
    if (currentModule?.requires_client) {
        const value = document.getElementById('mod-client')?.value;
        return value ? Number(value) : null;
    }
    return currentReview?.client_id || null;
}

async function geoRequired(message = 'Active la ubicación del dispositivo.') {
    const pos = await geo();
    if (pos?.latitude == null || pos?.longitude == null) {
        throw new Error(message);
    }
    return pos;
}

async function geo() {
    return new Promise((resolve) => {
        if (!navigator.geolocation) return resolve(lastGeo);
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                lastGeo = { latitude: pos.coords.latitude, longitude: pos.coords.longitude, accuracy: pos.coords.accuracy, timestamp: pos.timestamp };
                resolve(lastGeo);
            },
            () => resolve(lastGeo),
            { enableHighAccuracy: true, timeout: 4000, maximumAge: 15000 },
        );
    });
}

function checksHtml(items, prefix) {
    return items.map((item) => `
        <label class="check">
            <input type="checkbox" data-check="${prefix}" data-key="${item.key}">
            <span>${item.label}</span>
        </label>`).join('');
}

function readChecks(prefix) {
    const out = {};
    document.querySelectorAll(`input[data-check="${prefix}"]`).forEach((el) => {
        out[el.dataset.key] = el.checked;
    });
    return out;
}

const SLOT_ICON = '<svg class="slot-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8.5A2.5 2.5 0 0 1 5.5 6h1.9l1.2-1.8A1.5 1.5 0 0 1 9.85 3.5h4.3a1.5 1.5 0 0 1 1.25.7L16.6 6h1.9A2.5 2.5 0 0 1 21 8.5v9a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 17.5z"/><circle cx="12" cy="13" r="3.6"/></svg>';

function downscaleImage(file, max = 1600) {
    return new Promise((resolve) => {
        const img = new Image();
        img.onload = () => {
            const scale = Math.min(1, max / Math.max(img.width, img.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(img.width * scale);
            canvas.height = Math.round(img.height * scale);
            canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
            URL.revokeObjectURL(img.src);
            canvas.toBlob((blob) => resolve(blob || file), 'image/jpeg', 0.86);
        };
        img.onerror = () => resolve(file);
        img.src = URL.createObjectURL(file);
    });
}

function bindCameras() {
    const input = document.getElementById('cam-file');
    document.querySelectorAll('[data-cam-blob]').forEach((btn) => {
        btn.onclick = () => {
            input.dataset.blob = btn.dataset.camBlob;
            input.dataset.img = btn.dataset.camImg;
            input.setAttribute('capture', btn.dataset.camFacing === 'user' ? 'user' : 'environment');
            input.value = '';
            input.click();
        };
    });
    input.onchange = async () => {
        const file = input.files?.[0];
        if (!file) return;
        const key = input.dataset.blob;
        const blob = await downscaleImage(file);
        blobs[key] = blob;
        const img = document.getElementById(input.dataset.img);
        img.src = URL.createObjectURL(blob);
        img.classList.remove('hidden');
        setStatus('Foto tomada.');
        if (key === 'guard') saveReviewSession();
        if (key === 'odoEnd' || key === 'selfEnd') scheduleSessionSave();
    };
}

function renderIntake() {
    const slot = document.getElementById('shift-template');
    const templates = intake.shift_templates || [];
    slot.innerHTML = templates.map((s) => `<option value="${s.id}" data-schedule="${s.schedule}">${s.name}</option>`).join('')
        || '<option value="">Sin turnos activos</option>';
    document.getElementById('shift-schedule').value = templates[0]?.schedule || '';
    slot.onchange = () => {
        const opt = slot.selectedOptions[0];
        document.getElementById('shift-schedule').value = opt?.dataset.schedule || '';
    };
    const zones = intake.zones || [];
    document.getElementById('route-zone').innerHTML = zones.map((z) => `<option value="${z.id}">${z.name}</option>`).join('')
        || '<option value="">Sin zonas activas</option>';
    document.getElementById('ppe-list').innerHTML = checksHtml(intake.ppe || [], 'ppe');
    document.getElementById('vehicle-check-list').innerHTML = checksHtml(intake.vehicle_check || [], 'vcheck');
    const ppeToggle = document.getElementById('ppe-toggle');
    if (ppeToggle) ppeToggle.querySelector('span').textContent = `2. Preoperacional · EPP (${(intake.ppe || []).length})`;
    const vToggle = document.getElementById('vcheck-toggle');
    if (vToggle) vToggle.querySelector('span').textContent = `Preoperacional vehículo (${(intake.vehicle_check || []).length})`;

    const select = document.getElementById('fleet-vehicle');
    const vehicles = intake.vehicles || [];
    const first = intake.first_vehicle || vehicles.length === 0;
    document.getElementById('vehicle-hint').textContent = first
        ? 'Primera vez: diligencie la ficha completa del vehículo de flota.'
        : 'Elija un vehículo o registre uno nuevo.';
    select.innerHTML = first
        ? '<option value="">Registrar vehículo nuevo</option>'
        : ['<option value="">Registrar vehículo nuevo</option>']
            .concat(vehicles.map((v) => `<option value="${v.id}" data-km="${v.last_km}">${v.label} · ${v.last_km} km</option>`))
            .join('');
    document.getElementById('vehicle-form').innerHTML = `
        <label>Placa</label><input id="v-plate" maxlength="12">
        <label>Marca</label><input id="v-brand">
        <div class="row">
            <div><label>Línea</label><input id="v-line"></div>
            <div><label>Modelo</label><input id="v-model"></div>
        </div>
        <div class="row">
            <div><label>SOAT</label><input id="v-soat" type="date"></div>
            <div><label>Tecnomecánica</label><input id="v-tm" type="date"></div>
        </div>`;
    const form = document.getElementById('vehicle-form');
    const applyVehicle = () => {
        const id = select.value;
        const found = vehicles.find((v) => String(v.id) === id);
        form.classList.toggle('hidden', Boolean(id));
        document.getElementById('last-km').textContent = found ? `Último cierre: ${found.last_km} km` : '';
        if (found) document.getElementById('km-start').value = found.last_km;
    };
    select.onchange = applyVehicle;
    applyVehicle();
}

function renderHub() {
    const hubMods = catalog.filter((mod) => mod.hangs_off_review);
    document.getElementById('hub-grid').innerHTML = hubMods.map((mod) => {
        const count = draftCount(mod.key);
        const note = mod.single
            ? (count ? 'Registrada ✓' : (mod.mandatory ? 'Obligatoria' : 'Opcional'))
            : `${count} en esta revista`;
        const tone = mod.mandatory && !count ? ' is-required' : (mod.single && count ? ' is-done' : '');
        return `
        <button type="button" class="mod${tone}" data-key="${mod.key}">
            <b>${mod.label}</b>
            <span class="n">${note}</span>
        </button>`;
    }).join('');
    document.querySelectorAll('#hub-grid .mod').forEach((btn) => {
        btn.onclick = () => openModule(btn.dataset.key, 'review');
    });
    const ctx = document.getElementById('review-context');
    ctx.textContent = [reviewClient?.name, reviewPost?.name, reviewGuard?.name].filter(Boolean).join(' · ');
    syncReviewNovelty();
}

function draftHasNovelty(draft) {
    const p = draft.payload || {};
    const items = Array.isArray(p.items) ? p.items : [];
    switch (draft.module) {
    case 'uniform': return items.some((row) => ['yes', 'regular', 'bad'].includes(row.status));
    case 'inventory': return items.some((row) => row.status === 'regular' || row.status === 'bad');
    case 'control_books': return items.some((row) => row.novelty === 'yes');
    case 'folders': return p.status === 'missing';
    case 'weapons': return p.novelty === 'yes' || (p.permit_expires_at && p.permit_expires_at < new Date().toISOString().slice(0, 10));
    case 'recommendations': return items.length > 0;
    default: return false;
    }
}

function syncReviewNovelty() {
    const box = document.getElementById('rev-novelty');
    const why = document.getElementById('rev-novelty-why');
    if (!box) return;
    const sources = reviewDraftLogs.filter(draftHasNovelty)
        .map((draft) => catalog.find((m) => m.key === draft.module)?.label || draft.module);
    const forced = sources.length > 0;
    if (forced) {
        if (!box.disabled) box.dataset.manual = box.checked ? '1' : '0';
        box.checked = true;
    } else if (box.disabled) {
        box.checked = box.dataset.manual === '1';
    }
    box.disabled = forced;
    box.closest('.check')?.classList.toggle('is-locked', forced);
    if (why) {
        why.textContent = forced ? `Por novedad en: ${[...new Set(sources)].join(', ')}.` : '';
        why.classList.toggle('hidden', !forced);
    }
}

function draftCount(moduleKey) {
    return reviewDraftLogs.filter((row) => row.module === moduleKey).length;
}

function showOpsHome() {
    document.getElementById('ops-home').classList.remove('hidden');
    document.getElementById('review-card').classList.add('hidden');
    document.getElementById('module-card').classList.add('hidden');
    document.getElementById('sheets-card')?.classList.add('hidden');
    document.getElementById('observatory-card')?.classList.add('hidden');
    hideGeoCard();
    rememberScreen('home');
    scheduleSessionSave();
}

function showReview() {
    document.getElementById('ops-home').classList.add('hidden');
    document.getElementById('module-card').classList.add('hidden');
    document.getElementById('sheets-card')?.classList.add('hidden');
    document.getElementById('observatory-card')?.classList.add('hidden');
    hideGeoCard();
    document.getElementById('review-card').classList.remove('hidden');
    refreshSitesIfStale();
    rememberScreen('review');
    scheduleSessionSave();
}

/** Autoguardado en el teléfono de lo que se está escribiendo (módulo, Observatorio, cierre de turno). */
function bindDraftAutosave() {
    const moduleForm = document.getElementById('module-form');
    moduleForm?.addEventListener('input', scheduleSessionSave);
    moduleForm?.addEventListener('change', scheduleSessionSave);
    moduleForm?.addEventListener('click', (ev) => {
        if (ev.target.closest('[data-rep-add], [data-rep-remove]')) setTimeout(scheduleSessionSave, 0);
    });
    document.getElementById('mod-client')?.addEventListener('change', scheduleSessionSave);
    ['obs-body', 'obs-q'].forEach((id) => document.getElementById(id)?.addEventListener('input', scheduleSessionSave));
    ['obs-kind', 'obs-anonymous'].forEach((id) => document.getElementById(id)?.addEventListener('change', scheduleSessionSave));
    document.getElementById('km-end')?.addEventListener('input', scheduleSessionSave);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') flushDrafts();
    });
    window.addEventListener('pagehide', flushDrafts);
}

function flushDrafts() {
    if (!draftsReady) return;
    clearTimeout(sessionSaveTimer);
    saveReviewSession();
    saveObsDraft();
    saveCloseDraft();
}

function visible(id) {
    const el = document.getElementById(id);
    return Boolean(el && !el.classList.contains('hidden'));
}

async function saveObsDraft() {
    if (!offlineReady() || !visible('ops') || !visible('observatory-card')) return;
    const body = document.getElementById('obs-body').value;
    const photos = {};
    ['obs-0', 'obs-1', 'obs-2'].forEach((key) => { if (modulePhotos[key]) photos[key] = modulePhotos[key]; });
    const empty = !obsSite && !body.trim() && !document.getElementById('obs-kind').value && !Object.keys(photos).length;
    try {
        await ZentraOffline.metaSet('obsDraft', empty ? null : {
            site: obsSite,
            q: document.getElementById('obs-q').value,
            kind: document.getElementById('obs-kind').value,
            body,
            anonymous: document.getElementById('obs-anonymous').checked,
            pin: obsPin,
            photos,
        });
    } catch (e) {
        // sin almacenamiento
    }
}

async function restoreObsDraft() {
    if (!offlineReady()) return;
    const draft = await ZentraOffline.metaGet('obsDraft').catch(() => null);
    if (!draft) return;
    await loadObservatoryKinds().catch(() => {});
    obsSite = draft.site || null;
    document.getElementById('obs-q').value = draft.q || draft.site?.name || '';
    document.getElementById('obs-picked').textContent = obsSite
        ? `Elegido: ${obsSite.name}${obsSite.client ? ` · ${obsSite.client}` : ''}`
        : '';
    if (obsSite) fillObsKinds(kindsForSite(obsSite));
    document.getElementById('obs-kind').value = draft.kind || '';
    document.getElementById('obs-body').value = draft.body || '';
    document.getElementById('obs-anonymous').checked = Boolean(draft.anonymous);
    document.getElementById('obs-anon-warn').classList.toggle('hidden', !draft.anonymous);
    Object.entries(draft.photos || {}).forEach(([slot, blob]) => { if (blob) applySlotPhoto(slot, blob); });
    if (draft.pin) obsPin = draft.pin;
    if (obsSite) placeObsPin(obsSite);
    setStatus('Se recuperó el reporte del Observatorio que estabas llenando.');
}

async function clearObsDraft() {
    try { await ZentraOffline.metaSet('obsDraft', null); } catch (e) { /* sin almacenamiento */ }
}

async function saveCloseDraft() {
    if (!offlineReady() || !visible('close-shift')) return;
    const km = document.getElementById('km-end').value;
    try {
        await ZentraOffline.metaSet('closeDraft', {
            km,
            odometer: blobs.odoEnd || null,
            selfie: blobs.selfEnd || null,
        });
    } catch (e) {
        // sin almacenamiento
    }
}

async function restoreCloseDraft() {
    if (!offlineReady()) return;
    const draft = await ZentraOffline.metaGet('closeDraft').catch(() => null);
    if (!draft) return;
    if (draft.km) document.getElementById('km-end').value = draft.km;
    [['odoEnd', 'snap-odo-end', draft.odometer], ['selfEnd', 'snap-self-end', draft.selfie]].forEach(([key, imgId, blob]) => {
        if (!blob) return;
        blobs[key] = blob;
        const img = document.getElementById(imgId);
        img.src = URL.createObjectURL(blob);
        img.classList.remove('hidden');
    });
    setStatus('Se recuperó el cierre de turno que estabas llenando.');
}

async function clearCloseDraft() {
    try { await ZentraOffline.metaSet('closeDraft', null); } catch (e) { /* sin almacenamiento */ }
}

function openModule(key, from = 'home') {
    currentModule = catalog.find((m) => m.key === key);
    if (!currentModule) return;
    moduleReturn = from;
    document.getElementById('ops-home').classList.add('hidden');
    document.getElementById('review-card').classList.add('hidden');
    document.getElementById('sheets-card')?.classList.add('hidden');
    document.getElementById('observatory-card')?.classList.add('hidden');
    hideGeoCard();
    const wrap = document.getElementById('module-client-wrap');
    wrap.classList.toggle('hidden', !currentModule.requires_client);
    if (currentModule.requires_client) document.getElementById('mod-client').value = '';
    document.getElementById('module-card').classList.remove('hidden');
    document.getElementById('module-title').textContent = currentModule.label;
    document.getElementById('module-hint').textContent = currentModule.hint;
    document.getElementById('module-form').innerHTML = (currentModule.fields || []).map((field) => renderField(field)).join('');
    modulePhotos = {};
    (currentModule.fields || []).filter((field) => field.type === 'repeatable').forEach(bindRepeatable);
    bindAllPhotoGrids();
    bindConditionalFields();
    if (currentModule.key === 'trainings') {
        topicPick = null;
        trainingTimer = null;
        trainingDup = '';
        trainingWarned = false;
    }
    if (currentModule.key === 'uniform') bindUniform();
    if (currentModule.key === 'trainings') bindTraining();
    const existing = currentModule.single ? reviewDraftLogs.find((row) => row.module === currentModule.key) : null;
    if (existing) prefillModule(existing);
    document.getElementById('module-card').scrollIntoView({ behavior: 'smooth' });
    if (currentModule.requires_client) refreshSitesIfStale();
    rememberScreen('module');
    scheduleSessionSave();
}

function foldText(text) {
    return String(text || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
}

function trainingRunning() {
    return Boolean(trainingTimer?.startedAt && !trainingTimer?.endedAt);
}

function bindUniform() {
    const form = document.getElementById('module-form');
    if (!form) return;
    const sync = () => {
        form.querySelectorAll('.ptable tbody tr').forEach((row) => {
            const picked = row.querySelector('input:checked')?.value || '';
            row.dataset.status = picked;
        });
    };
    form.addEventListener('change', () => {
        sync();
        scheduleSessionSave();
    });
    sync();
}

function bindTraining() {
    const form = document.getElementById('module-form');
    if (!form) return;
    bindTopicPicker();
    document.getElementById('btn-timer-start').onclick = startTrainingTimer;
    document.getElementById('btn-timer-stop').onclick = stopTrainingTimer;
    form.addEventListener('change', scheduleSessionSave);
    form.addEventListener('input', scheduleSessionSave);
    renderTimer();
}

function topicField() {
    return (currentModule?.fields || []).find((f) => f.type === 'topic');
}

function bindTopicPicker() {
    const input = document.getElementById('f-topic-q');
    const list = document.getElementById('f-topic-list');
    const add = document.getElementById('f-topic-add');
    if (!input || !list || !add) return;
    let creating = false;
    const note = (text) => `<p class="hint topic-note">${text}</p>`;
    const setCreating = (on) => {
        creating = on;
        add.classList.toggle('hidden', !on);
        input.placeholder = on ? 'Escribe tu tema' : 'Toque para elegir o crear el tema';
        if (on) {
            input.value = '';
            topicPick = null;
            trainingDup = '';
            setTopicState('Revise que el tema no exista. Desde la app no se puede editar ni borrar.');
            input.focus();
        }
        syncAdd();
    };
    const syncAdd = () => {
        add.disabled = input.value.trim().length < 3;
    };
    const render = () => {
        const options = topicField()?.options || [];
        const typed = input.value.trim();
        const term = foldText(typed);
        const matches = options.filter((o) => term === '' || foldText(o.label).includes(term)).slice(0, 30);
        const exact = options.find((o) => foldText(o.label) === term);
        let html = '';
        if (creating) {
            if (!term || !matches.length) {
                list.classList.add('hidden');
                return;
            }
            html = note('Ya existen temas parecidos:');
        } else if (!term) {
            html = '<button type="button" data-create-topic="1" class="topic-new">+ Crear tema</button>';
            if (!options.length) html += note('Aún no hay temas. Cree el primero.');
        } else if (!exact) {
            html = typed.length >= 3
                ? `<button type="button" data-new-topic="1" class="topic-new">+ Agregar tema «${esc(typed)}»</button>`
                : note('Escriba al menos 3 letras para agregar el tema.');
        }
        html += matches.map((o) => `<button type="button" data-topic="${esc(o.value)}">${esc(o.label)}</button>`).join('');
        list.innerHTML = html;
        list.classList.remove('hidden');
        list.querySelectorAll('[data-topic]').forEach((btn) => {
            btn.onclick = () => {
                const found = options.find((o) => String(o.value) === btn.dataset.topic);
                if (!found) return;
                setCreating(false);
                pickTopic({ id: found.value, name: found.label, isNew: false });
            };
        });
        list.querySelector('[data-create-topic]')?.addEventListener('click', () => {
            setCreating(true);
            render();
        });
        list.querySelector('[data-new-topic]')?.addEventListener('click', () => createTopic(typed));
    };
    add.onclick = async () => {
        const name = input.value.trim();
        if (name.length < 3) return;
        await createTopic(name);
        if (topicPick) setCreating(false);
    };
    input.onkeydown = (ev) => {
        if (creating && ev.key === 'Enter') {
            ev.preventDefault();
            add.click();
        }
    };
    input.oninput = () => {
        if (topicPick && input.value !== topicPick.name) {
            topicPick = null;
            trainingDup = '';
            setTopicState(creating ? 'Revise que el tema no exista. Desde la app no se puede editar ni borrar.' : '');
        }
        syncAdd();
        render();
    };
    input.onfocus = render;
    setCreating(false);
}

function setTopicState(text, tone = '') {
    const el = document.getElementById('f-topic-state');
    if (!el) return;
    el.textContent = text;
    el.className = `hint${tone ? ` ${tone}` : ''}`;
}

async function createTopic(name) {
    const clean = name.replace(/\s+/g, ' ').trim();
    if (clean.length < 3) return;
    const field = topicField();
    const same = (field?.options || []).find((o) => foldText(o.label) === foldText(clean));
    if (same) {
        pickTopic({ id: same.value, name: same.label, isNew: false });
        return;
    }
    try {
        const data = await api('/supervision/training-topics', { method: 'POST', body: JSON.stringify({ name: clean }) });
        const topic = { value: String(data.topic.value), label: data.topic.label };
        if (field && !(field.options || []).some((o) => String(o.value) === topic.value)) {
            field.options = [...(field.options || []), topic].sort((a, b) => a.label.localeCompare(b.label));
        }
        pickTopic({ id: topic.value, name: topic.label, isNew: false });
        setStatus(data.created ? `Tema «${topic.label}» creado.` : `El tema «${topic.label}» ya existía; quedó seleccionado.`);
    } catch (e) {
        if (!ZentraOffline.isOfflineError(e)) {
            setStatus(e.message, false);
            return;
        }
        pickTopic({ id: null, name: clean, isNew: true });
    }
}

async function pickTopic(pick) {
    topicPick = pick;
    trainingDup = '';
    const input = document.getElementById('f-topic-q');
    if (input) input.value = pick.name;
    document.getElementById('f-topic-list')?.classList.add('hidden');
    scheduleSessionSave();
    await checkTrainingDuplicate();
}

async function checkTrainingDuplicate() {
    if (!topicPick) return;
    const employeeId = Number(reviewGuard?.id || 0);
    if (!employeeId) {
        setTopicState('Seleccione primero el vigilante en la revista.', 'err');
        return;
    }
    if (topicPick.isNew) {
        setTopicState('Sin señal: el tema nuevo se crea al sincronizar. Si el vigilante ya lo recibió este mes, la capacitación se eliminará al sincronizar.', 'warn');
        return;
    }
    const local = trainingsMonth.some((row) => Number(row.employee_id) === employeeId && Number(row.topic_id) === Number(topicPick.id));
    if (local) {
        trainingDup = `Este vigilante ya fue capacitado en «${topicPick.name}» este mes. Elija otro tema o cancele la capacitación.`;
        setTopicState(trainingDup, 'err');
        return;
    }
    if (!navigator.onLine) {
        setTopicState('Sin señal: no se puede validar si este vigilante ya recibió este tema. Si ya fue capacitado este mes, esta capacitación se eliminará al sincronizar.', 'warn');
        return;
    }
    try {
        const data = await api(`/supervision/trainings/check?employee_id=${employeeId}&topic_id=${encodeURIComponent(topicPick.id)}`);
        if (data.trained) {
            trainingDup = data.message;
            setTopicState(trainingDup, 'err');
        } else {
            setTopicState('Tema disponible para este vigilante.', 'ok');
        }
    } catch (e) {
        setTopicState('Sin señal: no se puede validar si este vigilante ya recibió este tema. Si ya fue capacitado este mes, esta capacitación se eliminará al sincronizar.', 'warn');
    }
}

function formatClock(ms) {
    const total = Math.max(0, Math.floor(ms / 1000));
    const h = String(Math.floor(total / 3600)).padStart(2, '0');
    const m = String(Math.floor((total % 3600) / 60)).padStart(2, '0');
    const s = String(total % 60).padStart(2, '0');
    return `${h}:${m}:${s}`;
}

function renderTimer() {
    const clock = document.getElementById('timer-clock');
    const start = document.getElementById('btn-timer-start');
    const stop = document.getElementById('btn-timer-stop');
    const hint = document.getElementById('timer-hint');
    if (!clock || !start || !stop) return;
    clearInterval(trainingTick);
    trainingTick = null;
    const running = trainingRunning();
    document.body.classList.toggle('training-lock', running);
    start.classList.toggle('hidden', Boolean(trainingTimer?.startedAt));
    stop.classList.toggle('hidden', !running);
    if (!trainingTimer?.startedAt) {
        clock.textContent = '00:00:00';
        hint.textContent = 'Pulse Iniciar al comenzar. La duración la calcula el sistema.';
        return;
    }
    const startedAt = new Date(trainingTimer.startedAt);
    const fmt = (d) => d.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });
    if (!running) {
        const ended = new Date(trainingTimer.endedAt);
        clock.textContent = formatClock(ended - startedAt);
        hint.textContent = `Capacitación de ${fmt(startedAt)} a ${fmt(ended)} · ${Math.max(1, Math.ceil((ended - startedAt) / 60000))} min.`;
        return;
    }
    const paint = () => {
        const elapsed = Date.now() - startedAt.getTime();
        clock.textContent = formatClock(elapsed);
        hint.textContent = `En curso desde las ${fmt(startedAt)}. No puede salir del módulo hasta terminar o cancelar.`;
        if (elapsed >= 2 * 3600 * 1000 && !trainingWarned) {
            trainingWarned = true;
            if (!window.confirm('La capacitación lleva más de 2 horas. ¿Sigue en curso?\n\nAceptar: sigue. Cancelar: terminarla ahora.')) {
                stopTrainingTimer();
            }
        }
    };
    paint();
    trainingTick = setInterval(paint, 1000);
}

function startTrainingTimer() {
    if (!reviewGuard?.id) {
        setStatus('Seleccione primero el vigilante en la revista.', false);
        return;
    }
    trainingTimer = { startedAt: new Date().toISOString(), endedAt: null };
    trainingWarned = false;
    try { history.pushState({ trainingLock: true }, ''); } catch (e) { /* sin historial */ }
    renderTimer();
    saveReviewSession();
    setStatus('Capacitación iniciada.');
}

function stopTrainingTimer() {
    if (!trainingRunning()) return;
    trainingTimer = { ...trainingTimer, endedAt: new Date().toISOString() };
    renderTimer();
    saveReviewSession();
    setStatus('Capacitación terminada.');
}

function cancelTraining() {
    clearInterval(trainingTick);
    trainingTick = null;
    trainingTimer = null;
    topicPick = null;
    trainingDup = '';
    document.body.classList.remove('training-lock');
    reviewDraftLogs = reviewDraftLogs.filter((row) => row.module !== 'trainings');
    saveReviewSession();
}

function prefillModule(state) {
    const payload = state.payload || {};
    const form = document.getElementById('module-form');
    if (!form) return;
    (currentModule.fields || []).forEach((field) => {
        if (field.type === 'checklist') {
            const legacy = { good: 'no', regular: 'yes', bad: 'yes' };
            (payload[field.name] || []).forEach((row) => {
                const status = legacy[row.status] || row.status;
                const input = form.querySelector(`input[name="f-${field.name}-${row.item_id}"][value="${status}"]`);
                if (input) input.checked = true;
            });
            return;
        }
        if (field.type === 'radio') {
            const select = document.getElementById(`f-${field.name}`);
            if (select?.tagName === 'SELECT') {
                select.value = payload[field.name] ?? '';
                return;
            }
            const input = form.querySelector(`input[name="f-${field.name}"][value="${payload[field.name]}"]`);
            if (input) input.checked = true;
            return;
        }
        if (field.type === 'textarea' || field.type === 'text') {
            const el = document.getElementById(`f-${field.name}`);
            if (el && payload[field.name] != null) el.value = payload[field.name];
        }
    });
    if (currentModule.key === 'trainings') {
        if (payload.topic_label) {
            topicPick = payload.topic_id
                ? { id: String(payload.topic_id), name: payload.topic_label, isNew: false }
                : { id: null, name: payload.topic_label, isNew: true };
            const input = document.getElementById('f-topic-q');
            if (input) input.value = payload.topic_label;
            checkTrainingDuplicate();
        }
        trainingTimer = state.timer || (payload.started_at ? { startedAt: payload.started_at, endedAt: payload.ended_at } : null);
        renderTimer();
    }
    Object.entries(state.photos || {}).forEach(([slot, blob]) => {
        if (blob) applySlotPhoto(slot, blob);
    });
    form.dispatchEvent(new Event('change'));
}

function announceDiscarded(data) {
    const rows = data?.discarded_trainings || [];
    if (!rows.length) return;
    const message = rows.map((row) => row.message).join(' ');
    setStatus(message, false);
    try { window.alert(message); } catch (e) { /* sin alert */ }
}

function moduleState() {
    if (!currentModule) return null;
    if (document.getElementById('module-card').classList.contains('hidden')) return null;
    let payload = {};
    try { payload = readPayload(); } catch (e) { payload = {}; }
    return {
        key: currentModule.key,
        from: moduleReturn,
        payload,
        form: moduleSnapshot(),
        photos: { ...modulePhotos },
        timer: currentModule.key === 'trainings' ? trainingTimer : null,
    };
}

/** Copia de lo escrito en el módulo abierto (campos, tarjetas repetibles y cliente). */
function moduleSnapshot() {
    const form = document.getElementById('module-form');
    const values = {};
    form?.querySelectorAll('input, select, textarea').forEach((el) => {
        if (el.type === 'file') return;
        if (el.type === 'radio') {
            if (el.checked && el.name) values[`radio:${el.name}`] = el.value;
            return;
        }
        if (!el.id) return;
        values[el.id] = el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value;
    });
    const cards = {};
    (currentModule?.fields || []).filter((field) => field.type === 'repeatable').forEach((field) => {
        cards[field.name] = [...document.querySelectorAll(`#rep-${field.name} .item-card`)].map((card) => card.dataset.index);
    });
    return { values, cards, client: document.getElementById('mod-client')?.value || '' };
}

function restoreModuleSnapshot(snapshot) {
    if (!snapshot || !currentModule) return;
    const form = document.getElementById('module-form');
    (currentModule.fields || []).filter((field) => field.type === 'repeatable').forEach((field) => {
        const indexes = snapshot.cards?.[field.name];
        const root = document.getElementById(`rep-${field.name}`);
        const list = root?.querySelector('.rep-list');
        if (!indexes?.length || !list) return;
        list.innerHTML = '';
        indexes.forEach((index) => {
            list.insertAdjacentHTML('beforeend', repeatableItemHtml(field, index));
            const card = list.querySelector(`.item-card[data-index="${index}"]`);
            bindAllPhotoGrids(card);
            bindComputedRisk(card, field, index);
        });
        syncRepeatable(root, field);
    });
    const apply = () => Object.entries(snapshot.values || {}).forEach(([key, value]) => {
        if (key.startsWith('radio:')) {
            const name = key.slice(6);
            const input = [...form.querySelectorAll('input[type="radio"]')].find((el) => el.name === name && el.value === value);
            if (input) input.checked = true;
            return;
        }
        const el = document.getElementById(key);
        if (!el) return;
        if (el.type === 'checkbox') el.checked = value === '1';
        else el.value = value;
    });
    // Dos pasadas: hay listas cuyas opciones dependen de otro campo (p. ej. resultado según modalidad).
    apply();
    form.dispatchEvent(new Event('change', { bubbles: true }));
    apply();
    form.querySelectorAll('select').forEach((el) => el.dispatchEvent(new Event('change', { bubbles: true })));
    const client = document.getElementById('mod-client');
    if (client && snapshot.client) client.value = snapshot.client;
}

function scheduleSessionSave() {
    if (!draftsReady) return;
    clearTimeout(sessionSaveTimer);
    sessionSaveTimer = setTimeout(() => {
        saveReviewSession();
        saveObsDraft();
        saveCloseDraft();
    }, 400);
}

async function saveReviewSession() {
    if (!offlineReady()) return;
    const open = moduleState();
    const empty = !reviewClient && !reviewPost && !reviewGuard && !reviewDraftLogs.length && !blobs.guard && !open;
    try {
        await ZentraOffline.metaSet('reviewSession', empty ? null : {
            client: reviewClient,
            post: reviewPost,
            guard: reviewGuard,
            notes: document.getElementById('rev-notes')?.value || '',
            novelty: Boolean(document.getElementById('rev-novelty')?.checked),
            drafts: reviewDraftLogs,
            guardBlob: blobs.guard || null,
            eventId: reviewEventId,
            open,
        });
    } catch (e) {
        // sin almacenamiento
    }
}

async function restoreReviewSession() {
    if (!offlineReady()) return;
    let session = null;
    try { session = await ZentraOffline.metaGet('reviewSession'); } catch (e) { session = null; }
    if (!session) return;
    reviewClient = session.client || null;
    reviewPost = session.post || null;
    reviewGuard = session.guard || null;
    reviewDraftLogs = Array.isArray(session.drafts) ? session.drafts : [];
    reviewEventId = session.eventId || null;
    blobs.guard = session.guardBlob || null;
    if (reviewClient) document.getElementById('rev-client-q').value = reviewClient.name || '';
    if (reviewPost) {
        const postQ = document.getElementById('rev-post-q');
        postQ.disabled = false;
        postQ.value = reviewPost.name || '';
    }
    if (reviewGuard) {
        document.getElementById('rev-guard-doc').value = reviewGuard.document_number || '';
        document.getElementById('rev-guard-name').value = reviewGuard.name || '';
    }
    document.getElementById('rev-notes').value = session.notes || '';
    document.getElementById('rev-novelty').checked = Boolean(session.novelty);
    if (blobs.guard) {
        const img = document.getElementById('snap-guard');
        img.src = URL.createObjectURL(blobs.guard);
        img.classList.remove('hidden');
    }
    showReview();
    renderHub();
    if (session.open?.key && catalog.some((m) => m.key === session.open.key)) {
        openModule(session.open.key, session.open.from || 'review');
        restoreModuleSnapshot(session.open.form);
        prefillModule(session.open);
        if (trainingRunning()) {
            try { history.pushState({ trainingLock: true }, ''); } catch (e) { /* sin historial */ }
        }
    }
    const standalone = session.open?.from === 'home';
    setStatus(trainingRunning()
        ? 'Capacitación en curso recuperada.'
        : (standalone ? 'Se recuperó lo que estabas registrando.' : 'Se recuperó la revista en curso.'));
}

window.addEventListener('popstate', () => {
    if (!trainingRunning()) return;
    try { history.pushState({ trainingLock: true }, ''); } catch (e) { /* sin historial */ }
    setStatus('Termine o cancele la capacitación antes de salir.', false);
});

window.addEventListener('beforeunload', (ev) => {
    if (!trainingRunning()) return;
    ev.preventDefault();
    ev.returnValue = '';
});

function fieldWrapper(field, html) {
    const attrs = [];
    if (field.show_when) attrs.push(`data-show-when='${JSON.stringify(field.show_when)}'`);
    if (field.options_when) attrs.push(`data-options-when='${JSON.stringify(field.options_when)}'`);
    if (!attrs.length) return html;
    return `<div class="field-block" ${attrs.join(' ')}>${html}</div>`;
}

function renderField(field) {
    if (field.type === 'repeatable') {
        return fieldWrapper(field, `<div id="rep-${field.name}" data-name="${field.name}">
            <div class="rep-list"></div>
            <button type="button" class="ghost" data-rep-add="${field.name}">${field.add_label || 'Agregar'}</button>
        </div>`);
    }
    if (field.type === 'photo_grid') {
        return fieldWrapper(field, `<label>${field.label}</label>
            <div class="photo-grid" id="photos-${field.name}">
                ${(field.slots || []).map((slot) => `<button type="button" class="photo-slot" data-photo-slot="${slot.key}">${SLOT_ICON}<span>${slot.label}</span></button>`).join('')}
            </div>`);
    }
    if (field.type === 'row') {
        return fieldWrapper(field, `<div class="row">${(field.fields || []).map((sub) => `<div><label>${sub.label}</label>${fieldControl(sub)}</div>`).join('')}</div>`);
    }
    if (field.type === 'checklist') {
        const options = field.options || [];
        const group = field.group || [];
        const grouped = options.filter((o) => group.includes(o.value));
        const loose = options.filter((o) => !group.includes(o.value));
        const head = grouped.length
            ? `<tr><th rowspan="2" class="pt-name">Prenda</th><th colspan="${grouped.length}">${esc(field.group_label || '')}</th>${loose.map((o) => `<th rowspan="2">${esc(o.label)}</th>`).join('')}</tr>
               <tr>${grouped.map((o) => `<th>${esc(o.label)}</th>`).join('')}</tr>`
            : `<tr><th class="pt-name">Prenda</th>${options.map((o) => `<th>${esc(o.label)}</th>`).join('')}</tr>`;
        const ordered = [...grouped, ...loose];
        return fieldWrapper(field, `<table class="ptable">
            <thead>${head}</thead>
            <tbody>${(field.items || []).map((item) => `
                <tr data-item="${esc(item.value)}">
                    <td class="pt-name">${esc(item.label)}</td>
                    ${ordered.map((o) => `<td><label class="tick tick-${esc(o.value)}"><input type="radio" name="f-${field.name}-${esc(item.value)}" value="${esc(o.value)}" aria-label="${esc(item.label)}: ${esc(o.label)}"><span></span></label></td>`).join('')}
                </tr>`).join('')}
            </tbody>
        </table>`);
    }
    if (field.type === 'radio' && isYesNo(field)) {
        return fieldWrapper(field, `<div class="yn-row"><span class="yn-label">${field.label}</span>${fieldControl(field)}</div>`);
    }
    if (field.type === 'topic') {
        return fieldWrapper(field, `<label for="f-topic-q">${field.label}</label>
            <div class="combo">
                <div class="topic-row">
                    <input id="f-topic-q" autocomplete="off" placeholder="Toque para elegir o crear el tema">
                    <button type="button" id="f-topic-add" class="gold btn-sm hidden" disabled>Agregar</button>
                </div>
                <div id="f-topic-list" class="combo-list hidden"></div>
            </div>
            <p class="hint" id="f-topic-state"></p>`);
    }
    if (field.type === 'timer') {
        return fieldWrapper(field, `<label>${field.label}</label>
            <div class="timer-box">
                <div class="timer-clock" id="timer-clock">00:00:00</div>
                <p class="hint" id="timer-hint">Pulse Iniciar al comenzar. La duración la calcula el sistema.</p>
                <div class="row">
                    <button type="button" class="gold" id="btn-timer-start">Iniciar capacitación</button>
                    <button type="button" class="secondary hidden" id="btn-timer-stop">Terminar capacitación</button>
                </div>
            </div>`);
    }
    const body = field.type === 'checkbox' ? fieldControl(field) : `<label>${field.label}</label>${fieldControl(field)}`;
    return fieldWrapper(field, body);
}

function bindConditionalFields() {
    const form = document.getElementById('module-form');
    if (!form) return;
    const apply = () => {
        const payload = readPayload();
        form.querySelectorAll('[data-show-when]').forEach((el) => {
            let cond = {};
            try { cond = JSON.parse(el.dataset.showWhen || '{}'); } catch { cond = {}; }
            const visible = Object.entries(cond).every(([key, value]) => payload[key] === value);
            el.classList.toggle('hidden', !visible);
        });
        form.querySelectorAll('[data-options-when]').forEach((el) => {
            let spec = {};
            try { spec = JSON.parse(el.dataset.optionsWhen || '{}'); } catch { spec = {}; }
            const fieldName = spec.field;
            const options = (spec.map && fieldName) ? (spec.map[payload[fieldName]] || []) : [];
            const select = el.querySelector('select');
            if (!select) return;
            const signature = options.map((o) => o.value).join('|');
            if (select.dataset.optionsSig === signature) return;
            select.dataset.optionsSig = signature;
            const current = select.value;
            select.innerHTML = `<option value="">${options.length ? 'Seleccione…' : 'Primero la modalidad'}</option>`
                + options.map((o) => `<option value="${esc(o.value)}">${esc(o.label)}</option>`).join('');
            select.disabled = !options.length;
            if (options.some((o) => o.value === current)) select.value = current;
        });
    };
    form.onchange = apply;
    apply();
}

function bindRepeatable(field) {
    const root = document.getElementById(`rep-${field.name}`);
    if (!root) return;
    const list = root.querySelector('.rep-list');
    const add = () => {
        const cards = list.querySelectorAll('.item-card');
        if (field.max && cards.length >= field.max) return;
        const index = Date.now() + Math.floor(Math.random() * 1000);
        list.insertAdjacentHTML('beforeend', repeatableItemHtml(field, index));
        const card = list.querySelector(`.item-card[data-index="${index}"]`);
        bindAllPhotoGrids(card);
        bindComputedRisk(card, field, index);
        syncRepeatable(root, field);
    };
    root.querySelector('[data-rep-add]').onclick = (ev) => {
        ev.preventDefault();
        add();
    };
    list.onclick = (ev) => {
        const btn = ev.target.closest('[data-rep-remove]');
        if (!btn) return;
        ev.preventDefault();
        btn.closest('.item-card')?.remove();
        syncRepeatable(root, field);
    };
    add();
}

function repeatableItemHtml(field, index) {
    const inner = (field.item_fields || []).map((item) => {
        if (item.type === 'photo_grid') {
            return `<label>${item.label}</label>
                <div class="photo-grid" data-photo-grid="${field.name}-${index}-${item.name}">
                    ${(item.slots || []).map((slot) => `<button type="button" class="photo-slot" data-photo-slot="${index}_${slot.key}">${SLOT_ICON}<span>${slot.label}</span></button>`).join('')}
                </div>`;
        }
        if (item.type === 'computed') {
            return `<label>${item.label}</label><p class="risk-level" id="f-${field.name}-${index}-${item.name}">—</p>`;
        }
        const named = { ...item, name: `${field.name}-${index}-${item.name}` };
        if (item.type === 'qty_pair') return fieldControl(named);
        if (isYesNo(item)) return `<div class="yn-row"><span class="yn-label">${item.label}</span>${fieldControl(named)}</div>`;
        return item.type === 'checkbox' ? fieldControl(named) : `<label>${item.label}</label>${fieldControl(named)}`;
    }).join('');
    return `<div class="item-card" data-index="${index}">
        <div class="head-row">
            <p class="hint" style="margin:0;">${field.item_label || 'Elemento'}</p>
            <button type="button" class="ghost btn-sm" data-rep-remove>Quitar</button>
        </div>
        ${inner}
    </div>`;
}

function syncRepeatable(root, field) {
    const cards = root.querySelectorAll('.item-card');
    const max = field.max || 99;
    const min = field.min || 1;
    const addBtn = root.querySelector('[data-rep-add]');
    if (addBtn) addBtn.classList.toggle('hidden', cards.length >= max);
    cards.forEach((card) => {
        const btn = card.querySelector('[data-rep-remove]');
        if (btn) btn.classList.toggle('hidden', cards.length <= min);
    });
}

function bindAllPhotoGrids(scope) {
    const root = scope || document.getElementById('module-form');
    const input = document.getElementById('photo-file');
    if (!root || !input) return;
    root.querySelectorAll('.photo-grid').forEach((grid) => {
        grid.onclick = (ev) => {
            const btn = ev.target.closest('[data-photo-slot]');
            if (!btn || !grid.contains(btn)) return;
            input.dataset.slot = btn.dataset.photoSlot;
            input.value = '';
            input.click();
        };
    });
}

function bindObsPhotoGrid() {
    bindAllPhotoGrids(document.getElementById('observatory-card'));
}

function obsPhotoBlobs() {
    return ['obs-0', 'obs-1', 'obs-2'].map((key) => modulePhotos[key]).filter(Boolean);
}

function resetObsPhotos() {
    ['obs-0', 'obs-1', 'obs-2'].forEach((key) => {
        delete modulePhotos[key];
        const btn = document.querySelector(`[data-photo-slot="${key}"]`);
        if (!btn) return;
        btn.classList.remove('has-photo');
        btn.querySelector('img')?.remove();
    });
}

function riskLevelFrom(likelihood, impact) {
    const score = likelihood * impact;
    if (score >= 17) return { key: 'extreme', label: 'Extremo' };
    if (score >= 10) return { key: 'high', label: 'Alto' };
    if (score >= 5) return { key: 'medium', label: 'Medio' };
    return { key: 'low', label: 'Bajo' };
}

function bindComputedRisk(card, field, index) {
    if (!card) return;
    const out = document.getElementById(`f-${field.name}-${index}-risk_level`);
    const likelihood = document.getElementById(`f-${field.name}-${index}-likelihood`);
    const impact = document.getElementById(`f-${field.name}-${index}-impact`);
    if (!out || !likelihood || !impact) return;
    const paint = () => {
        if (!likelihood.value || !impact.value) {
            out.textContent = '—';
            out.dataset.level = '';
            return;
        }
        const level = riskLevelFrom(Number(likelihood.value), Number(impact.value));
        out.textContent = level.label;
        out.dataset.level = level.key;
    };
    likelihood.addEventListener('change', paint);
    impact.addEventListener('change', paint);
    paint();
}

function collectRepeatablePhotos(field) {
    const photoField = (field?.item_fields || []).find((item) => item.type === 'photo_grid');
    const slots = photoField?.slots || [];
    const photos = {};
    [...document.querySelectorAll(`#rep-${field.name} .item-card`)].forEach((card, i) => {
        slots.forEach((slot) => {
            const key = `${card.dataset.index}_${slot.key}`;
            if (modulePhotos[key]) photos[`${i}_${slot.key}`] = modulePhotos[key];
        });
    });
    return photos;
}

function drawCropPreview() {
    if (!cropSession) return;
    const canvas = document.getElementById('crop-canvas');
    const stage = canvas.parentElement;
    const css = stage.clientWidth || 280;
    const size = Math.round(css * 2);
    canvas.width = size;
    canvas.height = size;
    const ctx = canvas.getContext('2d');
    const { img, angle, panX, panY } = cropSession;
    const rotated = angle % 180 === 0;
    const bw = rotated ? img.width : img.height;
    const bh = rotated ? img.height : img.width;
    const scale = Math.max(size / bw, size / bh);
    ctx.fillStyle = '#000';
    ctx.fillRect(0, 0, size, size);
    ctx.save();
    ctx.translate(size / 2 + panX, size / 2 + panY);
    ctx.rotate((angle * Math.PI) / 180);
    ctx.drawImage(img, -img.width * scale / 2, -img.height * scale / 2, img.width * scale, img.height * scale);
    ctx.restore();
}

function exportCropBlob() {
    return new Promise((resolve) => {
        const out = document.createElement('canvas');
        out.width = 720;
        out.height = 720;
        const ctx = out.getContext('2d');
        const { img, angle, panX, panY } = cropSession;
        const preview = document.getElementById('crop-canvas');
        const ratio = 720 / preview.width;
        const rotated = angle % 180 === 0;
        const bw = rotated ? img.width : img.height;
        const bh = rotated ? img.height : img.width;
        const scale = Math.max(preview.width / bw, preview.height / bh) * ratio;
        ctx.fillStyle = '#000';
        ctx.fillRect(0, 0, 720, 720);
        ctx.save();
        ctx.translate(360 + panX * ratio, 360 + panY * ratio);
        ctx.rotate((angle * Math.PI) / 180);
        ctx.drawImage(img, -img.width * scale / 2, -img.height * scale / 2, img.width * scale, img.height * scale);
        ctx.restore();
        out.toBlob((blob) => resolve(blob), 'image/jpeg', 0.86);
    });
}

function closePhotoEditor() {
    document.getElementById('photo-editor').classList.add('hidden');
    cropSession = null;
}

function openPhotoEditor(file, slot) {
    const img = new Image();
    img.onload = () => {
        cropSession = { img, slot, angle: 0, panX: 0, panY: 0 };
        document.getElementById('crop-title').textContent = 'Encuadrar foto';
        document.getElementById('photo-editor').classList.remove('hidden');
        drawCropPreview();
    };
    img.src = URL.createObjectURL(file);
}

function applySlotPhoto(slot, blob) {
    modulePhotos[slot] = blob;
    const btn = document.querySelector(`[data-photo-slot="${slot}"]`);
    if (!btn) return;
    btn.classList.add('has-photo');
    let img = btn.querySelector('img');
    if (!img) {
        img = document.createElement('img');
        img.alt = '';
        btn.prepend(img);
    }
    img.src = URL.createObjectURL(blob);
    scheduleSessionSave();
}

document.getElementById('photo-file').onchange = () => {
    const input = document.getElementById('photo-file');
    const file = input.files?.[0];
    const slot = input.dataset.slot;
    if (!file || !slot) return;
    openPhotoEditor(file, slot);
};

document.getElementById('btn-crop-rot').onclick = () => {
    if (!cropSession) return;
    cropSession.angle = (cropSession.angle + 90) % 360;
    drawCropPreview();
};
document.getElementById('btn-crop-cancel').onclick = closePhotoEditor;
document.getElementById('btn-crop-ok').onclick = async () => {
    if (!cropSession) return;
    const blob = await exportCropBlob();
    if (blob) applySlotPhoto(cropSession.slot, blob);
    closePhotoEditor();
};

(() => {
    const canvas = document.getElementById('crop-canvas');
    let dragging = false;
    let lastX = 0;
    let lastY = 0;
    const point = (ev) => {
        const t = ev.touches ? ev.touches[0] : ev;
        return { x: t.clientX, y: t.clientY };
    };
    const start = (ev) => {
        if (!cropSession) return;
        dragging = true;
        const p = point(ev);
        lastX = p.x;
        lastY = p.y;
        ev.preventDefault();
    };
    const move = (ev) => {
        if (!dragging || !cropSession) return;
        const p = point(ev);
        cropSession.panX += (p.x - lastX) * 2;
        cropSession.panY += (p.y - lastY) * 2;
        lastX = p.x;
        lastY = p.y;
        drawCropPreview();
        ev.preventDefault();
    };
    const end = () => { dragging = false; };
    canvas.addEventListener('pointerdown', start);
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', end);
    canvas.addEventListener('touchstart', start, { passive: false });
    window.addEventListener('touchmove', move, { passive: false });
    window.addEventListener('touchend', end);
})();

function isYesNo(field) {
    const values = (field.options || []).map((o) => o.value).sort().join(',');
    return field.type === 'radio' && values === 'no,yes';
}

function fieldControl(field) {
    const req = field.required ? 'required' : '';
    if (field.type === 'textarea') return `<textarea id="f-${field.name}" ${req}></textarea>`;
    if (field.type === 'select') {
        const options = field.options || [];
        const opts = options.length
            ? '<option value="">Seleccione…</option>' + options.map((o) => `<option value="${o.value}">${o.label}</option>`).join('')
            : `<option value="">${field.empty_label || 'Sin tipos. Agréguelos en Ajustes'}</option>`;
        return `<select id="f-${field.name}" ${req}>${opts}</select>`;
    }
    if (field.type === 'qty_pair') {
        return `<div class="row">${(field.fields || []).map((sub) => `
            <div>
                <label>${sub.label}</label>
                <input id="f-${field.name}-${sub.name}" type="number" min="0" value="0">
            </div>`).join('')}</div>`;
    }
    if (field.type === 'radio') {
        if (isYesNo(field)) {
            return `<div class="yn">${['yes', 'no'].map((value) => `
                <label class="yn-opt"><input type="radio" name="f-${field.name}" value="${value}"><span class="yn-box"></span><span>${value === 'yes' ? 'Sí' : 'No'}</span></label>`).join('')}</div>`;
        }
        return `<select id="f-${field.name}" data-choice="1" ${req}>
            <option value="">Seleccione…</option>
            ${(field.options || []).map((o) => `<option value="${esc(o.value)}">${esc(o.label)}</option>`).join('')}
        </select>`;
    }
    if (field.type === 'checkbox') return `<label class="opt"><input id="f-${field.name}" type="checkbox"><span>${field.label}</span></label>`;
    if (field.type === 'number') return `<input id="f-${field.name}" type="number" min="${field.min ?? 1}" value="${field.min ?? 1}" ${req}>`;
    if (field.type === 'date') return `<input id="f-${field.name}" type="date" ${req}>`;
    return `<input id="f-${field.name}" type="text" ${req}>`;
}

function readPayload() {
    const payload = {};
    (currentModule?.fields || []).forEach((field) => {
        if (field.type === 'repeatable') {
            payload[field.name] = readRepeatable(field);
            return;
        }
        if (field.type === 'photo_grid') return;
        if (field.type === 'row') {
            (field.fields || []).forEach((sub) => assignFieldValue(payload, sub));
            return;
        }
        if (field.type === 'checklist') {
            payload[field.name] = (field.items || []).map((item) => {
                const picked = document.querySelector(`input[name="f-${field.name}-${item.value}"]:checked`);
                return { item_id: Number(item.value), status: picked ? picked.value : null };
            });
            payload.novelty = payload[field.name].some((row) => row.status === 'yes') ? 'yes' : 'no';
            return;
        }
        if (field.type === 'topic') {
            payload.topic_id = topicPick && !topicPick.isNew ? Number(topicPick.id) : null;
            payload.topic_name = topicPick?.isNew ? topicPick.name : null;
            payload.topic_label = topicPick?.name || null;
            return;
        }
        if (field.type === 'timer') {
            payload.started_at = trainingTimer?.startedAt || null;
            payload.ended_at = trainingTimer?.endedAt || null;
            return;
        }
        if (field.type === 'radio') {
            const select = document.getElementById(`f-${field.name}`);
            if (select?.tagName === 'SELECT') {
                payload[field.name] = select.value || null;
                return;
            }
            const picked = document.querySelector(`input[name="f-${field.name}"]:checked`);
            payload[field.name] = picked ? picked.value : null;
            return;
        }
        assignFieldValue(payload, field);
    });
    return payload;
}

function assignFieldValue(payload, field) {
    const el = document.getElementById(`f-${field.name}`);
    if (!el) return;
    if (field.type === 'checkbox') payload[field.name] = el.checked;
    else if (field.type === 'number') payload[field.name] = el.value === '' ? null : Number(el.value);
    else payload[field.name] = el.value === '' ? null : el.value;
}

function readRepeatable(field) {
    return [...document.querySelectorAll(`#rep-${field.name} .item-card`)].map((card) => {
        const row = {};
        (field.item_fields || []).forEach((item) => {
            if (item.type === 'photo_grid' || item.type === 'computed') return;
            const key = `${field.name}-${card.dataset.index}-${item.name}`;
            if (item.type === 'qty_pair') {
                (item.fields || []).forEach((sub) => {
                    const el = document.getElementById(`f-${key}-${sub.name}`);
                    row[sub.name] = el?.value === '' ? 0 : Number(el.value);
                });
                return;
            }
            if (item.type === 'radio') {
                const select = document.getElementById(`f-${key}`);
                if (select?.tagName === 'SELECT') {
                    row[item.name] = select.value || null;
                    return;
                }
                const picked = document.querySelector(`input[name="f-${key}"]:checked`);
                row[item.name] = picked ? picked.value : null;
                return;
            }
            const el = document.getElementById(`f-${key}`);
            if (!el) return;
            if (item.type === 'checkbox') row[item.name] = el.checked;
            else if (item.type === 'number') row[item.name] = el.value === '' ? null : Number(el.value);
            else row[item.name] = el.value === '' ? null : el.value;
        });
        return row;
    });
}

async function loadSites() {
    try {
        await refreshOfflinePack();
    } catch (e) {
        await restorePack();
        if (!sites.length) throw e;
        if (ZentraOffline.isOfflineError(e)) {
            setStatus('Sin cobertura. Usando clientes guardados en el teléfono.');
            return;
        }
        throw e;
    }
}

function debounceSearch(key, fn) {
    clearTimeout(searchTimers[key]);
    searchTimers[key] = setTimeout(fn, 220);
}

function fillCombo(listId, rows, emptyText, onPick) {
    const list = document.getElementById(listId);
    if (!rows.length) {
        list.innerHTML = `<p class="hint" style="padding:.5rem .65rem;margin:0;">${emptyText}</p>`;
        list.classList.remove('hidden');
        return;
    }
    list.innerHTML = rows.map((row) =>
        `<button type="button" data-id="${row.id}">${row.label}</button>`,
    ).join('');
    list.classList.remove('hidden');
    list.querySelectorAll('button').forEach((btn) => {
        btn.onclick = () => {
            const found = rows.find((r) => String(r.id) === btn.dataset.id);
            list.classList.add('hidden');
            if (found) onPick(found);
        };
    });
}

function filterSites(query) {
    const term = query.trim().toLowerCase();
    return sites
        .filter((s) => term === '' || s.name.toLowerCase().includes(term))
        .slice(0, 20)
        .map((s) => ({ id: s.id, label: s.name, name: s.name }));
}

function selectReviewClient(row) {
    reviewClient = { id: row.id, name: row.name || row.label };
    reviewPost = null;
    document.getElementById('rev-client-q').value = reviewClient.name;
    document.getElementById('rev-post-q').disabled = false;
    document.getElementById('rev-post-q').value = '';
    document.getElementById('rev-post-q').placeholder = 'Escriba el puesto';
    scheduleSessionSave();
}

function selectReviewPost(row) {
    reviewPost = { id: row.id, name: row.name || row.label };
    document.getElementById('rev-post-q').value = row.label || reviewPost.name;
    scheduleSessionSave();
}

function selectReviewGuard(row) {
    const previous = reviewGuard?.id;
    reviewGuard = { id: row.id, name: row.name, document_number: row.document_number };
    document.getElementById('rev-guard-doc').value = row.document_number;
    document.getElementById('rev-guard-name').value = row.name;
    const tied = reviewDraftLogs.filter((d) => d.module === 'uniform' || d.module === 'trainings');
    if (previous && String(previous) !== String(row.id) && tied.length) {
        reviewDraftLogs = reviewDraftLogs.filter((d) => d.module !== 'uniform' && d.module !== 'trainings');
        renderHub();
        setStatus('Cambió el vigilante: la presentación personal y la capacitación deben registrarse de nuevo.', false);
    }
    scheduleSessionSave();
}

function bindReviewUi() {
    const clientQ = document.getElementById('rev-client-q');
    const postQ = document.getElementById('rev-post-q');
    const guardQ = document.getElementById('rev-guard-doc');
    const noClients = 'Ese cliente no está en tus zonas. Pide a la central que te habilite la zona.';
    clientQ.oninput = () => debounceSearch('client', () => {
        reviewClient = null;
        fillCombo('rev-client-list', filterSites(clientQ.value), noClients, selectReviewClient);
    });
    clientQ.onfocus = async () => {
        fillCombo('rev-client-list', filterSites(clientQ.value), noClients, selectReviewClient);
        if (await refreshSitesIfStale() && document.activeElement === clientQ) {
            fillCombo('rev-client-list', filterSites(clientQ.value), noClients, selectReviewClient);
        }
    };
    postQ.oninput = () => debounceSearch('post', () => searchPosts(postQ.value));
    postQ.onfocus = () => { if (reviewClient) searchPosts(postQ.value); };
    guardQ.oninput = () => debounceSearch('guard', () => searchGuards(guardQ.value));
    document.getElementById('rev-notes').addEventListener('input', scheduleSessionSave);
    document.getElementById('rev-novelty').addEventListener('change', scheduleSessionSave);
    document.addEventListener('click', (ev) => {
        if (!ev.target.closest('.combo')) {
            document.querySelectorAll('.combo-list').forEach((el) => el.classList.add('hidden'));
        }
    });
    document.getElementById('btn-save-review').onclick = saveReview;
}

async function searchPosts(query) {
    if (!reviewClient) {
        fillCombo('rev-post-list', [], 'Primero el cliente', selectReviewPost);
        return;
    }
    const term = (query || '').trim().toLowerCase();
    const local = (postsCache || [])
        .filter((p) => Number(p.client_id) === Number(reviewClient.id))
        .filter((p) => term === '' || (p.label || p.name || '').toLowerCase().includes(term))
        .slice(0, 30)
        .map((p) => ({
            id: p.id,
            name: p.name,
            label: p.label || [p.installation_name, p.name].filter(Boolean).join(' · '),
        }));
    if (local.length || !navigator.onLine) {
        fillCombo('rev-post-list', local, 'Este cliente no tiene puestos', selectReviewPost);
        return;
    }
    try {
        const data = await api(`/supervision/posts?client_id=${reviewClient.id}&q=${encodeURIComponent(query || '')}`);
        const rows = (data.posts || []).map((p) => ({
            id: p.id,
            name: p.name,
            label: p.label || [p.installation_name, p.name].filter(Boolean).join(' · '),
        }));
        fillCombo('rev-post-list', rows, 'Este cliente no tiene puestos', selectReviewPost);
    } catch (e) {
        fillCombo('rev-post-list', local, 'Este cliente no tiene puestos', selectReviewPost);
        if (!ZentraOffline.isOfflineError(e)) setStatus(e.message, false);
    }
}

async function searchGuards(query) {
    const term = (query || '').replace(/\s+/g, '');
    if (term.length < 3) {
        reviewGuard = null;
        document.getElementById('rev-guard-name').value = '';
        document.getElementById('rev-guard-list').classList.add('hidden');
        return;
    }
    const local = (guardsCache || [])
        .filter((g) => String(g.document_number || '').startsWith(term))
        .slice(0, 20)
        .map((g) => ({
            id: g.id,
            name: g.name,
            document_number: g.document_number,
            label: `${g.document_number} · ${g.name}`,
        }));
    if (local.length || !navigator.onLine) {
        fillCombo('rev-guard-list', local, 'Sin vigilantes con esa cédula', selectReviewGuard);
        return;
    }
    try {
        const data = await api(`/supervision/guards?document=${encodeURIComponent(term)}`);
        const rows = (data.guards || []).map((g) => ({
            id: g.id,
            name: g.name,
            document_number: g.document_number,
            label: `${g.document_number} · ${g.name}`,
        }));
        fillCombo('rev-guard-list', rows, 'Sin vigilantes con esa cédula', selectReviewGuard);
    } catch (e) {
        fillCombo('rev-guard-list', local, 'Sin vigilantes con esa cédula', selectReviewGuard);
        if (!ZentraOffline.isOfflineError(e)) setStatus(e.message, false);
    }
}

async function persistReview() {
    if (closeQueued) throw new Error('Hay un cierre pendiente de envío.');
    if (!reviewClient) throw new Error('Seleccione el cliente.');
    if (!reviewPost) throw new Error('Seleccione el puesto.');
    if (!reviewGuard) throw new Error('Seleccione el vigilante por cédula.');
    if (!blobs.guard) throw new Error('Tome la foto del vigilante.');
    const missingMandatory = catalog.filter((mod) => mod.mandatory && !reviewDraftLogs.some((row) => row.module === mod.key));
    if (missingMandatory.length) {
        throw new Error(`Diligencie ${missingMandatory.map((mod) => mod.label.toLowerCase()).join(' y ')} antes de guardar la revista.`);
    }
    const pos = await geoRequired('Active la ubicación del dispositivo para guardar la revista.');
    if (!reviewEventId) reviewEventId = ZentraOffline.uuid();
    const clientEventId = reviewEventId;
    const outgoing = reviewDraftLogs.map((row) => ({ module: row.module, payload: row.payload }));
    const logPhotos = {};
    reviewDraftLogs.forEach((row, index) => {
        Object.entries(row.photos || {}).forEach(([slot, blob]) => {
            logPhotos[`${index}::${slot}`] = blob;
        });
    });
    const body = {
        client_id: String(reviewClient.id),
        supervisor_post_id: String(reviewPost.id),
        employee_id: String(reviewGuard.id),
        notes: document.getElementById('rev-notes').value || '',
        has_novelty: document.getElementById('rev-novelty').checked ? '1' : '0',
        latitude: String(pos.latitude),
        longitude: String(pos.longitude),
        logs: JSON.stringify(outgoing),
        recorded_at: new Date().toISOString(),
        app_build: String(APP_BUILD),
    };
    const queued = {
        type: 'review',
        clientEventId,
        body,
        files: { guard: blobs.guard, logPhotos },
    };
    try {
        const fd = reviewFormFromQueue(queued);
        const data = await api('/supervision/reviews', { method: 'POST', body: fd });
        currentReview = data.review;
        activity = data.activity || activity;
        reviewEventId = null;
        announceDiscarded(data);
    } catch (e) {
        if (!ZentraOffline.isOfflineError(e)) {
            if (e.status === 422 && /turno/i.test(e.message || '')) {
                await saveReviewSession();
                lastShiftCheck = 0;
                await recheckShift();
                throw new Error('Tu turno ya no está abierto (fin del horario o cierre desde el panel). Abre un turno nuevo; la revista sigue guardada en el teléfono.');
            }
            throw e;
        }
        await enqueueOrThrow(queued, 'Revista guardada en el teléfono. Se enviará al reconectar.');
        currentReview = { id: null, pending: true, client_id: reviewClient.id };
        reviewEventId = null;
    }
    blobs.guard = null;
    document.getElementById('snap-guard').classList.add('hidden');
    reviewDraftLogs = [];
    resetReviewForm();
    renderHub();
    await saveReviewSession();
    return currentReview;
}

function resetReviewForm() {
    reviewClient = null;
    reviewPost = null;
    reviewGuard = null;
    document.getElementById('rev-client-q').value = '';
    document.getElementById('rev-post-q').value = '';
    document.getElementById('rev-post-q').disabled = true;
    document.getElementById('rev-post-q').placeholder = 'Primero el cliente';
    document.getElementById('rev-guard-doc').value = '';
    document.getElementById('rev-guard-name').value = '';
    document.getElementById('rev-notes').value = '';
    const novelty = document.getElementById('rev-novelty');
    novelty.checked = false;
    novelty.disabled = false;
    novelty.dataset.manual = '0';
    document.getElementById('rev-novelty-why')?.classList.add('hidden');
}

function discardReviewSession() {
    reviewDraftLogs = [];
    currentReview = null;
    blobs.guard = null;
    trainingTimer = null;
    topicPick = null;
    document.body.classList.remove('training-lock');
    document.getElementById('snap-guard').classList.add('hidden');
    resetReviewForm();
    renderHub();
    saveReviewSession();
}

async function saveReview() {
    const btn = document.getElementById('btn-save-review');
    await withBusy(btn, 'Guardando revista…', async () => {
        await persistReview();
        if (!currentReview?.pending) setStatus('Revista guardada.');
        showOpsHome();
    });
}

function enterOps() {
    show('ops');
    showOpsHome();
}

async function loadCatalog() {
    try {
        const data = await api(`/supervision/catalog?app_build=${APP_BUILD}`);
        catalog = data.modules || [];
        const pack = await ZentraOffline.metaGet('pack') || {};
        pack.modules = catalog;
        await ZentraOffline.metaSet('pack', pack);
        renderHub();
    } catch (e) {
        await restorePack();
        if (!catalog.length) throw e;
        if (ZentraOffline.isOfflineError(e)) {
            setStatus('Sin cobertura. Catálogo del teléfono.');
            return;
        }
        throw e;
    }
}

async function loadIntake() {
    try {
        intake = await api('/supervision/intake');
        await ZentraOffline.metaSet('intake', intake);
        renderIntake();
    } catch (e) {
        intake = await ZentraOffline.metaGet('intake');
        if (intake) {
            renderIntake();
            if (ZentraOffline.isOfflineError(e)) {
                setStatus('Sin cobertura. Abrir turno necesita red.');
            }
            return;
        }
        throw e;
    }
}

async function loadCurrent() {
    try {
        const data = await api('/supervision/shifts/current');
        activity = data.activity;
        if (data.supervisor?.id && typeof ZentraOffline.setUser === 'function') {
            await ZentraOffline.setUser(data.supervisor.id, token());
        }
        const shift = data.shift;
        const previous = await ZentraOffline.metaGet('shift').catch(() => null);
        await ZentraOffline.metaSet('shift', shift || null);
        if (!shift && previous && !closeQueued) {
            shiftLost();
            return null;
        }
        const nameEl = document.getElementById('sup-name');
        if (nameEl) nameEl.textContent = data.supervisor?.name || '';
        if (data.supervisor?.has_selfie) {
            await loadSupervisorSelfie();
        }
        return applyShift(shift);
    } catch (e) {
        const shift = await ZentraOffline.metaGet('shift');
        if (shift && ZentraOffline.isOfflineError(e)) {
            setStatus('Sin cobertura. Turno en el teléfono.');
            return applyShift(shift);
        }
        throw e;
    }
}

/** El servidor ya no tiene el turno abierto (cierre automático por horario): no seguir capturando. */
function shiftLost() {
    stopPing();
    activity = null;
    document.getElementById('shift-label').textContent = 'Sin turno';
    show('open-shift');
    setStatus('Tu turno ya está cerrado (fin del horario o cierre desde el panel). Abre un turno nuevo para seguir; la revista en curso queda guardada en el teléfono.', false);
}

let lastShiftCheck = 0;

/** Revisa el turno en el servidor (como mucho cada 30 s) cuando una captura dice que no hay turno abierto. */
async function recheckShift() {
    if (Date.now() - lastShiftCheck < 30000) return;
    lastShiftCheck = Date.now();
    try { await loadCurrent(); } catch (e) { /* sin red: se revisa en el próximo intento */ }
}

function applyShift(shift) {
    if (!shift) {
        document.getElementById('shift-label').textContent = 'Sin turno';
        stopPing();
        return null;
    }
    clearOwnCloseLock();
    document.getElementById('shift-label').textContent = 'Turno abierto';
    const meta = {
        'sup-schedule': shift.schedule_label,
        'sup-zone': shift.route_zone,
        'sup-plate': shift.fleet_vehicle?.plate,
        'sup-km': shift.km_start != null ? `${Number(shift.km_start).toLocaleString('es-CO')} km` : null,
    };
    Object.entries(meta).forEach(([id, value]) => {
        document.getElementById(id).textContent = value || '—';
    });
    document.getElementById('km-end').value = shift.km_start || '';
    renderHub();
    startPing();
    return shift;
}

function setSupervisorAvatar(blob) {
    if (!blob || !blob.size) {
        return Promise.resolve();
    }
    const img = document.getElementById('sup-avatar');
    if (!img) {
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = () => {
            img.src = reader.result;
            resolve();
        };
        reader.onerror = () => resolve();
        reader.readAsDataURL(blob);
    });
}

async function loadSupervisorSelfie() {
    try {
        const res = await fetch(`${apiBase()}/supervision/shift-photo/start-selfie`, {
            headers: { Authorization: `Bearer ${token()}`, Accept: 'image/*' },
        });
        if (!res.ok) return;
        const blob = await res.blob();
        await setSupervisorAvatar(blob);
    } catch (e) {
        // sin foto de perfil
    }
}

function shiftTrackingPlugin() {
    return window.Capacitor?.Plugins?.ShiftTracking || null;
}

function screenIsOn() {
    return document.visibilityState === 'visible';
}

async function startNativeTracking() {
    const plug = shiftTrackingPlugin();
    if (!plug?.start) return false;
    try {
        await plug.start({ apiBase: apiBase(), token: token() || '', userId: String(ZentraOffline.currentUserId?.() || '') });
        return true;
    } catch (e) {
        setStatus(e?.message || 'GPS nativo no disponible. Sigue el ping de la app.', false);
        return false;
    }
}

function stopNativeTracking() {
    const plug = shiftTrackingPlugin();
    if (!plug?.stop) return;
    plug.stop().catch(() => {});
}

function startPing() {
    stopPing();
    if (closeQueued) return;
    nativePing = false;
    const send = async (force = false) => {
        if (closeQueued || nativePing) return;
        const now = Date.now();
        if (!force && now - lastPingSentAt < 12000) return;
        if (pingBusy) return;
        pingBusy = true;
        try {
            const pos = await geo();
            if (!pos) return;
            lastPingSentAt = Date.now();
            const stats = offlineReady() && ZentraOffline.outboxStats
                ? await ZentraOffline.outboxStats()
                : { mine: 0 };
            const body = {
                latitude: pos.latitude,
                longitude: pos.longitude,
                accuracy: pos.accuracy,
                client_event_id: ZentraOffline.uuid(),
                pending_outbox: Number(stats.mine || 0),
                screen_on: screenIsOn(),
                source: 'app',
                recorded_at: new Date(pos.timestamp && Date.now() - pos.timestamp < 60000 ? pos.timestamp : Date.now()).toISOString(),
            };
            try {
                await api('/supervision/shifts/ping', { method: 'POST', body: JSON.stringify(body) });
            } catch (e) {
                if (ZentraOffline.isOfflineError(e)) {
                    await ZentraOffline.enqueue({ type: 'ping', clientEventId: body.client_event_id, body });
                    await updateSyncBar();
                } else if (e.status === 422) {
                    await recheckShift();
                }
            }
        } finally {
            pingBusy = false;
        }
    };
    const boot = async () => {
        if (isNativeApp()) {
            nativePing = await startNativeTracking();
        }
        if (!nativePing) {
            send(true);
            pingTimer = setInterval(() => {
                if (document.visibilityState === 'hidden') return;
                send();
            }, 15000);
        }
        if (navigator.geolocation?.watchPosition) {
            pingWatchId = navigator.geolocation.watchPosition(
                (pos) => {
                    lastGeo = {
                        latitude: pos.coords.latitude,
                        longitude: pos.coords.longitude,
                        accuracy: pos.coords.accuracy,
                        timestamp: pos.timestamp,
                    };
                    if (!nativePing) send();
                },
                () => {},
                { enableHighAccuracy: true, timeout: 20000, maximumAge: 10000 },
            );
        }
    };
    boot();
}

function stopPing() {
    nativePing = false;
    stopNativeTracking();
    if (pingTimer) clearInterval(pingTimer);
    pingTimer = null;
    if (pingWatchId != null && navigator.geolocation) {
        navigator.geolocation.clearWatch(pingWatchId);
        pingWatchId = null;
    }
}

function logout(opts = {}) {
    flushDrafts();
    draftsReady = false;
    stopPing();
    const uid = ZentraOffline.currentUserId?.() || null;
    const tok = token();
    if (uid && tok) ZentraOffline.rememberUser?.(uid, tok);
    localStorage.removeItem('token');
    if (typeof ZentraOffline.setUser === 'function') {
        ZentraOffline.setUser(null);
    }
    show('login');
    setStatus('Sesión cerrada.');
    if (!opts.skipFlush) flushOutbox();
}

async function afterLogin(data = {}) {
    const name = data.user?.name;
    setStatus(name ? `Hola ${name}` : 'Sesión activa');
    if (data.must_change_password) {
        const temp = document.getElementById('temp-username');
        if (temp) temp.textContent = data.user?.username || '';
        show('change-password');
        return;
    }
    await restorePack();
    let uiRestored = false;
    const cachedShift = await ZentraOffline.metaGet('shift').catch(() => null);
    if (cachedShift && catalog.length) {
        // Entrar de una vez con lo del teléfono; el servidor se consulta por detrás.
        applyShift(cachedShift);
        enterOps();
        await restoreUi();
        uiRestored = true;
    }
    try {
        await Promise.all([loadSites(), loadIntake()]);
        if (!catalog.length) await loadCatalog();
    } catch (e) {
        if (!ZentraOffline.isOfflineError(e)) {
            setStatus(e.message, false);
            if (!uiRestored) show('login');
            return;
        }
    }
    let shift = null;
    try {
        shift = await loadCurrent();
    } catch (e) {
        if (!ZentraOffline.isOfflineError(e)) {
            setStatus(e.message, false);
            return;
        }
        shift = await ZentraOffline.metaGet('shift');
        if (shift) applyShift(shift);
    }
    await updateSyncBar();
    flushOutbox();
    if (shift) {
        if (!uiRestored) {
            enterOps();
            await restoreUi();
        } else {
            renderHub();
        }
        return;
    }
    draftsReady = true;
    if (!document.getElementById('open-shift').classList.contains('hidden')) return;
    show('open-shift');
}

/** Reabre la pantalla donde quedó: revista o módulo en curso, Observatorio o cierre de turno. */
async function restoreUi() {
    draftsReady = false;
    try {
        let screen = 'home';
        try { screen = (await ZentraOffline.metaGet('uiScreen')) || 'home'; } catch (e) { screen = 'home'; }
        const session = await ZentraOffline.metaGet('reviewSession').catch(() => null);
        if (session) {
            await restoreReviewSession();
            if (session.open?.key || screen === 'review') return;
        }
        if (screen === 'observatory') {
            showObservatory();
            await restoreObsDraft();
            return;
        }
        if (screen === 'close-shift') {
            show('close-shift');
            await restoreCloseDraft();
        }
    } finally {
        draftsReady = true;
    }
}

bindCameras();
(() => {
    const home = document.getElementById('ops-home');
    const card = document.getElementById('sup-card');
    if (!home || !card) return;
    const sync = () => card.classList.toggle('hidden', home.classList.contains('hidden'));
    new MutationObserver(sync).observe(home, { attributes: true, attributeFilter: ['class'] });
    sync();
})();
bindReviewUi();
bindObsPhotoGrid();
bindDraftAutosave();
document.querySelectorAll('[data-collapse]').forEach((btn) => {
    btn.onclick = () => {
        const body = document.getElementById(btn.dataset.collapse);
        if (!body) return;
        const open = !body.classList.contains('hidden');
        body.classList.toggle('hidden', open);
        btn.classList.toggle('is-open', !open);
    };
});

document.getElementById('btn-login').onclick = () => withBusy(
    document.getElementById('btn-login'),
    'Entrando…',
    async () => {
        localStorage.removeItem('API_URL');
        const pos = await geo();
        const payload = {
            login: document.getElementById('login-user').value.trim(),
            password: document.getElementById('password').value,
            device_name: 'supervision-pwa',
            device_id: deviceId(),
            device_label: await deviceLabel(),
            latitude: pos?.latitude ?? null,
            longitude: pos?.longitude ?? null,
        };
        let data;
        try {
            data = await api('/supervision/login', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
        } catch (err) {
            if (err.status !== 423) throw err;
            showDeviceWait(err.message);
            return;
        }
        hideDeviceWait();
        if (!offlineReady()) {
            throw new Error('App desactualizada. Recargue o borre datos del sitio.');
        }
        const prevTok = token();
        const prevId = localStorage.getItem('sup_user_id');
        if (prevId && prevTok) ZentraOffline.rememberUser(prevId, prevTok);
        localStorage.setItem('token', data.token);
        await ZentraOffline.setUser(data.user?.id, data.token);
        await afterLogin(data);
    },
);

document.getElementById('btn-change-password').onclick = () => withBusy(
    document.getElementById('btn-change-password'),
    'Guardando…',
    async () => {
        const username = document.getElementById('new-username').value.trim();
        const password = document.getElementById('new-password').value;
        const confirm = document.getElementById('new-password-confirm').value;
        if (!username) throw new Error('Escriba el nuevo usuario.');
        if (password !== confirm) throw new Error('Las contraseñas no coinciden.');
        await api('/supervision/password', {
            method: 'POST',
            body: JSON.stringify({
                username,
                password,
                password_confirmation: confirm,
            }),
        });
        await afterLogin({ user: { name: '' }, must_change_password: false });
    },
);

document.getElementById('btn-start').onclick = () => withBusy(
    document.getElementById('btn-start'),
    'Iniciando turno…',
    async () => {
        if (!blobs.odo || !blobs.self) throw new Error('Tome foto del odómetro y selfie de inicio.');
        const fd = new FormData();
        fd.append('shift_template_id', document.getElementById('shift-template').value);
        fd.append('zone_id', document.getElementById('route-zone').value);
        fd.append('km_start', document.getElementById('km-start').value);
        fd.append('ppe_checklist', JSON.stringify(readChecks('ppe')));
        fd.append('vehicle_checklist', JSON.stringify(readChecks('vcheck')));
        const vehicleId = document.getElementById('fleet-vehicle').value;
        if (vehicleId) fd.append('vehicle_id', vehicleId);
        else {
            fd.append('vehicle[plate]', document.getElementById('v-plate').value);
            fd.append('vehicle[brand]', document.getElementById('v-brand').value);
            fd.append('vehicle[line]', document.getElementById('v-line').value);
            fd.append('vehicle[model]', document.getElementById('v-model').value);
            fd.append('vehicle[soat_expires_at]', document.getElementById('v-soat').value);
            fd.append('vehicle[technical_review_expires_at]', document.getElementById('v-tm').value);
        }
        if (!navigator.onLine) throw new Error('Abrir turno necesita internet. Conéctese y vuelva a intentar.');
        fd.append('odometer_photo', blobs.odo, 'odometer.jpg');
        fd.append('selfie_photo', blobs.self, 'selfie.jpg');
        await api('/supervision/shifts/open', { method: 'POST', body: fd });
        await clearOwnCloseLock();
        const startSelfie = blobs.self;
        blobs.odo = blobs.self = null;
        if (startSelfie) {
            await setSupervisorAvatar(startSelfie);
        }
        try { await refreshOfflinePack(); } catch (e) { /* el turno ya abrió */ }
        await loadCurrent();
        enterOps();
        setStatus('Turno iniciado. Clientes y catálogo en el teléfono.');
        await restoreUi();
    },
);

document.getElementById('btn-go-close').onclick = () => {
    if (trainingRunning()) {
        setStatus('Termine o cancele la capacitación antes de cerrar el turno.', false);
        return;
    }
    show('close-shift');
};

document.getElementById('btn-close-back').onclick = () => {
    show('ops');
    showOpsHome();
};

document.getElementById('btn-close').onclick = () => withBusy(
    document.getElementById('btn-close'),
    'Cerrando turno…',
    async () => {
        if (!blobs.odoEnd || !blobs.selfEnd) throw new Error('Tome foto del odómetro y selfie de cierre.');
        if (!closeEventId) closeEventId = ZentraOffline.uuid();
        const clientEventId = closeEventId;
        const openShift = await ZentraOffline.metaGet('shift').catch(() => null);
        const shiftId = openShift?.id ? String(openShift.id) : '';
        const fd = new FormData();
        fd.append('km_end', document.getElementById('km-end').value);
        fd.append('client_event_id', clientEventId);
        if (shiftId) fd.append('shift_id', shiftId);
        fd.append('odometer_photo', blobs.odoEnd, 'odometer-end.jpg');
        fd.append('selfie_photo', blobs.selfEnd, 'selfie-end.jpg');
        try {
            await api('/supervision/shifts/close', { method: 'POST', body: fd });
            closeEventId = null;
            blobs.odoEnd = blobs.selfEnd = null;
            stopPing();
            activity = null;
            currentReview = null;
            reviewDraftLogs = [];
            await ZentraOffline.metaSet('shift', null);
            await ZentraOffline.metaSet('uiScreen', 'home').catch(() => {});
            await clearCloseDraft();
            setStatus('Turno cerrado.');
            logout();
            return;
        } catch (e) {
            if (!ZentraOffline.isOfflineError(e)) throw e;
            await ZentraOffline.enqueue({
                type: 'close',
                clientEventId,
                body: { km_end: document.getElementById('km-end').value, shift_id: shiftId },
                files: { odometer: blobs.odoEnd, selfie: blobs.selfEnd },
            });
            closeEventId = null;
            closeQueued = true;
            await ZentraOffline.metaSet('closeQueued', true);
            blobs.odoEnd = blobs.selfEnd = null;
            stopPing();
            await clearCloseDraft();
            await updateSyncBar();
            show('ops');
            showOpsHome();
            setStatus('Cierre guardado en el teléfono. Al reconectar se envía. No borre datos del sitio.');
        }
    },
);

document.getElementById('btn-submit').onclick = () => withBusy(
    document.getElementById('btn-submit'),
    'Registrando…',
    async () => {
        if (!currentModule) return;
        if (closeQueued) throw new Error('Hay un cierre pendiente de envío.');
        const payload = readPayload();
        assertDraftPayload(currentModule, payload);
        if (currentModule.hangs_off_review) {
            if (currentModule.key === 'recommendations'
                && reviewDraftLogs.some((row) => row.module === 'recommendations')) {
                throw new Error('Ya hay recomendaciones en este puesto (hasta 3 en un registro).');
            }
            const draft = { module: currentModule.key, payload };
            if (currentModule.key === 'weapons') {
                draft.photos = { ...modulePhotos };
            }
            if (currentModule.single) {
                const slots = (currentModule.fields || [])
                    .filter((field) => field.type === 'photo_grid')
                    .flatMap((field) => field.slots || [])
                    .map((slot) => slot.key);
                const keep = currentModule.key !== 'uniform' || payload.novelty === 'yes';
                draft.photos = keep
                    ? Object.fromEntries(slots.filter((key) => modulePhotos[key]).map((key) => [key, modulePhotos[key]]))
                    : {};
                reviewDraftLogs = reviewDraftLogs.filter((row) => row.module !== currentModule.key);
            }
            if (currentModule.key === 'recommendations') {
                const field = (currentModule.fields || []).find((item) => item.type === 'repeatable');
                draft.photos = collectRepeatablePhotos(field);
            }
            modulePhotos = {};
            reviewDraftLogs.push(draft);
            if (currentModule.key === 'trainings') {
                trainingTimer = null;
                document.body.classList.remove('training-lock');
            }
            renderHub();
            setStatus(`${currentModule.label} registrada en la revista.`);
            showReview();
            saveReviewSession();
            return;
        }
        const pos = currentModule.requires_gps
            ? await geoRequired('Active la ubicación del dispositivo para registrar este módulo.')
            : await geo();
        if (!logEventId) logEventId = ZentraOffline.uuid();
        const body = {
            module: currentModule.key,
            payload,
            latitude: pos?.latitude,
            longitude: pos?.longitude,
            client_event_id: logEventId,
            recorded_at: new Date().toISOString(),
        };
        if (currentModule.requires_client) {
            const clientId = siteId();
            if (!clientId) throw new Error('Seleccione el cliente.');
            body.client_id = clientId;
        }
        try {
            await api('/supervision/logs', { method: 'POST', body: JSON.stringify(body) });
            logEventId = null;
            setStatus(`${currentModule.label} registrado.`);
            try { await loadCurrent(); } catch (e) { /* cola / offline */ }
        } catch (e) {
            if (!ZentraOffline.isOfflineError(e)) throw e;
            await enqueueOrThrow(
                { type: 'log', clientEventId: body.client_event_id, body },
                `${currentModule.label} guardado en el teléfono. Se enviará al reconectar.`,
            );
            logEventId = null;
        }
        showOpsHome();
    },
);

function assertDraftPayload(mod, payload) {
    if (mod.key === 'folders' && !payload.status) throw new Error('Indique el estado de la carpeta.');
    if (mod.key === 'uniform') {
        const field = (mod.fields || []).find((f) => f.type === 'checklist');
        const missing = (payload.items || []).filter((row) => !row.status)
            .map((row) => (field?.items || []).find((item) => Number(item.value) === row.item_id)?.label)
            .filter(Boolean);
        if (missing.length) throw new Error(`Marque todas las prendas. Falta: ${missing.join(', ')}.`);
        if (payload.novelty === 'yes') {
            if (!payload.notes) throw new Error('Describa la novedad de la presentación personal.');
            if (!['uniform_1', 'uniform_2', 'uniform_3'].some((key) => modulePhotos[key])) {
                throw new Error('Tome al menos una foto de la novedad.');
            }
        }
    }
    if (mod.key === 'trainings') {
        if (!reviewGuard?.id) throw new Error('Seleccione primero el vigilante en la revista.');
        if (!payload.kind) throw new Error('Indique si la capacitación es mensual o personalizada.');
        if (!payload.modality) throw new Error('Indique si la capacitación es virtual o en puesto.');
        if (!topicPick) throw new Error('Elija o cree el tema de la capacitación.');
        if (trainingDup) throw new Error(trainingDup);
        if (payload.kind === 'custom' && !payload.reason) throw new Error('Indique el motivo o la falencia de la capacitación personalizada.');
        if (payload.modality === 'onsite') {
            if (!payload.description) throw new Error('Describa la capacitación dada en el puesto.');
            if (trainingRunning()) throw new Error('Termine la capacitación antes de registrarla.');
            if (!payload.started_at || !payload.ended_at) throw new Error('Inicie y termine la capacitación para registrar la duración.');
        }
        if (!['training_1', 'training_2', 'training_3'].some((key) => modulePhotos[key])) {
            throw new Error('Tome al menos una foto de evidencia de la capacitación.');
        }
    }
    if (mod.key === 'inventory') {
        const items = payload.items || [];
        if (!items.length) throw new Error('Agregue al menos un elemento.');
        if (items.some((item) => !item.type)) throw new Error('Indique el tipo de cada elemento.');
        if (items.some((item) => !item.status)) throw new Error('Indique el estado de cada elemento.');
    }
    if (mod.key === 'documents') {
        const items = payload.items || [];
        if (!items.length) throw new Error('Agregue al menos un documento.');
        if (items.some((item) => !item.document_type_id)) throw new Error('Indique el tipo de cada documento.');
        if (items.some((item) => (Number(item.delivered) || 0) + (Number(item.pending) || 0) < 1)) {
            throw new Error('Indique al menos una cantidad entregada o pendiente.');
        }
    }
    if (mod.key === 'weapons') {
        if (!payload.weapon_type_id) throw new Error('Seleccione el tipo de arma.');
        if (!payload.weapon_brand_id) throw new Error('Seleccione la marca.');
        if (!payload.serial) throw new Error('Indique el serial observado.');
        if (!payload.caliber) throw new Error('Indique el calibre.');
        if (!payload.permit_kind) throw new Error('Seleccione el tipo de permiso.');
        if (!payload.permit_number) throw new Error('Indique el número de permiso.');
        if (!payload.permit_expires_at) throw new Error('Indique el vencimiento del permiso.');
        if (payload.ammo_quantity == null) throw new Error('Indique la cantidad de munición.');
        if (!payload.ammo_caliber) throw new Error('Indique el calibre de munición.');
        if (payload.novelty !== 'yes' && payload.novelty !== 'no') throw new Error('Indique si el arma tiene novedad.');
        if (payload.novelty === 'yes' && !payload.notes) throw new Error('Describa la novedad del arma.');
        if (payload.cleaned !== 'yes' && payload.cleaned !== 'no') throw new Error('Indique si realizó aseo del arma.');
        const idSlots = (mod.fields || []).filter((field) => field.type === 'photo_grid' && !field.show_when)
            .flatMap((field) => field.slots || []);
        if (idSlots.some((slot) => !modulePhotos[slot.key])) throw new Error('Tome las cinco fotos de identificación del arma.');
        if (payload.cleaned === 'yes' && !modulePhotos.cleaning) throw new Error('Tome la foto de aseo del arma.');
    }
    if (mod.key === 'alarms') {
        if (!payload.alarm_type_id) throw new Error('Seleccione el tipo de alarma.');
        if (!payload.kind) throw new Error('Indique si es prueba o atención.');
        if (!payload.result) throw new Error('Indique el resultado.');
    }
    if (mod.key === 'supports') {
        if (!payload.support_type_id) throw new Error('Seleccione el tipo de apoyo.');
        if (payload.novelty !== 'yes' && payload.novelty !== 'no') throw new Error('Indique si el apoyo fue por una novedad.');
        if (!payload.reason) throw new Error('Indique el motivo del apoyo.');
    }
    if (mod.key === 'control_books') {
        const items = payload.items || [];
        if (!items.length) throw new Error('Agregue al menos un libro.');
        if (items.some((item) => !item.control_book_type_id)) throw new Error('Indique el tipo de cada libro.');
        if (items.some((item) => item.novelty !== 'yes' && item.novelty !== 'no')) {
            throw new Error('Indique si cada libro tiene novedad.');
        }
    }
    if (mod.key === 'recommendations') {
        const items = payload.items || [];
        if (!items.length) throw new Error('Agregue al menos una recomendación.');
        if (items.length > 3) throw new Error('Máximo tres recomendaciones por puesto.');
        items.forEach((item, i) => {
            const n = i + 1;
            if (!item.risk_type_id) throw new Error(`Indique el tipo de riesgo de la recomendación ${n}.`);
            if (!item.risk) throw new Error(`Describa el riesgo de la recomendación ${n}.`);
            if (!item.likelihood) throw new Error(`Indique la probabilidad de la recomendación ${n}.`);
            if (!item.impact) throw new Error(`Indique el impacto de la recomendación ${n}.`);
            if (!item.consequence) throw new Error(`Indique la consecuencia de la recomendación ${n}.`);
            if (!item.treatment) throw new Error(`Indique la recomendación ${n}.`);
        });
        const field = (mod.fields || []).find((item) => item.type === 'repeatable');
        const photoField = (field?.item_fields || []).find((item) => item.type === 'photo_grid');
        const slots = photoField?.slots || [];
        [...document.querySelectorAll(`#rep-${field.name} .item-card`)].forEach((card, i) => {
            if (slots.some((slot) => !modulePhotos[`${card.dataset.index}_${slot.key}`])) {
                throw new Error(`Tome las tres fotos del riesgo en la recomendación ${i + 1}.`);
            }
        });
    }
}

document.getElementById('btn-mod-back').onclick = () => {
    if (trainingRunning()) {
        if (!window.confirm('La capacitación está en curso. ¿Cancelarla? Se perderá lo registrado de la capacitación; la revista sigue.')) {
            setStatus('Termine o cancele la capacitación antes de salir.', false);
            return;
        }
        cancelTraining();
        setStatus('Capacitación cancelada. Puede seguir con la revista.');
    }
    if (moduleReturn === 'review') showReview();
    else showOpsHome();
    saveReviewSession();
};
document.getElementById('btn-open-review').onclick = showReview;
document.getElementById('btn-review-back').onclick = () => {
    const hadDrafts = reviewDraftLogs.length > 0;
    discardReviewSession();
    showOpsHome();
    if (hadDrafts) {
        setStatus('Revista cancelada. No se guardó nada de los módulos.');
    }
};
document.getElementById('btn-open-alarms').onclick = () => openModule('alarms', 'home');
document.getElementById('btn-open-supports').onclick = () => openModule('supports', 'home');
document.getElementById('btn-open-documents').onclick = () => openModule('documents', 'home');

function showObservatory() {
    document.getElementById('ops-home').classList.add('hidden');
    document.getElementById('review-card').classList.add('hidden');
    document.getElementById('module-card').classList.add('hidden');
    document.getElementById('sheets-card')?.classList.add('hidden');
    hideGeoCard();
    document.getElementById('observatory-card').classList.remove('hidden');
    loadObservatoryKinds();
    if (obsSite) placeObsPin(obsSite);
    rememberScreen('observatory');
}

function fillObsKinds(kinds) {
    const select = document.getElementById('obs-kind');
    if (!select) return;
    const map = kinds && Object.keys(kinds).length ? kinds : obsKinds;
    select.innerHTML = '<option value="">Seleccione…</option>'
        + Object.entries(map || {}).map(([value, label]) => `<option value="${value}">${label}</option>`).join('');
}

function kindsForSite(site) {
    const clientId = site?.client_id;
    if (clientId && obsKindsByClient[clientId]) {
        return obsKindsByClient[clientId];
    }
    return obsKinds;
}

function rememberObsMaps(data) {
    const key = data?.google_maps?.api_key;
    if (key) obsMapsKey = key;
    if (data?.google_maps?.center && !obsPin) {
        obsPin = {
            lat: Number(data.google_maps.center.lat),
            lng: Number(data.google_maps.center.lng),
        };
    }
}

function loadGoogleMaps() {
    if (window.google?.maps) return Promise.resolve();
    if (!obsMapsKey) return Promise.reject(new Error('Sin mapa'));
    if (obsMapsPromise) return obsMapsPromise;
    obsMapsPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(obsMapsKey)}`;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => {
            obsMapsPromise = null;
            reject(new Error('No se pudo cargar el mapa.'));
        };
        document.head.appendChild(script);
    });
    return obsMapsPromise;
}

async function placeObsPin(site) {
    const hint = document.getElementById('obs-map-hint');
    const mapEl = document.getElementById('obs-map');
    const lat = site?.lat != null ? Number(site.lat) : (obsPin?.lat ?? lastGeo?.latitude);
    const lng = site?.lng != null ? Number(site.lng) : (obsPin?.lng ?? lastGeo?.longitude);
    if (lat == null || lng == null || Number.isNaN(lat) || Number.isNaN(lng)) {
        mapEl.classList.add('hidden');
        hint.textContent = 'Este colegio no tiene pin. El reporte queda sin mapa o con el GPS del turno.';
        obsPin = null;
        return;
    }
    obsPin = { lat, lng };
    hint.textContent = 'Arrastre el pin o toque el mapa. Si no lo mueve, queda en el colegio.';
    if (!obsMapsKey) {
        mapEl.classList.add('hidden');
        return;
    }
    try {
        await loadGoogleMaps();
    } catch {
        mapEl.classList.add('hidden');
        return;
    }
    mapEl.classList.remove('hidden');
    const pos = { lat, lng };
    if (!obsMap) {
        obsMap = new google.maps.Map(mapEl, {
            center: pos,
            zoom: 17,
            mapTypeId: 'hybrid',
            disableDefaultUI: true,
            zoomControl: true,
        });
        obsMap.addListener('click', (ev) => {
            if (!ev.latLng) return;
            obsPin = { lat: ev.latLng.lat(), lng: ev.latLng.lng() };
            obsMarker?.setPosition(ev.latLng);
        });
    } else {
        obsMap.setCenter(pos);
    }
    if (!obsMarker) {
        obsMarker = new google.maps.Marker({
            map: obsMap,
            position: pos,
            draggable: true,
        });
        obsMarker.addListener('dragend', () => {
            const p = obsMarker.getPosition();
            if (!p) return;
            obsPin = { lat: p.lat(), lng: p.lng() };
        });
    } else {
        obsMarker.setPosition(pos);
    }
    google.maps.event.trigger(obsMap, 'resize');
    obsMap.setCenter(pos);
}

async function loadObservatoryKinds() {
    const select = document.getElementById('obs-kind');
    if (select.options.length > 1 && obsMapsKey) return;
    try {
        const data = await api('/supervision/observatory/sites');
        obsKinds = data.kinds || {};
        if (data.kinds_by_client) obsKindsByClient = data.kinds_by_client;
        rememberObsMaps(data);
        fillObsKinds(obsKinds);
    } catch (e) {
        setStatus(e.message, false);
    }
}

document.getElementById('obs-q').addEventListener('input', () => {
    clearTimeout(obsSearchTimer);
    obsSearchTimer = setTimeout(searchObservatorySites, 250);
});

function filterObservatoryCache(q) {
    const term = q.toLowerCase();
    return (obsSitesCache || []).filter((row) => (
        String(row.name || '').toLowerCase().includes(term)
        || String(row.dane_code || '').toLowerCase().includes(term)
    )).slice(0, 30);
}

function renderObservatoryList(rows) {
    const list = document.getElementById('obs-list');
    list.innerHTML = rows.map((row) => `
        <button type="button" data-id="${row.id}">${row.name}${row.dane_code ? ` · DANE ${row.dane_code}` : ''}</button>
    `).join('') || '<p class="hint">Sin colegios</p>';
    list.classList.remove('hidden');
    list.querySelectorAll('button').forEach((btn) => {
        btn.onclick = () => {
            const found = rows.find((row) => String(row.id) === btn.dataset.id);
            obsSite = found || null;
            document.getElementById('obs-q').value = found?.name || '';
            document.getElementById('obs-picked').textContent = found
                ? `Elegido: ${found.name}${found.client ? ` · ${found.client}` : ''}`
                : '';
            fillObsKinds(kindsForSite(found));
            list.classList.add('hidden');
            placeObsPin(found);
        };
    });
}

async function searchObservatorySites() {
    const q = document.getElementById('obs-q').value.trim();
    const list = document.getElementById('obs-list');
    if (q.length < 2) {
        list.classList.add('hidden');
        list.innerHTML = '';
        return;
    }
    const cached = filterObservatoryCache(q);
    if (cached.length) {
        renderObservatoryList(cached);
        return;
    }
    try {
        const data = await api(`/supervision/observatory/sites?q=${encodeURIComponent(q)}`);
        const rows = data.sites || [];
        rememberObsMaps(data);
        if (data.kinds_by_client) obsKindsByClient = { ...obsKindsByClient, ...data.kinds_by_client };
        if (data.kinds && Object.keys(obsKinds).length === 0) {
            obsKinds = data.kinds;
            fillObsKinds(obsKinds);
        }
        renderObservatoryList(rows);
    } catch (e) {
        setStatus(e.message, false);
    }
}

document.getElementById('obs-anonymous').addEventListener('change', () => {
    document.getElementById('obs-anon-warn').classList.toggle('hidden', !document.getElementById('obs-anonymous').checked);
});

document.getElementById('btn-obs-send').onclick = () => withBusy(
    document.getElementById('btn-obs-send'),
    'Enviando…',
    async () => {
        if (closeQueued) throw new Error('Hay un cierre pendiente de envío.');
        if (!obsSite?.id) throw new Error('Elija el colegio.');
        const kind = document.getElementById('obs-kind').value;
        const body = document.getElementById('obs-body').value.trim();
        if (!kind) throw new Error('Indique el tipo.');
        if (body.length < 10) throw new Error('Describa qué pasó (mínimo 10 caracteres).');
        const photos = obsPhotoBlobs();
        if (!photos.length) throw new Error('Tome al menos una foto.');
        const pos = obsPin || await geo();
        const payload = {
            installation_id: obsSite.id,
            kind,
            body,
            is_anonymous: document.getElementById('obs-anonymous').checked ? 1 : 0,
            latitude: pos?.lat ?? pos?.latitude,
            longitude: pos?.lng ?? pos?.longitude,
            recorded_at: new Date().toISOString(),
        };
        const fd = new FormData();
        Object.entries(payload).forEach(([key, value]) => {
            if (value == null || value === '') return;
            fd.append(key, String(value));
        });
        photos.forEach((blob, i) => fd.append('photos[]', blob, `observatory-${i + 1}.jpg`));
        try {
            const data = await api('/supervision/observatory/reports', { method: 'POST', body: fd });
            setStatus(data.report?.folio ? `Reporte ${data.report.folio} enviado.` : 'Reporte enviado.');
        } catch (e) {
            if (!ZentraOffline.isOfflineError(e)) throw e;
            await enqueueOrThrow(
                { type: 'observatory', body: payload, files: { photos } },
                'Observatorio guardado en el teléfono. Se enviará al reconectar.',
            );
        }
        document.getElementById('obs-body').value = '';
        document.getElementById('obs-anonymous').checked = false;
        document.getElementById('obs-anon-warn').classList.add('hidden');
        resetObsPhotos();
        await clearObsDraft();
        showOpsHome();
    },
);

document.getElementById('btn-open-observatory').onclick = () => {
    showObservatory();
    if (!obsSite && !document.getElementById('obs-body').value.trim()) restoreObsDraft();
};
document.getElementById('btn-panic').onclick = async () => {
    try {
        await api('/supervision/panic', {
            method: 'POST',
            body: JSON.stringify({
                latitude: lastGeo?.latitude ?? null,
                longitude: lastGeo?.longitude ?? null,
            }),
        });
        statusEl.textContent = 'Pánico enviado a la empresa.';
    } catch (err) {
        statusEl.textContent = err.message || 'No se pudo enviar el pánico.';
    }
};
document.getElementById('btn-obs-back').onclick = showOpsHome;
document.getElementById('btn-open-sheets').onclick = () => {
    showSheets();
};
document.getElementById('btn-sheets-back').onclick = showOpsHome;

var GEO_MAX_ACCURACY = 30;
var geoWatchId = null;
var geoFix = null;
var geoClient = null;
var geoInstallation = null;
var geoInstallations = [];

function hideGeoCard() {
    document.getElementById('geo-card')?.classList.add('hidden');
    if (geoWatchId !== null && navigator.geolocation) {
        navigator.geolocation.clearWatch(geoWatchId);
    }
    geoWatchId = null;
}

function geoMeters(a, b) {
    const rad = (d) => (d * Math.PI) / 180;
    const dLat = rad(b.lat - a.lat);
    const dLng = rad(b.lng - a.lng);
    const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) ** 2;
    return 6371000 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
}

function geoDistanceLabel(m) {
    return m >= 1000 ? `${(m / 1000).toFixed(1).replace('.', ',')} km` : `${Math.round(m)} m`;
}

function resetGeoForm() {
    geoClient = null;
    geoInstallation = null;
    geoInstallations = [];
    document.getElementById('geo-client-q').value = '';
    document.getElementById('geo-inst-q').value = '';
    document.getElementById('geo-inst-wrap').classList.add('hidden');
    document.getElementById('geo-picked').textContent = '';
    renderGeoFix();
}

function renderGeoFix() {
    const box = document.getElementById('geo-fix');
    const send = document.getElementById('btn-geo-send');
    box.classList.toggle('hidden', !geoInstallation);
    if (!geoInstallation) {
        send.disabled = true;
        return;
    }
    const acc = document.getElementById('geo-accuracy');
    const dist = document.getElementById('geo-distance');
    document.getElementById('geo-pending').textContent = geoInstallation.pending
        ? 'Ya hay una solicitud pendiente para esta instalación. Si envía otra, reemplaza la anterior.'
        : '';
    if (!geoFix) {
        acc.textContent = 'Buscando GPS…';
        acc.className = '';
        dist.textContent = 'Active la ubicación y espere unos segundos al aire libre.';
        send.disabled = true;
        return;
    }
    const accuracy = Math.round(geoFix.accuracy);
    const good = accuracy <= GEO_MAX_ACCURACY;
    acc.textContent = `Precisión del GPS: ±${accuracy} m${good ? '' : ` (necesita ±${GEO_MAX_ACCURACY} m o menos)`}`;
    acc.className = good ? 'ok' : 'err';
    if (geoInstallation.latitude != null && geoInstallation.longitude != null) {
        const meters = geoMeters(
            { lat: geoFix.latitude, lng: geoFix.longitude },
            { lat: Number(geoInstallation.latitude), lng: Number(geoInstallation.longitude) },
        );
        dist.textContent = `Está a ${geoDistanceLabel(meters)} del punto registrado de la instalación.`;
    } else {
        dist.textContent = 'La instalación aún no tiene ubicación registrada.';
    }
    send.disabled = !good;
}

function startGeoWatch() {
    if (!navigator.geolocation) {
        setStatus('Este teléfono no permite leer el GPS.', false);
        return;
    }
    if (geoWatchId !== null) return;
    geoWatchId = navigator.geolocation.watchPosition(
        (pos) => {
            const next = {
                latitude: pos.coords.latitude,
                longitude: pos.coords.longitude,
                accuracy: pos.coords.accuracy,
                at: Date.now(),
            };
            const stale = !geoFix || Date.now() - geoFix.at > 20000;
            if (stale || next.accuracy <= geoFix.accuracy + 3) geoFix = next;
            renderGeoFix();
        },
        () => {
            renderGeoFix();
        },
        { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 },
    );
}

function showGeo() {
    ['ops-home', 'review-card', 'module-card', 'sheets-card', 'observatory-card'].forEach((id) => {
        document.getElementById(id)?.classList.add('hidden');
    });
    document.getElementById('geo-card').classList.remove('hidden');
    geoFix = null;
    resetGeoForm();
    startGeoWatch();
}

function pickGeoInstallation(row) {
    geoInstallation = row;
    document.getElementById('geo-inst-q').value = row.name;
    document.getElementById('geo-picked').textContent = `${geoClient.name} · ${row.name}`;
    renderGeoFix();
}

async function pickGeoClient(row) {
    geoClient = { id: row.id, name: row.name || row.label };
    geoInstallation = null;
    document.getElementById('geo-client-q').value = geoClient.name;
    document.getElementById('geo-inst-wrap').classList.add('hidden');
    document.getElementById('geo-picked').textContent = 'Cargando instalaciones…';
    renderGeoFix();
    try {
        const data = await api(`/supervision/geo/installations?client_id=${geoClient.id}`);
        geoInstallations = data.installations || [];
    } catch (e) {
        geoInstallations = [];
        document.getElementById('geo-picked').textContent = '';
        setStatus(e.offline ? 'Necesita conexión para enviar la georreferencia.' : e.message, false);
        return;
    }
    if (!geoInstallations.length) {
        document.getElementById('geo-picked').textContent = 'Este cliente no tiene instalaciones activas.';
        return;
    }
    if (geoInstallations.length === 1) {
        pickGeoInstallation(geoInstallations[0]);
        return;
    }
    document.getElementById('geo-picked').textContent = `${geoInstallations.length} instalaciones. Elija dónde está.`;
    document.getElementById('geo-inst-wrap').classList.remove('hidden');
    document.getElementById('geo-inst-q').value = '';
    document.getElementById('geo-inst-q').focus();
}

function filterGeoInstallations(query) {
    const term = (query || '').trim().toLowerCase();
    return geoInstallations
        .filter((i) => term === '' || i.name.toLowerCase().includes(term))
        .slice(0, 30)
        .map((i) => Object.assign({}, i, { label: i.name + (i.pending ? ' · pendiente' : '') }));
}

async function sendGeo() {
    if (!geoClient || !geoInstallation || !geoFix) return;
    if (Math.round(geoFix.accuracy) > GEO_MAX_ACCURACY) {
        setStatus(`Espere a que el GPS llegue a ±${GEO_MAX_ACCURACY} m o menos.`, false);
        return;
    }
    await withBusy(document.getElementById('btn-geo-send'), 'Enviando…', async () => {
        let data;
        try {
            data = await api('/supervision/geo/requests', {
                method: 'POST',
                body: JSON.stringify({
                    client_id: geoClient.id,
                    installation_id: geoInstallation.id,
                    latitude: Number(geoFix.latitude.toFixed(7)),
                    longitude: Number(geoFix.longitude.toFixed(7)),
                    accuracy: geoFix.accuracy,
                }),
            });
        } catch (e) {
            if (e.offline) throw new Error('Sin conexión. La georreferencia necesita internet; no se guardó.');
            throw e;
        }
        setStatus(data.message || 'Solicitud enviada a la empresa.');
        hideGeoCard();
        showOpsHome();
    });
}

(function bindGeoUi() {
    const clientQ = document.getElementById('geo-client-q');
    const instQ = document.getElementById('geo-inst-q');
    clientQ.oninput = () => debounceSearch('geo-client', () => {
        geoClient = null;
        geoInstallation = null;
        renderGeoFix();
        fillCombo('geo-client-list', filterSites(clientQ.value), 'Sin clientes con Supervisión', pickGeoClient);
    });
    clientQ.onfocus = () => fillCombo('geo-client-list', filterSites(clientQ.value), 'Sin clientes con Supervisión', pickGeoClient);
    instQ.oninput = () => debounceSearch('geo-inst', () => {
        geoInstallation = null;
        renderGeoFix();
        fillCombo('geo-inst-list', filterGeoInstallations(instQ.value), 'Sin coincidencias', pickGeoInstallation);
    });
    instQ.onfocus = () => fillCombo('geo-inst-list', filterGeoInstallations(instQ.value), 'Sin coincidencias', pickGeoInstallation);
    document.getElementById('btn-open-geo').onclick = showGeo;
    document.getElementById('btn-geo-back').onclick = () => { hideGeoCard(); showOpsHome(); };
    document.getElementById('btn-geo-cancel').onclick = () => { hideGeoCard(); showOpsHome(); };
    document.getElementById('btn-geo-send').onclick = () => {
        sendGeo().finally(renderGeoFix);
    };
})();

async function showSheets() {
    document.getElementById('ops-home').classList.add('hidden');
    document.getElementById('review-card').classList.add('hidden');
    document.getElementById('module-card').classList.add('hidden');
    document.getElementById('observatory-card')?.classList.add('hidden');
    hideGeoCard();
    document.getElementById('sheets-card').classList.remove('hidden');
    const list = document.getElementById('sheets-list');
    list.innerHTML = '<p class="hint">Cargando…</p>';
    try {
        const data = await api('/supervision/sheets');
        const rows = data.sheets || [];
        if (!rows.length) {
            list.innerHTML = '<p class="hint">Aún no hay fichas en sus turnos.</p>';
            return;
        }
        list.innerHTML = rows.map((row) => `
            <button type="button" class="entry" data-sheet-kind="${row.kind}" data-sheet-id="${row.id}" data-sheet-folio="${esc(row.folio)}">
                ${row.folio}<span>${row.type}${row.client ? ' · ' + row.client : ''}${row.novelty ? ' · novedad' : ''}</span>
            </button>
        `).join('');
        list.querySelectorAll('[data-sheet-kind]').forEach((btn) => {
            btn.onclick = () => openSheet(btn.dataset.sheetKind, btn.dataset.sheetId, btn.dataset.sheetFolio);
        });
    } catch (e) {
        list.innerHTML = '';
        setStatus(e.message, false);
    }
}

async function openSheet(kind, id, folio = '') {
    const viewer = document.getElementById('sheet-viewer');
    const frame = document.getElementById('sheet-viewer-frame');
    try {
        setStatus('Abriendo ficha…');
        const html = await apiHtml(`/supervision/sheets/${kind}/${id}`);
        document.getElementById('sheet-viewer-title').textContent = folio || 'Ficha';
        frame.srcdoc = html;
        viewer.classList.remove('hidden');
        document.body.classList.add('sheet-open');
        try { history.pushState({ sheetViewer: true }, ''); } catch (e) { /* sin historial */ }
        setStatus('');
    } catch (e) {
        setStatus(e.message, false);
    }
}

function closeSheet(fromHistory = false) {
    const viewer = document.getElementById('sheet-viewer');
    if (viewer.classList.contains('hidden')) return;
    viewer.classList.add('hidden');
    document.getElementById('sheet-viewer-frame').srcdoc = '';
    document.body.classList.remove('sheet-open');
    if (!fromHistory && history.state?.sheetViewer) history.back();
}

document.getElementById('btn-sheet-close').onclick = () => closeSheet();
window.addEventListener('popstate', () => closeSheet(true));
document.getElementById('btn-logout-open').onclick = logout;

if (token()) {
    if (!offlineReady()) {
        show('login');
        setStatus('Actualice la app (borre datos del sitio o recargue).', false);
    } else {
        ZentraOffline.restoreUserFromStorage();
        afterLogin().catch((e) => {
            setStatus(e.message || 'No se pudo restaurar la sesión.', false);
            if (e.status === 401) {
                localStorage.removeItem('token');
                show('login');
            } else if (!document.getElementById('boot').classList.contains('hidden')) {
                show('login');
            }
        });
    }
} else {
    show('login');
}

if ('serviceWorker' in navigator && !isNativeApp()) {
    navigator.serviceWorker.register('sw.js').then((reg) => {
        if (reg.waiting) {
            reg.waiting.postMessage({ type: 'SKIP_WAITING' });
        }
        reg.addEventListener('updatefound', () => {
            const sw = reg.installing;
            if (!sw) return;
            sw.addEventListener('statechange', () => {
                if (sw.state === 'installed' && navigator.serviceWorker.controller) {
                    sw.postMessage({ type: 'SKIP_WAITING' });
                }
            });
        });
    }).catch(() => {});
    let refreshing = false;
    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (refreshing) return;
        refreshing = true;
        location.reload();
    });
}

window.addEventListener('online', () => {
    setStatus('Conexión recuperada. Enviando pendientes…');
    flushOutbox();
});
window.addEventListener('offline', () => {
    updateSyncBar();
    setStatus('Sin cobertura. Puede seguir registrando; se enviará al reconectar.');
});
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && pingTimer) {
        startPing();
    }
});

document.getElementById('btn-theme')?.addEventListener('click', () => {
    const next = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', next);
    try { localStorage.setItem('zentra-theme', next); } catch (e) {}
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', next === 'light' ? '#eaf4f1' : '#0b1220');
});
