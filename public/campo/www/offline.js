/* Cola offline de Supervisión: IndexedDB por supervisor; flush con el token de quien encoló. */
(function (global) {
    const LEGACY_DB = 'zentra-sup';
    const USER_PREFIX = 'zentra-sup-u';
    const DB_VER = 1;
    const USERS_KEY = 'sup_queue_users';
    const TOKENS_KEY = 'sup_sync_tokens';
    let ownerId = null;

    function dbNameFor(userId) {
        return userId ? USER_PREFIX + Number(userId) : LEGACY_DB;
    }

    function dbName() {
        return dbNameFor(ownerId);
    }

    function knownUserIds() {
        try {
            return JSON.parse(localStorage.getItem(USERS_KEY) || '[]').map(Number).filter(Boolean);
        } catch (e) {
            return [];
        }
    }

    function tokenMap() {
        try {
            const raw = JSON.parse(localStorage.getItem(TOKENS_KEY) || '{}');
            return raw && typeof raw === 'object' ? raw : {};
        } catch (e) {
            return {};
        }
    }

    function rememberUser(userId, tok) {
        const id = Number(userId);
        if (!id) return;
        const ids = new Set(knownUserIds());
        ids.add(id);
        try {
            localStorage.setItem(USERS_KEY, JSON.stringify([...ids]));
            if (tok) {
                const map = tokenMap();
                map[String(id)] = tok;
                localStorage.setItem(TOKENS_KEY, JSON.stringify(map));
            }
        } catch (e) {
            // storage lleno o privado
        }
    }

    function tokenForUser(userId) {
        if (!userId) return '';
        return tokenMap()[String(userId)] || '';
    }

    function userIdFromDbName(name) {
        if (!name || name === LEGACY_DB) return null;
        if (name.indexOf(USER_PREFIX) === 0) {
            const id = Number(name.slice(USER_PREFIX.length));
            return id || null;
        }
        return null;
    }

    function openNamed(name) {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(name, DB_VER);
            req.onupgradeneeded = () => {
                const db = req.result;
                if (!db.objectStoreNames.contains('meta')) {
                    db.createObjectStore('meta');
                }
                if (!db.objectStoreNames.contains('outbox')) {
                    db.createObjectStore('outbox', { keyPath: 'id', autoIncrement: true });
                }
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
    }

    function openDb() {
        return openNamed(dbName());
    }

    function txDone(tx) {
        return new Promise((resolve, reject) => {
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
            tx.onabort = () => reject(tx.error);
        });
    }

    function storeGet(db, store, key) {
        return new Promise((resolve, reject) => {
            if (!db.objectStoreNames.contains(store)) {
                resolve(undefined);
                return;
            }
            const req = db.transaction(store).objectStore(store).get(key);
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
    }

    function storeGetAll(db, store) {
        return new Promise((resolve, reject) => {
            if (!db.objectStoreNames.contains(store)) {
                resolve([]);
                return;
            }
            const req = db.transaction(store).objectStore(store).getAll();
            req.onsuccess = () => resolve(req.result || []);
            req.onerror = () => reject(req.error);
        });
    }

    async function withDb(name, fn) {
        const db = await openNamed(name);
        try {
            return await fn(db);
        } finally {
            db.close();
        }
    }

    async function metaGet(key) {
        if (!ownerId) return undefined;
        return withDb(dbName(), (db) => storeGet(db, 'meta', key));
    }

    async function metaSet(key, value) {
        if (!ownerId) return;
        await withDb(dbName(), async (db) => {
            const tx = db.transaction('meta', 'readwrite');
            tx.objectStore('meta').put(value, key);
            await txDone(tx);
        });
    }

    async function enqueue(item) {
        if (!ownerId) return;
        const stamped = Object.assign({}, item, {
            userId: ownerId,
            authToken: item.authToken || tokenForUser(ownerId) || '',
        });
        await withDb(dbName(), async (db) => {
            const tx = db.transaction('outbox', 'readwrite');
            tx.objectStore('outbox').add(stamped);
            await txDone(tx);
        });
    }

    async function allOutbox() {
        if (!ownerId) return [];
        return withDb(dbName(), (db) => storeGetAll(db, 'outbox'));
    }

    async function removeOutbox(id) {
        if (!ownerId) return;
        await removeOutboxIn(dbName(), id);
    }

    async function removeOutboxIn(name, id) {
        await withDb(name, async (db) => {
            const tx = db.transaction('outbox', 'readwrite');
            tx.objectStore('outbox').delete(id);
            await txDone(tx);
        });
    }

    async function metaSetIn(name, key, value) {
        await withDb(name, async (db) => {
            const tx = db.transaction('meta', 'readwrite');
            tx.objectStore('meta').put(value, key);
            await txDone(tx);
        });
    }

    async function outboxCount() {
        const stats = await outboxStats();
        return stats.total;
    }

    async function listDbNames() {
        const names = new Set([LEGACY_DB]);
        knownUserIds().forEach((id) => names.add(dbNameFor(id)));
        if (ownerId) names.add(dbNameFor(ownerId));
        if (indexedDB.databases) {
            try {
                const dbs = await indexedDB.databases();
                (dbs || []).forEach((row) => {
                    const name = row && row.name;
                    if (name === LEGACY_DB || (name && name.indexOf(USER_PREFIX) === 0)) {
                        names.add(name);
                    }
                });
            } catch (e) {
                // iOS viejo
            }
        }
        return [...names];
    }

    async function allPendingJobs() {
        const jobs = [];
        const names = await listDbNames();
        for (const name of names) {
            try {
                await withDb(name, async (db) => {
                    const rows = await storeGetAll(db, 'outbox');
                    const shift = await storeGet(db, 'meta', 'shift');
                    const closeQueued = Boolean(await storeGet(db, 'meta', 'closeQueued'));
                    const metaToken = await storeGet(db, 'meta', 'authToken');
                    const dbUserId = userIdFromDbName(name)
                        || Number(shift?.user_id || shift?.user?.id || 0)
                        || null;
                    const fallback = (typeof metaToken === 'string' && metaToken)
                        || tokenForUser(dbUserId);
                    rows.forEach((row) => {
                        const uid = Number(row.userId || 0) || userIdFromDbName(name) || null;
                        const auth = row.authToken || (uid ? tokenForUser(uid) : '');
                        jobs.push({
                            dbName: name,
                            userId: uid,
                            authToken: auth,
                            closeQueued,
                            row,
                        });
                    });
                    if (rows.length === 0 && closeQueued && fallback) {
                        jobs.push({
                            dbName: name,
                            userId: dbUserId,
                            authToken: fallback,
                            closeQueued: true,
                            row: { id: null, type: 'close-meta' },
                        });
                    }
                });
            } catch (e) {
                // BD ausente
            }
        }
        return jobs;
    }

    async function outboxStats() {
        const jobs = (await allPendingJobs()).filter((job) => job.row && job.row.type !== 'close-meta');
        let mine = 0;
        let others = 0;
        jobs.forEach((job) => {
            if (ownerId && Number(job.userId) === Number(ownerId)) mine += 1;
            else others += 1;
        });
        return { mine, others, total: jobs.length };
    }

    async function clearStore(db, store) {
        if (!db.objectStoreNames.contains(store)) return;
        const tx = db.transaction(store, 'readwrite');
        tx.objectStore(store).clear();
        await txDone(tx);
    }

    async function claimLegacyIfOwned(userId) {
        let legacy;
        try {
            legacy = await openNamed(LEGACY_DB);
        } catch (e) {
            return;
        }
        try {
            const shift = await storeGet(legacy, 'meta', 'shift');
            const owner = Number(shift?.user_id || shift?.user?.id || 0);
            if (owner !== Number(userId)) {
                return;
            }
            const rows = await storeGetAll(legacy, 'outbox');
            const target = await openNamed(dbNameFor(userId));
            try {
                const metaKeys = ['pack', 'intake', 'shift', 'closeQueued'];
                for (const key of metaKeys) {
                    const value = await storeGet(legacy, 'meta', key);
                    if (value === undefined) continue;
                    const tx = target.transaction('meta', 'readwrite');
                    tx.objectStore('meta').put(value, key);
                    await txDone(tx);
                }
                const tok = tokenForUser(userId);
                if (tok) {
                    const tx = target.transaction('meta', 'readwrite');
                    tx.objectStore('meta').put(tok, 'authToken');
                    await txDone(tx);
                }
                for (const row of rows) {
                    const copy = Object.assign({}, row, {
                        userId: userId,
                        authToken: row.authToken || tok,
                    });
                    delete copy.id;
                    const tx = target.transaction('outbox', 'readwrite');
                    tx.objectStore('outbox').add(copy);
                    await txDone(tx);
                }
            } finally {
                target.close();
            }
            await clearStore(legacy, 'outbox');
            await clearStore(legacy, 'meta');
        } finally {
            legacy.close();
        }
    }

    async function setUser(id, tok) {
        ownerId = id ? Number(id) : null;
        try {
            if (ownerId) {
                localStorage.setItem('sup_user_id', String(ownerId));
                rememberUser(ownerId, tok);
                if (tok) {
                    await metaSet('authToken', tok);
                }
                await claimLegacyIfOwned(ownerId);
            } else {
                localStorage.removeItem('sup_user_id');
            }
        } catch (e) {
            // storage lleno o privado
        }
    }

    function restoreUserFromStorage() {
        try {
            const stored = localStorage.getItem('sup_user_id');
            ownerId = stored ? Number(stored) : null;
        } catch (e) {
            ownerId = null;
        }
        return ownerId;
    }

    function currentUserId() {
        return ownerId;
    }

    function uuid() {
        if (global.crypto?.randomUUID) return global.crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
            const r = (Math.random() * 16) | 0;
            return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
        });
    }

    function isOfflineError(err) {
        if (!err) return false;
        if (err.offline) return true;
        if (typeof navigator !== 'undefined' && navigator.onLine === false) return true;
        return err.name === 'TypeError';
    }

    global.ZentraOffline = {
        setUser,
        restoreUserFromStorage,
        currentUserId,
        rememberUser,
        tokenForUser,
        metaGet,
        metaSet,
        metaSetIn,
        enqueue,
        allOutbox,
        allPendingJobs,
        removeOutbox,
        removeOutboxIn,
        outboxCount,
        outboxStats,
        uuid,
        isOfflineError,
    };
})(window);
