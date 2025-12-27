<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */
    'google' => ['key' => env('GOOGLE_MAPS_KEY')],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    
    'fcm' => [
        'project_id'   => env('FIREBASE_PROJECT_ID'),
        'client_email' => env('FIREBASE_CLIENT_EMAIL'),
        'private_key'  => env('FIREBASE_PRIVATE_KEY'),
    ],

    'turnstile' => [
    'site_key' => env('TURNSTILE_SITE_KEY'),
    'secret_key' => env('TURNSTILE_SECRET_KEY'),
  ],


      'mercadopago' => [
    'token'          => env('MERCADOPAGO_ACCESS_TOKEN'),
    'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
     'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),
    'sandbox'        => (bool) env('MERCADOPAGO_SANDBOX', true),
    'public_url'     => env('MERCADOPAGO_PUBLIC_URL', env('APP_URL')),
],


'public_contact' => [
  'key' => env('PUBLIC_CONTACT_KEY', ''),
  'to'  => env('CONTACT_TO_EMAIL', 'contacto@orbana.mx'),
],


];
