<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaxiStandController extends Controller
{
    // GET /api/taxistands
    public function index(Request $request)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;

        $rows = DB::table('taxi_stands as t')
            ->leftJoin('sectores as s', function ($q) use ($tenantId) {
                $q->on('s.id', '=', 't.sector_id')->where('s.tenant_id', '=', $tenantId);
            })
            ->where('t.tenant_id', $tenantId)
            ->where('t.activo', 1)
            ->select(
                't.id','t.nombre','t.latitud','t.longitud','t.capacidad','t.codigo',
                't.qr_secret','t.sector_id',
                DB::raw('COALESCE(s.nombre, "") as sector_nombre')
            )
            ->orderBy('t.id', 'desc')
            ->get();

        return response()->json($rows);
    }

    // POST /api/driver/stands/join { stand_id?:int, codigo?:string }
    public function join(Request $req)
    {
        $tenantId = Auth::user()->tenant_id;
        $driverId = Auth::user()->driver_id ?? $req->user()->driver_id ?? null;
        if (!$driverId) abort(403, 'Driver no asociado al token');

        $data = $req->validate([
            'stand_id' => 'nullable|integer',
            'codigo'   => 'nullable|string'
        ]);

        $standId = $data['stand_id'] ?? null;

        // Permite unirse por código/QR también
        if (!$standId && !empty($data['codigo'])) {
            $stand = DB::table('taxi_stands')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($data) {
                    $q->where('codigo', $data['codigo'])
                      ->orWhere('qr_secret', $data['codigo']);
                })->first();
            if (!$stand) return response()->json(['ok'=>false,'error'=>'Código de base inválido'], 422);
            $standId = $stand->id;
        }

        if (!$standId) {
            return response()->json(['ok'=>false,'error'=>'stand_id o codigo requeridos'], 422);
        }

        try {
            DB::statement('CALL sp_queue_join_stand_v1(?, ?, ?)', [
                $tenantId, $standId, $driverId
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok'=>false,'error'=>$e->getMessage()], 422);
        }

        return response()->json(['ok'=>true]);
    }

    // POST /api/driver/stands/leave { stand_id:int, status_to?: "asignado"|"salio" }
    public function leave(Request $req)
    {
        $tenantId = Auth::user()->tenant_id;
        $driverId = Auth::user()->driver_id ?? $req->user()->driver_id ?? null;
        if (!$driverId) abort(403, 'Driver no asociado al token');

        $v = $req->validate([
            'stand_id'  => 'required|integer',
            'status_to' => 'nullable|string|in:asignado,salio'
        ]);

        try {
            DB::statement('CALL sp_queue_leave_stand_v1(?, ?, ?, ?)', [
                $tenantId, $v['stand_id'], $driverId, $v['status_to'] ?? 'salio'
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok'=>false,'error'=>$e->getMessage()], 422);
        }

        return response()->json(['ok'=>true]);
    }

    // GET /api/driver/stands/status?stand_id=...
    public function status(Request $req)
    {
        $tenantId = Auth::user()->tenant_id;
        $driverId = Auth::user()->driver_id ?? $req->user()->driver_id ?? null;
        if (!$driverId) abort(403, 'Driver no asociado al token');

        $standId = (int) $req->query('stand_id');
        if (!$standId) return response()->json(['ok'=>false,'error'=>'stand_id requerido'], 422);

        // posición actual del driver (si está en cola)
        $me = DB::table('taxi_stand_queue')
            ->where(compact('tenantId'))
            ->where('stand_id', $standId)
            ->where('driver_id', $driverId)
            ->where('status', 'en_cola')
            ->orderByDesc('id')
            ->first(['position']);

        // lista compacta de cola (top 50)
        $queue = DB::table('taxi_stand_queue as q')
            ->join('drivers as d', 'd.id', '=', 'q.driver_id')
            ->leftJoin('vehicles as v', 'v.id', '=', 'd.active_vehicle_id') // o vía shift si prefieres
            ->where('q.tenant_id', $tenantId)
            ->where('q.stand_id',  $standId)
            ->where('q.status',    'en_cola')
            ->orderBy('q.position')
            ->limit(50)
            ->get([
                'q.driver_id', 'q.position',
                'd.status as driver_status',
                'd.last_lat', 'd.last_lng',
                'v.economico', 'v.plate'
            ]);

        $ahead = null;
        if ($me && isset($me->position)) {
            $ahead = DB::table('taxi_stand_queue')
                ->where('tenant_id', $tenantId)
                ->where('stand_id',  $standId)
                ->where('status',    'en_cola')
                ->where('position', '<', $me->position)
                ->count();
        }

        return response()->json([
            'ok'          => true,
            'in_queue'    => !!$me,
            'my_position' => $me->position ?? null,
            'ahead_count' => $ahead,
            'queue'       => $queue
        ]);
    }
}
