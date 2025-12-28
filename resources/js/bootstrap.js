import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher
// Activar logs para debugging
window.Pusher.logToConsole = true

console.log('📍 Dispatch - Origen actual:', window.location.origin)
console.log('🔗 Dispatch - Hostname:', window.location.hostname)

console.log('🔄 Dispatch - Inicializando Echo...')
console.log('REVERB_HOST:', import.meta.env.VITE_REVERB_HOST)
console.log('REVERB_PORT:', import.meta.env.VITE_REVERB_PORT)
console.log('REVERB_SCHEME:', import.meta.env.VITE_REVERB_SCHEME)
console.log('REVERB_APP_KEY:', import.meta.env.VITE_REVERB_APP_KEY)

const host = import.meta.env.VITE_REVERB_HOST
const port = Number(import.meta.env.VITE_REVERB_PORT)
const scheme = import.meta.env.VITE_REVERB_SCHEME

console.log('🔧 Dispatch - Configuración final:', { host, port, scheme })

// Configuración de Echo para el Dispatch
const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

window.Echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: host,
  wsPort: port,
  wssPort: port,
  forceTLS: scheme === 'https',
  enabledTransports: ['ws', 'wss'],
  enableStats: false,

  // Importante para private/presence
  authEndpoint: '/broadcasting/auth',
  withCredentials: true,
  auth: {
    headers: {
      ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json',
    }
  },
});


// Logs de conexión para debugging
window.Echo.connector.pusher.connection.bind('connecting', () => {
  console.log('🔄 Dispatch - Conectando a Reverb...')
})

window.Echo.connector.pusher.connection.bind('connected', () => {
  console.log('✅ Dispatch - CONECTADO a Reverb correctamente')
})

window.Echo.connector.pusher.connection.bind('failed', (error) => {
  console.log('❌ Dispatch - FALLO la conexión:', error)
})

window.Echo.connector.pusher.connection.bind('error', (error) => {
  console.log('💥 Dispatch - ERROR de conexión:', error)
})

window.Echo.connector.pusher.connection.bind('state_change', (states) => {
  console.log('🔄 Dispatch - Estado cambiado:', states.previous, '->', states.current)
})

// Solo para testing - escuchar eventos públicos
const publicChannel = window.Echo.channel('public-test')
console.log('📡 Dispatch - Suscrito al canal público: public-test')

publicChannel.listen('.TestEvent', (e) => {
  console.log('🎉 Dispatch - EVENTO RECIBIDO:', e)
})

// También puedes escuchar los eventos que envías para verificar
publicChannel.listenToAll((event, data) => {
  console.log('🔍 Dispatch - Evento global:', event, data)
})