<?php

return [

    // Rutas donde aplica CORS
    'paths' => [
        'api/*',
        'broadcasting/auth',
        'sanctum/csrf-cookie',
        'login',
        'logout',
    ],

    'allowed_methods' => ['*'],

    // Orígenes permitidos (panel local, Vite y túneles efímeros de Cloudflare)
    'allowed_origins' => [
        'http://localhost',
        'http://localhost:5173',
        'https://localhost',
        'https://localhost:5173',
    ],

    // Cualquier subdominio *.trycloudflare.com (túneles “quick”)
    'allowed_origins_patterns' => [
        '#^https://[a-z0-9-]+\.trycloudflare\.com$#',
    ],

    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,

    // Si usas cookies (Sanctum) en el panel web:
    'supports_credentials' => true,
];
