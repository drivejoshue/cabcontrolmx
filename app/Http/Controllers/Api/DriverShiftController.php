<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverShiftController extends Controller
{
    public function start(Request $r)
    {
        $user = $r->user();
        $tenantId = $user->tenant_id ?? 1;

        $data = $r->validate([
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
        ]);

        // driver vinculado
        $driver = DB::table('drivers')->where('user_id',$user->id)->where('tenant_id',$tenantId)->first();
        if (!$driver) return response()->json(['message'=>'No driver'], 403);

        // Buscar asignación vigente; si no hay y mandaron vehicle_id, crearla on-the-fly
        $assignment = DB::table('driver_vehicle_assignments')
            ->where('tenant_id',$tenantId)
            ->where('driver_id',$driver->id)
            ->whereNull('end_at')
            ->first();

        if (!$assignment && !empty($data['vehicle_id'])) {
            // cerrar conflictos (driver y vehicle)
            DB::table('driver_vehicle_assignments')
                ->where('tenant_id',$tenantId)->where('driver_id',$driver->id)->whereNull('end_at')
                ->update(['end_at'=>now(),'updated_at'=>now()]);
            DB::table('driver_vehicle_assignments')
                ->where('tenant_id',$tenantId)->where('vehicle_id',$data['vehicle_id'])->whereNull('end_at')
                ->update(['end_at'=>now(),'updated_at'=>now()]);

            $assignId = DB::table('driver_vehicle_assignments')->insertGetId([
                'tenant_id'=>$tenantId,'driver_id'=>$driver->id,'vehicle_id'=>$data['vehicle_id'],
                'start_at'=>now(),'end_at'=>null,'note'=>null,'created_at'=>now(),'updated_at'=>now(),
            ]);
            $assignment = (object)['id'=>$assignId, 'vehicle_id'=>$data['vehicle_id']];
        }

        if (!$assignment) {
            return response()->json(['message'=>'Sin asignación vigente. Selecciona vehículo.'], 422);
        }

        $shiftId = DB::table('driver_shifts')->insertGetId([
            'tenant_id'    => $tenantId,
            'driver_id'    => $driver->id,
            'vehicle_id'   => $assignment->vehicle_id ?? null,
            'assignment_id'=> $assignment->id,
            'started_at'   => now(),
            'ended_at'     => null,
            'status'       => 'open',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return response()->json(['ok'=>true, 'shift_id'=>$shiftId]);
    }

    public function finish(Request $r)
    {
        $user = $r->user();
        $tenantId = $user->tenant_id ?? 1;

        $data = $r->validate([
            'shift_id' => 'required|integer',
        ]);

        $aff = DB::table('driver_shifts')
            ->where('tenant_id',$tenantId)
            ->where('id',$data['shift_id'])
            ->whereNull('ended_at')
            ->update(['ended_at'=>now(), 'status'=>'closed', 'updated_at'=>now()]);

        return response()->json(['ok'=> (bool)$aff]);
    }
}
