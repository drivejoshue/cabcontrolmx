<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
   use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DispatchController extends Controller
{


public function index()
{
    $user = auth()->user();
    abort_unless($user && $user->tenant_id, 403, 'Usuario sin tenant');

    $tenant = \App\Models\Tenant::find($user->tenant_id);
    abort_unless($tenant, 404, 'Tenant no encontrado');

    // OJO: usa tus columnas reales
    $ccTenant = [
        'id'   => (int)$tenant->id,
        'name' => (string)$tenant->name,
        'map'  => [
            'lat'       => $tenant->latitud !== null ? (float)$tenant->latitud : null,
            'lng'       => $tenant->longitud !== null ? (float)$tenant->longitud : null,
            'zoom'      => $tenant->map_zoom ? (int)$tenant->map_zoom : 14,
            'radius_km' => $tenant->coverage_radius_km ? (float)$tenant->coverage_radius_km : 8,
        ],
        'map_icons' => [
            'origin' => '/images/origen.png',
            'dest'   => '/images/destino.png',
            'stand'  => '/images/marker-parqueo5.png',
            'stop'   => '/images/stopride.png',
        ],
    ];

    \Log::info('TENANT(view)', ['id'=>$ccTenant['id'], 'name'=>$ccTenant['name'], 'map'=>$ccTenant['map']]);

    return view('admin.dispatch', compact('tenant','ccTenant'));
}


}
