<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantBillingProfile;
use Illuminate\Http\Request;

class TenantBillingController extends Controller
{
    public function show(Tenant $tenant)
    {
        $profile = $tenant->billingProfile; // relación hasOne en el modelo Tenant

        return view('sysadmin.tenants.billing', [
            'tenant'  => $tenant,
            'profile' => $profile,
        ]);
    }

    public function update(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'plan_code'         => 'required|string|max:40',
            'billing_model'     => 'required|in:per_vehicle,commission',
            'status'            => 'required|in:trial,active,paused,canceled',

            'trial_ends_at'     => 'nullable|date',
            'trial_vehicles'    => 'required|integer|min:0',

            'base_monthly_fee'  => 'required|numeric|min:0',
            'included_vehicles' => 'required|integer|min:0',
            'price_per_vehicle' => 'required|numeric|min:0',

            'max_vehicles'      => 'nullable|integer|min:0',

            'invoice_day'       => 'required|integer|min:1|max:28',

            'commission_percent' => 'nullable|numeric|min:0|max:100',
            'commission_min_fee' => 'required|numeric|min:0',

            'notes'             => 'nullable|string',
        ]);

        /** @var TenantBillingProfile $profile */
        $profile = $tenant->billingProfile ?: new TenantBillingProfile([
            'tenant_id' => $tenant->id,
        ]);

        $profile->fill($data);
        $profile->tenant_id = $tenant->id;
        $profile->save();

        return redirect()
            ->back()
            ->with('status', 'Perfil de billing actualizado correctamente.');
    }
}
