<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Apps registradas en Reverb
    |--------------------------------------------------------------------------
    |
    | Debe ser un ARREGLO. Cada elemento representa una app con sus credenciales.
    |
    */

    'apps' => [
        [
             'app_id' => env('REVERB_APP_ID', 'cabcontrol'),
        'key'    => env('REVERB_APP_KEY', 'localkey'),
        'secret' => env('REVERB_APP_SECRET', 'localsecret'),
            'name'   => env('APP_NAME', 'Laravel'),

            // Opcionales
            'capacity'                => null,
            'enable_client_messages'  => false,
            'enable_statistics'       => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Otras opciones (deja por defecto)
    |--------------------------------------------------------------------------
    */

    'max_request_size' => 1024 * 1024,
    'max_payload_size' => 1024 * 1024,
    'apps_repository'  => null, // usa repositorio por defecto
];
