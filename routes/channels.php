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

    // Si este user es driver-app, NO debe poder escuchar como staff.
    $driver = DB::table('drivers')->where('user_id', $user->id)->first(['id','tenant_id']);

    // ✅ Staff del tenant (admin/dispatcher) puede escuchar driver.*
    if (!$driver) {
        return (int)($user->tenant_id ?? 0) === (int)$tenantId
            && (!empty($user->is_admin) || !empty($user->is_dispatcher));
    }

    // ✅ El propio driver (app) también
    return (int)$driver->tenant_id === (int)$tenantId
        && (int)$driver->id === (int)$driverId;
});




Broadcast::channel('tenant.{tenantId}.ride.{rideId}', function ($user, int $tenantId, int $rideId) {
    if (!$user) return false;

    // staff del tenant
    if ((int)($user->tenant_id ?? 0) === (int)$tenantId && (!empty($user->is_admin) || !empty($user->is_dispatcher))) {
        return true;
    }

    // driver app: validar que sea SU ride
    $driver = DB::table('drivers')->where('user_id', $user->id)->first(['id','tenant_id']);
    if (!$driver) return false;
    if ((int)$driver->tenant_id !== (int)$tenantId) return false;

    $owns = DB::table('rides')
        ->where('id', $rideId)
        ->where('tenant_id', $tenantId)
        ->where('driver_id', $driver->id)
        ->exists();

    return $owns;
});


Broadcast::channel('partner.{partnerId}.drivers', function ($user, $partnerId) {
    return (int) session('partner_id') === (int) $partnerId;
});

Broadcast::channel('partner.{partnerId}.rides', function ($user, $partnerId) {
    return (int) session('partner_id') === (int) $partnerId;
});
