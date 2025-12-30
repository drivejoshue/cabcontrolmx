<?php

return [
    // ID del tenant global (Orbana Global)
       'global_tenant_slug' => env('ORBANA_GLOBAL_TENANT_SLUG', 'orbana-global'),

        'public_contact_key' => env('PUBLIC_CONTACT_KEY', null),
        
        'issues' => [
        'passenger_window_hours' => env('ORBANA_ISSUES_PASSENGER_HOURS', 24),
        'driver_window_hours'    => env('ORBANA_ISSUES_DRIVER_HOURS', 24),
    ],


];