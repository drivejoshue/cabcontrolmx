<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\RideBroadcaster;

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
            return response()->json(['ok'=>false,'message'=>'Driver sin tenant asignado'], 403);
        }

        $headerTenant = $r->header('X-Tenant-ID');
        if ($headerTenant && (int)$headerTenant !== $userTenant) {
            return response()->json(['ok'=>false,'message'=>'Tenant inválido para este driver'], 403);
        }

        $tenantId = $userTenant;

        $data = $r->validate([
            'lat'        => 'required|numeric',
            'lng'        => 'required|numeric',
            'busy'       => 'nullable|boolean',
            'speed_kmh'  => 'nullable|numeric',
            'bearing'    => 'nullable|numeric|min:0|max:360', // 360 la normalizamos a 0
            'heading_deg'=> 'nullable|numeric|min:0|max:360',
        ]);

        $driver = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->select('id','status','last_active_status')
            ->first();

        if (!$driver) {
            return response()->json(['ok'=>false,'message'=>'Usuario no vinculado a conductor'], 403);
        }

        if ($driverId && (int)$driverId !== (int)$driver->id) {
            return response()->json(['ok'=>false,'message'=>'Driver inválido'], 403);
        }

        // TZ tenant (para last_ping_at y reported_at)
        $tz = DB::table('tenants')->where('id', $tenantId)->value('timezone')
            ?: config('app.timezone', 'UTC');

        $nowLocal = now($tz);
        $nowUtc   = now('UTC');

        $activeRide = DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('driver_id', $driver->id)
            ->whereIn('status', ['accepted','arrived','on_board'])
            ->select('id','status')
            ->first();

        $speed = array_key_exists('speed_kmh', $data) ? (float)$data['speed_kmh'] : null;
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
            if ($b < 0) $b += 360.0;   // normaliza [0,360)
            $bearing = $b;
            $heading = $b;
        }

        // 1) Historial (si lo estás usando)
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

        // 2) Estado efectivo
        $currentStatus = strtolower((string)($driver->status ?? 'offline'));
        $effectiveStatus = $currentStatus;

        if ($activeRide) {
            $effectiveStatus = 'on_ride';
        } elseif (array_key_exists('busy', $data)) {
            $effectiveStatus = $data['busy'] ? 'busy' : 'idle';
        } elseif ($currentStatus === 'offline') {
            // ping sin busy: revive a last_active_status o idle
            $lastActive = $driver->last_active_status ?? null;
            $effectiveStatus = $lastActive ?: 'idle';
        }

        // 3) Update de drivers (lo que usa el panel)
        $update = [
            'last_lat'     => (float)$data['lat'],
            'last_lng'     => (float)$data['lng'],
            'last_ping_at' => $nowLocal, // local tenant
            'last_bearing' => $bearing,
            'last_speed'   => $speed,
            'last_seen_at' => $nowUtc,   // UTC
            'status'       => $effectiveStatus,
            'updated_at'   => $nowUtc,
        ];

        if ($effectiveStatus !== 'offline') {
            $update['last_active_status'] = $effectiveStatus;
            $update['last_active_at']     = $nowLocal;
        }

        DB::table('drivers')->where('id', $driver->id)->update($update);

        // 4) Broadcast al pasajero si hay ride activo
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
                Log::error('DriverLocation → Error en broadcast', [
                    'tenant_id' => $tenantId,
                    'ride_id'   => $activeRide->id,
                    'driver_id' => $driver->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'ok'          => true,
            'tenant_id'   => $tenantId,
            'driver_id'   => $driver->id,
            'status'      => $effectiveStatus,
            'active_ride' => $activeRide ? [
                'ride_id' => $activeRide->id,
                'status'  => $activeRide->status,
            ] : null,
            'is_stopped'  => $isStopped,
            'bearing_sent'=> $bearing !== null,
        ]);
    }
}
