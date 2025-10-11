<?php

// app/Http/Controllers/Api/DriverLocationController.php
// app/Http/Controllers/Api/DriverLocationController.php
namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverLocationController
{
    public function store(Request $req, ?int $driver = null) {
        return $this->update($req, $driver);
    }

    public function update(Request $req, ?int $driver = null)
    {
        $data = $req->validate([
            'lat'         => 'required|numeric',
            'lng'         => 'required|numeric',
            'heading_deg' => 'nullable|numeric',
            'speed'       => 'nullable|numeric',  // ← aceptamos 'speed' y mapeamos a speed_kmh
        ]);

        $driverId = $this->resolveDriverId($req, $driver);
        if (!$driverId) abort(400, 'No driver bound');

        $tenantId = $this->resolveTenantId($req, $driverId);
        if (!$tenantId) abort(400, 'No tenant bound');

        // Inserta ping (speed -> speed_kmh)
        DB::table('driver_locations')->insert([
            'tenant_id'   => $tenantId,
            'driver_id'   => $driverId,
            'lat'         => (float)$data['lat'],
            'lng'         => (float)$data['lng'],
            'heading_deg' => $data['heading_deg'] ?? null,
            'speed_kmh'   => $data['speed'] ?? null,
            'reported_at' => now(),
            'created_at'  => now(),
            
        ]);

        // ¿tiene viaje activo?
        $hasRide = DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('driver_id', $driverId)
            ->whereIn('status', ['ASSIGNED','EN_ROUTE','ARRIVED','BOARDING','ONBOARD'])
            ->exists();

        // Actualiza estado/last_seen
        DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('id', $driverId)
            ->update([
                'status'       => $hasRide ? 'busy' : 'idle',
                'last_seen_at' => now(),
                'updated_at'  => now(),
               
            ]);

        return response()->json(['ok'=>true, 'driver_id'=>$driverId, 'tenant_id'=>$tenantId]);
    }

    private function resolveDriverId(Request $req, ?int $driverParam): ?int
    {
        if ($driverParam) return (int)$driverParam;
        if ($h = $req->header('X-Driver-ID')) return (int)$h;

        if ($u = $req->user()) {
            if (!empty($u->driver_id)) return (int)$u->driver_id;
            $found = DB::table('drivers')->where('user_id', $u->id)->value('id');
            if ($found) return (int)$found;
        }
        return null;
    }

    private function resolveTenantId(Request $req, int $driverId): ?int
    {
        // Prioriza header para pruebas si lo envías
        if ($h = $req->header('X-Tenant-ID')) return (int)$h;

        // Obtén tenant del driver
        $tid = DB::table('drivers')->where('id', $driverId)->value('tenant_id');
        return $tid ? (int)$tid : null;
    }
}
