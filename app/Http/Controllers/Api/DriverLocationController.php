<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\RideBroadcaster;

class DriverLocationController
{
    public function store(Request $req, ?int $driver = null) {
        return $this->update($req, $driver);
    }

    public function update(Request $r)
    {
        $user = $r->user();
        $tenantId = $r->header('X-Tenant-ID') ?: ($user->tenant_id ?? 1);

        $data = $r->validate([
            'lat'  => 'required|numeric',
            'lng'  => 'required|numeric',
            'busy' => 'nullable|boolean',
            'speed_kmh' => 'nullable|numeric',
            'bearing' => 'nullable|numeric|min:0|max:360',
            'heading_deg' => 'nullable|numeric|min:0|max:360',
        ]);

        $driver = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->first();
            
        if(!$driver) {
            return response()->json(['ok'=>false,'msg'=>'No driver'], 403);
        }

        // === TZ local del tenant ===
        $tz = DB::table('tenants')->where('id', $tenantId)->value('timezone')
            ?: config('app.timezone', 'UTC');
        $now = now($tz);

        // 🔥 Buscar ride activo del driver - CORREGIDO
        $activeRide = DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('driver_id', $driver->id)
            ->whereIn('status', ['accepted', 'arrived', 'on_board'])
            ->select('id', 'status')
            ->first();

        // Insertar ubicación del driver
     DB::table('driver_locations')->insert([
    'tenant_id'    => $tenantId,
    'driver_id'    => $driver->id,
    'lat'          => (float)$data['lat'],
    'lng'          => (float)$data['lng'],
    'speed_kmh'    => isset($data['speed_kmh']) ? (float)$data['speed_kmh'] : null,
    'bearing'      => isset($data['bearing']) ? (float)$data['bearing'] : null,
    'heading_deg'  => isset($data['bearing']) ? (float)$data['bearing'] : null, // 👈 clave
    'reported_at'  => $now,
    'created_at'   => $now,
]);


        // 🔥 Broadcast al passenger si hay ride activo
        if ($activeRide) {
            try {
                RideBroadcaster::location(
                    tenantId: $tenantId,
                    rideId: (int)$activeRide->id,
                    lat: (float)$data['lat'],
                    lng: (float)$data['lng'],
                    bearing: isset($data['bearing']) ? (float)$data['bearing'] : null
                );

                \Log::info('DriverLocation → Ubicación broadcast al ride', [
                    'tenant_id' => $tenantId,
                    'ride_id' => $activeRide->id,
                    'driver_id' => $driver->id,
                    'lat' => $data['lat'],
                    'lng' => $data['lng'],
                    'bearing' => $data['bearing'] ?? null
                ]);

            } catch (\Throwable $e) {
                \Log::error('DriverLocation → Error en broadcast', [
                    'tenant_id' => $tenantId,
                    'ride_id' => $activeRide->id,
                    'driver_id' => $driver->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Actualizar estado busy/idle si viene en la request
        if (array_key_exists('busy', $data)) {
            DB::table('drivers')->where('id', $driver->id)->update([
                'status'     => $data['busy'] ? 'busy' : 'idle',
                'updated_at' => $now,
            ]);
        }

        return response()->json([
            'ok' => true,
            'active_ride' => $activeRide ? [
                'ride_id' => $activeRide->id,
                'status' => $activeRide->status
            ] : null
        ]);
    }
}