<?php

use Illuminate\Support\Facades\Broadcast;

// Para producción puedes registrar rutas de auth con Sanctum:
// Broadcast::routes(['middleware' => ['auth:sanctum']]);

// Demo: canal público (ya probado)
Broadcast::channel('driver.location.{tenantId}', function ($user = null, int $tenantId) {
    return true;
});

// Producción (privado/presence), ejemplo:
//
// Broadcast::routes(['middleware' => ['auth:sanctum']]);
// Broadcast::channel('private.driver.location.{tenantId}', function ($user, int $tenantId) {
//     return (int)($user->tenant_id ?? 0) === $tenantId ? [
//         'id' => $user->id,
//         'name' => $user->name,
//         'role' => $user->role,
//     ] : false;
// });
