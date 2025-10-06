import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';   // 👈 Reverb usa protocolo Pusher
window.Pusher = Pusher;

window.Echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY ?? 'localkey',
  wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
  wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
  wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
  forceTLS: false,                       // Reverb lo tienes en http
  enabledTransports: ['ws', 'wss'],
});

// Prueba
window.Echo.channel('driver.location.1')
  .listen('.LocationUpdated', e => console.log('LocationUpdated:', e));
