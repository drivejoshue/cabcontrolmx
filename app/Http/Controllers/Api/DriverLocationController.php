<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\RideBroadcaster;
use App\Services\AutoKickService;

class DriverLocationController extends Controller
{
    public function store(Request $req, ?int $driverId = null)
    {
        return $this->update($req, $driverId);
    }

    public function update(Request $r, ?int $driverId = null)
    {
        $user = $r->user();
        $userTenant = (int)($user->tenant_id ?? 0);

        if ($userTenant <= 0) {
            return response()->json(['ok' => false, 'message' => 'Driver sin tenant asignado'], 403);
        }

        $headerTenant = $r->header('X-Tenant-ID');
        if ($headerTenant && (int)$headerTenant !== $userTenant) {
            return response()->json(['ok' => false, 'message' => 'Tenant inválido para este driver'], 403);
        }

        $tenantId = $userTenant;

        $data = $r->validate([
            'lat'         => 'required|numeric',
            'lng'         => 'required|numeric',
            'busy'        => 'nullable|boolean',
            'speed_kmh'   => 'nullable|numeric',
            'bearing'     => 'nullable|numeric|min:0|max:360', // 360 -> 0
            'heading_deg' => 'nullable|numeric|min:0|max:360',
        ]);

        $driver = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->select('id', 'status')
            ->first();

        if (!$driver) {
            return response()->json(['ok' => false, 'message' => 'Usuario no vinculado a conductor'], 403);
        }

        if ($driverId && (int)$driverId !== (int)$driver->id) {
            return response()->json(['ok' => false, 'message' => 'Driver inválido'], 403);
        }

        // ====== STATUS PREVIO (solo para logging / transición AutoKick) ======
        $prevStatus = strtolower((string)($driver->status ?? 'offline'));

        // TZ tenant (para last_ping_at y reported_at)
        $tz = DB::table('tenants')->where('id', $tenantId)->value('timezone')
            ?: config('app.timezone', 'UTC');

        $nowLocal = now($tz);
        $nowUtc   = now('UTC');

        // OJO: NO forzamos status por activeRide. Solo usamos para broadcast.
       $activeRide = DB::table('rides')
    ->where('tenant_id', $tenantId)
    ->where('driver_id', $driver->id)
    ->whereIn('status', ['accepted', 'arrived', 'on_board'])
    ->select('id', 'status')
    ->first();

    $speed     = array_key_exists('speed_kmh', $data) ? (float)$data['speed_kmh'] : null;
    $isStopped = $speed !== null && $speed < 1.0;

    // bearing: prefer bearing; fallback heading_deg
    $rawBearing = null;
    if (array_key_exists('bearing', $data)) {
        $rawBearing = $data['bearing'];
    } elseif (array_key_exists('heading_deg', $data)) {
        $rawBearing = $data['heading_deg'];
    }

    $bearing = null;
    $heading = null;

 if (!$isStopped && $rawBearing !== null) {
    $b = (float)$rawBearing;
    $b = fmod($b, 360.0);
    if ($b < 0) $b += 360.0; // [0,360)

    // ✅ PNG base apunta a la izquierda (Oeste=270)
    // Rotación necesaria: bearing_real - 270  == bearing_real + 90
    $offset = 90.0;
    $b = fmod($b + $offset, 360.0);
    if ($b < 0) $b += 360.0;

    if ($b == 360.0) $b = 0.12;

    $bearing = $b;
    $heading = $b;
}


        // 1) Historial
        DB::table('driver_locations')->insert([
            'tenant_id'   => $tenantId,
            'driver_id'   => $driver->id,
            'lat'         => (float)$data['lat'],
            'lng'         => (float)$data['lng'],
            'speed_kmh'   => $speed,
            'bearing'     => $bearing,
            'heading_deg' => $heading,
            'reported_at' => $nowLocal,
            'created_at'  => $nowLocal,
        ]);

        // 2) Status: SOLO si viene busy explícito.
        // - Nunca forzamos on_ride.
        // - Nunca revivimos last_active_status.
        $busyInPayload = array_key_exists('busy', $data);

        $effectiveStatus = $prevStatus;
        if ($busyInPayload) {
            $effectiveStatus = ((bool)$data['busy']) ? 'busy' : 'idle';
        }

        // 3) Update drivers (panel)
        // Siempre telemetría. Status solo si busy viene explícito.
        $update = [
            'last_lat'     => (float)$data['lat'],
            'last_lng'     => (float)$data['lng'],
            'last_ping_at' => $nowLocal, // local tenant
            'last_bearing' => $bearing,
            'last_speed'   => $speed,
            'last_seen_at' => $nowUtc,   // UTC
            'updated_at'   => $nowUtc,
        ];

        if ($busyInPayload) {
            $update['status'] = $effectiveStatus;
        }

        DB::table('drivers')->where('id', $driver->id)->update($update);

        // ===== LOG BASE DEL PING =====
        Log::info('DriverLocation.ping', [
            'tenant_id'        => $tenantId,
            'driver_id'        => (int)$driver->id,
            'prev_status'      => $prevStatus,
            'effective_status' => $effectiveStatus,
            'busy_in_payload'  => $busyInPayload ? (bool)$data['busy'] : null,
            'active_ride_id'   => $activeRide ? (int)$activeRide->id : null,
            'speed_kmh'        => $speed,
            'bearing'          => $bearing,
            'status_written'   => $busyInPayload ? 1 : 0,
        ]);

        // ===============================
        // AUTOKICK: TRANSICIÓN A IDLE
        // Solo si NO hay ride activo y el payload trae busy=false (o sea, transición explícita).
        // ===============================
        if (!$activeRide && $busyInPayload) {
            $wasUnavailable = in_array($prevStatus, ['offline', 'busy', 'on_ride'], true);
            $becameIdle     = ($effectiveStatus === 'idle');

            if ($wasUnavailable && $becameIdle) {
                $key = "autokick:tenant:$tenantId:driver:{$driver->id}";
                $debounced = Cache::add($key, 1, 8); // 8s debounce

                Log::info('AutoKick.check', [
                    'tenant_id'   => $tenantId,
                    'driver_id'   => (int)$driver->id,
                    'prev_status' => $prevStatus,
                    'now_status'  => $effectiveStatus,
                    'debounced'   => $debounced ? 0 : 1,
                ]);

                if ($debounced) {
                    try {
                        $kickRes = AutoKickService::kickNearestRideForDriver(
                            tenantId: $tenantId,
                            driverId: (int)$driver->id,
                            lat: (float)$data['lat'],
                            lng: (float)$data['lng']
                        );

                        Log::info('AutoKick.done', [
                            'tenant_id' => $tenantId,
                            'driver_id' => (int)$driver->id,
                            'res'       => $kickRes,
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('AutoKick.failed', [
                            'tenant_id' => $tenantId,
                            'driver_id' => (int)$driver->id,
                            'err'       => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        // 4) Broadcast al pasajero si hay ride activo (solo ubicación, no status)
        if ($activeRide) {
            try {
                RideBroadcaster::location(
                    tenantId: $tenantId,
                    rideId: (int)$activeRide->id,
                    lat: (float)$data['lat'],
                    lng: (float)$data['lng'],
                    bearing: $bearing
                );
            } catch (\Throwable $e) {
                Log::error('DriverLocation.broadcast_failed', [
                    'tenant_id' => $tenantId,
                    'ride_id'   => (int)$activeRide->id,
                    'driver_id' => (int)$driver->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'ok'           => true,
            'tenant_id'    => $tenantId,
            'driver_id'    => (int)$driver->id,
            'prev_status'  => $prevStatus,
            'status'       => $effectiveStatus,
            'active_ride'  => $activeRide ? [
                'ride_id' => (int)$activeRide->id,
                'status'  => (string)$activeRide->status,
            ] : null,
            'is_stopped'   => $isStopped,
            'bearing_sent' => $bearing !== null,
        ]);
    }
}
