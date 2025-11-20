<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

// Panel (sesión web)
Broadcast::routes(['middleware' => ['web', 'auth']]); // /broadcasting/auth

// API (Driver App con Sanctum) → la app usará /api/broadcasting/auth
Broadcast::routes([
    'middleware' => ['auth:sanctum'],
    'prefix'     => 'api',
]);

Broadcast::channel('tenant.{tenantId}.driver.{driverId}', function ($user, $tenantId, $driverId) {
    if (!$user) return false;

    $driver = DB::table('drivers')->where('user_id', $user->id)->first(['id','tenant_id']);
    if (!$driver) return false;

    return (int)$driver->tenant_id === (int)$tenantId
        && (int)$driver->id === (int)$driverId;
});


// Canal privado del RIDE (para dispatch/pasajero). Lo usamos en “RIDES” (siguiente paso).
Broadcast::channel('tenant.{tenantId}.ride.{rideId}', function ($user = null, int $tenantId, int $rideId) {
    return $user ? (int)($user->tenant_id ?? 0) === (int)$tenantId : false;
});
