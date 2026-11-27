/** Mueve la moto a la velocidad real entre dos GPS, en orden, sin reiniciar el tramo. */

const queues = new Map();
const running = new Set();
const lastAt = new Map();

function metersBetween(a, b) {
    const r = 6371000;
    const dLat = ((b.lat - a.lat) * Math.PI) / 180;
    const dLng = ((b.lng - a.lng) * Math.PI) / 180;
    const s = Math.sin(dLat / 2) ** 2
        + Math.cos((a.lat * Math.PI) / 180) * Math.cos((b.lat * Math.PI) / 180) * Math.sin(dLng / 2) ** 2;

    return 2 * r * Math.asin(Math.min(1, Math.sqrt(s)));
}

function segmentMs(fromAt, toAt, meters) {
    if (fromAt && toAt) {
        const ms = new Date(toAt).getTime() - new Date(fromAt).getTime();
        if (ms > 250 && ms < 30000) {
            return ms;
        }
    }

    return Math.max(400, Math.min(20000, (meters / 12) * 1000));
}

function pump(marker, key) {
    if (running.has(key)) {
        return;
    }
    const queue = queues.get(key) || [];
    const next = queue.shift();
    if (!next) {
        return;
    }
    const current = marker.getPosition?.();
    if (!current) {
        marker.setPosition({ lat: next.lat, lng: next.lng });
        lastAt.set(key, next.at || null);
        pump(marker, key);

        return;
    }
    const from = { lat: current.lat(), lng: current.lng() };
    const meters = metersBetween(from, next);
    if (meters < 2) {
        if (next.at) {
            lastAt.set(key, next.at);
        }
        pump(marker, key);

        return;
    }
    const ms = segmentMs(lastAt.get(key), next.at, meters);
    const started = performance.now();
    running.add(key);
    const step = (now) => {
        const t = Math.min(1, (now - started) / ms);
        marker.setPosition({
            lat: from.lat + (next.lat - from.lat) * t,
            lng: from.lng + (next.lng - from.lng) * t,
        });
        if (t < 1) {
            requestAnimationFrame(step);

            return;
        }
        running.delete(key);
        if (next.at) {
            lastAt.set(key, next.at);
        }
        pump(marker, key);
    };
    requestAnimationFrame(step);
}

export function slideMoto(marker, id, lat, lng, at) {
    if (!marker || !Number.isFinite(lat) || !Number.isFinite(lng)) {
        return;
    }
    const key = String(id);
    const queue = queues.get(key) || [];
    const tail = queue[queue.length - 1];
    if (tail && tail.lat === lat && tail.lng === lng) {
        return;
    }
    const current = marker.getPosition?.();
    if (!current && queue.length === 0 && !running.has(key)) {
        marker.setPosition({ lat, lng });
        lastAt.set(key, at || null);

        return;
    }
    queue.push({ lat, lng, at: at || null });
    queues.set(key, queue);
    pump(marker, key);
}
