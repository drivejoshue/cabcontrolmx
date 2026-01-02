<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TenantFarePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantFarePolicyController extends Controller
{   

    private function tenantIdFrom(Request $request): int
    {
        $tid = $request->header('X-Tenant-ID')
            ?? optional(Auth::user())->tenant_id;

        if (!$tid) {
            abort(403, 'Usuario sin tenant asignado');
        }

        return (int) $tid;
    }


    // Lista (muestra SOLO la del tenant actual, si existe)
    public function index(Request $request)
    {
        $tenantId = $this->tenantIdFrom($request);
        $policy   = TenantFarePolicy::where('tenant_id', $tenantId)->orderByDesc('id')->first();

        return view('admin.fare_policies.index', [
            'tenantId' => $tenantId,
            'policy'   => $policy,
        ]);
    }

    // Edita (si no existe, la crea con defaults seguros para evitar múltiples)
   public function edit(Request $request)
{
    $tenantId = $this->tenantIdFrom($request);

    $policy = TenantFarePolicy::where('tenant_id', $tenantId)->orderByDesc('id')->first();

    if (!$policy) {
        // 1) intenta clonar desde tenant 100
        $global = TenantFarePolicy::where('tenant_id', 100)->orderByDesc('id')->first();

        $seed = $global ? [
            'mode'             => $global->mode,
            'base_fee'         => $global->base_fee,
            'per_km'           => $global->per_km,
            'per_min'          => $global->per_min,
            'night_start_hour' => $global->night_start_hour,
            'night_end_hour'   => $global->night_end_hour,
            'round_mode'       => $global->round_mode,
            'round_decimals'   => $global->round_decimals,
            'round_step'       => $global->round_step,
            'night_multiplier' => $global->night_multiplier,
            'round_to'         => $global->round_to,
            'min_total'        => $global->min_total,
            'extras'           => $global->extras ?? [],
            'active_from'      => null,
            'active_to'        => null,
        ] : [
            // 2) fallback “demo”
            'mode'             => 'meter',
            'base_fee'         => 35,
            'per_km'           => 12,
            'per_min'          => 2,
            'night_start_hour' => 22,
            'night_end_hour'   => 6,
            'round_mode'       => 'step',
            'round_decimals'   => 0,
            'round_step'       => 1.00,
            'night_multiplier' => 1.20,
            'round_to'         => 1.00,
            'min_total'        => 50,
            'extras'           => [],
            'active_from'      => null,
            'active_to'        => null,
        ];

        $policy = TenantFarePolicy::create(['tenant_id' => $tenantId] + $seed);
    }

    return view('admin.fare_policies.form', [
        'tenantId' => $tenantId,
        'policy'   => $policy,
    ]);
}


    // Actualiza (sin crear adicionales)
    public function update(Request $request)
    {
        $tenantId = $this->tenantIdFrom($request);
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
