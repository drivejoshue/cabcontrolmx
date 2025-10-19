<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        ]);

        $driver = DB::table('drivers')
            ->where('tenant_id',$tenantId)->where('user_id',$user->id)->first();
        if(!$driver) return response()->json(['ok'=>false,'msg'=>'No driver'], 403);

        DB::table('driver_locations')->insert([
            'tenant_id' => $tenantId, 'driver_id'=>$driver->id,
            'lat'=>$data['lat'], 'lng'=>$data['lng'],
            'speed_kmh'=>$data['speed_kmh'] ?? null,
            'reported_at'=>now(), 'created_at'=>now(), 
        ]);

        // Ajusta estado manual si viene busy explícito
        if (array_key_exists('busy',$data)) {
            $new = $data['busy'] ? 'busy' : 'idle';
            DB::table('drivers')->where('id',$driver->id)->update([
                'status'=>$new, 
            ]);
        } else {
            // si no mandan busy y el driver no tiene ride activo, lo mantenemos como está
        }

        // Mantén el watchdog aparte para offline por inactividad
        return response()->json(['ok'=>true]);
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
