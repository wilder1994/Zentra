import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const key = import.meta.env.VITE_REVERB_APP_KEY;
const bakedHost = String(import.meta.env.VITE_REVERB_HOST || '').trim();
const pageHost = window.location.hostname;
const isLocalPage = pageHost === 'localhost' || pageHost === '127.0.0.1' || pageHost.endsWith('.test');
const bakedIsLocal = bakedHost === '' || bakedHost === 'localhost' || bakedHost === '127.0.0.1';

// Build local no debe abrir wss://localhost desde el dominio público (ERR_CONNECTION_REFUSED).
// Sin Echo el tablero sigue con sondeo JSON.
if (key && !(bakedIsLocal && !isLocalPage)) {
    const wsHost = bakedIsLocal ? pageHost : bakedHost;
    const scheme = import.meta.env.VITE_REVERB_SCHEME ?? (window.location.protocol === 'https:' ? 'https' : 'http');
    const port = Number(import.meta.env.VITE_REVERB_PORT ?? (scheme === 'https' ? 443 : 80));

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
