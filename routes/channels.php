<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

// Panel (sesión web)
Broadcast::routes(['middleware' => ['web', 'auth','staff']]); // /broadcasting/auth

// API (Driver App con Sanctum) → la app usará /api/broadcasting/auth
Broadcast::routes([
    'middleware' => ['auth:sanctum'],
    'prefix'     => 'api',
]);

Broadcast::channel('tenant.{tenantId}.dispatch', function ($user, $tenantId) {
    if (!$user) return false;

    // Regla mínima: mismo tenant y es admin del tenant
    return (int)($user->tenant_id ?? 0) === (int)$tenantId
        && !empty($user->is_admin);
});

Broadcast::channel('tenant.{tenantId}.chat', function ($user, $tenantId) {
    if (!$user) return false;

    return (int)($user->tenant_id ?? 0) === (int)$tenantId
        && !empty($user->is_admin);
});




Broadcast::channel('tenant.{tenantId}.driver.{driverId}', function ($user, $tenantId, $driverId) {
    if (!$user) return false;

    // ✅ Staff del tenant (admin/dispatcher) puede escuchar driver.*
    if ((int)($user->tenant_id ?? 0) === (int)$tenantId && (!empty($user->is_admin) || !empty($user->is_dispatcher))) {
        return true;
    }

    // ✅ El propio driver (app) también
    $driver = DB::table('drivers')->where('user_id', $user->id)->first(['id','tenant_id']);
    if (!$driver) return false;

    return (int)$driver->tenant_id === (int)$tenantId
        && (int)$driver->id === (int)$driverId;
});



Broadcast::channel('tenant.{tenantId}.ride.{rideId}', function ($user = null, int $tenantId, int $rideId) {
    return $user ? (int)($user->tenant_id ?? 0) === (int)$tenantId : false;
});
