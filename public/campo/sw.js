const CACHE = 'zentra-sup-v52';
const PRECACHE = ['./', './index.html', './offline.js', './app.js', './manifest.json'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data === 'skipWaiting' || event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

function isAppShell(url) {
    const path = url.pathname;
    return path.endsWith('/app.js')
        || path.endsWith('/offline.js')
        || path.endsWith('/index.html')
        || path.endsWith('/sw.js')
        || /\/field-app\/?$/.test(path)
        || /\/campo\/?$/.test(path)
        || path.endsWith('/');
}

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;
    const url = new URL(event.request.url);
    if (url.origin !== self.location.origin) return;

    if (isAppShell(url)) {
        event.respondWith(
            fetch(event.request).then((res) => {
                if (res && res.ok) {
                    const copy = res.clone();
                    caches.open(CACHE).then((cache) => cache.put(event.request, copy));
                }
                return res;
            }).catch(() => caches.open(CACHE).then((cache) => cache.match(event.request)
                .then((hit) => hit || cache.match('./index.html')))),
        );
        return;
    }

    event.respondWith(
        caches.open(CACHE).then((cache) => cache.match(event.request).then((hit) => hit || fetch(event.request))),
    );
});
