import Echo from 'laravel-echo';

window.Echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: Number(import.meta.env.VITE_REVERB_PORT || 80),
  wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
  forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
  enabledTransports: ['ws', 'wss'],
});

window.Echo.channel('driver.location.1')
  .listen('.LocationUpdated', (e) => console.log('LocationUpdated:', e));
