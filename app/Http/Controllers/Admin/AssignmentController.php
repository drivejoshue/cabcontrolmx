<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssignmentController extends Controller
{
    private function currentTenantId(): int
    {
        $tenantId = Auth::user()->tenant_id ?? null;

        if (!$tenantId) {
            abort(403, 'Usuario sin tenant asignado');
        }

        return (int) $tenantId;
    }

    // Asigna VEHÍCULO a un DRIVER
    public function assignVehicleToDriver(Request $r, int $driverId)
    {
        $tenantId = $this->currentTenantId();

        $data = $r->validate([
            'vehicle_id'      => 'required|integer|exists:vehicles,id',
            'start_at'        => 'nullable|date',
            'note'            => 'nullable|string|max:255',
            'close_conflicts' => 'nullable|boolean',
        ]);

        $driver = DB::table('drivers')->where('tenant_id',$tenantId)->where('id',$driverId)->first();
        $vehicle= DB::table('vehicles')->where('tenant_id',$tenantId)->where('id',$data['vehicle_id'])->first();
        abort_if(!$driver || !$vehicle, 404);

        $startAt = $data['start_at'] ?? now();

        DB::beginTransaction();
        try {
            if ($data['close_conflicts'] ?? true) {
                DB::table('driver_vehicle_assignments')
                    ->where('tenant_id',$tenantId)
                    ->where('driver_id',$driverId)
                    ->whereNull('end_at')
                    ->update(['end_at'=>now(), 'updated_at'=>now()]);

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

    // Asigna DRIVER a un VEHÍCULO
    public function assignDriverToVehicle(Request $r, int $vehicleId)
    {
        $tenantId = $this->currentTenantId();

        $data = $r->validate([
            'driver_id'       => 'required|integer|exists:drivers,id',
            'start_at'        => 'nullable|date',
            'note'            => 'nullable|string|max:255',
            'close_conflicts' => 'nullable|boolean',
        ]);

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

    // Cierra una asignación
    public function close(int $id)
    {
        $tenantId = $this->currentTenantId();

        $aff = DB::table('driver_vehicle_assignments')
            ->where('tenant_id',$tenantId)
            ->where('id',$id)
            ->whereNull('end_at')
            ->update(['end_at'=>now(), 'updated_at'=>now()]);

        return back()->with($aff ? 'ok' : 'warn', $aff ? 'Asignación cerrada.' : 'Asignación ya estaba cerrada o no existe.');
    }
}
