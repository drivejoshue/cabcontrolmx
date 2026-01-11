<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantConsoleVehicleController extends Controller
{
    public function toggleActive(Request $request, Tenant $tenant, Vehicle $vehicle)
    {
        // Guardarraíl: evitar cross-tenant por route-model-binding
        abort_unless((int)$vehicle->tenant_id === (int)$tenant->id, 404, 'Vehicle no pertenece al tenant');

        DB::transaction(function () use ($tenant, $vehicle) {
            $row = DB::table('vehicles')
                ->where('id', $vehicle->id)
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->first();

            abort_unless($row, 404, 'Vehicle no encontrado');

            $new = ((int)$row->active === 1) ? 0 : 1;

            DB::table('vehicles')
                ->where('id', $vehicle->id)
                ->where('tenant_id', $tenant->id)
                ->update([
                    'active'     => $new,
                    'updated_at' => now(),
                ]);

            Log::warning('SYSADMIN_VEHICLE_TOGGLE_ACTIVE', [
                'tenant_id'  => $tenant->id,
                'vehicle_id' => $vehicle->id,
                'new_active' => $new,
            ]);
        });

        return back()->with('ok', 'Estado del vehículo actualizado.');
    }
}
