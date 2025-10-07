// resources/js/app.js

import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
// Nada de tema aquí. Lo maneja adminkit.js
