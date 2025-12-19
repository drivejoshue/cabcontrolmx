<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DriverVehiclesController extends Controller
{
    public function index(Request $r)
    {
        $user = $r->user();
        if (!$user) return response()->json(['ok'=>false,'message'=>'Unauthorized'], 401);

        $tenantId = $user->tenant_id ?? null;
        if (!$tenantId) {
            return response()->json([
                'ok'      => false,
                'message' => 'Usuario sin tenant asignado',
            ], 403);
        }
        // Localizar driver por user_id (multi-tenant si existe columna)
        $driverQ = DB::table('drivers')->where('user_id', $user->id);
        if (Schema::hasColumn('drivers','tenant_id')) {
            $driverQ->where('tenant_id', $tenantId);
        }
        $driver = $driverQ->first();
        if (!$driver) return response()->json(['ok'=>false,'message'=>'Driver no encontrado'], 404);

       

        // Asignaciones abiertas (end_at NULL) -> vehículos distintos
        $q = DB::table('driver_vehicle_assignments as a')
            ->join('vehicles as v', function ($j) use ($tenantId) {
                $j->on('v.id', '=', 'a.vehicle_id')
                ->where('v.active', 1)
                  ->where('v.tenant_id', '=', $tenantId);
            })
            ->where('a.tenant_id', $tenantId)
            ->where('a.driver_id', $driver->id)
            ->whereNull('a.end_at')
            ->select(
                'v.id','v.economico','v.plate','v.brand','v.model','v.type'
            )
            ->distinct()
            ->orderBy('v.economico');

        $items = $q->get();

        return response()->json([
            'ok'    => true,
            'count' => $items->count(),
            'items' => $items,
        ]);
    }
}
