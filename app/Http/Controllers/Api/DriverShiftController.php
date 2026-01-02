<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DriverShiftController extends Controller
{   





  public function start(Request $r)
{
    $user = $r->user();
    $tenantId = (int)($user->tenant_id ?? 0);
    if ($tenantId <= 0) abort(403, 'Driver sin tenant asignado');

    $driver = DB::table('drivers')
        ->where('user_id', $user->id)
        ->where('tenant_id', $tenantId)
        ->first();
    if (!$driver) return response()->json(['ok'=>false,'message'=>'No driver'], 403);

   $data = $r->validate([
    'vehicle_id' => [
        'nullable',
        'integer',
        Rule::exists('vehicles', 'id')->where(fn($q) => $q
            ->where('tenant_id', $tenantId)
            ->where('active', 1)
        ),
    ],
]);

    try {
        return DB::transaction(function () use ($tenantId, $driver, $data) {

            // ✅ 1) Si ya hay turno abierto, NO crees otro (idempotencia)
            $openShift = DB::table('driver_shifts')
                ->where('tenant_id', $tenantId)
                ->where('driver_id', $driver->id)
                ->whereNull('ended_at')
                ->where('status', 'abierto')
                ->orderByDesc('started_at')
                ->lockForUpdate()
                ->first();

            if ($openShift) {
                // (Opcional) si mandan vehicle_id distinto, puedes bloquear cambio
                if (!empty($data['vehicle_id']) && (int)$data['vehicle_id'] !== (int)$openShift->vehicle_id) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Ya tienes un turno abierto. Ciérralo para cambiar de vehículo.'
                    ], 409);
                }

                return response()->json([
                    'ok' => true,
                    'shift_id' => $openShift->id,
                    'already_open' => true,
                    'message' => 'Turno ya estaba abierto'
                ], 200);
            }

            // ✅ 2) (Tu lógica actual) resolver assignment
            $assignment = DB::table('driver_vehicle_assignments')
                ->where('tenant_id',$tenantId)
                ->where('driver_id',$driver->id)
                ->whereNull('end_at')
                ->first();

            if (!$assignment && !empty($data['vehicle_id'])) {
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
                return response()->json(['ok'=>false,'message'=>'Sin asignación vigente. Selecciona vehículo.'], 422);
            }

            // ✅ 3) Crear turno (único)
            $shiftId = DB::table('driver_shifts')->insertGetId([
                'tenant_id'     => $tenantId,
                'driver_id'     => $driver->id,
                'vehicle_id'    => $assignment->vehicle_id ?? null,
                'assignment_id' => $assignment->id,
                'started_at'    => now(),
                'ended_at'      => null,
                'status'        => 'abierto',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            DB::table('drivers')->where('id', $driver->id)->update([
                'status'             => 'idle',
                'last_active_status' => 'idle',
                'last_active_at'     => now(),
                'updated_at'         => now(),
            ]);

            return response()->json(['ok'=>true, 'shift_id'=>$shiftId], 200);
        });
    } catch (\Throwable $e) {
        return response()->json([
            'ok'=>false,
            'message'=>'Error al abrir turno',
            'error'=>config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}


 public function finish(Request $r)
{
    $user = $r->user();
    $tenantId = (int)($user->tenant_id ?? 0);
    if ($tenantId <= 0) abort(403, 'Driver sin tenant asignado');

    $driver = DB::table('drivers')
        ->where('user_id', $user->id)
        ->where('tenant_id', $tenantId)
        ->first();

    if (!$driver) {
        return response()->json(['ok' => false, 'message' => 'No driver'], 403);
    }

    $data = $r->validate([
        'shift_id' => 'nullable|integer',
    ]);

    try {
        $result = DB::transaction(function () use ($tenantId, $driver, $data) {

            // 1) Resolver el turno a cerrar (id opcional)
            $shift = null;

            if (!empty($data['shift_id'])) {
                // si el cliente manda shift_id, intenta ese
                $shift = DB::table('driver_shifts')
                    ->where('tenant_id', $tenantId)
                    ->where('id', (int)$data['shift_id'])
                    ->where('driver_id', $driver->id)
                    ->lockForUpdate()
                    ->first();

                if (!$shift) {
                    // Idempotencia: si no existe, no es "error fatal"
                    return [
                        'ok' => true,
                        'already_closed' => true,
                        'shift_id' => (int)$data['shift_id'],
                        'http' => 200,
                        'msg' => 'Turno ya estaba cerrado o no existe'
                    ];
                }
            } else {
                // si NO mandan shift_id, toma el último abierto
                $shift = DB::table('driver_shifts')
                    ->where('tenant_id', $tenantId)
                    ->where('driver_id', $driver->id)
                    ->where('status', 'abierto')
                    ->whereNull('ended_at')
                    ->orderByDesc('started_at')
                    ->lockForUpdate()
                    ->first();

                if (!$shift) {
                    // ✅ Idempotente: NO 404
                    return [
                        'ok' => true,
                        'already_closed' => true,
                        'shift_id' => null,
                        'http' => 200,
                        'msg' => 'No hay turno abierto (ya estaba cerrado)'
                    ];
                }
            }

            // 2) Si el turno ya está cerrado, responde idempotente
            if ($shift->ended_at !== null || $shift->status !== 'abierto') {
                return [
                    'ok' => true,
                    'already_closed' => true,
                    'shift_id' => $shift->id,
                    'http' => 200,
                    'msg' => 'Turno ya estaba cerrado'
                ];
            }

            // 3) No cerrar turno si hay ride activo
            // Incluye queued porque en tu flujo es "activo en cola"
            $hasActiveRide = DB::table('rides')
                ->where('tenant_id', $tenantId)
                ->where('driver_id', $driver->id)
                ->whereIn('status', [
                    'queued',
                    'accepted',
                    'en_route',
                    'arrived',
                    'on_board',
                    'on_trip',
                ])
                ->exists();

            if ($hasActiveRide) {
                return [
                    'ok' => false,
                    'already_closed' => false,
                    'shift_id' => $shift->id,
                    'http' => 422,
                    'msg' => 'No puedes cerrar turno con un servicio activo'
                ];
            }

            // 4) Cerrar turno
            DB::table('driver_shifts')
                ->where('tenant_id', $tenantId)
                ->where('id', $shift->id)
                ->update([
                    'ended_at'   => now(),
                    'status'     => 'cerrado',
                    'updated_at' => now(),
                ]);

            // 5) Marcar driver offline si ya no tiene otro shift abierto
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

            return [
                'ok' => true,
                'already_closed' => false,
                'shift_id' => $shift->id,
                'http' => 200,
                'msg' => 'Turno cerrado'
            ];
        });

        return response()->json([
            'ok'            => $result['ok'],
            'message'       => $result['msg'],
            'shift_id'      => $result['shift_id'],
            'already_closed'=> $result['already_closed'],
        ], $result['http']);

    } catch (\Throwable $e) {
        return response()->json([
            'ok'      => false,
            'message' => 'Error al cerrar turno',
            'error'   => config('app.debug') ? $e->getMessage() : null,
        ], 500);
    }
}


}
