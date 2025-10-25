<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TenantFarePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantFarePolicyController extends Controller
{
    // Lista (muestra SOLO la del tenant actual, si existe)
    public function index(Request $request)
    {
        $tenantId = (int)($request->header('X-Tenant-ID') ?? Auth::user()->tenant_id ?? 1);
        $policy   = TenantFarePolicy::where('tenant_id', $tenantId)->orderByDesc('id')->first();

        return view('admin.fare_policies.index', [
            'tenantId' => $tenantId,
            'policy'   => $policy,
        ]);
    }

    // Edita (si no existe, la crea con defaults seguros para evitar múltiples)
    public function edit(Request $request)
    {
        $tenantId = (int)($request->header('X-Tenant-ID') ?? Auth::user()->tenant_id ?? 1);

        // Garantiza ÚNICA por tenant (o la última vigente). Si no hay, crea una base.
        $policy = TenantFarePolicy::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'mode'             => 'meter',
                'base_fee'         => 0,
                'per_km'           => 0,
                'per_min'          => 0,
                'night_start_hour' => 22,
                'night_end_hour'   => 6,
                'round_mode'       => 'step',
                'round_decimals'   => 0,
                'round_step'       => 1.00,
                'night_multiplier' => 1.00,
                'round_to'         => 1.00,
                'min_total'        => 0,
                'extras'           => [],
            ]
        );

        return view('admin.fare_policies.form', [
            'tenantId' => $tenantId,
            'policy'   => $policy,
        ]);
    }

    // Actualiza (sin crear adicionales)
    public function update(Request $request)
    {
        $tenantId = (int)($request->header('X-Tenant-ID') ?? Auth::user()->tenant_id ?? 1);
        $policy   = TenantFarePolicy::where('tenant_id', $tenantId)->orderByDesc('id')->firstOrFail();

        $data = $request->validate([
            'mode'             => 'required|in:meter',
            'base_fee'         => 'required|numeric|min:0',
            'per_km'           => 'required|numeric|min:0',
            'per_min'          => 'required|numeric|min:0',
            'night_start_hour' => 'nullable|integer|min:0|max:23',
            'night_end_hour'   => 'nullable|integer|min:0|max:23',
            'round_mode'       => 'required|in:decimals,step',
            'round_decimals'   => 'nullable|integer|min:0|max:4',
            'round_step'       => 'nullable|numeric|min:0',
            'night_multiplier' => 'nullable|numeric|min:0',
            'round_to'         => 'nullable|numeric|min:0',
            'min_total'        => 'nullable|numeric|min:0',
            'active_from'      => 'nullable|date',
            'active_to'        => 'nullable|date|after_or_equal:active_from',
            'extras'           => 'nullable|json',
        ]);

        // Normaliza extras a array
        if (isset($data['extras']) && is_string($data['extras'])) {
            $data['extras'] = json_decode($data['extras'], true) ?: [];
        }

        $policy->update($data);

        return redirect()->route('admin.fare_policies.index')
            ->with('ok', 'Política de tarifa actualizada');
    }
}
