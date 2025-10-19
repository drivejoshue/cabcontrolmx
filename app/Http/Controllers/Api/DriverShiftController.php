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
            'status'       => 'abierto',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return response()->json(['ok'=>true, 'shift_id'=>$shiftId]);
    }

  public function finish(Request $r)
    {
        $user     = $r->user();
        $tenantId = $user->tenant_id ?? 1;

        // Driver primero (lo usas abajo)
        $driver = DB::table('drivers')
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$driver) {
            return response()->json(['message' => 'No driver'], 403);
        }

        // shift_id OPCIONAL
        $data = $r->validate([
            'shift_id' => 'nullable|integer',
        ]);

        if (empty($data['shift_id'])) {
            $openShift = DB::table('driver_shifts')
                ->where('tenant_id', $tenantId)
                ->where('driver_id', $driver->id)
                ->whereNull('ended_at')
                ->where('status', 'abierto')
                ->orderByDesc('started_at')
                ->first();
            if (!$openShift) {
                return response()->json(['ok'=>false,'message'=>'No hay turno abierto'], 404);
            }
            $data['shift_id'] = $openShift->id;
        }

        try {
            $result = DB::transaction(function () use ($tenantId, $driver, $data) {
                $shift = DB::table('driver_shifts')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $data['shift_id'])
                    ->where('driver_id', $driver->id)
                    ->whereNull('ended_at')
                    ->where('status', 'abierto')
                    ->lockForUpdate()
                    ->first();

                if (!$shift) {
                    return ['ok' => false, 'http' => 404, 'msg' => 'Turno no encontrado o ya cerrado'];
                }

                $hasActiveRide = DB::table('rides')
                    ->where('tenant_id', $tenantId)
                    ->where('driver_id', $driver->id)
                    ->whereIn('status', ['accepted','en_route','arrived','on_board'])
                    ->exists();

                if ($hasActiveRide) {
                    return ['ok' => false, 'http' => 422, 'msg' => 'No puedes cerrar turno con un servicio activo'];
                }

                DB::table('driver_shifts')->where('id', $shift->id)->update([
                    'ended_at'   => now(),
                    'status'     => 'cerrado',
                    'updated_at' => now(),
                ]);

                $hasOtherOpenShift = DB::table('driver_shifts')
                    ->where('tenant_id', $tenantId)
                    ->where('driver_id', $driver->id)
                    ->whereNull('ended_at')
                    ->where('status', 'abierto')
                    ->exists();

                if (!$hasOtherOpenShift) {
                    DB::table('drivers')->where('id', $driver->id)->update([
                        'status'     => 'offline',
                        'updated_at' => now(),
                    ]);
                }

                return ['ok' => true, 'http' => 200, 'msg' => 'Turno cerrado'];
            });

            return response()->json(['ok' => $result['ok'], 'message' => $result['msg']], $result['http']);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Error al cerrar turno',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

}
