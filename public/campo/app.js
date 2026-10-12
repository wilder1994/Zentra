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
let streams = {};
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
    ['login', 'change-password', 'open-shift', 'ops', 'close-shift'].forEach((key) => {
        document.getElementById(key).classList.toggle('hidden', key !== id);
    });
    stopAllCams();
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
    if (pack.observatory_sites) obsSitesCache = pack.observatory_sites;
    if (pack.observatory_kinds_by_client) obsKindsByClient = pack.observatory_kinds_by_client;
    if (pack.observatory_kinds) {
        obsKinds = pack.observatory_kinds;
        fillObsKinds(obsKinds);
    }
    const select = document.getElementById('mod-client');
    if (select) {
        select.innerHTML = (sites || []).map((s) => `<option value="${s.id}">${s.name}</option>`).join('')
            || '<option value="">Sin clientes con Supervisión</option>';
    }
    renderHub();
}

async function refreshOfflinePack() {
    const pack = await api('/supervision/offline-pack');
    await ZentraOffline.metaSet('pack', pack);
    applyPack(pack);
    return pack;
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
        await api('/supervision/reviews', { method: 'POST', body: fd, ...auth });
        return;
    }
    if (row.type === 'close') {
        const fd = new FormData();
        fd.append('km_end', String(row.body.km_end));
        fd.append('client_event_id', row.clientEventId);
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

function cameraAvailable() {
    return Boolean(window.isSecureContext && navigator.mediaDevices?.getUserMedia);
}

async function startCam(videoId, facing) {
    stopCam(videoId);
    const video = document.getElementById(videoId);
    if (!cameraAvailable()) {
        setStatus('Cámara no disponible. Abra la PWA por HTTPS (Tailscale Serve), no por http://IP:8085.', false);
        return;
    }
    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: facing === 'user' ? 'user' : 'environment' } },
            audio: false,
        });
        streams[videoId] = stream;
        video.srcObject = stream;
        await video.play();
    } catch (e) {
        setStatus('Sin cámara o permiso denegado.', false);
    }
}

function stopCam(videoId) {
    const stream = streams[videoId];
    if (stream) stream.getTracks().forEach((t) => t.stop());
    delete streams[videoId];
    const video = document.getElementById(videoId);
    if (video) video.srcObject = null;
}

function stopAllCams() {
    Object.keys(streams).forEach(stopCam);
}

function waitForVideo(video, ms = 3000) {
    return new Promise((resolve) => {
        if (video.videoWidth) {
            resolve(true);
            return;
        }
        let settled = false;
        const finish = (ok) => {
            if (settled) return;
            settled = true;
            video.removeEventListener('loadeddata', onReady);
            resolve(ok);
        };
        const onReady = () => finish(video.videoWidth > 0);
        video.addEventListener('loadeddata', onReady);
        setTimeout(() => finish(video.videoWidth > 0), ms);
    });
}

function captureFrame(videoId, imgId, key) {
    const video = document.getElementById(videoId);
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    canvas.toBlob((blob) => {
        blobs[key] = blob;
        const img = document.getElementById(imgId);
        img.src = URL.createObjectURL(blob);
        img.classList.remove('hidden');
        stopCam(videoId);
        setStatus('Foto tomada.');
    }, 'image/jpeg', 0.86);
}

async function snapTo(videoId, imgId, key, label, facing) {
    const video = document.getElementById(videoId);
    if (!video.videoWidth) {
        if (!cameraAvailable()) {
            setStatus('Cámara no disponible. Abra la PWA por HTTPS (Tailscale Serve), no por http://IP:8085.', false);
            return;
        }
        await startCam(videoId, facing);
        const ready = await waitForVideo(video);
        if (!ready) {
            setStatus('Active Trasera o Frontal y permita la cámara.', false);
            return;
        }
    }
    captureFrame(videoId, imgId, key);
}

function bindCameras() {
    document.querySelectorAll('[data-live-cam]').forEach((btn) => {
        btn.onclick = () => {
            const videoId = btn.dataset.liveCam;
            const snapId = btn.dataset.snap;
            const blobKey = btn.dataset.blob;
            const facing = btn.dataset.facing === 'user' ? 'user' : 'environment';
            if (snapId) document.getElementById(snapId)?.classList.add('hidden');
            if (blobKey) blobs[blobKey] = null;
            startCam(videoId, facing);
        };
    });
    document.getElementById('btn-snap-odo').onclick = () => snapTo('cam-odo', 'snap-odo', 'odo', 'Odómetro inicio', 'environment');
    document.getElementById('btn-snap-self').onclick = () => snapTo('cam-self', 'snap-self', 'self', 'Selfie inicio', 'user');
    document.getElementById('btn-snap-odo-end').onclick = () => snapTo('cam-odo-end', 'snap-odo-end', 'odoEnd', 'Odómetro cierre', 'environment');
    document.getElementById('btn-snap-self-end').onclick = () => snapTo('cam-self-end', 'snap-self-end', 'selfEnd', 'Selfie cierre', 'user');
    document.getElementById('btn-snap-guard').onclick = () => snapTo('cam-guard', 'snap-guard', 'guard', 'Vigilante', 'environment');
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
    document.getElementById('hub-grid').innerHTML = hubMods.map((mod) => `
        <button type="button" class="mod" data-key="${mod.key}">
            <b>${mod.label}</b>
            <span class="n">${draftCount(mod.key)} en esta revista</span>
        </button>`).join('');
    document.querySelectorAll('#hub-grid .mod').forEach((btn) => {
        btn.onclick = () => openModule(btn.dataset.key, 'review');
    });
    const ctx = document.getElementById('review-context');
    ctx.textContent = [reviewClient?.name, reviewPost?.name, reviewGuard?.name].filter(Boolean).join(' · ');
}

function draftCount(moduleKey) {
    return reviewDraftLogs.filter((row) => row.module === moduleKey).length;
}

function showOpsHome() {
    stopAllCams();
    document.getElementById('ops-home').classList.remove('hidden');
    document.getElementById('review-card').classList.add('hidden');
    document.getElementById('module-card').classList.add('hidden');
    document.getElementById('sheets-card')?.classList.add('hidden');
    document.getElementById('observatory-card')?.classList.add('hidden');
}

function showReview() {
    stopAllCams();
    document.getElementById('ops-home').classList.add('hidden');
    document.getElementById('module-card').classList.add('hidden');
    document.getElementById('sheets-card')?.classList.add('hidden');
    document.getElementById('observatory-card')?.classList.add('hidden');
    document.getElementById('review-card').classList.remove('hidden');
}

function openModule(key, from = 'home') {
    currentModule = catalog.find((m) => m.key === key);
    if (!currentModule) return;
    stopAllCams();
    moduleReturn = from;
    document.getElementById('ops-home').classList.add('hidden');
    document.getElementById('review-card').classList.add('hidden');
    document.getElementById('sheets-card')?.classList.add('hidden');
    document.getElementById('observatory-card')?.classList.add('hidden');
    const wrap = document.getElementById('module-client-wrap');
    wrap.classList.toggle('hidden', !currentModule.requires_client);
    document.getElementById('module-card').classList.remove('hidden');
    document.getElementById('module-title').textContent = currentModule.label;
    document.getElementById('module-hint').textContent = currentModule.hint;
    document.getElementById('module-form').innerHTML = (currentModule.fields || []).map((field) => renderField(field)).join('');
    modulePhotos = {};
    (currentModule.fields || []).filter((field) => field.type === 'repeatable').forEach(bindRepeatable);
    bindAllPhotoGrids();
    bindConditionalFields();
    document.getElementById('module-card').scrollIntoView({ behavior: 'smooth' });
}

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
                ${(field.slots || []).map((slot) => `<button type="button" class="photo-slot" data-photo-slot="${slot.key}"><span>${slot.label}</span></button>`).join('')}
            </div>`);
    }
    if (field.type === 'row') {
        return fieldWrapper(field, `<div class="row">${(field.fields || []).map((sub) => `<div><label>${sub.label}</label>${fieldControl(sub)}</div>`).join('')}</div>`);
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
            const radios = el.querySelector('.opts');
            if (!radios || !options.length) return;
            const current = radios.querySelector('input[type="radio"]:checked')?.value;
            const name = radios.querySelector('input[type="radio"]')?.name;
            if (!name) return;
            radios.innerHTML = options.map((option, i) => {
                const checked = option.value === current || (current == null && i === 0) ? 'checked' : '';
                return `<label class="opt"><input type="radio" name="${name}" value="${option.value}" ${checked}><span>${option.label}</span></label>`;
            }).join('');
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
                    ${(item.slots || []).map((slot) => `<button type="button" class="photo-slot" data-photo-slot="${index}_${slot.key}"><span>${slot.label}</span></button>`).join('')}
                </div>`;
        }
        if (item.type === 'computed') {
            return `<label>${item.label}</label><p class="risk-level" id="f-${field.name}-${index}-${item.name}">—</p>`;
        }
        const named = { ...item, name: `${field.name}-${index}-${item.name}` };
        if (item.type === 'qty_pair') return fieldControl(named);
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

function fieldControl(field) {
    const req = field.required ? 'required' : '';
    if (field.type === 'textarea') return `<textarea id="f-${field.name}" ${req}></textarea>`;
    if (field.type === 'select') {
        const options = field.options || [];
        const opts = options.length
            ? options.map((o) => `<option value="${o.value}">${o.label}</option>`).join('')
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
        return `<div class="opts">${(field.options || []).map((o, i) => `
            <label class="opt"><input type="radio" name="f-${field.name}" value="${o.value}" ${i === 0 && field.required ? 'checked' : ''}><span>${o.label}</span></label>`).join('')}</div>`;
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
        if (field.type === 'radio') {
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
}

function selectReviewPost(row) {
    reviewPost = { id: row.id, name: row.name || row.label };
    document.getElementById('rev-post-q').value = row.label || reviewPost.name;
}

function selectReviewGuard(row) {
    reviewGuard = { id: row.id, name: row.name, document_number: row.document_number };
    document.getElementById('rev-guard-doc').value = row.document_number;
    document.getElementById('rev-guard-name').value = row.name;
}

function bindReviewUi() {
    const clientQ = document.getElementById('rev-client-q');
    const postQ = document.getElementById('rev-post-q');
    const guardQ = document.getElementById('rev-guard-doc');
    clientQ.oninput = () => debounceSearch('client', () => {
        reviewClient = null;
        fillCombo('rev-client-list', filterSites(clientQ.value), 'Sin clientes con Supervisión', selectReviewClient);
    });
    clientQ.onfocus = () => fillCombo('rev-client-list', filterSites(clientQ.value), 'Sin clientes con Supervisión', selectReviewClient);
    postQ.oninput = () => debounceSearch('post', () => searchPosts(postQ.value));
    postQ.onfocus = () => { if (reviewClient) searchPosts(postQ.value); };
    guardQ.oninput = () => debounceSearch('guard', () => searchGuards(guardQ.value));
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

async function searchGuards(document) {
    const term = (document || '').replace(/\s+/g, '');
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
    } catch (e) {
        if (!ZentraOffline.isOfflineError(e)) throw e;
        await enqueueOrThrow(queued, 'Revista guardada en el teléfono. Se enviará al reconectar.');
        currentReview = { id: null, pending: true, client_id: reviewClient.id };
        reviewEventId = null;
    }
    blobs.guard = null;
    document.getElementById('snap-guard').classList.add('hidden');
    reviewDraftLogs = [];
    resetReviewForm();
    renderHub();
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
    document.getElementById('rev-novelty').checked = false;
}

function discardReviewSession() {
    reviewDraftLogs = [];
    currentReview = null;
    blobs.guard = null;
    document.getElementById('snap-guard').classList.add('hidden');
    resetReviewForm();
    renderHub();
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
        const data = await api('/supervision/catalog');
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
        await ZentraOffline.metaSet('shift', shift || null);
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

function applyShift(shift) {
    if (!shift) {
        document.getElementById('shift-label').textContent = 'Sin turno';
        stopPing();
        return null;
    }
    clearOwnCloseLock();
    document.getElementById('shift-label').textContent = 'Turno abierto';
    document.getElementById('shift-meta').textContent = [
        shift.schedule_label,
        shift.route_zone,
        shift.fleet_vehicle?.plate,
        shift.km_start != null ? `${shift.km_start} km` : null,
    ].filter(Boolean).join(' · ');
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
    stopPing();
    stopAllCams();
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
    try {
        await Promise.all([loadSites(), loadIntake()]);
        if (!catalog.length) await loadCatalog();
    } catch (e) {
        if (!ZentraOffline.isOfflineError(e)) {
            setStatus(e.message, false);
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
        enterOps();
        return;
    }
    show('open-shift');
}

bindCameras();
bindReviewUi();
bindObsPhotoGrid();
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
        stopAllCams();
        try { await refreshOfflinePack(); } catch (e) { /* el turno ya abrió */ }
        await loadCurrent();
        enterOps();
        setStatus('Turno iniciado. Clientes y catálogo en el teléfono.');
    },
);

document.getElementById('btn-go-close').onclick = () => {
    show('close-shift');
};

document.getElementById('btn-close-back').onclick = () => {
    stopAllCams();
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
        const fd = new FormData();
        fd.append('km_end', document.getElementById('km-end').value);
        fd.append('client_event_id', clientEventId);
        fd.append('odometer_photo', blobs.odoEnd, 'odometer-end.jpg');
        fd.append('selfie_photo', blobs.selfEnd, 'selfie-end.jpg');
        try {
            await api('/supervision/shifts/close', { method: 'POST', body: fd });
            closeEventId = null;
            blobs.odoEnd = blobs.selfEnd = null;
            stopPing();
            stopAllCams();
            activity = null;
            currentReview = null;
            reviewDraftLogs = [];
            await ZentraOffline.metaSet('shift', null);
            setStatus('Turno cerrado.');
            logout();
            return;
        } catch (e) {
            if (!ZentraOffline.isOfflineError(e)) throw e;
            await ZentraOffline.enqueue({
                type: 'close',
                clientEventId,
                body: { km_end: document.getElementById('km-end').value },
                files: { odometer: blobs.odoEnd, selfie: blobs.selfEnd },
            });
            closeEventId = null;
            closeQueued = true;
            await ZentraOffline.metaSet('closeQueued', true);
            blobs.odoEnd = blobs.selfEnd = null;
            stopPing();
            stopAllCams();
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
            if (currentModule.key === 'recommendations') {
                const field = (currentModule.fields || []).find((item) => item.type === 'repeatable');
                draft.photos = collectRepeatablePhotos(field);
            }
            modulePhotos = {};
            reviewDraftLogs.push(draft);
            renderHub();
            setStatus(`${currentModule.label} registrado.`);
            showReview();
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
    if (moduleReturn === 'review') showReview();
    else showOpsHome();
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
    stopAllCams();
    document.getElementById('ops-home').classList.add('hidden');
    document.getElementById('review-card').classList.add('hidden');
    document.getElementById('module-card').classList.add('hidden');
    document.getElementById('sheets-card')?.classList.add('hidden');
    document.getElementById('observatory-card').classList.remove('hidden');
    loadObservatoryKinds();
    if (obsSite) placeObsPin(obsSite);
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
        showOpsHome();
    },
);

document.getElementById('btn-open-observatory').onclick = () => showObservatory();
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

async function showSheets() {
    stopAllCams();
    document.getElementById('ops-home').classList.add('hidden');
    document.getElementById('review-card').classList.add('hidden');
    document.getElementById('module-card').classList.add('hidden');
    document.getElementById('observatory-card')?.classList.add('hidden');
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
            <button type="button" class="entry" data-sheet-kind="${row.kind}" data-sheet-id="${row.id}">
                ${row.folio}<span>${row.type}${row.client ? ' · ' + row.client : ''}${row.novelty ? ' · novedad' : ''}</span>
            </button>
        `).join('');
        list.querySelectorAll('[data-sheet-kind]').forEach((btn) => {
            btn.onclick = () => openSheet(btn.dataset.sheetKind, btn.dataset.sheetId);
        });
    } catch (e) {
        list.innerHTML = '';
        setStatus(e.message, false);
    }
}

async function openSheet(kind, id) {
    try {
        const html = await apiHtml(`/supervision/sheets/${kind}/${id}`);
        const w = window.open('', '_blank');
        if (!w) {
            setStatus('Permita ventanas emergentes para ver la ficha.', false);
            return;
        }
        w.document.open();
        w.document.write(html);
        w.document.close();
    } catch (e) {
        setStatus(e.message, false);
    }
}
document.getElementById('btn-logout-open').onclick = logout;

if (token()) {
    if (!offlineReady()) {
        setStatus('Actualice la app (borre datos del sitio o recargue).', false);
    } else {
        ZentraOffline.restoreUserFromStorage();
        afterLogin().catch((e) => {
            setStatus(e.message || 'No se pudo restaurar la sesión.', false);
            if (e.status === 401) {
                localStorage.removeItem('token');
                show('login');
            }
        });
    }
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
