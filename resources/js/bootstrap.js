import Echo from 'laravel-echo';

console.log('🔄 Inicializando Echo...');
console.log('REVERB_HOST:', import.meta.env.VITE_REVERB_HOST);
console.log('REVERB_PORT:', import.meta.env.VITE_REVERB_PORT);
console.log('REVERB_APP_KEY:', import.meta.env.VITE_REVERB_APP_KEY);

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'localkey',
    wsHost: import.meta.env.VITE_REVERB_HOST || '127.0.0.1',
    wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
});

// Debug de conexión
window.Echo.connector.pusher.connection.bind('connected', () => {
    console.log('✅ CONECTADO a Reverb correctamente');
});

window.Echo.connector.pusher.connection.bind('error', (error) => {
    console.log('❌ ERROR de conexión:', error);
});

// Suscribir al canal
const channel = window.Echo.channel('public-test');
console.log('📡 Suscrito al canal: public-test');

channel.listen('.TestEvent', (e) => {
    console.log('🎉 EVENTO RECIBIDO:', e);
});

// También escuchar todos los eventos para debug
channel.listenToAll((event, data) => {
    console.log('🔍 TODOS LOS EVENTOS:', event, data);
});