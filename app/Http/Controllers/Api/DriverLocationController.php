<?php

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
            'lat'          => 'required|numeric',
            'lng'          => 'required|numeric',
            'heading_deg'  => 'nullable|numeric',
            'speed'        => 'nullable|numeric',   // alias
            'speed_kmh'    => 'nullable|numeric',   // nativo si lo mandas
        ]);

        // --- quién es el driver / tenant ---
        $driverId = $this->resolveDriverId($req, $driver);
        if (!$driverId) abort(400, 'No driver bound');

        $tenantId = $this->resolveTenantId($req, $driverId);
        if (!$tenantId) abort(400, 'No tenant bound');

        $now   = now();
        $speed = $data['speed_kmh'] ?? $data['speed'] ?? null;

        // --- 1) guarda ping crudo ---
        DB::table('driver_locations')->insert([
            'tenant_id'   => $tenantId,
            'driver_id'   => $driverId,
            'lat'         => (float)$data['lat'],
            'lng'         => (float)$data['lng'],
            'speed_kmh'   => $speed,
            'heading_deg' => $data['heading_deg'] ?? null,
            'reported_at' => $now,
            'created_at'  => $now,
        ]);

        // --- 2) ¿tiene viaje activo? (para status busy/idle) ---
        $hasRide = DB::table('rides')
            ->where('tenant_id', $tenantId)
            ->where('driver_id', $driverId)
            ->whereIn('status', ['ASSIGNED','EN_ROUTE','ARRIVED','BOARDING','ONBOARD'])
            ->exists();

        // --- 3) actualiza “last_*” en drivers (esto es lo que requieren tus SPs) ---
        DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('id', $driverId)
            ->update([
                'last_lat'     => (float)$data['lat'],
                'last_lng'     => (float)$data['lng'],
                'last_ping_at' => $now,                  // DATETIME
                'last_bearing' => $data['heading_deg'] ?? null,
                'last_speed'   => $speed,
                'last_seen_at' => $now,                  // TIMESTAMP
                'status'       => $hasRide ? 'busy' : 'idle',
                'updated_at'   => $now,
            ]);

        return response()->json([
            'ok'        => true,
            'driver_id' => $driverId,
            'tenant_id' => $tenantId,
            'status'    => $hasRide ? 'busy' : 'idle',
        ]);
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
        if ($h = $req->header('X-Tenant-ID')) return (int)$h;
        $tid = DB::table('drivers')->where('id', $driverId)->value('tenant_id');
        return $tid ? (int)$tid : null;
    }
}
