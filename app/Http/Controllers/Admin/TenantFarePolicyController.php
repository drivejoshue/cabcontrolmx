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
    'round_to'         => $global->round_to,

    'night_multiplier' => $global->night_multiplier,
    'min_total'        => $global->min_total,

    'stop_fee'         => $global->stop_fee ?? 0.00,

    // Slider de puja en Passenger
    'slider_min_pct'   => $global->slider_min_pct ?? 0.80,
    'slider_max_pct'   => $global->slider_max_pct ?? 1.20,

    'extras'           => $global->extras ?? [],
    'active_from'      => null,
    'active_to'        => null,
] : [
    'mode'             => 'meter',
    'base_fee'         => 35,
    'per_km'           => 12,
    'per_min'          => 2,

    'night_start_hour' => 22,
    'night_end_hour'   => 6,

    'round_mode'       => 'step',
    'round_decimals'   => 0,
    'round_step'       => 1.00,
    'round_to'         => 1.00,

    'night_multiplier' => 1.20,
    'min_total'        => 50,

    'stop_fee'         => 20.00,

    'slider_min_pct'   => 0.80,
    'slider_max_pct'   => 1.20,

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

        // Campos clave: nunca 0
        'base_fee'         => 'required|numeric|min:1|max:9999',
        'per_km'           => 'required|numeric|min:0.10|max:9999',
        'per_min'          => 'required|numeric|min:0.10|max:9999',
        'min_total'        => 'required|numeric|min:1|max:9999',

        // Noche
        'night_start_hour' => 'required|integer|min:0|max:23',
        'night_end_hour'   => 'required|integer|min:0|max:23',
        'night_multiplier' => 'required|numeric|min:1.00|max:3.00',

        // Redondeo (obligar consistencia)
        'round_mode'       => 'required|in:decimals,step',
        'round_decimals'   => 'nullable|integer|min:0|max:4',
        'round_step'       => 'nullable|numeric|min:0.50|max:50',
        'round_to'         => 'required|numeric|min:1|max:50',

        // Vigencia
        'active_from'      => 'nullable|date',
        'active_to'        => 'nullable|date|after_or_equal:active_from',

        // Extras
        'extras'           => 'nullable|json',

        'stop_fee'         => 'required|numeric|min:0|max:9999',

'slider_min_pct'   => 'required|numeric|min:0.50|max:1.00',
'slider_max_pct'   => 'required|numeric|min:1.00|max:1.50',

    ]);
// Slider: asegurar orden lógico
$minPct = (float)($data['slider_min_pct'] ?? 0.80);
$maxPct = (float)($data['slider_max_pct'] ?? 1.20);
if ($maxPct < $minPct) {
    // Corrige en caliente en vez de rebotar, para UX
    $data['slider_max_pct'] = $minPct;
}

    // Regla: según round_mode, exige el campo correspondiente
    if ($data['round_mode'] === 'step') {
        if (!isset($data['round_step']) || (float)$data['round_step'] < 0.50) {
            return back()->withErrors(['round_step' => 'El paso debe ser mínimo 0.50.'])->withInput();
        }
        $data['round_decimals'] = null;
    } else { // decimals
        if (!isset($data['round_decimals'])) {
            return back()->withErrors(['round_decimals' => 'Indica cuántos decimales usar.'])->withInput();
        }
        $data['round_step'] = null;
    }

    // Normaliza extras a array
    if (isset($data['extras']) && is_string($data['extras'])) {
        $data['extras'] = json_decode($data['extras'], true) ?: [];
    }

    $policy->update($data);

    return redirect()->route('admin.fare_policies.index')
        ->with('ok', 'Política de tarifa actualizada');
}

}
