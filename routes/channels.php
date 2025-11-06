<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

Broadcast::routes([
    'middleware' => ['auth:sanctum', 'throttle:60,1'],
    'prefix' => 'api',
]);

// Canal PRIVADO por chofer
Broadcast::channel('tenant.{tenantId}.driver.{driverId}', function ($user, $tenantId, $driverId) {
    if (!$user) return false;

    $tenantId = (int)$tenantId;
    $driverId = (int)$driverId;

    $driver = DB::table('drivers')->where('user_id', $user->id)->first(['id','tenant_id']);
    if (!$driver) return false;

    return ((int)$driver->tenant_id === $tenantId) && ((int)$driver->id === $driverId);
});
Broadcast::channel('public-test', function () {
    return true;
});
