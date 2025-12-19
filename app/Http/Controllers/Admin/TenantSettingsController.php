<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenant;

class TenantSettingsController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->tenant_id) {
            abort(403, 'Usuario sin tenant asignado');
        }

        $tenant = Tenant::findOrFail($user->tenant_id);

        // Vista específica del panel tenant, NO la de SysAdmin
        return view('admin.tenant-settings.edit', [
            'tenant' => $tenant,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->tenant_id) {
            abort(403, 'Usuario sin tenant asignado');
        }

        $tenant = Tenant::findOrFail($user->tenant_id);

        // Aquí solo permites campos "seguros" para la central
        $data = $request->validate([
            'display_name' => 'required|string|max:255',
            'timezone'     => 'required|string|max:64',
            'city'         => 'nullable|string|max:255',
            // agrega aquí solo lo que pueda tocar la central
        ]);

        $tenant->fill($data);
        $tenant->save();

        return redirect()
            ->route('admin.tenant_settings.edit')
            ->with('status', 'Ajustes del tenant actualizados correctamente.');
    }
}
