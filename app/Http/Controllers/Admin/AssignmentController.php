<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssignmentController extends Controller
{
    // Asigna VEHÍCULO a un DRIVER (crea una nueva asignación vigente)
    public function assignVehicleToDriver(Request $r, int $driverId)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;

        $data = $r->validate([
            'vehicle_id' => 'required|integer|exists:vehicles,id',
            'start_at'   => 'nullable|date',
            'note'       => 'nullable|string|max:255',
            'close_conflicts' => 'nullable|boolean',
        ]);

        // Validar pertenencia al tenant (driver y vehicle)
        $driver = DB::table('drivers')->where('tenant_id',$tenantId)->where('id',$driverId)->first();
        $vehicle= DB::table('vehicles')->where('tenant_id',$tenantId)->where('id',$data['vehicle_id'])->first();
        abort_if(!$driver || !$vehicle, 404);

        $startAt = $data['start_at'] ?? now();

        DB::beginTransaction();
        try {
            if ($data['close_conflicts'] ?? true) {
                // Cerrar asignaciones vigentes del driver
                DB::table('driver_vehicle_assignments')
                    ->where('tenant_id',$tenantId)
                    ->where('driver_id',$driverId)
                    ->whereNull('end_at')
                    ->update(['end_at'=>now(), 'updated_at'=>now()]);

                // Cerrar asignaciones vigentes del vehicle
                DB::table('driver_vehicle_assignments')
                    ->where('tenant_id',$tenantId)
                    ->where('vehicle_id',$data['vehicle_id'])
                    ->whereNull('end_at')
                    ->update(['end_at'=>now(), 'updated_at'=>now()]);
            }

            DB::table('driver_vehicle_assignments')->insert([
                'tenant_id'  => $tenantId,
                'driver_id'  => $driverId,
                'vehicle_id' => $data['vehicle_id'],
                'start_at'   => $startAt,
                'end_at'     => null,
                'note'       => $data['note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();
            return back()->with('ok','Asignación actualizada para el conductor.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['assign'=>'No se pudo crear la asignación: '.$e->getMessage()]);
        }
    }

    // Asigna DRIVER a un VEHÍCULO (atajo simétrico)
    public function assignDriverToVehicle(Request $r, int $vehicleId)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;

        $data = $r->validate([
            'driver_id'    => 'required|integer|exists:drivers,id',
            'start_at'     => 'nullable|date',
            'note'         => 'nullable|string|max:255',
            'close_conflicts' => 'nullable|boolean',
        ]);

        // Validar pertenencia al tenant
        $vehicle= DB::table('vehicles')->where('tenant_id',$tenantId)->where('id',$vehicleId)->first();
        $driver = DB::table('drivers')->where('tenant_id',$tenantId)->where('id',$data['driver_id'])->first();
        abort_if(!$vehicle || !$driver, 404);

        $startAt = $data['start_at'] ?? now();

        DB::beginTransaction();
        try {
            if ($data['close_conflicts'] ?? true) {
                DB::table('driver_vehicle_assignments')
                    ->where('tenant_id',$tenantId)
                    ->where('driver_id',$data['driver_id'])
                    ->whereNull('end_at')
                    ->update(['end_at'=>now(), 'updated_at'=>now()]);
                DB::table('driver_vehicle_assignments')
                    ->where('tenant_id',$tenantId)
                    ->where('vehicle_id',$vehicleId)
                    ->whereNull('end_at')
                    ->update(['end_at'=>now(), 'updated_at'=>now()]);
            }

            DB::table('driver_vehicle_assignments')->insert([
                'tenant_id'  => $tenantId,
                'driver_id'  => $data['driver_id'],
                'vehicle_id' => $vehicleId,
                'start_at'   => $startAt,
                'end_at'     => null,
                'note'       => $data['note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();
            return back()->with('ok','Asignación actualizada para el vehículo.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['assign'=>'No se pudo crear la asignación: '.$e->getMessage()]);
        }
    }

    // Cierra una asignación (end_at = now)
    public function close(int $id)
    {
        $tenantId = Auth::user()->tenant_id ?? 1;

        $aff = DB::table('driver_vehicle_assignments')
            ->where('tenant_id',$tenantId)
            ->where('id',$id)
            ->whereNull('end_at')
            ->update(['end_at'=>now(), 'updated_at'=>now()]);

        return back()->with($aff ? 'ok' : 'warn', $aff ? 'Asignación cerrada.' : 'Asignación ya estaba cerrada o no existe.');
    }
}
