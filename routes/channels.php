<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

/*
 |----------------------------------------------------------------------
 | Auth de broadcasting para APPS (Bearer token) bajo /api/*
 |  - Móvil NO usa cookies; requiere 'auth:sanctum'
 |  - Prefijo /api para que el cliente use /api/broadcasting/auth
 |  - Aplícale un throttle básico
 |----------------------------------------------------------------------
 |
 | ⚠️ Asegúrate de NO registrar Broadcast::routes() también en
 |     App\Providers\BroadcastServiceProvider::boot() para evitar doble registro.
 |
 */
Broadcast::routes([
    'middleware' => ['auth:sanctum', 'throttle:60,1'],
    'prefix'     => 'api',
]);

/*
 |----------------------------------------------------------------------
 | Canal PÚBLICO de demo (opcional)
 |   - Úsalo solo para pruebas rápidas de ubicación pública por tenant
 |----------------------------------------------------------------------
 */
Broadcast::channel('driver.location.{tenantId}', function ($user = null, int $tenantId) {
    return true; // público; elimina esto en producción si no lo necesitas
});

/*
 |----------------------------------------------------------------------
 | Canal PRIVADO por chofer
 |   tenant.{tenantId}.driver.{driverId}
 |   - Requiere auth Bearer (Sanctum) en /api/broadcasting/auth
 |   - Verifica que el usuario autenticado efectivamente es ese driver del tenant
 |----------------------------------------------------------------------
 */
Broadcast::channel('tenant.{tenantId}.driver.{driverId}', function ($user, int $tenantId, int $driverId) {
    if (!$user) return false;

    $driver = DB::table('drivers')
        ->where('user_id', $user->id)
        ->first(['id', 'tenant_id']);

    if (!$driver) return false;

    return ((int)$driver->tenant_id === (int)$tenantId)
        && ((int)$driver->id       === (int)$driverId);
});
