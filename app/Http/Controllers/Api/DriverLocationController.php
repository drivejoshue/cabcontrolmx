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
            'presence'    => 'nullable|boolean', // ✅ CAMBIO: aceptar presence one-shot
        ]);

        // ✅ CAMBIO: traer shift_open y last_active_status (y lo que ya estás usando en payload)
        // Nota: hoy tu payload usa vehicle_* y stand_*; si NO están en drivers, déjalos null o haz join real.
       $driver = DB::table('drivers')
    ->where('tenant_id', $tenantId)
    ->where('user_id', $user->id)
    ->select('id','status','partner_id') 
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
            ->select('id', 'status', 'origin_lat', 'origin_lng')
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
            if ($b < 0) $b += 360.0; // normaliza [0,360)
            if ($b == 360.0) $b = 0.5;
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

        // ===============================
        // 2) Status: busy explícito y/o presence one-shot
        // - Nunca forzamos on_ride.
        // - ✅ CAMBIO: presence=1 puede “revivir” offline->idle + last_active_status, solo con shift abierto y sin ride activo.
        // ===============================
        $busyInPayload     = array_key_exists('busy', $data);
        $presenceInPayload = array_key_exists('presence', $data) && (bool)$data['presence'] === true;

        $hasOpenShift = ((int)($driver->shift_open ?? 0) === 1);

        // ✅ Fallback: si está offline + turno abierto + sin ride activo, lo reponemos a idle
        $restoreIdle = false;
        if ($hasOpenShift && !$activeRide) {
            if ($prevStatus === 'offline' && ($presenceInPayload || !$busyInPayload)) {
                $restoreIdle = true;
            }
        }

        $effectiveStatus = $prevStatus;

        // ✅ CAMBIO: presence puede forzar idle (seguro)
        if ($restoreIdle) {
            $effectiveStatus = 'idle';
        }

        // Busy explícito tiene prioridad final
        if ($busyInPayload) {
            $effectiveStatus = ((bool)$data['busy']) ? 'busy' : 'idle';
        }

        if ($busyInPayload || $restoreIdle) {
            $update['status'] = $effectiveStatus; // ✅ lo importante
        }


        // 3) Update drivers (panel)
        // Siempre telemetría. Status/last_active_status si busy viene explícito O presence restore.
        $update = [
            'last_lat'     => (float)$data['lat'],
            'last_lng'     => (float)$data['lng'],
            'last_ping_at' => $nowLocal, // local tenant
            'last_bearing' => $bearing,
            'last_speed'   => $speed,
            'last_seen_at' => $nowUtc,   // UTC
            'updated_at'   => $nowUtc,
        ];

        $statusWritten = 0; // ✅ CAMBIO: log consistente
        if ($busyInPayload || $restoreIdle) {
            $update['status'] = $effectiveStatus;
            $update['last_active_status'] = $effectiveStatus; // ✅ CAMBIO CLAVE
            $statusWritten = 1;
        }

        DB::table('drivers')->where('id', $driver->id)->update($update);

        // ===== LOG BASE DEL PING =====
        Log::info('DriverLocation.ping', [
            'tenant_id'        => $tenantId,
            'driver_id'        => (int)$driver->id,
            'prev_status'      => $prevStatus,
            'effective_status' => $effectiveStatus,
            'busy_in_payload'  => $busyInPayload ? (bool)$data['busy'] : null,
            'presence'         => $presenceInPayload ? 1 : 0,     // ✅ CAMBIO
            'restore_idle'     => $restoreIdle ? 1 : 0,           // ✅ CAMBIO
            'active_ride_id'   => $activeRide ? (int)$activeRide->id : null,
            'speed_kmh'        => $speed,
            'bearing'          => $bearing,
            'status_written'   => $statusWritten,                 // ✅ CAMBIO
        ]);

        // ===============================
        // AUTOKICK: TRANSICIÓN A IDLE
        // Solo si NO hay ride activo y el payload trae busy=false (transición explícita).
        // (presence NO dispara AutoKick)
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

        // ======= payload base =======
        $rideStatus = $activeRide ? (string)$activeRide->status : null;

        $payload = [
            'tenant_id'       => $tenantId,
            'driver_id'       => (int)$driver->id,
            'lat'             => (float)$data['lat'],
            'lng'             => (float)$data['lng'],
            'bearing'         => $bearing,
            'reported_at'     => $nowLocal->format('Y-m-d H:i:s'),
            'driver_status'   => $effectiveStatus,
            'ride_status'     => $rideStatus,
            'shift_open'      => (int)($driver->shift_open ?? 0),

            'vehicle_economico' => $driver->vehicle_economico ?? null,
            'vehicle_plate'     => $driver->vehicle_plate ?? null,
            'vehicle_type'      => $driver->vehicle_type ?? 'sedan',

            'stand_id'       => $driver->stand_id ?? null,
            'stand_status'   => $driver->stand_status ?? null,
        ];

        // origin coords si hay ride activo (para suggested route)
        if ($activeRide && $activeRide->origin_lat !== null && $activeRide->origin_lng !== null) {
            $payload['origin_lat'] = (float)$activeRide->origin_lat;
            $payload['origin_lng'] = (float)$activeRide->origin_lng;
        }

        // broadcast partner (solo si tiene partner_id)
        if (!empty($driver->partner_id)) {
            broadcast(new \App\Events\PartnerDriverLocationUpdated(
                (int)$driver->partner_id,
                $payload
            ));
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
