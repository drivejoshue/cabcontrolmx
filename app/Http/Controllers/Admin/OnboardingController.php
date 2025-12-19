<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class OnboardingController extends Controller
{
    public function index()
    {
        $tenant = Tenant::findOrFail(auth()->user()->tenant_id);

        return view('admin.onboarding.index', [
            'tenant'          => $tenant,
            'opsUrl'          => route('admin.dispatch'), // ajusta si tu nombre es otro
            'driverAppUrl'    => config('app.driver_app_url'),
            'passengerAppUrl' => config('app.passenger_app_url'),
        ]);
    }

   public function saveLocation(Request $request)
{
    $tenant = Tenant::findOrFail(auth()->user()->tenant_id);

    $data = $request->validate([
        'latitud'            => ['required','numeric','between:-90,90'],
        'longitud'           => ['required','numeric','between:-180,180'],
        'coverage_radius_km' => ['required','numeric','min:1','max:200'],
        'public_city'        => ['nullable','string','max:190'],
    ]);

    $tenant->fill($data);

    // Si ya es “ready”, permitimos cambios? tú dijiste que no es necesario botón,
    // así que normalmente sí permitiría ajustes mientras estás en onboarding.
    // Si quieres bloquear, aquí sería la validación.
    if (empty($tenant->onboarding_done_at)) {
        $tenant->onboarding_done_at = now();
    }

    $tenant->save();

    return back()->with('status', 'Ubicación guardada. Ya puedes continuar al panel.');
}


public function complete(Request $request)
{
    return redirect()->route('admin.dashboard');
}


public function cities(Request $request)
{
    $q = trim((string)$request->query('q',''));
    if ($q === '') return response()->json(['items'=>[]]);

    // tabla: mx  (la que importaste)
    // OJO: ajusta nombres reales después de renombrar columnas (abajo te doy SQL).
    $rows = \DB::table('mx_cities_simplemaps')
        ->where('city', 'like', "%{$q}%")
        ->orWhere('state', 'like', "%{$q}%")
        ->limit(30)
        ->get(['city','state','lat','lng']);

    $items = $rows->map(function($r){
        return [
            'label' => trim($r->city . ', ' . $r->state),
            'city'  => (string)$r->city,
            'state' => (string)$r->state,
            'lat'   => (float)$r->lat,
            'lng'   => (float)$r->lng,
        ];
    });

    return response()->json(['items' => $items]);
}



}
