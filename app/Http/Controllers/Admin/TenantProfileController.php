<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use App\Models\TenantDocument;
use App\Models\BillingPlan;
use App\Models\ProviderProfile;



class TenantProfileController extends Controller
{

   


public function edit()
{
     $provider = ProviderProfile::activeOne();
     
    $tenant = Tenant::findOrFail(auth()->user()->tenant_id);

    $docs = TenantDocument::where('tenant_id', $tenant->id)
        ->get()
        ->keyBy('type'); // id_official, proof_address, tax_certificate

 $p = $tenant->billingProfile;
$billingPlan = null;

if ($p && !empty($p->plan_code)) {
    $billingPlan = BillingPlan::where('code', $p->plan_code)->first();
}

return view('admin.tenant.edit', [
    'tenant' => $tenant,
    'docs' => $docs,
    'billingPlan' => $billingPlan,
    'provider' => $provider,
]);

}


    public function update(Request $request)
    {
        $tenant = Tenant::findOrFail(auth()->user()->tenant_id);

        $data = $request->validate([
            'name' => ['required','string','max:150'],
            'notification_email' => ['nullable','email','max:190'],
            'public_phone' => ['nullable','string','max:30'],
            'public_city' => ['nullable','string','max:120'],
        ]);

        // fallback: si no hay notification_email, usamos el email del usuario admin
        if (empty($data['notification_email'])) {
            $data['notification_email'] = auth()->user()->email;
        }

        $tenant->fill($data);
        $tenant->save();

        return back()->with('status', 'Central actualizada correctamente.');
    }
}
