<?php

return [
    'default' => env('BROADCAST_DRIVER', 'null'),

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'app_id' => env('REVERB_APP_ID', 'cabcontrol'),
            'key'    => env('REVERB_APP_KEY', 'localkey'),
            'secret' => env('REVERB_APP_SECRET', 'localsecret'),
            'name'   => env('APP_NAME', 'Laravel'),
            'options' => [
                'host'   => env('REVERB_HOST', '127.0.0.1'),
                'port'   => (int) env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
            ],
        ],

        'log'  => ['driver' => 'log'],
        'null' => ['driver' => 'null'],
        // No declares pusher si no lo usarás
    ],
];
